<p align="center">
  <img src="agovena_banner.png" alt="Agovena" width="100%">
</p>

<p align="center">
  <strong>Open-source commerce, built to stay modular.</strong><br>
  Run physical, digital and service-based commerce on infrastructure you control.
</p>

<p align="center">
  <a href="https://agovena.com">Website</a> ·
  <a href="https://agovena.com/docs">Documentation</a> ·
  <a href="https://agovena.com/marketplace">Marketplace</a> ·
  <a href="https://agovena.com/development">Developers</a>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="MIT License"></a>
  <a href="https://github.com/milovd/Agovena/stargazers"><img src="https://img.shields.io/github/stars/milovd/Agovena?style=flat" alt="GitHub stars"></a>
</p>

## About Agovena

Agovena is an early-stage, self-hosted commerce platform built with Laravel. It provides a shared commerce Core and lets you add capabilities through Modules, provider integrations through Extensions, and presentation through Themes.

The platform is designed for more than one selling model. You can combine physical products, digital goods, downloads, subscriptions, domains, events and provisioned services without locking the store into one permanent type.

Agovena is not production-ready for every provider, host or deployment environment. Read the documentation, test the flows you need in an isolated environment, and review the limitations before accepting live orders.

## Start here

| You want to... | Go to |
|---|---|
| Learn what Agovena is | [Introduction](https://agovena.com/docs) |
| Install a store | [Installation guide](https://agovena.com/docs/installation) |
| Set up a first store | [Getting started](https://agovena.com/docs/getting-started) |
| Browse Modules and Extensions | [Marketplace](https://agovena.com/marketplace) |
| Build a Module, Extension or Theme | [Developer documentation](https://agovena.com/development) |
| Run Agovena locally | [Contributing guide](CONTRIBUTING.md) |
| Report a security issue | [Security policy](SECURITY.md) |

The website is the canonical home for installation, operator and developer documentation. This repository keeps source code, contribution policy, security policy and release history close to the code.

## Repositories

- [Agovena Core](https://github.com/milovd/Agovena): the Laravel application and shared commerce contracts.
- [Optional packages](https://github.com/milovd/optional-packages): first-party Modules and Extensions.
- [Agovena website](https://github.com/milovd/agovena-site): the product website and documentation source.

Package identity comes from each `module.json` or `extension.json` manifest. Optional packages are not permanent business types and should remain separate from Core.

## Development

Agovena uses Laravel, Livewire, Blade, Alpine, Vite and native CSS. For a contributor checkout:

```bash
composer install
npm ci
npm run build
```

Then follow the [contributing guide](CONTRIBUTING.md) and the [developer documentation](https://agovena.com/development) for the relevant setup and architecture context.

## Community and contribution

- [Join the Agovena Discord](https://discord.gg/W2eJzwsfC6)
- [Read the contribution guide](CONTRIBUTING.md)
- [Read the Code of Conduct](CODE_OF_CONDUCT.md)
- [Browse open issues](https://github.com/milovd/Agovena/issues)

Please do not include secrets, credentials, tokens or private customer data in issues, pull requests or support requests.

## License and attribution

Agovena is released under the [MIT License](LICENSE). Third-party data sources used by optional features are documented in [ATTRIBUTION.md](ATTRIBUTION.md).

See [CHANGELOG.md](CHANGELOG.md) for public product changes.
