# Nirog Bhumi Academy — LMS Plugin Plan

**Status:** Proposal for review, version 1.0, 20 November 2026
**Scope:** A standalone WordPress plugin that delivers Nirog Bhumi programmes
(starting with the Yoga for Diabetes programme, then the 99 Day and 6 Month
programmes) as paid, protected online courses.

---

## Decisions and status (24 November 2026)

| Question | Decision |
| --- | --- |
| Hosting | Hostinger shared plan → self-hosted encrypted HLS, one file per quality level to stay within file-count limits; external video service remains a drop-in option. |
| Devices | Strictly one device (setting `max_devices = 1`, can be raised later). Self-service change once every 30 days. |
| Yoga programme | Direct purchase. |
| Lesson release | Configurable per programme: all at once, self-paced, or batch. The LMS serves every programme, not only Yoga. |
| Access length | 1 year (365 days), per-programme setting. |
| Language | English and Hindi. |
| Participant area | "Nirog Bhumi Academy" at `/academy/` (both are settings). |

**Status:** version 1.0.0 of the plugin is built in `wp-plugins/nirog-academy/` (installable ZIP: `nirog-academy.zip` in the repository root). Phase 1 is complete, plus batches, reports, CSV export and the Hindi interface from Phase 2. Not yet built: web push notifications, certificates, practice log/streaks, a Hindi translation of the documentation. All 30 integration checks and all 11 browser end-to-end checks pass.

---

## 0. Summary

We will build **Nirog Bhumi Academy** (`nirog-academy`), a separate WordPress
plugin, **not part of the theme**. It will:

1. **Use what we already have for payments.** WooCommerce and Razorpay already
   run the store and consultations. A programme becomes a WooCommerce product.
   A paid order opens access automatically, and a refund or cancellation closes
   it again. We do not need to build a second checkout.
2. **Put one gate in front of everything.** Every page, every API call and every
   video key request goes through one access check: *logged in, on the
   registered device, enrolled and paid, inside the access window, lesson
   released*. If any of these fails, the content is not sent.
3. **Allow one device per participant.** Each account is tied to one registered
   browser. Other devices are blocked until the device is changed through a
   controlled reset.
4. **Give participants a calm, minimal place to learn:** a dashboard, a lesson
   player that remembers where they stopped, progress tracking, bonus videos,
   downloadable resources, live-session reminders and notifications.
5. **Give the team a simple admin area:** a programme builder, enrollments,
   participant progress, device resets, announcements, reports and an audit log.
6. **Match the website.** The plugin uses the theme's colours, fonts, spacing and
   radii. It also ships its own copy of these settings, so it still looks right
   if the theme changes.
7. **Cost nothing in software licences.** Everything is custom code or free,
   open-source components. The only running costs are ones we already pay
   (hosting, Razorpay fees). Section 9 sets out where "zero cost" has real limits,
   especially for video.
8. **Ship with full documentation.** The docs cover participants, admins and
   developers. They are written in self-contained Markdown articles, so a person
   can read them and an AI assistant can use them as context.

---

## 1. Build our own, or use a free LMS plugin?

There are well-known LMS plugins with free versions: Tutor LMS, LearnPress,
Sensei LMS, LifterLMS and MasterStudy. We considered them first.

| Requirement | Free LMS plugins (typical) | Custom plugin |
| --- | --- | --- |
| Courses, lessons, progress | Yes | Yes |
| WooCommerce selling | Often a paid add-on or limited in the free tier* | Native, reuses our Razorpay setup |
| Drip (release lessons over time) | Often paid* | Yes |
| **One device per account** | Not offered in free tiers as far as I know* | Yes, designed in |
| Protected video with signed keys | No | Yes |
| Exact match to our design | Needs heavy CSS overrides that break on updates | Built from our design tokens |
| Health-specific needs (safety consent, contraindication notes, practice log) | No | Yes |
| Long-term licence cost | Upsells over time | None |

\* **Uncertainty:** these products change their free and paid feature lists
often. I am confident about the general pattern (key features such as drip,
WooCommerce integration and certificates are commonly paid add-ons) but not
about the current tier of each feature in each product. Check this before
treating it as final.

**Recommendation: build a focused custom plugin.** We need fewer features than a
general LMS, but the features we need (device lock, payment gate, protected
video, our design) are exactly the ones the free tiers lack. A focused plugin is
also easier to secure, because there is less code to attack.

---

## 2. How it fits with the current site

```
WordPress
├── Theme: nirog-bhumi            (look and feel, marketing pages, store design,
│                                   consultations, invoices)
├── Plugin: WooCommerce + Razorpay (cart, checkout, orders, payments, refunds)
└── Plugin: nirog-academy  ← NEW   (programmes, lessons, enrollments, progress,
                                    devices, notifications, help center)
```

Rules for the split:

- **The plugin never needs the theme to work.** If the theme is switched, the
  Academy still works and still looks like Nirog Bhumi, because it carries its
  own design tokens.
