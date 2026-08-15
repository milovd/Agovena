#!/usr/bin/env bash
# Native Ubuntu Nginx + PHP-FPM + MariaDB smoke for a release tree or checkout.
# Expects: APP_DIR, DB_* env vars, nginx/php-fpm already installed.
# Does NOT use php artisan serve as the production front door.

set -euo pipefail

APP_DIR="${APP_DIR:?APP_DIR required}"
SITE_HOST="${SITE_HOST:-127.0.0.1}"
SITE_PORT="${SITE_PORT:-8080}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-/run/php/php8.3-fpm.sock}"
WEB_USER="${WEB_USER:-www-data}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-agovena}"
DB_USERNAME="${DB_USERNAME:-agovena}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

fail() { echo "::error::native-smoke: $*" >&2; exit 1; }

cd "$APP_DIR"
[[ -f artisan ]] || fail "artisan missing in APP_DIR=$APP_DIR"
[[ -f vendor/autoload.php ]] || fail "vendor missing — release must ship production Composer deps"
[[ -f scripts/ci/native-order-smoke.php ]] || fail "native-order-smoke.php missing from release"

echo "==> PHP $(php -v | head -n1)"
php -m | grep -qi pdo_mysql || fail "pdo_mysql extension missing"
php -m | grep -qi openssl || fail "openssl extension missing"

echo "==> Waiting for MariaDB at ${DB_HOST}:${DB_PORT}"
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
ready=0
for _ in $(seq 1 60); do
  if php -r '
    try {
      new PDO(
        "mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "3306").";dbname=".getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
      );
      exit(0);
    } catch (Throwable $e) {
      fwrite(STDERR, $e->getMessage()."\n");
      exit(1);
    }
  ' 2>/tmp/agovena-db-wait.err; then
    ready=1
    break
  fi
  sleep 1
done
[[ "$ready" -eq 1 ]] || {
  cat /tmp/agovena-db-wait.err || true
  fail "MariaDB not reachable"
}
echo "MariaDB OK"

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --force --no-interaction || fail "key:generate failed"
fi

# Force production-ish settings for the smoke
php -r '
$path = ".env";
$env = file_get_contents($path);
$pairs = [
  "APP_ENV" => getenv("APP_ENV") ?: "production",
  "APP_DEBUG" => getenv("APP_DEBUG") ?: "false",
  "APP_URL" => getenv("APP_URL") ?: ("http://".(getenv("SITE_HOST") ?: "127.0.0.1").":".(getenv("SITE_PORT") ?: "8080")),
  "DB_CONNECTION" => getenv("DB_CONNECTION") ?: "mysql",
  "DB_HOST" => getenv("DB_HOST") ?: "127.0.0.1",
  "DB_PORT" => getenv("DB_PORT") ?: "3306",
  "DB_DATABASE" => getenv("DB_DATABASE") ?: "agovena",
  "DB_USERNAME" => getenv("DB_USERNAME") ?: "agovena",
  "DB_PASSWORD" => getenv("DB_PASSWORD") ?: "secret",
  "QUEUE_CONNECTION" => "database",
  "CACHE_STORE" => "database",
  "SESSION_DRIVER" => "database",
  "MAIL_MAILER" => "log",
  "AGOVENA_DEV_INSTANT_PAY" => "true",
];
foreach ($pairs as $k => $v) {
  if (preg_match("/^".preg_quote($k, "/")."=.*/m", $env)) {
    $env = preg_replace("/^".preg_quote($k, "/")."=.*/m", $k."=".$v, $env);
  } else {
    $env .= "\n".$k."=".$v."\n";
  }
}
file_put_contents($path, $env);
'

