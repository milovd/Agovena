#!/usr/bin/env bash
# Extract a release archive into a clean temp dir and smoke-test boot + install.
# Usage: ./scripts/smoke-extracted-release.sh /path/to/agovena-0.0.1.tar.gz

set -euo pipefail

ARCHIVE="${1:?archive required}"
if command -v cygpath >/dev/null 2>&1 && [[ "$ARCHIVE" =~ ^[A-Za-z]:[\\/]|^\\\\ ]]; then
  ARCHIVE="$(cygpath -u "$ARCHIVE")"
fi
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

run_artisan() {
  local label="$1"
  shift
  echo "==> $label"
  if ! php artisan "$@" ; then
    echo "::error::artisan failed: $label ($*)"
    if [[ -f storage/logs/laravel.log ]]; then
      echo "---- laravel.log (tail) ----"
      tail -n 80 storage/logs/laravel.log || true
    fi
    exit 1
  fi
}

run_artisan "key:generate" key:generate --force --no-interaction

# SQLite smoke (no MariaDB required for artifact smoke)
mkdir -p database
touch database/database.sqlite
DB_ABS="$(pwd)/database/database.sqlite"
if command -v cygpath >/dev/null 2>&1; then
  DB_ABS="$(cygpath -w "$DB_ABS")"
fi
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

# Ensure writable runtime dirs after extract (tar may preserve non-writable modes).
chmod -R u+rwX storage bootstrap/cache database 2>/dev/null || true

run_artisan "migrate" migrate --force --no-interaction
BOOTSTRAP_OUTPUT="$(php scripts/ci/bootstrap-packages.php smoke 2>&1)" || {
  printf '%s\n' "$BOOTSTRAP_OUTPUT" >&2
  echo "::error::package bootstrap failed" >&2
  exit 1
}
printf '%s\n' "$BOOTSTRAP_OUTPUT"
if ! grep -Fq 'Packages bootstrapped (profile=smoke).' <<< "$BOOTSTRAP_OUTPUT"; then
  echo "::error::package bootstrap did not report success" >&2
  exit 1
fi
SMOKE_PASSWORD="$(php -r 'echo bin2hex(random_bytes(24));')"
run_artisan "agovena:install" agovena:install --no-interaction \
  --name="Release Smoke Owner" \
  --email="release-smoke@example.test" \
  --password="$SMOKE_PASSWORD" \
  --site-name="Release Smoke" \
  --locale=en \
  --timezone=UTC \
  --currency=EUR \
  --theme=default \
  --presets=physical,digital
echo "==> agovena:backup"
if ! BACKUP_OUTPUT="$(php artisan agovena:backup --no-interaction 2>&1)"; then
  printf '%s\n' "$BACKUP_OUTPUT" >&2
  echo "::error::artisan failed: agovena:backup" >&2
  exit 1
fi
printf '%s\n' "$BACKUP_OUTPUT"
BACKUP_PATH="$(printf '%s\n' "$BACKUP_OUTPUT" | tr -d '\r' | sed -E $'s/\033\\[[0-9;]*m//g' | sed -n 's/.*Backup created: //p' | sed -n '$p')"
[[ -n "$BACKUP_PATH" ]] || { echo "::error::backup command did not report an artifact path"; exit 1; }
run_artisan "agovena:backup-verify" agovena:backup-verify "$BACKUP_PATH" --no-interaction

bash "$APP/scripts/smoke-backup-restore.sh" "$APP"

rm -rf public/storage
run_artisan "storage:link" storage:link --force --no-interaction
run_artisan "config:clear" config:clear
# Laravel 13 route:list has no --columns option; --json keeps the assertion quiet and stable.
run_artisan "route:list" route:list --path=/ --json >/dev/null

SERVE_LOG_PATH="$WORK/agovena-smoke-serve.log"
php artisan serve --host=127.0.0.1 --port=8088 >"$SERVE_LOG_PATH" 2>&1 &
SERVE_PID=$!
HOME_HTML_PATH="$WORK/agovena-home.html"
HOME_HTML_NATIVE="$HOME_HTML_PATH"
if command -v cygpath >/dev/null 2>&1; then
  HOME_HTML_NATIVE="$(cygpath -w "$HOME_HTML_PATH")"
fi

CODE="000"
for attempt in $(seq 1 20); do
  CODE="$(curl -s -o "$HOME_HTML_NATIVE" -w '%{http_code}' http://127.0.0.1:8088/ || true)"
  [[ "$CODE" == "200" ]] && break
  kill -0 "$SERVE_PID" 2>/dev/null || break
  sleep 1
done
[[ "$CODE" == "200" ]] || {
  echo "::error::homepage HTTP $CODE"
  cat "$SERVE_LOG_PATH" || true
  if [[ -f storage/logs/laravel.log ]]; then
    echo "---- laravel.log (tail) ----"
    tail -n 80 storage/logs/laravel.log || true
  fi
  exit 1
}
if ! grep -q 'build/' "$HOME_HTML_PATH" && ! grep -qiE 'stylesheet|script' "$HOME_HTML_PATH"; then
  echo "::error::homepage missing asset references"
  exit 1
fi

[[ ! -f "$APP/public/.env" ]]
[[ -f "$APP/.env" ]]

echo "Extracted release smoke OK"
