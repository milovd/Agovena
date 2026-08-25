# Core

Agovena **Core** is the generic platform and commerce orchestration layer.

Implementation lives in the Laravel application (`app/`, `routes/`, Core migrations, shared Admin/customer contracts). This directory is the conceptual boundary documentation - not a separate Composer package tree.

## Owns

- Users, customers, addresses, roles/permissions
- Catalog products, categories, media, Product Options, Custom Properties
- Cart, checkout requirements composition, orders
- Payments abstraction (`PaymentGateway` contracts), invoices, credit notes, refunds, credits
- Module / Extension / Theme runtime (discovery, enable, settings, health)
- Installer, updates/schema status, audit, notifications catalogue, public `/api/v1`

## Does not own

- Provider SDKs or provider-specific branching (`if ($gateway === 'mollie')`)
- Module domain models imported into Core when a contract/resolver exists
- Theme Blade business orchestration

## Composition example

A hosting subscription product is typically:

- **Core** - Order / Payment / Invoice
- **Modules** - Subscriptions + Provisioning
- **Extension** - e.g. Pterodactyl (provisioner adapter)
- **Theme** - storefront presentation

Merchant-facing “Hosting & Provisioned Services” is a selling intent / preset over those capabilities, not a Core `store_type`.
