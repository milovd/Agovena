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

`extensions/manual-payment` proves Extension lifecycle and PaymentGateway registration (manual + optional development). Mollie/Stripe are not bundled yet.
