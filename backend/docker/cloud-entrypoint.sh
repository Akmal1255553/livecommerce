#!/bin/sh
set -e

cd /app

# Render / Railway inject PORT (Render default 10000)
PORT="${PORT:-8080}"

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

php artisan config:clear
php artisan migrate --force --no-interaction

if [ "$SEED_ON_BOOT" = "true" ]; then
  php artisan db:seed --force --no-interaction
fi

exec php artisan serve --host=0.0.0.0 --port="$PORT"
