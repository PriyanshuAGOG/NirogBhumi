---
id: admin-live-sessions
title: Running live sessions
summary: How live classes work, which tool to use, who does what in the team, and what happens before, during and after.
audience: admin
order: 30
last_reviewed: 2026-11-25
related: admin-create-a-programme, admin-announcements-and-emails
---
# In short

Nirog Bhumi Courses does not run video calls itself — that would be expensive and fragile. Each session uses **Google Meet** (recommended: one button creates the link), any other **meeting link** (Zoom, Teams), or a **YouTube Live** stream. Participants always go through their session page on the course pages, so only enrolled people get in, attendance is counted, and reminders and recordings are handled for you.

# Which tool to use

| | Meeting link (Zoom / Google Meet) | YouTube Live (unlisted) |
| --- | --- | --- |
| Best for | Q&A, small groups, seeing participants | Guided practice with many people |
| Participants | Join in the meeting app, can talk | Watch on Nirog Bhumi Courses page, cannot talk |
| Cost | Free plans have time limits on group calls (check your plan) | Free |
| Recording | Zoom/Meet recording (paid plans) or screen recording | Saved automatically on YouTube |
| Privacy | Anyone with the meeting link can try to join — use a waiting room / passcode | Unlisted: hidden from search, but anyone with the link can watch |

Free-plan limits change often; check your Zoom or Google Workspace plan before scheduling long sessions.

# Google Meet with one click (recommended)

Once your Google account is connected, every live session has a **Create Google Meet link** button. It creates an event in that Google account's calendar with its own Meet link, invites the session's host, and keeps the event's title and time in step when you change them in the Studio. Deleting a session removes the event.

## One-time set-up (about 10 minutes, free)

Do this signed in to the Google account that should own the meetings (for example the clinic's account):

1. Open [Google Cloud Console](https://console.cloud.google.com/) and create a project (any name, e.g. "Nirog Bhumi Courses").
2. **APIs & Services → Library**: find **Google Calendar API** and press **Enable**.
3. **APIs & Services → OAuth consent screen**: choose **External**, fill in the app name and your email. Under **Test users** add the Google account you will connect. (You can leave the app in "Testing"; see the note below.)
4. **APIs & Services → Credentials → Create credentials → OAuth client ID** → type **Web application**. Under **Authorised redirect URIs** paste the address shown in **Courses → Settings → Google Meet** (it ends in `admin-post.php?action=nba_google_callback`). Create.
5. Copy the **Client ID** and **Client secret** into **Courses → Settings → Google Meet** and press **Save**.
6. Press **Connect Google account**, choose the account and allow access to calendar events.

Settings then shows **Connected**. In the Studio, open a live session → **How people join → Google Meet or meeting link → Create Google Meet link**.

Notes (please check these on your own account — Google changes details from time to time):

- While the consent screen is in **Testing**, Google may ask you to reconnect after about 7 days. Publishing the app (OAuth consent screen → **Publish app**) avoids that; for an app used only by your own team, Google usually does not require a review for this permission, but it may show an "unverified app" warning that you can continue past.
- **Who can enter without waiting**: in Meet, people outside your organisation may have to "Ask to join" and be admitted by the host. Before the session, the host can open the meeting's **Host controls** and set access so participants can join directly, or admit people as they arrive. Participants only see the link on their session page, from 15 minutes before the start.
- **Recording** in Google Meet is available only on some Google Workspace plans. Without it, record with free screen-recording software, or run larger guided classes on YouTube Live, which records automatically.
- **Length limits**: free Google accounts limit group calls of three or more people to 60 minutes (at the time of writing). A Google Workspace plan removes this.

You can still paste any meeting link by hand; the button is only a shortcut.

# Who does what

- **Host** (for example the yoga teacher): teaches. Is chosen on each session, sees their sessions on **Courses → Live desk**.
- **Technical lead** (you): creates sessions in the Course Studio, starts the meeting or stream, admits people, adds the recording afterwards.
- Give team members the **Course Coach** role (they see the Live desk and participants) or **Course Manager** (they can also edit programmes). See [Reports, participants and staff roles](reports-and-roles.md).

# Set up a session

1. **Course Studio → programme → Weeks** (under the right week) or the **Live sessions** tab → **Live session**.
2. Fill in: title, date and time (India time), length.
3. **How people join**: Google Meet or meeting link, or YouTube Live.
   - Google Meet: press **Create Google Meet link** (after the one-time set-up above), or paste a link.
   - YouTube: in YouTube Studio create the live stream as **Unlisted** and allow embedding, then paste its link.
   - Zoom: turn on the waiting room or passcode, then paste the link.
4. **What participants should know** (for example "have a mat and a cushion ready").
5. **Host**, and **Notes for the team** — private: meeting ID, passcode, host key, who handles what.
6. **Batch** (for batch programmes) and **Show in week**.
7. Switch **Visible to participants** on.

# What happens automatically

- The day before: reminder on the course pages inbox and by email.
- 15–30 minutes before: second reminder.
- 15 minutes before: the **Join** button (or the stream) appears on the session page.
- Every Join is recorded: see **Attendance** in the Studio or on the Live desk.

# On the day

1. Open **Courses → Live desk**. "Happening now" shows the session with its link and team notes.
2. Start the meeting or the YouTube stream from your own account.
3. If needed, press **Send reminder** to nudge everyone.
4. Watch **joined** grow as people arrive.

# Changes

- **Reschedule**: change the date and time in the Studio. Reminders reset for the new time.
- **Cancel**: open the session in the Studio → **Cancel session**. Everyone is told immediately (inbox and email). **Restore session** undoes it.

# After the session

1. Add the recording as a **Recorded lesson** (in the week or in Bonus) — a protected video or a YouTube link from the Video library.
2. Open the live session in the Studio → **After the session → Recording** → choose that lesson. Participants then see **Watch the recording**.
