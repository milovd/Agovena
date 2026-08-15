# Install Agovena (v0.1)

Native Linux (Ubuntu) is the primary production path. Docker is optional.

## Requirements

- Ubuntu 24.04 (validated in CI) or another Linux host with the same stack
- PHP 8.3 or 8.4 with PHP-FPM (`mbstring`, `intl`, `bcmath`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `pdo_mysql`)
- Composer 2
- MariaDB 10.11+ / 11.x (MySQL 8 may work; MariaDB is what CI validates)
- Nginx (recommended) or Apache
- A queue worker and a cron entry for `schedule:run`

Node/npm is **not** required when installing from a release artifact that includes `public/build`.

## Release artifact (recommended)

1. Download `agovena-<version>.tar.gz`.
2. Extract to `/var/www/agovena`.
3. `cp .env.example .env` and set `APP_URL`, MariaDB credentials, then `php artisan key:generate`.
4. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP-FPM/queue user.
5. Point Nginx/Apache document root at `public/` (see `deploy/nginx.conf`).
6. Open `/install` or run `php artisan agovena:install`.
7. Enable the queue systemd unit and cron from `deploy/`.
8. Run `php artisan agovena:doctor`.

Composer dependencies are already installed in the release (`composer install --no-dev`). Do not delete `vendor/`.

## Source checkout

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# configure DB…
php artisan migrate --force
npm ci && npm run build   # only for source trees without public/build
php artisan agovena:install
```

## Upgrade

```bash
php artisan down
# replace application files; keep .env, storage/, and the database
# if upgrading from a source tree: composer install --no-dev --optimize-autoloader
php artisan agovena:upgrade
php artisan up
systemctl restart agovena-queue.service
```

Never use `migrate:fresh` on a live store. Take a MariaDB dump plus `storage/app/{private,public}` and `.env` before upgrading. MariaDB DDL is not fully transactional — a mid-upgrade failure needs an operator restore from backup, not a fake “rollback” button.

## HTTPS

Terminate TLS at Nginx/Apache (`deploy/nginx-https.conf`). Set `APP_URL=https://…`.

## Support matrix

See [SUPPORT.md](SUPPORT.md).
