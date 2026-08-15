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

cd "$APP_DIR"

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --force --no-interaction
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

php artisan migrate --force --no-interaction
php artisan agovena:install --no-interaction \
  --name="Native Smoke Owner" \
  --email="native-smoke@example.test" \
  --password="password-password" \
  --site-name="Native Smoke" \
  --locale=en \
  --timezone=UTC \
  --currency=EUR \
  --theme=default \
  --presets=physical,digital

php artisan storage:link --force --no-interaction || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions for FPM user
sudo chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# Nginx site using deploy/nginx.conf patterns
NGINX_CONF="/etc/nginx/sites-available/agovena-smoke"
sudo tee "$NGINX_CONF" >/dev/null <<EOF
server {
    listen ${SITE_PORT};
    listen [::]:${SITE_PORT};
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

sudo ln -sfn "$NGINX_CONF" /etc/nginx/sites-enabled/agovena-smoke
sudo rm -f /etc/nginx/sites-enabled/default || true
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || true

BASE="http://${SITE_HOST}:${SITE_PORT}"

echo "==> HTTP homepage"
CODE="$(curl -s -o /tmp/agovena-native-home.html -w '%{http_code}' "$BASE/")"
[[ "$CODE" == "200" ]] || { echo "homepage $CODE"; exit 1; }

echo "==> .env must not be served"
ENV_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/.env" || true)"
[[ "$ENV_CODE" == "403" || "$ENV_CODE" == "404" ]] || { echo ".env leaked HTTP $ENV_CODE"; exit 1; }

echo "==> Place Development order via CLI (same app + DB as FPM)"
php "$APP_DIR/scripts/ci/native-order-smoke.php"

echo "==> Queue worker processes pending jobs"
php artisan queue:work --once --stop-when-empty --tries=1 || php artisan queue:work --stop-when-empty --tries=1 --max-jobs=20

echo "==> Scheduler heartbeat"
php artisan tinker --execute="Illuminate\\Support\\Facades\\Cache::forget('agovena:scheduler:heartbeat');"
php artisan schedule:run --no-interaction
HB="$(php artisan tinker --execute="echo Illuminate\\Support\\Facades\\Cache::get('agovena:scheduler:heartbeat') ?? '';")"
[[ -n "$HB" ]] || { echo "scheduler heartbeat missing"; exit 1; }
echo "heartbeat=$HB"

echo "==> Restart PHP-FPM + Nginx and re-check"
sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm || true
sudo systemctl reload nginx
CODE2="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")"
[[ "$CODE2" == "200" ]] || { echo "post-restart homepage $CODE2"; exit 1; }

php artisan agovena:doctor --no-interaction || true

echo "Native Nginx/PHP-FPM smoke OK"
