#!/usr/bin/env bash
# Extract a release archive into a clean temp dir and smoke-test boot + install.
# Usage: ./scripts/smoke-extracted-release.sh /path/to/agovena-0.1.0.tar.gz

set -euo pipefail

ARCHIVE="${1:?archive required}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "==> Extracting $ARCHIVE into $WORK"
tar -xzf "$ARCHIVE" -C "$WORK"
APP=""
for dir in "$WORK"/agovena-*; do
  if [[ -d "$dir" ]]; then
    APP="$dir"
    break
  fi
done
[[ -n "$APP" && -d "$APP" ]] || { echo "extracted app dir missing"; exit 1; }

bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/assert-release-contents.sh" "$APP"

cd "$APP"
cp .env.example .env
php artisan key:generate --force --no-interaction

# SQLite smoke (no MariaDB required for artifact smoke)
touch database/database.sqlite
php -r '
$env = file_get_contents(".env");
$env = preg_replace("/^DB_CONNECTION=.*/m", "DB_CONNECTION=sqlite", $env);
$env = preg_replace("/^#? ?DB_DATABASE=.*/m", "DB_DATABASE=database/database.sqlite", $env);
$env = preg_replace("/^APP_ENV=.*/m", "APP_ENV=local", $env);
$env = preg_replace("/^APP_DEBUG=.*/m", "APP_DEBUG=true", $env);
$env = preg_replace("/^APP_URL=.*/m", "APP_URL=http://127.0.0.1:8080", $env);
file_put_contents(".env", $env);
'

php artisan migrate --force --no-interaction
php artisan agovena:install --no-interaction \
  --name="Release Smoke Owner" \
  --email="release-smoke@example.test" \
  --password="password-password" \
  --site-name="Release Smoke" \
  --locale=en \
  --timezone=UTC \
  --currency=EUR \
  --theme=default \
  --presets=physical,digital

php artisan storage:link --force --no-interaction || true
php artisan config:clear
php artisan route:list --path=/ --columns=method,uri,name >/dev/null

# Boot HTTP via artisan serve for a single request (smoke only — not production validation)
php artisan serve --host=127.0.0.1 --port=8088 >/tmp/agovena-smoke-serve.log 2>&1 &
SERVE_PID=$!
trap 'kill $SERVE_PID 2>/dev/null || true; rm -rf "$WORK"' EXIT
sleep 2

CODE="$(curl -s -o /tmp/agovena-home.html -w '%{http_code}' http://127.0.0.1:8088/ || true)"
[[ "$CODE" == "200" ]] || { echo "homepage HTTP $CODE"; cat /tmp/agovena-smoke-serve.log || true; exit 1; }
if ! grep -q 'build/' /tmp/agovena-home.html && ! grep -qiE 'stylesheet|script' /tmp/agovena-home.html; then
  echo "homepage missing asset references"
  exit 1
fi

# Ensure .env is not under public/
[[ ! -f "$APP/public/.env" ]]
[[ -f "$APP/.env" ]]

echo "Extracted release smoke OK"
