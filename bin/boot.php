<?php

/*
 * Runs once on every instance start: CC_PRE_RUN_HOOK="php bin/boot.php".
 * Signs the watch roll (data/boot.log) and seeds the log on the very first start.
 * It never fails a deployment: problems are printed to the deployment logs.
 */

declare(strict_types=1);

require __DIR__ . '/../src/Logbook.php';
require __DIR__ . '/../src/Platform.php';

$book = Logbook::fromEnv();

if (Platform::get('INSTANCE_TYPE') === 'build') {
    echo "[logbook] build instance: nothing to sign\n";
    exit(0);
}

// Writing into a folder the bucket is not mounted on would be lost at the next deploy.
if ($book->isBucket && $book->mount() === null) {
    echo "[logbook] warning: no FS Bucket mounted on {$book->label}/, watch roll not signed\n";
    exit(0);
}

try {
    $line = $book->recordBoot(Platform::who());
    echo '[logbook] signed the watch roll: ' . str_replace("\t", '  ', trim($line)) . "\n";
    if ($book->seed(require __DIR__ . '/../seed/entries.php', __DIR__ . '/../seed', Platform::who())) {
        echo "[logbook] first voyage: seed entries written\n";
    }
} catch (Throwable $e) {
    echo '[logbook] warning: ' . $e->getMessage() . "\n";
}

exit(0);
