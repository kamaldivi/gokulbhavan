<?php
/**
 * GET    /api/admin/video-library.php                     — list categories with playlist counts
 * POST   /api/admin/video-library.php                     — add category       {category_name}
 * PATCH  /api/admin/video-library.php?id=N                — rename category    {category_name}
 * DELETE /api/admin/video-library.php?id=N                — delete category    (blocked if playlists exist)
 * POST   /api/admin/video-library.php?action=add_playlist — add playlist       {playlist_id, playlist_name, category_id}
 * DELETE /api/admin/video-library.php?action=del_playlist — delete playlist    {playlist_id}  (blocked if videos mapped)
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    $db = get_db();

    // ── GET ?playlists=1 — flat list of all playlists (for dropdowns) ─
    if ($method === 'GET' && !empty($_GET['playlists'])) {
        $rows = $db->query("
            SELECT vp.playlist_id, vp.playlist_name, vc.category_name
            FROM video_playlist vp
            JOIN video_category vc ON vc.category_id = vp.category_id
            ORDER BY vc.category_name ASC, vp.playlist_name ASC
        ")->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── GET — list all categories with playlist counts ────────
    if ($method === 'GET') {
        $rows = $db->query("
            SELECT vc.category_id, vc.category_name,
                   COUNT(vp.playlist_id) AS playlist_count
            FROM video_category vc
            LEFT JOIN video_playlist vp ON vp.category_id = vc.category_id
            GROUP BY vc.category_id, vc.category_name
            ORDER BY vc.category_name ASC
        ")->fetchAll();

        foreach ($rows as &$r) {
            $r['category_id']    = (int) $r['category_id'];
            $r['playlist_count'] = (int) $r['playlist_count'];
        }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── POST ?action=add_playlist — add playlist to a category ─
    if ($method === 'POST' && $action === 'add_playlist') {
        $playlistId   = trim($body['playlist_id']   ?? '');
        $playlistName = trim($body['playlist_name'] ?? '');
        $categoryId   = isset($body['category_id']) ? (int) $body['category_id'] : 0;

        if ($playlistId === '' || $playlistName === '' || $categoryId === 0) {
            http_response_code(422);
            echo json_encode(['message' => 'playlist_id, playlist_name and category_id are required']);
            exit;
        }

        // Verify category exists
        $cat = $db->prepare("SELECT category_id FROM video_category WHERE category_id = ?");
        $cat->execute([$categoryId]);
        if (!$cat->fetch()) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO video_playlist (playlist_id, playlist_name, category_id)
            VALUES (:playlist_id, :playlist_name, :category_id)
        ");
        $stmt->execute([
            ':playlist_id'   => $playlistId,
            ':playlist_name' => $playlistName,
            ':category_id'   => $categoryId,
        ]);
        echo json_encode(['message' => 'Playlist added', 'playlist_id' => $playlistId]);
        exit;
    }

    // ── DELETE ?action=del_playlist — remove a playlist ────────
    if ($method === 'DELETE' && $action === 'del_playlist') {
        $playlistId = trim($body['playlist_id'] ?? '');
        if ($playlistId === '') {
            http_response_code(422);
            echo json_encode(['message' => 'playlist_id is required']);
            exit;
        }

        // Guard: block if videos are mapped to this playlist
        $count = $db->prepare("SELECT COUNT(*) FROM video_playlist_map WHERE playlist_id = ?");
        $count->execute([$playlistId]);
        if ((int) $count->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete — videos are mapped to this playlist. Run YouTube Sync after removing from YouTube, or reassign videos first.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM video_playlist WHERE playlist_id = ?");
        $stmt->execute([$playlistId]);
        echo json_encode(['message' => 'Playlist deleted']);
        exit;
    }

    // ── PATCH ?action=rename_playlist — rename a playlist ──────
    if ($method === 'PATCH' && $action === 'rename_playlist') {
        $playlistId   = trim($body['playlist_id']   ?? '');
        $playlistName = trim($body['playlist_name'] ?? '');
        if ($playlistId === '' || $playlistName === '') {
            http_response_code(422);
            echo json_encode(['message' => 'playlist_id and playlist_name are required']);
            exit;
        }
        $stmt = $db->prepare("UPDATE video_playlist SET playlist_name = ? WHERE playlist_id = ?");
        $stmt->execute([$playlistName, $playlistId]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Playlist not found']);
            exit;
        }
        echo json_encode(['message' => 'Playlist renamed']);
        exit;
    }

    // ── POST — add category ────────────────────────────────────
    if ($method === 'POST') {
        $name = trim($body['category_name'] ?? '');
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['message' => 'category_name is required']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO video_category (category_name) VALUES (?)");
        $stmt->execute([$name]);
        echo json_encode(['message' => 'Category added', 'category_id' => (int) $db->lastInsertId()]);
        exit;
    }

    // ── PATCH ?id=N — rename category ─────────────────────────
    if ($method === 'PATCH') {
        if (!$id) { http_response_code(400); echo json_encode(['message' => 'id is required']); exit; }
        $name = trim($body['category_name'] ?? '');
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['message' => 'category_name is required']);
            exit;
        }
        $stmt = $db->prepare("UPDATE video_category SET category_name = ? WHERE category_id = ?");
        $stmt->execute([$name, $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
            exit;
        }
        echo json_encode(['message' => 'Category updated']);
        exit;
    }

    // ── DELETE ?id=N — delete category ────────────────────────
    if ($method === 'DELETE') {
        if (!$id) { http_response_code(400); echo json_encode(['message' => 'id is required']); exit; }

        // Guard: block if playlists exist under this category
        $count = $db->prepare("SELECT COUNT(*) FROM video_playlist WHERE category_id = ?");
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete — playlists exist under this category. Remove playlists first.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM video_category WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
            exit;
        }
        echo json_encode(['message' => 'Category deleted']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('video-library.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error. Please try again later.']);
}
