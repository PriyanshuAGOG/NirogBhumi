# Changelog

## 1.1.0 — 2026-11-24

- **Course Studio**: a dedicated app inside WordPress (Academy → Course Studio) for building programmes — weeks, recorded lessons, audio practices, booklets, readings, bonus content and live sessions — with drag and drop, a side panel editor, autosave, PDF and worksheet uploads, a video library and programme settings. The old form screens are no longer in the menu.
- **Booklets**: weekly PDF booklets read inside the Academy (PDF.js, bundled), page-by-page progress, resume on last page, watermark on every page, optional download.
- **Participant area redesigned** in the Nirog Bhumi website's style: plain top menu, large serif headings, numbered rows, booklet covers. New Today page, Library (booklets, bonus, downloads), live sessions shown inside their week, mobile menu.
- **Hindi rewritten** in simple everyday Hindi, keeping common English words (programme, lesson, video, booklet, device…).
- Week names no longer repeat "Week 1" next to the week number.

## 1.0.0 — 2026-11-24

First release.

- Programmes, modules, lessons (video, audio, reading), bonus library, live sessions, private downloads.
- Lesson release: all at once, self-paced, or by batch; optional sequential order.
- Batches with automatic placement, places and coaches.
- WooCommerce/Razorpay: automatic enrollment on payment, revocation on refund/cancel, partial-refund review flag, renewals extend access, payment links for consult-first programmes, guest checkout accounts.
- Single access gate for pages, REST, downloads, playlists and keys.
- One-device rule with self-service email-code change, staff requests, single session and single stream.
- Encrypted HLS (single-file byte-range renditions for low file counts on shared hosting), signed expiring URLs, moving watermark; ffmpeg encode scripts for Mac/Linux and Windows; folder importer.
- Watch tracking in 5-second buckets with server-side time budget; resume; notes.
- Notifications: in-app bell; welcome, lesson unlocked digest, nudges, live reminders, expiry warnings, device and announcement emails; participant email preferences.
- Admin: overview, enrollments, participant page, batches, device requests, announcements, reports with CSV, video import, audit log, settings with health check, in-product guides, sample programme.
- English and Hindi interface; Hindi content fields with English fallback.
- WP-CLI commands; integration and end-to-end tests; participant, team and developer documentation.
