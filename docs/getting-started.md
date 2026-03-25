---
name: wp-module-reset
title: Getting started
description: Prerequisites, installing the module, and running tests locally.
updated: 2025-03-25
---

# Getting started

## Prerequisites

- **PHP** 7.4+ and **Composer**
- A **WordPress** environment if you run integration-style tests (Codeception WPUnit loads WordPress; see `codeception.dist.yml` and `.env.testing` / `.env.testing.example` if present)
- **Node/npm** is not required for this module’s runtime (no frontend build)

## Install as a dependency

Brand plugins add the package via Composer (Newfold Satis), for example:

```json
"require": {
  "newfold-labs/wp-module-reset": "^1.0"
}
```

The module’s `bootstrap.php` is autoloaded via Composer `files`; it registers `ResetFeature` on the `newfold/features/filter/register` filter.

## Local development clone

```bash
git clone <repository-url> wp-module-reset
cd wp-module-reset
composer install
```

Ensure **PHPCS cache** can be written (the ruleset uses `.cache/phpcs.json`) or create `.cache` before `composer run lint`.

## Run tests

```bash
composer test
```

See [testing.md](testing.md) for coverage, configuration files, and CI.

## Translations

Text domain: **`wp-module-reset`**. Regenerate templates and derivatives:

```bash
composer run i18n-pot   # POT only
composer run i18n       # full pipeline
```

See [development.md](development.md) for details.
