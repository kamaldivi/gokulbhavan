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
        'registrations_total'  => safe_count($pdo, "SELECT COUNT(*) FROM registration WHERE active = 1"),
        'registrations_recent' => safe_count($pdo, "SELECT COUNT(*) FROM registration WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        'announcements_active' => safe_count($pdo, "SELECT COUNT(*) FROM announcement WHERE active = 1
                                                     AND (start_date IS NULL OR start_date <= CURDATE())
                                                     AND (end_date   IS NULL OR end_date   >= CURDATE())"),
        'questions_pending'    => safe_count($pdo, "SELECT COUNT(*) FROM question WHERE status = 'submitted'"),
        'questions_total'      => safe_count($pdo, "SELECT COUNT(*) FROM question"),
        'programs_active'      => safe_count($pdo, "SELECT COUNT(*) FROM program WHERE status IN ('active','upcoming')"),
        'tracks_total'         => safe_count($pdo, "SELECT COUNT(*) FROM audio_track"),
        'tracks_with_lyrics'   => safe_count($pdo, "SELECT COUNT(DISTINCT track_id) FROM lyrics"),
        'bhajan_count'         => safe_count($pdo, "SELECT COUNT(*) FROM audio_track t JOIN audio_category c ON c.category_code = t.category_code WHERE c.audio_family = 'bhajan'"),
        'sloka_count'          => safe_count($pdo, "SELECT COUNT(*) FROM audio_track t JOIN audio_category c ON c.category_code = t.category_code WHERE c.audio_family = 'sloka'"),
        'sankirtan_count'      => safe_count($pdo, "SELECT COUNT(*) FROM audio_track t JOIN audio_category c ON c.category_code = t.category_code WHERE c.audio_family = 'sankirtan'"),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
