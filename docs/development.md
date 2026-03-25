---
name: wp-module-reset
title: Development
description: Lint, internationalization, and day-to-day workflow.
updated: 2025-03-25
---

# Development

## PHP standards

```bash
composer run lint    # PHPCS against phpcs.xml (Newfold standard)
composer run fix     # PHPCBF auto-fix
```

Create **`./.cache`** if PHPCS reports a missing cache directory (see `phpcs.xml` `cache` argument).

## Internationalization

- **Text domain:** `wp-module-reset`
- **Template:** `languages/wp-module-reset.pot`
- **Locale files:** `languages/wp-module-reset-*.po`

Commands:

```bash
composer run i18n-pot   # regenerate POT (excludes tests, vendor, node_modules, wordpress)
composer run i18n-po    # merge POT into PO files in languages/
composer run i18n-mo    # compile MO files
composer run i18n-php   # optional PHP translation files
composer run i18n       # run pot, po, mo, php in sequence
```

The **Check for Updates to Translations** GitHub workflow uses the reusable translations workflow with `text_domain: wp-module-reset` (see [workflows.md](workflows.md)).

## Versioning

Release versioning is documented in [release.md](release.md). Use the **Newfold Prepare Release** workflow when cutting releases so `package.json` and `bootstrap.php` stay in sync.

## Related docs

- [testing.md](testing.md) – test commands
- [architecture.md](architecture.md) – where to hook new behavior
