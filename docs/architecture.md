---
name: wp-module-reset
title: Architecture
description: How the module registers with the loader, brand context, and two-phase reset flow.
updated: 2025-03-25
---

# Architecture

## Registration

1. **Composer** autoloads `bootstrap.php`, which defines `NFD_RESET_VERSION`, `NFD_RESET_DIR`, and merges `ResetFeature::class` into the array passed through **`newfold/features/filter/register`**.
2. **`ResetFeature`** (extends Newfold **Feature**) enables the feature and, on **`plugins_loaded`**, instantiates **`Reset`** with the module loader **container**.
3. **`Reset`** loads the text domain on **`init`** (priority 100), registers **`ToolsPage`** in admin, and constructs **`RestApi`**.

## Brand integration

**`BrandConfig`** reads the host plugin from the loader **container** (`container()->plugin()`):

- **Brand ID** – default theme slug, tools page slug (`{id}-factory-reset-website`), REST namespace (`{id}/v1`).
- **Brand plugin** – basename preserved during reset; only that plugin stays active after Phase 1 deactivation.

If the container is unavailable (e.g. some test contexts), defaults fall back toward **bluehost**-compatible values where implemented.

## Two-phase admin flow

The **Tools** screen intentionally uses a **raw redirect** between phases so third-party plugins cannot hook `allowed_redirect_hosts` during a half-torn-down state.

- **Phase 1 (POST)** – Nonce + capability checks; user’s typed URL must match `home_url()`. **`ResetService::prepare()`** preserves data, ensures default theme, sets `active_plugins` to only the brand plugin, removes MU plugins and drop-ins, then stores state in a **transient** and redirects to Phase 2.
- **Phase 2 (GET)** – Loads with only the brand plugin; **`ResetService::execute()`** runs destructive steps, then redirects to the results view.

## REST flow

**`ResetController`** runs **prepare** then **execute** in one request (no redirect), after validating `confirmation_url` against `home_url()`.

## Text domain

Strings use **`wp-module-reset`**; MO files are expected under **`languages/`** relative to the package, loaded via `load_plugin_textdomain` with a path derived from `plugin_basename( NFD_RESET_DIR )`.
