---
name: wp-module-reset
title: Testing
description: WPUnit tests, manual QA, coverage, CI, and Playwright via the brand plugin.
updated: 2025-03-25
---

# Testing

This guide covers **automated** tests (Codeception WPUnit), **CI**, **Playwright** (via the brand plugin), and **manual QA** for the destructive factory-reset flow.

---

## Automated tests (WPUnit)

The module ships a **Codeception / wp-browser** suite under `tests/wpunit/`. It verifies class loading, permissions, admin page registration, REST routes, brand configuration, and **non-destructive** paths of `ResetService` (for example `prepare()` error handling and result shape).

| Item | Location / notes |
|------|------------------|
| Suite | `tests/wpunit/` |
| Suite config | `tests/wpunit.suite.yml` |
| Global config | `codeception.dist.yml` (params from `.env.testing`) |
| Support | `tests/_support/` |

### Running locally

You need a WordPress installation and a test database. Configure environment variables (typically via **`.env.testing`** — copy from **`.env.testing.example`** if your checkout includes it):

| Variable | Example / purpose |
|----------|-------------------|
| `WP_ROOT_FOLDER` | Path to a WordPress installation |
| `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_PASSWORD`, `TEST_DB_NAME` | Test database credentials |
| `TEST_SITE_WP_DOMAIN` | e.g. `localhost` |
| `TEST_SITE_ADMIN_EMAIL` | e.g. `admin@example.org` |

Then:

```bash
composer install
composer test              # codecept run wpunit
composer test-coverage     # coverage + phpcov merge to tests/_output/
```

### What’s covered

| Test class | What it verifies |
|------------|------------------|
| `ModuleLoadingWPUnitTest` | Module classes autoload; constants defined |
| `PermissionsWPUnitTest` | Admin / subscriber / logged-out permission checks |
| `ToolsPageWPUnitTest` | Admin page registration, slug, capability, nonce constants |
| `ResetControllerWPUnitTest` | REST route registration, namespace, methods, permissions, URL validation |
| `BrandConfigWPUnitTest` | Brand accessors return expected types and shapes |
| `ResetServiceWPUnitTest` | `prepare()` error paths and result structure |

**Other classes in `tests/wpunit/`** (open files for scope): `BootstrapWPUnitTest`, `HiivePreservationWPUnitTest`, `ResetServiceEdgeCasesWPUnitTest`, `ResetServiceExecuteWPUnitTest`, `ResetServiceStepsWPUnitTest`, `SilentUpgraderSkinWPUnitTest`, `ToolsPageRenderingWPUnitTest`.

### What’s not covered by automated tests

**`ResetService::execute()`** is intentionally **not** fully exercised in automated tests: it drops database tables, deletes files, and reinstalls WordPress. That path is covered by **manual QA** below and by **Playwright** in the brand plugin CI when applicable.

### CI (unit / coverage)

Automated tests run on **push** and **pull requests** via **`.github/workflows/codecoverage-main.yml`** across **PHP 7.4–8.4** (see [workflows.md](workflows.md)).

---

## End-to-end (Playwright)

This repository does **not** ship Playwright specs under `tests/playwright/`. **E2E coverage** for UI flows runs in CI via **Build and Test Module Updates in Brand Plugins (Playwright tests)** (`.github/workflows/brand-plugin-test-playwright.yml`), which checks out this module against **newfold-labs/wp-plugin-bluehost** and runs that plugin’s Playwright suite.

When you change user-visible behavior, coordinate with the brand plugin: specs and helpers (`auth`, `wpCli`, etc.) usually **live in the plugin** and reuse shared Playwright utilities there.

---

## Manual QA

The following checks cover the **full destructive reset** on a **throwaway** WordPress site with a brand plugin (examples use **Bluehost**; adapt menu URLs and labels for other brands). **Do not run on production** — the reset is irreversible.

### Before you start

Seed the site so you can verify cleanup:

- Install 2–3 extra plugins (e.g. WPForms Lite, Yoast SEO)
- Install an extra theme (e.g. Twenty Twenty-Four)
- Create posts, pages, and upload media
- Keep the brand plugin active

