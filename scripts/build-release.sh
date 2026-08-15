#!/usr/bin/env bash
# Build a production-oriented Agovena release archive with prebuilt frontend assets.
# Usage: ./scripts/build-release.sh [output-dir]
# Merchants extracting the archive should not need Node/npm to start Agovena.
#
# The working tree's vendor/ is NOT converted to --no-dev permanently when
# AGOVENA_RELEASE_KEEP_DEV_VENDOR=1 (default in CI after build we restore).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/dist}"
VERSION="$(grep -E "'version'" "$ROOT/config/agovena.php" | head -n1 | sed -n "s/.*'version'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p")"
VERSION="${VERSION:-0.1.0}"
STAGING="$OUT_DIR/agovena-$VERSION"
ARCHIVE="$OUT_DIR/agovena-$VERSION.tar.gz"

mkdir -p "$OUT_DIR"
rm -rf "$STAGING"
mkdir -p "$STAGING"

echo "==> Building frontend assets in source tree..."
if [[ ! -d "$ROOT/node_modules" ]]; then
  npm --prefix "$ROOT" ci
fi
npm --prefix "$ROOT" run build

if [[ ! -f "$ROOT/public/build/manifest.json" && ! -f "$ROOT/public/build/.vite/manifest.json" ]]; then
  echo "ERROR: public/build manifest missing after npm run build" >&2
  exit 1
fi

echo "==> Staging application tree (no vendor/node_modules/tests)..."
# Prefer portable tar excludes over rsync (rsync is not always installed in CI).
tar -C "$ROOT" \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.cursor' \
  --exclude='docs' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='e2e' \
  --exclude='dist' \
  --exclude='storage/logs' \
  --exclude='storage/framework/cache/data' \
  --exclude='storage/framework/sessions' \
  --exclude='storage/framework/views' \
  --exclude='storage/app/private' \
  --exclude='storage/app/public' \
  --exclude='database/*.sqlite' \
  --exclude='database/*.sqlite-*' \
  --exclude='.env' \
  --exclude='.env.local' \
  --exclude='.env.production' \
  --exclude='.env.testing' \
  --exclude='phpunit.xml' \
  --exclude='phpunit.mariadb.xml' \
  --exclude='playwright.config.ts' \
  --exclude='playwright.config.js' \
  --exclude='Pest.php' \
  --exclude='agovena_banner.png' \
  -cf - . | tar -C "$STAGING" -xf -

# Public operator docs that belong in the release
mkdir -p "$STAGING/deploy"
cp -a "$ROOT/deploy/." "$STAGING/deploy/"
cp -f "$ROOT/README.md" "$ROOT/LICENSE" "$ROOT/SECURITY.md" "$STAGING/" 2>/dev/null || true
[[ -f "$ROOT/INSTALL.md" ]] && cp -f "$ROOT/INSTALL.md" "$STAGING/"
[[ -f "$ROOT/SUPPORT.md" ]] && cp -f "$ROOT/SUPPORT.md" "$STAGING/"
[[ -f "$ROOT/CHANGELOG.md" ]] && cp -f "$ROOT/CHANGELOG.md" "$STAGING/"

# Keep a few root assets used by README / branding references if present
[[ -f "$ROOT/agovena_banner.png" ]] && cp -f "$ROOT/agovena_banner.png" "$STAGING/"

mkdir -p \
  "$STAGING/storage/framework/cache" \
  "$STAGING/storage/framework/sessions" \
  "$STAGING/storage/framework/views" \
  "$STAGING/storage/logs" \
  "$STAGING/storage/app/public" \
  "$STAGING/storage/app/private" \
  "$STAGING/bootstrap/cache"
touch "$STAGING/storage/logs/.gitignore" "$STAGING/bootstrap/cache/.gitignore"

echo "==> Installing production Composer dependencies into staging..."
if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: composer not found on PATH" >&2
  exit 1
fi
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  --working-dir="$STAGING"

echo "==> Asserting release contents..."
bash "$ROOT/scripts/assert-release-contents.sh" "$STAGING"

tar -czf "$ARCHIVE" -C "$OUT_DIR" "agovena-$VERSION"
# Record path for CI
echo "$ARCHIVE" > "$OUT_DIR/latest-archive.txt"
echo "Wrote $ARCHIVE"
echo "Merchants: extract → configure .env → php artisan key:generate → migrate/install → queue + cron."
