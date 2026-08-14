#!/usr/bin/env bash
# Build a production-oriented Agovena release archive with prebuilt frontend assets.
# Usage: ./scripts/build-release.sh [output-dir]
# Merchants should not need Node/npm after extracting the archive.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/dist}"
VERSION="$(git -C "$ROOT" describe --tags --always --dirty 2>/dev/null || echo dev)"
STAGING="$OUT_DIR/agovena-$VERSION"
ARCHIVE="$OUT_DIR/agovena-$VERSION.tar.gz"

mkdir -p "$OUT_DIR"
rm -rf "$STAGING"
mkdir -p "$STAGING"

echo "Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$ROOT"

echo "Building frontend assets..."
npm --prefix "$ROOT" ci
npm --prefix "$ROOT" run build

if [[ ! -f "$ROOT/public/build/manifest.json" && ! -f "$ROOT/public/build/.vite/manifest.json" ]]; then
  echo "ERROR: public/build manifest missing after npm run build" >&2
  exit 1
fi

echo "Staging release tree..."
rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.cursor' \
  --exclude='docs' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='e2e' \
  --exclude='dist' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='phpunit*.xml' \
  --exclude='playwright.config.*' \
  "$ROOT/" "$STAGING/"

mkdir -p "$STAGING/storage/framework/cache" "$STAGING/storage/framework/sessions" "$STAGING/storage/framework/views" "$STAGING/storage/logs" "$STAGING/bootstrap/cache"
touch "$STAGING/storage/logs/.gitignore" "$STAGING/bootstrap/cache/.gitignore"

tar -czf "$ARCHIVE" -C "$OUT_DIR" "agovena-$VERSION"
echo "Wrote $ARCHIVE"
echo "Merchants: extract, configure .env, composer not required if vendor is included, open /install."
