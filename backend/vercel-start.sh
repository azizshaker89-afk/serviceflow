#!/usr/bin/env bash
# Starts the API container on Vercel. Every instance gets its own fresh SQLite demo database.
set -euo pipefail
cd /app

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set: add it in Vercel > Settings > Environment Variables" >&2
    exit 1
fi
export APP_URL="${APP_URL:-https://${VERCEL_PROJECT_PRODUCTION_URL:-${VERCEL_URL:-localhost}}}"

# Copy the database seeded at build time; reseed if it is older than 12 hours so
# "today" and the SLA timers in the demo stay realistic.
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

exec php artisan serve --host=0.0.0.0 --port="${PORT:-80}" --no-reload
