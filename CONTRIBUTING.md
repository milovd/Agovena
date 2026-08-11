# Contributing to Agovena

Thanks for taking an interest in Agovena.

## Current stage

The project is early but has a runnable Laravel foundation, Admin product management, and a default Theme storefront checkout slice. Useful help right now:

- Issues that point out gaps or confusing bits
- Discussion around module / extension / theme boundaries
- Focused code improvements with tests

## Ground rules

- Keep the core small; prefer modules and extensions over bloating everything
- Respect the split: core / modules / extensions / themes
- Prefer simple, explicit code
- No secrets in the repo
- Don’t describe unfinished work as shipped

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

## Security

See [SECURITY.md](SECURITY.md). Don’t file public issues for vulnerabilities.

## Code of conduct

Be decent. Full text: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
