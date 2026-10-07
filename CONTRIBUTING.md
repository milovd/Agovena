# Contributing to Agovena

Thanks for taking an interest in Agovena.

## Current stage

The project is early but has a runnable Laravel Core, Admin, default Theme storefront (catalog through checkout and customer account), and first-party Modules/Extensions via [optional-packages](https://github.com/milovd/optional-packages). Useful help right now:

- Issues that point out gaps or confusing bits
- Discussion around Module / Extension / Theme boundaries
- Focused code improvements with tests

## Ground rules

- Keep Core small; prefer Modules and Extensions over bloating everything
- Respect the split: Core / Modules / Extensions / Themes
- First-party packages live in **optional-packages**, not as permanent trees under Core `modules/` or `extensions/`
- Prefer simple, explicit code
- No secrets in the repo
- Do not describe unfinished work as shipped
- Do not use long dash punctuation in UI copy, docs, comments, or commits (use commas, colons, parentheses, or spaced hyphens)

## Frontend structure

The frontend is server-rendered Blade + Livewire with small Alpine components; there is no SPA layer. Ownership follows the domains in `app/Agovena/*`.

**Where things go**

| What | Where |
| --- | --- |
| Generic UI primitive or small composition (icon, switch, checkbox, card, badge, file upload, empty state) | `resources/views/components/ag` as `<x-ag.*>`. No domain knowledge. |
| Storefront page for a core domain (catalog, cart, checkout, account, pages) | `themes/default/views/<domain>/`, sections in `<domain>/partials/` |
| Homepage sections | `themes/default/views/sections/` |
| Storefront chrome (header, footer, consent) | `themes/default/views/partials/`, header pieces in `partials/header/` |
| Admin screen for a core domain | `resources/views/livewire/admin/<domain>/`, sections in `<domain>/partials/` |
| Admin screen for an optional Module | the Module itself: `modules/<id>/resources/views/admin/`, rendered as `<id>::admin.<view>` |
| Shared Admin partials (confirmation modal, tab bar) | `resources/views/livewire/admin/partials/` |
| Storefront CSS | `themes/default/resources/css/components/store/_<domain>.css`, imported by `components/_store.css` |
| Theme Admin skin CSS | `themes/default/resources/css/admin/_<area>.css`, imported by `admin.css` |
| Core Admin CSS | `resources/css/admin/components/` for reusable Admin UI, `resources/css/admin/screens/_<domain>.css` for one screen or domain; all imported by `resources/css/admin.css` (ITCSS) |
| Storefront JavaScript | `resources/js/storefront/<domain>.js`, registered from `resources/js/storefront.js` |
| Admin JavaScript | `resources/js/admin/<area>.js`, registered from `resources/js/admin.js` |
| Behaviour shared by both | `resources/js/shared/` |

- Domain meaning wins over size: a product price, order summary or cart line stays in its domain even when it is small. `<x-ag.*>` must not know about orders, products, checkout or payments.
- A large page is a composition: the route view stays the entry point and includes sections from a `partials/` folder next to it. Keep `@php use ...` enums and nested `@livewire` components in the parent view.
- The `@import` order in each CSS index is the cascade order. `tests/Feature/Architecture/FrontendArchitectureTest.php` fails when a CSS partial, script module or Alpine component is not wired to the entry that ships it.

**Contracts**

| Contract | Status |
| --- | --- |
| Theme entry views: every `theme::` view that core or a Module renders by name (`layouts.storefront`, `layouts.checkout`, `layouts.error`, `account.*`, `catalog.*`, `cart.index`, `checkout.index`, `domains.search`, `invoices.document`, `errors.*`, `account.partials.nav`, ...) | Public. A Theme replaces the whole `theme::` namespace (there is no per-view fallback), so renaming one breaks every third-party Theme. Keep these names. |
| `layouts.admin` / `layouts.admin-guest` and their composer data | Public. Rendered by core and Modules; provided by a Theme with the `admin` capability. |
| Views only included from inside the default Theme (its `partials/header/*`, `partials/admin/*`, `<domain>/partials/*`) | Private to that Theme. Another Theme never sees them; reorganise freely. |
| `resources/views/livewire/admin/**` | Internal to core. A Theme with the `admin` capability can still shadow a path, so prefer adding partials over renaming. |
| Module and Extension views | Owned by the package under its own namespace (`provisioning::admin.show`, `paypal::checkout`), registered with `loadViewsFrom()`. Never add package views to core. |
| `<x-ag.*>`, `resources/js/storefront.js`, `resources/js/admin.js`, `theme.json` `css` / `admin_css` | Public building blocks for Themes and packages. |
| `ModuleContext` / `AdminRegistrar` hooks, `module.json`, `extension.json`, `theme.json`, `settings.schema.php` | Public extension API. |

## Commits

Prefer [Conventional Commits](https://www.conventionalcommits.org/) that describe the **actual product change**:

- `feat: add admin settings`
- `fix: prevent duplicate payment recording`
- `refactor: extract admin navigation registry`
- `test: add checkout authorization coverage`
- `chore: update dependencies`

Group related work into meaningful commits. Avoid noise commits for tiny edits; also avoid squashing unrelated work into one mega-commit.

## Pull requests

- Keep PRs focused
- Explain why the change is needed
- Use the PR template when present
- Update relevant documentation / operator docs when behavior merchants rely on changes

## Local packages

For Core + packages side by side:

```env
AGOVENA_OPTIONAL_PACKAGES_PATH=../optional-packages
AGOVENA_PACKAGES_MONOREPO_URL=https://github.com/milovd/optional-packages
```

## Security

See [SECURITY.md](SECURITY.md). Do not file public issues for vulnerabilities.

## Code of conduct

Be decent. Full text: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
