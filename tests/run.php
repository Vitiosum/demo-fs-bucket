<?php

/*
 * The whole test suite, no dependency: php tests/run.php
 * 1. The logbook class on a temporary folder.
 * 2. The real HTTP routes, through PHP's built-in server.
 */

declare(strict_types=1);

require __DIR__ . '/../src/Logbook.php';
require __DIR__ . '/../src/Platform.php';

$failures = 0;
$passed = 0;
function check(string $name, bool $ok): void
{
    global $failures, $passed;
    $ok ? $passed++ : $failures++;
    echo ($ok ? '  ok    ' : '  FAIL  ') . $name . "\n";
}

function tempDir(): string
{
    $dir = sys_get_temp_dir() . '/logbook-test-' . bin2hex(random_bytes(4));
    mkdir($dir);
    return $dir;
}

function pngFile(int $w = 40, int $h = 30): string
{
    $file = tempnam(sys_get_temp_dir(), 'png');
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 60));
    imagepng($img, $file);
    return $file;
}

$who = ['instance' => '0', 'name' => 'Test watch', 'deployment' => 'dep-a', 'commit' => 'abc1234'];

echo "Logbook\n";

[$fields, $sketch, $error] = Logbook::validate(['remarks' => '   '], null);
check('empty remarks are refused', $error !== null);

[$fields, , $error] = Logbook::validate(['remarks' => str_repeat('a', 281)], null);
check('remarks over 280 characters are refused', $error !== null);

[$fields, , $error] = Logbook::validate(['remarks' => "Land ho\x07!", 'wind' => 'XX', 'weather' => 'Tornado'], null);
check('control characters are stripped', $fields['remarks'] === 'Land ho!');
check('unknown wind and weather fall back', $fields['wind'] === '' && $fields['weather'] === 'Fair' && $error === null);
check('an anonymous hand gets a default name', $fields['hand'] === 'A passenger');

check('file names are sanitised', Logbook::cleanName('../../etc/passwd') === 'passwd' && Logbook::cleanName('my photo (1).PNG') === 'my-photo-1-.PNG');

$png = pngFile();
[$info, $error] = Logbook::checkSketch(['error' => UPLOAD_ERR_OK, 'tmp_name' => $png, 'name' => 'x.png', 'size' => filesize($png)]);
check('a real PNG is accepted', $error === null && $info['ext'] === 'png' && $info['width'] === 40);

$fake = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($fake, '<?php echo "not an image";');
[, $error] = Logbook::checkSketch(['error' => UPLOAD_ERR_OK, 'tmp_name' => $fake, 'name' => 'evil.png', 'size' => filesize($fake)]);
check('a PHP file named .png is refused', $error !== null);

[, $error] = Logbook::checkSketch(['error' => UPLOAD_ERR_OK, 'tmp_name' => $png, 'name' => 'x.png', 'size' => 3 * 1024 * 1024]);
check('a sketch over 2 MB is refused', $error !== null && str_contains($error, '2 MB'));

[, $error] = Logbook::checkSketch(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'name' => 'x.png', 'size' => 0]);
check('PHP upload size errors read as "over 2 MB"', $error !== null && str_contains($error, '2 MB'));

$dir = tempDir();
$book = new Logbook($dir, 'data');
[$fields] = Logbook::validate(['remarks' => 'Sighted land', 'hand' => 'Ada', 'wind' => 'NE'], null);
$entry = $book->seal($fields, $info, $who, null, false);
$log = $book->entries();
check('an entry is written and read back', $log['total'] === 1 && $log['entries'][0]['remarks'] === 'Sighted land');
check('the sketch is stored next to it', $book->sketchPath($entry['sketch']['file']) !== null);
check('no temporary file is left behind', glob("$dir/entries/.tmp-*") === []);
check('sketch names outside the pattern are refused', $book->sketchPath('../boot.log') === null);

$book->recordBoot($who);
$book->recordBoot(['deployment' => 'dep-b'] + $who);
$book->recordBoot(['instance' => '1', 'deployment' => 'dep-b'] + $who);
$boot = $book->bootLog();
check('boot lines are appended', $boot['total'] === 3 && $boot['lines'][2]['instance'] === '1');
check('deployments are counted (storms)', $boot['deployments'] === 2);

$seeds = require __DIR__ . '/../seed/entries.php';
check('seeding runs once', $book->seed($seeds, __DIR__ . '/../seed', $who) === true && $book->seed($seeds, __DIR__ . '/../seed', $who) === false);
$seeded = $book->entries(10)['entries'];
check('seed entries are readable', count($seeded) === 1 + count($seeds));
check('seed entries are flagged and signed by the real instance', count(array_filter($seeded, static fn (array $e): bool => $e['seed'] === true && $e['instance'] === '0')) === count($seeds));

$hold = $book->inventory();
check('the inventory sums every file', $hold['files'] > 5 && $hold['bytes'] > 80000 && isset($hold['groups']['entries/']));
check('every call is traced', count($book->ops) > 5 && isset($book->ops[0]['call'], $book->ops[0]['ms']));
check('the probe writes, reads and deletes', $book->probe('0')['ok'] === true && glob("$dir/.health/*") === []);

