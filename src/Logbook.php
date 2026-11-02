<?php

declare(strict_types=1);

/**
 * The ship's logbook: one folder on the FS Bucket, and nothing else.
 *
 *   data/boot.log             one line per instance start (bin/boot.php)
 *   data/entries/<id>.json    one file per log entry
 *   data/sketches/<id>.<ext>  the image pasted in an entry, if any
 *
 * Everything here is plain PHP file I/O (fopen, rename, scandir…): no SDK,
 * no API. Each call is recorded in $ops so the page can show, live, what the
 * FS Bucket did to answer the request.
 */
final class Logbook
{
    public const FREE_TIER = 100 * 1024 * 1024;  // FS Bucket plan "s": 100 MB free
    public const HOLD_FULL = 90 * 1024 * 1024;   // above this, sketches are refused
    public const MAX_SKETCH = 2 * 1024 * 1024;   // 2 MB per image
    public const MAX_REMARKS = 280;
    public const MAX_HAND = 40;
    public const WINDS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    public const WEATHER = ['Fair', 'Fresh breeze', 'Squall', 'Fog', 'Gale', 'Becalmed'];
    public const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    private const ENTRY_FILE = '/^\d{8}-\d{6}-[0-9a-f]{6}\.json$/';
    private const SKETCH_FILE = '/^\d{8}-\d{6}-[0-9a-f]{6}\.(png|jpg|gif|webp)$/';

    /** @var list<array{call: string, path: string, detail: string, ms: float}> */
    public array $ops = [];

    public function __construct(
        public readonly string $root,          // absolute path of the folder
        public readonly string $label,         // how that folder is named on the page
        public readonly bool $isBucket = false, // true when CC_FS_BUCKET mounts it
    ) {
    }

    public static function fromEnv(): self
    {
        $appHome = rtrim(getenv('APP_HOME') ?: dirname(__DIR__), '/');

        // On Clever Cloud: CC_FS_BUCKET="/data:<bucket-host>". The folder is
        // RELATIVE to the app: the bucket appears in $APP_HOME/data, not in /data.
        $bucket = getenv('CC_FS_BUCKET');
        if ($bucket) {
            $folder = trim(explode(':', $bucket, 2)[0], '/');
            return new self("$appHome/$folder", $folder, true);
        }

        $local = getenv('LOGBOOK_DATA_DIR');  // local runs and tests
        return new self($local ? rtrim($local, '/') : "$appHome/data", 'data');
    }

    // ------------------------------------------------------------------ read

