#!/usr/bin/env bash
# Boots the public demo. The disk on free hosting is ephemeral, so every start gets a
# fresh SQLite database with five months of demo activity replayed through the real Actions.
# Visitors always get clean data, whatever the previous visitor clicked.
set -euo pipefail
cd /app

# Render sets RENDER_EXTERNAL_HOSTNAME, Hugging Face Spaces sets SPACE_HOST,
# Vercel sets VERCEL_PROJECT_PRODUCTION_URL / VERCEL_URL / VERCEL_BRANCH_URL.
HOST="${RENDER_EXTERNAL_HOSTNAME:-${SPACE_HOST:-${VERCEL_PROJECT_PRODUCTION_URL:-${VERCEL_URL:-localhost}}}}"
STATEFUL="${HOST}"
for extra in "${VERCEL_URL:-}" "${VERCEL_BRANCH_URL:-}"; do
    [ -n "$extra" ] && STATEFUL="${STATEFUL},${extra}"
done
export APP_URL="${APP_URL:-https://${HOST}}"
export SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS:-${STATEFUL}}"
# Hosts that run several instances (Vercel) must set APP_KEY so cookies work on all of them.
export APP_KEY="${APP_KEY:-$(php artisan key:generate --show --no-ansi)}"

# The image ships a database seeded at build time, so a cold start only copies a file.
# If that snapshot is older than 12 hours, reseed so "today" and the SLA timers stay realistic.
SNAPSHOT=database/snapshot.sqlite
if [ -f "$SNAPSHOT" ] && [ $(( $(date +%s) - $(stat -c %Y "$SNAPSHOT") )) -lt 43200 ]; then
    cp "$SNAPSHOT" database/database.sqlite
else
    rm -f database/database.sqlite
    touch database/database.sqlite
    php artisan migrate --force --seed --no-interaction
fi
php artisan config:cache
php artisan route:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
