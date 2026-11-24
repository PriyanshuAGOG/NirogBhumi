---
id: dev-testing-and-release
title: Testing and releasing
summary: Running the tests, coding standards, versioning and building the ZIP.
audience: developer
order: 30
last_reviewed: 2026-11-24
---
# Integration tests

`tests/integration/run.php` exercises enrollments, drip dates, the access gate, pause/extend, WooCommerce grant/revoke, progress anti-cheat and devices against a real WordPress database. Run it on a **staging** site only (it creates and deletes test data):

```
wp eval-file wp-content/plugins/nirog-academy/tests/integration/run.php
```

# End-to-end test

`tests/e2e/smoke.mjs` drives real browsers with Playwright: login, orientation, encrypted playback, progress, second-device block, copied-link refusal and email-code device change. See the header of the file for the environment variables.

# Standards

- WordPress Coding Standards (PHPCS) with the security sniffs; escape on output, sanitise on input, `$wpdb->prepare()` for values.
- Every form has a nonce; every admin action checks a capability.
- No external requests from Academy pages (fonts, scripts and icons are local).

# Versioning and releases

1. Bump `Version` in `nirog-academy.php` and `NBA_VERSION`.
2. If the schema changed, bump `NBA_DB_VERSION` and add a step in `Installer::run_migrations()`.
3. Update `CHANGELOG.md`.
4. Build: from `wp-plugins/`, `zip -r nirog-academy.zip nirog-academy -x "*/tests/*" "*/node_modules/*"`.
5. Install on staging, run both test suites, then upload to production.

# Translations

Regenerate the template with `wp i18n make-pot . languages/nirog-academy.pot`. Hindi strings live in `languages/nirog-academy-hi_IN.l10n.php` (WordPress 6.5+ PHP translation format) and should be reviewed by a native Hindi speaker before launch.
