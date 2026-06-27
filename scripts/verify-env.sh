#!/usr/bin/env bash
# LiveCommerce — Environment Verification (Linux / macOS)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE_FILE="$ROOT/docker/docker-compose.yml"
REPORT="$ROOT/docs/19_ENVIRONMENT_VERIFICATION.md"
TIMESTAMP="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"

declare -A STATUS=()
declare -A DETAIL=()

pass=0
fail=0
skip=0

record() {
  local name="$1" st="$2" detail="$3"
  STATUS["$name"]="$st"
  DETAIL["$name"]="$detail"
  case "$st" in
    PASS) pass=$((pass + 1)); echo -e "\033[32m[PASS]\033[0m $name — $detail" ;;
    FAIL) fail=$((fail + 1)); echo -e "\033[31m[FAIL]\033[0m $name — $detail" ;;
    SKIP) skip=$((skip + 1)); echo -e "\033[33m[SKIP]\033[0m $name — $detail" ;;
  esac
}

cd "$ROOT"

if ! docker info >/dev/null 2>&1; then
  record "Docker daemon" "FAIL" "Docker is not running."
  docker_up=false
else
  record "Docker daemon" "PASS" "Docker engine reachable."
  docker_up=true
fi

if [ "$docker_up" = true ]; then
  docker compose -f "$COMPOSE_FILE" up -d --build
  sleep 10

  running=$(docker compose -f "$COMPOSE_FILE" ps --status running -q | wc -l | tr -d ' ')
  if [ "$running" -lt 5 ]; then
    record "Docker containers" "FAIL" "Only $running containers running."
  else
    record "Docker containers" "PASS" "$running containers running."
  fi

  if [ ! -f backend/.env ] || grep -q 'DB_CONNECTION=sqlite' backend/.env 2>/dev/null; then
    cp backend/.env.example backend/.env
    record "Backend .env" "PASS" "Reset from .env.example."
  else
    record "Backend .env" "PASS" "Docker-oriented .env present."
  fi

  if docker compose -f "$COMPOSE_FILE" exec -T app composer install --no-interaction; then
    record "Composer install" "PASS" "Dependencies installed."
  else
    record "Composer install" "FAIL" "composer install failed."
  fi

  docker compose -f "$COMPOSE_FILE" exec -T app php artisan key:generate --force || true
  docker compose -f "$COMPOSE_FILE" exec -T app php -r "
    if (!getenv('JWT_SECRET')) {
      \$env = file_get_contents('.env');
      \$env = preg_replace('/^JWT_SECRET=.*/m', 'JWT_SECRET='.bin2hex(random_bytes(32)), \$env);
      file_put_contents('.env', \$env);
    }
  " || true

  if docker compose -f "$COMPOSE_FILE" exec -T app php artisan about --no-ansi >/dev/null; then
    record "Laravel boot" "PASS" "Artisan runs."
  else
    record "Laravel boot" "FAIL" "artisan about failed."
  fi

  if docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force; then
    record "Migrations" "PASS" "Migrations applied."
  else
    record "Migrations" "FAIL" "migrate failed."
  fi

  if docker compose -f "$COMPOSE_FILE" exec -T postgres pg_isready -U livecommerce -d livecommerce >/dev/null; then
    record "PostgreSQL" "PASS" "pg_isready OK."
  else
    record "PostgreSQL" "FAIL" "pg_isready failed."
  fi

  if docker compose -f "$COMPOSE_FILE" exec -T redis redis-cli ping | grep -q PONG; then
    record "Redis" "PASS" "PONG received."
  else
    record "Redis" "FAIL" "redis-cli ping failed."
  fi

  docker compose -f "$COMPOSE_FILE" --profile workers up -d worker
  sleep 3
  if docker compose -f "$COMPOSE_FILE" ps worker --format '{{.State}}' | grep -q running; then
    record "Queue worker" "PASS" "Worker running."
  else
    record "Queue worker" "FAIL" "Worker not running."
  fi

  if curl -sf http://localhost:9000/minio/health/live >/dev/null; then
    record "MinIO" "PASS" "Health 200."
  else
    record "MinIO" "FAIL" "Not reachable."
  fi

  if curl -sf http://localhost:8025 >/dev/null; then
    record "Mailpit" "PASS" "Web UI reachable."
  else
    record "Mailpit" "FAIL" "Not reachable."
  fi

  if curl -sf http://localhost:8080/api/v1/health | grep -q '"status":"ok"'; then
    record "Health endpoint" "PASS" "API health OK."
  else
    record "Health endpoint" "FAIL" "Health check failed."
  fi

  if docker compose -f "$COMPOSE_FILE" exec -T app ./vendor/bin/pest --no-ansi; then
    record "Pest tests" "PASS" "All tests passed."
  else
    record "Pest tests" "FAIL" "Tests failed."
  fi
else
  for c in "Docker containers" "Backend .env" "Composer install" "Laravel boot" \
    "Migrations" "PostgreSQL" "Redis" "Queue worker" "MinIO" "Mailpit" \
    "Health endpoint" "Pest tests"; do
    record "$c" "SKIP" "Docker not available."
  done
fi

if command -v flutter >/dev/null 2>&1; then
  pushd mobile >/dev/null
  if flutter pub get; then record "Flutter pub get" "PASS" "OK."; else record "Flutter pub get" "FAIL" "Failed."; fi
  flutter gen-l10n >/dev/null 2>&1 || true
  if flutter analyze; then record "Flutter analyze" "PASS" "No issues."; else record "Flutter analyze" "FAIL" "Issues found."; fi
  if flutter test; then record "Flutter test" "PASS" "All passed."; else record "Flutter test" "FAIL" "Failed."; fi
  popd >/dev/null
else
  record "Flutter pub get" "SKIP" "Flutter not on PATH."
  record "Flutter analyze" "SKIP" "Flutter not on PATH."
  record "Flutter test" "SKIP" "Flutter not on PATH."
fi

if [ "$fail" -eq 0 ] && [ "$skip" -eq 0 ]; then overall="PASS"
elif [ "$fail" -eq 0 ]; then overall="PASS (with skips)"
else overall="FAIL"; fi

{
  echo "# Environment Verification Report"
  echo ""
  echo "**Generated:** $TIMESTAMP  "
  echo "**Overall status:** **$overall**  "
  echo "**Results:** $pass passed · $fail failed · $skip skipped"
  echo ""
  echo "See \`scripts/verify-env.ps1\` for full template and remediation steps."
  echo ""
  echo "## Checklist"
  echo ""
  echo "| Check | Status | Detail |"
  echo "|-------|--------|--------|"
  for name in "Docker daemon" "Docker containers" "Backend .env" "Composer install" \
    "Laravel boot" "Migrations" "PostgreSQL" "Redis" "Queue worker" "MinIO" \
    "Mailpit" "Health endpoint" "Pest tests" "Flutter pub get" "Flutter analyze" "Flutter test"; do
  if [ -n "${STATUS[$name]+x}" ]; then
    echo "| $name | ${STATUS[$name]} | ${DETAIL[$name]} |"
  fi
  done
} > "$REPORT"

echo ""
echo "Report: docs/19_ENVIRONMENT_VERIFICATION.md"
echo "Overall: $overall"

[ "$fail" -eq 0 ]
