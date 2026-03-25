---
name: wp-module-reset
title: GitHub workflows
description: CI, translations, Playwright via brand plugin, release prep, and Satis.
updated: 2025-03-25
---

# GitHub workflows

Workflows live under **`.github/workflows/`**.

## Code Coverage

**File:** `codecoverage-main.yml`  
**Triggers:** `push` / `pull_request` to `main`, `workflow_dispatch`  
**Behavior:** Calls **`newfold-labs/workflows`** reusable **codecoverage** workflow with multiple PHP versions and a configured minimum coverage.

## Playwright (brand plugin)

**File:** `brand-plugin-test-playwright.yml`  
**Triggers:** `pull_request` to `main`, `workflow_dispatch`  
**Behavior:** Uses **`module-plugin-test-playwright.yml`** with `plugin-repo: newfold-labs/wp-plugin-bluehost` and this repository’s branch so Bluehost’s Playwright tests run against the proposed module changes.

## Newfold Prepare Release

**File:** `newfold-prep-release.yml`  
**Triggers:** `workflow_dispatch` (patch / minor / major)  
**Behavior:** **`reusable-module-prep-release.yml`** – bumps version, updates **`package.json`** and **`bootstrap.php`** (`json-file` / `php-file` inputs), and opens a release PR per org standards.

## Translations

**File:** `auto-translate.yml`  
**Triggers:** `push` to `main`, `workflow_dispatch`  
**Behavior:** **`reusable-translations.yml`** with **`text_domain: wp-module-reset`**.

## Satis

**File:** `satis-update.yml`  
**Triggers:** GitHub **release created**  
**Behavior:** Dispatches to **newfold-labs/satis** to refresh the Composer mirror for the released package version.
