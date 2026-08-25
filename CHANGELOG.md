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
- Representative MariaDB upgrade rehearsal and large-data list sanity
- Operator docs: `INSTALL.md`, `SUPPORT.md`, `deploy/LIVE_PROVIDER_CHECKS.md`, expanded `deploy/README.md`
- `php artisan agovena:verify-providers [extension] [--sandbox]` connection checks (never creates charges)

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

- **Mollie / Stripe / PayPal / PostNL / Pterodactyl / Proxmox:** MOCK-TESTED ONLY until real sandbox credentials complete `deploy/LIVE_PROVIDER_CHECKS.md` (then SANDBOX-VERIFIED; never PRODUCTION-VERIFIED from test mode alone)
- Automatic VAT uses a remote EU standard-rate dataset (not reduced rates, not US sales tax). See [ATTRIBUTION.md](ATTRIBUTION.md)
- FX sync is informational (ECB via Frankfurter); confirm terms for your use case in ATTRIBUTION.md
- **Docker** Compose production stack: optional / **UNVERIFIED**
- Host OS beyond Ubuntu 24.04 CI: not broadly validated
- Minimal subscription dunning; no reserved seating; no OAuth / Admin JSON:API
- MariaDB DDL upgrades are not fully transactional - restore from backup on mid-upgrade failure
