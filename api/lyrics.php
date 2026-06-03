<?php
/**
 * GET /api/lyrics.php
 *
 * Returns lyrics and/or meaning for a single track.
 * All lyrics are stored in the DB (lyrics table).
 *
 * Query parameters:
 *   id=A-01   — track_id (required)
 *   type=B    — B=Bhajan (default), S=Sloka, N=Sankirtan, A=Album
 *   lang=en   — language code (default: en)
 *
 * Response shape:
 * {
 *   "id":      "A-01",
 *   "lang":    "en",
 *   "lyrics":  "vande 'ham sri-guroh…",   // null if not present
 *   "meaning": "I bow to the lotus feet…" // null if not present
 * }
 *
 * Special cases:
 *   - Sankirtan (type=N): falls back to sentinel track_id 'MAHAMANTRA'
 *     if no track-specific row exists (all sankirtans share Mahamantra lyrics).
 *   - Album (type=A): follows lyrics_source_track_id on audio_track to look
 *     up the source bhajan's lyrics instead of duplicating them.
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$id   = trim($_GET['id']   ?? '');
$type = strtoupper(trim($_GET['type'] ?? 'B'));
$lang = trim($_GET['lang'] ?? 'en');

if ($id === '') {
    http_response_code(400);
    echo json_encode(['message' => 'id is required']);
    exit;
}

if (!in_array($type, ['B', 'S', 'N', 'A'], true)) {
    http_response_code(400);
    echo json_encode(['message' => 'type must be B, S, N, or A']);
    exit;
}

// Sanitise inputs
if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $id)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid id format']);
    exit;
}
if (!preg_match('/^[a-z]{2,10}$/', $lang)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid lang format']);
    exit;
}

try {
    $pdo = get_db();

    // ── 1. Try DB rows (lyrics table) ────────────────────────────
    $dbStmt = $pdo->prepare("
        SELECT content_type, body
        FROM lyrics
        WHERE track_id = :id
          AND lang      = :lang
    ");
    $dbStmt->execute([':id' => $id, ':lang' => $lang]);
    $dbRows = $dbStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── 1a. Sankirtan sentinel fallback ──────────────────────────
    // Sankirtan tracks share identical Mahamantra lyrics stored once
    // under the sentinel track_id 'MAHAMANTRA' rather than per-track.
    if (empty($dbRows) && $type === 'N') {
        $sentinelStmt = $pdo->prepare("
            SELECT content_type, body
            FROM lyrics
            WHERE track_id = 'MAHAMANTRA'
              AND lang      = :lang
        ");
        $sentinelStmt->execute([':lang' => $lang]);
        $dbRows = $sentinelStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── 1b. Source-track redirect (album tracks → source bhajan lyrics) ──
    // Album tracks store lyrics_source_track_id pointing to the original
    // bhajan track. Look up that track's lyrics instead of duplicating them.
    if (empty($dbRows) && $type === 'A') {
        $srcStmt = $pdo->prepare("
            SELECT lyrics_source_track_id
            FROM audio_track
            WHERE track_id = :id
        ");
        $srcStmt->execute([':id' => $id]);
        $srcId = $srcStmt->fetchColumn() ?: null;
        if ($srcId) {
            $redirStmt = $pdo->prepare("
                SELECT content_type, body
                FROM lyrics
                WHERE track_id = :src_id
                  AND lang      = :lang
            ");
            $redirStmt->execute([':src_id' => $srcId, ':lang' => $lang]);
            $dbRows = $redirStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!empty($dbRows)) {
        $lyricsBody  = null;
        $meaningBody = null;
        foreach ($dbRows as $row) {
            if ($row['content_type'] === 'lyrics')  $lyricsBody  = $row['body'];
            if ($row['content_type'] === 'meaning') $meaningBody = $row['body'];
        }
        echo json_encode([
            'id'      => $id,
            'lang'    => $lang,
            'lyrics'  => $lyricsBody,
            'meaning' => $meaningBody,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['message' => 'Lyrics not found', 'id' => $id]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('lyrics.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
