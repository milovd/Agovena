# Native self-hosting

Agovena is a normal Laravel application. **Docker is optional.** Production does not require containers, Node, or Redis.

## Layout

```
/var/www/agovena application
/var/www/agovena/public web document root
MariaDB production database
php-fpm PHP 8.3 or 8.4
queue worker systemd unit in this directory
scheduler cron in this directory (`schedule:run`)
```

Writable by the application user only (typically `www-data`), not the whole tree:

- `storage/`
- `bootstrap/cache/`

Do not `chmod 777`. The web server must not own `vendor/`, `app/`, or `.env` as world-writable.

Public merchant media: `php artisan storage:link` (`public/storage` → `storage/app/public`).
Private files stay on the `local` disk (`storage/app/private`) and are never served at `/storage`.

PHP `upload_max_filesize` / `post_max_size` and Nginx `client_max_body_size` / Apache `LimitRequestBody` should agree (templates use 20M).

## Install (source checkout)

1. Install PHP-FPM, Nginx or Apache, MariaDB, Composer 2.
2. Place Agovena under `/var/www/agovena`.
3. Create a MariaDB database and user.
4. `cp .env.example .env` and set `APP_URL`, database, and `APP_KEY` (`php artisan key:generate`).
5. `composer install --no-dev --optimize-autoloader`
6. If this tree has no `public/build` (source checkout, not a release), run `npm ci && npm run build` once. **Releases should ship prebuilt assets** (see `scripts/build-release.sh`).
7. Make `storage` and `bootstrap/cache` writable by the FPM/queue user.
8. Point Nginx/Apache at `public/` (see `nginx.conf` / `apache.conf`). Use `nginx-https.conf` for TLS. Match FPM with `php-fpm-pool.conf`.
9. Open `/install` or run `php artisan agovena:install`.
10. Enable `deploy/systemd/agovena-queue.service`.
11. Install `deploy/cron` for `schedule:run`.
12. Terminate TLS at Nginx/Apache. Set `APP_URL=https://…`. Optionally `TRUSTED_PROXIES` for a reverse proxy you control - never `*` on the public internet.

## Release artifacts

`scripts/build-release.sh` produces a tarball that includes `vendor/` (no-dev) and `public/build`. Merchants extracting a release should not need Node/npm merely to start Agovena.

The browser installer is **application setup** (owner, store, modules, theme). It is not a Linux provisioning panel.

## Upgrade

```
php artisan down
# replace application files; keep .env, storage/, and the database
composer install --no-dev --optimize-autoloader # source trees / if vendor changed
php artisan agovena:upgrade
php artisan up
systemctl restart agovena-queue.service
```

Do not `migrate:fresh`. Do not auto-migrate on HTTP requests.

Backup MariaDB + `storage/app/private` + `storage/app/public` + `.env` before upgrading.
If `agovena:upgrade` fails mid-way, MariaDB DDL may already be partially applied - restore from backup, fix the cause, then retry. There is no universal web “rollback” button.

Admin → Updates shows the current application version and whether schema migrations are pending. Operators still deploy release files themselves; Agovena does not self-modify application source over HTTP.

See also [INSTALL.md](../INSTALL.md) and [SUPPORT.md](../SUPPORT.md).

## Queue and Redis

Baseline: `QUEUE_CONNECTION=database` and `CACHE_STORE=database` (or `file` sessions). Redis is recommended for multi-node cache/queue/locks, not mandatory for a single VPS.

## Backup

Preserve: MariaDB dump, `storage/app/private`, `storage/app/public`, and `.env`.

`php artisan agovena:doctor` warns when storage looks ephemeral (`/tmp`) and when the private disk is configured to be publicly served.

## Docker

`docker-compose.prod.yml` remains an optional convenience stack. It does not auto-migrate.
