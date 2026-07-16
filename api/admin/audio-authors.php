<?php
/**
 * Admin API — audio author management
 *
 * GET    /api/admin/audio-authors.php
 *   Returns all authors with track counts, ordered by name.
 *   Response: [{ "id": 1, "author_name": "Narottama Dasa", "track_count": 12 }, …]
 *
 * POST   /api/admin/audio-authors.php
 *   Create a new author.
 *   Body: { "author_name": "..." }
 *
 * PUT    /api/admin/audio-authors.php
 *   Rename an author.
 *   Body: { "id": 5, "author_name": "..." }
 *
 * DELETE /api/admin/audio-authors.php
 *   Delete an author. Rejected if any tracks still reference it.
 *   Body: { "id": 5 }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $stmt = get_db()->prepare("
            SELECT a.id, a.author_name,
                   COUNT(t.track_id) AS track_count
            FROM audio_author a
            LEFT JOIN audio_track t ON t.author_id = a.id
            GROUP BY a.id, a.author_name
            ORDER BY a.author_name ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id']          = (int) $r['id'];
            $r['track_count'] = (int) $r['track_count'];
        }
        echo json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST — create ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $name = trim($body['author_name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'author_name is required']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("INSERT INTO audio_author (author_name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        $id = (int) $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $id, 'author_name' => $name, 'track_count' => 0]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            http_response_code(409);
            echo json_encode(['message' => "Author '$name' already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── PUT — rename ──────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id   = (int) ($body['id'] ?? 0);
    $name = trim($body['author_name'] ?? '');

    if (!$id || $name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'id and author_name are required']);
        exit;
    }

    try {
        $stmt = get_db()->prepare("UPDATE audio_author SET author_name = :name WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => "Author $id not found"]);
        } else {
            echo json_encode(['message' => 'Updated', 'id' => $id, 'author_name' => $name]);
        }
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            http_response_code(409);
            echo json_encode(['message' => "Author name '$name' already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['message' => 'id is required']);
        exit;
    }

    try {
        $pdo   = get_db();
        $count = $pdo->prepare("SELECT COUNT(*) FROM audio_track WHERE author_id = :id");
        $count->execute([':id' => $id]);
        if ((int) $count->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete: author is assigned to tracks. Reassign or remove those tracks first.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM audio_author WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => "Author $id not found"]);
        } else {
            echo json_encode(['message' => 'Deleted', 'id' => $id]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
