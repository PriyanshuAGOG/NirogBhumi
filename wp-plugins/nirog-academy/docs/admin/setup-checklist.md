---
id: admin-setup-checklist
title: Setup checklist (install to launch)
summary: Everything to do, in order, before participants get access.
audience: admin
order: 10
last_reviewed: 2026-11-24
related: admin-create-a-programme, admin-prepare-and-upload-videos, admin-security-and-backups
---
# In short

Install the plugin, keep Nirog Bhumi Courses **Closed** while you build and test, then switch it to **Open**. While Closed, every Courses address shows "page not found" to everyone except staff (Administrators and Course Managers/Coaches, logged in through `wp-admin`), and no participant emails are sent — welcome emails for anyone who buys in the meantime are held and sent automatically when you open. Work through the list below in order.

# 1. Install

1. WordPress → **Plugins → Add New → Upload Plugin** → choose `nirog-academy.zip` → **Install** → **Activate**.
2. **Settings → Permalinks**: make sure "Post name" is selected and press **Save** once.
3. Open **Courses → Settings**. Check the **Health check** box at the bottom: every line should have a green tick.

# 2. Email that arrives

WordPress email from Hostinger often lands in Spam. Before launch:

1. Create a mailbox such as `academy@yourdomain` in Hostinger (hPanel → Emails).
2. Install the free **WP Mail SMTP** plugin and connect it to that mailbox (SMTP host and port are shown in hPanel).
3. In hPanel → Domains → DNS, confirm SPF, DKIM and DMARC records exist for the domain.
4. Use WP Mail SMTP's "Send test email" to a Gmail address and check it arrives in the Inbox.

# 3. Reliable timing (cron)

Lesson emails, reminders and expiry run on a schedule. On a quiet site WordPress only checks the schedule when someone visits, so:

1. Add to `wp-config.php`: `define('DISABLE_WP_CRON', true);`
2. hPanel → **Advanced → Cron Jobs** → add a job every 15 minutes running:
   `wget -q -O - https://yourdomain/wp-cron.php?doing_wp_cron >/dev/null 2>&1`

# 4. Settings

In **Courses → Settings** set: support email, WhatsApp number, devices per participant (1), self-service device change (30 days), default access (365 days).

# 5. Build the first programme

Want to see everything first? In **Courses → Course Studio** press **Load demo programme** and log in with the demo participant in a private window — see [The demo programme](demo-programme.md). Remove it before launch.

Open **Courses → Course Studio** and follow [Building a programme in the Course Studio](create-a-programme.md), [Booklets](booklets.md) and [Preparing and uploading videos](prepare-and-upload-videos.md). Add the **health and safety note** in the programme's Settings tab — written and approved by your clinical team.

# 6. Connect it to payment

Follow [Enrollments and payments](enrollments-and-payments.md): link the WooCommerce product, set it as Virtual, and make sure the store's launch state allows it to be bought.

# 7. Test end to end (while Closed)

1. Create a test participant with **Courses → Enrollments → Enroll someone**.
2. Temporarily set Nirog Bhumi Courses to **Open**, then on a phone: open the welcome email, choose a password, accept the safety note, play a lesson to the end.
3. On a second device log in with the same account: you should see "Your programme is on another device".
4. Make a real low-value Razorpay payment for the product and refund it. Access should open, then close.
5. Set it back to **Closed** until launch day.
6. Remove the demo programme if you loaded it (Course Studio → **Remove demo**).

# 8. Launch

1. Back up the site (see [Security and backups](security-and-backups.md)).
2. **Courses → Settings → Course status → Open**.
3. Send an announcement to welcome everyone.
