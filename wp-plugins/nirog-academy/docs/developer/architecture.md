---
id: dev-architecture
title: Architecture
summary: How the plugin is organised, boot order, and design decisions.
audience: developer
order: 10
last_reviewed: 2026-11-24
---
# Overview

`nirog-academy` is a standalone WordPress plugin (PHP 8.1+, WordPress 6.5+). It has no runtime Composer dependencies and no build step: upload the folder and activate. WooCommerce is optional (needed for paid enrollments).

```
nirog-academy.php        bootstrap: constants, PSR-4 autoloader, activation hooks
src/Plugin.php           service container; nba() returns it
src/Install/             tables (dbDelta), roles/capabilities, secrets, upgrades
src/Support/             Settings, Crypto, RateLimiter, Time, I18n, Storage, Docs, Markdown
src/Content/             post types, meta boxes, Curriculum (tree + cache)
src/Enrollment/          Enrollments (table), Cohorts (batches), WooBridge
src/Access/              Gate — the single access check — and AccessResult
src/Device/              one-device rule, device codes, requests
src/Media/               Stream (signed HLS/key/file delivery), Importer
src/Progress/            watch tracking, completion, notes, one-stream rule
src/Notify/              Notifications (in-app + email), Scheduler (jobs)
src/Rest/                REST API (nirog-academy/v1)
src/Frontend/            Router (/courses/*), Auth, Actions (forms), View
src/Admin/               menu, Course Studio page, admin pages, form handlers, sample data
src/Studio/              Course Studio data layer (programmes, weeks, items, live, uploads)
src/Cli/                 WP-CLI commands (wp academy …)
templates/               participant screens + email (theme-overridable)
assets/                  academy.css/js (participant), studio.css/js (Course Studio),
                         admin.css/js, vendor/ (hls.js, PDF.js, SortableJS, htm)
tools/                   encode-lesson.sh / .ps1 (ffmpeg → encrypted HLS)
docs/                    participant, admin and developer guides
tests/                   integration (wp eval-file) and e2e (Playwright)
```

Services: `nba()->settings`, `audit`, `curriculum`, `enrollments`, `cohorts`, `devices`, `progress`, `notifications`, `stream`, `gate`.

# Key decisions

- **Separate from the theme.** Nirog Bhumi Courses carries its own design tokens (copied from the theme) and its own page shell, so a theme change never breaks it. Templates can be overridden in `{theme}/nirog-academy/`.
- **Content in custom post types; high-volume data in custom tables.** Editors get the familiar WordPress screens and revisions; enrollments, progress, devices, notifications and the audit log get indexed tables.
- **One access gate.** `Access\Gate::check_lesson()` is called by page views, REST endpoints, downloads, playlists and key requests.
- **Self-hosted encrypted HLS with byte ranges.** One `.ts` file per rendition keeps the file count low on shared hosting. Segments are static files (cheap to serve); playlists and keys go through PHP after the gate, via HMAC-signed URLs bound to user and expiry.
- **Devices are browsers.** A random 256-bit token in an HttpOnly cookie, stored as an HMAC hash. Self-service changes are confirmed with an emailed code and rate-limited.
- **Server-rendered participant pages + small vanilla JS.** Fast on mid-range phones, no framework, no CDN, no external fonts.
- **Self-contained participant pages.** `templates/layout.php` does not call `wp_head()`/`wp_footer()`; `Router::head()`/`Router::foot()` print only the course area's stylesheet, the `NBA` config and `academy.js` (vendor libraries load on demand). The theme, other plugins and the admin toolbar never print on Courses pages (`show_admin_bar` is off there), so they cannot break its layout, and Nirog Bhumi Courses never changes the rest of the site. Staff get a small team bar from the layout instead.
- **Google Meet** (`Live\GoogleMeet`): OAuth 2.0 web-server flow with the team's own Google Cloud client (scope `calendar.events`), refresh token encrypted at rest (AES-256-GCM, key from the site's salts). `create_for_session()` inserts a Calendar event with `conferenceData.createRequest` (`hangoutsMeet`) and stores `_nba_gcal_event_id`; `sync_session()` PATCHes title/time when a fingerprint changes; trashing a session deletes the event.
- **YouTube lessons** are tracked through the IFrame API: an adapter exposes `currentTime`, `duration`, `paused` and play/pause/timeupdate/ended events to the same watch tracker as protected video. Lengths are learned from the first play.
- **Booklet reader**: full-screen overlay (`#read` in the URL so the back button closes it); Scroll view uses IntersectionObserver to draw pages near the screen; Book view uses page-flip.
- **Demo programme** (`Admin\Demo`): built from `demo/` (encrypted sample HLS, PDFs, covers); every created ID is stored in the `nba_demo` option and `Demo::remove()` deletes only those. The demo user (user meta `nba_demo_user`) bypasses the launch lock and one-device rule and is never emailed.
- **Course Studio as a small React app** built on WordPress's bundled React (`wp-element`) with htm templates — no build step. It talks to `/wp-json/nirog-academy/v1/studio/*` (capability `edit_nba_items`).
- **Booklets** are private PDFs rendered page by page with PDF.js (bundled, `isEvalSupported: false`), served through the gate, with a per-page watermark and page-based progress.
- **Times stored in UTC**, shown in the site timezone; lessons open at local midnight.
- **Bilingual.** Interface strings via WordPress i18n (`languages/`), content via optional `_hi` meta fields with English fallback.

# Request flow (lesson page)

```
/courses/lesson/{slug}/ → Router::dispatch
  → login? → device verify (registers first browser) → launch mode
  → Gate::check_lesson → deny → redirect (login/device/orientation) or notice
  → Stream::player_config → signed playlist URL → templates/lesson.php
Browser: hls.js → ?nba_stream=playlist (gate) → ?nba_stream=key (gate) → static .ts
         heartbeat → POST /wp-json/nirog-academy/v1/lessons/{id}/progress (gate)
```
