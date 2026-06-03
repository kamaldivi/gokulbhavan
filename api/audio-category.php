<?php
/**
 * GET /api/audio-menu.php
 *
 * Returns the list of audio categories (families) for a given media type.
 *
 * Query parameters:
 *   type=B   — Bhajan categories  (default)
 *   type=S   — Sloka categories
 *
 * Response shape (array of):
 * {
 *   "category_code":  "A",
 *   "category_name":  "Guru Vaishnavas Gaura Nitai",
 *   "cover_path":     null,
 *   "sort_order":     0,
 *   "track_count":    64
 * }
 * For type=A (albums), cover_path contains the album art path (or null).
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$type = strtoupper(trim($_GET['type'] ?? 'B'));
if (!in_array($type, ['B', 'S', 'A'], true)) {
    http_response_code(400);
    echo json_encode(['message' => 'type must be B, S, or A']);
    exit;
}

// Map type codes to audio_family enum values
$familyMap = ['B' => 'bhajan', 'S' => 'sloka', 'N' => 'sankirtan', 'A' => 'album'];
$family    = $familyMap[$type];

try {
    $pdo = get_db();

    $sql = "
        SELECT
            c.category_code,
            c.category_name,
            c.image_path                            AS cover_path,
            COALESCE(c.sort_order, 0)               AS sort_order,
            COUNT(t.track_id)                       AS track_count
        FROM audio_category c
        LEFT JOIN audio_track t ON t.category_code = c.category_code
        WHERE c.audio_family = :family
        GROUP BY
            c.category_code, c.category_name, c.image_path, c.sort_order
        ORDER BY
            c.sort_order ASC, c.category_code ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':family' => $family]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['sort_order']  = (int) $r['sort_order'];
        $r['track_count'] = (int) $r['track_count'];
        if (empty($r['cover_path'])) $r['cover_path'] = null;
    }

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('audio-menu.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
