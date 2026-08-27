# Agovena v0.0.1 release readiness

This file is the working release matrix for the first public Agovena release. It deliberately distinguishes implementation from provider, browser, deployment, and production verification.

## Status vocabulary

- `implemented`: present in the current repositories and covered by relevant automated tests.
- `partial`: a foundation exists, but release scope or edge cases remain.
- `mock-tested-only`: automated provider fakes exist; no real sandbox flow has been exercised here.
- `sandbox-only`: a real test provider flow is required before the status can be upgraded.
- `missing`: no complete implementation was found in the current repositories.
- `deferred`: explicitly post-release scope.

## Current baseline

- Release line: `v0.0.1`.
- Core source of truth: `config/agovena.php` and `CHANGELOG.md`.
- Optional package source: the `optional-packages` monorepo.
- Core hardening commits: `a6f7b30`, `e230fb5`, `6188770`, `83c2e3e`, `01335a8`, `a3fb36b`, `edf2547`.
- Last commit with full CI matrix green before the current Admin hardening push: `2ad3e3a` via Actions run `33030783714`.
- Current functional baseline: `edf2547` plus browser-test fix `a3fb36b`. This release-matrix update also includes a CI-only Composer retry; its new full CI matrix remains required before release evidence is complete.
- Earlier hardening run `33026830598` for `e230fb5` failed; it is superseded by the successful current-baseline run and is not used as release evidence.
- Optional-packages hardening commits: `43d523f`, `0167242`, `ce2d2cf`.
- Core has only the local `.hermes/` workspace directory untracked; optional-packages is clean.
- The current local full application suite has passed: 921 tests and 12,846 assertions. This local result does not yet prove the pushed commit in CI, external provider behavior, or authenticated browser behavior.

## Implemented or feature-tested foundations

| Area | Status | Evidence or boundary |
|---|---|---|
| Core catalog, cart, checkout, orders, invoices | implemented | Feature coverage exists in the application suite. |
| Refunds, credit notes, payment attempts, fee snapshots and webhook contracts | implemented | Automated idempotency, signature, fee pass-through and invoice snapshot tests exist. |
| Inventory reservations and provisioning seams | partial | Atomic stock reservations, idempotent cancellation release, queue retry propagation, server-selection fail-closed behavior and manual-review transitions are covered. All 18 optional extension manifests now declare `production_ready: false` until provider-specific endpoints, credentials and acceptance flows are proven. MariaDB multi-process proof and live provider failure review remain release gates. |
| Subscriptions and recurring renewal seams | partial | Automated lifecycle coverage and subscription import coverage exist; provider-specific recurring behavior remains capability-bound. |
| Account security, TOTP, recovery and sessions | implemented | Customer security flows and automated coverage exist. |
| Audit logging | implemented | Capture, redaction, integrity metadata, filters, export and retention command paths are covered. |
| Customer notifications and preferences | implemented | In-app center, unread counts, email/in-app policy and browser push foundation are feature-tested. |
| Outbound webhooks and destinations | partial | Generic signed HTTPS and Discord-compatible payload destinations, management, persistence, retries, dead-letter handling and SSRF checks are feature-tested; a real external receiver and production queue verification are still open. |
| Default Theme and theme customizer | partial | Default storefront, Admin chrome and safe section editor are implemented and feature-tested; responsive, keyboard and authenticated browser acceptance remain release gates. |
| Module and Extension lifecycle | implemented | Discovery, dependencies, install, enable, disable and package tests exist. |
| Paddle and Tebex package catalog registration | implemented | Both packages exist in optional-packages and are registered in Core catalog configuration. |

## Release work still required

### Domain sales

`implemented` for the provider-neutral domain layer and tested provider seams. The `domains` module provides the product capability, order-paid idempotent records, lifecycle statuses, independent registrar/DNS roles, registrar and DNS registries, provider capability checks, admin operations and customer-owned list. The `hosting` preset enables `domains` together with `provisioning` and `subscriptions`; it does not install a registrar provider or activate provider credentials.

The Cloudflare Registrar extension is registered outside the `domains` module and depends on it. It supplies provider-specific account/token settings, availability and registration API calls, and explicit capability metadata. The separate `cloudflare-dns` extension supplies zone discovery/creation and record list/create/update/delete operations through its own encrypted settings. Disabling an extension preserves generic domain records but removes provider actions; disabling the module removes the domain surface while preserving its data.

The optional package catalog also contains a separate `namecheap-registrar` extension. It uses the same registrar contract and currently exposes only availability checks, registrations and renewals. Its XML transport and response mapping are automated-test covered, but no live Namecheap sandbox flow has been run here. It does not claim DNS, nameserver, transfer or contact-update support.

Domain records snapshot `registrar_key` and `dns_provider_key` independently. This supports Namecheap for registration plus Cloudflare for DNS without coupling registrar billing, renewals or transfers to DNS hosting. Product configuration and admin actions are feature-tested; live provider registration, DNS and renewal verification remain external gates.

- availability and price checks via `domain-check`;
- new registrations via `registrations`;
- encrypted account/token settings through the existing ExtensionSettingsRepository;
- explicit capability metadata for `availability_check` and `registration`;
- explicit unsupported behavior for renewals while the Cloudflare API beta does not expose renew endpoints.

Transfers, contact updates, renewals, supported-TLD policy, billing-profile setup and real sandbox registration remain provider verification gates. IDNs and unsupported extensions must not be promised.

### Import and migration tooling

`partial`: the generic migration framework and complete core entity matrix are now implemented and covered by automated tests. It provides source aliases for the four supported source profiles plus CSV/custom mapping, dry-run, validation, duplicate detection, rollback and auditable import rows. Customer, product, order, invoice, payment/transaction, discount, product media and module-gated subscription/service-instance writes are covered. Source-specific fixtures and provider acceptance remain external verification work.

