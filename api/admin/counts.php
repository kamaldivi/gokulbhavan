<?php
/**
 * GET /api/admin/counts.php
 * Returns dashboard badge counts.
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

function safe_count(PDO $pdo, string $sql): int {
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        error_log('counts.php query error: ' . $e->getMessage());
        return 0;
    }
}

try {
    $pdo = get_db();

    echo json_encode([
        'messages_unread'     => safe_count($pdo, "SELECT COUNT(*) FROM contact_submission WHERE status = 'new'"),
        'registrations_total' => safe_count($pdo, "SELECT COUNT(*) FROM registration"),
        'announcements_active'=> safe_count($pdo, "SELECT COUNT(*) FROM announcement WHERE active = 1
                                                    AND (start_date IS NULL OR start_date <= CURDATE())
                                                    AND (end_date   IS NULL OR end_date   >= CURDATE())"),
        'questions_new'       => safe_count($pdo, "SELECT COUNT(*) FROM question WHERE status = 'new'"),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
