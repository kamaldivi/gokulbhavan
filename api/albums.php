<?php
/**
 * GET /api/albums.php
 *
 * Without query params — returns the list of all albums:
 * [{ "vol": 1, "title": "Gokula Ganam Vol. 1",
 *    "cover_path": "media/gokula-ganam/vol1/images/CD1.jpg",
 *    "track_count": 18 }, ...]
 *
 * With ?vol=N — returns the ordered track list for that volume:
 * [{ "track_num": 1, "title": "Guru Vandana",
 *    "bhajan_id": "X-01", "singer": "Priya",
 *    "audio_path": "media/gokula-ganam/vol1/ready/01-X-01-Guru Vandana.mp3" }, ...]
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = get_db();

    // ── Route: album list ─────────────────────────────────────
    if (!isset($_GET['vol'])) {
        $stmt = $pdo->query("
            SELECT
                CAST(SUBSTRING(c.category_code, 3) AS UNSIGNED) AS vol,
                c.category_name                                   AS title,
                c.image_path                                      AS cover_path,
                COUNT(t.track_id)                                 AS track_count
            FROM audio_category c
            LEFT JOIN audio_track t ON t.category_code = c.category_code
            WHERE c.audio_family = 'album'
            GROUP BY c.category_code, c.category_name, c.image_path, c.sort_order
            ORDER BY c.sort_order ASC
        ");
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['vol']         = (int) $r['vol'];
            $r['track_count'] = (int) $r['track_count'];
            // Return null for missing cover images (keeps response shape consistent)
            if (empty($r['cover_path'])) {
                $r['cover_path'] = null;
            }
        }

        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Route: tracks for a volume ────────────────────────────
    $vol = max(1, (int) $_GET['vol']);
    $categoryCode = 'GG' . $vol;

    // Verify this album category exists
    $check = $pdo->prepare("SELECT category_code FROM audio_category WHERE category_code = ? AND audio_family = 'album'");
    $check->execute([$categoryCode]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => 'Album not found']);
        exit;
    }

    // track_id format: GG{vol}-{track_num} — extract bhajan_id from audio_file_path filename
    // Filename format: {02d}-{CAT}-{02d}-{Title}.mp3 or {02d}-{Title}.mp3
    $stmt = $pdo->prepare("
        SELECT
            t.track_num,
            t.track_name    AS title,
            t.singer,
            t.audio_file_path AS audio_path
        FROM audio_track t
        WHERE t.category_code = :cat
        ORDER BY t.track_num ASC
    ");
    $stmt->execute([':cat' => $categoryCode]);
    $rows = $stmt->fetchAll();

    // Derive bhajan_id from filename for each track (e.g. 01-A-03-Title.mp3 → A-03)
    foreach ($rows as &$r) {
        $r['track_num'] = (int) $r['track_num'];
        $basename = basename($r['audio_path'], '.mp3');
        $bhajan_id = null;
        if (preg_match('/^\d{2}-([A-Z][A-Z0-9]*)-(\d{2})-/', $basename, $m)) {
            $bhajan_id = "{$m[1]}-{$m[2]}";
        }
        $r['bhajan_id'] = $bhajan_id;
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('albums.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
