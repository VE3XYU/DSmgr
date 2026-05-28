# Digital Signage Manager — Setup

A tiny self-hosted signage system. No database, no framework.
- **player.html** — what the Raspberry Pi loads (public, but obscure URL + noindex).
- **playlist.json** — the schedule and timing data.
- **admin/admin.php** — dashboard to upload media and edit playlists. Protected by cPanel Basic Auth.
- **uploads/** — media files live here.

## 1. Upload to cPanel
Put the whole folder somewhere non-obvious in your signage site, e.g.:
```
public_html/mds/        (so the player is https://yoursite/mds/player.html)
```
Use cPanel File Manager or FTP. Keep the structure intact (`admin/` and `uploads/` as subfolders).

Make sure `uploads/` is writable by PHP (permissions 755 is usually fine on cPanel; if uploads fail, try 775).

## 2. Lock the dashboard with cPanel Basic Auth
This is your login. cPanel handles it — the app has no password code.
1. cPanel → **Directory Privacy** (sometimes "Password Protect Directories").
2. Navigate to the **`mds/admin`** folder.
3. Tick **"Password protect this directory"**, give it a label, Save.
4. Add a user (your username + password). Add a second for a colleague if needed.

Now visiting `https://yoursite/mds/admin/admin.php` prompts for login.
The player URL stays open (no prompt) so the Pi loads without credentials.

> To add/remove who has access later: same Directory Privacy screen.

## 3. Raise the upload size limit (for video)
Default cPanel limits will reject larger MP4s. cPanel → **MultiPHP INI Editor** → select your domain → set:
- `upload_max_filesize` = `256M`
- `post_max_size` = `256M`
- `memory_limit` = `256M`
- `max_execution_time` = `300`

Tip: export signage video as 1080p H.264 at ~8–10 Mbps — usually well under the limit and smooth on a Pi 4.

## 4. Point the Raspberry Pi at the player
In your Chromium kiosk launch command, the URL is:
```
https://yoursite/mds/player.html
```
The player auto-detects the weekday, plays the matching playlist, crossfades between
items, plays videos to the end, and re-checks playlist.json every 60s so new content
appears without touching the Pi.

## playlist.json format
```json
{
  "playlists": {
    "default":  { "label": "Everyday", "items": [ {"file":"welcome.jpg","type":"image","duration":10} ] },
    "weekend":  { "label": "Weekend",  "items": [ {"file":"clip.mp4","type":"video","duration":0} ] }
  },
  "schedule": { "0":"weekend","1":"default","2":"default","3":"default","4":"default","5":"default","6":"weekend" },
  "settings": { "transition_ms": 800, "poll_seconds": 60 }
}
```
- `duration` is seconds for images; use `0` for video (plays to its natural end).
- `schedule` keys: `0`=Sunday … `6`=Saturday → the playlist name to show that day.
- You normally won't hand-edit this — the dashboard's text box does it, and validates the JSON before saving.

## Notes / gotchas
- **Image sizing:** export at exactly 1920×1080 with text kept ~5% inside the edges. The player letterboxes (`object-fit: contain`) so nothing is cropped; switch to `cover` in player.html if you'd rather fill-and-crop.
- **Filenames** are auto-sanitised on upload (spaces/odd characters become `_`). After uploading `winter activities V3.mp4` it becomes `winter_activities_V3.mp4` — use that name in the playlist.
- **noindex** is already set in player.html so search engines skip it.
