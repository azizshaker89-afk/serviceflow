#!/usr/bin/env bash
# Boots the public demo. The disk on free hosting is ephemeral, so every start builds a
# fresh SQLite database and replays five months of demo activity through the real Actions.
# Visitors always get clean data, whatever the previous visitor clicked.
set -euo pipefail
cd /app

HOST="${RENDER_EXTERNAL_HOSTNAME:-localhost}"
export APP_URL="${APP_URL:-https://${HOST}}"
export SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS:-${HOST}}"
export APP_KEY="${APP_KEY:-$(php artisan key:generate --show --no-ansi)}"

touch database/database.sqlite
php artisan migrate --force --seed --no-interaction
php artisan config:cache
php artisan route:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
