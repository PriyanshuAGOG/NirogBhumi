# Nirog Bhumi Courses

A WordPress plugin that delivers Nirog Bhumi programmes — the Yoga programme, the 99 Day and 6 Month programmes, and any future programme — as paid, protected online courses.

It is separate from the `nirog-bhumi` theme and carries the website's design tokens, so the participant area looks like the rest of the site even if the theme changes.

## What it does

**For participants** (`/courses/`)
- Login with password or a one-time email link; branded "choose your password" and reset flows.
- Today page: continue where you left off, this week's lessons, booklet, next live session, team messages.
- Programme page by week: lessons, booklets and live sessions — done, in progress, open, or locked with the date it opens.
- Lesson page: protected video or audio player that resumes where you stopped, safety note, downloads, private notes, moving watermark.
- Booklets read on the course pages, page by page, with the participant's name on each page.
- Library (booklets, bonus, downloads), live sessions (join button 15 minutes before), inbox, account, help center.
- English and हिन्दी.
- Designed in the Nirog Bhumi website's style; works on phones first.

**For the team** (WordPress → Courses)
- **Course Studio**: build programmes in one screen — weeks, recorded lessons, audio practices, booklets (PDF), readings, bonus content and live sessions — with drag and drop and autosave.
- Lesson release: all at once, self-paced day by day, or by batch.
- Batches with start dates, places and coaches; automatic placement of new buyers.
- Enrollments: automatic from paid WooCommerce/Razorpay orders, payment links after consultation, manual or complimentary access, pause, extend, revoke; refunds close access automatically.
- Participant pages with lesson-by-lesson progress, device and activity log.
- Device requests, announcements, reports (drop-off per lesson, CSV export), audit log, settings, health check, in-product guides.
- Roles: Administrator, Course Manager, Course Coach, Course Participant.

**Protection**
- One access check for every page, API call, download, playlist and video key.
- One device per participant (configurable), one session, one stream at a time.
- Videos encrypted (AES-128 HLS), keys sent only through signed, expiring, per-user links.
- Rate-limited login, login links, password resets and device codes; secrets stored as hashes; audit log.

## Requirements

- WordPress 6.5+, PHP 8.1+, pretty permalinks.
- WooCommerce (with Razorpay) for paid enrollments.
- A web server that supports HTTP Range requests and `.htaccess` (Hostinger LiteSpeed does), or the Nginx rules in `docs/admin/security-and-backups.md`.
- ffmpeg on the computer used to prepare videos (free).

## Install

Upload `nirog-academy.zip` in **Plugins → Add New → Upload Plugin** and activate. Then follow `docs/admin/setup-checklist.md` (also in WordPress under **Courses → Help**). Nirog Bhumi Courses starts **Closed** (staff only) until you open it in **Courses → Settings**. To try every feature first, press **Load demo programme** in the Course Studio (see `docs/admin/demo-programme.md`).

## Running costs

The plugin and every component it uses are free. Running costs are the ones you already have — hosting, domain, and Razorpay fees. Optional paid extras, none of which are needed: a video service if hosting bandwidth becomes a limit, the WhatsApp Business API, an AI help assistant.

## Documentation

`docs/index.md` lists every guide (participant, team, developer). The guides are self-contained Markdown articles with stable IDs, suitable for people and as AI context.

## Tests

- `tests/integration/run.php` — 37 checks of enrollment, release dates, the access gate, pause/extend/expiry, devices, batches, WooCommerce grant/refund, anti-cheat progress, booklet reading and the Studio. Run with `wp eval-file` on staging.
- `tests/e2e/smoke.mjs` — Playwright: login, orientation, encrypted playback, progress, second-device block, copied-link refusal, email-code device change.

## Licence

GPL-2.0-or-later. Bundles hls.js 1.5.20 (Apache-2.0), PDF.js 4.10.38 (Apache-2.0), SortableJS 1.15.6 (MIT) and htm 3.1.1 (Apache-2.0); licences are in `assets/vendor/`.
