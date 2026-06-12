<?php
/**
 * GET /api/post-categories.php — public read-only categories endpoint
 *
 * Query parameters:
 *   post_type=blog|event  — filter by post type (default: all)
 *
 * Response: array of { id, name, slug, description, post_type,
 *                      is_sequential, placement, sort_order }
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $db        = get_db();
    $postType  = isset($_GET['post_type']) ? trim($_GET['post_type']) : null;

    if ($postType !== null && in_array($postType, ['blog', 'event'], true)) {
        $stmt = $db->prepare(
            "SELECT id, name, slug, description, post_type, is_sequential, placement, sort_order
             FROM post_category
             WHERE post_type = :pt
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute([':pt' => $postType]);
    } else {
        $stmt = $db->query(
            "SELECT id, name, slug, description, post_type, is_sequential, placement, sort_order
             FROM post_category
             ORDER BY post_type ASC, sort_order ASC, name ASC"
        );
    }

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']            = (int) $r['id'];
        $r['is_sequential'] = (int) $r['is_sequential'];
        $r['sort_order']    = (int) $r['sort_order'];
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('post-categories.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error']);
}
