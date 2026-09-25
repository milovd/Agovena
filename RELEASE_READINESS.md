# Agovena v0.0.1 release readiness

Last verified: 2026-09-25

This is the public release-control document for the declared v0.0.1 scope. It records implementation, test and operator evidence. It intentionally does not enumerate private commercial post-release ideas.

## Current verdict

**Status: blocked for tagging.**

The current source and CI baseline are healthy, but the release gate is not closed. The remaining work is mainly the finite manual and external checklist: module visibility, the known provisioning demo-data issue, non-payment provider extensions, digital entitlements, disposable backup/restore, security and legal review. No release tag, GitHub Release or deployment has been created.

A green CI run is evidence for the jobs it executed. It does not replace authenticated browser review, provider sandbox evidence, disposable restore evidence or legal acceptance.

## Current source and evidence baseline

| Repository or evidence | Current value | State |
|---|---|---|
| Core | `milovd/Agovena`, `main`, `54b87accbc033d9cf5615760301e684abef578fe` | verified |
| Optional packages | `milovd/optional-packages`, `main`, `9eca7527303041326ad07cbd5e0ad35a720c94a4` | verified |
| Public site | `milovd/agovena-site`, `main`, `834b415ca76abe023dbc81c6444277be086b348a` | verified as repository state |
| Core CI | Run `36080996898` for Core SHA `54b87ac` | verified |
| Core CI jobs | `tests (8.3)`, `tests (8.4)`, `browser`, `mariadb`, `release-artifact`, `native-linux` all completed successfully | verified |
| Local Pint | `php vendor/bin/pint --test` | verified, passed |
| Local PHPStan | `php vendor/bin/phpstan analyse --memory-limit=1G` | verified, 0 errors over 710 files |
| Local frontend build | `npm run build` | verified, passed |
| Local Composer wrapper | `composer test` | blocked locally because Composer is not installed on PATH |
| Local full test command | `php artisan test` | unverified locally because the run exceeded the 420-second tool limit; do not count it as a local pass |

## Status vocabulary

- `verified`: the exact behavior has evidence from the current CI, repository checks or Milo's recorded manual acceptance.
- `fixed`: a concrete issue was changed and its regression check passed.
- `mock-only`: contract or fake-provider evidence exists, but no real provider sandbox behavior was observed.
- `sandbox-unverified`: a provider sandbox or external environment is still required.
- `unverified`: no sufficient current evidence has been recorded yet.
- `blocked`: a required gate failed or a required prerequisite is unavailable.

## Explicit payment scope decision

The remaining v0.0.1 manual checklist excludes these five payment Extensions because Milo has just completed and accepted them manually:

- Mollie
- Stripe
- PayPal
- Paddle
- Tebex

Their Agovena integration acceptance is `verified` for this release checklist. They must not be installed, configured, redirected, refunded or retested in the guided checklist unless another test finds a concrete payment regression.

This acceptance does not claim that every provider is live production-verified. Provider limitations, sandbox requirements, merchant approval, webhook delivery and operational production evidence remain documented separately.

## Already verified by Milo

These checks are closed and must not be repeated without a concrete regression:

- installer flow;
- owner and Admin login, logout, session persistence and authorization;
- storefront home, catalog, search, categories and product detail;
- cart add, update and remove;
- guest checkout and checkout with an account;
- account balance with sufficient and insufficient balance;
- Admin catalog, products, categories, prices and stock;
- Admin orders and order status;
- Admin customer search and detail;
- Dutch and English;
- light and dark mode, layout and copy in both modes;
- desktop, tablet and mobile responsive review;
- cart and payment-step overflow and spacing;
- demo data activation and logical appearance of products, categories and capabilities;
- no real provider payment in the basic flows;
- the five payment Extensions listed above.

## Current package inventory

The current optional-packages repository contains five Modules:

1. `downloads`
2. `digital-delivery`
3. `domains`
4. `events`
5. `provisioning`

`inventory`, `shipping` and `subscriptions` are not separate optional Modules in the current repository. Their generic commerce capabilities are Core-owned according to the current architecture decision. The manual checklist must therefore test the five actual Modules and the relevant Core capability surfaces, not eight historical package names.

The current optional Extension inventory contains:

- payment: `mollie`, `stripe`, `paypal`, `paddle`, `tebex`;
- shipping: `postnl`;
- domains: `cloudflare-domain`, `namecheap-domain`;
- provisioning: `pterodactyl`, `proxmox`, `convoy`, `cpanel`, `directadmin`, `enhance`, `plesk`, `virtfusion`, `virtualizor`.

All non-payment provider manifests currently declare `production_ready: false`. This is consistent with the rule that contract tests and mocks do not prove provider readiness.

## Release matrix

