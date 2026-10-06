# Install Agovena (v0.0.1)

Native Linux (Ubuntu) is the primary production path. Docker is optional.

## Requirements

- Ubuntu 24.04 (validated in CI) or another Linux host with the same stack
- PHP 8.3 or 8.4 with PHP-FPM (`mbstring`, `intl`, `bcmath`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `pdo_mysql`)
- Composer 2
- MariaDB 10.11+ (CI validates MariaDB 11.4; MySQL 8 may work but is not validated)
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

## Local demo data

`php artisan agovena:seed-demo --force` replaces store-owned records. It refuses `APP_ENV=production`; never run it on a merchant store. Before a destructive reset, inventory the isolated demo and take a restorable database and storage backup. The command deletes orders, accounts, sessions, catalog and other store data while preserving package installation rows, role definitions and provider settings.

For the full public demo, deploy current Core and optional-packages, configure `APP_ENV=demo` and `AGOVENA_PACKAGES_MONOREPO_URL`, and keep `storage/app/packages` writable and persistent. Verify the effective container environment. The command installs/enables all five first-party Modules and then attempts the Pterodactyl and Cloudflare domain Extensions before resetting store data. A missing required Module stops the seed. An unavailable Extension produces an explicit partial-provider warning. Only products that actually depend on that Extension are omitted with their orders and fulfilment: without Pterodactyl the Minecraft journey is absent. The domain product uses the `domains` Module's internal `demo-registrar` and `demo-dns`, so it remains available even without Cloudflare. In `staging` the demo provider Extensions are not seeded and the Minecraft product is omitted. To run the complete provider demo, use only an isolated `demo` deployment and fix any Extension installation failure; do not relabel a merchant environment. Outbound provider HTTP fails closed in `demo` even with configured credentials, so the demo never reaches a real provider. If the deployment cannot fetch packages, install them ahead of time.

Run the command from a private interactive terminal with normal output (for example `docker exec -it ... sh`), not through CI, redirected output or a recorded session. Without `AGOVENA_DEMO_PASSWORD`, it generates separate high-entropy passwords for the demo customer and admin and displays them once after seeding. Save them directly in a password manager. Reseeding replaces both accounts and passwords. Never share generated passwords in chat or logs.

For non-interactive local automation, set `AGOVENA_DEMO_PASSWORD` securely in the process environment (the existing behavior uses it for both accounts), or use `--skip-accounts` for catalog-only tests. Never commit credentials to `.env` or pipe the generated output to logs.

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

## Provider checks

Each provider Extension documents its setup, supported operations and failure handling in its package README and on [agovena.com](https://agovena.com/docs/providers). `php artisan agovena:verify-providers` runs connection checks only and never creates payments. Run one test transaction on your own provider account before accepting customer orders.
