<?php
/**
 * GET /api/slokas.php
 *
 * Returns sloka rows. Includes full content (sloka_text, word_by_word,
 * translation) in every response so the page can expand rows inline.
 *
 * Query parameters:
 *   id=42              — single sloka by id (also returns scripture name)
 *   category=GURU      — filter by category_code
 *   search=vande       — search in search_title and title (LIKE %…%)
 *   search_ref=BG 2   — search in scripture_ref (LIKE %…%)
 *   scripture_id=1     — filter by scripture FK (results ordered numerically by scripture_ref)
 *   limit=200          — max rows (default: all)
 *   offset=0
 *
 * Response shape (array of):
 * {
 *   "id":              3,
 *   "category_code":   "MANGAL",
 *   "category_name":   "Maṅgalācaraṇa",
 *   "slokamrtam_ref":  "0.3",
 *   "title":           "Śrī Guru Praṇāma",
 *   "search_title":    "ajnana-timirandhasya jnananjana-salakaya",
 *   "sloka_text":      "ajñāna-timirāndhasya…",
 *   "scripture_id":    null,
 *   "scripture_ref":   "Śrī Prema-bhakti-candrikā",
 *   "scripture_name":  null,
 *   "scripture_short": null,
 *   "word_by_word":    "ajñāna—of ignorance…",
 *   "translation":     "O Gurudeva…",
 *   "audio_file_path": null
 * }
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

/**
 * Normalize a search query for flexible matching.
 * Replaces hyphens, em-dashes, and common punctuation with spaces,
 * then converts each word into a LIKE wildcard token.
 * e.g. "man-manabhava" and "man manabhava" both become "%man%manabhava%"
 */
function normalizeSearchLike(string $q): string {
    $q = str_replace(['—', '–', '-', ':', ';', ',', '.', '!', '?', '(', ')', '[', ']'], ' ', $q);
    $q = preg_replace('/\s+/', ' ', trim($q));
    $tokens = array_filter(explode(' ', $q), fn($t) => $t !== '');
    return '%' . implode('%', $tokens) . '%';
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$id          = isset($_GET['id'])          ? (int) $_GET['id']           : null;
$category    = trim($_GET['category']    ?? '');
$search      = trim($_GET['search']      ?? '');
$searchRef   = trim($_GET['search_ref']  ?? '');
$scriptureId = isset($_GET['scripture_id']) ? (int) $_GET['scripture_id'] : null;
$limit       = isset($_GET['limit'])  ? max(1, (int) $_GET['limit'])  : null;
$offset      = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

try {
    $pdo = get_db();

    $select = "
        SELECT
            s.id,
            s.category_code,
            sc.category_name,
            s.slokamrtam_ref,
            s.title,
            s.search_title,
            s.sloka_text,
            s.scripture_id,
            s.scripture_ref,
            scr.name        AS scripture_name,
            scr.short_title AS scripture_short,
            s.word_by_word,
            s.translation,
            s.commentary,
            s.audio_file_path
        FROM sloka s
        JOIN sloka_category sc  ON sc.category_code = s.category_code
        LEFT JOIN scripture scr ON scr.id = s.scripture_id
    ";

    // ── Single by id ─────────────────────────────────────────────────
    if ($id !== null) {
        $stmt = $pdo->prepare($select . ' WHERE s.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['message' => 'Sloka not found']);
            exit;
        }
        $row['id'] = (int) $row['id'];
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── List with optional filters ────────────────────────────────────
    $where  = [];
    $params = [];

    if ($category !== '') {
        $where[]              = 's.category_code = :category';
        $params[':category']  = $category;
    }
    if ($search !== '') {
        $like                = normalizeSearchLike($search);
        $where[]             = '(s.search_title LIKE :search1 OR s.title LIKE :search2)';
        $params[':search1']  = $like;
        $params[':search2']  = $like;
    }
    if ($searchRef !== '') {
        $where[]               = 's.scripture_ref LIKE :search_ref';
        $params[':search_ref'] = normalizeSearchLike($searchRef);
    }
    if ($scriptureId !== null) {
        $where[]                = 's.scripture_id = :scripture_id';
        $params[':scripture_id'] = $scriptureId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // When filtering by scripture, sort numerically by reference (e.g. SB 1.36.1 → 1, 36, 1).
    // Plain string sort would mis-order canto/chapter 10+ before 2–9.
    // All other filters keep the default category + id order.
    if ($scriptureId !== null) {
        $numPart = "TRIM(SUBSTRING(s.scripture_ref, LOCATE(' ', s.scripture_ref) + 1))";
        $orderBy = "ORDER BY
            CAST(SUBSTRING_INDEX($numPart, '.', 1)                                    AS UNSIGNED),
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX($numPart, '.', 2), '.', -1)          AS UNSIGNED),
            CAST(SUBSTRING_INDEX($numPart, '.', -1)                                   AS UNSIGNED),
            s.id ASC";
    } else {
        $orderBy = 'ORDER BY s.category_code ASC, s.id ASC';
    }

    $sql = $select . $whereClause . ' ' . $orderBy;

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

    // Cast id to int
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('slokas.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
