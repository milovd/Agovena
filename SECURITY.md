# Security Policy

## Supported versions

Security fixes are released as patch versions of the latest release, for example `0.1.1` after `0.1.0`. Upgrade to the newest release to receive them. Older releases do not receive backports while Agovena is below 1.0.

## Reporting a vulnerability

Do not open a public issue, discussion or pull request for a security problem.

Report it privately through [GitHub private vulnerability reporting](https://github.com/milovd/Agovena/security/advisories/new). This also covers first-party Modules and Extensions from the [optional-packages](https://github.com/milovd/optional-packages) repository.

Include the affected version, the steps to reproduce and the impact you expect. We aim to respond within 7 days. We agree on a disclosure date once a fix is available and credit you in the advisory unless you prefer otherwise.

## Account security (product)

- Customers and staff manage **two-factor authentication** and **sessions** under the customer account: `/account/security`.
- Privileged Admin access may require 2FA (`AGOVENA_PRIVILEGED_2FA`). Setup happens in the customer Security page, not a separate Admin-only Security tab.
- Never store card numbers in Core. Payment card entry happens on payment-provider hosted pages (Mollie, Stripe, PayPal Extensions).

## Please don’t

- Share exploit details in public issues, Discord, or social media before a fix is available
- Commit secrets, tokens, or production credentials to this repository
