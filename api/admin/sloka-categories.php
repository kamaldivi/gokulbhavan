<?php
/**
 * Admin API — sloka category CRUD
 *
 * GET    /api/admin/sloka-categories.php
 *   Returns all sloka categories with sloka counts.
 *
 * POST   { category_code, category_name, image_path?, sort_order? }
 *   Create a category.
 *
 * PUT    { id, category_code, category_name, image_path?, sort_order? }
 *   Update a category by id. Renaming category_code cascades to sloka rows
 *   automatically via FK ON UPDATE CASCADE.
 *
 * DELETE { id }
 *   Delete a category. Blocked (409) if any slokas reference it.
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $pdo  = get_db();
        $stmt = $pdo->query("
            SELECT sc.id, sc.category_code, sc.category_name,
                   sc.image_path, sc.sort_order,
                   COUNT(s.id) AS sloka_count
            FROM sloka_category sc
            LEFT JOIN sloka s ON s.category_code = sc.category_code
            GROUP BY sc.id, sc.category_code, sc.category_name, sc.image_path, sc.sort_order
            ORDER BY sc.sort_order ASC, sc.category_name ASC
        ");
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
        error_log('admin/sloka-categories GET: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $code      = strtoupper(trim($body['category_code']  ?? ''));
    $name      = trim($body['category_name'] ?? '');
    $imagePath = trim($body['image_path']    ?? '') ?: null;
    $sortOrder = isset($body['sort_order'])  ? (int) $body['sort_order'] : 0;

    if ($code === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'category_code and category_name are required']);
        exit;
    }
    if (!preg_match('/^[A-Z0-9\-]{1,20}$/', $code)) {
        http_response_code(400);
        echo json_encode(['message' => 'category_code must be uppercase letters, digits, or hyphens (max 20)']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO sloka_category (category_code, category_name, image_path, sort_order)
            VALUES (:code, :name, :img, :ord)
        ");
        $stmt->execute([':code' => $code, ':name' => $name, ':img' => $imagePath, ':ord' => $sortOrder]);
        $newId = (int) $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $newId, 'category_code' => $code, 'category_name' => $name]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => "Category code '$code' already exists"]);
        } else {
            http_response_code(500);
            error_log('admin/sloka-categories POST: ' . $e->getMessage());
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── PUT ───────────────────────────────────────────────────────────────────────
// Uses numeric id as the stable identifier. ON UPDATE CASCADE on the FK means
// renaming category_code propagates automatically to all sloka rows.
if ($method === 'PUT') {
    $id        = isset($body['id']) ? (int) $body['id'] : 0;
    $code      = strtoupper(trim($body['category_code']  ?? ''));
    $name      = trim($body['category_name'] ?? '');
    $imagePath = trim($body['image_path']    ?? '') ?: null;
    $sortOrder = isset($body['sort_order'])  ? (int) $body['sort_order'] : 0;

    if ($id <= 0 || $code === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'id, category_code and category_name are required']);
        exit;
    }
    if (!preg_match('/^[A-Z0-9\-]{1,20}$/', $code)) {
        http_response_code(400);
        echo json_encode(['message' => 'category_code must be uppercase letters, digits, or hyphens (max 20)']);
        exit;
    }

    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare("
            UPDATE sloka_category
               SET category_code = :code,
                   category_name = :name,
                   image_path    = :img,
                   sort_order    = :ord
             WHERE id = :id
        ");
        $stmt->execute([':code' => $code, ':name' => $name, ':img' => $imagePath, ':ord' => $sortOrder, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
        } else {
            echo json_encode(['id' => $id, 'category_code' => $code, 'category_name' => $name]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => "Code '$code' already in use"]);
        } else {
            http_response_code(500);
            error_log('admin/sloka-categories PUT: ' . $e->getMessage());
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
        $pdo = get_db();
        // Count slokas in this category
        $check = $pdo->prepare("
            SELECT COUNT(s.id) FROM sloka s
            JOIN sloka_category sc ON sc.category_code = s.category_code
            WHERE sc.id = :id
        ");
        $check->execute([':id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete: category has slokas. Reassign them first.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM sloka_category WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
        } else {
            echo json_encode(['deleted' => $id]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('admin/sloka-categories DELETE: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
