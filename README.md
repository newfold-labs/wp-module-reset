# wp-module-reset

Factory Reset module for Newfold WordPress plugins. Provides a destructive, irreversible "factory reset" that restores a WordPress site to a near fresh-install state.

## Module Responsibilities

- Register a **Tools > Factory Reset Website** admin page with a confirmation flow
- Register a **Factory Reset** section at the bottom of the brand plugin settings page (via `wp-module-settings`)
- Provide a REST API endpoint (`POST /{brand}/v1/factory-reset`) for programmatic resets
- Execute the two-phase reset process (prepare in one request, execute in a clean follow-up request)

## Entry Points

The feature is accessible from two places:

1. **Settings page** — A "Factory Reset" section at the very bottom of the brand plugin's settings page. It links to the standalone Tools page.
2. **Tools submenu** — A standalone admin page at `Tools > Factory Reset Website` (`wp-admin/admin.php?page={brand}-factory-reset-website`).

## What Gets Reset

When a reset is executed, the following happens in order:

### Phase 1 (runs with all plugins still loaded)
1. Install the brand default theme from WordPress.org (safety gate — aborts if this fails)
2. Deactivate all plugins except the brand plugin at the DB level
3. Delete all MU plugins
4. Delete all WordPress drop-in files (`object-cache.php`, `advanced-cache.php`, etc.)

### Phase 2 (runs on a clean request with only the brand plugin active)
5. Delete all third-party plugin files
6. Delete all third-party theme files
7. Clean extra files/folders from `wp-content/` (except `plugins/`, `themes/`, `uploads/`, `mu-plugins/`, `index.php`)
8. Empty the `wp-content/uploads/` directory (directory is preserved, contents deleted)
9. Drop all database tables and run `wp_install()` to recreate core tables
10. Restore preserved values (see below)
11. Reinstall WordPress core files (fresh copy of the current version)
12. Reinstall the brand default theme (fresh copy from WordPress.org)
13. Restore Hiive/NFD connection data and enable coming soon mode
14. Verify the site passes fresh-install detection
15. Restore the admin session so the user stays logged in

### What Gets Preserved

The following values are saved before the reset and restored afterward:

| Value | Why |
|---|---|
| Site title (`blogname`) | Passed to `wp_install()` |
| Site URL (`siteurl`) and Home URL (`home`) | Restored after DB reset |
| Site visibility (`blog_public`) | Passed to `wp_install()` |
| Language (`WPLANG`) | Passed to `wp_install()` |
| Admin username, email, and password hash | User is recreated with original credentials |
| `nfd_data_token` | Hiive auth token — preserves hosting platform connection |
| `nfd_data_module_version` | Data module version for upgrade handler |
| `nfd_data_connection_attempts` | Connection retry counter |
| `nfd_data_connection_throttle` (transient) | Connection rate limiting |

### What Gets Deleted

- All posts, pages, comments, and custom post types
- All options (except those restored above)
- All custom database tables
- All users except the admin who initiated the reset
- All plugins except the brand plugin
- All themes except the brand default theme
- All uploaded media files
- All MU plugins and drop-in files
- All extra files in `wp-content/` (caches, backups, etc.)
- All staging data (cleared with the database reset)

## Confirmation / Safety

- The confirmation screen explains everything that will happen and warns that the action is irreversible
- The user must type their full site URL (similar to GitHub's "type repo name to delete" pattern) before the submit button becomes enabled
- The submit button is disabled by default — JavaScript enables it only when the URL matches exactly
- Server-side validation re-checks the URL even if the client-side check is bypassed
- Nonce verification protects against CSRF
- `manage_options` capability is required (administrators only)
- Multisite installations are explicitly blocked

## Brand Compatibility

The module uses the Newfold module loader container for all brand-specific values. No brand names are hardcoded in the application logic.

| Value | Source |
|---|---|
| Brand ID (e.g. `bluehost`) | `container()->plugin()->id` |
| Plugin basename | `container()->plugin()->basename` |
| Plugin display name | `container()->plugin()->name` |
| Page slug | `{brand_id}-factory-reset-website` |
| REST namespace | `{brand_id}/v1` |
| Default theme | Mapped per brand in `BrandConfig::$themes` |

Currently supported brands: **Bluehost** (`bluehost-blueprint` theme), **HostGator** (`flavor` theme). To add a new brand, add an entry to the `$themes` array in `includes/Data/BrandConfig.php`.

## Installation

Add the Newfold Satis repository to your `composer.json` if not already present:

```json
"repositories": {
    "newfold": {
        "type": "composer",
        "url": "https://newfold-labs.github.io/satis/"
    }
}
```

Then require the module:

```bash
composer require newfold-labs/wp-module-reset
```

The module registers itself via the Newfold features system automatically.

## Development

### Linting

```bash
composer run lint
composer run fix    # auto-fix
```

### Translations (i18n)

The text domain is `wp-module-reset`. All user-facing strings are wrapped in `__()`, `esc_html__()`, etc.

```bash
composer run i18n-pot   # generate POT file
composer run i18n       # full i18n pipeline (POT + PO + MO + PHP)
```

### Testing

See [TESTING.md](TESTING.md) for the full QA guide covering both automated and manual tests.

```bash
composer run test              # run automated test suite
composer run test-coverage     # run with code coverage
```
