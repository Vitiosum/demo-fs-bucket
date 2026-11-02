#!/usr/bin/env sh
# Local run: sign the watch roll like an instance boot, then serve on PORT (default 8143).
set -eu
cd "$(dirname "$0")/.."
PORT="${PORT:-8143}"
php bin/boot.php
exec php -d upload_max_filesize=3M -d post_max_size=4M -S "127.0.0.1:${PORT}" -t public public/index.php
