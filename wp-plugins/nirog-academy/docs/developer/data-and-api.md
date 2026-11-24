---
id: dev-data-and-api
title: Data model, REST API and hooks
summary: Tables, post meta, endpoints, actions and filters.
audience: developer
order: 20
last_reviewed: 2026-11-24
---
# Tables (prefix `{wp}_nba_`)

| Table | Purpose | Key columns |
| --- | --- | --- |
| enrollments | Access per user and programme | user_id, programme_id, cohort_id, order_id, source, status, starts_at, ends_at, paused_at, paused_days, needs_review. Unique (order_id, programme_id). |
| cohorts | Batches | programme_id, name, starts_at, enroll_opens_at, enroll_closes_at, capacity, status, coach_id |
| progress | Per user and lesson | PK (user_id, lesson_id); status, position_sec, duration_sec, watched (5-second bucket map), watched_pct, completed_at |
| devices | Registered browsers | user_id, token_hash (unique), label, registered_at, last_seen_at, revoked_at |
| device_requests | Requests to staff | user_id, status, reason, device_label, decided_by |
| notifications | In-app messages | user_id, type, title, body, url, dedupe_key (unique per user), read_at |
| notes | Private lesson notes | PK (user_id, lesson_id) |
| acknowledgements | Safety note acceptance | PK (user_id, programme_id, version) |
| audit_log | Sensitive actions | actor_id, action, subject_user_id, target_type, target_id, context (JSON), ip_hash |

Enrollment statuses: `active`, `paused`, `expired`, `revoked`, `pending`. All datetimes are UTC.

# Post types and meta

- `nba_programme`: `_nba_access_days`, `_nba_drip_mode` (none|enrollment|cohort), `_nba_cohort_assignment` (auto|manual), `_nba_sequential`, `_nba_element`, `_nba_summary(_hi)`, `_nba_title_hi`, `_nba_ack_text(_hi)`, `_nba_ack_version`, `_nba_product_ids`.
- `nba_module`: `_nba_programme_id`, `menu_order`.
- `nba_lesson`: `_nba_programme_id`, `_nba_module_id`, `_nba_placement` (curriculum|bonus), `_nba_category`, `_nba_type` (video|audio|reading), `_nba_media_id`, `_nba_media_id_hi`, `_nba_duration_min`, `_nba_release_day`, `_nba_required`, `_nba_preview`, `_nba_summary(_hi)`, `_nba_safety_note(_hi)`, `_nba_title_hi`, `_nba_resources` (attachment IDs).
- `nba_media`: `_nba_provider` (hls|file|youtube|external), `_nba_path`, `_nba_key_file`, `_nba_file`, `_nba_youtube_id`, `_nba_external_url`, `_nba_duration_sec`.
- `nba_live`: `_nba_programme_id`, `_nba_cohort_id`, `_nba_starts_at` (UTC), `_nba_duration_min`, `_nba_join_url`, `_nba_recording_lesson_id`.
- WooCommerce product: `_nba_programme_ids`. Order: `_nba_enrollment_ids`, `_nba_granted_{programme}`.

# REST API — `/wp-json/nirog-academy/v1/`

All endpoints require a logged-in user and the `X-WP-Nonce` header (`wp_rest`). Lesson endpoints run the gate.

| Method | Route | Body | Returns |
| --- | --- | --- | --- |
| POST | `/lessons/{id}/progress` | position, duration, ranges [[start,end]…], stream | status, watched_pct, completed. 409 `{stop:true}` if another stream owns playback. |
| POST | `/lessons/{id}/pages` | page, total | Booklet page seen; status, watched_pct, completed |
| POST | `/lessons/{id}/complete` | — | completed, summary. 400 if a video/audio/booklet has not been watched or read enough. |
| POST | `/lessons/{id}/note` | body | saved |
| POST | `/stream/claim` | stream | ok |
| GET | `/notifications` | — | unread, items |
| POST | `/notifications/read` | id (optional) | ok |

Progress credit is capped by elapsed time between reports (×1.5 + 10 s, max 180 s), so fabricated ranges cannot complete a lesson instantly.

# Media endpoints (query string on the home URL)

`?nba_stream=playlist|key|file|res|booklet` with `m` (media), `l` (lesson), `r` (playlist path), `a` (attachment), `u` (user), `e` (expiry), `s` (HMAC). Each request re-verifies user, signature, expiry and the gate.

# Course Studio API — `/wp-json/nirog-academy/v1/studio/`

Requires `edit_nba_items`. `GET/POST programmes`, `GET/POST/DELETE programmes/{id}`, `POST programmes/{id}/order`, `POST programmes/{id}/weeks`, `POST/DELETE weeks/{id}`, `POST programmes/{id}/items`, `POST/DELETE items/{id}`, `POST items/{id}/booklet` (multipart PDF), `POST items/{id}/files` and `DELETE items/{id}/files/{file}`, `POST programmes/{id}/live`, `POST/DELETE live/{id}`, `GET/POST media`, `POST media/import`, `POST media/{id}`.

Lesson meta added for booklets: `_nba_file` (relative path in the private files folder), `_nba_file_name`, `_nba_pages`, `_nba_allow_download`. Live sessions: `_nba_module_id` places a session inside a week.

# Actions

`nba_loaded`, `nba_enrollment_created ($id, $user_id, $programme_id)`, `nba_enrollment_extended`, `nba_enrollment_status_changed ($id, $from, $to)`, `nba_lesson_completed ($user_id, $lesson_id, $programme_id)`, `nba_programme_completed ($user_id, $programme_id)`, `nba_device_registered`, `nba_device_changed ($user_id, 'self'|'admin')`, `nba_device_request_created`, `nba_device_request_decided`, `nba_audit`, `nba_daily`, `nba_quarter_hourly`.

# Filters

`nba_access_check (AccessResult, $user_id, $lesson_id)`, `nba_logo_url`, `nba_programmes_page_url`.

# WP-CLI

`wp academy import-videos`, `wp academy enroll <email> <programme-id>`, `wp academy device-reset <email>`, `wp academy run-daily`, `wp academy sample`.
