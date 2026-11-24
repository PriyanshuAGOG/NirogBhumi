---
id: admin-security-and-backups
title: Security and backups
summary: What the plugin protects, free tools to add, backups, and server rules.
audience: admin
order: 90
last_reviewed: 2026-11-24
related: admin-setup-checklist, admin-incident-playbook
---
# What the Academy does by itself

- Every lesson page, download, video playlist and video key goes through one access check: logged in, registered device, paid and active, inside the access period, lesson released, safety note accepted.
- Videos are encrypted. The key is sent only after the access check, through links that expire and only work for that logged-in participant.
- Downloads come from a private folder, never by direct link.
- One device per participant; logging in on the registered device signs out other sessions; one video plays at a time.
- A visible watermark with name and phone ending on every video.
- Login, login-link, password-reset and device-code requests are rate-limited.
- Passwords, login links and device codes are stored only as one-way hashes.
- Participants are kept out of WordPress admin. The public user list is hidden.
- Sensitive actions are recorded in **Academy → Audit Log**.

# Honest limits

Encryption stops casual downloading and link sharing. It is not studio-grade DRM: a determined technical person, or a camera pointed at a screen, can still copy a video. The watermark makes such copies traceable.

# Free tools to add

1. **Cloudflare (free plan)** in front of the site, or the **Wordfence** free plugin — a web application firewall.
2. **Two Factor** plugin (by WordPress contributors) — require it for Administrators and Academy Managers.
3. **UpdraftPlus** (free) — daily database and weekly files backup to Google Drive. **Test a restore** before launch.
4. Keep WordPress, WooCommerce, Razorpay and the theme up to date.

Do not cache Academy pages in a caching plugin. They already send "no cache" headers; if you use LiteSpeed Cache, add `/academy/` and `nba_stream` to its "Do not cache" lists.

# Server rules

Hostinger uses LiteSpeed, which follows the `.htaccess` files the plugin writes in `wp-content/uploads/nirog-academy/`. On an Nginx server, add:

```
location ~* ^/wp-content/uploads/nirog-academy/(keys|files|incoming)/ { deny all; }
location ~* ^/wp-content/uploads/nirog-academy/media/.*\.(m3u8|key|php)$ { deny all; }
```

The web server must support HTTP Range requests for `.ts` files (all standard servers do).

# Data protection

The Academy stores progress, device labels (for example "Chrome on Android"), notes and an audit log. Mention these in your privacy policy, keep only what you need (audit log retention is a setting), and remove data when someone asks. Have the policy wording checked by someone qualified in Indian data protection law (DPDP Act, 2023).
