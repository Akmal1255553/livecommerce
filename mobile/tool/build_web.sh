#!/usr/bin/env sh
set -eu

# Run from mobile/. The SDK is built/downloaded on the host, not the seller PC.
: "${API_BASE_URL:?Set API_BASE_URL to the public HTTPS API URL ending in /api/v1}"
case "$API_BASE_URL" in
  https://*/api/v1) ;;
  *) echo "API_BASE_URL must be HTTPS and end with /api/v1" >&2; exit 1 ;;
esac
SDK_DIR=".flutter-sdk-3.44.4"
if [ ! -x "$SDK_DIR/bin/flutter" ]; then
  git clone --depth 1 --branch 3.44.4 https://github.com/flutter/flutter.git "$SDK_DIR"
fi
"$SDK_DIR/bin/flutter" config --no-analytics
"$SDK_DIR/bin/flutter" pub get
"$SDK_DIR/bin/flutter" gen-l10n
"$SDK_DIR/bin/flutter" build web --release --no-wasm-dry-run --dart-define="API_BASE_URL=$API_BASE_URL"
