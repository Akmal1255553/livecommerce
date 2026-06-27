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
  (cd "$BACKEND" && composer lint)
}

run_mobile() {
  if ! command -v flutter >/dev/null 2>&1; then
    echo "Flutter not found — skipping mobile lint."
    return 0
  fi
  (cd "$MOBILE" && flutter analyze --no-fatal-infos)
}

run_backend
run_mobile
echo "Lint passed."
