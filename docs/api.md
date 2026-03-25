---
name: wp-module-reset
title: REST API
description: Factory reset REST route, parameters, and responses.
updated: 2025-03-25
---

# REST API

Namespace and path are **brand-specific**. The namespace is `{brand_id}/v1` (e.g. `bluehost/v1`).

## Endpoints

| Method | Path | Handler | Description |
|--------|------|---------|-------------|
| POST | `/{namespace}/factory-reset` | `ResetController::execute_reset` | Runs factory reset after URL confirmation. |

Full route pattern: `POST /wp-json/{namespace}/factory-reset`

## Authentication and permissions

- User must be logged in with **`manage_options`**.
- Use a **REST nonce** (`X-WP-Nonce`) or cookie authentication as for other WordPress REST endpoints.

## Request body (JSON)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `confirmation_url` | string | Yes | Must match the site’s front URL (same normalization as admin: untrailingslashit `home_url()`). |

## Responses

- **200** – Reset completed successfully; body contains the result structure from **`ResetService::execute()`** (steps, success flag, errors array as applicable).
- **400** – `invalid_confirmation` if the URL does not match.
- **403** – `rest_forbidden` if the user lacks capability.
- **500** – Preparation failure (`reset_preparation_failed`) or execution failure (response may include step details).

## Implementation reference

- `includes/Api/Controllers/ResetController.php`
- `includes/Services/ResetService.php`
