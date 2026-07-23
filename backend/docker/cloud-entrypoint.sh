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
  export APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
  echo "WARNING: Generated ephemeral APP_KEY for this boot. Set a permanent APP_KEY in the host dashboard."
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

# Always ensure demo admin exists for /admin (idempotent)
php artisan db:seed --class=AdminUserSeeder --force --no-interaction || true

# Idempotent demo catalog when empty (or SEED_ON_BOOT=true)
if [ "$SEED_ON_BOOT" = "true" ]; then
  php artisan db:seed --class=DemoCommerceSeeder --force --no-interaction
else
  php artisan db:seed --class=DemoCommerceSeeder --force --no-interaction || true
fi

exec php artisan serve --host=0.0.0.0 --port="$PORT"
