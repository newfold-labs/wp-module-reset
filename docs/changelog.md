---
name: wp-module-reset
title: Changelog
description: Notable version-to-version changes for wp-module-reset.
updated: 2026-11-19
---

# Changelog

All notable changes to this project are documented here. For older history, see **GitHub Releases** on the canonical repository.

## [Unreleased]

- Fix: factory reset failed on HostGator with "Failed to install the default theme. Reset aborted with no changes made." HostGator's default theme slug was `flavor`, which is not on WordPress.org. The reset now targets WordPress core's own default theme for the running version (`WP_DEFAULT_THEME`) for every brand instead of a brand-specific theme, so it works on any WordPress version (old or new) and no longer aborts over an unavailable brand theme. Re-applying a brand theme is onboarding's responsibility. (PRESS0-5302)
- The factory-reset REST endpoint no longer accepts application-password authentication (returns `403`); run the reset from an admin session. (PRESS0-5302)
- The Tools-page factory-reset confirmation step now verifies a nonce. (PRESS0-5302)
- Workflows: added standard module workflows.
- Translations: updated strings to all use i18n methods, added translation files for supported languages.
- Documentation: added **AGENTS.md**, **CLAUDE.md** (symlink), and **docs/** per Newfold module documentation standards.
- Testing: merged root **TESTING.md** into **docs/testing.md**; **TESTING.md** now points to the doc.

---

## 1.0.1 - 2026-02-20

- Fix factory reset breaking Hiive site connection
- UI changes to factory reset

## 1.0.0 - 2026-02-14

- Initial release.

___

When tagging a release, add a dated section (e.g. `## [1.0.1] - YYYY-MM-DD`) and summarize user-facing behavior, API changes, and migration notes.
