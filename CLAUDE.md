# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This code lives inside the Bluehost WordPress plugin and contains the **Factory Reset Website** module (`wp-module-reset`).  
The module adds a tools page in the WordPress admin that **factory-resets the WordPress site** to a fresh state while **preserving selected hosting and connection data** (for example, hosting connection, some Newfold-specific settings, and session data).

It is designed to:
- Run safely in typical shared-hosting environments.
- Avoid third-party plugin interference as much as possible.
- Provide clear admin UI flows for confirmation and results.

## Local Development Context

This module is not a standalone application. It is:
- A **module within a larger WordPress plugin** (`wp-plugin-bluehost`).
- Loaded via WordPress’ standard plugin bootstrap inside a WordPress site.

Assumptions for local development:
- WordPress is already installed and running locally.
- The Bluehost plugin (containing this module) is installed and activated.
- Typical workflows involve editing PHP files under `vendor/newfold-labs/wp-module-reset/` and reloading the admin.

There are no Laravel- or Vapor-specific commands here; treat this as a standard WordPress plugin module.

## Core Concepts & Architecture

### Admin UI – Tools Page

The main entry point is the **Tools page** registered under:
- `Tools > Factory Reset Website` in the WordPress admin.
- Implemented by the `ToolsPage` class in the module.

Key responsibilities:
- Render a **confirmation screen** (`reset-confirmation` view) explaining what reset does and requiring explicit user confirmation.
- Handle a **two-phase reset flow** to avoid interference from other plugins.
- Render a **results screen** (`reset-results` view) summarizing what happened and any errors.

### Two-Phase Reset Flow

The reset runs in **two HTTP requests**:

1. **Phase 1 (POST)** – Preparation
   - Validates nonce and permissions.
   - Validates the confirmation URL the user typed matches the site URL.
   - Calls `ResetService::prepare()` to:
     - Preserve values that should survive reset.
     - Install or ensure the default theme.
     - Deactivate third-party plugins.
     - Remove MU plugins and drop-ins.
   - Stores preparation data in a transient for Phase 2.
   - Issues a **raw `header()` redirect** to Phase 2 to reduce risk of third-party hooks breaking the redirect.

2. **Phase 2 (GET)** – Destructive reset
   - Runs after plugins have been deactivated so the environment is as clean as possible.
   - Retrieves Phase 1 data from the transient.
   - Calls `ResetService::execute()` to:
     - Delete/clean plugin, theme, and `wp-content` assets (except what needs to be preserved).
     - Reset the database to a fresh-install–like state.
     - Restore previously preserved values and Newfold-specific data.
   - Stores the result in a transient and redirects back to the tools page with a `reset_complete` flag.

Claude should treat this flow as **security- and integrity-critical**: any change that affects nonce handling, redirects, or the two-phase separation needs extra scrutiny.

### Services & Data Preservation

Core services include:
- `ResetService` – Orchestrates the preparation and execution phases, and tracks the individual steps (e.g., install default theme, deactivate plugins, remove MU plugins, reset DB, restore values, reinstall core/theme, restore hosting connection, restore session, verify fresh install).
- `ResetDataPreserver` – Handles **which data is preserved** across the reset (for example, hosting connection details, selected options, and session information) and how that data is restored afterward.

When reviewing changes that touch these services, focus on:
- What is preserved vs. destroyed.
- How failures in one step are reported back to the admin UI.
- Ensuring partial failures don’t leave the site in an unusable or insecure state.

### Views

The module uses simple PHP views rather than a SPA:
- `reset-confirmation.php` – Confirmation UI, shows warnings and what will happen, collects the confirmation URL and submits the POST that kicks off Phase 1.
- `reset-results.php` – Summary UI that lists steps executed, errors (if any), and provides a path back into the admin.

Claude should:
- Ensure output is properly escaped using WordPress helpers.
- Ensure destructive actions are clearly communicated to the user.

## Code Review Guidance for this Module

When reviewing pull requests in this repository (especially under `wp-module-reset`), please:

### Safety & Permissions

- Verify that only **authorized admins** can trigger a reset:
  - Check capability checks (e.g., `manage_options` or helper `Permissions::is_admin()`).
  - Ensure the tools page and reset handlers are not accessible to unauthenticated users.
- Confirm that **nonces are verified** correctly on destructive operations.
- Be wary of any new code that:
  - Bypasses nonce checks.
  - Exposes reset behavior via public endpoints or AJAX without proper checks.

### Data Integrity & Reset Behavior

- Understand the intent: this module should approximate a **fresh WordPress install** while still preserving:
  - Hosting connection and Newfold-specific data required to keep the hosting product functioning.
  - Any explicitly preserved configuration or session data defined in `ResetDataPreserver`.
- When code modifies what is preserved/destroyed:
  - Ask whether the change could unintentionally wipe critical hosting data.
  - Ask whether it could leave behind sensitive or unnecessary data that should have been cleaned.
- Check that new steps added to the reset process:
  - Are correctly ordered.
  - Have error handling that surfaces meaningful messages in the results screen.

### WordPress / PHP Best Practices

- **Escaping & sanitization**
  - Inputs from `$_GET`, `$_POST`, and other globals must be sanitized (`sanitize_text_field()`, `absint()`, etc.).
  - Output in views must be escaped (`esc_html()`, `esc_attr()`, `esc_url()`).
- **Admin UX**
  - Destructive actions should always be clearly labeled and require explicit confirmation.
  - Errors from individual reset steps should be visible and understandable to the site owner.
- **File and database operations**
  - Use WordPress APIs where practical (filesystem, options, transients) instead of low-level PHP functions when that improves portability/safety.
  - Avoid assumptions that only apply to a single hosting environment.

### Testing & Manual Verification

For changes that touch the reset logic, Claude should recommend:
- Manual test steps such as:
  - Install additional plugins/themes, create posts/users/options, then run a factory reset and verify:
    - The site behaves like a fresh install (login, dashboard, default theme).
    - Preserved data (hosting connection, key options) is still intact.
    - The Bluehost plugin and reset module remain functional.
- Automated tests if a test harness exists for this module (for example, unit tests around `ResetService` and `ResetDataPreserver`).

## How to Structure Your Review

When leaving review comments, respond in GitHub Markdown using these sections:

- **Summary**
  - 2–5 bullets summarizing the overall assessment of the changes to this module.
- **Must-fix before merge**
  - Security, data-loss, or correctness issues.
  - Any changes that might break the two-phase reset or lock users out of their site.
- **Nice-to-have improvements**
  - Readability, refactoring, or small UX polish that would improve maintainability but are not blockers.
- **Testing suggestions**
  - Concrete manual test flows (e.g., “install plugin X and run reset”) or automated test ideas.

Focus reviews on **safety, clarity, and predictable reset behavior** rather than large architectural rewrites, unless the existing code is clearly unsafe or unmaintainable. 

