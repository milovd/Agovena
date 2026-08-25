# Changelog

All notable public releases will be documented here.

Format: product capability, fixes, migrations, security notes, upgrade steps, known limitations.
Internal planning labels and agent workflow are not recorded.

Planned first public candidate tag (not created until explicitly approved): **v0.1.0-rc.1**.
Application version source: `config('agovena.version')` → currently `0.1.0`.

## Unreleased (toward 0.1.0 / v0.1.0-rc.1)

### Added

- Release artifact build (`scripts/build-release.sh`) with production Composer deps and prebuilt `public/build`
- Native Ubuntu Nginx/PHP-FPM deploy smoke scripts and CI jobs (queue worker, scheduler heartbeat, restart)
- Multi-process MariaDB race coverage for refunds, credit notes, renewals, ticket check-in, and provisioning dispatch
- Representative MariaDB upgrade rehearsal and large-data list sanity
- Operator docs: `INSTALL.md`, `SUPPORT.md`, `deploy/LIVE_PROVIDER_CHECKS.md`, expanded `deploy/README.md`
- `php artisan agovena:verify-providers [extension] [--sandbox]` connection checks (never creates charges)

### Security

- Recent-password confirmation for credit notes, extension secret settings, and admin API token create/revoke
- Provider health output redacts API-key shaped secrets

### Known limitations (honest for first RC)

- **Mollie:** MOCK-TESTED ONLY until a real `test_` API key completes `deploy/LIVE_PROVIDER_CHECKS.md` (then SANDBOX-VERIFIED, never PRODUCTION-VERIFIED from test mode)
- **Stripe / PostNL / Pterodactyl:** MOCK-TESTED ONLY (not required for first RC if Mollie sandbox is verified)
- **Docker** Compose production stack: optional / **UNVERIFIED**
- Host OS beyond Ubuntu 24.04 CI: not broadly validated
- Minimal subscription dunning; no reserved seating; no OAuth / Admin JSON:API
- MariaDB DDL upgrades are not fully transactional - restore from backup on mid-upgrade failure
