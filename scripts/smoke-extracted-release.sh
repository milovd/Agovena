#!/usr/bin/env bash
# Extract a release archive into a clean temp dir and smoke-test boot + install.
# Usage: ./scripts/smoke-extracted-release.sh /path/to/agovena-0.1.0.tar.gz

set -euo pipefail

ARCHIVE="${1:?archive required}"
[[ -f "$ARCHIVE" ]] || { echo "::error::archive missing: $ARCHIVE"; exit 1; }

WORK="$(mktemp -d)"
cleanup() {
  if [[ -n "${SERVE_PID:-}" ]]; then
    kill "$SERVE_PID" 2>/dev/null || true
  fi
  rm -rf "$WORK"
}
trap cleanup EXIT

echo "==> Extracting $ARCHIVE into $WORK"
tar -xzf "$ARCHIVE" -C "$WORK"
APP=""
for dir in "$WORK"/agovena-*; do
  if [[ -d "$dir" ]]; then
    APP="$dir"
    break
  fi
done
[[ -n "$APP" && -d "$APP" ]] || { echo "::error::extracted app dir missing"; ls -la "$WORK"; exit 1; }

bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/assert-release-contents.sh" "$APP"

cd "$APP"
[[ -f .env.example ]] || { echo "::error::.env.example missing from release"; exit 1; }
cp .env.example .env
php artisan key:generate --force --no-interaction

# SQLite smoke (no MariaDB required for artifact smoke)
mkdir -p database
touch database/database.sqlite
DB_ABS="$(pwd)/database/database.sqlite"
php -r '
$env = file_get_contents(".env");
$pairs = [
  "APP_ENV" => "local",
  "APP_DEBUG" => "true",
  "APP_URL" => "http://127.0.0.1:8088",
  "DB_CONNECTION" => "sqlite",
  "DB_DATABASE" => $argv[1],
  "DB_URL" => "",
];
foreach ($pairs as $k => $v) {
  if (preg_match("/^".preg_quote($k, "/")."=.*/m", $env)) {
    $env = preg_replace("/^".preg_quote($k, "/")."=.*/m", $k."=".$v, $env);
  } else {
    $env .= "\n".$k."=".$v."\n";
  }
}
$env = preg_replace("/^#\\s*DB_DATABASE=.*/m", "DB_DATABASE=".$argv[1], $env);
file_put_contents(".env", $env);
' "$DB_ABS"

php artisan migrate --force --no-interaction
php artisan agovena:install --no-interaction \
  --name="Release Smoke Owner" \
  --email="release-smoke@example.test" \
  --password="Agovena-Release-Smoke-9f3a" \
  --site-name="Release Smoke" \
  --locale=en \
  --timezone=UTC \
  --currency=EUR \
  --theme=default \
  --presets=physical,digital

php artisan storage:link --force --no-interaction || true
php artisan config:clear
php artisan route:list --path=/ --columns=method,uri,name >/dev/null

php artisan serve --host=127.0.0.1 --port=8088 >/tmp/agovena-smoke-serve.log 2>&1 &
SERVE_PID=$!
sleep 2

CODE="$(curl -s -o /tmp/agovena-home.html -w '%{http_code}' http://127.0.0.1:8088/ || true)"
[[ "$CODE" == "200" ]] || {
  echo "::error::homepage HTTP $CODE"
  cat /tmp/agovena-smoke-serve.log || true
  exit 1
}
if ! grep -q 'build/' /tmp/agovena-home.html && ! grep -qiE 'stylesheet|script' /tmp/agovena-home.html; then
  echo "::error::homepage missing asset references"
  exit 1
fi

[[ ! -f "$APP/public/.env" ]]
[[ -f "$APP/.env" ]]

echo "Extracted release smoke OK"
