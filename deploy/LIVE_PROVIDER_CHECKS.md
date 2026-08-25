# Live provider verification checklists

Statuses used in Agovena:

| Label | Meaning |
|-------|---------|
| **MOCK-TESTED** | Automated fakes/mocks in CI only |
| **SANDBOX-VERIFIED** | Real provider **test/sandbox** API exercised successfully |
| **PRODUCTION-VERIFIED** | Real **live** money / production API exercised (not required for v0.1 RC) |

CI never runs live or sandbox charges. Connection-only checks:

```bash
php artisan agovena:verify-providers
php artisan agovena:verify-providers mollie --sandbox
```

`--sandbox` refuses Mollie `live_` keys. These commands **never** create payments.

Do **not** put credentials in this repository. Never log API keys, Authorization headers, or secret response fields.

---

## READY FOR CREDENTIALS - Mollie (first sandbox target for v0.1 RC)

**Current status: MOCK-TESTED ONLY**

Blocked on operator-supplied **test** credentials only. Do not invent keys. Do not use `live_` keys. Do not create real-money charges.

### Required input

| What | Where |
|------|--------|
| Mollie **test** API key (`test_…`) | Admin → Extensions → Mollie → **API key**, **or** env `AGOVENA_EXT_MOLLIE_API_KEY` |
| Mollie profile in **test mode** | Mollie Dashboard |
| Reachable HTTPS webhook | `https://<your-host>/webhooks/payments/mollie` (or Mollie/local tunnel guidance for test mode) |

Report string when missing: **READY FOR MOLLIE TEST API KEY**

### Operator workflow (after `test_` key is available)

1. Enable Mollie Extension; save `test_` key (leave blank after save to keep stored secret).
2. `php artisan agovena:verify-providers mollie --sandbox` - expect OK, mode **test**, methods discovered, no secret leakage.
3. Storefront checkout with Mollie methods; complete one **test** hosted payment.
4. Walk the checklist below. Mark each item only when observed against the real Mollie test API.
5. On full success for A–J (and K–L if safely testable): set Mollie status to **SANDBOX-VERIFIED** (never PRODUCTION-VERIFIED from test mode).

### Mollie checklist

**A. Connection**

- [ ] Credentials accepted
- [ ] Health / `agovena:verify-providers mollie --sandbox` OK
- [ ] No secret leakage in UI, logs, or command output

**B. Payment method discovery**

- [ ] Methods load from Mollie
- [ ] Merchant restrictions respected
- [ ] Checkout renders normalized methods

**C. Successful hosted payment**

- [ ] Order → Payment → PaymentAttempt → Mollie checkout → return → webhook/status sync
- [ ] Payment paid → Order paid → Invoice paid
- [ ] Downstream fulfillment exactly once

**D. Return before webhook**

- [ ] Customer returns first; order is **not** falsely marked paid until webhook/status sync

**E. Webhook before return**

- [ ] Webhook confirms payment first; return page reconciles cleanly

**F. Duplicate webhook**

- [ ] No duplicate OrderPaid / fulfillment effects

**G. Cancelled payment**

- [ ] Normalized correctly; customer can retry where allowed

**H. Failed / expired payment**

- [ ] Normalized correctly; no false paid state

**I. Partial refund**

- [ ] Real Mollie **TEST** refund if sandbox supports it; Refund + Payment status coherent

**J. Full refund**

- [ ] Real Mollie **TEST** refund if supported

**K. Recurring authorization**

- [ ] If Mollie test mode supports mandate/sequence flow: reusable authorization created
- [ ] If not safely testable: document “not proven in Mollie test mode” (do not fake)

**L. Recurring renewal charge**

- [ ] If safely testable: Subscription renewal via generic recurring seam
- [ ] If not: document explicitly

---

## Stripe (Payment Extension)

Status: **MOCK-TESTED ONLY** (not required for first RC if Mollie is SANDBOX-VERIFIED).

- [ ] Successful hosted Checkout Session
- [ ] Cancelled / failed / webhook / duplicate / delayed webhook
- [ ] Partial / full refund
- [ ] Recurring authorization / charge
- [ ] Timeout / invalid secrets fail without leaking secrets

## PostNL (Shipping Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Create / invalid address / label / tracking / duplicate / cancel / timeout / invalid credentials

## Pterodactyl (Provisioning Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Create / retry idempotent / poll / activate / power / suspend / unsuspend / resize / terminate / timeout / invalid credentials

## PayPal (Payment Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Connection / checkout redirect / capture or webhook confirm / refund / timeout / invalid credentials (no secret leakage)

## Proxmox VE (Provisioning Extension)

Status: **MOCK-TESTED ONLY**

- [ ] Create / poll / power / suspend / unsuspend / terminate / timeout / invalid credentials
