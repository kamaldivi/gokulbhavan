<?php
/**
 * GET /api/announcements.php
 *
 * Public endpoint — returns active announcements whose date range
 * includes today (or have no date range set).
 *
 * Response shape (array of):
 * {
 *   "id":         1,
 *   "title":      "New program starting this Friday",
 *   "body":       "Join us for…",
 *   "url":        "https://…",
 *   "start_date": "2026-05-01",
 *   "end_date":   "2026-05-31"
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
    $rows = $pdo->query("
        SELECT id, title, body, url, start_date, end_date
        FROM announcement
        WHERE active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date   IS NULL OR end_date   >= CURDATE())
        ORDER BY id DESC
    ")->fetchAll();

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
