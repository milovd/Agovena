# Install Agovena (v0.0.1)

Native Linux (Ubuntu) is the primary production path. Docker is optional.

## Requirements

- Ubuntu 24.04 (validated in CI) or another Linux host with the same stack
- PHP 8.3 or 8.4 with PHP-FPM (`mbstring`, `intl`, `bcmath`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `pdo_mysql`)
- Composer 2
- MariaDB 10.11+ / 11.x (MySQL 8 may work; MariaDB is what CI validates)
- Nginx (recommended) or Apache
- A queue worker and a cron entry for `schedule:run`
- Outbound HTTPS if you use Admin currency sync (Frankfurter) or automatic EU VAT rates (vatnode JSON via jsDelivr)

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
npm ci && npm run build # only for source trees without public/build
php artisan agovena:install
```

Optional packages (Modules / Extensions) install from the monorepo. Set for production discovery:

```env
AGOVENA_PACKAGES_MONOREPO_URL=https://github.com/milovd/optional-packages
```

For local development beside Core:

```env
AGOVENA_OPTIONAL_PACKAGES_PATH=../optional-packages
```

Then install/enable packages from **Admin → Modules** / **Admin → Extensions**.

## Upgrade

```bash
php artisan down
# replace application files; keep .env, storage/, and the database
# if upgrading from a source tree: composer install --no-dev --optimize-autoloader
php artisan agovena:upgrade
php artisan up
systemctl restart agovena-queue.service
```

Never use `migrate:fresh` on a live store. Take a MariaDB dump plus `storage/app/{private,public}` and `.env` before upgrading. MariaDB DDL is not fully transactional - a mid-upgrade failure needs an operator restore from backup, not a fake “rollback” button.

`agovena:upgrade` also migrates installed Extensions when applicable.

## Backup and restore verification

The release smoke includes `scripts/smoke-backup-restore.sh`. It performs a temporary SQLite artifact roundtrip for `.env`, the database, `storage/app/private`, and `storage/app/public`, then runs `agovena:doctor`. This proves the extracted artifact path only; production operators must still back up the MariaDB dump and storage directories using their own protected backup system.

## HTTPS

Terminate TLS at Nginx/Apache (`deploy/nginx-https.conf`). Set `APP_URL=https://…`.

## Queue worker and scheduler

Release templates live under `deploy/` (systemd unit + cron). Without both, subscriptions, unpaid-cancel, and queued mail will stall. Confirm with `php artisan agovena:doctor`.

## Customer Security (2FA)

Every account manages TOTP and sessions under **Account → Security** (`/account/security`). Staff who can open Admin may be required to enable 2FA before using Admin (see `AGOVENA_PRIVILEGED_2FA`).

## Tax and currencies

- **Admin → Taxes / Store settings:** enable tax, optional automatic EU VAT rates, country overrides.
- **Admin → Currencies:** sync market rates when outbound HTTPS is available.
- Legal notes for remote sources: [ATTRIBUTION.md](ATTRIBUTION.md).

## Third-party Modules and Extensions

Only install code you trust. Composer/Git installs run PHP from that package. Prefer first-party or reviewed sources.

## Support matrix

See [SUPPORT.md](SUPPORT.md).

## Provider sandbox / live checks

See [deploy/LIVE_PROVIDER_CHECKS.md](deploy/LIVE_PROVIDER_CHECKS.md). CI never creates live charges. Connection-only: `php artisan agovena:verify-providers`.
