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

Agovena is in early development.

**Requirements**

- PHP 8.3 or 8.4
- Composer 2
- Node.js 22+ (asset build only)
- MariaDB/MySQL for production (SQLite is OK for local/dev)

PHP 8.3 and 8.4 are exercised in CI. MariaDB 11 is exercised in CI for migrations and the test suite. That is not a claim that every host OS is production-verified.

**Deployment**

The application is distro-agnostic PHP. A practical production layout is Nginx + PHP-FPM + MariaDB, with a queue worker and scheduler:

```bash
php artisan migrate --force   # or agovena:install / agovena:upgrade
php artisan queue:work
php artisan schedule:work     # or cron: * * * * * php artisan schedule:run
```

`docker-compose.prod.yml` is a starting point (nginx, php-fpm, worker, scheduler, MariaDB, Redis). It does **not** auto-migrate. Install and upgrade stay explicit commands. Container healthchecks cover MariaDB, Redis, and nginx `/up`. Worker/scheduler health is the existing doctor/heartbeat, not a fake HTTP server.

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
npm install
npm run build
php artisan agovena:create-owner
php artisan agovena:seed-demo
php artisan agovena:doctor
php artisan agovena:verify-providers   # connection health only; never live charges
php artisan agovena:prune-logs
php artisan serve
```

`agovena:seed-demo` loads local-only sample products, categories, pages, and menus (refuses in production). Use `--force` to replace existing catalog demo data.

- Storefront (default Theme): `/`
- Admin: `/admin` (sign in at `/admin/login`)
- Appearance: Themes, Customize, Navigation, Pages under `/admin/appearance/*`
- Installer placeholder: `/install`

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
