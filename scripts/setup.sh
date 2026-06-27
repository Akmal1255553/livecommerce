#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> LiveCommerce Sprint 0.1 Setup"

if ! docker info >/dev/null 2>&1; then
  echo "ERROR: Docker is not running. Start Docker and retry."
  exit 1
fi

echo "==> Building and starting Docker services..."
docker compose -f docker/docker-compose.yml up -d --build postgres redis minio mailpit

echo "==> Waiting for PostgreSQL..."
sleep 8

echo "==> Starting app + nginx..."
docker compose -f docker/docker-compose.yml up -d app nginx

echo "==> Installing backend dependencies..."
docker compose -f docker/docker-compose.yml exec -T app composer install --no-interaction

if [ ! -f backend/.env ]; then
  echo "==> Creating backend .env..."
  docker compose -f docker/docker-compose.yml exec -T app cp .env.example .env
  docker compose -f docker/docker-compose.yml exec -T app php artisan key:generate
fi

echo "==> Running migrations..."
docker compose -f docker/docker-compose.yml exec -T app php artisan migrate --force

if [ ! -d mobile/android ]; then
  echo "==> Flutter android/ not found."
  if command -v flutter >/dev/null 2>&1; then
    cd mobile
    flutter create . --org uz.livecommerce --project-name livecommerce_mobile
    flutter pub get
    cd "$ROOT"
  else
    echo "    Install Flutter SDK and run:"
    echo "    cd mobile && flutter create . --org uz.livecommerce --project-name livecommerce_mobile"
  fi
else
  cd mobile && flutter pub get && cd "$ROOT"
fi

echo ""
echo "==> Installing git hooks (Sprint 0.2)..."
"$ROOT/scripts/install-hooks.sh"

echo ""
echo "==> Setup complete!"
echo "API:      http://localhost:8080/api/v1/health"
echo "API Docs: http://localhost:8080/docs/api"
echo "Mailpit:  http://localhost:8025"
echo "MinIO:    http://localhost:9001 (minio / minio123456)"
echo ""
echo "Run QA:   ./scripts/qa.sh"
