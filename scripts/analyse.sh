#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
MOBILE="$ROOT/mobile"

run_backend() {
  if [[ ! -d "$BACKEND/vendor" ]]; then
    echo "Backend vendor/ missing. Run scripts/setup.sh first."
    exit 1
  fi
  (cd "$BACKEND" && composer analyse)
}

run_mobile() {
  if ! command -v flutter >/dev/null 2>&1; then
    echo "Flutter not found — skipping mobile analysis."
    return 0
  fi
  (cd "$MOBILE" && flutter analyze --no-fatal-infos)
}

run_backend
run_mobile
echo "Static analysis passed."