$missing = new Logbook("$dir-missing", 'data', true);
try {
    $missing->seal($fields, null, $who);
    check('an unmounted bucket refuses writes', false);
} catch (RuntimeException) {
    check('an unmounted bucket refuses writes', true);
}
check('an unmounted bucket folder is never created', !is_dir("$dir-missing"));
check('the bucket host is masked', Logbook::mask('bucket-3f2a9c1e-1111-2222-3333-444455556666-fsbucket.internal:/data/orga_00/00/00/orga_example/bucket_9b8a7c6d-0000-0000-0000-000000000000') === 'bucket-3f2a…-fsbucket.internal:/…/bucket_9b8a…');

echo "\nHTTP routes\n";

$port = null;
for ($p = 8144; $p < 8199; $p++) {
    $probe = @fsockopen('127.0.0.1', $p, $errno, $errstr, 0.2);
    if ($probe === false) {
        $port = $p;
        break;
    }
    fclose($probe);
}
$data = tempDir();
$env = ['LOGBOOK_DATA_DIR' => $data, 'PATH' => getenv('PATH')];
$root = dirname(__DIR__);
exec('LOGBOOK_DATA_DIR=' . escapeshellarg($data) . ' php ' . escapeshellarg("$root/bin/boot.php"));
$server = proc_open(
    ['php', '-d', 'upload_max_filesize=3M', '-d', 'post_max_size=4M', '-S', "127.0.0.1:$port", '-t', "$root/public", "$root/public/index.php"],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    $env,
);
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
    usleep(100_000);
}

function http(string $method, string $path, array $options = []): array
{
    global $port;
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true] + $options);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return [$status, substr($raw, 0, $size), substr($raw, $size)];
}

try {
    [$status, , $body] = http('GET', '/health');
    check('GET /health is 200', $status === 200 && str_contains($body, '"ok"'));
    [$status, , $body] = http('GET', '/health/deep');
    check('GET /health/deep is 200 and probes the folder', $status === 200 && str_contains($body, '"ok":true'));
    [$status, $headers, $body] = http('GET', '/');
    check('GET / shows the seeded log', $status === 200 && str_contains($body, 'The Lookout') && str_contains($body, 'The watch roll'));
    check('responses say which instance answered', str_contains($headers, 'X-Logbook-Instance: local'));
    check('a strict CSP is sent', str_contains($headers, "script-src 'self'"));

    $form = ['remarks' => 'Whales off the port bow', 'hand' => 'Ishmael', 'wind' => 'SW', 'weather' => 'Gale', 'sketch' => new CURLFile($png, 'image/png', 'whale <1>.png')];
    [$status, $headers] = http('POST', '/entries', [CURLOPT_POSTFIELDS => $form]);
    check('POST /entries redirects to the sealed entry', $status === 303 && str_contains($headers, 'Location: /?sealed='));

    $form['remarks'] = 'Second entry, JSON';
    $form['sketch'] = new CURLFile($png, 'image/png', 'b.png');
    [$status, , $body] = http('POST', '/entries', [CURLOPT_POSTFIELDS => $form, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $json = json_decode($body, true);
    check('POST /entries answers JSON with the write trace', $status === 201 && in_array('rename()', array_column($json['ops'] ?? [], 'call'), true));

    [$status, , $body] = http('GET', '/');
    check('the new entry is on the page', str_contains($body, 'Whales off the port bow') && str_contains($body, 'Ishmael'));
    preg_match('#/sketches/([0-9a-f-]+\.png)#', $body, $m);
    [$status, $headers] = http('GET', '/sketches/' . ($m[1] ?? 'none.png'));
    check('the sketch is served from the bucket', $status === 200 && str_contains($headers, 'Content-Type: image/png'));

    [$status] = http('GET', '/sketches/..%2F..%2Fboot.log');
    check('path tricks on /sketches are refused', $status === 404);

    [$status, , $body] = http('POST', '/entries', [CURLOPT_POSTFIELDS => ['remarks' => 'x', 'sketch' => new CURLFile($fake, 'image/png', 'evil.png')], CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    check('a fake image is refused with 422', $status === 422 && str_contains($body, 'PNG, JPEG'));

    [$status] = http('POST', '/entries', [CURLOPT_POSTFIELDS => ['remarks' => 'x'], CURLOPT_HTTPHEADER => ['Origin: https://evil.example']]);
    check('a cross-site post is refused', $status === 403);

    [$status, , $body] = http('GET', '/api/hail');
    $hail = json_decode($body, true);
    check('GET /api/hail reports the shared log', $status === 200 && $hail['entries'] === 5 && $hail['boots'] === 1);

    [$status] = http('GET', '/nowhere');
    check('unknown paths are 404', $status === 404);
} finally {
    proc_terminate($server);
    proc_close($server);
}

echo "\n$passed passed, $failures failed\n";
exit($failures === 0 ? 0 : 1);
