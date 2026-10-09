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

## Backend structure

Business logic is organised by domain under `app/Agovena/<Domain>`, the namespace optional packages build against. Laravel's own layers stay where Laravel puts them and stay thin.

| What | Where |
| --- | --- |
| Business rules for a core domain: actions (`IssueInvoiceFromOrder`, `DeleteOrder`), services, registries, value objects | `app/Agovena/<Domain>/` |
| Interfaces that Modules and Extensions implement or call | `app/Agovena/<Domain>/Contracts/` (public package API; changing one breaks packages) |
| A core capability a product can switch on (`Availability`, `Physical`, `Recurring`) | Self-contained like a Module: `app/Agovena/<Capability>/` with its own service provider, `Models`, `Enums`, `Events`, `Listeners`, `Http/Livewire` and `resources/{database,lang}` |
| Eloquent models, enums and events shared by core domains | `app/Models`, `app/Enums`, `app/Events` |
| Admin and account screens for core domains | `app/Livewire/Admin/<Area>`, `app/Livewire/Customer` (call domain actions; no business rules) |
| HTTP controllers, middleware, console commands | `app/Http`, `app/Console/Commands` (thin entry points) |
| Anything specific to one Module or Extension | That package in optional-packages. Core exposes a contract, capability or `ModuleContext`/`AdminRegistrar` hook instead of naming the package. |

Domains with similar names have separate jobs:

- `Recurring` is subscriptions and renewals, including consolidated renewal billing. `PlanChanges` moves a subscription or service to another plan.
- `Physical` is shipping methods, zones, shipments and returns. `Shipping` holds the carrier contracts that shipping Extensions implement. `Fulfillment` is the seam that shows fulfillment on order pages.
- `Support` is the helpdesk (tickets and attachments). `Customer` is customer accounts and their properties.

`tests/Feature/Architecture/CoreModuleBoundaryTest.php` fails when Core imports, names, enables-by-ID or queries a package outside its documented list of remaining couplings.

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
| Admin screen for an optional Module | the Module itself: `modules/<id>/resources/views/admin/`, rendered as `<namespace>::admin.<view>`, where `<namespace>` is what the Module registers with `loadViewsFrom()` (usually its ID; Downloads uses `digital`). Core keeps legacy copies of existing ones, see Contracts. |
| Shared Admin partials (confirmation modal, tab bar) | `resources/views/livewire/admin/partials/` |
| Storefront CSS | `themes/default/resources/css/components/store/_<domain>.css`, imported by `components/_store.css` |
| Theme Admin skin CSS | `themes/default/resources/css/admin/_<area>.css`, imported by `admin.css` |
| Core Admin CSS | `resources/css/admin/components/` for reusable Admin UI, `resources/css/admin/screens/_<domain>.css` for one screen or domain; all imported by `resources/css/admin.css` (ITCSS) |
| Storefront JavaScript | `resources/js/storefront/<domain>.js`, registered from `resources/js/storefront.js` |
| Admin JavaScript | `resources/js/admin/<area>.js`, registered from `resources/js/admin.js` |
| Behaviour shared by both | `resources/js/shared/` |

- Domain meaning wins over size: a product price, order summary or cart line stays in its domain even when it is small. `<x-ag.*>` must not know about orders, products, checkout or payments. The one exception is `<x-ag.payment-method-icon>`, a public icon lookup shared by Themes and the Admin Extensions list.
- A large page is a composition: the route view stays the entry point and includes sections from a `partials/` folder next to it. Keep `@php use ...` enums and nested `@livewire` components in the parent view.
- The `@import` order in each CSS index is the cascade order. `tests/Feature/Architecture/FrontendArchitectureTest.php` fails when a CSS partial, script module or Alpine component is not wired to the entry that ships it.

**Contracts**

| Contract | Status |
| --- | --- |
| Theme entry views: every `theme::` view that core or a Module renders by name (`layouts.storefront`, `layouts.checkout`, `layouts.error`, `account.*`, `catalog.*`, `cart.index`, `checkout.index`, `domains.search`, `errors.*` (including `errors.maintenance`, which receives `$maintenanceMessage` as plain text and `$maintenanceEndsAt`), `account.partials.nav`, `checkout.partials.address-suggestions`, ...) | Public. A Theme replaces the whole `theme::` namespace (there is no per-view fallback), so renaming one breaks every third-party Theme. Keep these names. Optional overrides: `invoices.document` (receives `$invoice` and `$printable`) and `invoices.credit-note` (receives `$creditNote` and `$printable`). Without them core renders the merchant's invoice design from Admin > Invoices > Design, which is the normal case. |
| `layouts.admin` / `layouts.admin-guest` and their composer data | Public. Rendered by core and Modules; provided by a Theme with the `admin` capability. |
| Views only included from inside the default Theme (its `partials/header/*`, `partials/admin/*`, `<domain>/partials/*`) | Private to that Theme, unless core or a Module includes them by `theme::` name; those are entry views (above). Another Theme never sees the private ones; reorganise freely. |
| `resources/views/livewire/admin/**` | Internal to core. A Theme with the `admin` capability can still shadow a path, so prefer adding partials over renaming. |
| `livewire.admin.partials.confirm-password-modal` with `App\Livewire\Concerns\RequiresRecentPassword` | Public. Module Admin screens include the modal and use the trait for re-authentication (Provisioning). Keep the view name and the trait's properties and methods. |
| Module and Extension views | Owned by the package under its own namespace (`provisioning::admin.show`, `paypal::checkout`), registered with `loadViewsFrom()`. Never add new package views to core. |
| Legacy Module Admin views (`livewire.admin.{digital,digital-delivery,domains,events,provisioning}.*`) | Compatibility copies for Modules released before they shipped their own views. Operators install optional-packages `main` by default and keep installed copies until they update, so these stay until Core requires Module 1.1.0 (see Package versions). `ModuleViewCompatibilityTest` keeps them identical to the Module copies and fails if they outlive that. |
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

## Package versions

`config/agovena.php` `version` is the only Core version. Each Module and Extension declares the Core versions it runs on in the `agovena` field of its manifest.

- While Core is `0.x`, a patch release (`0.0.1` → `0.0.2`) must not break packages; a minor release (`0.0.x` → `0.1.0`) may. Packages therefore declare a range such as `>=0.0.1 <0.1.0`, not `^0.0.1` (which Composer reads as `>=0.0.1 <0.0.2` and would disable every package on the next patch release). `make:agovena-*` scaffolding writes this range for the current Core.
- Bump a package's `version` whenever its shipped files change, so installs see the update.
- When Core stops supporting older first-party package releases, set `agovena.packages.minimum_versions` (for example `'events' => '1.1.0'`). Older installs cannot be installed or enabled, stay enabled but are not booted, and show an Update action.

Upgrades: deploy the new Core release, then run `php artisan agovena:upgrade`. It applies Core migrations, updates every installed Module and Extension that has a newer version or cannot run on the new Core (each update rolls back on failure), runs package migrations, and fails listing any enabled package that still cannot run.

Core still ships 14 legacy Module Admin views (`livewire.admin.{digital,digital-delivery,domains,events,provisioning}.*`) for Modules older than 1.1.0. Remove them in the release that sets `minimum_versions` to `1.1.0` for downloads, digital-delivery, domains, events and provisioning; `ModuleViewCompatibilityTest` fails until both happen together.

## Security

See [SECURITY.md](SECURITY.md). Do not file public issues for vulnerabilities.

## Code of conduct

Be decent. Full text: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
