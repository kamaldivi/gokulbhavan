<?php
/**
 * Admin API — sloka CRUD
 *
 * GET    /api/admin/slokas.php?category=GURU   — list slokas in category
 * GET    /api/admin/slokas.php?id=42            — single sloka (full detail)
 * GET    /api/admin/slokas.php?search=vande     — search across all slokas
 *
 * POST   /api/admin/slokas.php
 *   Create a sloka. Body:
 *   {
 *     "category_code":  "GURU",
 *     "slokamrtam_ref": "1.2",       // optional
 *     "title":          "...",        // optional
 *     "search_title":   "...",        // optional — auto-derived if omitted
 *     "sloka_text":     "...",        // required
 *     "scripture_id":   null,         // optional
 *     "scripture_ref":  "SB 11.3.21", // optional
 *     "word_by_word":   "...",        // optional
 *     "translation":    "...",        // optional
 *     "audio_file_path": null         // optional
 *   }
 *
 * PUT    /api/admin/slokas.php
 *   Update a sloka. Body: same as POST plus "id" (required).
 *
 * DELETE /api/admin/slokas.php
 *   Body: { "id": 42 }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Strip diacritics from transliterated Sanskrit for search_title.
 * Mirrors the logic in import_slokas.py.
 */
function makeSearchTitle(string $slokaText): string {
    // Extract first non-empty line
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $slokaText));
    $first = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') { $first = $line; break; }
    }
    if ($first === '') return '';

    // NFD normalise, strip combining marks, keep ASCII printable only
    $nfd = \Normalizer::normalize($first, \Normalizer::FORM_D);
    $out = '';
    $len = mb_strlen($nfd, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $c   = mb_substr($nfd, $i, 1, 'UTF-8');
        $ord = mb_ord($c, 'UTF-8');
        // Keep ASCII printable (32-126); skip combining diacritical marks (0x0300-0x036F)
        if ($ord >= 32 && $ord <= 126) {
            $out .= $c;
        }
    }

    // Replace hyphens, em-dashes, and punctuation with spaces so searches
    // like "man manabhava" match stored values like "man-manabhava"
    $out = str_replace(['—', '–', '-', ':', ';', ',', '.', '!', '?', '(', ')', '[', ']'], ' ', $out);
    $out = strtolower(preg_replace('/\s+/', ' ', $out));
    return trim($out);
}

