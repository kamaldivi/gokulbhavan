<?php
/**
 * GET  /api/admin/reload-albums.php
 *   Renders an HTML preview table of all album tracks parsed from
 *   /media/albums/GG1-GG10 folders, with parsed track_id, title,
 *   singer, and lyrics_source_track_id.
 *   Includes a "Run Reload" button.
 *
 * POST /api/admin/reload-albums.php
 *   1. Deletes all audio_track rows whose category_code is in GG1-GG10.
 *   2. Inserts fresh rows parsed from the filesystem.
 *   Returns JSON: { deleted, inserted, errors[] }
 *
 * Protected by _auth.php (admin session required).
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method  = $_SERVER['REQUEST_METHOD'];
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// ── Parse all album files from filesystem ────────────────────────────────────

/**
 * Scan /media/albums/GG1-GG10 and parse each filename into a track record.
 *
 * Filename pattern: {nn}-{CAT}-{num}-{title} - {singer}.mp3
 * Examples:
 *   01-A-01-Guru Vandana.mp3              → no singer
 *   02-G-03-Krishna Rap Song - Vivek.mp3  → singer = Vivek
 *
 * X-00 is a placeholder for tracks with no source bhajan in the DB.
 * These get lyrics_source_track_id = NULL.
 */
function parseAlbumTracks(string $docRoot): array {
    $tracks = [];
    $errors = [];

    for ($vol = 1; $vol <= 10; $vol++) {
        $albumCode = 'GG' . $vol;
        $dir = $docRoot . '/media/albums/' . $albumCode;

        if (!is_dir($dir)) {
            $errors[] = "Directory not found: media/albums/$albumCode";
            continue;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.mp3')) continue;

            // Pattern: {nn}-{CAT}-{num}-{rest}.mp3
            if (!preg_match('/^(\d+)-([A-Z]+)-(\d+)-(.+)\.mp3$/', $file, $m)) {
                $errors[] = "Could not parse filename: media/albums/$albumCode/$file";
                continue;
            }

            [, $trackNum, $bhajanCat, $bhajanNum, $rest] = $m;

            // Split on LAST " - " to separate title from singer
            $sepPos = strrpos($rest, ' - ');
            if ($sepPos !== false) {
                $title  = substr($rest, 0, $sepPos);
                $singer = substr($rest, $sepPos + 3);
            } else {
                $title  = $rest;
                $singer = null;
            }

            // X-00 is a placeholder — no source bhajan
            $sourceTrackId = ($bhajanCat === 'X') ? null : ($bhajanCat . '-' . $bhajanNum);

            $tracks[] = [
                'track_id'               => $albumCode . '-' . $trackNum,
                'category_code'          => $albumCode,
                'track_name'             => trim($title),
                'singer'                 => $singer !== null ? trim($singer) : null,
                'track_num'              => (int) $trackNum,
                'audio_file_path'        => "media/albums/$albumCode/$file",
                'lyrics_source_track_id' => $sourceTrackId,
            ];
        }
    }

    return ['tracks' => $tracks, 'errors' => $errors];
}

// ── POST: execute reload ──────────────────────────────────────────────────────

