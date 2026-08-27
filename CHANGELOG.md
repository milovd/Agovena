# Changelog

All notable public releases will be documented here.

Format: product capability, fixes, migrations, security notes, upgrade steps, known limitations.
Internal planning labels and agent workflow are not recorded.

Planned first public tag (not created until explicitly approved): **v0.0.1**.
Application version source: `config('agovena.version')` → currently `0.0.1`.

## Unreleased (toward 0.0.1)

### Added

- Optional Modules and Extensions distributed from the [optional-packages](https://github.com/milovd/optional-packages) monorepo (install/update from Admin; migrations run on install/enable)
- First-party payment Extensions: Mollie, Stripe, PayPal (hosted checkout; no card data in Core)
- First-party provisioning Extensions: Pterodactyl, Proxmox VE
- First-party Domain capability with the integrated `domain-dns` package for Cloudflare DNS, Cloudflare registration and Namecheap registration flows
- First-party payment Extensions: Paddle and Tebex (contract-tested; live sandbox still pending)
- First-party shipping Extension: PostNL
- Customer **Account → Security**: TOTP 2FA, recovery codes, and logged-in session revoke for all accounts
- Account balance at checkout: reserve until gateway payment, full-balance settle without a gateway, partial balance + gateway remainder
- Store tax controls: enable/disable tax, automatic EU VAT rates (remote dataset), merchant country overrides only in Admin → Taxes
- Admin currency exchange-rate sync via Frankfurter (ECB-sourced); no baked-in FX seed table for production
- Admin Settings hub with tab switch (General / Branding / Mail / Store), same pattern as Extensions
- Customer properties, cron statistics, package tabs / zip install in Admin
- Checkout finish step, worldwide countries list, address suggestions
- Third-party data attribution: [ATTRIBUTION.md](ATTRIBUTION.md)
- Release artifact build (`scripts/build-release.sh`) with production Composer deps and prebuilt `public/build`
- Native Ubuntu Nginx/PHP-FPM deploy smoke scripts and CI jobs (queue worker, scheduler heartbeat, restart)
- Multi-process MariaDB race coverage for refunds, credit notes, renewals, ticket check-in, and provisioning dispatch
- Core consolidated recurring billing for active subscriptions: one customer/currency/gateway renewal order and invoice with integer-day proration
- Representative MariaDB upgrade rehearsal and large-data list sanity
- Operator docs: `INSTALL.md`, `SUPPORT.md`, `deploy/LIVE_PROVIDER_CHECKS.md`, expanded `deploy/README.md`
- `php artisan agovena:verify-providers [extension] [--sandbox]` connection checks (never creates charges)
- Customer notification center with authenticated bell, unread count, read actions, notification preferences, browser push installation controls, service worker, and subscription ownership checks
- Admin outbound webhook management with HTTPS and SSRF validation, encrypted signing secrets, event allowlists, generic and Discord-compatible destinations, delivery history, audit events, and controlled retries
- Provider-neutral payment fee policy with integer minor-unit arithmetic, order/invoice snapshots, and invoice fee lines
- Inventory reservations with idempotent cancellation release, provisioning retry propagation, and manual-review transitions
- First-party Domain capability with one integrated registration, renewal and Cloudflare DNS package behind the single Domain workspace
- Product-level domain configuration for registrar, DNS provider, default domain, term and auto-renew
- Admin domain operations for registration, renewal and DNS-zone initialization with capability and permission checks
- Migration dry-run foundation with CSV validation, duplicate detection, source profiles, auditable rows, rollback, invoice/payment/transaction/discount/media mappings, and module-gated subscription/service-instance writes
- Referral policy, customer code management, admin activation controls, reward/credit ledger, expiry/limits and fraud review
- Fail-closed scheduled backup failure alerts and deterministic CycloneDX 1.5 dependency SBOM generation
- Safe section editor normalization for allowed section types, escaped text, safe links, local media paths and bounded lists
- OAuth state/nonce storage and provider metadata for Google and Discord with internal redirect validation
- Fail-closed Turnstile and reCAPTCHA challenge verification adapters
- Public robots.txt and sitemap.xml routes with private-path exclusions
- Advanced audit log UI and core services with actor/context capture, redaction, integrity metadata, filtering, export, retention pruning, and feature coverage

### Changed

- Manual / pay-later is not offered at storefront checkout (historical manual gateway remains for refunds/tests only)
- Development instant-pay is not auto-injected into storefront payment methods
- Privileged staff without 2FA are redirected to customer Security (Admin Security page removed)
- Mollie / Stripe storefront options: one gateway choice; method picking happens on the provider hosted page

### Security

- Recent-password confirmation for credit notes, extension secret settings, and admin API token create/revoke
- Provider health output redacts API-key shaped secrets
- Account 2FA and session management live in the customer dashboard, not the Admin control plane

### Known limitations (honest for first RC)

- **Integrated Domain DNS package:** MOCK-TESTED ONLY for Cloudflare DNS, Cloudflare availability/registration and Namecheap availability/registration/renewal scope. Renewals, transfers, contact updates, TLD coverage and live billing-profile registration are not verified here.
- **Mollie / Stripe / PayPal / PostNL / Pterodactyl / Proxmox:** MOCK-TESTED ONLY until real sandbox credentials complete `deploy/LIVE_PROVIDER_CHECKS.md` (then SANDBOX-VERIFIED; never PRODUCTION-VERIFIED from test mode alone)
- **Paddle / Tebex:** MOCK-TESTED ONLY. Core API contracts and signed/idempotent webhook paths have automated tests, but no real sandbox API, hosted checkout, refund, or provider webhook has been verified in this environment. Recurring lifecycle and partial refunds remain outside the first capability surface.
- **Browser push:** the customer UI, service worker, permission handling, fallback state, and subscription API are implemented and feature-tested. End-to-end delivery still requires configured VAPID material and a real browser/provider run; it is not yet SANDBOX-VERIFIED.
- **Outbound webhooks:** endpoint management, signing, generic and Discord-compatible payload formatting, delivery persistence, retries, SSRF validation, and admin controls are implemented and feature-tested. Delivery to a real customer-controlled HTTPS receiver is not verified in CI.
- **Payment fees:** fee calculation and historical order/invoice snapshots are feature-tested. Merchant tax treatment and live provider reconciliation remain deployment and accounting responsibilities.
- **Migration framework:** CSV/custom dry-run, source profiles, durable audit rows, customer/product/order/invoice/payment/transaction/discount/media writes, rollback and module-gated subscription/service-instance writes are feature-tested. Source-specific fixtures, MariaDB/concurrency verification for the extended import chain and provider acceptance remain open.
- **Referrals:** policy, code creation, customer/admin management, rewards, credit ledger, expiry/limits and fraud review are feature-tested. External payment settlement and human fraud policy remain operational responsibilities.
- **OAuth and anti-abuse:** state/nonce, provider metadata, callback exchange, account linking, challenge verification, rate policy, suspensions, IP reputation and recovery are feature-tested. Live OAuth callbacks, reputation services and authenticated browser review are not verified.
- **SEO/editor:** canonical metadata, structured data, exports, consent history, privacy retention and the safe section editor are feature-tested. Human responsive/keyboard review remains open.
- **Advanced audit logs:** core capture, redaction, integrity metadata, filters, export, permissions, and retention command paths are feature-tested. Production retention scheduling, backup policy, and operational review remain deployment responsibilities.
- Automatic VAT uses a remote EU standard-rate dataset (not reduced rates, not US sales tax). See [ATTRIBUTION.md](ATTRIBUTION.md)
- FX sync is informational (ECB via Frankfurter); confirm terms for your use case in ATTRIBUTION.md
- **Docker** Compose production stack: optional / **UNVERIFIED**
- Host OS beyond Ubuntu 24.04 CI: not broadly validated
- Minimal subscription dunning; no reserved seating; no OAuth / Admin JSON:API
- MariaDB DDL upgrades are not fully transactional - restore from backup on mid-upgrade failure
