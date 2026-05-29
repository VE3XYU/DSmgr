# DSmgr
Digital Signage Manager

A really simple and free content management system for a cPanel-based (or other PHP loving) webhost.

I was looking for a free and open source content management system for digital signage but wasn't satisfied with anything. They all want you to pay a subscription for cloud storage, etc. and only a handful allow you to host yourself. Setup was needlessly complicated...I wanted something simple.


Features:

1. ✅ User authentication — handled by cPanel Directory Privacy (Basic Auth) on the `admin/` folder
2. ✅ Simple setup; check [SETUP.md](SETUP.md)
3. ✅ Upload videos/images and set how long each is shown (visual editor in the dashboard)
4. ✅ Expire/remove old content — delete files, or give an item an end date and it disappears on its own
5. ✅ Manage the order in which media are displayed
6. ✅ Set a schedule for each item (start/end date/time)
7. ✅ Set schedules for different days **and** times of day
8. ✅ Preview mode — watch the feed exactly as the screen renders it, live or per-playlist, from the dashboard

Built to run unattended: the player releases video memory as it goes, skips stuck or missing
media instead of freezing, survives a corrupt/half-written playlist, and self-recovers via a
watchdog — so it keeps playing for months without intervention.



