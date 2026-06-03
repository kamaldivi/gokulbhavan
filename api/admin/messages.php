<?php
/**
 * GET  /api/admin/messages.php         — list all messages
 * POST /api/admin/messages.php         — mark as read/unread
 *   body: { id: int, status: "read"|"new" }
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

try {
    $pdo = get_db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT id, name, email, subject, message, status, submitted_at
            FROM contact_submission
            ORDER BY submitted_at DESC
        ");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
        }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id     = (int)   ($body['id']     ?? 0);
        $status = trim($body['status'] ?? '');

        if (!$id || !in_array($status, ['new', 'read'], true)) {
            http_response_code(422);
            echo json_encode(['message' => 'Invalid payload']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE contact_submission SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        echo json_encode(['message' => 'Updated']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
