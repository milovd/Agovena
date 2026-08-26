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
- Core and optional package worktrees contain local changes. They must not be reset without an explicit decision.
- The full application suite has passed in the current worktree. Passing automated tests do not prove external provider or authenticated browser behavior.

## Implemented or feature-tested foundations

| Area | Status | Evidence or boundary |
|---|---|---|
| Core catalog, cart, checkout, orders, invoices | implemented | Feature coverage exists in the application suite. |
| Refunds, credit notes, payment attempts, fee snapshots and webhook contracts | implemented | Automated idempotency, signature, fee pass-through and invoice snapshot tests exist. |
| Inventory reservations and provisioning seams | partial | Atomic stock reservations, idempotent cancellation release, queue retry propagation and manual-review transitions are covered; MariaDB multi-process proof and live provider failure review remain release gates. |
| Subscriptions and recurring renewal seams | partial | Automated lifecycle coverage exists; provider-specific recurring behavior remains capability-bound. |
| Account security, TOTP, recovery and sessions | implemented | Customer security flows and automated coverage exist. |
| Audit logging | implemented | Capture, redaction, integrity metadata, filters, export and retention command paths are covered. |
| Customer notifications and preferences | implemented | In-app center, unread counts, email/in-app policy and browser push foundation are feature-tested. |
| Outbound webhooks and destinations | partial | Generic signed HTTPS and Discord-compatible payload destinations, management, persistence, retries and SSRF checks exist; a real receiver and production queue verification are not verified here. |
| Default Theme and theme customizer | partial | Default storefront and Admin chrome exist; the simple section-based page editor still needs release acceptance. |
| Module and Extension lifecycle | implemented | Discovery, dependencies, install, enable, disable and package tests exist. |
| Paddle and Tebex package catalog registration | implemented | Both packages exist in optional-packages and are registered in Core catalog configuration. |

## Release work still required

### Domain sales

`partial`: the generic `domains` Module provides the product capability, order-paid idempotent records, lifecycle statuses, registrar registry, Admin list and customer-owned list. The `hosting` preset enables `domains` together with `provisioning` and `subscriptions`; it does not install a registrar provider or activate provider credentials.

The Cloudflare Registrar extension is registered outside the `domains` Module and depends on it. It supplies provider-specific account/token settings, availability and registration API calls, and explicit capability metadata. Disabling the extension preserves generic domain records but removes Cloudflare actions; disabling the module removes the domain surface while preserving its data.

The optional package catalog also contains a separate `namecheap-registrar` extension. It uses the same registrar contract and currently exposes only availability checks, registrations and renewals. Its XML transport and response mapping are automated-test covered, but no live Namecheap sandbox flow has been run here. It does not claim DNS, nameserver, transfer or contact-update support.

Domain records snapshot `registrar_key` and `dns_provider_key` independently. This allows a supported combination such as Namecheap for registration and Cloudflare for DNS without coupling registrar billing, renewals or transfers to DNS hosting. The `domains` module now exposes a separate DNS-provider contract and registry; a DNS provider extension is still required before DNS actions can be called.

- availability and price checks via `domain-check`;
- new registrations via `registrations`;
- encrypted account/token settings through the existing ExtensionSettingsRepository;
- explicit capability metadata for `availability_check` and `registration`;
- explicit unsupported behavior for renewals while the Cloudflare API beta does not expose renew endpoints.

Transfers, contact updates, renewals, supported-TLD policy, billing-profile setup and real sandbox registration remain provider verification gates. IDNs and unsupported extensions must not be promised.

### Import and migration tooling

`missing` as a complete release capability. The roadmap requires:

- Paymenter import for customers, services, invoices, products and provider mappings;
- WHMCS import for clients, services, invoices, transactions and subscriptions;
- WooCommerce import for products, customers, orders, coupons and media mappings;
- Shopify import for products, customers, orders, discounts and content handoff;
- CSV/custom import with field mapping, validation, dry-run, duplicate detection, rollback and audit history.

### Payment foundation

`partial` and `mock-tested-only` for external providers.

- Provider-neutral fee pass-through now snapshots the selected policy on orders and invoices.
- Saved payment method consent, removal, re-authentication and provider revocation are covered by the existing gateway and renewal suites.
- Keep Paddle, Tebex, Mollie, Stripe and PayPal status honest until real sandbox checks are completed.

### Migration and import

`partial`.

- Provider-neutral CSV/custom preview now supports header mapping, adapter validation, malformed-row reporting and duplicate detection without writing domain data.
- Source-specific customer, service, invoice, product, discount, media and subscription mappings, durable import audit records, domain writes, rollback and provider acceptance fixtures remain required.

### Referrals

`partial`.

- Configureerbare policy, normalized codecreatie, self-referral blocking en idempotente checkoutattributie bestaan.
- Customer/admin beheer, reward/credit ledger, expiry/limits en fraud review blijven vereist.

### Merchant trust and identity

`partial` or `missing` until each item has code and tests:

- OAuth/OIDC state/nonce storage and first-party Google/Discord provider metadata are feature-tested; callback token exchange, account linking, Auth/OAuth Admin settings and end-to-end login remain open;
- Google and Discord login;
- Turnstile/reCAPTCHA endpoint adapters now fail closed on missing configuration, invalid responses and provider timeouts; request policy wiring, rate limits, IP reputation, bans, whitelists and recovery remain open;
- name and IP validation;
- repeated or disposable IP detection;
- temporary and permanent suspensions;
- account and IP blocking, whitelists and historical IP logs;
- CLI recovery when Admin is unavailable;
- optional VPN, proxy and Tor reputation checks without a default hard block;
- complete maintenance mode and recovery route;
- complete 403, 404, 405, 419, 429, 500, 503 and 505 error pages.

### Backups, privacy and operational recovery

`partial`.

- The extracted release has a backup and restore smoke path, plus a first-party artifact verifier for `.env`, SQLite and private/public storage paths.
- Automatic database backups, retention, failure alerts and a documented production restore procedure still require implementation and verification.
- Cookie banner, cookie settings, consent history, privacy retention and legal page review remain release work.
- Backup verification must cover the real MariaDB and storage deployment responsibilities, not only a temporary SQLite artifact.

### Storefront, SEO and data export

`partial` or `missing` until verified:

- simple section-based page editor with safe exportable definitions;
- robots.txt and sitemap.xml now expose only public storefront URLs and are feature-tested.
- canonical metadata, Open Graph, structured data, redirects, permission-scoped exports, section editor and privacy/consent flows remain release work;
- responsive, keyboard and authenticated browser review for the Default Theme.

### Notifications and destinations

`partial`.

- Generic signed HTTP webhook management exists.
- Discord-compatible destination formatting and Admin destination allowlisting are feature-tested; live receiver acceptance, production queue verification and broader destination policies remain release work.
- Browser push requires configured VAPID material and a real browser/provider run before it can be called sandbox-verified.

### Release packaging and gates

Still required before a tag:

- fresh install and upgrade from representative schema states;
- release archive build on the supported release environment;
- extracted artifact smoke and backup/restore smoke;
- Composer tests, analysis, lint and frontend build;
- optional-packages contract and lifecycle tests;
- Playwright flows for login, product, cart, checkout, payment, webhook, invoice, refund, stock race and mobile navigation;
- dependency, CVE, license, SBOM, third-party attribution and tracked-file scans;
- secret and generated-file review;
- tenant and authorization checks for detail, list, export, update and delete paths;
- sandbox provider status matrix;
- human review on desktop, tablet, mobile and keyboard-only navigation.

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
