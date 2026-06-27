#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOOKS="$ROOT/.githooks"

if [[ ! -d "$ROOT/.git" ]]; then
  echo "Not a git repository."
  exit 1
fi

git config core.hooksPath "$HOOKS"
chmod +x "$HOOKS/pre-commit"
echo "Git hooks installed from .githooks/"
