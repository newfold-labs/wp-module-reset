---
name: wp-module-reset
title: Overview
description: What the factory reset module does, who it is for, and main capabilities.
updated: 2025-03-25
---

# Overview

**wp-module-reset** provides a **factory reset** for sites running Newfold brand plugins. It returns WordPress to a near-fresh install while **keeping** the current administrator account (including password hash), **siteurl** / **home**, and selected **Hiive / NFD** options so the hosting connection can remain intact.

## Capabilities

- **Admin UI** – Submenu under **Tools → Factory Reset Website** (slug and copy are brand-aware via `BrandConfig`). The user must type the site URL to confirm; reset runs in **two HTTP phases** (prepare with plugins loaded, then execute with only the brand plugin active) when using the form.
- **REST API** – `POST` to `{brand}/v1/factory-reset` with `confirmation_url` for clients that integrate programmatically (single request: prepare + execute).
- **Safety rails** – Refuses multisite; requires `manage_options`; installs target default theme before destructive steps; suppresses email and hardens the environment during execution.

## Who maintains it

Developed and released as **newfold-labs/wp-module-reset** (Satis / Composer). Consuming repos include brand plugins such as **wp-plugin-bluehost**.

## Related reading

- [architecture.md](architecture.md) – registration and two-phase flow
- [api.md](api.md) – REST contract
- [backend.md](backend.md) – main PHP classes
