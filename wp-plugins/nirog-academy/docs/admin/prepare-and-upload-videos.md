---
id: admin-prepare-and-upload-videos
title: Preparing and uploading videos
summary: Turning an MP4 into a protected video, uploading it to Hostinger and adding it to a lesson.
audience: admin
order: 30
last_reviewed: 2026-11-24
related: admin-create-a-programme, admin-security-and-backups
---
# In short

Each lesson video is converted **on your computer** into an encrypted format (HLS), uploaded as a folder, then registered with one click. The original MP4 never goes on the website. This is free; it uses your hosting's storage and bandwidth.

# One-time setup on your computer

- **Windows:** open PowerShell and run `winget install Gyan.FFmpeg`. Copy `tools/encode-lesson.ps1` from the plugin to a folder for your videos.
- **Mac:** install Homebrew, then `brew install ffmpeg`. Copy `tools/encode-lesson.sh`.

# Convert a video

1. Put the edited MP4 in your videos folder. Use a clear name, for example `week-1-breath-awareness.mp4`.
2. Run:
   - Windows: `.\encode-lesson.ps1 week-1-breath-awareness.mp4`
   - Mac: `./encode-lesson.sh week-1-breath-awareness.mp4`
   - Audio-only lesson (Yoga Nidra, pranayama): add `-Audio` (Windows) or `--audio` (Mac).
3. A folder with the same name appears, containing `master.m3u8`, three quality folders (360p, 540p, 720p) and `enc.key`.

A 20-minute lesson takes a few minutes to convert and is roughly 300–400 MB in total.

# Upload

1. hPanel → **Files → File Manager** (or an SFTP app such as FileZilla).
2. Open `public_html/wp-content/uploads/nirog-academy/media/`.
3. Upload the **whole folder** (zip it, upload, then "Extract" in File Manager).
4. **Academy → Course Studio → Video library → Find newly uploaded videos** (or Academy → Import Videos).
5. The video appears in the Studio's **Video library** with status **Ready**. The key has been moved to a private folder.

# Add it to a lesson

In the Course Studio, open the lesson → **From the video library** → choose the video. It saves automatically.

# Other sources

In the Studio's **Video library → Or link a video** you can also add:

- **YouTube (unlisted)** — free and fast, but *not protected*: anyone with the link can watch. Use only for free previews.
- **External video service** — an HLS or MP4 link from a paid service such as Bunny Stream, if hosting bandwidth becomes a problem. Lessons do not need to change; only the Video Library entry.

# Hosting limits (Hostinger)

- Shared plans limit the **number of files** (inodes). The script writes each quality as one file, so a lesson is only about 8 files.
- "Unlimited" bandwidth plans still have fair-use rules. Check with Hostinger support before large launches; if video traffic becomes a problem, move busy videos to an external service.

# If this doesn't work

| Status / problem | Fix |
| --- | --- |
| "No master.m3u8 inside" | You uploaded the parent folder or a single file. Upload the lesson folder itself. |
| "No enc.key found" | Re-run the script; do not delete enc.key before importing. |
| "Folder names may only use letters, numbers, - and _" | Rename the folder, e.g. `week-1-breath`. |
| Video plays for staff but not participants | Check the lesson is released and the participant's access is active. |
