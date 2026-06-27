#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"

if [[ ! -d "$BACKEND/vendor" ]]; then
  echo "Backend vendor/ missing. Run scripts/setup.sh first."
  exit 1
fi

(cd "$BACKEND" && composer format)
echo "Format complete."
