#!/usr/bin/env bash
# Build a production-oriented Agovena release archive with prebuilt frontend assets.
# Usage: ./scripts/build-release.sh [output-dir]
# Merchants extracting the archive should not need Node/npm to start Agovena.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/dist}"
VERSION="0.1.0"
version_re="'version'[[:space:]]*=>[[:space:]]*'([^']+)'"
while IFS= read -r line || [[ -n "$line" ]]; do
  if [[ "$line" =~ $version_re ]]; then
    VERSION="${BASH_REMATCH[1]}"
    break
  fi
done < "$ROOT/config/agovena.php"

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"
STAGING_NAME="agovena-$VERSION"
ARCHIVE="$OUT_DIR/$STAGING_NAME.tar.gz"

# Stage outside the application tree so tar cannot race with OUT_DIR/dist.
STAGING="$(mktemp -d "${TMPDIR:-/tmp}/agovena-release.XXXXXX")/$STAGING_NAME"
mkdir -p "$STAGING"
trap 'rm -rf "$(dirname "$STAGING")"' EXIT

echo "==> Building frontend assets in source tree (version=$VERSION)..."
if [[ ! -d "$ROOT/node_modules" ]]; then
  npm --prefix "$ROOT" ci
fi
npm --prefix "$ROOT" run build

if [[ ! -f "$ROOT/public/build/manifest.json" && ! -f "$ROOT/public/build/.vite/manifest.json" ]]; then
  echo "ERROR: public/build manifest missing after npm run build" >&2
  exit 1
fi

echo "==> Staging application tree into $STAGING ..."
tar -C "$ROOT" \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./.cursor' \
  --exclude='./docs' \
  --exclude='./node_modules' \
  --exclude='./vendor' \
  --exclude='./tests' \
  --exclude='./e2e' \
  --exclude='./dist' \
  --exclude='./storage/logs' \
  --exclude='./storage/framework/cache/data' \
  --exclude='./storage/framework/sessions' \
  --exclude='./storage/framework/views' \
  --exclude='./storage/app/private' \
  --exclude='./storage/app/public' \
  --exclude='./.env' \
  --exclude='./.env.local' \
  --exclude='./.env.production' \
  --exclude='./.env.testing' \
  --exclude='./database/*.sqlite' \
  --exclude='./database/*.sqlite-*' \
  --exclude='./database/database.sqlite' \
  --exclude='./phpunit.xml' \
  --exclude='./phpunit.mariadb.xml' \
  --exclude='./.phpunit.result.cache' \
  --exclude='./playwright.config.ts' \
  --exclude='./playwright.config.js' \
  --exclude='./Pest.php' \
  --exclude='./storage/pail' \
  --exclude='./*.log' \
  -cf - . | tar -C "$STAGING" -xf -

# Bundled chrome logo must ship even if a broad exclude ever bites public/vendor.
mkdir -p "$STAGING/public/vendor/agovena"
cp -f "$ROOT/public/vendor/agovena/logo.png" "$STAGING/public/vendor/agovena/logo.png"

mkdir -p "$STAGING/deploy"
cp -a "$ROOT/deploy/." "$STAGING/deploy/"
cp -f "$ROOT/README.md" "$ROOT/LICENSE" "$ROOT/SECURITY.md" "$STAGING/" 2>/dev/null || true
[[ -f "$ROOT/INSTALL.md" ]] && cp -f "$ROOT/INSTALL.md" "$STAGING/"
[[ -f "$ROOT/SUPPORT.md" ]] && cp -f "$ROOT/SUPPORT.md" "$STAGING/"
[[ -f "$ROOT/CHANGELOG.md" ]] && cp -f "$ROOT/CHANGELOG.md" "$STAGING/"
[[ -f "$ROOT/agovena_banner.png" ]] && cp -f "$ROOT/agovena_banner.png" "$STAGING/"

mkdir -p \
  "$STAGING/storage/framework/cache" \
  "$STAGING/storage/framework/sessions" \
  "$STAGING/storage/framework/views" \
  "$STAGING/storage/logs" \
  "$STAGING/storage/app/public" \
  "$STAGING/storage/app/private" \
  "$STAGING/bootstrap/cache"
: > "$STAGING/storage/logs/.gitignore"
: > "$STAGING/bootstrap/cache/.gitignore"

echo "==> Installing production Composer dependencies into staging..."
if ! command -v composer >/dev/null 2>&1; then
  echo "::error::composer not found on PATH"
  exit 1
fi
# Avoid default-sqlite side effects during package:discover (no merchant .env yet).
printf '%s\n' \
  'APP_KEY=' \
  'APP_ENV=production' \
  'APP_DEBUG=false' \
  'DB_CONNECTION=mysql' \
  'DB_HOST=127.0.0.1' \
  'DB_DATABASE=agovena' \
  > "$STAGING/.env"
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  --working-dir="$STAGING"
rm -f "$STAGING/.env"

# package:discover may still create a default SQLite file; never ship local DBs.
rm -f "$STAGING"/database/*.sqlite "$STAGING"/database/*.sqlite-* 2>/dev/null || true

echo "==> Asserting release contents..."
bash "$ROOT/scripts/assert-release-contents.sh" "$STAGING"

tar -czf "$ARCHIVE" -C "$(dirname "$STAGING")" "$STAGING_NAME"
echo "$ARCHIVE" > "$OUT_DIR/latest-archive.txt"
echo "Wrote $ARCHIVE"
echo "Merchants: extract → configure .env → php artisan key:generate → migrate/install → queue + cron."
