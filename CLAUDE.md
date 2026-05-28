# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

DSmgr (Digital Signage Manager) is a deliberately minimal, self-hosted digital signage CMS
for cPanel / generic PHP hosting. **No database, no framework, no build step, no package manager,
no test suite.** The entire app is three files plus a JSON data file. Keep it that way — the
project's whole point is simplicity, so resist introducing dependencies, build tooling, or a
backend framework unless explicitly asked.

## Architecture

Three moving parts share state only through `playlist.json` on disk:

- **`player.html`** — Pure client-side JS, loaded by a Raspberry Pi in Chromium kiosk mode at an
  obscure public URL (`noindex`). It fetches `playlist.json`, picks today's playlist via the
  `schedule` map, and loops through items. Re-fetches `playlist.json` every `poll_seconds` so
  content updates reach the screen without touching the Pi.
- **`admin/admin.php`** — Server-side dashboard. Uploads/deletes media in `uploads/` and reads/writes
  `playlist.json` (validates JSON before saving, pretty-prints on write). **Has no auth code by
  design** — the `admin/` directory is protected by cPanel Directory Privacy (Apache Basic Auth),
  which runs before PHP. Don't add app-level login.
- **`playlist.json`** — The single source of truth. `playlists` = named item lists; `schedule` maps
  weekday `0`=Sunday … `6`=Saturday → playlist name; `settings` holds `transition_ms` and `poll_seconds`.
- **`uploads/`** — Media directory (not in the repo; created on the host). Both other files reference it.

`player.html` resolves the parent dir's `uploads/`; `admin.php` computes `$ROOT = dirname(__DIR__)`
because it lives one level down in `admin/`. The deployed layout is e.g. `mds/player.html`,
`mds/playlist.json`, `mds/admin/admin.php`, `mds/uploads/` — preserve that relative structure.

## Key behaviors to preserve when editing

- **player.html** uses two stacked `.layer` divs for crossfade double-buffering; it waits for media
  `oncanplaythrough`/`onload` before swapping to avoid black flashes, with timeout safety nets. Images
  use `duration` seconds (default 10 if `0`/missing); videos use `duration: 0` and play to natural end
  (`onended` advances). `object-fit: contain` letterboxes — switching to `cover` fills-and-crops.
- **admin.php** sanitises upload filenames via `preg_replace('/[^A-Za-z0-9._-]/','_', ...)` — so a
  playlist must reference the sanitised name. Allowed types live in `$IMAGE_EXT` / `$VIDEO_EXT`; update
  both PHP arrays *and* the player's `buildElement` type handling if adding formats.

## There is no build/lint/test

Validate changes by reasoning + manual inspection. To smoke-test locally you can serve the folder with
PHP's built-in server (`php -S localhost:8000` from the repo root) and open `/player.html`; `admin.php`
needs a writable `uploads/` dir and a `playlist.json`. Upload-size limits (video) are configured on the
host, not in code — see `SETUP.md`.

## Deployment notes

Setup, cPanel auth, and host PHP limits (`upload_max_filesize`, `post_max_size`, etc.) are documented in
`SETUP.md`. The targeted feature roadmap is in `README.md`.
