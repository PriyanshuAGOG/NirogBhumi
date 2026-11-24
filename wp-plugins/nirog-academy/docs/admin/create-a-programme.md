---
id: admin-create-a-programme
title: Building a programme in the Course Studio
summary: Weeks, recorded lessons, audio, booklets, readings, bonus content and live sessions — all from Academy → Course Studio.
audience: admin
order: 20
last_reviewed: 2026-11-24
related: admin-batches-and-release, admin-prepare-and-upload-videos, admin-booklets
---
# In short

Open **Academy → Course Studio**. Every programme is built there: weeks, and inside each week its recorded lessons, audio practices, booklets, readings and live sessions. Bonus content has its own tab. Everything saves automatically ("All changes saved" at the top).

Tip: **Academy → Settings → Create sample programme** builds a draft example you can open in the Studio.

# The words used

| In the Studio | What participants see |
| --- | --- |
| Week | A section of the programme ("Week 2 · Building the practice") |
| Recorded lesson | A protected video |
| Audio practice | Audio only (Yoga Nidra, pranayama) — light on mobile data |
| Booklet | A PDF they read inside the Academy, page by page |
| Reading | Text written in the Studio |
| Live session | A scheduled class with a join link and reminders |
| Bonus | Extra content in their Library |

# Create a programme

1. **Course Studio → New programme**, type the name, **Create**. It starts as a **Draft** (only staff can see it) with one week.
2. Open the **Settings** tab: short description, colour, cover image, access length (365 = one year), how lessons open (Self-paced, Batch or All at once), the health and safety note, Hindi name and description.
3. When it is ready, switch the toggle at the top to **Published**.

# Add weeks and content

1. On the **Weeks** tab, give each week a name by clicking on it (for example "Foundations" — the week number is shown automatically).
2. Under a week, choose **Add: Recorded lesson / Audio practice / Booklet / Reading / Live session**.
3. The item opens in the panel on the right. Fill in:
   - **Title**.
   - **Opens on day** — 0 = from the start; 7 = one week after the start.
   - **Video or audio** — pick from the Video library (see [Preparing and uploading videos](prepare-and-upload-videos.md)), or **Booklet** — drop the PDF in (see [Booklets](booklets.md)), or **Text** for readings.
   - **Short summary** and **Safety note** (shown in a highlighted box; must be approved by your clinical team).
   - **Downloads** — worksheets or charts (stored privately).
   - **Options** — counts towards progress; free preview.
   - **Hindi** — title, summary and safety note in Hindi (optional).
4. Switch **Visible to participants** on when it is ready. New items start hidden.
5. Drag items with the ⠿ handle to reorder them or move them to another week. Drag weeks the same way.

The list shows warnings in gold, such as "No video chosen" or "No PDF yet", so nothing is published half-finished by mistake.

# Bonus content

**Bonus** tab → Add. Give each item a **Bonus category** (Recipes, Q&A, Pranayama…); participants see bonus content grouped by category in their Library.

# Live sessions

Add them under a week (they then appear inside that week for participants) or from the **Live sessions** tab. Set date and time (India time), length, join link (Zoom / Google Meet) and, for batch programmes, which batch. Reminders go out the day before and shortly before the start; the join button appears 15 minutes before. Afterwards, add the recording as a recorded lesson (often in Bonus) and choose it under **Recording**.

# Video library

**Video library** (top of the Studio) lists every protected video and linked YouTube/video-service video, its length, whether it is ready, and where it is used.

# If this doesn't work

| Problem | Fix |
| --- | --- |
| Participants do not see an item | Is it **Visible to participants**? Is the programme **Published**? Has its opening day come? |
| "Not saved" at the top | Check your internet and try again. If it repeats, reload the page. |
| Upload fails | The file may be larger than the server allows (the limit is shown in the upload box). Ask Hostinger to raise "upload_max_filesize". |
| I need the old WordPress screen | Programmes and lessons can still be opened at `wp-admin/post.php?post=ID&action=edit`, but the Studio is the intended place. |
