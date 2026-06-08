<?php
/**
 * GET /api/questions-public.php
 *
 * Returns questions that have been responded to and marked public.
 * Used by ask-guruji.php to display a public Q&A section.
 *
 * Response: [{ "question": "...", "response": "..." }, ...]
 * Newest first. No user identification returned.
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
        SELECT question, response
        FROM   `question`
        WHERE  status     = 'responded'
          AND  visibility = 'public'
        ORDER  BY submitted_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('questions-public.php error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
