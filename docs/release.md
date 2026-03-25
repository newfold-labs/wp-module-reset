---
name: wp-module-reset
title: Release process
description: Version bump locations, prep-release workflow, and build requirements.
updated: 2025-03-25
---

# Release process

## Preferred: Newfold Prepare Release workflow

Use **Actions → Newfold Prepare Release → Run workflow** (`.github/workflows/newfold-prep-release.yml`). It invokes the reusable **module prep release** workflow with:

- **`json-file`:** `package.json`
- **`php-file`:** `bootstrap.php`

That keeps PHP and npm metadata aligned and opens a PR for review.

## Hardcoded version locations

Bump these together for every release:

| Location | Constant / key |
|----------|----------------|
| `bootstrap.php` | **`NFD_RESET_VERSION`** (`define`) |
| `package.json` | **`version`** |

There is **no production asset build** (no `npm run build`); `package.json` exists mainly for versioning and workflow inputs. If you add scripts or shipped assets later, update this doc and the prep-release workflow inputs as needed.

## After merge / tag

Creating a **GitHub release** triggers **`satis-update.yml`**, which notifies Satis to publish the new package version (see [workflows.md](workflows.md)).

## Changelog

Record notable user-facing and integration changes in **docs/changelog.md** when you cut releases.
