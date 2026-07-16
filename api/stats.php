<?php
/**
 * GET /api/stats.php
 *
 * Public endpoint — returns site-wide counters used for progress displays.
 *
 * Response shape:
 * {
 *   "gokul_kids_completed": 89
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
    $pdo = get_db();

    $completed = (int) $pdo
        ->query("SELECT COUNT(*) FROM audio_track WHERE category_code = 'N'")
        ->fetchColumn();

    echo json_encode(
        ['gokul_kids_completed' => $completed],
        JSON_UNESCAPED_UNICODE
    );

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
