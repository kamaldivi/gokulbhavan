<?php
/**
 * Admin CRUD for the lyrics table.
 *
 * GET    /api/admin/lyrics.php?track_id=A-01[&lang=en]
 *   Returns all rows for the track (optionally filtered by lang).
 *   Response: { track_id, rows: [{ lang, lyrics, meaning }] }
 *
 * GET    /api/admin/lyrics.php?list=1[&type=B][&search=xyz][&limit=50][&offset=0]
 *   Returns paginated track list with has_lyrics flag.
 *   Response: [ { track_id, track_name, audio_family, has_lyrics } ]
 *
 * PUT    /api/admin/lyrics.php
 *   Upsert one content_type for a track/lang.
 *   Body: { track_id, lang, content_type, body }
 *   Response: { ok: true }
 *
 * DELETE /api/admin/lyrics.php
 *   Delete all lyrics rows for a track + lang.
 *   Body: { track_id, lang }
 *   Response: { deleted: N }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = get_db();

    // ── GET — list tracks or fetch single track rows ─────────────
    if ($method === 'GET') {

        // list=1: paginated track list with has_lyrics indicator
        if (!empty($_GET['list'])) {
            $type   = strtoupper(trim($_GET['type'] ?? ''));
            $search = trim($_GET['search'] ?? '');
            $limit  = isset($_GET['limit'])  ? max(1, (int) $_GET['limit'])  : 50;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

            $where  = [];
            $params = [];

            $familyMap = ['B' => 'bhajan', 'S' => 'sloka', 'N' => 'sankirtan', 'A' => 'album'];
            if (isset($familyMap[$type])) {
                $where[]           = 'c.audio_family = :family';
                $params[':family'] = $familyMap[$type];
            }
            if ($search !== '') {
                $where[]                = '(t.track_name LIKE :search_name OR t.track_id LIKE :search_id)';
                $params[':search_name'] = '%' . $search . '%';
                $params[':search_id']   = '%' . $search . '%';
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    t.track_id,
                    t.track_name,
                    c.audio_family,
                    CASE WHEN l.track_id IS NOT NULL THEN 1 ELSE 0 END AS has_lyrics
                FROM audio_track t
                JOIN audio_category c ON c.category_code = t.category_code
                LEFT JOIN (
                    SELECT DISTINCT track_id FROM lyrics
                ) l ON l.track_id = t.track_id
                $whereClause
                ORDER BY c.audio_family ASC, t.track_id ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) $r['has_lyrics'] = (int) $r['has_lyrics'];
            unset($r);

            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // track_id: return all lang rows for a single track
        $trackId = trim($_GET['track_id'] ?? '');
        if ($trackId === '') {
            http_response_code(400);
            echo json_encode(['message' => 'track_id is required']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $trackId)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid track_id']);
            exit;
        }

        $langFilter = trim($_GET['lang'] ?? '');
        $langWhere  = '';
        $langParams = [':track_id' => $trackId];
        if ($langFilter !== '') {
            $langWhere  = ' AND lang = :lang';
            $langParams[':lang'] = $langFilter;
        }

        $stmt = $pdo->prepare("
            SELECT lang, content_type, body, updated_at
            FROM lyrics
            WHERE track_id = :track_id $langWhere
            ORDER BY lang ASC, content_type ASC
        ");
        $stmt->execute($langParams);
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pivot into { lang: { lyrics, meaning, updated_at } }
        $byLang = [];
        foreach ($dbRows as $row) {
            $l = $row['lang'];
            if (!isset($byLang[$l])) {
                $byLang[$l] = ['lang' => $l, 'lyrics' => null, 'meaning' => null, 'updated_at' => null];
            }
            $byLang[$l][$row['content_type']] = $row['body'];
            // updated_at: take the most recent
            if ($byLang[$l]['updated_at'] === null || $row['updated_at'] > $byLang[$l]['updated_at']) {
                $byLang[$l]['updated_at'] = $row['updated_at'];
            }
        }

        echo json_encode([
            'track_id' => $trackId,
            'rows'     => array_values($byLang),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── PUT — upsert one content_type row ────────────────────────
    if ($method === 'PUT') {
        $trackId     = trim($body['track_id']     ?? '');
        $lang        = trim($body['lang']         ?? 'en');
        $contentType = trim($body['content_type'] ?? '');
        $text        = $body['body']              ?? '';

        if ($trackId === '' || !in_array($contentType, ['lyrics', 'meaning'], true)) {
            http_response_code(400);
            echo json_encode(['message' => 'track_id and content_type (lyrics|meaning) are required']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $trackId)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid track_id']);
            exit;
        }
        if (!preg_match('/^[a-z]{2,10}$/', $lang)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid lang']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO lyrics (track_id, lang, content_type, body)
            VALUES (:track_id, :lang, :content_type, :body)
            ON DUPLICATE KEY UPDATE body = VALUES(body), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':track_id'     => $trackId,
            ':lang'         => $lang,
            ':content_type' => $contentType,
            ':body'         => $text,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ── DELETE — remove all rows for a track + lang ──────────────
    if ($method === 'DELETE') {
        $trackId = trim($body['track_id'] ?? '');
        $lang    = trim($body['lang']     ?? '');

        if ($trackId === '' || $lang === '') {
            http_response_code(400);
            echo json_encode(['message' => 'track_id and lang are required']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $trackId)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid track_id']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM lyrics WHERE track_id = :track_id AND lang = :lang");
        $stmt->execute([':track_id' => $trackId, ':lang' => $lang]);

        echo json_encode(['deleted' => $stmt->rowCount()]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('admin/lyrics.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
