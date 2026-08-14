# Live provider verification checklists

These providers are **mock-tested in CI**. This file is the future sandbox/live checklist.
Do not mark a provider live-tested until these steps actually run against a real sandbox or live API.

Do **not** put credentials in this repository.

## Mollie (Payment Extension)

- [ ] Successful hosted payment
- [ ] Cancelled payment
- [ ] Failed payment
- [ ] Webhook after success
- [ ] Duplicate webhook (same event id)
- [ ] Delayed webhook (return URL before webhook)
- [ ] Partial refund
- [ ] Full refund
- [ ] Recurring authorization / mandate
- [ ] Recurring charge
- [ ] Provider timeout / unreachable API
- [ ] Invalid API key fails health without leaking the key

## Stripe (Payment Extension)

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

- [ ] Valid shipment create
- [ ] Invalid address rejected safely
- [ ] Label retrieval
- [ ] Tracking
- [ ] Duplicate create
- [ ] Cancel where the API supports it
- [ ] Timeout
- [ ] Invalid credentials

## Pterodactyl (Provisioning Extension)

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

Status until the boxes above are actually executed: **MOCK-TESTED ONLY**.
