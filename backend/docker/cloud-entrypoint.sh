#!/bin/sh
set -e

cd /app

# Render / Railway inject PORT (Render default 10000)
PORT="${PORT:-8080}"
# web (default) | worker - set via Dockerfile CMD / Render dockerCommand
MODE="${1:-web}"

# Laravel expects DB_URL; some hosts only set DATABASE_URL
if [ -z "$DB_URL" ] && [ -n "$DATABASE_URL" ]; then
  export DB_URL="$DATABASE_URL"
fi

if [ -z "$APP_KEY" ]; then
  echo "ERROR: Set a permanent APP_KEY in the host dashboard before deployment." >&2
  exit 1
elif ! echo "$APP_KEY" | grep -q '^base64:'; then
  export APP_KEY="base64:${APP_KEY}"
fi

php artisan package:discover --ansi --no-interaction || true
php artisan config:clear

if [ "$MODE" = "worker" ]; then
  echo "Starting queue worker (queues: video-processing,default)"
  # --timeout must stay below DB_QUEUE_RETRY_AFTER (see render.yaml)
  exec php artisan queue:work "${QUEUE_CONNECTION:-database}" \
    --queue=video-processing,default \
    --sleep=3 \
    --tries=3 \
    --timeout=600 \
    --max-time=3600
fi

php artisan migrate --force --no-interaction

# Cache config/routes/views now that env is present (best-effort)
if [ "$MODE" = "web" ]; then
  php artisan optimize --no-interaction || true
fi

# Demo accounts have public passwords. Never create them in production.
if [ "$SEED_ON_BOOT" = "true" ] && [ "$APP_ENV" != "production" ]; then
  php artisan db:seed --class=AdminUserSeeder --force --no-interaction
  php artisan db:seed --class=DemoCommerceSeeder --force --no-interaction
fi

# Optional demo worker shares the free web instance. Persistent jobs and media
# remain in Supabase when Render sleeps or restarts the instance.
if [ "${RUN_QUEUE_WORKER:-false}" = "true" ]; then
  (
    while true; do
      php -d memory_limit=256M artisan queue:work database --queue=video-processing,default --sleep=3 --tries=3 --timeout=600 --memory=192 --max-time=3600 || true
      sleep 3
    done
  ) &
  WORKER_PID=$!
  trap 'kill "$WORKER_PID" 2>/dev/null || true' EXIT
  trap 'exit 0' TERM INT
  php artisan serve --host=0.0.0.0 --port="$PORT" &
  WEB_PID=$!
  trap 'kill "$WEB_PID" "$WORKER_PID" 2>/dev/null || true; exit 0' TERM INT
  wait "$WEB_PID"
else
  exec php artisan serve --host=0.0.0.0 --port="$PORT"
fi
