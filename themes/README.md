# Themes

Presentation for **storefront and Admin**. Themes must not contain backend business rules that belong in Core or Modules. Payment provider internals, Module Eloquent models, and fulfillment orchestration stay out of Theme Blade.

## Default Theme

`themes/default` is the reference Theme. It ships two surfaces:

- **storefront** — catalog, cart, checkout, account, invoice document
- **admin** — Admin chrome (`layouts/admin`, `layouts/admin-guest`)

Module-contributed account surfaces (downloads, digital secrets, subscriptions, services, tickets, returns) render through Theme views when those Modules are enabled. A storefront-only Theme may omit the `admin` capability; Admin then falls back to the Default Theme so the control center never breaks.

## Contracts

- Discover via Theme manifest (`theme.json` capabilities)
- Activate one Theme for storefront; Admin uses that Theme when it provides `admin`, otherwise Default
- Schema-driven customize values (branding, homepage sections, navigation)
- Graceful fallback when a Theme view is missing
- Invoice HTML/PDF uses `theme::invoices.document` unless an Extension overrides the document view
