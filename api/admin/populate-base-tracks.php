<?php
/**
 * GET /api/admin/populate-base-tracks.php
 *
 * One-time utility: populates base_track_path in audio_track for all
 * bhajan rows where it is currently NULL, by scanning the filesystem
 * at media/audio/base/ for a matching {track_id}-*.mp3 file.
 *
 * Dry-run by default. Pass ?apply=1 to write to DB.
 *
 * Response:
 * {
 *   "mode": "dry-run" | "apply",
 *   "summary": { "found": N, "not_found": N, "already_set": N, "updated": N },
 *   "found":     [{ track_id, path }],
 *   "not_found": [track_id, ...],
 *   "already_set": [{ track_id, path }]
 * }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$apply   = ($_GET['apply'] ?? '') === '1';
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$baseDir = 'media/audio/base';

try {
    $pdo = get_db();

    // All bhajan tracks — both null and already-set, for full visibility
    $rows = $pdo->query("
        SELECT t.track_id, t.base_track_path
        FROM audio_track t
        JOIN audio_category c ON c.category_code = t.category_code
        WHERE c.audio_family = 'bhajan'
        ORDER BY t.track_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

$found      = [];   // null in DB, file found on disk
$notFound   = [];   // null in DB, no file on disk
$alreadySet = [];   // already has a value in DB

foreach ($rows as $r) {
    $trackId = $r['track_id'];

    if (!empty($r['base_track_path'])) {
        $alreadySet[] = ['track_id' => $trackId, 'path' => $r['base_track_path']];
        continue;
    }

    // Scan for {track_id}-*.mp3 in media/audio/base/
    $pattern = $docRoot . '/' . $baseDir . '/' . $trackId . '-*.mp3';
    $matches = glob($pattern);

    if ($matches) {
        $found[] = [
            'track_id' => $trackId,
            'path'     => $baseDir . '/' . basename($matches[0]),
        ];
    } else {
        $notFound[] = $trackId;
    }
}

$updated = 0;

if ($apply && !empty($found)) {
    try {
        $stmt = $pdo->prepare("
            UPDATE audio_track SET base_track_path = :path WHERE track_id = :id
        ");
        foreach ($found as $item) {
            $stmt->execute([':path' => $item['path'], ':id' => $item['track_id']]);
            $updated++;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB update error: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode([
    'mode'    => $apply ? 'apply' : 'dry-run',
    'summary' => [
        'already_set' => count($alreadySet),
        'found'       => count($found),
        'not_found'   => count($notFound),
        'updated'     => $updated,
    ],
    'found'       => $found,
    'not_found'   => $notFound,
    'already_set' => $alreadySet,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
