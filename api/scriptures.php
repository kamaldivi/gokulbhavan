<?php
/**
 * GET /api/scriptures.php
 *
 * Returns scriptures that have at least one sloka.
 * Used to populate the Scripture dropdown on the public slokas page.
 *
 * Response shape (array of):
 * {
 *   "id":          1,
 *   "name":        "Bhagavad-gītā As It Is",
 *   "short_title": "BG",
 *   "sloka_count": 42
 * }
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $pdo  = get_db();
    $stmt = $pdo->query("
        SELECT scr.id, scr.name, scr.short_title,
               COUNT(s.id) AS sloka_count
        FROM scripture scr
        JOIN sloka s ON s.scripture_id = scr.id
        GROUP BY scr.id, scr.name, scr.short_title
        ORDER BY scr.sort_order ASC, scr.name ASC
    ");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']          = (int) $r['id'];
        $r['sloka_count'] = (int) $r['sloka_count'];
    }
    unset($r);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('scriptures.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
