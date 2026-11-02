<?php

declare(strict_types=1);

// Local dev with `php -S`: let the built-in server serve the real files in public/.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../src/Logbook.php';
require __DIR__ . '/../src/Platform.php';

$book = Logbook::fromEnv();
$who = Platform::who();
$read = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true);
$write = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Logbook-Instance: ' . $who['instance']);  // which instance answered, handy with curl

match (true) {
    $read && $path === '/health' => json(['status' => 'ok']),  // never touches the bucket
    $read && $path === '/health/deep' => deepHealth($book, $who),
    $read && $path === '/api/hail' => hail($book, $who),
    $read && str_starts_with($path, '/sketches/') => sketch($book, substr($path, 10)),
    $read && $path === '/' => page($book, $who),
    $write && $path === '/entries' => sealEntry($book, $who),
    default => json(['error' => 'Not found'], 404),
};

/** The logbook page. */
function page(Logbook $book, array $who, ?string $error = null, array $old = [], int $status = 200): void
{
    if (!$book->isSeeded() && is_dir($book->root)) {
        $book->seed(require __DIR__ . '/../seed/entries.php', __DIR__ . '/../seed', $who);  // only if the boot hook did not run
    }
    $log = $book->entries(8);
    $boot = $book->bootLog(10);
    $hold = $book->inventory();
    $mount = $book->mount();
    $platform = Platform::panel();
    $sealed = preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $_GET['sealed'] ?? '') ? $_GET['sealed'] : null;
    $ops = $book->ops;

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/../templates/page.php';
}

/** POST /entries: validate, store the sketch, write the entry file. */
function sealEntry(Logbook $book, array $who): void
{
    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        json(['error' => 'Entries can only be written from this page.'], 403);
        return;
    }

    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'The sketch weighs more than 2 MB. Attach a smaller image.';  // body above post_max_size
    } else {
        [$fields, $sketch, $error] = Logbook::validate($_POST, $_FILES['sketch'] ?? null);
        if ($error === null) {
            try {
                $entry = $book->seal($fields, $sketch, $who);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($error !== null) {
        $wantsJson ? json(['error' => $error], 422) : page($book, $who, $error, $_POST, 422);
        return;
    }
    if ($wantsJson) {
        json(['id' => $entry['id'], 'instance' => $who['instance'], 'ops' => $book->ops], 201);
        return;
    }
    header('Location: /?sealed=' . $entry['id'] . '#entry-' . $entry['id'], true, 303);
}

/** GET /sketches/<file>: images live on the bucket, outside the web root. */
function sketch(Logbook $book, string $name): void
{
    $file = $book->sketchPath($name);
    if ($file === null) {
        json(['error' => 'No such sketch in the hold'], 404);
        return;
    }
    $types = array_flip(Logbook::IMAGE_TYPES);
    header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=86400, immutable');
    readfile($file);
}

/** GET /api/hail: each instance reports what it reads on the shared bucket. */
function hail(Logbook $book, array $who): void
{
    $log = $book->entries(1);
    $last = $log['entries'][0] ?? null;
    header('Cache-Control: no-store');
    // The load balancer keeps a browser connection on one instance; closing the
    // backend connection lets the next hail be routed again, maybe elsewhere.
    header('Connection: close');
    json([
        'instance' => $who['instance'],
        'name' => $who['name'],
        'entries' => $log['total'],
        'last' => $last ? ['id' => $last['id'], 'hand' => $last['hand'], 'at' => $last['written_at']] : null,
        'boots' => $book->bootLog(0)['total'],
        'ms' => round(array_sum(array_column($book->ops, 'ms')), 2),
    ]);
}

/** GET /health/deep: the bucket is mounted, writable and readable. */
function deepHealth(Logbook $book, array $who): void
{
    $mount = $book->mount();
    $checks = ['folder' => $book->label . '/', 'mounted' => $mount !== null, 'fs_type' => $mount['type'] ?? null];
    try {
        $checks += $book->probe($who['instance']);
    } catch (Throwable $e) {
        $checks += ['ok' => false, 'error' => $e->getMessage()];
    }
    // On Clever Cloud the folder must be a real mount, otherwise files die with the instance.
    $healthy = $checks['ok'] && ($checks['mounted'] || !$book->isBucket);
    header('Cache-Control: no-store');
    json(['status' => $healthy ? 'ok' : 'failing', 'instance' => $who['instance']] + $checks, $healthy ? 200 : 503);
}

function json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}

function h(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** CSS class giving each instance its own colour of sealing wax. */
function wax(string $instance): string
{
    return ctype_digit($instance) ? 'wax--' . ((int) $instance % 5) : 'wax--local';
}

/** deployment_0a1b2c3d-4e5f-… → 0a1b2c3d */
function shortId(string $id): string
{
    return substr(preg_replace('/^deployment_/', '', $id) ?? $id, 0, 8);
}

/** "1 Nov, 21:40" (UTC, like every Clever Cloud instance). */
function logDate(string $iso, bool $twoLines = false): string
{
    $time = strtotime($iso) ?: 0;

    return gmdate($twoLines ? "j M\nH:i" : 'j M, H:i', $time);
}
