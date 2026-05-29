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
The player picks the right playlist for the current weekday **and time of day**, crossfades
between items, plays videos to the end, hides items outside their scheduled window, and
re-checks playlist.json every 60s so new content appears without touching the Pi.

It is built to run unattended for months: each video's decoder is released after it plays
(so memory doesn't creep), stuck or missing media is skipped instead of freezing the screen,
a corrupt or half-written playlist.json can never replace the last good one, and an
independent watchdog revives the loop if anything ever wedges. For extra insurance against
long-term browser memory growth you can set `reload_hours` (see below).

## playlist.json format
You normally won't hand-edit this — the dashboard has a visual editor (add/reorder/remove
items, pick files, set durations and start/end windows, and build schedule rules). The
**Advanced** toggle still exposes the raw JSON, which is validated before saving.

```json
{
  "playlists": {
    "default": { "label": "Everyday", "items": [
      { "file": "welcome.jpg", "type": "image", "duration": 10 }
    ] },
    "weekend": { "label": "Weekend", "items": [
      { "file": "clip.mp4", "type": "video", "duration": 0, "end": "2026-03-21T00:00" }
    ] }
  },
  "schedule": {
    "rules": [
      { "days": [0, 6],          "from": "00:00", "to": "23:59", "playlist": "weekend" },
      { "days": [1,2,3,4,5],     "from": "00:00", "to": "17:00", "playlist": "default" },
      { "days": [1,2,3,4,5],     "from": "17:00", "to": "23:59", "playlist": "afterhours" }
    ],
    "default": "default"
  },
  "settings": { "transition_ms": 800, "poll_seconds": 60, "reload_hours": 0 }
}
```
- **Items:** `duration` is seconds for images; use `0` for video (plays to its natural end).
  Optional `start` / `end` are local datetimes (`YYYY-MM-DDTHH:MM`) — the item only shows
  inside that window and **self-expires** once `end` passes (no need to delete it).
- **Schedule (`rules`):** checked top to bottom; the first rule matching today's weekday
  (`0`=Sunday … `6`=Saturday) and the current time wins. `from`/`to` are `HH:MM`; `to` is
  exclusive. `default` is the fallback playlist when no rule matches. *(The old flat map —
  `"schedule": {"0":"weekend", …}` — still works and is upgraded to rules when you open the editor.)*
- **Settings:** `transition_ms` crossfade length · `poll_seconds` how often the Pi re-reads
  this file · `reload_hours` optional full-page reload interval for extra long-run stability
  (`0` = off; the reload waits for an item boundary so it never cuts a video).

## Notes / gotchas
- **Image sizing:** export at exactly 1920×1080 with text kept ~5% inside the edges. The player letterboxes (`object-fit: contain`) so nothing is cropped; switch to `cover` in player.html if you'd rather fill-and-crop.
- **Filenames** are auto-sanitised on upload (spaces/odd characters become `_`). After uploading `winter activities V3.mp4` it becomes `winter_activities_V3.mp4` — use that name in the playlist.
- **noindex** is already set in player.html so search engines skip it.
