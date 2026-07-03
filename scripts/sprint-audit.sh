#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ROUTES_OUT="$ROOT/docs/.audit/routes-api-v1.txt"

echo "=== Sprint Release Audit (automated helpers) ==="
echo ""

echo "[1/2] Running QA..."
"$ROOT/scripts/qa.sh"

echo ""
echo "[2/2] Exporting api/v1 routes..."
mkdir -p "$ROOT/docs/.audit"

if command -v docker >/dev/null 2>&1 && docker compose -f "$ROOT/docker/docker-compose.yml" ps -q app >/dev/null 2>&1; then
  docker compose -f "$ROOT/docker/docker-compose.yml" exec -T app \
    php artisan route:list --path=api/v1 --columns=method,uri,name,action \
    >"$ROUTES_OUT"
elif [[ -d "$ROOT/backend/vendor" ]]; then
  (cd "$ROOT/backend" && php artisan route:list --path=api/v1 --columns=method,uri,name,action) \
    >"$ROUTES_OUT"
else
  echo "WARN: Skip route export — Docker/app unavailable and backend/vendor missing." >&2
fi

if [[ -f "$ROUTES_OUT" ]]; then
  echo "Routes written to: docs/.audit/routes-api-v1.txt"
fi

echo ""
echo "Automated checks complete."
echo "Complete manual audits per docs/21_SPRINT_RELEASE_AUDIT.md:"
echo "  - Architecture Audit (ADR + Blueprint)"
echo "  - API Audit (compare routes export vs 04_API_SPECIFICATION.md)"
echo "  - Mobile Audit (if mobile changed)"
echo "  - E2E Smoke Test (Phase A script after 4.5M)"
