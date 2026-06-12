<?php
/**
 * GET /api/audio.php
 *
 * Returns grouped audio tracks — one entry per unique track_id —
 * with singer versions, base track, and related videos attached.
 *
 * Query parameters:
 *   type=B              — B=Bhajan (default), S=Sloka, N=Sankirtan, A=Album
 *   category=A          — category_code filter
 *   search=mangala      — title search (LIKE %...%)
 *   limit=50            — applied to primary rows (default: all)
 *   offset=0            — pagination offset
 *
 * Response shape (array of) for B/S/N:
 * {
 *   "track_id":         "A-03",
 *   "track_name":       "Guru Carana Kamala",
 *   "category_code":    "A",
 *   "display_name":     "Guru Vaishnavas",
 *   "audio_file_path":  "media/audio-bhajans/A/A-03-Guru Carana Kamala.mp3",
 *   "lyrics_file_path": "media/bhajan-lyrics/A/A-03.txt",
 *   "base_track_path": "media/audio/base/A-03-Guru Carana Kamala - Base.mp3",
 *   "download_allowed": 1,
 *   "singers": [{ "singer": "Aishwarya", "audio_file_path": "..." }],
 *   "videos":  [{ "video_id": "dQw4...", "title": "..." }]
 * }
 *
 * Response shape (array of) for A (Albums) — ordered by track_num:
 * {
 *   "track_id":         "GG1-01",
 *   "track_name":       "Guru Vandana",
 *   "category_code":    "GG1",
 *   "display_name":     "Gokula Ganam Vol. 1",
 *   "audio_file_path":  "media/gokula-ganam/vol1/ready/01-...mp3",
 *   "track_num":        1,
 *   "singer":           "Priya",
 *   "bhajan_id":        "X-01",
 *   "download_allowed": 1
 * }
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$type     = strtoupper(trim($_GET['type']     ?? 'B'));
$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['search']   ?? '');
$limit    = isset($_GET['limit'])  ? max(1, (int) $_GET['limit'])  : null;
$offset   = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

if (!in_array($type, ['B', 'S', 'N', 'A'], true)) {
    http_response_code(400);
    echo json_encode(['message' => 'type must be B, S, N, or A']);
    exit;
}

// Map type codes to audio_family enum values
$familyMap = ['B' => 'bhajan', 'S' => 'sloka', 'N' => 'sankirtan', 'A' => 'album'];
$family    = $familyMap[$type];

try {
    $pdo    = get_db();
    $params = [':family' => $family];

    $where = ['c.audio_family = :family'];

    if ($category !== '') {
        $where[]             = 'c.category_code = :category';
        $params[':category'] = $category;
    }
    if ($search !== '') {
        $where[]           = '(t.track_name LIKE :search OR t.track_id LIKE :search OR t.author LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // ── 1. Primary rows ───────────────────────────────────────
    if ($type === 'A') {
        // Albums: include track_num, singer, lyrics_file_path; order by track position
        $sql = "
            SELECT
                t.track_id,
                t.track_name,
                t.author,
                c.category_code,
                c.category_name             AS display_name,
                t.audio_file_path,
                t.lyrics_file_path,
                t.track_num,
                t.singer,
                1                           AS download_allowed
            FROM audio_track t
            JOIN audio_category c ON c.category_code = t.category_code
            $whereClause
            ORDER BY t.track_num ASC
        ";
    } else {
        $sql = "
            SELECT
                t.track_id,
                t.track_name,
                t.author,
                c.category_code,
                c.category_name             AS display_name,
                t.audio_file_path,
                t.lyrics_file_path,
                t.base_track_path,
                1                           AS download_allowed
            FROM audio_track t
            JOIN audio_category c ON c.category_code = t.category_code
            $whereClause
            ORDER BY c.category_code ASC, t.track_id ASC
        ";
    }

    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    if ($limit !== null) {
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        echo json_encode([]);
        exit;
    }

    // Build index and collect all track_ids
    $index    = [];
    $trackIds = [];
    foreach ($rows as $i => &$r) {
        $r['download_allowed'] = 1;

        if ($type === 'A') {
            // Derive bhajan_id from filename (e.g. 01-A-03-Title.mp3 → A-03)
            $basename  = basename($r['audio_file_path'], '.mp3');
            $bhajan_id = null;
            if (preg_match('/^\d{2}-([A-Z][A-Z0-9]*)-(\d{2})-/', $basename, $m)) {
                $bhajan_id = "{$m[1]}-{$m[2]}";
            }
            $r['bhajan_id'] = $bhajan_id;
        } else {
            $r['singers'] = [];
            $r['videos']  = [];
        }

        $index[$r['track_id']] = $i;
        $trackIds[]            = $r['track_id'];
    }
    unset($r);

    $placeholders = implode(',', array_fill(0, count($trackIds), '?'));

    // ── 2. Singer versions (bhajans only) ────────────────────
    if ($type === 'B') {
        $singerSql = "
            SELECT track_id, singer, audio_file_path
            FROM audio_singer_version
            WHERE track_id IN ($placeholders)
            ORDER BY singer ASC
        ";
        $sStmt = $pdo->prepare($singerSql);
        $sStmt->execute($trackIds);
        foreach ($sStmt->fetchAll() as $s) {
            if (isset($index[$s['track_id']])) {
                $rows[$index[$s['track_id']]]['singers'][] = [
                    'singer'          => $s['singer'],
                    'audio_file_path' => $s['audio_file_path'],
                ];
            }
        }
    }

    // ── 3. Related videos (bhajans and slokas only) ───────────
    if ($type !== 'N' && $type !== 'A') {
        $videoSql = "
            SELECT track_id, video_id, video_title AS title
            FROM video
            WHERE track_id IN ($placeholders)
            ORDER BY published_date DESC
        ";
        $vStmt = $pdo->prepare($videoSql);
        $vStmt->execute($trackIds);
        foreach ($vStmt->fetchAll() as $v) {
            if (isset($index[$v['track_id']])) {
                $rows[$index[$v['track_id']]]['videos'][] = [
                    'video_id' => $v['video_id'],
                    'title'    => $v['title'],
                ];
            }
        }
    }

    echo json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('audio.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
