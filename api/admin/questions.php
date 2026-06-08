<?php
/**
 * Admin API — question management
 *
 * GET    /api/admin/questions.php[?status=submitted|accepted|rejected|responded]
 *   Returns all questions (newest first), optionally filtered by status.
 *
 * PUT    /api/admin/questions.php
 *   Update status, visibility, and/or response for a question.
 *   Body: { "id": 5, "status": "responded", "visibility": "public", "response": "..." }
 *
 * DELETE /api/admin/questions.php
 *   Delete a question.
 *   Body: { "id": 5 }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = get_db();

    // ── GET: list ──────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $statusFlt = trim($_GET['status'] ?? '');
        $validStatuses = ['submitted', 'accepted', 'rejected', 'responded'];

        $where  = '';
        $params = [];
        if (in_array($statusFlt, $validStatuses, true)) {
            $where            = 'WHERE status = :status';
            $params[':status'] = $statusFlt;
        }

        $stmt = $pdo->prepare("
            SELECT id, name, email, whatsapp, location, question,
                   status, visibility, response, submitted_at
            FROM `question`
            $where
            ORDER BY submitted_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
        }

        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── PUT: update ────────────────────────────────────────────────────────
    if ($method === 'PUT') {
        $id         = (int) ($body['id'] ?? 0);
        $status     = trim($body['status']     ?? '');
        $visibility = trim($body['visibility'] ?? '');
        $response   = isset($body['response']) ? trim($body['response']) : null;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['message' => 'id is required']);
            exit;
        }

        $validStatuses    = ['submitted', 'accepted', 'rejected', 'responded'];
        $validVisibility  = ['public', 'private'];

        if (!in_array($status, $validStatuses, true)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid status']);
            exit;
        }
        if (!in_array($visibility, $validVisibility, true)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid visibility']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE `question`
            SET status = :status, visibility = :visibility, response = :response
            WHERE id = :id
        ");
        $stmt->execute([
            ':status'     => $status,
            ':visibility' => $visibility,
            ':response'   => ($response !== null && $response !== '') ? $response : null,
            ':id'         => $id,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ── DELETE ─────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['message' => 'id is required']);
            exit;
        }

        $pdo->prepare("DELETE FROM `question` WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('admin/questions.php error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
