# Themes

Storefront **presentation** only.

Themes must not contain backend business rules that belong in Core or Modules. Payment provider internals, Module Eloquent models, and fulfillment orchestration stay out of Theme Blade.

## Default Theme

`themes/default` is the reference storefront (catalog, cart, checkout, account). Module-contributed account surfaces (downloads, digital secrets, subscriptions, services, tickets, returns) render through Theme views when those Modules are enabled.

## Contracts

- Discover via Theme manifest
- Activate one Theme
- Schema-driven customize values (branding, homepage sections, navigation)
- Graceful fallback when a Theme view is missing

Admin uses the Agovena Admin design system — Themes do not style Admin.
