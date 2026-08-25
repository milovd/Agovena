# Third-party data attribution

Agovena uses a small set of remote datasets for optional Admin features (currency rate sync and automatic EU VAT rates). This file records what is used, where it comes from, and what the upstream projects and institutions state about license or reuse. It is documentation, not legal advice. Operators should confirm current terms with the linked sources before redistributing or reselling derived data.

## Summary

| Source | Used for | Software / service terms | Underlying data notes |
| --- | --- | --- | --- |
| [Frankfurter](https://github.com/lineofflight/frankfurter) (`api.frankfurter.app`) | Admin currency exchange-rate sync | MIT (project software) | Rates are sourced from the European Central Bank (ECB); ECB reuse conditions apply to the statistics |
| [vatnode/eu-vat-rates-data](https://github.com/vatnode/eu-vat-rates-data) | Automatic standard VAT rates when enabled | MIT (dataset publication) | EU-27 rates are derived from European Commission TEDB; confirm TEDB / Commission reuse terms separately |
| [jsDelivr](https://www.jsdelivr.com/) | CDN host for the vatnode JSON file | Free personal and commercial CDN use under [jsDelivr Terms of Use](https://www.jsdelivr.com/terms/terms-of-use) | jsDelivr does not replace the license of the hosted content |

---

## 1. Frankfurter (FX rates)

**What Agovena uses**

- HTTP `GET` against `https://api.frankfurter.app/latest` when an Admin user syncs exchange rates.
- Rates are written to `currencies.exchange_rate` for merchant review and display conversion. They are not treated as a silent checkout FX feed.

**Project**

- Site: https://frankfurter.dev/ (legacy/public API also documented at `api.frankfurter.app`)
- Source: https://github.com/lineofflight/frankfurter
- License of the Frankfurter software: **MIT** (Copyright (c) Hakan Ensari). Full text: https://github.com/lineofflight/frankfurter/blob/main/LICENSE

**Required / recommended attribution wording (software)**

MIT requires retaining the copyright notice and permission notice when redistributing substantial portions of the Software. Agovena does not vendor Frankfurter source; it calls the public HTTP API. When mentioning the integration, a fair short credit is:

> Exchange rates via [Frankfurter](https://github.com/lineofflight/frankfurter) (MIT).

**Underlying ECB statistics**

Frankfurter’s euro reference path is based on European Central Bank (ECB) euro foreign exchange reference rates. ESCB / ECB public statistics may be reused free of charge if the source is quoted and the statistics (including metadata) are not modified. See:

- https://www.ecb.europa.eu/stats/ecb_statistics/governance_and_quality_framework/html/usage_policy.en.html
- https://www.ecb.europa.eu/services/using-our-site/disclaimer/html/index.en.html
- https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html

ECB states that reference rates are published for information purposes only and strongly discourages using them for transaction purposes. Additional ECB website copyright conditions may apply when information is redistributed or sold (including informing users that the same information is available free of charge from the ECB). Check the linked pages for current wording.

**Suggested credit for ECB-sourced rates**

> Source: ECB statistics (via Frankfurter). Reference rates are for information only.

---

## 2. vatnode / eu-vat-rates-data (EU VAT rates)

**What Agovena uses**

- Standard VAT rates from the published JSON dataset when Automatic tax rates is enabled and no merchant country override exists.
- Default fetch URL (configurable):  
  `https://cdn.jsdelivr.net/gh/vatnode/eu-vat-rates-data@main/data/eu-vat-rates-data.json`
- Code entry point: `App\Agovena\Tax\VatnodeRemoteTaxRateProvider`

**Project**

- Dataset repo: https://github.com/vatnode/eu-vat-rates-data
- Methodology: https://vatnode.dev/data
- License of the published dataset / packages: **MIT** (Copyright (c) 2026 Iurii Rogulia). Full text: https://github.com/vatnode/eu-vat-rates-data/blob/main/LICENSE

**Upstream statement on attribution (dataset)**

vatnode’s methodology page states the dataset is MIT-licensed and, for commercial use of that dataset packaging, that product attribution is not required. MIT still requires preserving the copyright and permission notices when redistributing the Software (or substantial portions). Agovena fetches the JSON over HTTP rather than bundling the package; we still credit the project honestly in Admin help and here.

**Suggested credit for the dataset**

> Automatic VAT rates from [vatnode/eu-vat-rates-data](https://github.com/vatnode/eu-vat-rates-data) (MIT), sourced for EU-27 from European Commission TEDB.

**Underlying European Commission TEDB data**

EU-27 rates in the dataset are fetched from the European Commission Taxes in Europe Database (TEDB). TEDB is an official Commission information tool; national authorities supply much of the content, and TEDB is not a substitute for binding national law.

This repository does **not** claim a specific open license (for example CC BY) for TEDB SOAP responses or database contents on TEDB’s behalf. European Commission documents and website materials are often covered by the Commission’s reuse framework, but operators and redistributors should **check the official TEDB / Taxation and Customs Union legal notices and the Commission reuse decision** that apply to the exact data product they use:

- TEDB overview (Commission Taxation and Customs Union): search “Taxes in Europe Database TEDB” on https://taxation-customs.ec.europa.eu/
- Commission reuse of documents: Commission Decision 2011/833/EU and related notices on Commission sites

Until those terms are confirmed for your use case, treat TEDB reuse as: **verify official Commission terms before redistributing or commercially re-publishing TEDB-derived figures beyond consuming them for store tax configuration.**

The dataset is reference data for software integration. It is not tax or legal advice.

---

## 3. jsDelivr (CDN)

**What Agovena uses**

- Delivery of the vatnode `eu-vat-rates-data.json` file via `cdn.jsdelivr.net` (GitHub proxy path).

**Service**

- Site: https://www.jsdelivr.com/
- Terms: https://www.jsdelivr.com/terms/terms-of-use  
  (also mirrored in the jsDelivr GitHub repo)

**Terms summary (as of the Terms effective date May 30, 2026)**

- Free for personal and commercial use when use stays within the Terms.
- Content accessed through the CDN must also comply with the origin platform’s terms (for example GitHub) and with the license of the hosted files.
- jsDelivr retains IP in its Website and CDN service branding; the Terms do not grant a blanket right to reuse jsDelivr’s look and feel.
- No separate “must display jsDelivr logo” attribution clause was found in the Terms for ordinary CDN fetches; credit remains good practice.

**Suggested credit**

> VAT rate JSON delivered via [jsDelivr](https://www.jsdelivr.com/).

---

## Where this appears in the product

- Admin → Currencies: sync help and flash messages name Frankfurter / ECB.
- Admin → Settings / Taxes: automatic tax help names vatnode, EC TEDB, and jsDelivr delivery where relevant.

Code that performs the fetches lives under `app/Agovena/Money/` and `app/Agovena/Tax/`. Default remote URLs are also noted in `config/agovena.php`.
