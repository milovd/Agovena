# Support matrix (v0.0.1)

Statuses:

- **VALIDATED** - exercised in this project's CI or an explicit release rehearsal
- **PRODUCTION-READY** - first-party Extension marked `production_ready: true` for its documented scope
- **EXPECTED COMPATIBLE** - should work from dependency/runtime similarity; not separately proven
- **UNVERIFIED** - not proven; do not assume production readiness

## Application runtime

| Item | Status |
|------|--------|
| PHP 8.3 (CI Pest) | VALIDATED |
| PHP 8.4 (CI Pest) | VALIDATED |
| MariaDB 11.4 (CI Feature / Upgrade / Concurrency) | VALIDATED |
| SQLite (local/dev + default CI) | VALIDATED for non-production |
| Ubuntu 24.04 + Nginx + PHP-FPM (native CI job) | VALIDATED |
| Queue worker + scheduler heartbeat (native CI) | VALIDATED |
| Ubuntu 22.04 | EXPECTED COMPATIBLE |
| Debian 12/13 | UNVERIFIED |
| Rocky Linux 9 / AlmaLinux 9 | UNVERIFIED |
| Apache (`deploy/apache.conf`) | EXPECTED COMPATIBLE |
| Redis (cache/queue/locks) | EXPECTED COMPATIBLE (recommended multi-node; not mandatory single VPS) |
| Docker Compose prod stack | UNVERIFIED |

## Release packaging

| Item | Status |
|------|--------|
| `scripts/build-release.sh` tarball with `vendor/` + `public/build` | VALIDATED |
| Extracted-artifact install smoke | VALIDATED |
| Artifact SQLite backup/restore smoke | VALIDATED for release smoke only |
| optional-packages monorepo install path (CI checkout) | VALIDATED |

## Payment / shipping / domain / provisioning providers

| Provider | Status |
|----------|--------|
| Account balance (Core ledger) | VALIDATED (CI) |
| Development instant-pay (non-production config) | VALIDATED for CI/tests only; not auto-offered on storefront |
| Mollie, Stripe, PayPal, Paddle, Tebex Extensions | PRODUCTION-READY |
| PostNL Extension | PRODUCTION-READY |
| Cloudflare Domains, Namecheap Domains Extensions | PRODUCTION-READY |
| Pterodactyl, Proxmox VE Extensions | PRODUCTION-READY |
| cPanel, DirectAdmin, Plesk, Enhance Extensions | PRODUCTION-READY |
| VirtFusion, Virtualizor, Convoy Extensions | PRODUCTION-READY |

Every Extension is covered by automated tests against its documented provider API in CI. Supported and unsupported operations are listed in each package README and on [agovena.com](https://agovena.com/docs/providers). Run one test transaction on your own provider account before accepting customer orders.

Connection-only check (no charges): `php artisan agovena:verify-providers mollie --sandbox`

## Remote data (optional Admin features)

| Source | Status |
|--------|--------|
| Frankfurter FX sync | EXPECTED COMPATIBLE (live HTTP; needs outbound network) |
| vatnode EU VAT JSON (automatic tax) | EXPECTED COMPATIBLE (live HTTP via jsDelivr; needs outbound network) |

Attribution and license notes: [ATTRIBUTION.md](ATTRIBUTION.md).

## Known limitations

- Docker optional/unverified
- No broad OS matrix beyond Ubuntu 24.04 CI
- Minimal dunning; no reserved seating; no OAuth/Admin API
- Third-party Modules/Extensions are trusted code - only install code you trust (see `SECURITY.md`)
- Automatic tax covers EU standard VAT rates only (not reduced rates, not US sales tax)
- One built-in invoice template; more templates are planned after v0.0.1
- Imports use CSV mapping profiles; no direct Paymenter, WHMCS, WooCommerce or Shopify importers yet
- No VPN, proxy, Tor or IP reputation detection yet
- No bulk CSV/JSON/XML exports yet; only the audit log and per-customer data export
- No Admin maintenance toggle yet; use `php artisan down --secret=<token>`

See also https://agovena.com/docs/known-limitations
