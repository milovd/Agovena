# Live provider verification checklists

These providers are **mock-tested in CI**. This file is the future sandbox/live checklist.
Do not mark a provider live-tested until these steps actually run against a real sandbox or live API.

Do **not** put credentials in this repository.

## READY FOR CREDENTIALS — Mollie (first sandbox target)

Blocked on operator-supplied **test** credentials only. Do not invent keys. Do not create live charges.

Provide (out of band, never commit):

1. Mollie **test** API key (`test_…`) — set as Extension setting `api_key` or env `AGOVENA_EXT_MOLLIE_API_KEY`
2. A reachable HTTPS webhook URL for `/webhooks/payments/mollie` (or Mollie’s local tunnel guidance for test mode)
3. Confirmation the Mollie profile is in **test mode**

Then run the checklist below. Until that happens: **MOCK-TESTED ONLY**.

## Mollie (Payment Extension)

- [ ] Checkout method discovery
- [ ] Hosted payment creation
- [ ] Redirect to Mollie hosted page
- [ ] Return URL does **not** mark paid by itself
- [ ] Webhook confirmation marks paid
- [ ] Duplicate webhook (same event id) is idempotent
- [ ] Delayed webhook (return before webhook)
- [ ] Cancelled payment
- [ ] Failed payment
- [ ] Partial refund
- [ ] Full refund
- [ ] Recurring authorization / mandate (if test environment supports it)
- [ ] Recurring charge (if test environment supports it)
- [ ] Provider timeout / unreachable API
- [ ] Invalid API key fails health without leaking the key

Never store raw card data. Never log the API key.

## Stripe (Payment Extension)

Status: **MOCK-TESTED ONLY** (not required for first RC if Mollie sandbox is live-verified).

- [ ] Successful hosted Checkout Session
- [ ] Cancelled Checkout
- [ ] Failed payment
- [ ] Webhook after success
- [ ] Duplicate webhook
- [ ] Delayed webhook
- [ ] Partial refund
- [ ] Full refund
- [ ] Recurring authorization
- [ ] Recurring charge
- [ ] Provider timeout
- [ ] Invalid secret/webhook secret fails without leaking secrets

## PostNL (Shipping Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Valid shipment create
- [ ] Invalid address rejected safely
- [ ] Label retrieval
- [ ] Tracking
- [ ] Duplicate create
- [ ] Cancel where the API supports it
- [ ] Timeout
- [ ] Invalid credentials

## Pterodactyl (Provisioning Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Create server
- [ ] Retry create (idempotent external id)
- [ ] Install / poll until ready
- [ ] Activate
- [ ] Power action
- [ ] Suspend
- [ ] Unsuspend
- [ ] Plan resize
- [ ] Terminate
- [ ] Timeout
- [ ] Invalid credentials
