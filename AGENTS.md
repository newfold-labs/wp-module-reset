# Agent guidance – wp-module-reset

Short orientation for AI agents and developers. Full detail lives in **docs/**; start with **docs/index.md**.

## What this project is

**wp-module-reset** is a Newfold Labs Composer package that adds a **factory reset** flow to brand WordPress plugins (Bluehost, HostGator, etc.). It registers with the **Newfold Module Loader** via the features filter, exposes a **Tools → Factory Reset Website** admin page (PHP-rendered), and a **REST API** endpoint for programmatic reset. The reset is destructive (database, uploads, third-party plugins/themes) while preserving the admin user, site URLs, and brand/hosting connection data where configured.

## Stack

- **PHP** 7.4+ (platform constraint in `composer.json`; aligns with WordPress 6.6+ in PHPCS config)
- **WordPress** – hooks, REST API, filesystem, upgrader APIs
- **No React build** – admin UI is server-rendered PHP in `includes/Admin/ToolsPage.php`
- **Tests** – Codeception wp-browser (WPUnit suite)

## Key paths

| Purpose | Location |
|--------|----------|
| Version constants, feature registration | `bootstrap.php` |
| Feature flag / init | `includes/ResetFeature.php`, `includes/Reset.php` |
| Admin Tools page (two-phase form flow) | `includes/Admin/ToolsPage.php` |
| REST API registration | `includes/Api/RestApi.php`, `includes/Api/Controllers/ResetController.php` |
| Reset orchestration | `includes/Services/ResetService.php` |
| Brand-specific config (slug, theme, REST namespace) | `includes/Data/BrandConfig.php` |
| Capabilities / REST permission helpers | `includes/Permissions.php` |
| Translations (text domain `wp-module-reset`) | `languages/` |
| PHP coding standard | `phpcs.xml` |
| WPUnit tests | `tests/wpunit/` |
| CI / release workflows | `.github/workflows/` |

## Essential commands

```bash
composer install          # dependencies (includes wp-php-standards, wp-browser)
composer run lint         # PHPCS (requires .cache/ or adjust phpcs cache path)
composer run fix          # PHPCBF auto-fix
composer test             # Codeception WPUnit: codecept run wpunit
composer test-coverage    # coverage (see docs/testing.md)
composer run i18n-pot     # regenerate languages/wp-module-reset.pot
composer run i18n         # pot + update-po + make-mo + make-php
```

Copy `.env.testing` from your team’s template if present; Codeception reads `.env.testing` per `codeception.dist.yml`.

## Documentation

- **Full documentation:** **docs/** – table of contents in **docs/index.md**.
- **CLAUDE.md** in this repo should be a **symlink to AGENTS.md** (for Cursor/Claude). If symlinks are not preserved on your OS, duplicate the content or recreate the link.

## Keeping documentation current

When you change code, features, or workflows, update the docs so they stay accurate.

- **Keep docs/index.md current:** when you add, remove, or rename files under `docs/`, update the table of contents (and any quick links).
- Prefer updating the right doc in **docs/** over leaving stale text.
- Examples: new admin behavior → **overview.md**, **backend.md**, or **architecture.md**; new REST args or routes → **api.md**; new Composer deps → **dependencies.md**; CI or release changes → **workflows.md** / **release.md**; test layout or commands → **testing.md**.
- For versioned releases, record user-visible changes in **docs/changelog.md** (and align with GitHub releases when applicable).
