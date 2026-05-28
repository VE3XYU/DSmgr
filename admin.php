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
      // pretty-print so it stays human-editable
      file_put_contents($PLAYLIST, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      $notice = "Playlist saved.";
    }
  }
}

// --- Gather current state ---
$files = [];
foreach (scandir($UPLOADS) as $f) {
  if ($f === '.' || $f === '..') continue;
  $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
  if (!in_array($ext, array_merge($IMAGE_EXT, $VIDEO_EXT))) continue;
  $files[] = ['name'=>$f, 'type'=>in_array($ext,$VIDEO_EXT)?'video':'image',
              'size'=>filesize("$UPLOADS/$f")];
}
$playlist_raw = file_exists($PLAYLIST)
  ? json_encode(read_playlist($PLAYLIST), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
  : "{}";

function human($bytes){ $u=['B','KB','MB','GB']; $i=0; while($bytes>=1024 && $i<3){$bytes/=1024;$i++;} return round($bytes,1).' '.$u[$i]; }
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
  .notice { background:#eef6ff; border:1px solid #b6d4f5; padding:.6rem .8rem; border-radius:6px; }
  table { border-collapse: collapse; width:100%; }
  td, th { text-align:left; padding:.4rem .6rem; border-bottom:1px solid #eee; font-size:.9rem; }
  textarea { width:100%; height:340px; font-family:monospace; font-size:.85rem; }
  button { cursor:pointer; padding:.35rem .7rem; }
  .tag { font-size:.7rem; padding:.1rem .4rem; border-radius:4px; background:#eee; }
  .tag.video { background:#ffe6cc; } .tag.image { background:#e6f0ff; }
  code { background:#f4f4f4; padding:.1rem .3rem; border-radius:3px; }
</style>
</head>
<body>
  <h1>Digital Signage Manager — Dashboard</h1>
  <?php if ($notice): ?><p class="notice"><?= htmlspecialchars($notice) ?></p><?php endif; ?>

  <h2>Upload media</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="media[]" multiple accept="image/*,video/*" required>
    <button type="submit">Upload</button>
  </form>
  <p style="font-size:.8rem;color:#666">Images: jpg, png, gif, webp · Video: mp4, webm. Export images at 1920×1080.</p>

  <h2>Current files</h2>
  <table>
    <tr><th>File</th><th>Type</th><th>Size</th><th></th></tr>
    <?php foreach ($files as $f): ?>
    <tr>
      <td><code><?= htmlspecialchars($f['name']) ?></code></td>
      <td><span class="tag <?= $f['type'] ?>"><?= $f['type'] ?></span></td>
      <td><?= human($f['size']) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Delete <?= htmlspecialchars($f['name']) ?>?');" style="margin:0">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="file" value="<?= htmlspecialchars($f['name']) ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$files): ?><tr><td colspan="4" style="color:#999">No files yet.</td></tr><?php endif; ?>
  </table>

  <h2>Playlists &amp; schedule</h2>
  <p style="font-size:.85rem;color:#555">
    Edit the JSON below. <code>playlists</code> = named lists of items (each with <code>file</code>,
    <code>type</code>, and <code>duration</code> in seconds — videos use <code>0</code>, they play to the end).
    <code>schedule</code> maps weekday → playlist name (<code>0</code>=Sunday … <code>6</code>=Saturday).
    The screen updates within a minute of saving.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="save_playlist">
    <textarea name="playlist_json"><?= htmlspecialchars($playlist_raw) ?></textarea>
    <p><button type="submit">Save playlist</button></p>
  </form>
</body>
</html>
