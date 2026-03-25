# wp-module-reset documentation

Documentation for the **newfold-labs/wp-module-reset** package (factory reset for Newfold brand plugins).

## Table of contents

| Doc | Description |
|-----|-------------|
| [overview.md](overview.md) | What the module does, capabilities, and safety notes |
| [getting-started.md](getting-started.md) | Prerequisites, install via Composer, running tests |
| [architecture.md](architecture.md) | Loader registration, two-phase reset, brand integration |
| [backend.md](backend.md) | PHP layout: services, admin, API, data |
| [api.md](api.md) | REST route, parameters, responses, permissions |
| [dependencies.md](dependencies.md) | Composer dependencies and how they are used |
| [development.md](development.md) | Lint, i18n, day-to-day workflow |
| [testing.md](testing.md) | WPUnit (Codeception), coverage, CI, Playwright via brand plugin, manual QA |
| [workflows.md](workflows.md) | GitHub Actions (coverage, Playwright, release, Satis) |
| [release.md](release.md) | Version locations, prep-release workflow, build step |
| [reference.md](reference.md) | Transients, notable options, hooks overview |
| [changelog.md](changelog.md) | Release history / notable changes |

## Quick links by role

- **New to the repo** → [overview.md](overview.md), then [getting-started.md](getting-started.md).
- **Changing reset behavior or admin UI** → [architecture.md](architecture.md), [backend.md](backend.md).
- **REST consumers** → [api.md](api.md).
- **Shipping a release** → [release.md](release.md), [changelog.md](changelog.md).
