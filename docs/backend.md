---
name: wp-module-reset
title: Backend (PHP)
description: Main PHP classes, admin page, services, and supporting types.
updated: 2025-03-25
---

# Backend (PHP)

## Entry and lifecycle

| Class / file | Role |
|--------------|------|
| `bootstrap.php` | Constants; registers `ResetFeature` on the features filter |
| `includes/ResetFeature.php` | Feature name `reset`; wires `Reset` on `plugins_loaded` |
| `includes/Reset.php` | `load_textdomain`; admin `ToolsPage`; `RestApi` |

## Admin

**`includes/Admin/ToolsPage.php`** – Registers the submenu, handles Phase 1/2 routing on `admin_init`, renders confirmation and result HTML (inline CSS/JS for confirm button state). Uses transients for errors, Phase 2 payload, and completion result.

## REST

**`includes/Api/RestApi.php`** – Hooks `rest_api_init` and registers **`ResetController`**.

**`includes/Api/Controllers/ResetController.php`** – Namespace from `BrandConfig::get_rest_namespace()`; base `factory-reset`; `POST` handler `execute_reset`.

## Core logic

**`includes/Services/ResetService.php`** – Static **prepare()** and **execute()**; filesystem cleanup; database drop + `wp_install`; core/theme reinstall; restore of preserved options and user password hash; **`get_step_label()`** for translated step names in UI and errors.

**`includes/Services/SilentUpgraderSkin.php`** – Captures upgrader output for core/theme reinstall without echoing.

## Data and security

**`includes/Data/BrandConfig.php`** – Brand ID, plugin basename/name, default theme slug, page slug, REST namespace.

**`includes/Permissions.php`** – `manage_options` checks for admin and REST permission callback.