- **The theme can integrate if it wants to.** It can add a "My Programmes" link to
  the header and to the WooCommerce My Account page. The plugin provides hooks
  for this.
- **WooCommerce is required for paid enrollments.** Without WooCommerce, the
  plugin still runs, but only manual enrollment by an admin is available.
- **The source lives in this repository** at `wp-plugins/nirog-academy/`, next to
  `wp-theme/`. It gets its own versioned ZIP for upload.

---

## 3. Participant experience (client side)

### 3.1 Journey from payment to first lesson

```
Consultation (existing flow)
        │  admin approves the programme for this person
        ▼
Admin creates an offer ──► participant gets a "Pay now" link (WooCommerce order)
        │                       or buys directly if the product is On sale
        ▼
Razorpay payment succeeds ──► WooCommerce order: Processing / Completed
        │
        ▼
Academy creates the enrollment (access start, access end, cohort)
        │
        ▼
Welcome email: "Set your password and start" (one-time, expiring link)
        │
        ▼
First login ──► this browser becomes the registered device
        │
        ▼
Orientation: health and safety acknowledgement (one time) ──► Dashboard
```

### 3.2 Screens

All screens live under `/academy/`. They use a separate, distraction-free layout
(no marketing navigation), designed mobile-first. We expect most participants to
use phones.

| Screen | What it shows |
| --- | --- |
| **Login** `/academy/login/` | Email and password, "Email me a login link" (one-time link), forgotten password. Uses the brand styling, not the default WordPress login. |
| **Dashboard** `/academy/` | Greeting; **Continue where you left off** (one large button); today's practice; programme progress ring; the week's schedule; next live session; latest announcement. Nothing else. |
| **My Programme** `/academy/programme/{slug}/` | Modules shown as weeks. Each lesson shows its state: completed ✓, in progress, available, or locked with its unlock date ("Opens Monday, 8 Dec"). |
| **Lesson** `/academy/lesson/{slug}/` | Player (video or audio), title, duration, short description, **safety note** (for example "Skip inversions if you have uncontrolled blood pressure"), downloadable resources, private notes, "Mark as complete", previous and next lesson. |
| **Bonus Library** `/academy/bonus/` | Bonus videos and talks, filterable (Recipes, Pranayama, Q&A recordings). Can unlock with the programme or at milestones. |
| **Resources** `/academy/resources/` | PDFs: food rhythm charts, practice sheets, tracking sheets. |
| **Live Sessions** `/academy/live/` | Upcoming sessions with "Add to calendar" and a join button that appears 15 minutes before start. Past sessions link to recordings. |
| **Practice Log** `/academy/log/` (phase 2) | Daily tick-boxes (yoga done, pranayama done, walk, sleep time). Streaks. Optional glucose readings, see section 6.6. |
| **Notifications** (bell icon) | In-app list, unread count, mark all as read. |
| **Profile and Settings** | Name, phone, language, notification preferences, **My device** (which device is registered, when it was registered, "Request device change"), password. |
| **Orders and Invoices** | Links to the existing WooCommerce account area, where invoices already work. |
| **Help Center** `/academy/help/` | Searchable participant guides (section 10), plus "Contact support" (email or WhatsApp click-to-chat). |
| **Certificate** (phase 2) | Downloadable completion certificate (PDF made with the PDF code we already have in the theme). |

### 3.3 Lesson player behaviour

- **Resume:** reopening a lesson continues from the last position.
- **Honest completion:** a lesson counts as watched only when about 90% of its
  **unique seconds** have played. Skipping to the end does not count. Lessons
  without media (reading, checklist) use a "Mark as complete" button.
- **Speed control:** 0.75×, 1×, 1.25×. **Quality:** Auto, 360p, 540p, 720p.
- **Low-data mode:** for practices that are mainly guided by voice (Yoga Nidra,
  pranayama), an audio-only version. This uses roughly a tenth of the data,
  which helps participants on mobile data.
- **Visible watermark:** the participant's name and the last four digits of
  their phone, faint, moving every few seconds. This discourages screen
  recording and sharing.
- **Interruption-safe:** progress is saved every 15 seconds and on pause or
  close. A dropped connection loses at most a few seconds.

### 3.4 Design system (matched to the website)

Taken from `wp-theme/nirog-bhumi/assets/css/styles.css`:

| Token | Value | Use in Academy |
| --- | --- | --- |
| `--canvas` | `#e8dec9` | Page background behind cards |
| `--paper` | `#f8f6ef` | Cards, player surround, panels |
| `--soft` | `#eee8dc` | Hover states, locked lesson rows |
| `--ink` | `#304535` | Body text, icons |
| `--dark` | `#314936` | Primary buttons, sidebar |
| `--line` | `#d8d0c0` | Borders, dividers |
| `--earth` | `#82ad5c` | Completed, progress, success |
| `--water` | `#77d0d4` | Information, live sessions |
| `--fire` | `#d7b84e` | Warnings, safety notes, streaks |
| `--air` | `#87a8e8` | Links, notifications |
| `--space` | `#9e82c9` | Bonus content, milestones |
| Headings | Georgia, "Times New Roman", serif | Page and lesson titles |
| Body/UI | Inter, system-ui stack | Everything else |
| Radius | 8px (cards, inputs), 999px (pills, progress) | |

