# Support matrix (v0.1)

Statuses:

- **VALIDATED** — exercised in this project’s CI or an explicit rehearsal
- **EXPECTED COMPATIBLE** — should work from dependency/runtime similarity; not separately proven
- **UNVERIFIED** — not proven; do not assume production readiness
- **MOCK-TESTED ONLY** — automated mocks/fakes; no live sandbox credentials run yet
- **SANDBOX-VERIFIED** — real provider test/sandbox API exercised (see `deploy/LIVE_PROVIDER_CHECKS.md`)
- **PRODUCTION-VERIFIED** — real live/production API exercised (not claimed for v0.1 RC)

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

## Payment / shipping / provisioning providers

| Provider | Status |
|----------|--------|
| Development / Manual payment | VALIDATED (CI + native smoke) |
| Mollie Extension | MOCK-TESTED ONLY — needs `test_` key (`AGOVENA_EXT_MOLLIE_API_KEY` or Admin api_key) for SANDBOX-VERIFIED |
| Stripe Extension | MOCK-TESTED ONLY |
| PostNL Extension | MOCK-TESTED ONLY |
| Pterodactyl Extension | MOCK-TESTED ONLY |

Connection-only (no charges): `php artisan agovena:verify-providers mollie --sandbox`

Transactional sandbox checklist: `deploy/LIVE_PROVIDER_CHECKS.md`.

## Known RC limitations

- One real sandbox payment proof (Mollie) is the target for first RC; other providers stay mock-tested
- Docker optional/unverified
- No broad OS matrix beyond Ubuntu 24.04 CI
- Minimal dunning; no reserved seating; no OAuth/Admin API
- Third-party Modules/Extensions are trusted-code — only install code you trust (see `INSTALL.md` / Security)
