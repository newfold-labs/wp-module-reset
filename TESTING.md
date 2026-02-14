# Factory Reset Module — QA Testing Guide

This guide covers both automated and manual testing for the Factory Reset module.

---

## Part 1: Automated Tests

The module includes a Codeception/WP-Browser test suite in `tests/wpunit/`. These tests verify class loading, permissions, page registration, REST API routes, brand configuration, and the non-destructive paths of the reset service.

### Running locally

Automated tests require a WordPress installation and a test database. Set the following environment variables (or use a `.env` file):

- `WP_ROOT_FOLDER` — path to a WordPress installation
- `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_PASSWORD`, `TEST_DB_NAME` — test database credentials
- `TEST_SITE_WP_DOMAIN` — e.g. `localhost`
- `TEST_SITE_ADMIN_EMAIL` — e.g. `admin@example.org`

Then run:

```bash
composer install
composer run test
```

### What's covered

| Test class | What it verifies |
|---|---|
| `ModuleLoadingWPUnitTest` | All module classes are autoloadable, constants are defined |
| `PermissionsWPUnitTest` | Admin/subscriber/logged-out permission checks |
| `ToolsPageWPUnitTest` | Admin page registration, slug, capability, nonce constants |
| `ResetControllerWPUnitTest` | REST route registration, namespace, methods, permissions, URL validation |
| `BrandConfigWPUnitTest` | Brand-specific accessor methods return correct types and shapes |
| `ResetServiceWPUnitTest` | `prepare()` error paths and result structure |

### What's NOT covered by automated tests

The destructive `ResetService::execute()` method is **intentionally excluded** from automated tests because it drops all database tables, deletes files, and reinstalls WordPress. That path is covered by the manual tests below.

### CI

Automated tests run on every push/PR via `.github/workflows/codecoverage-main.yml` across PHP 7.4–8.4.

---

## Part 2: Manual QA Tests

The following manual tests cover the full end-to-end flow including the destructive reset. You will need a disposable WordPress test site with the brand plugin (e.g. Bluehost) installed. **Do not test this on a real site — the reset is irreversible.**

---

### Before You Start

Set up your test site with some "stuff" so you can verify it all gets cleaned up:

- Install 2–3 extra plugins (e.g. WPForms Lite, Yoast SEO)
- Install an extra theme (e.g. Twenty Twenty-Four)
- Create a few posts, pages, and upload some media images
- Leave the Bluehost plugin active

---

## Test 1: Settings Page Entry Point

