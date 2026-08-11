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

- PHP 8.3+
- Composer 2
- Node.js 22+ (asset build only)
- MariaDB/MySQL (SQLite OK for local/dev)

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