Still required for the complete roadmap matrix:

- source-specific fixtures for the remaining entities;
- MariaDB/concurrency verification for the extended import chain;
- provider acceptance verification.

### Payment foundation

`partial` and `mock-tested-only` for external providers.

- Provider-neutral fee pass-through now snapshots the selected policy on orders and invoices.
- Saved payment method consent, removal, re-authentication and provider revocation are covered by the existing gateway and renewal suites.
- Keep Paddle, Tebex, Mollie, Stripe and PayPal status honest until real sandbox checks are completed.

### Migration and import

`partial`.

- Provider-neutral CSV/custom preview now supports header mapping, adapter validation, malformed-row reporting and duplicate detection without writing domain data.
- Customer, product, order, invoice, payment/transaction, discount, product media and module-gated subscription/service-instance mappings now have transaction-safe writes and rollback coverage.
- Source-specific fixtures, MariaDB/concurrency verification for the extended import chain and provider acceptance remain required.

### Referrals

`implemented` for the current v0.0.1 scope.

- Configurable policy, normalized code creation, customer code listing, admin activate/deactivate, self-referral blocking and idempotent checkout attribution are covered.
- Reward/credit ledger entries, expiry, usage limits, fraud-review hold/approve/reject and admin permission checks are feature-tested.
- External payment settlement and human fraud policy remain operational decisions, not unverified release claims.

### Merchant trust and identity

`implemented` for code and automated test scope; live OAuth provider verification and authenticated browser review remain external gates. OAuth callback state is now bound to the initiating browser session:

- OAuth/OIDC state/nonce storage, provider metadata, callback token exchange, verified user creation, account linking and replay rejection are feature-tested;
- Google and Discord login metadata is limited to explicitly enabled providers;
- Turnstile/reCAPTCHA adapters fail closed on missing configuration, invalid responses and provider timeouts;
- request policy wiring, rate limits, hashed IP reputation, bans, whitelists, historical IP logs and CLI recovery are feature-tested;
- optional VPN, proxy and Tor reputation checks do not default to a hard block;
- maintenance recovery and the complete error-page matrix are feature-tested.

Live provider credentials, reputation services and production browser/identity review are not run in this workspace.

### Backups, privacy and operational recovery

`partial` pending a production database/storage run.

- The extracted release has a backup and restore smoke path, plus a first-party artifact verifier for `.env`, SQLite and private/public storage paths.
- Automatic database backups, retention, failure alerts and the documented production restore command path are implemented and feature-tested.
- Cookie banner, cookie settings, consent history, privacy retention and legal page review are feature-tested; legal sign-off remains an operational gate.
- Backup verification still needs the real MariaDB and storage deployment responsibilities, not only a temporary SQLite artifact.

### Storefront, SEO and data export

`partial` pending browser and legal acceptance:

- simple section-based page editor with safe exportable definitions is implemented and normalization-tested;
- robots.txt and sitemap.xml expose only public storefront URLs and are feature-tested;
- canonical metadata, Open Graph, structured data, redirects and permission-scoped exports are implemented and feature-tested;
- privacy/consent flows are feature-tested;
- responsive, keyboard and authenticated browser review for the Default Theme remain release gates.

### Notifications and destinations

`partial` pending a real receiver and production queue run.

- Generic signed HTTP webhook management exists.
- Discord-compatible destination formatting, Admin destination allowlisting, persistence, retries and dead-letter handling are feature-tested.
- Live external receiver acceptance, production queue verification and browser-push provider verification remain open.

### Release packaging and gates

Validated gates:

- full SQLite application suite: 921 tests, 12,846 assertions;
- upgrade suite: 14 tests, 132 assertions;
- Release archive build and extracted-release smoke: passed;
- backup/restore smoke: passed;
- CycloneDX 1.5 dependency SBOM generated and validated with 203 components;
- GitHub Actions full matrix for commit `2ad3e3a`: PHP 8.3, PHP 8.4, browser, native-linux, release-artifact, MariaDB feature/upgrade/concurrency/large-data: passed. The current functional baseline (`edf2547` plus `a3fb36b`) and the workflow retry follow-up still require a fresh green matrix;
- Pint, PHPStan, Blade cache, Vite build, Composer audit, npm production audit, PHP syntax and diff-check: passed.

Still required before a release tag:

- fresh independent security review after the current hardening changes;
- actual provider-specific implementations and acceptance tests for external payment, shipping, registrar, DNS and provisioning providers, or an explicit post-release deferral; all 18 optional adapters are marked `production_ready: false` and cannot be installed, enabled or booted outside local/testing environments;
- real Namecheap/Cloudflare sandbox status matrix;
- authenticated browser review and human responsive/keyboard review;
- live external webhook receiver acceptance;
- final legal/privacy sign-off and third-party attribution review; npm package license inventory is not independently verified.

## Explicitly deferred after v0.0.1

- Atelier, Forge, Journal and Pulse commercial Themes;
- Blog and Knowledgebase Extensions;
- InvoicePlus and visual email template manager;
- Discord Bot runtime;
- Status Page Extension;
- Chatbot Extension;
- Quotes;
- Advanced Tickets;
- Newsletter;
- MinecraftBridge;
- Agovena Cloud/SaaS;
- advanced visual freeform Pages Editor.

## Release rule

Do not describe a capability as production-ready because its classes or mocks exist. Upgrade a status only after the relevant automated, browser, deployment, or provider evidence exists and is recorded in the support matrix and changelog.
