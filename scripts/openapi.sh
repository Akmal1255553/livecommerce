#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
OUTPUT="$ROOT/docs/openapi.json"
TEMP="$BACKEND/storage/app/openapi.json"

if [[ ! -d "$BACKEND/vendor" ]]; then
  echo "Backend vendor/ missing. Run scripts/setup.sh first."
  exit 1
fi

mkdir -p "$(dirname "$OUTPUT")"
mkdir -p "$(dirname "$TEMP")"

(cd "$BACKEND" && php artisan scramble:export --path=storage/app/openapi.json)
cp "$TEMP" "$OUTPUT"

echo "OpenAPI spec exported to docs/openapi.json"
echo "Live docs: http://localhost:8080/docs/api"
