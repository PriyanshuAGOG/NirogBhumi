---
id: admin-incident-playbook
title: Incident playbook
summary: What to do if videos leak, an account is shared, the site is down or emails stop.
audience: admin
order: 100
last_reviewed: 2026-11-24
related: admin-security-and-backups
---
# A lesson video appears somewhere it should not

1. Save the link and a screenshot. Read the watermark: name and phone ending.
2. Find the participant (Academy → Enrollments → search). Check their Audit Log and device changes.
3. Pause their access while you talk to them.
4. Ask the hosting platform to remove the copy (most have a copyright form).
5. If needed, re-encode that lesson (new key) and re-import.

# An account seems to be shared

Signs: many device change requests, "Blocked: another device" entries in the Audit Log. Contact the participant, then decide whether to approve further changes.

# Participants cannot play videos

1. Academy → Video Library: status should be **Ready**.
2. Academy → Settings → Health check: all ticks green.
3. Try the lesson yourself as staff. If it works for you, check the participant's access and device.
4. If nobody can play: check hosting bandwidth/limits with Hostinger support.

# Emails stopped arriving

Send a test from WP Mail SMTP. Check the mailbox password has not changed, and that the daily sending limit was not reached.

# The site is down

Check Hostinger status and hPanel. Restore from the latest UpdraftPlus backup if an update broke the site.
