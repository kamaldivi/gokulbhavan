<?php
/**
 * Admin API — audio category management (Bhajan, Sloka, Sankirtan only)
 *
 * GET    /api/admin/audio-categories.php?type=B|S|N
 *   Returns all categories for the given family, ordered by sort_order.
 *
 * POST   /api/admin/audio-categories.php
 *   Create a new category.
 *   Body: { "type": "B"|"S"|"N", "category_code": "X", "category_name": "...", "sort_order": 0 }
 *
 * PUT    /api/admin/audio-categories.php
 *   Update an existing category name and/or sort_order.
 *   Body: { "category_code": "X", "category_name": "...", "sort_order": 0 }
 *
 * DELETE /api/admin/audio-categories.php
 *   Delete a category. Rejected if it still has tracks.
 *   Body: { "category_code": "X" }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = null;

$familyMap = ['B' => 'bhajan', 'S' => 'sloka', 'N' => 'sankirtan'];

function get_pdo(): PDO {
    global $pdo;
    if (!$pdo) $pdo = get_db();
    return $pdo;
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $type = strtoupper(trim($_GET['type'] ?? 'B'));
    if (!isset($familyMap[$type])) {
        http_response_code(400);
        echo json_encode(['message' => 'type must be B, S, or N']);
        exit;
    }

    $rows = get_pdo()->prepare("
        SELECT
            c.category_code,
            c.category_name,
            COALESCE(c.sort_order, 0)   AS sort_order,
            COUNT(t.track_id)           AS track_count
        FROM audio_category c
        LEFT JOIN audio_track t ON t.category_code = c.category_code
        WHERE c.audio_family = :family
        GROUP BY c.category_code, c.category_name, c.sort_order
        ORDER BY c.sort_order ASC, c.category_code ASC
    ");
    $rows->execute([':family' => $familyMap[$type]]);
    $data = $rows->fetchAll();
    foreach ($data as &$r) {
        $r['sort_order']  = (int) $r['sort_order'];
        $r['track_count'] = (int) $r['track_count'];
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST — create ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $type   = strtoupper(trim($body['type'] ?? ''));
    $code   = strtoupper(trim($body['category_code'] ?? ''));
    $name   = trim($body['category_name'] ?? '');
    $order  = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;

    if (!isset($familyMap[$type]) || $code === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['message' => 'type, category_code, and category_name are required']);
        exit;
    }
    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
        http_response_code(400);
        echo json_encode(['message' => 'category_code must be 1-20 uppercase letters, digits, hyphens, or underscores']);
        exit;
    }

    try {
        $stmt = get_pdo()->prepare("
            INSERT INTO audio_category (category_code, category_name, audio_family, sort_order)
            VALUES (:code, :name, :family, :order)
        ");
        $stmt->execute([
            ':code'   => $code,
            ':name'   => $name,
            ':family' => $familyMap[$type],
            ':order'  => $order,
        ]);
        http_response_code(201);
        echo json_encode(['category_code' => $code, 'category_name' => $name, 'sort_order' => $order, 'track_count' => 0]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => "Category code '$code' already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── PUT — update ──────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $code  = strtoupper(trim($body['category_code'] ?? ''));
    $name  = trim($body['category_name'] ?? '');
    $order = isset($body['sort_order']) ? (int) $body['sort_order'] : null;

    if ($code === '' || ($name === '' && $order === null)) {
        http_response_code(400);
        echo json_encode(['message' => 'category_code and at least one of category_name or sort_order required']);
        exit;
    }

    $sets   = [];
    $params = [':code' => $code];
    if ($name !== '')     { $sets[] = 'category_name = :name';  $params[':name']  = $name; }
    if ($order !== null)  { $sets[] = 'sort_order = :order';    $params[':order'] = $order; }

    try {
        $stmt = get_pdo()->prepare("UPDATE audio_category SET " . implode(', ', $sets) . " WHERE category_code = :code");
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => "Category '$code' not found"]);
        } else {
            echo json_encode(['message' => 'Updated']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $code = strtoupper(trim($body['category_code'] ?? ''));
    if ($code === '') {
        http_response_code(400);
        echo json_encode(['message' => 'category_code required']);
        exit;
    }

    try {
        $pdo = get_pdo();
        // Block delete if tracks exist
        $count = $pdo->prepare("SELECT COUNT(*) FROM audio_track WHERE category_code = :code");
        $count->execute([':code' => $code]);
        if ((int) $count->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete: category still has tracks. Remove all tracks first.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM audio_category WHERE category_code = :code");
        $stmt->execute([':code' => $code]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => "Category '$code' not found"]);
        } else {
            echo json_encode(['message' => 'Deleted']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
