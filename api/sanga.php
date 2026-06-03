<?php
/**
 * GET /api/sanga.php
 * Public endpoint — returns all active sangas ordered by sort_order.
 * Used by the public Contact page (no auth required).
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
        SELECT id, sanga_name, contact_person, description, region, flag,
               address_line1, address_line2, city, state, postal_code, country,
               contacts_list, phone, email, map_url, service_times, sort_order
        FROM sanga
        WHERE active = 1
        ORDER BY sort_order ASC, id ASC
    ")->fetchAll();

    foreach ($rows as &$r) {
        $r['id']         = (int) $r['id'];
        $r['sort_order'] = (int) $r['sort_order'];
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
