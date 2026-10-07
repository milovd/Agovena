# Changelog

All notable changes to Agovena are documented here. Each release has a matching `release/vX.Y.Z` branch and `vX.Y.Z` tag. `main` is the beta line and `dev` is where development happens.

## v0.0.1

First public release of Agovena, an open-source, self-hosted and modular commerce platform. Agovena is early-stage software: read the [known limitations](https://agovena.com/docs/known-limitations) and run one test order per provider before you accept customer orders.

### Core

- Catalog with products, options, images, categories and per-currency prices.
- Cart, checkout, orders, invoices, credit notes, refunds and account balance.
- Built-in inventory with reservations, shipping with zones and methods, and subscriptions with renewals, automatic retries and consolidated billing.
- Coupons, discounts, referrals and gateway fee pass-through.
- Admin with roles and permissions, an audit log with CSV export, database backups with restore verification, outbound webhooks and notification email templates.
- Customer accounts with MFA, sessions, OAuth login providers, Turnstile or reCAPTCHA challenges, customer data export and support tickets.
- Default Theme with a homepage section editor, content pages, SEO basics, cookie consent and themed error pages.
- REST API with scoped tokens, a checkout API and signed webhooks.

### Modules

- Downloads, Digital Delivery, Domains, Events and Provisioning, installable from the [optional-packages](https://github.com/milovd/optional-packages) repository.

### Extensions

- Payments: Mollie, Stripe, PayPal, Paddle and Tebex.
- Shipping: PostNL.
- Domains: Cloudflare Domains and Namecheap Domains.
- Provisioning: Pterodactyl, Proxmox VE, cPanel, DirectAdmin, Plesk, Enhance, VirtFusion, Virtualizor and Convoy.

### Provider readiness

- Products that depend on a provider stay visible when that provider is not ready, but cannot be ordered. The product page shows "Currently unavailable to order" and Admin shows a warning on the product.
- Account-based hosting panels accept orders without a capacity reservation; Pterodactyl and Proxmox VE check capacity at checkout.
- Demo domain adapters are not loaded in production. A failing registrar shows a temporary notice in domain search.
- Checkout reuses payment gateway connection checks for up to five minutes and refreshes them when gateway settings change.

### Release assets

- `agovena-0.0.1.tar.gz` with prebuilt frontend assets, its CycloneDX SBOM and `checksum.txt` with SHA-256 sums.
