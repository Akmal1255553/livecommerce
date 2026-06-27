#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"$ROOT/scripts/lint.sh"
"$ROOT/scripts/analyse.sh"
"$ROOT/scripts/test.sh"

echo "QA complete."
