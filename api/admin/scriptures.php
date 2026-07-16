<?php
/**
 * Admin API — scripture CRUD
 *
 * GET    /api/admin/scriptures.php              — list all (ordered by sort_order, name)
 * GET    /api/admin/scriptures.php?search=X     — filtered list
 * GET    /api/admin/scriptures.php?id=X         — single scripture
 *
 * POST   { name, short_title?, image_path?, sort_order? }   → 201 { id }
 * PUT    { id, name, short_title?, image_path?, sort_order? }
 * DELETE { id }   — FK ON DELETE SET NULL clears sloka.scripture_id automatically.
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id     = isset($_GET['id'])    && $_GET['id']    !== '' ? (int) $_GET['id'] : null;
    $search = trim($_GET['search'] ?? '');

    $baseSelect = "
        SELECT sc.id, sc.name, sc.short_title, sc.image_path, sc.sort_order,
               COUNT(sl.id) AS sloka_count
        FROM scripture sc
        LEFT JOIN sloka sl ON sl.scripture_id = sc.id
    ";

    try {
        $pdo = get_db();

        if ($id !== null) {
            $stmt = $pdo->prepare(
                $baseSelect . "WHERE sc.id = :id
                 GROUP BY sc.id, sc.name, sc.short_title, sc.image_path, sc.sort_order"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                exit;
            }
            $row['id']         = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['sloka_count']= (int) $row['sloka_count'];
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($search !== '') {
            $stmt = $pdo->prepare(
                $baseSelect . "WHERE sc.name LIKE :s1 OR sc.short_title LIKE :s2
                 GROUP BY sc.id, sc.name, sc.short_title, sc.image_path, sc.sort_order
                 ORDER BY sc.sort_order ASC, sc.name ASC"
            );
            $like = '%' . $search . '%';
            $stmt->execute([':s1' => $like, ':s2' => $like]);
        } else {
            $stmt = $pdo->query(
                $baseSelect . "GROUP BY sc.id, sc.name, sc.short_title, sc.image_path, sc.sort_order
                 ORDER BY sc.sort_order ASC, sc.name ASC"
            );
        }

        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id']         = (int) $r['id'];
            $r['sort_order'] = (int) $r['sort_order'];
            $r['sloka_count']= (int) $r['sloka_count'];
        }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/scriptures GET: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $name       = trim($body['name']        ?? '');
    $shortTitle = trim($body['short_title'] ?? '') ?: null;
    $imagePath  = trim($body['image_path']  ?? '') ?: null;
    $sortOrder  = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'name is required']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO scripture (name, short_title, image_path, sort_order)
            VALUES (:name, :short, :img, :ord)
        ");
        $stmt->execute([':name' => $name, ':short' => $shortTitle, ':img' => $imagePath, ':ord' => $sortOrder]);
        $newId = (int) $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $newId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => 'A scripture with that name already exists']);
        } else {
            http_response_code(500);
            error_log('admin/scriptures POST: ' . $e->getMessage());
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── PUT ───────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id         = isset($body['id']) ? (int) $body['id'] : 0;
    $name       = trim($body['name']        ?? '');
    $shortTitle = trim($body['short_title'] ?? '') ?: null;
    $imagePath  = trim($body['image_path']  ?? '') ?: null;
    $sortOrder  = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;

    if ($id <= 0 || $name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'id and name are required']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("
            UPDATE scripture
               SET name = :name, short_title = :short, image_path = :img, sort_order = :ord
             WHERE id = :id
        ");
        $stmt->execute([':name' => $name, ':short' => $shortTitle, ':img' => $imagePath, ':ord' => $sortOrder, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Scripture not found']);
        } else {
            echo json_encode(['id' => $id, 'updated' => true]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => 'Name already in use']);
        } else {
            http_response_code(500);
            error_log('admin/scriptures PUT: ' . $e->getMessage());
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['message' => 'id is required']);
        exit;
    }

    try {
        $pdo  = get_db();
        // FK `fk_sloka_scr` ON DELETE SET NULL clears sloka.scripture_id automatically
        $stmt = $pdo->prepare('DELETE FROM scripture WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Scripture not found']);
        } else {
            echo json_encode(['deleted' => $id]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/scriptures DELETE: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