if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $parsed  = parseAlbumTracks($docRoot);
    $tracks  = $parsed['tracks'];
    $errors  = $parsed['errors'];

    try {
        $pdo = get_db();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Delete stale lyrics rows for album track_ids (GG1-01 format)
        $pdo->exec("DELETE FROM lyrics WHERE track_id REGEXP '^GG[0-9]+-'");

        // 2. Delete all existing album tracks (GG1-GG10)
        $deleted = $pdo->exec("
            DELETE FROM audio_track
            WHERE category_code IN (
                'GG1','GG2','GG3','GG4','GG5',
                'GG6','GG7','GG8','GG9','GG10'
            )
        ");

        // 2. Insert fresh rows
        $stmt = $pdo->prepare("
            INSERT INTO audio_track
                (track_id, category_code, track_name, singer, track_num,
                 audio_file_path, lyrics_source_track_id)
            VALUES
                (:track_id, :category_code, :track_name, :singer, :track_num,
                 :audio_file_path, :lyrics_source_track_id)
        ");

        $inserted = 0;
        foreach ($tracks as $t) {
            try {
                $stmt->execute([
                    ':track_id'               => $t['track_id'],
                    ':category_code'          => $t['category_code'],
                    ':track_name'             => $t['track_name'],
                    ':singer'                 => $t['singer'],
                    ':track_num'              => $t['track_num'],
                    ':audio_file_path'        => $t['audio_file_path'],
                    ':lyrics_source_track_id' => $t['lyrics_source_track_id'],
                ]);
                $inserted++;
            } catch (PDOException $e) {
                $errors[] = "Failed to insert {$t['track_id']}: " . $e->getMessage();
            }
        }

        echo json_encode([
            'deleted'  => $deleted,
            'inserted' => $inserted,
            'errors'   => $errors,
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('reload-albums.php PDO error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── GET: preview page ─────────────────────────────────────────────────────────

header('Content-Type: text/html; charset=utf-8');

$parsed = parseAlbumTracks($docRoot);
$tracks = $parsed['tracks'];
$errors = $parsed['errors'];

$totalTracks    = count($tracks);
$withSource     = count(array_filter($tracks, fn($t) => $t['lyrics_source_track_id'] !== null));
$withoutSource  = $totalTracks - $withSource;

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reload Album Tracks</title>
<style>
  body { font-family: sans-serif; font-size: 14px; padding: 24px; background: #0f172a; color: #e2e8f0; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .summary { background: #1e293b; border-radius: 8px; padding: 16px; margin: 16px 0; display: flex; gap: 32px; }
  .stat { text-align: center; }
  .stat-n { font-size: 28px; font-weight: bold; color: #38bdf8; }
  .stat-l { font-size: 12px; color: #94a3b8; margin-top: 4px; }
  .stat-n.warn { color: #fb923c; }
  .errors { background: #450a0a; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; color: #fca5a5; }
  .errors li { margin: 4px 0; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; padding: 8px 10px; background: #1e293b; color: #94a3b8; font-weight: 600; position: sticky; top: 0; }
  td { padding: 6px 10px; border-bottom: 1px solid #1e293b; }
  tr:hover td { background: #1e293b44; }
  .null { color: #64748b; font-style: italic; }
  .source { color: #86efac; font-family: monospace; }
  .badge { display: inline-block; padding: 1px 7px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
  .badge-ok { background: #14532d; color: #86efac; }
  .badge-null { background: #292524; color: #78716c; }
  .run-btn { background: #0ea5e9; color: white; border: none; padding: 10px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; margin: 16px 0; }
  .run-btn:hover { background: #0284c7; }
  #result { margin-top: 12px; padding: 12px 16px; border-radius: 6px; display: none; }
  .result-ok { background: #14532d; color: #86efac; }
  .result-err { background: #450a0a; color: #fca5a5; }
</style>
</head>
<body>
<h1>Reload Album Tracks</h1>
<p style="color:#94a3b8">Parsed from <code>media/albums/GG1–GG10</code>. Clicking Run will <strong>delete all existing GG1–GG10 rows</strong> and re-insert from filesystem.</p>

<div class="summary">
  <div class="stat"><div class="stat-n"><?= $totalTracks ?></div><div class="stat-l">Total tracks</div></div>
  <div class="stat"><div class="stat-n"><?= $withSource ?></div><div class="stat-l">With lyrics source</div></div>
  <div class="stat"><div class="stat-n <?= $withoutSource > 0 ? 'warn' : '' ?>"><?= $withoutSource ?></div><div class="stat-l">No lyrics source (X-00)</div></div>
</div>

<?php if ($errors): ?>
<div class="errors"><strong>Parse errors:</strong><ul>
<?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<button class="run-btn" onclick="runReload()">Run Reload (<?= $totalTracks ?> tracks)</button>
<div id="result"></div>

<table>
<thead>
  <tr>
    <th>Track ID</th>
    <th>Album</th>
    <th>#</th>
    <th>Title</th>
    <th>Singer</th>
    <th>Lyrics Source</th>
  </tr>
</thead>
<tbody>
<?php foreach ($tracks as $t): ?>
<tr>
  <td><code><?= htmlspecialchars($t['track_id']) ?></code></td>
  <td><?= htmlspecialchars($t['category_code']) ?></td>
  <td><?= $t['track_num'] ?></td>
  <td><?= htmlspecialchars($t['track_name']) ?></td>
  <td><?= $t['singer'] !== null ? htmlspecialchars($t['singer']) : '<span class="null">—</span>' ?></td>
  <td>
    <?php if ($t['lyrics_source_track_id']): ?>
      <span class="badge badge-ok source"><?= htmlspecialchars($t['lyrics_source_track_id']) ?></span>
    <?php else: ?>
      <span class="badge badge-null">none</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<script>
async function runReload() {
  const btn = document.querySelector('.run-btn');
  const res = document.getElementById('result');
  btn.disabled = true;
  btn.textContent = 'Running…';
  res.style.display = 'none';

  try {
    const r = await fetch(location.href, { method: 'POST' });
    const data = await r.json();
    const hasErrors = data.errors && data.errors.length > 0;
    res.className = 'result ' + (hasErrors ? 'result-err' : 'result-ok');
    res.style.display = 'block';
    res.innerHTML = `<strong>Done.</strong> Deleted: ${data.deleted}, Inserted: ${data.inserted}`
      + (hasErrors ? '<br><br><strong>Errors:</strong><ul>' + data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>' : '');
  } catch (e) {
    res.className = 'result result-err';
    res.style.display = 'block';
    res.textContent = 'Request failed: ' + e.message;
  }

  btn.disabled = false;
  btn.textContent = 'Run Reload (<?= $totalTracks ?> tracks)';
}
</script>
</body>
</html>
