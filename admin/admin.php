<?php
// admin.php — Digital Signage Manager
// AUTH: none here. This folder is protected by cPanel Directory Privacy (Basic Auth).
// Apache authenticates before this script runs.

$ROOT     = dirname(__DIR__);          // the signage root (parent of /admin)
$UPLOADS  = $ROOT . '/uploads';
$PLAYLIST = $ROOT . '/playlist.json';

$IMAGE_EXT = ['jpg','jpeg','png','gif','webp'];
$VIDEO_EXT = ['mp4','webm','m4v'];

function read_playlist($path) {
  if (!file_exists($path)) return ['playlists'=>[], 'schedule'=>[], 'settings'=>[]];
  $j = json_decode(file_get_contents($path), true);
  return is_array($j) ? $j : ['playlists'=>[], 'schedule'=>[], 'settings'=>[]];
}

$notice = '';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'upload' && !empty($_FILES['media']['name'][0])) {
    $count = count($_FILES['media']['name']);
    $ok = 0;
    for ($i = 0; $i < $count; $i++) {
      if ($_FILES['media']['error'][$i] !== UPLOAD_ERR_OK) continue;
      $name = basename($_FILES['media']['name'][$i]);
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, array_merge($IMAGE_EXT, $VIDEO_EXT))) continue;
      // sanitise filename
      $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
      if (move_uploaded_file($_FILES['media']['tmp_name'][$i], "$UPLOADS/$safe")) $ok++;
    }
    $notice = "$ok file(s) uploaded.";
  }

  if ($action === 'delete' && !empty($_POST['file'])) {
    $safe = basename($_POST['file']);
    $target = "$UPLOADS/$safe";
    if (is_file($target)) { unlink($target); $notice = "Deleted $safe."; }
  }

  if ($action === 'save_playlist' && isset($_POST['playlist_json'])) {
    $decoded = json_decode($_POST['playlist_json'], true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
      $notice = "NOT SAVED — invalid JSON: " . json_last_error_msg();
    } else {
      // pretty-print so it stays human-editable; write atomically (temp + rename)
      // so the player never reads a half-written file mid-save.
      $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      $tmp  = $PLAYLIST . '.tmp';
      if (file_put_contents($tmp, $json) !== false && rename($tmp, $PLAYLIST)) {
        $notice = "Playlist saved.";
      } else {
        @unlink($tmp);
        $notice = "NOT SAVED — could not write playlist.json (check folder permissions).";
      }
    }
  }
}

