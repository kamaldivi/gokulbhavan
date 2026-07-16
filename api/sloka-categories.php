<?php
/**
 * GET /api/sloka-categories.php
 *
 * Returns all sloka categories with sloka counts.
 *
 * Response shape (array of):
 * {
 *   "category_code": "GURU",
 *   "category_name": "Guru-tattva",
 *   "sloka_count":   67
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
        SELECT sc.category_code, sc.category_name,
               COUNT(s.id) AS sloka_count
        FROM sloka_category sc
        LEFT JOIN sloka s ON s.category_code = sc.category_code
        GROUP BY sc.category_code, sc.category_name
        ORDER BY sc.category_name ASC
    ");
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('sloka-categories.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
