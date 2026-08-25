# Contributing to Agovena

Thanks for taking an interest in Agovena.

## Current stage

The project is early but has a runnable Laravel Core, Admin, default Theme storefront (catalog through checkout and customer account), and first-party Modules/Extensions via [optional-packages](https://github.com/milovd/optional-packages). Useful help right now:

- Issues that point out gaps or confusing bits
- Discussion around Module / Extension / Theme boundaries
- Focused code improvements with tests

## Ground rules

- Keep Core small; prefer Modules and Extensions over bloating everything
- Respect the split: Core / Modules / Extensions / Themes
- First-party packages live in **optional-packages**, not as permanent trees under Core `modules/` or `extensions/`
- Prefer simple, explicit code
- No secrets in the repo
- Do not describe unfinished work as shipped
- Do not use em dashes (`—`) in UI copy, docs, comments, or commits (use commas, colons, parentheses, or spaced hyphens)

## Commits

Prefer [Conventional Commits](https://www.conventionalcommits.org/) that describe the **actual product change**:

- `feat: add admin settings`
- `fix: prevent duplicate payment recording`
- `refactor: extract admin navigation registry`
- `test: add checkout authorization coverage`
- `chore: update dependencies`

Group related work into meaningful commits. Avoid noise commits for tiny edits; also avoid squashing unrelated work into one mega-commit.

## Pull requests

- Keep PRs focused
- Explain why the change is needed
- Use the PR template when present
- Update [CHANGELOG.md](CHANGELOG.md) / operator docs when behavior merchants rely on changes

## Local packages

For Core + packages side by side:

```env
AGOVENA_OPTIONAL_PACKAGES_PATH=../optional-packages
AGOVENA_PACKAGES_MONOREPO_URL=https://github.com/milovd/optional-packages
```

## Security

See [SECURITY.md](SECURITY.md). Do not file public issues for vulnerabilities.

## Code of conduct

Be decent. Full text: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
