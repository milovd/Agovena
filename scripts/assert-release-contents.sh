#!/usr/bin/env bash
# Fail if a staged Agovena release tree contains secrets, tests, or missing assets.
# Usage: ./scripts/assert-release-contents.sh /path/to/agovena-0.1.0

set -euo pipefail

ROOT="${1:?staging directory required}"

fail() { echo "RELEASE ASSERT FAIL: $*" >&2; echo "::error::RELEASE ASSERT FAIL: $*" >&2; exit 1; }

[[ -d "$ROOT" ]] || fail "staging missing: $ROOT"
[[ -f "$ROOT/artisan" ]] || fail "artisan missing"
[[ -f "$ROOT/public/index.php" ]] || fail "public/index.php missing"
[[ -d "$ROOT/vendor" ]] || fail "vendor missing (production Composer install required)"
[[ -f "$ROOT/vendor/autoload.php" ]] || fail "vendor/autoload.php missing"
[[ -d "$ROOT/themes/default" ]] || fail "Default Theme missing"
[[ -d "$ROOT/deploy" ]] || fail "deploy templates missing"
[[ -f "$ROOT/scripts/ci/bootstrap-packages.php" ]] || fail "bootstrap-packages.php missing"

if [[ ! -f "$ROOT/public/build/manifest.json" && ! -f "$ROOT/public/build/.vite/manifest.json" ]]; then
  fail "public/build manifest missing — merchants must not need npm"
fi

[[ -f "$ROOT/public/vendor/agovena/logo.png" ]] || fail "bundled logo missing"
[[ -f "$ROOT/scripts/native-deploy-smoke.sh" ]] || fail "native-deploy-smoke.sh missing"
[[ -f "$ROOT/scripts/ci/native-order-smoke.php" ]] || fail "native-order-smoke.php missing"
[[ -f "$ROOT/scripts/ci/native-queue-proof.php" ]] || fail "native-queue-proof.php missing"
[[ -f "$ROOT/INSTALL.md" ]] || fail "INSTALL.md missing"
[[ -f "$ROOT/SUPPORT.md" ]] || fail "SUPPORT.md missing"

for bad in \
  "$ROOT/.env" \
  "$ROOT/.git" \
  "$ROOT/node_modules" \
  "$ROOT/tests" \
  "$ROOT/e2e" \
  "$ROOT/docs" \
  "$ROOT/.cursor" \
  "$ROOT/database/database.sqlite"
do
  [[ ! -e "$bad" ]] || fail "must not include: $bad"
done

sqlite_hits="$(find "$ROOT/database" -type f \( -name '*.sqlite' -o -name '*.sqlite-*' \) -print 2>/dev/null || true)"
if [[ -n "${sqlite_hits}" ]]; then
  fail "must not include local SQLite databases: ${sqlite_hits}"
fi

secret_hits="$(find "$ROOT" \( -path "$ROOT/vendor" -o -path "$ROOT/node_modules" \) -prune -o -type f \( -name '.env' -o -name '.env.local' -o -name '*.pem' \) -print 2>/dev/null || true)"
if [[ -n "${secret_hits}" ]]; then
  fail "found forbidden secret-like files outside vendor"
fi

review_hits="$(find "$ROOT" -type d -name 'browser-review' -print 2>/dev/null || true)"
if [[ -n "${review_hits}" ]]; then
  fail "browser-review must not ship"
fi

echo "Release contents OK: $ROOT"