| Release area | State | What is done | What remains |
|---|---|---|---|
| Core commerce vertical slice | verified | Current CI tests on PHP 8.3 and 8.4 are green. Milo accepted the main storefront, cart, checkout and Admin flows. | No additional manual repetition unless a regression appears. |
| Authentication and authorization | verified | Current CI and Milo's manual login, session and authorization checks are green. | Security gate still needs its final independent review. |
| Payment Extensions | verified for this checklist | Milo manually accepted Mollie, Stripe, PayPal, Paddle and Tebex. Current CI is green. | Do not retest. Keep live-provider limitations separate. |
| Module lifecycle and visibility | unverified | Lifecycle contracts and automated coverage exist. | Manually test each of the five current Modules: install, enable, Admin surface, navigation, fields/capabilities, migrations, disable, retained data and disabled visibility. |
| Provisioning demo data and visibility | unverified, concrete open issue | Provisioning contracts, dependency gates and Admin surfaces exist. | First manual gate. Determine whether demo records, persisted records or visibility registration expose provisioning while disabled. Fix only the responsible demo data or visibility path if reproduced, then add regression coverage. |
| Shipping `postnl` | mock-only, sandbox-unverified | Extension manifest and contract/test surface exist. | Install and enable without secrets, settings validation, quote/method behavior, physical versus digital-only visibility, safe invalid-config failure and disable behavior. No live label or shipment. |
| Domain `cloudflare-domain` and `namecheap-domain` | mock-only, sandbox-unverified | Provider-neutral domain module and provider adapters are implemented and tested. | Settings validation, health checks, invalid and missing config, availability flow where available, disable and failure states. No registration or renewal. |
| Provisioning provider Extensions | mock-only, sandbox-unverified | Provider contracts, dependency gates and failure-state paths exist. | Test each listed Extension only where its capability is visible. Use fake/mock success if available. Test missing and invalid config, mapping, capacity, provider failure, retry/manual review and lifecycle actions. Never call them production-ready. |
| Downloads and Digital Delivery | unverified | The current code contains separate downloads and digital-delivery boundaries and automated coverage. | Verify paid entitlement, no pre-payment access, customer isolation, no public URL leak and safe disable/uninstall behavior. |
| Backup and restore | unverified for operator evidence | Backup/restore code paths and CI coverage exist. | Start a backup, inspect the artifact, restore only in a disposable environment, verify database/config/uploads and check that secrets do not appear in output or logs. |
| MariaDB migrations and commerce | verified via current CI | Current run `36080996898` passed the MariaDB job, including feature, upgrade, concurrency and large-data paths defined by CI. | Local MariaDB is not available. Any additional deployment-specific acceptance remains external. |
| Native deployment smoke | verified via current CI | Current `native-linux` job passed from the release artifact with Nginx, PHP-FPM and MariaDB. | No claim for every host or OS outside this CI environment. |
| Release artifact | verified via current CI | Current `release-artifact` job built and extracted the archive smoke successfully. | A separate operator clean-install review can still confirm the exact acceptance checklist. |
| Security and release gate | unverified | Current CI includes dependency checks and current code has security-header, authorization and secret-redaction coverage. | Review headers, CSP, secret leakage, data isolation, package authorization, safe failures, dependency and license evidence, tracked-file and secret scans. |
| Legal and third-party attribution | unverified | Package and dependency metadata exists in the repositories. | Human legal/privacy and attribution acceptance is still required. |
| Private post-release commercial scope | not a release gate | Maintained in the private founder scope. | Not enumerated in this public file. |

## Finite remaining checklist

Run these in order. The guided operator chat must issue one concrete test per message and wait for the result before continuing.

1. Provisioning Module visibility and demo-data check.
2. Lifecycle and visibility for `downloads`.
3. Lifecycle and visibility for `digital-delivery`.
4. Lifecycle and visibility for `domains`.
5. Lifecycle and visibility for `events`.
6. Lifecycle and visibility for `provisioning`.
7. Shipping `postnl` manual failure and capability check.
8. Domain Extension checks for `cloudflare-domain` and `namecheap-domain`.
9. Provisioning Extension checks for the nine current provider packages.
10. Digital download entitlement and isolation checks.
11. Disposable backup and restore check.
12. Security, artifact-content and legal release review.

The five payment Extensions are deliberately absent from this list.

## Release decision rule

- `blocked`: any required gate above has a concrete failure, missing prerequisite or unresolved in-scope finding.
- `candidate`: current source, automated checks, artifact and security checks are green, but required manual or provider evidence is still open.
- `ready to tag`: every required v0.0.1 gate is closed with evidence and no known in-scope finding remains.
- `released`: the approved tag and distribution artifact exist and were independently verified.

The current status is `blocked`, not `candidate`, because the required manual checklist and concrete provisioning visibility issue are still open. Do not tag or publish automatically.