// --- Gather current state ---
$files = [];
$entries = is_dir($UPLOADS) ? scandir($UPLOADS) : [];
foreach ($entries as $f) {
  if ($f === '.' || $f === '..') continue;
  $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
  if (!in_array($ext, array_merge($IMAGE_EXT, $VIDEO_EXT))) continue;
  $files[] = ['name'=>$f, 'type'=>in_array($ext,$VIDEO_EXT)?'video':'image',
              'size'=>filesize("$UPLOADS/$f")];
}
$playlist_data = read_playlist($PLAYLIST);
$playlist_raw  = json_encode($playlist_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// AJAX uploads/deletes return the refreshed file list as JSON and exit — the page
// never reloads, so anything the user is mid-edit in the playlist editor is preserved.
if (!empty($_POST['ajax'])) {
  header('Content-Type: application/json');
  echo json_encode(['notice'=>$notice, 'files'=>$files], JSON_UNESCAPED_SLASHES);
  exit;
}

// Safe to embed inside a <script> tag
$JS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Signage Dashboard</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 900px; margin: 1.5rem auto; padding: 0 1rem; color:#222; }
  h1 { font-size: 1.3rem; } h2 { font-size: 1.05rem; margin-top: 2rem; border-bottom:1px solid #ddd; padding-bottom:.3rem;}
  h3 { font-size: .95rem; margin: 0; }
  .notice { background:#eef6ff; border:1px solid #b6d4f5; padding:.6rem .8rem; border-radius:6px; }
  table { border-collapse: collapse; width:100%; }
  td, th { text-align:left; padding:.4rem .6rem; border-bottom:1px solid #eee; font-size:.9rem; }
  textarea { width:100%; height:340px; font-family:monospace; font-size:.85rem; }
  button { cursor:pointer; padding:.35rem .7rem; }
  button[disabled] { opacity:.6; cursor:default; }
  .tag { font-size:.7rem; padding:.1rem .4rem; border-radius:4px; background:#eee; }
  .tag.video { background:#ffe6cc; } .tag.image { background:#e6f0ff; }
  code { background:#f4f4f4; padding:.1rem .3rem; border-radius:3px; }

  /* --- Structured playlist editor --- */
  .bar { display:flex; gap:.5rem; align-items:center; margin:1.1rem 0 .4rem; }
  .bar h3 { flex:1; }
  .pl { border:1px solid #ddd; border-radius:8px; padding:.7rem .8rem; margin-bottom:1rem; }
  .pl > header { display:flex; gap:.5rem; align-items:center; justify-content:space-between; margin-bottom:.3rem; }
  .pl > header input[type=text] { font-size:.85rem; padding:.15rem .3rem; }
  table.items { width:100%; }
  table.items th, table.items td { padding:.3rem .4rem; border-bottom:1px solid #f3f3f3; font-size:.82rem; vertical-align:middle; }
  table.items input[type=number] { width:4.5rem; }
  table.items input[type=datetime-local] { font-size:.78rem; }
  table.items select { max-width:14rem; }
  .row-btns button, .rule-btns button { padding:.1rem .4rem; font-size:.8rem; }
  .rule { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; border:1px solid #eee; border-radius:6px; padding:.4rem .55rem; margin-bottom:.4rem; font-size:.83rem; }
  .rule .days label { font-size:.72rem; margin-right:.2rem; white-space:nowrap; }
  .rule input[type=time] { font-size:.8rem; }
  .muted { color:#999; } .missing { color:#c00; }
  details.adv { margin-top:1.5rem; } details.adv summary { cursor:pointer; color:#36c; }
</style>
</head>
<body>
  <h1>Digital Signage Manager — Dashboard</h1>
  <p class="notice" id="notice"<?= $notice ? '' : ' style="display:none"' ?>><?= htmlspecialchars($notice) ?></p>

  <h2>Upload media</h2>
  <form method="post" enctype="multipart/form-data" id="uploadForm">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="media[]" multiple accept="image/*,video/*" required>
    <button type="submit">Upload</button>
  </form>
  <p style="font-size:.8rem;color:#666">Images: jpg, png, gif, webp · Video: mp4, webm, m4v. Export images at 1920×1080.
    Uploading won't disturb edits in progress below.</p>

  <h2>Current files</h2>
  <table>
    <thead><tr><th>File</th><th>Type</th><th>Size</th><th></th></tr></thead>
    <tbody id="fileRows"></tbody>
  </table>

  <h2>Playlists &amp; schedule</h2>
  <p style="font-size:.85rem;color:#555">
    A <b>playlist</b> is an ordered list of items. Each item has a <b>duration</b> in seconds
    (videos play to the end, so they use 0) and an optional <b>start/end</b> window — outside that
    window the item is hidden, and it disappears for good once the end passes. The <b>schedule</b>
    decides which playlist is on screen: rules are checked top to bottom, and the first one matching
    today's weekday and the current time wins. The screen updates within a minute of saving.
  </p>

  <form method="post" id="editorForm">
    <input type="hidden" name="action" value="save_playlist">
    <input type="hidden" name="playlist_json" id="playlist_json">

    <div class="bar"><h3>Playlists</h3>
      <button type="button" onclick="addPlaylist()">+ Add playlist</button></div>
    <div id="playlists"></div>

    <div class="bar"><h3>Schedule</h3>
      <button type="button" onclick="addRule()">+ Add rule</button></div>
    <div id="rules"></div>
    <p style="font-size:.83rem">When no rule matches, show:
      <select id="schedDefault"></select></p>

    <div class="bar"><h3>Settings</h3></div>
    <p style="font-size:.83rem">
      Crossfade (ms): <input type="number" id="setTransition" min="0" style="width:6rem">
      &nbsp;·&nbsp; Re-check every (seconds): <input type="number" id="setPoll" min="5" style="width:6rem">
    </p>

    <p><button type="submit">Save playlist</button></p>
  </form>

  <details class="adv">
    <summary>Advanced — edit raw JSON</summary>
    <p style="font-size:.82rem;color:#666">Edits here are validated before saving. Use this for bulk
      changes or to rename a playlist id (the visual editor above can't rename ids).</p>
    <form method="post">
      <input type="hidden" name="action" value="save_playlist">
      <textarea name="playlist_json"><?= htmlspecialchars($playlist_raw) ?></textarea>
      <p><button type="submit">Save raw JSON</button></p>
    </form>
  </details>

<script>
const VIDEO_EXT = ["mp4","webm","m4v"];
const DAY_NAMES = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
const FILES = <?= json_encode($files, $JS) ?>;
let DATA = <?= json_encode($playlist_data, $JS) ?>;
let dirty = false;   // unsaved changes in the editor?

// --- Normalise loaded data so the editor always has something to render ---
DATA.playlists = DATA.playlists || {};
DATA.settings  = DATA.settings  || {};
DATA.schedule  = normalizeSchedule(DATA.schedule);

function firstPlaylistKey(){ const k = Object.keys(DATA.playlists); return k.length ? k[0] : "default"; }

// Upgrade the legacy weekday map ({ "0":"weekend", ... }) to the rules format for editing.
function normalizeSchedule(sched){
  if (sched && Array.isArray(sched.rules))
    return { rules: sched.rules, default: sched.default || firstPlaylistKey() };
  const rules = [];
  if (sched && typeof sched === "object") {
    const byPl = {};
    for (let d = 0; d < 7; d++){ const k = sched[String(d)]; if (k) (byPl[k] = byPl[k] || []).push(d); }
    for (const pl in byPl) rules.push({ days: byPl[pl], from: "00:00", to: "23:59", playlist: pl });
  }
  // The player's legacy path falls back to the literal playlist "default" on unmapped
  // days; keep that so merely opening + saving a legacy file doesn't change the screen.
  return { rules, default: "default" };
}

function esc(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function el(html){ const t = document.createElement("template"); t.innerHTML = html.trim(); return t.content.firstChild; }
function typeFromName(name){ return VIDEO_EXT.includes((name.split(".").pop()||"").toLowerCase()) ? "video" : "image"; }
function markDirty(){ dirty = true; }

// --- Playlist / item mutations ---
function setLabel(pk, v){ DATA.playlists[pk].label = v; }
function setItem(pk, i, field, v){
  const it = DATA.playlists[pk].items[i];
  if (field === "duration") v = parseInt(v, 10) || 0;
  it[field] = v;
}
function setItemFile(pk, i, name){
  const it = DATA.playlists[pk].items[i];
  it.file = name;
  const t = typeFromName(name);
  if (t !== it.type){
    it.type = t;
    it.duration = (t === "video") ? 0 : (it.duration || 10);
    renderPlaylists();            // type flip changes the duration field
  }
}
function moveItem(pk, i, dir){
  const a = DATA.playlists[pk].items, j = i + dir;
  if (j < 0 || j >= a.length) return;
  [a[i], a[j]] = [a[j], a[i]]; markDirty(); renderPlaylists();
}
function removeItem(pk, i){ DATA.playlists[pk].items.splice(i, 1); markDirty(); renderPlaylists(); }
function addItem(pk){
  const first = FILES[0] ? FILES[0].name : "";
  const t = first ? typeFromName(first) : "image";
  DATA.playlists[pk].items.push({ file: first, type: t, duration: t === "video" ? 0 : 10 });
  markDirty(); renderPlaylists();
}
function addPlaylist(){
  let key = prompt("New playlist id (letters, numbers, - and _):", "");
  if (!key) return;
  key = key.replace(/[^A-Za-z0-9_-]/g, "");
  if (!key) { alert("Id must contain letters or numbers."); return; }
  if (DATA.playlists[key]) { alert("That id already exists."); return; }
  DATA.playlists[key] = { label: key, items: [] };
  markDirty(); renderAll();
}
function removePlaylist(pk){
  if (!confirm("Delete playlist '" + pk + "'? Its items are removed (uploaded files stay).")) return;
  delete DATA.playlists[pk];
  markDirty(); renderAll();
}

// --- Schedule mutations ---
function toggleDay(i, d, on){
  const r = DATA.schedule.rules[i]; r.days = r.days || [];
  const k = r.days.indexOf(d);
  if (on && k < 0) r.days.push(d);
  if (!on && k >= 0) r.days.splice(k, 1);
  r.days.sort((a, b) => a - b);
}
function setRule(i, field, v){ DATA.schedule.rules[i][field] = v; }
function moveRule(i, dir){
  const a = DATA.schedule.rules, j = i + dir;
  if (j < 0 || j >= a.length) return;
  [a[i], a[j]] = [a[j], a[i]]; markDirty(); renderRules();
}
function removeRule(i){ DATA.schedule.rules.splice(i, 1); markDirty(); renderRules(); }
function addRule(){
  DATA.schedule.rules.push({ days: [0,1,2,3,4,5,6], from: "00:00", to: "23:59", playlist: firstPlaylistKey() });
  markDirty(); renderRules();
}

// --- Rendering: file list (kept in sync with FILES so AJAX upload/delete needn't reload) ---
function humanSize(b){ const u=['B','KB','MB','GB']; let i=0; b=Number(b)||0; while(b>=1024 && i<3){b/=1024;i++;} return (Math.round(b*10)/10)+' '+u[i]; }
function renderFiles(){
  const tb = document.getElementById("fileRows");
  if (!FILES.length){ tb.innerHTML = '<tr><td colspan="4" style="color:#999">No files yet.</td></tr>'; return; }
  tb.innerHTML = FILES.map(f => `<tr>
    <td><code>${esc(f.name)}</code></td>
    <td><span class="tag ${f.type === 'video' ? 'video' : 'image'}">${esc(f.type)}</span></td>
    <td>${humanSize(f.size)}</td>
    <td><button type="button" class="del" data-name="${esc(f.name)}">Delete</button></td>
  </tr>`).join("");
}

// --- Rendering: playlists ---
function fileOptions(sel){
  let opts = FILES.map(f => `<option value="${esc(f.name)}"${f.name === sel ? " selected" : ""}>${esc(f.name)}</option>`).join("");
  if (sel && !FILES.some(f => f.name === sel))
    opts = `<option value="${esc(sel)}" selected class="missing">${esc(sel)} — missing</option>` + opts;
  if (!FILES.length && !sel) opts = `<option value="">(upload files first)</option>`;
  return opts;
}

function renderPlaylists(){
  const host = document.getElementById("playlists");
  host.innerHTML = "";
  const keys = Object.keys(DATA.playlists);
  if (!keys.length){ host.innerHTML = '<p class="muted">No playlists yet — add one above.</p>'; return; }
  for (const pk of keys){
    const pl = DATA.playlists[pk]; pl.items = pl.items || [];
    let rows = "";
    pl.items.forEach((it, i) => {
      const isVid = it.type === "video";
      rows += `<tr data-i="${i}">
        <td><select data-field="file">${fileOptions(it.file)}</select></td>
        <td><span class="tag ${isVid ? 'video' : 'image'}">${isVid ? 'video' : 'image'}</span></td>
        <td>${isVid
          ? '<span class="muted">to end</span>'
          : `<input type="number" min="1" data-field="duration" value="${it.duration || 10}">`}</td>
        <td><input type="datetime-local" data-field="start" value="${esc(it.start || '')}"></td>
        <td><input type="datetime-local" data-field="end" value="${esc(it.end || '')}"></td>
        <td class="row-btns">
          <button type="button" class="mv" data-dir="-1" title="Move up">↑</button>
          <button type="button" class="mv" data-dir="1" title="Move down">↓</button>
          <button type="button" class="rm" title="Remove">✕</button>
        </td></tr>`;
    });
    host.appendChild(el(`<div class="pl" data-pk="${esc(pk)}">
      <header>
        <div>id <code>${esc(pk)}</code> &nbsp; label
          <input type="text" class="pl-label" value="${esc(pl.label || '')}"></div>
        <button type="button" class="rm-pl">Delete playlist</button>
      </header>
      <table class="items">
        <tr><th>File</th><th>Type</th><th>Duration (s)</th><th>Start (optional)</th><th>End (optional)</th><th></th></tr>
        ${rows || '<tr><td colspan="6" class="muted">No items yet.</td></tr>'}
      </table>
      <p style="margin:.4rem 0 0"><button type="button" class="add-item">+ Add item</button></p>
    </div>`));
  }
}

function renderRules(){
  const host = document.getElementById("rules");
  host.innerHTML = "";
  DATA.schedule.rules = DATA.schedule.rules || [];
  if (!DATA.schedule.rules.length)
    host.innerHTML = '<p class="muted">No rules — the fallback playlist below always shows.</p>';
  DATA.schedule.rules.forEach((r, i) => {
    const days = (r.days || []).map(Number);
    const dayboxes = DAY_NAMES.map((nm, d) =>
      `<label><input type="checkbox" class="day" data-d="${d}"${days.includes(d) ? " checked" : ""}>${nm}</label>`).join("");
    const plopts = Object.keys(DATA.playlists).map(k =>
      `<option value="${esc(k)}"${k === r.playlist ? " selected" : ""}>${esc(k)}</option>`).join("");
    host.appendChild(el(`<div class="rule" data-i="${i}">
      <span class="days">${dayboxes}</span>
      <span>from <input type="time" data-field="from" value="${esc(r.from || '00:00')}"></span>
      <span>to <input type="time" data-field="to" value="${esc(r.to || '23:59')}"></span>
      <span>show <select data-field="playlist">${plopts}</select></span>
      <span class="rule-btns">
        <button type="button" class="mv" data-dir="-1" title="Move up">↑</button>
        <button type="button" class="mv" data-dir="1" title="Move down">↓</button>
        <button type="button" class="rm" title="Remove">✕</button>
      </span></div>`));
  });
}

function renderSchedDefault(){
  const sel = document.getElementById("schedDefault");
  const keys = Object.keys(DATA.playlists);
  if (keys.length && !keys.includes(DATA.schedule.default)) DATA.schedule.default = keys[0];
  sel.innerHTML = keys.length
    ? keys.map(k => `<option value="${esc(k)}"${k === DATA.schedule.default ? " selected" : ""}>${esc(k)}</option>`).join("")
    : '<option value="default">default</option>';
}

function renderAll(){ renderFiles(); renderPlaylists(); renderRules(); renderSchedDefault(); }

// --- Uploads & deletes over fetch, so the page never reloads mid-edit ---
function setNotice(msg){
  const n = document.getElementById("notice");
  n.textContent = msg || "";
  n.style.display = msg ? "" : "none";
}
function applyFiles(files){
  FILES.length = 0;
  (files || []).forEach(f => FILES.push(f));
  renderFiles();
  renderPlaylists();   // refresh file dropdowns and any "missing" markers
}
async function postAjax(fd){
  fd.set("ajax", "1");
  const res = await fetch(location.href, { method: "POST", body: fd });
  if (!res.ok) throw new Error("HTTP " + res.status);
  return res.json();
}

document.getElementById("uploadForm").addEventListener("submit", async e => {
  e.preventDefault();
  const form = e.target, btn = form.querySelector("button");
  btn.disabled = true; const label = btn.textContent; btn.textContent = "Uploading…";
  try {
    const data = await postAjax(new FormData(form));
    applyFiles(data.files);
    setNotice(data.notice || "Uploaded.");
    form.reset();
  } catch (err){ setNotice("Upload failed: " + err.message); }
  finally { btn.disabled = false; btn.textContent = label; }
});

async function deleteFile(name){
  if (!confirm("Delete " + name + "?")) return;
  try {
    const fd = new FormData();
    fd.set("action", "delete"); fd.set("file", name);
    const data = await postAjax(fd);
    applyFiles(data.files);
    setNotice(data.notice || ("Deleted " + name));
  } catch (err){ setNotice("Delete failed: " + err.message); }
}
document.getElementById("fileRows").addEventListener("click", e => {
  const b = e.target.closest("button.del");
  if (b) deleteFile(b.dataset.name);
});

// --- Editor event delegation (one listener per container; survives re-renders, and
//     avoids interpolating playlist ids into inline handlers — see fileRows pattern) ---
const plHost = document.getElementById("playlists");
plHost.addEventListener("click", e => {
  const card = e.target.closest(".pl"); if (!card) return;
  const pk = card.dataset.pk;
  if (e.target.closest(".add-item")) return addItem(pk);
  if (e.target.closest(".rm-pl")) return removePlaylist(pk);
  const row = e.target.closest("tr[data-i]"); if (!row) return;
  const i = +row.dataset.i;
  const mv = e.target.closest(".mv");
  if (mv) return moveItem(pk, i, +mv.dataset.dir);
  if (e.target.closest(".rm")) return removeItem(pk, i);
});
plHost.addEventListener("change", e => {
  const card = e.target.closest(".pl"); if (!card) return;
  const pk = card.dataset.pk;
  if (e.target.classList.contains("pl-label")) return setLabel(pk, e.target.value);
  const row = e.target.closest("tr[data-i]"); if (!row) return;
  const i = +row.dataset.i, field = e.target.dataset.field;
  if (field === "file") return setItemFile(pk, i, e.target.value);
  if (field) return setItem(pk, i, field, e.target.value);
});

const ruleHost = document.getElementById("rules");
ruleHost.addEventListener("click", e => {
  const rule = e.target.closest(".rule"); if (!rule) return;
  const i = +rule.dataset.i;
  const mv = e.target.closest(".mv");
  if (mv) return moveRule(i, +mv.dataset.dir);
  if (e.target.closest(".rm")) return removeRule(i);
});
ruleHost.addEventListener("change", e => {
  const rule = e.target.closest(".rule"); if (!rule) return;
  const i = +rule.dataset.i;
  if (e.target.classList.contains("day")) return toggleDay(i, +e.target.dataset.d, e.target.checked);
  if (e.target.dataset.field) return setRule(i, e.target.dataset.field, e.target.value);
});

// --- Settings + submit ---
document.getElementById("schedDefault").addEventListener("change", e => { DATA.schedule.default = e.target.value; });

const setT = document.getElementById("setTransition"), setP = document.getElementById("setPoll");
setT.value = DATA.settings.transition_ms ?? 800;
setP.value = DATA.settings.poll_seconds ?? 60;
setT.addEventListener("change", e => { DATA.settings.transition_ms = parseInt(e.target.value, 10) || 0; });
setP.addEventListener("change", e => { DATA.settings.poll_seconds = parseInt(e.target.value, 10) || 60; });

const editorForm = document.getElementById("editorForm");
editorForm.addEventListener("input", markDirty);
editorForm.addEventListener("change", markDirty);
editorForm.addEventListener("submit", () => {
  // Tidy the data: drop empty start/end, force videos to duration 0
  for (const pk in DATA.playlists){
    (DATA.playlists[pk].items || []).forEach(it => {
      if (!it.start) delete it.start;
      if (!it.end) delete it.end;
      if (it.type === "video") it.duration = 0;
    });
  }
  document.getElementById("playlist_json").value = JSON.stringify(DATA, null, 2);
  dirty = false;   // we're saving, so it's safe to navigate
});
// Saving via the raw-JSON form also clears the unsaved-changes guard.
document.querySelectorAll("details.adv form").forEach(f => f.addEventListener("submit", () => { dirty = false; }));

// Modern expectation: don't silently lose in-progress edits on refresh/close/back.
window.addEventListener("beforeunload", e => { if (dirty){ e.preventDefault(); e.returnValue = ""; } });

renderAll();
</script>
</body>
</html>
