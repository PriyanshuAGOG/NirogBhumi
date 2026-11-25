---
id: admin-demo-programme
title: The demo programme
summary: Load a complete demo programme with a demo participant to try every feature, then remove it with one button.
audience: admin
order: 5
last_reviewed: 2026-11-25
related: admin-setup-checklist, admin-create-a-programme
---
# In short

The demo programme is a complete, published programme you can click through before building your own. It is made from small sample files that come with the plugin, so nothing needs to be uploaded. Remove it with one button when you are done — your own programmes, participants, website, theme and shop are never touched.

# Load it

1. **Academy → Course Studio**. Below your programmes, press **Load demo programme** (about 10 seconds).
   Or: **Academy → Settings → Demo programme → Load demo programme**.
2. The panel then shows the demo participant's **username** and **password**.

# What it contains

| Part | What you get |
| --- | --- |
| 4 weeks | Recorded lessons (a 16-second sample video), audio practices (a 20-second sample audio), readings with headings, lists and a table, and two weekly booklets (flipbook) |
| Bonus | A recipes booklet, a Q&A recording and a short reading |
| Downloads | A daily practice tracker (PDF) on the "Breath awareness" lesson |
| Live sessions | One starting in about 10 minutes (Join is open), a Q&A later this week, a YouTube Live class next Sunday, and a past welcome call with its recording |
| Safety | A "Before you start" note on practices and a first-lesson acknowledgement ("Before your first lesson") |
| Hindi | Programme, week and lesson names in Hindi — switch to हिंदी at the top |
| Inbox | A welcome message from the team |
| Demo participant | "Asha (demo)", enrolled 9 days ago: weeks 1–2 are open, weeks 3–4 show "Opens on …"; two lessons already done |

You are enrolled too, so the Academy also works in your own browser. As staff you also see a dark **team bar** at the top of Academy pages with links to Course Studio, Live desk and the dashboard, and whether the Academy is open or closed.

# See it as a participant

1. Open a private (incognito) window, another browser, or your phone.
2. Go to the address shown in the demo panel. While the Academy is **Closed** this is the WordPress login page (`/wp-login.php`), because the Academy's own login page is hidden from the public until launch; after logging in you land in the Academy. Once the Academy is **Open** it is `/academy/login/`.
3. Log in with the demo username and password.

The demo participant:

- can log in **even while the Academy is Closed** (everyone else outside the team still sees "page not found");
- may use **several devices** (real participants are limited to one);
- **never receives emails** (its address is not real).

To try the one-device rule and the emailed device code, use a real test account with your own email address instead.

# Remove it

Course Studio → **Remove demo**, or Settings → **Remove demo programme**. This deletes the demo programme, its weeks, lessons, live sessions, booklets, covers, sample videos, the demo participant and their progress. It removes only what the demo created.

Command line: `wp academy sample` loads it, `wp academy sample --remove` removes it.