The five elements double as a quiet visual language for content types (Earth =
body practice, Water = cleansing and food, Fire = digestion and energy, Air =
breath, Space = mind and rest). This is optional, but it ties the Academy to the
philosophy behind the programme.

**Layout principles:** one main action per screen; at most two accent colours
on a screen; large tap targets (at least 44px); WCAG AA text contrast (we will
check that `--ink` on `--paper` and white text on `--dark` pass AA); no external
fonts or CDNs, keeping the theme's "no external requests" rule. Desktop gets a
left sidebar. Mobile gets a bottom bar with 4 items: Home, Programme, Library,
Profile.

**Before coding the front end, we will produce clickable mockups** of the
Dashboard, Programme, Lesson and Login screens for your approval.

---

## 4. Admin experience

A top-level **Academy** menu in WordPress admin:

| Screen | Purpose |
| --- | --- |
| **Overview** | Active participants; enrollments this month; average progress; **participants who need attention** (no login for 5+ days, stuck on one lesson, access ending soon); upcoming live sessions. |
| **Programmes** | Create or edit a programme: title, cover, description, duration, linked WooCommerce product, access length, drip mode, certificate on or off. |
| **Curriculum builder** | Drag-and-drop **Modules → Lessons**. Lesson types: Video, Audio, Reading, PDF, Live session, Practice checklist. Each lesson has: media, duration, release rule, safety note, resources, required or optional, preview (free sample). |
| **Bonus Library** | Bonus items and their unlock rule (with programme, after module X, after N days). |
| **Media** | Protected video library: status (processing, ready), renditions, duration, which lessons use it. "Import HLS folder" tool. |
| **Cohorts / Batches** | For example "Yoga Jan 2027 batch": start date, which lessons release when, live session schedule, coach. |
| **Enrollments** | List and filters. Actions: **Grant** (manual or complimentary), **Extend** access, **Pause** (illness or travel, stops the clock), **Revoke**, **Move to another cohort**, **Create payment offer** (makes a WooCommerce order and emails the pay link). |
| **Participants** | One page per participant: profile, enrollments, progress by lesson, last active, device record, notifications sent, notes (coach-only), activity timeline. |
| **Device Requests** | Pending device-change requests: approve or deny, with history. |
| **Announcements** | Write once and send to a programme, cohort or everyone. Channels: in-app, email, push. Can be scheduled. |
| **Notifications** | Editable email templates (welcome, lesson unlocked, reminders, access ending), with placeholders such as `{first_name}` and `{lesson_title}`. |
| **Reports** | Completion per lesson (where people drop off), engagement over time, cohort comparison. CSV export. |
| **Audit Log** | Who did what and when: enrollment changes, device resets, refunds, settings changes, failed access attempts. Read-only. |
| **Settings** | Device policy, video provider, watermark, access defaults, email sender, push keys, data retention, and the **launch switch** (Closed / Admin preview / Open, the same model the store uses). |
| **Help** | The admin guides from the docs, readable inside WordPress. |

### 4.1 Roles

| Role | Can do |
| --- | --- |
| **Administrator** | Everything. |
| **Academy Manager** (`nba_manager`) | Programmes, enrollments, device approvals, announcements, reports. No WordPress settings or plugins. |
| **Coach** (`nba_coach`) | Sees assigned cohorts, participant progress and coach notes; can post announcements to their cohort. Cannot see payments or change access. |
| **Participant** (`nba_participant`, alongside WooCommerce `customer`) | Only their own Academy area. Never sees `/wp-admin/`. |

---

## 5. The payment gate (how access works)

### 5.1 Linking a programme to payment

- Each programme is linked to a **virtual WooCommerce product** (for example the
  existing `NB-PROG-99D` product for the 99 Day programme).
- When an order containing that product becomes **Processing** or **Completed**,
  the plugin creates an enrollment. This handles Razorpay's normal flow, where
  a captured payment moves the order to Processing.
- **Idempotent:** one enrollment per order and programme (a unique database key).
  If WooCommerce fires the hook twice, nothing is duplicated.
- **Refunded / Cancelled / Failed** → enrollment becomes `revoked` and is logged.
  A **partial refund** does not revoke automatically. It flags the enrollment for
  the manager to decide.
- **Consult-first programmes** (6 Month, 99 Day) keep the current "Enquiry only"
  shelf state. After the consultation, the manager clicks **Create payment
  offer**, which creates a WooCommerce order for that person and emails the
  standard WooCommerce "pay for this order" link. The payment still goes through
  Razorpay and still produces your sequential invoice, because the theme already
  hooks invoices to paid orders.