    /** @return array{entries: list<array<string, mixed>>, total: int} newest first */
    public function entries(int $limit = 8): array
    {
        $dir = $this->path('entries');
        $t = hrtime(true);
        $names = is_dir($dir) ? array_values(preg_grep(self::ENTRY_FILE, scandir($dir, SCANDIR_SORT_DESCENDING) ?: [])) : [];
        $this->trace('scandir()', 'entries/', count($names) . ' entries', $t);

        $entries = [];
        foreach (array_slice($names, 0, $limit) as $name) {
            $t = hrtime(true);
            $raw = @file_get_contents("$dir/$name");
            $this->trace('file_get_contents()', "entries/$name", $raw === false ? 'unreadable' : self::bytes(strlen($raw)), $t);
            $entry = $raw === false ? null : json_decode($raw, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return ['entries' => $entries, 'total' => count($names)];
    }

    /** @return array{lines: list<array<string, string>>, total: int, deployments: int} last lines of boot.log */
    public function bootLog(int $limit = 10): array
    {
        $t = hrtime(true);
        $raw = @file_get_contents($this->path('boot.log'));
        $rows = $raw === false ? [] : array_values(array_filter(explode("\n", $raw)));
        $this->trace('file_get_contents()', 'boot.log', $raw === false ? 'not written yet' : count($rows) . (count($rows) === 1 ? ' line, ' : ' lines, ') . self::bytes(strlen($raw)), $t);

        $lines = array_map(static function (string $row): array {
            $f = explode("\t", $row) + ['', '', '', '', ''];
            return [
                'at' => $f[0],
                'instance' => preg_replace('/^instance /', '', $f[1]) ?? '',
                'name' => $f[2],
                'deployment' => preg_replace('/^deployment /', '', $f[3]) ?? '',
                'commit' => preg_replace('/^commit /', '', $f[4]) ?? '',
            ];
        }, $rows);

        return [
            'lines' => array_slice($lines, -$limit),
            'total' => count($lines),
            'deployments' => count(array_unique(array_column($lines, 'deployment'))),
        ];
    }

    /** Every file in the hold, grouped by folder, with sizes and dates. */
    public function inventory(): array
    {
        $t = hrtime(true);
        $groups = [];
        $total = 0;
        $count = 0;
        if (is_dir($this->root)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $rel = substr($file->getPathname(), strlen($this->root) + 1);
                $folder = str_contains($rel, '/') ? dirname($rel) . '/' : '';
                $groups[$folder]['files'][] = ['name' => basename($rel), 'bytes' => $file->getSize(), 'mtime' => $file->getMTime()];
                $groups[$folder]['bytes'] = ($groups[$folder]['bytes'] ?? 0) + $file->getSize();
                $total += $file->getSize();
                $count++;
            }
        }
        foreach ($groups as &$group) {
            usort($group['files'], static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        }
        unset($group);
        ksort($groups);
        $this->trace('RecursiveDirectoryIterator', '', "$count files, " . self::bytes($total), $t);

        return ['groups' => $groups, 'bytes' => $total, 'files' => $count, 'share' => $total / self::FREE_TIER];
    }

    /** What is mounted on the logbook folder, from /proc/self/mountinfo (Linux only). */
    public function mount(): ?array
    {
        $info = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES);
        if (!$info) {
            return null;
        }
        $target = realpath($this->root) ?: $this->root;
        $found = null;
        foreach ($info as $line) {
            [$left, $right] = explode(' - ', $line, 2) + [1 => ''];
            if ((explode(' ', $left)[4] ?? '') === $target) {
                $r = explode(' ', $right);
                $found = ['type' => $r[0], 'source' => self::mask($r[1] ?? '')];
            }
        }

        return $found;
    }

    /** Absolute path of a stored sketch, or null if the name is not one of ours. */
    public function sketchPath(string $name): ?string
    {
        $path = $this->path("sketches/$name");

        return preg_match(self::SKETCH_FILE, $name) && is_file($path) ? $path : null;
    }

    // ----------------------------------------------------------------- write

    /**
     * Checks a visitor's entry. Returns [clean fields, sketch info, error].
     *
     * @param array<string, mixed>      $post
     * @param array<string, mixed>|null $file one item of $_FILES
     */
    public static function validate(array $post, ?array $file): array
    {
        $remarks = self::cleanText($post['remarks'] ?? '');
        $hand = self::cleanText($post['hand'] ?? '', false);
        $wind = in_array($post['wind'] ?? '', self::WINDS, true) ? $post['wind'] : '';
        $weather = in_array($post['weather'] ?? '', self::WEATHER, true) ? $post['weather'] : 'Fair';
        $fields = ['remarks' => $remarks, 'hand' => $hand ?: 'A passenger', 'wind' => $wind, 'weather' => $weather];

        $error = match (true) {
            $remarks === '' => 'Write a few words in the remarks before sealing the entry.',
            mb_strlen($remarks) > self::MAX_REMARKS => 'Remarks are limited to ' . self::MAX_REMARKS . ' characters.',
            mb_strlen($hand) > self::MAX_HAND => 'Your name is limited to ' . self::MAX_HAND . ' characters.',
            default => null,
        };
        [$sketch, $sketchError] = self::checkSketch($file);

        return [$fields, $sketch, $error ?? $sketchError];
    }

    /** @return array{0: ?array<string, mixed>, 1: ?string} [sketch info, error] */
    public static function checkSketch(?array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > self::MAX_SKETCH) {
            return [null, 'The sketch weighs more than 2 MB. Attach a smaller image.'];
        }
        if ($error !== UPLOAD_ERR_OK || !is_file((string) $file['tmp_name'])) {
            return [null, "The sketch did not come aboard (upload error $error). Try again."];
        }
        $tmp = (string) $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $size = @getimagesize($tmp);
        if (!isset(self::IMAGE_TYPES[$mime]) || $size === false || $size['mime'] !== $mime) {
            return [null, 'Only PNG, JPEG, GIF or WebP images can be pasted in the log.'];
        }

        return [[
            'tmp' => $tmp,
            'ext' => self::IMAGE_TYPES[$mime],
            'original' => self::cleanName((string) ($file['name'] ?? '')),
            'bytes' => (int) filesize($tmp),
            'width' => $size[0],
            'height' => $size[1],
        ], null];
    }

