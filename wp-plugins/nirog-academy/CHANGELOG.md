# Changelog

## 1.3.0 — 2026-11-25

- **Demo programme**: one button (Course Studio or Settings) loads a complete, published demo — four weeks of sample videos, audio, booklets and readings, bonus content, a downloadable worksheet, live sessions of every kind (starting soon, later this week, YouTube Live, past with recording), Hindi names, an Inbox message and a demo participant whose access started nine days ago. One button removes everything it created. The demo participant can log in while the Academy is closed, may use several devices and never gets emails.
- **Fixed: the bottom of Academy pages for logged-in admins.** Academy pages no longer print WordPress's admin toolbar or any theme/plugin head and footer code, which showed up as an unstyled list below the footer. Academy pages are now fully self-contained. The team sees a small team bar instead (open/closed status, Course Studio, Live desk, Dashboard).
- Live sessions in week lists match the rest of the design (no blue tint); past sessions show "Recording" or "Done" in grey, cancelled ones are struck through.
- Booklet pages no longer show the generic "Take your time with this practice" line; the booklet card on Today has room for its title.


## 1.2.0 — 2026-11-25

- **Launch lock**: while the Academy is Closed, every Academy address shows "page not found" to everyone except staff, and no participant emails are sent. Welcome emails for enrollments made meanwhile are held and sent when the Academy opens.
- **Live sessions for a team**: meeting link (Zoom/Meet/Teams) or YouTube Live played inside the Academy; a page for each session with countdown, Join and recording; host, private team notes, attendance, "send reminder now", cancel/restore with automatic notices. New **Live desk** screen for hosts.
- **Booklets as books**: 3D books on the shelf (real cover from the PDF's first page, spine coloured from the cover, thickness from page count), a booklet page with a draggable book, and a **flipbook reader** with real page turns (spreads on computers, single pages on phones), All pages, full screen and keyboard.
- **Design**: header shows only the Nirog Bhumi logo; no green italic accents; Today shows a plain "Up next" panel with the week's progress instead of the decorative arch.
- Hindi added for all new participant text. Database version 2 (attendance table), upgraded automatically.

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
