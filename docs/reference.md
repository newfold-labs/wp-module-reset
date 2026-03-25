---
name: wp-module-reset
title: Reference
description: Transients, options, and extension points at a glance.
updated: 2025-03-25
---

# Reference

## Transients (admin flow)

Used by **`ToolsPage`** for cross-request state and messaging (prefix **`nfd_`**):

| Transient | Purpose |
|-----------|---------|
| `nfd_reset_error` | Error message shown on the confirmation screen (short TTL) |
| `nfd_reset_phase2` | Serialized Phase 1 payload for Phase 2 execution |
| `nfd_reset_result` | Result array for the post-reset results view |

Exact TTL values are defined in `includes/Admin/ToolsPage.php`.

## Preserved options (Hiive / brand)

**`ResetService::prepare()`** snapshots options such as **`nfd_data_token`**, related **`nfd_data_*`** keys, brand plugin version option, and core options **`blogname`**, **`siteurl`**, **`home`**, **`blog_public`**, **`WPLANG`**, plus the current admin user’s credentials (see **`ResetService`** and **`restore_nfd_data`** / **`restore_values`**).

## Filters

| Filter | Registered in | Purpose |
|--------|----------------|---------|
| `newfold/features/filter/register` | `bootstrap.php` | Registers **`ResetFeature`** |

## REST namespace

Derived from **`BrandConfig::get_rest_namespace()`** → `{brand_id}/v1` (e.g. `bluehost/v1`). Route base: **`factory-reset`**.

## Constants

| Constant | Defined in | Purpose |
|----------|------------|---------|
| `NFD_RESET_VERSION` | `bootstrap.php` | Package version |
| `NFD_RESET_DIR` | `bootstrap.php` | Filesystem path to the package root |