function nullIfEmpty(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id          = isset($_GET['id'])          && $_GET['id']          !== '' ? (int) $_GET['id']          : null;
    $scriptureId = isset($_GET['scripture_id']) && $_GET['scripture_id'] !== '' ? (int) $_GET['scripture_id'] : null;
    $category    = trim($_GET['category'] ?? '');
    $search      = trim($_GET['search']   ?? '');

    $select = "
        SELECT s.id, s.category_code, sc.category_name, s.slokamrtam_ref,
               s.title, s.search_title, s.sloka_text,
               s.scripture_id, s.scripture_ref,
               scr.name AS scripture_name, scr.short_title AS scripture_short,
               s.word_by_word, s.translation, s.commentary, s.audio_file_path,
               s.updated_at
        FROM sloka s
        JOIN sloka_category sc  ON sc.category_code = s.category_code
        LEFT JOIN scripture scr ON scr.id = s.scripture_id
    ";

    try {
        $pdo = get_db();

        if ($id !== null) {
            $stmt = $pdo->prepare($select . ' WHERE s.id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) { http_response_code(404); echo json_encode(['message' => 'Not found']); exit; }
            $row['id'] = (int) $row['id'];
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $where  = [];
        $params = [];
        if ($category !== '') {
            $where[] = 's.category_code = :cat';
            $params[':cat'] = $category;
        }
        if ($scriptureId !== null) {
            $where[] = 's.scripture_id = :scr_id';
            $params[':scr_id'] = $scriptureId;
        }
        if ($search !== '') {
            $where[] = '(s.search_title LIKE :s1 OR s.title LIKE :s2 OR s.sloka_text LIKE :s3)';
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
        }

        $wc  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = $select . $wc . ' ORDER BY s.category_code ASC, s.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/slokas GET error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $catCode   = strtoupper(trim($body['category_code'] ?? ''));
    $slokaText = trim($body['sloka_text'] ?? '');

    if ($catCode === '' || $slokaText === '') {
        http_response_code(400);
        echo json_encode(['message' => 'category_code and sloka_text are required']);
        exit;
    }

    // Derive search_title if not supplied
    $searchTitle = nullIfEmpty($body['search_title'] ?? null)
                   ?? makeSearchTitle($slokaText);

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO sloka
              (category_code, slokamrtam_ref, title, search_title, sloka_text,
               scripture_id, scripture_ref, word_by_word, translation, commentary, audio_file_path)
            VALUES
              (:cat, :ref, :title, :stitle, :text,
               :scr_id, :scr_ref, :wbw, :trans, :commentary, :audio)
        ");
        $stmt->execute([
            ':cat'    => $catCode,
            ':ref'    => nullIfEmpty($body['slokamrtam_ref'] ?? null),
            ':title'  => nullIfEmpty($body['title']          ?? null),
            ':stitle' => $searchTitle,
            ':text'   => $slokaText,
            ':scr_id' => isset($body['scripture_id']) && $body['scripture_id'] !== null
                         ? (int) $body['scripture_id'] : null,
            ':scr_ref'=> nullIfEmpty($body['scripture_ref']  ?? null),
            ':wbw'        => nullIfEmpty($body['word_by_word']   ?? null),
            ':trans'      => nullIfEmpty($body['translation']    ?? null),
            ':commentary' => nullIfEmpty($body['commentary']     ?? null),
            ':audio'      => nullIfEmpty($body['audio_file_path'] ?? null),
        ]);
        $newId = (int) $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $newId]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/slokas POST error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── PUT ───────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id        = isset($body['id']) ? (int) $body['id'] : 0;
    $catCode   = strtoupper(trim($body['category_code'] ?? ''));
    $slokaText = trim($body['sloka_text'] ?? '');

    if ($id <= 0 || $catCode === '' || $slokaText === '') {
        http_response_code(400);
        echo json_encode(['message' => 'id, category_code and sloka_text are required']);
        exit;
    }

    // Re-derive search_title if not explicitly supplied
    $searchTitle = nullIfEmpty($body['search_title'] ?? null)
                   ?? makeSearchTitle($slokaText);

    try {
        $pdo  = get_db();

        // Verify existence first — rowCount() on UPDATE returns 0 when data is
        // unchanged (not only when the row is missing), causing false 404s.
        $chk = $pdo->prepare("SELECT COUNT(*) FROM sloka WHERE id = :id");
        $chk->execute([':id' => $id]);
        if ((int) $chk->fetchColumn() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Sloka not found']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE sloka SET
              category_code   = :cat,
              slokamrtam_ref  = :ref,
              title           = :title,
              search_title    = :stitle,
              sloka_text      = :text,
              scripture_id    = :scr_id,
              scripture_ref   = :scr_ref,
              word_by_word    = :wbw,
              translation     = :trans,
              commentary      = :commentary,
              audio_file_path = :audio
            WHERE id = :id
        ");
        $stmt->execute([
            ':cat'    => $catCode,
            ':ref'    => nullIfEmpty($body['slokamrtam_ref'] ?? null),
            ':title'  => nullIfEmpty($body['title']          ?? null),
            ':stitle' => $searchTitle,
            ':text'   => $slokaText,
            ':scr_id' => isset($body['scripture_id']) && $body['scripture_id'] !== null
                         ? (int) $body['scripture_id'] : null,
            ':scr_ref'=> nullIfEmpty($body['scripture_ref']  ?? null),
            ':wbw'        => nullIfEmpty($body['word_by_word']   ?? null),
            ':trans'      => nullIfEmpty($body['translation']    ?? null),
            ':commentary' => nullIfEmpty($body['commentary']     ?? null),
            ':audio'      => nullIfEmpty($body['audio_file_path'] ?? null),
            ':id'         => $id,
        ]);
        echo json_encode(['id' => $id, 'updated' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/slokas PUT error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['message' => 'id is required']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("DELETE FROM sloka WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Sloka not found']);
        } else {
            echo json_encode(['deleted' => $id]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/slokas DELETE error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
