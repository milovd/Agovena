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

The frontend is server-rendered Blade + Livewire with small Alpine components; there is no SPA layer.

**Ownership** follows the same domains as `app/Agovena/*` and `app/Livewire/*`:

| What | Where |
| --- | --- |
| Storefront pages, layouts and sections | `themes/default/views/{catalog,cart,checkout,account,pages,sections,layouts}` |
| Shared storefront chrome (header, footer, consent, icons) | `themes/default/views/partials` |
| Admin screens | `resources/views/livewire/admin/<domain>` |
| Generic UI primitives shared by Admin and storefront | `resources/views/components/ag` (`<x-ag.*>`) |
| Storefront CSS | `themes/default/resources/css/components/store/*` (ordered by `_store.css`) |
| Storefront / Admin Alpine components | `resources/js/storefront/*`, `resources/js/admin/*`, shared behaviour in `resources/js/shared/*` |

**Placing new UI**

- Generic and free of business meaning (button, switch, card, icon, empty state): an `<x-ag.*>` component. It must not know about orders, products, checkout or any other domain.
- Has domain meaning (product price, order summary, cart line): keep it in that domain's views, even if it is small.
- A large page is a composition: keep the route view as the entry point and move sections into a `partials/` folder next to it (for example `checkout/partials/payment.blade.php`, `livewire/admin/orders/partials/show-items.blade.php`). Keep `@php use ...` enums and nested `@livewire` components in the parent view.
- New storefront CSS goes in the matching `components/store/_<domain>.css` file. The `@import` order in `_store.css` is the cascade order.

**Theme compatibility.** View names are a public contract: Themes and optional packages render views such as `theme::partials.header`, `theme::account.services` and `livewire.admin.orders.show` by name, and a Theme can override any non-namespaced view by shipping the same relative path. Do not rename or move existing views, the `resources/js/storefront.js` / `resources/js/admin.js` entries, or the CSS entries in `theme.json`; add new partials instead.

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
