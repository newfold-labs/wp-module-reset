---
name: wp-module-reset
title: Dependencies
description: Composer packages the module relies on and why.
updated: 2025-03-25
---

# Dependencies

## Runtime (`require`)

| Package | Purpose |
|---------|---------|
| **newfold-labs/wp-module-features** (^1.5) | Base **`Feature`** class and registration pattern used by **`ResetFeature`**. |

The module also depends on **WordPress** and the **Newfold module loader / container** at runtime in host plugins (`BrandConfig` calls `NewfoldLabs\WP\ModuleLoader\container()`).

## Development (`require-dev`)

| Package | Purpose |
|---------|---------|
| **newfold-labs/wp-php-standards** | PHPCS ruleset (project `phpcs.xml` references **Newfold**) |
| **wp-cli/i18n-command** | `composer run i18n*` scripts (`wp i18n make-pot`, etc.) |
| **johnpbloch/wordpress** | WordPress core for tests / tooling |
| **lucatume/wp-browser** | Codeception WordPress integration (WPUnit) |
| **phpunit/phpcov**, **yoast/phpunit-polyfills** | Coverage and PHPUnit compatibility |

Routine transitive dependencies are omitted here unless they affect how you develop or release the module.
