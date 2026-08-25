# Core

Agovena **Core** is the generic platform and commerce orchestration layer.

Implementation lives in the Laravel application (`app/`, `routes/`, Core migrations, shared Admin/customer contracts). This directory is the conceptual boundary documentation - not a separate Composer package tree.

## Owns

- Users, customers, addresses, roles/permissions
- Catalog products, categories, media, Product Options, Custom Properties / customer properties
- Cart, checkout requirements composition, orders
- Payments abstraction (`PaymentGateway` contracts), invoices, credit notes, refunds, **account balance** ledger (reserve / capture / release)
- Tax resolution settings (enable tax, automatic rates provider, merchant overrides) and currency records (including optional remote FX sync)
- Module / Extension / Theme runtime (discovery, install, enable, settings, health, package migrations)
- Installer, updates/schema status, audit, notifications catalogue, public `/api/v1`
- Auth challenge for TOTP (setup/UI for 2FA and sessions live in the customer Theme account area)

## Does not own

- Provider SDKs or provider-specific branching (`if ($gateway === 'mollie')`)
- Module domain models imported into Core when a contract/resolver exists
- Theme Blade business orchestration
- First-party Module/Extension source trees (those ship from [optional-packages](https://github.com/milovd/optional-packages))

## Composition example

A hosting subscription product is typically:

- **Core** - Order / Payment / Invoice
- **Modules** - Subscriptions + Provisioning
- **Extension** - e.g. Pterodactyl or Proxmox (provisioner adapter)
- **Theme** - storefront presentation

Merchant-facing “Hosting & Provisioned Services” is a selling intent / preset over those capabilities, not a Core `store_type`.
