# Extensions

Provider integrations that plug into Agovena capabilities. Distinct from Modules.

- **Modules** add platform capabilities/domains (inventory, shipping, digital, …).
- **Extensions** integrate external providers into those capabilities (payment gateways, carriers, provisioners, …).

## Distribution

Composer / GitHub oriented. Do **not** execute arbitrary uploaded PHP ZIPs.

## Layout

```
extensions/{id}/
  extension.json
  src/
```

Lifecycle: discover → install → enable / disable → uninstall. Disable preserves settings and data.

## Categories

`payment_gateway`, `provisioning`, `shipping`, `authentication`, `storage`, `notifications`, `analytics`, `tax`, `other`

## Reference

`extensions/manual-payment` is the lifecycle reference adapter (manual + optional development).
`extensions/mollie` is the first production Payment Extension: hosted checkout, webhooks, refunds, and status sync behind the generic `PaymentGateway` contracts.
`extensions/pterodactyl` is the first production Provisioning Extension: panel lifecycle behind the generic `Provisioner` contracts.

## Implementing a Payment Extension

1. Add `extensions/{id}/extension.json` with `category: payment_gateway`
2. Implement `App\Agovena\Payments\Contracts\PaymentGateway`
3. Register it from `Extension::register()` via `$context->paymentGateway(...)`
4. Store secrets with `$context->setting(..., secret: true)` — encrypted, never redisplayed, never logged
5. Optional seams: `OffersCheckoutMethods`, `SynchronizesPayments`, `CancelsPayments`, `ChargesRecurringPayments`
6. Return URLs are UX only. Verify provider status by fetching the provider resource (or the provider’s documented webhook model). Do not trust customer-supplied status query params.
7. Tests must fake the provider HTTP/SDK. CI must not require live credentials.

## Implementing a Provisioner Extension

1. `category: provisioning`
2. Implement `Provisioner` plus optional `ProvisionerLifecycle`, `ProvisionerActions`, `ProvisionerPanel`, `ConfiguresProvisionedProducts`
3. Register via `$context->provisioner(...)`
4. Product mapping belongs in Extension-owned `provider_settings` on the provisionable capability — never Core columns such as vendor ids
5. Disable preserves Service Instance data

Mollie/Stripe/Pterodactyl-specific types must not appear in Core or Modules.