### Test 1: Settings page entry point (brand UI)

**Where:** Brand plugin **Settings** (e.g. Bluehost → main settings)

1. Scroll to the bottom of the settings page (below Staging).
2. Find a **Factory Reset** section (standalone, not inside an accordion).
3. Title and primary action should read as a **danger** pattern (e.g. red heading, warning copy, red “go to factory reset” control).
4. Follow the control to the standalone Tools page (Test 2).

**Expect:** Section is visible, danger styling is obvious, navigation reaches the Tools screen.

### Test 2: Standalone Tools page

**Where:** **Tools → Factory Reset Website**

1. Open **Tools** in the admin menu.
2. Open **Factory Reset Website**.
3. URL should resemble `wp-admin/admin.php?page={brand}-factory-reset-website` (e.g. `bluehost-factory-reset-website`).

**Expect:** Menu item exists; page loads without errors.

### Test 3: Confirmation screen

1. Red or prominent warning explains consequences (database, plugins/themes, uploads, staging, etc., per current copy).
2. Copy states that **admin account and site URL** are preserved where applicable.
3. Text field asks the user to type the **site URL** to confirm.
4. **Reset Website** is **disabled** until the URL matches.

**Expect:** Severity is clear; button stays disabled until confirmation.

### Test 4: URL confirmation behavior

1. Random text keeps the button disabled.
2. Wrong URL keeps it disabled.
3. **Exact** site URL (as shown on the page) enables the button.
4. Removing a character disables it again.

**Expect:** Client-side gate only enables on an exact match.

### Test 5: Non-admin cannot access

As **Editor** or **Subscriber**, browse directly to the factory-reset admin URL.

**Expect:** No menu access and/or permission error; reset cannot be used.

### Test 6: Execute the reset

On a **disposable** site, as **Administrator**:

1. Enter the correct site URL.
2. Choose **Reset Website** and wait (may take tens of seconds).
3. Review the **results** screen (per-step success/failure).

**Expect:** Steps list includes phases such as default theme install, staging cleanup messaging, plugin/theme/MU/drop-in removal, wp-content/uploads cleanup, database reset, settings restore, session restore; success path shows **Set up my site** / **Exit to dashboard** (or current equivalents).

### Test 7: Verify outcome

After completion:

| Area | Expect |
|------|--------|
| **Session** | Still logged in as the same admin; password unchanged |
| **Plugins** | Only the brand plugin remains active |
| **Themes** | Only the brand default theme (e.g. `bluehost-blueprint` for Bluehost) remains |
| **Content** | Fresh default post/page; prior content gone |
| **Settings** | Site title and URLs match pre-reset where preserved |
| **Front end** | Loads |
| **Uploads** | `wp-content/uploads/` exists and is effectively empty (if you can inspect the filesystem) |

### Test 8: Edge case — minimal site

On a site with **only** the brand plugin and no extra themes/plugins, run the reset again.

**Expect:** Completes successfully; “remove plugins/themes” style steps may report nothing to remove.

### Test 9: Edge case — server-side URL check

1. Open the factory reset page.
2. In devtools, enable the submit control while the URL field is wrong or empty (bypass client-side disable).
3. Submit.

**Expect:** Error that the URL does not match; **no** destructive reset runs.

### Summary checklist

| # | Test | Pass? |
|---|------|-------|
| 1 | Factory Reset block visible on brand Settings with danger styling; link to Tools works | |
| 2 | Tools → Factory Reset Website loads | |
| 3 | Warning lists consequences; URL field + disabled button | |
| 4 | Button only enables on exact URL match | |
| 5 | Non-admins blocked | |
| 6 | Reset runs; results screen shows steps | |
| 7a–7f | Post-reset: login, plugins, themes, content, settings, uploads as above | |
| 8 | Works on minimal site | |
| 9 | Server rejects bad URL if JS is bypassed | |

---

## Pull request expectations

- **WPUnit** / codecoverage jobs must pass for PHP changes.
- Add or update tests in `tests/wpunit/` when behavior changes.
- Playwright regressions may appear from the **brand plugin** workflow; fix in the plugin or module as appropriate.
