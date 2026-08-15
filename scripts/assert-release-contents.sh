#!/usr/bin/env bash
# Fail if a staged Agovena release tree contains secrets, tests, or missing assets.
# Usage: ./scripts/assert-release-contents.sh /path/to/agovena-0.1.0

set -euo pipefail

ROOT="${1:?staging directory required}"

fail() { echo "RELEASE ASSERT FAIL: $*" >&2; exit 1; }

[[ -d "$ROOT" ]] || fail "staging missing: $ROOT"
[[ -f "$ROOT/artisan" ]] || fail "artisan missing"
[[ -f "$ROOT/public/index.php" ]] || fail "public/index.php missing"
[[ -d "$ROOT/vendor" ]] || fail "vendor missing (production Composer install required)"
[[ -f "$ROOT/vendor/autoload.php" ]] || fail "vendor/autoload.php missing"
[[ -d "$ROOT/themes/default" ]] || fail "Default Theme missing"
[[ -d "$ROOT/modules" ]] || fail "modules missing"
[[ -d "$ROOT/extensions" ]] || fail "extensions missing"
[[ -d "$ROOT/deploy" ]] || fail "deploy templates missing"

if [[ ! -f "$ROOT/public/build/manifest.json" && ! -f "$ROOT/public/build/.vite/manifest.json" ]]; then
  fail "public/build manifest missing — merchants must not need npm"
fi

[[ -f "$ROOT/public/vendor/agovena/logo.png" ]] || fail "bundled logo missing"

# Forbidden paths / secrets
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

# No nested env dumps / PEM keys outside Composer vendor (vendor may ship test certs)
if find "$ROOT" \( -path "$ROOT/vendor" -o -path "$ROOT/node_modules" \) -prune -o -type f \( -name '.env' -o -name '.env.local' -o -name '*.pem' \) -print 2>/dev/null | grep -q .; then
  fail "found forbidden secret-like files outside vendor"
fi

# No browser screenshots / private review dumps
if find "$ROOT" -type d -name 'browser-review' 2>/dev/null | grep -q .; then
  fail "browser-review must not ship"
fi

echo "Release contents OK: $ROOT"
