<?php
/**
 * Admin API — question management
 *
 * GET  /api/admin/questions.php         — list all (newest first)
 * GET  /api/admin/questions.php?new=1   — unread only
 * POST /api/admin/questions.php         — mark read or delete
 *   body: { "action": "read",   "id": 5 }
 *   body: { "action": "delete", "id": 5 }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';    // sets $adminUser or exits 401 JSON

$pdo = get_db();

// ── GET: list ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $newOnly = !empty($_GET['new']);
    $where   = $newOnly ? "WHERE status = 'new'" : '';

    $rows = $pdo->query("
        SELECT id, name, email, whatsapp, location, question,
               status, submitted_at, read_at
        FROM question
        $where
        ORDER BY submitted_at DESC
    ")->fetchAll();

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: action ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';
    $id     = (int) ($body['id'] ?? 0);

    if ($id <= 0 || !in_array($action, ['read', 'delete'], true)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid request']);
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM question WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("
            UPDATE question SET status = 'read', read_at = NOW() WHERE id = ?
        ")->execute([$id]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
