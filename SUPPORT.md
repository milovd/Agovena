# Support matrix (v0.1)

Statuses:

- **VALIDATED** — exercised in this project’s CI or an explicit rehearsal
- **EXPECTED COMPATIBLE** — should work from dependency/runtime similarity; not separately proven
- **UNVERIFIED** — not proven; do not assume production readiness
- **MOCK-TESTED ONLY** — automated mocks/fakes; no live sandbox credentials run yet

## Application runtime

| Item | Status |
|------|--------|
| PHP 8.3 (CI Pest) | VALIDATED |
| PHP 8.4 (CI Pest) | VALIDATED |
| MariaDB 11.4 (CI Feature / Upgrade / Concurrency) | VALIDATED |
| SQLite (local/dev + default CI) | VALIDATED for non-production |
| Ubuntu 24.04 + Nginx + PHP-FPM (native CI job) | VALIDATED when `native-linux` job is green |
| Ubuntu 22.04 | EXPECTED COMPATIBLE |
| Debian 12/13 | UNVERIFIED |
| Rocky Linux 9 / AlmaLinux 9 | UNVERIFIED |
| Apache (`deploy/apache.conf`) | EXPECTED COMPATIBLE |
| Redis (cache/queue/locks) | EXPECTED COMPATIBLE (recommended multi-node; not mandatory single VPS) |
| Docker Compose prod stack | UNVERIFIED |

## Release packaging

| Item | Status |
|------|--------|
| `scripts/build-release.sh` tarball with `vendor/` + `public/build` | VALIDATED when `release-artifact` CI is green |
| Extracted-artifact install smoke | VALIDATED when `release-artifact` CI is green |

## Payment / shipping / provisioning providers

| Provider | Status |
|----------|--------|
| Development / Manual payment | VALIDATED (CI + native smoke) |
| Mollie Extension | MOCK-TESTED ONLY — live sandbox blocked until credentials |
| Stripe Extension | MOCK-TESTED ONLY |
| PostNL Extension | MOCK-TESTED ONLY |
| Pterodactyl Extension | MOCK-TESTED ONLY |

See `deploy/LIVE_PROVIDER_CHECKS.md`.
