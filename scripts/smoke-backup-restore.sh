#!/usr/bin/env bash
# Smoke-test restoration of the release state without exposing backup contents.
# Usage: ./scripts/smoke-backup-restore.sh /path/to/installed-agovena

set -euo pipefail

APP_DIR="${1:?installed application directory required}"
[[ -d "$APP_DIR" ]] || { echo "::error::application directory missing: $APP_DIR"; exit 1; }

TEMP_BACKUP_ROOT=""
if [[ $# -ge 2 ]]; then
  BACKUP_ROOT="$2"
else
  TEMP_BACKUP_ROOT="$(mktemp -d)/agovena-backup"
  BACKUP_ROOT="$TEMP_BACKUP_ROOT"
fi

cleanup() {
  rm -f "$APP_DIR/storage/app/private/.backup-restore-proof"
  if [[ -n "$TEMP_BACKUP_ROOT" ]]; then
    rm -rf "$(dirname "$TEMP_BACKUP_ROOT")"
  fi
}
trap cleanup EXIT

cd "$APP_DIR"
DB_FILE="$APP_DIR/database/database.sqlite"
PROOF_FILE="$APP_DIR/storage/app/private/.backup-restore-proof"

[[ -f .env ]] || { echo "::error::.env missing"; exit 1; }
[[ -f "$DB_FILE" ]] || { echo "::error::SQLite database missing"; exit 1; }
[[ -d storage/app/private ]] || { echo "::error::private storage missing"; exit 1; }
[[ -d storage/app/public ]] || { echo "::error::public storage missing"; exit 1; }

mkdir -p "$BACKUP_ROOT/storage/app"
printf '%s\n' 'agovena-backup-restore-proof' > "$PROOF_FILE"
cp .env "$BACKUP_ROOT/.env"
cp "$DB_FILE" "$BACKUP_ROOT/database.sqlite"
tar -czf "$BACKUP_ROOT/storage-app.tar.gz" -C storage/app private public

rm -f .env "$DB_FILE"
rm -rf storage/app/private storage/app/public
mkdir -p storage/app

cp "$BACKUP_ROOT/.env" .env
cp "$BACKUP_ROOT/database.sqlite" "$DB_FILE"
tar -xzf "$BACKUP_ROOT/storage-app.tar.gz" -C storage/app

[[ -f .env ]] || { echo "::error::.env restore failed"; exit 1; }
[[ -f "$DB_FILE" ]] || { echo "::error::database restore failed"; exit 1; }
[[ -f "$PROOF_FILE" ]] || { echo "::error::private storage restore failed"; exit 1; }
[[ "$(<"$PROOF_FILE")" == 'agovena-backup-restore-proof' ]] || { echo "::error::restored proof mismatch"; exit 1; }
MIGRATION_OUTPUT="$(php artisan migrate:status --no-interaction 2>&1)" || {
  printf '%s\n' "$MIGRATION_OUTPUT" >&2
  echo "::error::migration status failed after backup/restore" >&2
  exit 1
}

printf '%s\n' 'Backup/restore smoke OK: SQLite database, .env, private storage, and public storage restored.'
