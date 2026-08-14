<p align="center">
  <img src="agovena_banner.png" alt="Agovena" width="100%">
</p>

<p align="center">
  <strong>Open-source commerce, built to stay modular.</strong><br>
  Sell physical products, digital goods, hosting, domains, and subscriptions from one self-hosted platform. Only enable what you need.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="MIT License"></a>
  <a href="https://github.com/milovd/Agovena/stargazers"><img src="https://img.shields.io/github/stars/milovd/Agovena?style=flat" alt="Stars"></a>
</p>

## Getting started

Agovena is in early development. It is a normal self-hosted Laravel application.

**Native Linux is the primary production path.** Docker is optional convenience, not a runtime requirement.

**Requirements**

- PHP 8.3 or 8.4 with PHP-FPM
- Composer 2
- MariaDB/MySQL for production (SQLite is OK for local/dev; it is not equivalent for concurrency)
- Nginx (recommended) or Apache, document root = `public/`
- A queue worker (`php artisan queue:work`) and cron `* * * * * php artisan schedule:run`
- Node.js 22+ only to **build** frontend assets from a source checkout. Release artifacts should include `public/build`. Merchants should not need npm merely to install a stable release.

PHP 8.3 and 8.4 are exercised in CI. MariaDB 11 is exercised in CI for migrations and the test suite. That is not a claim that every host OS is production-verified.

See [deploy/README.md](deploy/README.md) for Nginx/Apache, systemd, cron, permissions, backup, and upgrade.

Redis is recommended for multi-node cache/queue/locks. A single VPS can use `QUEUE_CONNECTION=database` without Redis.

`docker-compose.prod.yml` is an optional stack (nginx, php-fpm, worker, scheduler, MariaDB, Redis). It does **not** auto-migrate.

OS status:

- Application/runtime compatible: any OS that can run PHP 8.3+ with the extensions CI uses
- Officially validated host images: none yet
- Community/unverified: Ubuntu 22.04/24.04, Debian 12/13, Rocky Linux 9, AlmaLinux 9
- Unsupported: EOL releases

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB in .env, then:
php artisan migrate
# source checkouts only — skip when public/build is already present:
npm install && npm run build
php artisan agovena:install   # or open /install
php artisan agovena:doctor
php artisan agovena:verify-providers   # connection health only; never live charges
```

`agovena:seed-demo` loads local-only sample products (refuses in production).

- Storefront: `/`
- Admin: `/admin` (everyone signs in at `/login`; Admin is permission-based)
- Installer: `/install` until the store is installed, then it stays closed

## Stack

- Laravel 13
- Livewire 4
- Blade + Alpine (via Livewire)
- Vite + native CSS (ITCSS/BEM/`--ag-*` tokens)
- No Filament / no project-wide Tailwind

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## License

Licensed under the [MIT License](LICENSE).