**Where:** Bluehost > Settings (the plugin's main settings page)

1. Scroll to the very bottom of the settings page, past Staging
2. You should see a "Factory Reset" section — it is **not** inside an accordion, it stands on its own
3. The title should be red and say "Factory Reset"
4. The description warns that this is permanent and cannot be undone
5. There is a red "Go to Factory Reset" button/link
6. Click it — it should take you to the standalone Tools page (next test)

**What to look for:**
- The section is always visible (not collapsed/hidden)
- The styling clearly communicates "danger" (red text, red button)
- The link works and goes to the right page

---

## Test 2: Standalone Tools Page Exists

**Where:** WordPress admin sidebar > Tools > Factory Reset Website

1. In the WordPress admin menu, click **Tools**
2. You should see a submenu item called **Factory Reset Website**
3. Click it — the page should load at a URL like `wp-admin/admin.php?page=bluehost-factory-reset-website`

**What to look for:**
- The menu item shows up under Tools
- The page loads without errors

---

## Test 3: Confirmation Screen Safety Checks

**Where:** The Factory Reset page (Tools > Factory Reset Website)

1. The page should show a red warning box explaining what will happen
2. The warning should list everything that will be deleted:
   - All database content (posts, pages, comments, settings, custom tables)
   - All plugins and themes (except Bluehost plugin and default theme)
   - All uploaded media files
   - All MU plugins and drop-in files
   - Any staging sites
3. It should also say your admin account and site URL will be preserved
4. There is a text input asking you to type your website URL to confirm
5. The "Reset Website" button should be **disabled** (grayed out)

**What to look for:**
- The warning copy is clear and communicates the severity
- The button is disabled — you cannot click it yet

---

## Test 4: URL Confirmation Behavior

**Where:** Same page

1. Type something random in the confirmation field (e.g. "hello") — the button should stay disabled
2. Type your site URL but add a typo — the button should stay disabled
3. Type your exact site URL (shown on the page next to "type your website URL") — the button should become enabled (no longer grayed out)
4. Delete a character — the button should go back to disabled

**What to look for:**
- The button only enables when the URL matches exactly
- This is a real safety gate — it should not be easy to accidentally trigger

---

## Test 5: Non-Admin User Cannot Access

**Where:** Log in as a non-admin user (e.g. an Editor or Subscriber role)

1. Try navigating directly to `wp-admin/admin.php?page=bluehost-factory-reset-website`
2. You should be blocked — either the page does not appear in the menu, or you get a permission error

**What to look for:**
- Only administrators can see and use the Factory Reset page

---

## Test 6: Execute the Reset

**Where:** The Factory Reset page, logged in as an administrator on a **disposable test site**

1. Type your site URL correctly in the confirmation field
2. Click "Reset Website"
3. Wait — this may take 10–30 seconds depending on how much content exists
4. You should see a results screen showing each step with a checkmark or X

**What to look for on the results screen:**
- Each step should show a green checkmark:
  - Install default theme
  - Clean up staging sites (may say "skipped" — that is fine)
  - Remove plugins
  - Remove themes
  - Remove MU plugins
  - Remove drop-in files
  - Clean wp-content directory
  - Clean uploads directory
  - Reset database
  - Restore settings
  - Restore session
- There are "Setup my site" and "Exit to dashboard" links at the bottom

---

## Test 7: Verify the Reset Worked

After the reset completes, verify the following:

**You are still logged in:**
- You should not be kicked out to a login screen
- You are the same admin user with the same username and password

**Plugins:**
- Go to Plugins — only the Bluehost plugin should be installed and active
- The extra plugins you installed earlier should be gone

**Themes:**
- Go to Appearance > Themes — only the brand default theme (e.g. `bluehost-blueprint`) should be installed and active
- The extra themes you installed earlier should be gone

**Content:**
- Go to Posts — the default "Hello World" post from a fresh WordPress install should be the only one (your old posts are gone)
- Go to Pages — the default "Sample Page" should be the only one (your old pages are gone)
- Go to Media — the library should be empty (your uploaded images are gone)

**Site identity:**
- Go to Settings > General — your site title, site URL, and WordPress URL should be the same as before the reset
- Visit the front end of your site — it should load (not be broken)

**Uploads folder:**
- If you have FTP/file access: check that `wp-content/uploads/` exists but is empty

---

## Test 8: Edge Case — No Extra Plugins or Themes

1. On a fresh test site with no extra plugins or themes installed (only Bluehost plugin), run the reset
2. It should complete successfully without errors
3. The steps for "Remove plugins" and "Remove themes" should say something like "No third-party plugins to remove"

---

## Test 9: Edge Case — Wrong URL Submitted via Tampering

This tests that the server-side check works even if someone bypasses the JavaScript:

1. Open the Factory Reset page
2. Using your browser's developer tools, find the submit button and remove the `disabled` attribute
3. Leave the URL field empty or type a wrong URL
4. Click the submit button
5. You should see an error message saying the URL does not match — the reset should **not** execute

---

## Summary Checklist

| # | Test | Pass? |
|---|------|-------|
| 1 | Factory Reset section visible at bottom of Settings page with red/danger styling | |
| 2 | Tools > Factory Reset Website menu item exists and page loads | |
| 3 | Warning screen shows all consequences clearly | |
| 4 | Submit button is disabled until exact URL is typed | |
| 5 | Non-admin users cannot access the page | |
| 6 | Reset executes and shows step-by-step results | |
| 7a | After reset: still logged in as same user | |
| 7b | After reset: only the brand plugin remains | |
| 7c | After reset: only the brand default theme remains | |
| 7d | After reset: all old content is gone, fresh defaults exist | |
| 7e | After reset: site URL and title preserved | |
| 7f | After reset: uploads folder exists but is empty | |
| 8 | Reset works cleanly on a site with no extra plugins/themes | |
| 9 | Server rejects form submission with wrong/missing URL even if JS is bypassed | |