echo "==> migrate"
php artisan migrate --force --no-interaction || fail "migrate failed"
echo "==> agovena:install"
php artisan agovena:install --no-interaction \
  --name="Native Smoke Owner" \
  --email="native-smoke@example.test" \
  --password="Agovena-Native-Smoke-9f3a" \
  --site-name="Native Smoke" \
  --locale=en \
  --timezone=UTC \
  --currency=EUR \
  --theme=default \
  --presets=physical,digital || fail "agovena:install failed"

php artisan storage:link --force --no-interaction || true
php artisan config:cache || fail "config:cache failed"
# Avoid route:cache — Livewire / dynamic admin routes are not a stable cache target for smoke.
php artisan view:cache || fail "view:cache failed"

# Permissions for FPM user
sudo chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# Nginx site using deploy/nginx.conf patterns
NGINX_CONF="/etc/nginx/sites-available/agovena-smoke"
sudo tee "$NGINX_CONF" >/dev/null <<EOF
server {
    listen ${SITE_PORT};
    server_name ${SITE_HOST};
    root ${APP_DIR}/public;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ ^/storage/.*\\.php\$ {
        deny all;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }

    location ~* /(?:\\.env|composer\\.(?:json|lock)|package(?:-lock)?\\.json|artisan)\$ {
        deny all;
    }

    location ~ \\.php\$ {
        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
    }
}
EOF

[[ -S "$PHP_FPM_SOCK" ]] || fail "PHP-FPM socket missing: $PHP_FPM_SOCK (ls /run/php: $(ls -la /run/php 2>/dev/null || true))"

sudo ln -sfn "$NGINX_CONF" /etc/nginx/sites-enabled/agovena-smoke
sudo rm -f /etc/nginx/sites-enabled/default || true
sudo nginx -t || fail "nginx -t failed"
# Fresh apt installs may not have nginx running yet; reload then fails.
sudo systemctl enable nginx >/dev/null 2>&1 || true
sudo systemctl restart nginx || fail "nginx restart failed"
sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || fail "php-fpm restart failed"

BASE="http://${SITE_HOST}:${SITE_PORT}"

echo "==> HTTP homepage"
CODE="$(curl -s -o /tmp/agovena-native-home.html -w '%{http_code}' "$BASE/" || true)"
[[ "$CODE" == "200" ]] || {
  echo "---- homepage body (head) ----"
  head -n 40 /tmp/agovena-native-home.html || true
  echo "---- laravel.log ----"
  tail -n 80 storage/logs/laravel.log || true
  fail "homepage HTTP $CODE"
}

echo "==> .env must not be served"
ENV_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/.env" || true)"
[[ "$ENV_CODE" == "403" || "$ENV_CODE" == "404" ]] || fail ".env leaked HTTP $ENV_CODE"

echo "==> Place Development order via CLI (same app + DB as FPM)"
php "$APP_DIR/scripts/ci/native-order-smoke.php" || fail "native-order-smoke.php failed"

echo "==> Queue worker processes pending jobs (including explicit proof job)"
php "$APP_DIR/scripts/ci/native-queue-proof.php" || fail "native-queue-proof.php failed"
echo "Queue worker OK"

echo "==> Scheduler heartbeat"
php artisan tinker --execute="Illuminate\\Support\\Facades\\Cache::forget('agovena:scheduler:heartbeat');" || fail "tinker forget heartbeat failed"
php artisan schedule:run --no-interaction || fail "schedule:run failed"
HB="$(php artisan tinker --execute="echo Illuminate\\Support\\Facades\\Cache::get('agovena:scheduler:heartbeat') ?? '';")"
[[ -n "$HB" ]] || fail "scheduler heartbeat missing"
echo "heartbeat=$HB"

echo "==> Restart PHP-FPM + Nginx and re-check"
sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || true
sudo systemctl restart nginx || fail "nginx restart after persistence check failed"
CODE2="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/" || true)"
[[ "$CODE2" == "200" ]] || fail "post-restart homepage $CODE2"

php artisan agovena:doctor --no-interaction || true

echo "Native Nginx/PHP-FPM smoke OK"
