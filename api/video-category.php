<?php
/**
 * GET /api/video-menu.php
 *
 * Returns the full category → playlist hierarchy from video_category + video_playlist.
 *
 * Response:
 * [
 *   {
 *     "category_id": 1,
 *     "category": "Harikatha English",
 *     "playlists": [
 *       { "playlist_id": "PLxxx", "name": "Harikatha English-01" }
 *     ]
 *   }, ...
 * ]
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $db = get_db();

    $rows = $db->query("
        SELECT
            vc.category_id,
            vc.category_name,
            vp.playlist_id,
            vp.playlist_name
        FROM video_category vc
        JOIN video_playlist vp ON vp.category_id = vc.category_id
        ORDER BY vc.category_name ASC, vp.playlist_name ASC
    ")->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $id = $row['category_id'];
        if (!isset($grouped[$id])) {
            $grouped[$id] = [
                'category_id' => (int) $row['category_id'],
                'category'    => $row['category_name'],
                'playlists'   => [],
            ];
        }
        $grouped[$id]['playlists'][] = [
            'playlist_id' => $row['playlist_id'],
            'name'        => $row['playlist_name'],
        ];
    }

    echo json_encode(array_values($grouped), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('video-menu.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error. Please try again later.']);
}
