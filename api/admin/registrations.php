<?php
/**
 * GET    /api/admin/registrations.php        — list all (newest first)
 * GET    /api/admin/registrations.php?id=N   — single record (for edit pre-fill)
 * PUT    /api/admin/registrations.php?id=N   — update editable fields
 * DELETE /api/admin/registrations.php?id=N   — delete record
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$pdo    = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($id > 0) {
        // Single record for edit modal pre-fill
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, spiritual_name, email, phone, whatsapp,
                   address1, address2, city, state_province, postal_code, country,
                   language_pref, active, notes, submitted_at
            FROM registration WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['message' => 'Not found']); exit; }
        $row['id']     = (int) $row['id'];
        $row['active'] = (int) $row['active'];
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Full list
    $search = trim($_GET['search'] ?? '');
    $where  = '';
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where = "WHERE first_name LIKE :s1 OR last_name LIKE :s2
                     OR email LIKE :s3 OR city LIKE :s4";
        $params = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
    }

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, spiritual_name, email, phone, whatsapp,
               address1, city, state_province, postal_code, country,
               language_pref, active, notes, submitted_at
        FROM registration
        $where
        ORDER BY submitted_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id']     = (int) $r['id'];
        $r['active'] = (int) $r['active'];
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── PUT: update ───────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) { http_response_code(400); echo json_encode(['message' => 'Missing id']); exit; }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) { http_response_code(400); echo json_encode(['message' => 'Invalid JSON']); exit; }

    $allowed = ['first_name','last_name','spiritual_name','email','phone','whatsapp',
                'address1','address2','city','state_province','postal_code',
                'country','language_pref','active','notes'];

    $sets   = [];
    $params = [':id' => $id];

    foreach ($allowed as $f) {
        if (!array_key_exists($f, $body)) continue;
        $v = $body[$f];
        if ($f === 'active') {
            $params[":$f"] = $v ? 1 : 0;
        } else {
            $params[":$f"] = ($v === '' || $v === null) ? null : trim((string) $v);
        }
        $sets[] = "$f = :$f";
    }

    if (empty($sets)) {
        http_response_code(422);
        echo json_encode(['message' => 'No fields to update']);
        exit;
    }

    // Validate email if provided
    if (isset($params[':email']) && $params[':email'] !== null) {
        if (!filter_var($params[':email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['message' => 'Invalid email address']);
            exit;
        }
    }

    try {
        $pdo->prepare("UPDATE registration SET " . implode(', ', $sets) . " WHERE id = :id")
            ->execute($params);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('registrations PUT error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) { http_response_code(400); echo json_encode(['message' => 'Missing id']); exit; }

    try {
        $pdo->prepare("DELETE FROM registration WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('registrations DELETE error: ' . $e->getMessage());
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