- **The Yoga programme** can be "On sale" for direct purchase, or consult-first.
  This is your choice (see open decisions).

### 5.2 Enrollment record

| Field | Example |
| --- | --- |
| status | `active`, `paused`, `expired`, `revoked`, `pending` |
| source | `woocommerce`, `manual`, `complimentary` |
| order_id | 1042 |
| cohort_id | Yoga Jan 2027 |
| access_starts_at | 5 Jan 2027 |
| access_ends_at | programme length + grace period (for example 99 days + 30 days) |
| paused_days | total days paused (added back to the end date) |

### 5.3 The single access check

Every protected request runs the same function, `Access::check( $user, $lesson )`,
in this order. It stops at the first failure and shows a clear message:

1. **Logged in?** If not → login page, then back to the requested page.
2. **Registered device?** If not → "This account is registered to another
   device" screen (section 6).
3. **Enrollment active?** If not → "Your access has ended / is paused" with next
   steps (renew, contact us).
4. **Inside the access window?** If not → same as above.
5. **Health acknowledgement completed?** If not → orientation screen.
6. **Lesson released?** (drip rule) If not → "Opens on Monday, 8 Dec".
7. **Prerequisite done?** (optional per lesson) If not → "Complete *Breath
   Basics* first".

**The check runs on the server for page loads, REST API calls, resource
downloads and video key requests.** Hiding a button is never the protection.
Even someone who copies a URL, or calls the API directly, gets nothing without
passing every step.

---

## 6. One device per participant

### 6.1 What "one device" means in a browser, honestly

A website **cannot read a phone's or laptop's hardware identity**. Browsers
deliberately prevent this. What we *can* do reliably is register **one browser on
one device** using a secret, server-issued token. This works well in practice,
but you should know its limits before launch:

- If a participant **clears browser data, uses private/incognito mode or
  switches browser** (Chrome → Safari) on the same phone, the site sees a new
  device.
- Determined, technical people can copy browser data between devices. This is
  rare, and the watermark and the session rules below reduce the value of doing it.

The policy therefore needs a **friendly, controlled reset path**. Otherwise
genuine participants get locked out and contact support.

### 6.2 Mechanism

1. **Registration:** on first Academy login, the server generates a random
   256-bit device token. It is stored in a `Secure; HttpOnly; SameSite=Lax`
   cookie, and only a **hash** of it is saved in the database (like a password).
   We also store a few non-identifying hints to help humans recognise the
   device: "Chrome on Android", approximate city from IP (optional),
   registration date.
2. **Every request:** the cookie token is hashed and compared with the
   registered device. A mismatch means blocked.
3. **One session at a time:** a successful login on the registered device ends
   all other WordPress sessions for that user, using WordPress's built-in
   session tokens.
4. **One stream at a time:** the player sends a heartbeat. If a second playback
   starts somewhere else (for example with a copied cookie), the older one stops
   with a message.
5. **Login from a different device:** the password works, but the participant
   sees: *"Your programme is registered to Chrome on Android (since 5 Jan). To
   use this device instead, request a device change."* They are not silently
   logged in, and the attempt is logged.

### 6.3 Device change (reset) policy — configurable

- **Self-service:** the participant confirms with a 6-digit code sent to their
  email. Allowed **once every 30 days** by default (adjustable). The old device is
  signed out immediately.
- **Beyond the limit:** the request goes to **Academy → Device Requests** for a
  manager to approve, with the history shown ("3rd change in 40 days").
