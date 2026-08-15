# Changelog

All notable public releases will be documented here.

Format: product capability, fixes, migrations, security notes, upgrade steps, known limitations.
Internal planning labels and agent workflow are not recorded.

## Unreleased (toward 0.1.0)

### Added

- Release artifact build (`scripts/build-release.sh`) with production Composer deps and prebuilt `public/build`
- Native Ubuntu Nginx/PHP-FPM deploy smoke scripts and CI jobs
- Multi-process MariaDB race coverage for refunds, credit notes, renewals, ticket check-in, and provisioning dispatch
- Operator docs: `INSTALL.md`, `SUPPORT.md`, expanded `deploy/README.md`

### Security

- Recent-password confirmation for credit notes, extension secret settings, and admin API token create/revoke

### Known limitations

- Mollie / Stripe / PostNL / Pterodactyl remain mock-tested until sandbox credentials are exercised
- Docker production stack remains optional and unverified until explicitly run
- MariaDB DDL upgrades are not fully transactional; operators must restore from backup on mid-upgrade failure
