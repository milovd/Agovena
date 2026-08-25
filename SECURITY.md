# Security Policy

## Supported versions

Agovena is in early development. Security fixes will target the latest `main` branch until stable releases exist.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Prefer one of these:

1. [GitHub private vulnerability reporting](https://github.com/milovd/Agovena/security/advisories/new) (if enabled on this repository)
2. Contact the maintainers privately once a security contact is published here

Include as much detail as you can: what you found, how to reproduce it, and the impact. We will try to respond within a reasonable time and coordinate a fix before any public disclosure.

## Account security (product)

- Customers and staff manage **two-factor authentication** and **sessions** under the customer account: `/account/security`.
- Privileged Admin access may require 2FA (`AGOVENA_PRIVILEGED_2FA`). Setup happens in the customer Security page, not a separate Admin-only Security tab.
- Never store card numbers in Core. Payment card entry happens on payment-provider hosted pages (Mollie, Stripe, PayPal Extensions).

## Please don’t

- Share exploit details in public issues, Discord, or social media before a fix is available
- Commit secrets, tokens, or production credentials to this repository
