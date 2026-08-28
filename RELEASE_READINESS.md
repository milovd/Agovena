# Agovena v0.0.1 release readiness

This file is the working release matrix for the first public Agovena release. It deliberately distinguishes implementation from provider, browser, deployment, and production verification.

## Release verdict

**Not ready for v0.0.1.** The current runtime baseline has a green local matrix and a green GitHub Actions matrix on `f36a777` (including MariaDB concurrency), but provider sandbox, deployment, live receiver, post-fix independent security, human UI and legal gates remain open. No release tag or GitHub Release has been created.


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
- Core hardening commits: `a6f7b30`, `e230fb5`, `6188770`, `83c2e3e`, `01335a8`, `a3fb36b`, `edf2547`, `7f7beb4`, `89d1b66`, `04d4da1`, `7ca1770`, `d8b9efa`, `24a14b5`, `f36a777`.
- GitHub Actions run `33129833665` is green on `f36a777`, including the MariaDB import-identity concurrency regression.
- Previous successful baseline run `33030783714` remains historical evidence only.
- Previous functional baseline: `89d1b66` (including the browser, backup and import hardening); CI-163 for this baseline passed all jobs.
- Current Domain integration commit: `f36a777`; it provides two provider-specific extensions: `cloudflare-domain` combines Cloudflare Registrar and Cloudflare DNS, while `namecheap-domain` combines Namecheap registration and renewal management. Core and optional-packages commits are pushed to their `main` branches.
- Earlier hardening run `33026830598` for `e230fb5` failed; it is superseded by the successful current-baseline run and is not used as release evidence.
- Optional-packages commits: `43d523f`, `0167242`, `ce2d2cf`, `d18c916`, `a176fbd`.
- Core has only the local `.hermes/` workspace directory untracked; optional-packages is clean.
- The current local full application suite has passed: 948 tests and 12,956 assertions. GitHub Actions run `33129833665` passed PHP 8.3, PHP 8.4, browser, native-linux, release-artifact and MariaDB feature/upgrade/concurrency checks.

## Implemented or feature-tested foundations

| Area | Status | Evidence or boundary |
|---|---|---|
| Core catalog, cart, checkout, orders, invoices | implemented | Feature coverage exists in the application suite. |
| Refunds, credit notes, payment attempts, fee snapshots and webhook contracts | implemented | Automated idempotency, signature, fee pass-through and invoice snapshot tests exist. |
| Inventory reservations and provisioning seams | partial | Atomic stock reservations, idempotent cancellation release, queue retry propagation, server-selection fail-closed behavior and manual-review transitions are covered. All 16 optional extension manifests now declare `production_ready: false` until provider-specific endpoints, credentials and acceptance flows are proven. Live provider failure review remains a release gate; the MariaDB multi-process matrix is green in CI. |
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

`implemented` for the provider-neutral domain layer and tested provider seams. The `domains` module provides the product capability, order-paid idempotent records, lifecycle statuses, registrar and DNS registries, provider capability checks, admin operations and customer-owned list. The `hosting` preset enables `domains` together with `provisioning` and `subscriptions`; it does not install either provider extension or activate provider credentials.

The `cloudflare-domain` extension is registered outside the `domains` module and depends on it. It supplies Cloudflare registration and DNS operations behind its own settings surface. The separate `namecheap-domain` extension supplies Namecheap registration and renewal operations behind its own settings surface. Both extensions preserve generic domain records when disabled; disabling the module removes the domain surface while preserving its data.

The `namecheap-domain` extension contains the Namecheap registrar adapter. It currently exposes only availability checks, registrations and renewals. Its XML transport and response mapping are automated-test covered, but no live Namecheap sandbox flow has been run here. It does not claim DNS, nameserver, transfer or contact-update support.

Domain records snapshot `registrar_key` and `dns_provider_key` independently. This supports Namecheap for registration plus Cloudflare for DNS without coupling registrar billing, renewals or transfers to DNS hosting. Product configuration and admin actions are feature-tested; live provider registration, DNS and renewal verification remain external gates.

- availability and price checks through the provider-specific Domain extensions;
- separate encrypted settings surfaces through the existing ExtensionSettingsRepository for Cloudflare and Namecheap credentials;
- Cloudflare DNS zone and record management plus Cloudflare and Namecheap registrar capabilities;
- explicit unsupported behavior for lifecycle operations that the provider contracts do not expose.

Transfers, contact updates, renewals, supported-TLD policy, billing-profile setup and real sandbox registration remain provider verification gates. IDNs and unsupported extensions must not be promised.

### Import and migration tooling

`partial`: the generic migration framework and complete core entity matrix are now implemented and covered by automated tests. It provides source aliases for the four supported source profiles plus CSV/custom mapping, dry-run, validation, source-isolated dependency references, source/entity/external-identity reservations, duplicate detection, reconciled invoice and payment totals, refund-ledger import, fail-closed rollback and auditable import rows. Customer, product, order, invoice, payment/transaction, discount, product media and module-gated subscription/service-instance writes are covered. Source-specific fixtures, independent post-fix review and provider acceptance remain external verification work.

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
- Customer, product, order, invoice, payment/transaction, discount, product media and module-gated subscription/service-instance mappings now have transaction-safe writes, source-isolated dependencies, validated currency and accounting fields, identity reservations and fail-closed rollback coverage.
- Source-specific fixtures, MariaDB/concurrency verification for the extended import chain, independent post-fix review and provider acceptance remain required.

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
- Backup storage writes are verified before success is reported, MySQL credentials use a short-lived defaults file instead of a process environment variable, and the Admin backup view rechecks authorization during Livewire renders.
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

- full SQLite application suite: 938 tests, 12,919 assertions;
- upgrade suite: 14 tests, 132 assertions;
- Release archive build and extracted-release smoke: passed;
- backup/restore smoke: passed;
- CycloneDX 1.5 dependency SBOM generated and validated with 203 components;
- local Playwright browser matrix: 24 tests passed against a prepared E2E server, including desktop/mobile responsive, checkout and keyboard/accessibility flows;
- GitHub Actions full matrix for commit `f36a777` (run `33129833665`): PHP 8.3, PHP 8.4, browser, native-linux, release-artifact, MariaDB feature/upgrade/concurrency/large-data: passed;
- Full PHPStan with `APP_ENV=testing` and a 1 GB CLI memory limit, targeted Pint, Blade cache, Vite build, npm production audit, PHP syntax and diff-check: passed on the current worktree.

Still required before a release tag:

- independent post-fix security review after the current hardening changes; the preceding review found concrete findings and is not a release approval;
- MariaDB multi-process proof is now green in GitHub Actions run `33129833665`, including the import identity race regression after the current-read reservation fix. Local MariaDB remains unavailable, so this evidence is CI-host evidence rather than local-host evidence.
- actual provider-specific implementations and acceptance tests for external payment, shipping, registrar, DNS and provisioning providers, or an explicit post-release deferral; all 16 optional adapters are marked `production_ready: false` and cannot be installed, enabled or booted outside local/testing environments;
- real Namecheap/Cloudflare sandbox status matrix;
- authenticated Admin desktop browser review of the Domain extension catalog and product Automation surface: passed; full human responsive/keyboard review remains open;
- live external webhook receiver acceptance;
- final legal/privacy sign-off and third-party attribution review; dependency license metadata is present in `composer.lock` for 155 packages and npm production packages report SPDX licenses, but legal approval of upstream data terms is still an operator responsibility;

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