    /**
     * Writes one entry: the sketch first, then the JSON file, atomically.
     *
     * @param array<string, string>      $fields from validate()
     * @param array<string, mixed>|null  $sketch from validate()
     * @param array<string, string>      $who    Platform::who()
     */
    public function seal(array $fields, ?array $sketch, array $who, ?int $at = null, bool $uploaded = true, bool $seed = false): array
    {
        $this->ensureRoot();
        $at ??= time();
        $id = gmdate('Ymd-His', $at) . '-' . bin2hex(random_bytes(3));
        $entry = ['id' => $id, 'written_at' => gmdate('Y-m-d\TH:i:s\Z', $at)] + $fields + [
            'instance' => $who['instance'],
            'instance_name' => $who['name'],
            'seed' => $seed,
            'sketch' => null,
        ];

        if ($sketch) {
            if ($this->inventory()['bytes'] > self::HOLD_FULL) {
                throw new RuntimeException('The hold is nearly full (90 MB of the 100 MB free tier): sketches are refused, words are still welcome.');
            }
            $this->ensureDir('sketches');
            $rel = "sketches/$id.{$sketch['ext']}";
            $t = hrtime(true);
            $ok = $uploaded ? move_uploaded_file($sketch['tmp'], $this->path($rel)) : copy($sketch['tmp'], $this->path($rel));
            $this->trace($uploaded ? 'move_uploaded_file()' : 'copy()', $rel, self::bytes($sketch['bytes']), $t);
            if (!$ok) {
                throw new RuntimeException('The sketch could not be stowed in the hold. Try again.');
            }
            $entry['sketch'] = ['file' => "$id.{$sketch['ext']}", 'original' => $sketch['original'], 'bytes' => $sketch['bytes'], 'width' => $sketch['width'], 'height' => $sketch['height']];
        }

        $this->ensureDir('entries');
        $this->writeAtomic("entries/$id.json", json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return $entry;
    }

    /** One line per instance start. LOCK_EX keeps two instances from interleaving. */
    public function recordBoot(array $who): string
    {
        $this->ensureRoot();
        $line = implode("\t", [
            gmdate('Y-m-d\TH:i:s\Z'),
            'instance ' . $who['instance'],
            $who['name'],
            'deployment ' . $who['deployment'],
            'commit ' . $who['commit'],
        ]) . "\n";
        $t = hrtime(true);
        file_put_contents($this->path('boot.log'), $line, FILE_APPEND | LOCK_EX);
        $this->trace('file_put_contents(FILE_APPEND | LOCK_EX)', 'boot.log', self::bytes(strlen($line)), $t);

        return $line;
    }

    /**
     * First start only: a few entries so the first screen is never empty.
     * fopen('x') is an exclusive create: if two instances boot at once, one wins.
     *
     * @param list<array<string, string>> $seeds see seed/entries.php
     * @param array<string, string>       $who   the instance writing them
     */
    public function seed(array $seeds, string $seedDir, array $who): bool
    {
        $this->ensureRoot();
        $t = hrtime(true);
        $marker = @fopen($this->path('.seeded'), 'x');
        $this->trace("fopen('x')", '.seeded', $marker ? 'first voyage: seeding' : 'already seeded', $t);
        if (!$marker) {
            return false;
        }
        fwrite($marker, gmdate('Y-m-d\TH:i:s\Z') . "\n");
        fclose($marker);

        $now = time();
        foreach ($seeds as $i => $seed) {
            $sketch = null;
            if (!empty($seed['sketch'])) {
                $file = "$seedDir/{$seed['sketch']}";
                [$sketch] = self::checkSketch(['error' => UPLOAD_ERR_OK, 'tmp_name' => $file, 'name' => $seed['sketch'], 'size' => filesize($file)]);
            }
            [$fields] = self::validate($seed, null);
            $at = $now - (count($seeds) - $i) * 3600;
            $this->seal($fields, $sketch, $who, $at, uploaded: false, seed: true);
        }

        return true;
    }

    public function isSeeded(): bool
    {
        return is_file($this->path('.seeded'));
    }

    /** Write, read back and delete a probe file. Used by /health/deep. */
    public function probe(string $instance): array
    {
        $this->ensureRoot();
        $this->ensureDir('.health');
        $rel = ".health/probe-$instance-" . bin2hex(random_bytes(3));
        $token = bin2hex(random_bytes(8));
        $t = hrtime(true);
        $written = file_put_contents($this->path($rel), $token);
        $read = file_get_contents($this->path($rel));
        unlink($this->path($rel));
        $this->trace('write + read + unlink', $rel, $written === 16 && $read === $token ? 'ok' : 'mismatch', $t);

        return ['ok' => $written === 16 && $read === $token, 'ms' => round((hrtime(true) - $t) / 1e6, 2)];
    }

    // --------------------------------------------------------------- helpers

    /** The mount point itself is never created here: an existing folder would hide the bucket. */
    private function ensureRoot(): void
    {
        if (is_dir($this->root)) {
            return;
        }
        if ($this->isBucket) {
            throw new RuntimeException("The FS Bucket is not mounted on {$this->label}/. Check CC_FS_BUCKET.");
        }
        mkdir($this->root, 0775, true);
    }

    private function ensureDir(string $rel): void
    {
        if (!is_dir($this->path($rel))) {
            $t = hrtime(true);
            @mkdir($this->path($rel), 0775);
            $this->trace('mkdir()', "$rel/", 'created', $t);
        }
    }

    /** Write to a temporary name, fsync, then rename: readers never see half a file. */
    private function writeAtomic(string $rel, string $bytes): void
    {
        $tmp = dirname($rel) . '/.tmp-' . bin2hex(random_bytes(4));
        $t = hrtime(true);
        $fh = fopen($this->path($tmp), 'xb');
        if ($fh === false) {
            throw new RuntimeException("Could not open {$this->label}/$tmp for writing.");
        }
        fwrite($fh, $bytes);
        fflush($fh);
        fsync($fh);
        fclose($fh);
        $this->trace('fopen + fwrite + fsync', $tmp, self::bytes(strlen($bytes)), $t);

        $t = hrtime(true);
        if (!rename($this->path($tmp), $this->path($rel))) {
            throw new RuntimeException("Could not rename {$this->label}/$tmp.");
        }
        $this->trace('rename()', $rel, 'atomic swap', $t);
    }

    private function path(string $rel): string
    {
        return $rel === '' ? $this->root : "{$this->root}/$rel";
    }

    private function trace(string $call, string $rel, string $detail, int $start): void
    {
        $this->ops[] = [
            'call' => $call,
            'path' => "{$this->label}/$rel",
            'detail' => $detail,
            'ms' => round((hrtime(true) - $start) / 1e6, 2),
        ];
    }

    private static function cleanText(mixed $value, bool $multiline = true): string
    {
        $text = is_string($value) ? str_replace("\r", '', $value) : '';
        $text = preg_replace($multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u', '', $text) ?? '';

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }

    /** Keeps a readable, harmless version of the uploaded file name (shown, never used as a path). */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '', '.-');

        return substr($name, 0, 60) ?: 'sketch';
    }

    /** "bucket-4adc…-fsbucket.internal:/…/bucket_4adc…": recognisable, not reusable. */
    public static function mask(string $source): string
    {
        [$host, $export] = explode(':', $source, 2) + [1 => ''];
        $host = preg_replace('/^(bucket-[0-9a-f]{4})[0-9a-f]{4}(-[0-9a-f]{4}){3}-[0-9a-f]{12}/', '$1…', $host) ?? '';
        $last = preg_replace('/^(bucket_[0-9a-f]{4}).*/', '$1…', basename($export)) ?? '';

        return $export === '' ? $host : "$host:/…/$last";
    }

    public static function bytes(int|float $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 => number_format($bytes / 1024 / 1024, 2) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 1) . ' KB',
            default => $bytes . ' B',
        };
    }
}