- **Admin reset:** available on every participant page, and always logged.
- **Notifications:** every device change emails the participant ("Your
  programme was moved to a new device. Not you? Contact us"). This also acts as
  an account-sharing alarm.

### 6.4 Decision needed

Strict "one device" means a participant **cannot** watch the morning session on
their phone and the evening session on a laptop. Some programmes allow "1 phone
+ 1 computer" for this reason. The plugin will support a **maximum devices**
setting (default 1), so you can change this later without new code.

### 6.5 Privacy note on device data

Device records are personal data. Under India's **Digital Personal Data Protection
Act, 2023**, we should state in the privacy policy what we store and why
(preventing account sharing), keep only what is needed, and delete it when the
enrollment ends plus a retention period. I am confident the Act applies to this
kind of data. The detailed rules under the Act have been rolling out in stages,
so **have the final privacy wording checked by someone qualified in Indian data
protection law.**

### 6.6 Health data — keep it minimal

The optional glucose log (phase 2) would store **health data**, which is
sensitive. Recommendation: keep the LMS for learning, and leave glucose readings
to the consultation process, unless the team will actively review them. If we
include the log: explicit opt-in consent, no sharing, export and delete on
request (reusing the anonymise and export pattern already in
`inc/data-admin.php`), and no medical advice generated from the numbers.

---

## 7. Video and content protection

### 7.1 The zero-cost video problem (please read)

Video is the one place where "zero cost" and "secure" pull against each other.

| Option | Cost | Protection | Notes |
| --- | --- | --- | --- |
| **A. Self-hosted encrypted HLS** (recommended start) | Free software. Uses our hosting's storage and bandwidth. | Good: files are encrypted, keys are released only through the access gate, links expire. | Needs a one-time conversion of each video on a computer with the free tool `ffmpeg` (we will provide a one-click script). Depends on hosting bandwidth (see below). |
| B. YouTube "Unlisted" embeds | Free | **Weak.** Anyone who gets the video ID can watch it outside the site, and the ID is visible in the page. | Fast and reliable streaming, but the content can easily leak. Fine for free preview or bonus content only. |
| C. Low-cost video host (Bunny Stream, Cloudflare Stream, etc.) | Small monthly usage bill | Very good: token-signed URLs, domain lock, their CDN. | The upgrade path if hosting struggles. I do not want to quote current prices from memory; they change, so check before deciding. |
| D. Commercial DRM (Widevine/FairPlay) | Paid | Strongest | Not justified at launch. |

The plugin has a **video provider setting**, so we can start with A (with B for
free previews) and move to C later **without re-entering any lessons**.

**Bandwidth estimate (rough):** a 20-minute lesson at 720p is about 200–250 MB;
at 360p it is about 60–80 MB. A participant watching 40 lessons once at 720p uses
roughly **8–10 GB**. 100 participants → **about 1 TB per programme run**, less if
many watch on mobile at lower quality. Many "unlimited" shared hosting plans have
fair-use limits well below this. **I don't know your hosting plan, so this is the
first thing to confirm.** If hosting cannot handle it, option C becomes the
practical choice, and its cost is usage-based.

### 7.2 How option A works

```
Your computer:  lesson.mp4 ──ffmpeg──► 360p / 540p / 720p HLS segments,
                                        encrypted with AES-128, + key file
Upload (SFTP or the Media → Import tool) to a protected folder on the server
                                        │
Participant opens lesson ──► Access::check ✓ ──► playlist with 5-minute signed URLs
                                        │
Player (hls.js, bundled locally) asks for the key ──► Access::check ✓ + device ✓
                                        │                 + valid signature
                                        ▼
                          key released → segments decrypt in the browser
```

- The **raw MP4 never goes on the server**.
- Keys and playlists come from PHP endpoints that run the full access check.
  Encrypted segments are useless without the key.
- Signed URLs (HMAC, tied to user, device and lesson, valid 5 minutes) stop link
  sharing.
- The folder is blocked from direct listing and hotlinking (Apache `.htaccess`
  rules included; Nginx rules documented).

**Honest limit:** AES-128 HLS is a strong deterrent, not true DRM. A skilled
person with developer tools can still capture a stream, and anyone can point a
camera at a screen. That is why the **moving watermark** with name and phone
matters: it makes leaks traceable, which deters most people.

### 7.3 Other content

- **PDFs and downloads** are served through a PHP endpoint after the access
  check, never by direct file URL, and are stamped with the participant's name
  in the footer (phase 2).
- **Right-click blocking** is cosmetic only. We will not rely on it or market it
  as protection.

---

## 8. Notifications

| Event | In-app | Email | Push |
| --- | --- | --- | --- |
| Welcome + set password | ✓ | ✓ | |
| New lesson unlocked (drip) | ✓ | ✓ (weekly digest option) | ✓ |
| Live session reminder (24 h and 15 min before) | ✓ | ✓ | ✓ |
| Gentle nudge (no activity for 3 days, configurable) | ✓ | ✓ | ✓ |
| Streak or milestone ("Week 4 complete") | ✓ | | ✓ |
| Announcement from team or coach | ✓ | optional | optional |
| Access ending in 7 days | ✓ | ✓ | |
| Device changed / new device attempt | ✓ | ✓ | |
| Payment received / refunded | | ✓ (WooCommerce already sends) | |

**Channels and their costs:**

- **In-app:** free; stored in our database.
- **Email:** free in software. WordPress emails often land in spam unless they
  are sent through a proper mail service (SMTP). The free *WP Mail SMTP* plugin
  with a free-tier sender works for modest volumes. Free tiers have daily limits
  (often a few hundred emails per day, but I'm not certain of current figures).
  Set up SPF, DKIM and DMARC records on the domain in either case.
- **Web push** (browser notifications on Android and desktop; on iPhone only
  after the participant adds the site to their home screen): **free**, using the
  open Web Push standard with our own keys. No paid service is needed.
- **WhatsApp:** the official WhatsApp Business API **charges per conversation**,
  so it is not zero cost. The free route is a "Chat with us on WhatsApp" button
  and manual broadcast lists. The plugin will keep a WhatsApp channel slot so
  it can be added later.

Scheduling uses **Action Scheduler**, the reliable background job queue that
already ships with WooCommerce. No extra cost, and it is safer than plain
WP-Cron. We will also recommend a real server cron job, so reminders go out on
time even when traffic is low.

Participants can switch each channel on or off, except account-security emails.

---

## 9. Security plan

### 9.1 Application security (built into the plugin)

- **Capability checks** on every admin action and REST endpoint (custom
  capabilities such as `nba_manage_enrollments`, never just "is logged in").
- **Nonces** on every form and state-changing request (CSRF protection).
- **Prepared SQL statements** everywhere (`$wpdb->prepare`). All input is
  sanitised and all output escaped, following WordPress coding standards.
- **Rate limiting:** login, the one-time login link, device-change codes and
  progress pings are throttled per IP and per account.
- **Brute-force protection:** lockout after repeated failed logins, without
  revealing whether an email exists.
- **Tokens stored as hashes:** device tokens, login links and reset codes. They
  expire and work only once.
- **Participants never see `/wp-admin/`** and the admin bar is hidden for them.
- **REST API:** user enumeration endpoints are disabled for the public, and the
  Academy endpoints require authentication.
- **Audit log** for every sensitive action, kept separate from content.
- **Secrets** (signing keys, push keys) are generated on activation and stored
  outside the web root where hosting allows, otherwise in options with
  `autoload = no`.

### 9.2 Site-level protection (free tools)

- **Firewall (WAF):** Cloudflare's free plan in front of the site, *or* the
  Wordfence free plugin. Either is fine; Cloudflare also reduces bandwidth for
  static files (it does not cache our protected video keys).
- **Two-factor login for staff:** the free *Two Factor* plugin from WordPress
  contributors, required for Administrator and Academy Manager roles.
- **HTTPS everywhere,** HSTS, secure cookies.
- **Backups:** free *UpdraftPlus* to Google Drive, daily database and weekly
  files. **Test a restore** before launch.
- **Updates:** WordPress, WooCommerce and Razorpay kept current; staging site
  first if your hosting provides one.
- **Least privilege:** only 1–2 Administrators; everyone else uses Academy
  Manager or Coach.

### 9.3 Security testing before launch

- Automated: PHPCS with the WordPress security rules, PHPStan static analysis.
- Manual checklist: try to open a lesson logged out, unpaid, expired, on a second
  device, with a copied playlist URL, with an expired key URL, as a Coach trying
  to change access. Every one must fail cleanly.

---

## 10. Documentation (human- and AI-ready)

Docs live in `wp-plugins/nirog-academy/docs/` (Markdown, versioned with the
code). Participant and admin guides are also shown inside the product (Help
Center and Academy → Help).

```
docs/
├── index.md                     (map of every article + one-line summary each)
├── participant/
│   ├── getting-started.md       first login, setting a password
│   ├── your-device.md           one-device rule, changing device
│   ├── watching-lessons.md      player, quality, audio mode, resume
│   ├── progress-and-completion.md
│   ├── live-sessions.md
│   ├── notifications.md         turning on phone notifications
│   ├── troubleshooting.md       "video won't play", "locked out", "no email"
│   └── faq.md
├── admin/
│   ├── setup-checklist.md       install → launch, step by step
│   ├── create-a-programme.md
│   ├── prepare-and-upload-videos.md   (the ffmpeg script, explained simply)
│   ├── enrollments-and-payments.md
│   ├── cohorts-and-drip.md
│   ├── device-requests.md
│   ├── announcements-and-emails.md
│   ├── reports.md
│   ├── security-and-backups.md
│   └── incident-playbook.md     what to do if content leaks / site is down
└── developer/
    ├── architecture.md
    ├── data-model.md
    ├── access-gate.md
    ├── rest-api.md
    ├── hooks-and-filters.md
    ├── coding-standards.md
    └── release-process.md
```

**Article format (makes them work well as AI context):**

```markdown
---
id: participant-your-device
title: Using the Academy on your device
audience: participant
applies_to: nirog-academy 1.x
last_reviewed: 2026-11-20
related: [participant-troubleshooting, admin-device-requests]
---
## In short
(2–3 sentences answering the main question)
## Steps
(numbered, one action each)
## If this doesn't work
(symptom → fix)
## Words used here
(glossary)
```

Each article stands on its own (no "as mentioned above"), uses the same words as
the screens, and has a stable `id`. An AI assistant can then quote the right
article and link to it.

**An AI help assistant** inside the Academy is possible later, but calling an AI
model is a **paid service** (per use). So it is outside zero cost. The docs will
be ready for it on day one.

---

## 11. Technical architecture

### 11.1 Plugin structure

```
wp-plugins/nirog-academy/
├── nirog-academy.php            bootstrap, version, requirements check
├── uninstall.php                removes data only if "Delete data on uninstall" is on
├── src/                         PHP 8.1+, namespace NirogBhumi\Academy, PSR-4
│   ├── Plugin.php               service container, boot order
│   ├── Install/                 activation, versioned DB migrations, roles
│   ├── Content/                 post types: programme, module, lesson, bonus, resource
│   ├── Enrollment/              enrollment service, WooCommerce bridge
│   ├── Access/                  the single access gate + drip rules
│   ├── Device/                  device registry, session limiting, resets
│   ├── Media/                   providers (SelfHostedHls, YouTube, Bunny…), signed URLs, key server
│   ├── Progress/                heartbeats, completion, streaks
│   ├── Notify/                  channels (in-app, email, web push), templates, scheduler
│   ├── Rest/                    REST controllers (namespace nirog-academy/v1)
│   ├── Admin/                   admin screens, list tables, settings
│   ├── Frontend/                router for /academy/*, templates, shortcodes
│   ├── Audit/                   audit logger
│   └── Support/                 rate limiter, crypto helpers, logging
├── templates/                   participant screens (theme can override in /nirog-academy/)
├── assets/
│   ├── css/academy.css          design tokens + components (no external fonts)
│   ├── js/academy.js            small vanilla JS; no framework, no CDN
│   └── vendor/hls.min.js        bundled locally (Apache-2.0 licence)
├── tools/encode-lesson.sh       ffmpeg script: MP4 → encrypted HLS (Windows .bat too)
├── languages/                   English + Hindi-ready (.pot)
├── docs/
└── tests/                       PHPUnit + Playwright end-to-end
```

**Front-end approach:** server-rendered PHP templates with light JavaScript. This
keeps pages fast on mobile, avoids a build step on the server, and lets the
theme override templates. No React or other heavy framework is needed for
these screens.

### 11.2 Data model

**Content** (WordPress custom post types, so the familiar editor, revisions and
media library work):

| Post type | Key fields (post meta) |
| --- | --- |
| `nba_programme` | product_id, duration_days, grace_days, drip_mode (`enrollment` / `cohort` / `none`), certificate |
| `nba_module` | programme_id, order |
| `nba_lesson` | module_id, order, type, media_id, duration, release_offset_days, required, preview, safety_note, prerequisite_id |
| `nba_bonus` | programme_id, unlock_rule |
| `nba_resource` | attachment_id, programme_id / lesson_id |
| `nba_live_session` | cohort_id, starts_at, join_url, recording_media_id |

**High-volume and transactional data** (custom tables, indexed, created by
versioned migrations):

| Table | Columns (main) |
| --- | --- |
| `nba_enrollments` | id, user_id, programme_id, cohort_id, order_id, source, status, starts_at, ends_at, paused_days, created_at — **unique (order_id, programme_id)** |
| `nba_cohorts` | id, programme_id, name, starts_at, coach_user_id, status |
| `nba_progress` | user_id, lesson_id, status, position_sec, watched_sec, watched_map (compact bitmap), completed_at, updated_at — **PK (user_id, lesson_id)** |
| `nba_devices` | id, user_id, token_hash, label, ua_family, registered_at, last_seen_at, revoked_at, revoked_by |
| `nba_device_requests` | id, user_id, status, reason, decided_by, created_at, decided_at |
| `nba_notifications` | id, user_id, type, title, body, url, read_at, created_at |
| `nba_push_subscriptions` | id, user_id, endpoint, keys, created_at |
| `nba_activity` | id, user_id, event, object_id, created_at (for nudges and reports; pruned after N days) |
| `nba_notes` | id, user_id, lesson_id, body, updated_at (participant's private notes) |
| `nba_audit_log` | id, actor_id, action, target_type, target_id, context_json, ip_hash, created_at |
| `nba_acknowledgements` | user_id, programme_id, version, accepted_at (health and safety consent) |

### 11.3 REST API (`/wp-json/nirog-academy/v1/`)

| Endpoint | Purpose |
| --- | --- |
| `GET /me/dashboard` | Data for the dashboard |
| `GET /programmes/{id}/curriculum` | Modules, lessons and their lock state |
| `POST /lessons/{id}/progress` | Heartbeat: position + watched ranges |
| `POST /lessons/{id}/complete` | Manual completion for non-media lessons |
| `GET /media/{id}/playlist` | Signed HLS playlist (access-checked) |
| `GET /media/{id}/key` | Decryption key (access + device + signature) |
| `GET/POST /notifications` | List, mark read |
| `POST /push/subscribe` | Register web push |
| `POST /device/change-request` | Start a device change (sends code) |
| `POST /device/confirm` | Confirm with code |
| Admin endpoints | under `/admin/…`, manager capabilities only |

### 11.4 Hooks for the theme and future extensions

`nba_enrollment_created`, `nba_enrollment_status_changed`, `nba_lesson_completed`,
`nba_programme_completed`, `nba_device_changed`; filters such as
`nba_access_check`, `nba_watermark_text`, `nba_email_template`.

### 11.5 Engineering standards ("enterprise grade")

- WordPress Coding Standards (PHPCS), PHPStan level 6+, PHPUnit for services,
  Playwright end-to-end tests for the access and device rules. All free.
- GitHub Actions to run checks on every change (free minutes are included for
  private repositories, with a monthly cap).
- Semantic versioning, a changelog, versioned database migrations with
  roll-forward safety, and an uninstall that keeps data unless asked.
- **Health screen** under Academy → Settings: PHP version, cron running, mail
  sending, video folder protected, HTTPS on, secrets present.
- Translation-ready (English first, Hindi strings later).
- Performance: indexed queries, cached curriculum, progress pings batched, no
  queries in loops. Target: dashboard under 1 second on a mid-range phone on 4G
  (to be measured).

---

## 12. Safety and compliance notes (non-technical, important)

These are not engineering items, but launching the LMS raises them.

1. **Advertising claims.** India's *Drugs and Magic Remedies (Objectionable
   Advertisements) Act, 1954* lists diabetes among the conditions for which
   advertising a "cure" is restricted. I am fairly confident diabetes is on that
   list. How the Act applies to a specific programme's wording needs a lawyer,
   and ASCI's advertising guidelines also apply. **I recommend a legal review of
   programme names and sales copy** (for example "cure" versus "reversal" versus
   "remission support") before the paid launch.
2. **Safety acknowledgement before the first lesson:** continue medicines unless
   the treating doctor changes them; watch for low blood sugar during practice
   (especially on insulin or sulfonylureas); stop if dizzy; contraindications
   for certain postures (for example inversions with retinopathy or uncontrolled
   blood pressure). **These notes should be written and signed off by your
   clinical team. I am not giving medical guidance here, only showing where the
   product must display it.**
3. **Refund and access terms** for programmes, shown at checkout. Razorpay and
   Indian payment rules expect published refund and cancellation terms.
4. **Privacy policy update** covering device data, progress data, notifications
   and (if used) health logs.

---

## 13. Delivery phases

### Phase 0 — Foundations
Repository scaffold, coding standards, CI, database migrations, roles, settings,
design tokens, **clickable mockups for approval**.

### Phase 1 — Yoga programme launch (minimum to sell and deliver safely)
- Programmes, modules, lessons, bonus, resources; curriculum builder
- WooCommerce bridge: auto-enroll on payment, revoke on refund; manual grant;
  payment offers
- Single access gate; launch switch (Closed / Admin preview / Open)
- One-device policy with self-service and admin resets
- Self-hosted encrypted HLS + YouTube for previews; watermark; resume; progress
- Dashboard, Programme, Lesson, Bonus, Resources, Profile, Login, Help Center
- In-app + email notifications (welcome, lesson unlocked, live reminders)
- Health acknowledgement; audit log; participant and admin docs
- Security test checklist passed; backup restore tested

### Phase 2 — Engagement and insight
Cohorts with scheduled releases, web push, inactivity nudges, practice log and
streaks, certificates, reports and CSV, coach role, Hindi interface.

### Phase 3 — Flagship programmes
99 Day and 6 Month programmes, consultation-linked check-ins, Cure Kit
fulfilment status on the dashboard, optional low-cost video host, optional AI
help assistant (paid).

I have not put calendar estimates on these phases. Timing depends on how much
content is ready and how quickly mockups are approved. Once the open decisions
below are settled, I can give a sized estimate.

---

## 14. Running costs summary

| Item | Cost |
| --- | --- |
| Academy plugin, docs, tools | **₹0** (custom, owned by Nirog Bhumi) |
| WooCommerce, Action Scheduler, hls.js, ffmpeg, Two Factor, UpdraftPlus (free), Cloudflare (free) / Wordfence (free) | **₹0** |
| Hosting, domain | Already paid; **video bandwidth may need a plan check** |
| Razorpay | Per-transaction fee (as today) |
| Email sending | ₹0 within free SMTP tiers |
| Web push | ₹0 |
| WhatsApp Business API, video CDN, AI assistant | **Optional, paid**, not in Phase 1 |

---

## 15. Open decisions

1. **Hosting:** which provider and plan? (Decides whether self-hosted video is
   workable; see 7.1.)
2. **Strict 1 device, or 1 phone + 1 computer?** (6.4)
3. **Device reset limit:** once every 30 days self-service. Is that right?
4. **Yoga programme:** direct purchase, or consult-first like the others?
5. **Yoga programme format:** self-paced (lessons release from each person's
   start date) or **batch-based** (everyone starts together, with live sessions)?
6. **Access length after the programme ends:** none, 30 days, or 1 year
   (for revision)?
7. **Language:** English only at launch, or Hindi too?
8. **Name of the participant area:** "Academy", "My Sadhana", "Nirog Bhumi Learn"…
9. **Who reviews safety notes and the acknowledgement text** (clinical sign-off)?
