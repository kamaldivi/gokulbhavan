<?php
/**
 * GET    /api/admin/announcements.php       — list all
 * POST   /api/admin/announcements.php       — create
 * PUT    /api/admin/announcements.php?id=N  — update
 * DELETE /api/admin/announcements.php?id=N  — delete
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$allowed = ['title', 'body', 'url', 'start_date', 'end_date', 'active'];

try {
    $pdo = get_db();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM announcement ORDER BY created_at DESC")->fetchAll();
        foreach ($rows as &$r) {
            $r['id']     = (int) $r['id'];
            $r['active'] = (int) $r['active'];
        }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            http_response_code(422);
            echo json_encode(['message' => 'Title is required']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO announcement (title, body, url, start_date, end_date, active)
            VALUES (:title, :body, :url, :start_date, :end_date, :active)
        ");
        $stmt->execute([
            ':title'      => $title,
            ':body'       => $body['body']       ?? null,
            ':url'        => $body['url']        ?: null,
            ':start_date' => $body['start_date'] ?: null,
            ':end_date'   => $body['end_date']   ?: null,
            ':active'     => isset($body['active']) ? (int) $body['active'] : 1,
        ]);

        http_response_code(201);
        echo json_encode(['id' => (int) $pdo->lastInsertId(), 'message' => 'Created']);

    } elseif ($method === 'PUT') {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true);

        if (!$id || !$body) {
            http_response_code(422);
            echo json_encode(['message' => 'Invalid request']);
            exit;
        }

        $sets   = [];
        $params = [':id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $sets[]      = "$f = :$f";
                $v = $body[$f];
                $params[":$f"] = ($v === '' || $v === null) ? null : ($f === 'active' ? (int)$v : trim((string)$v));
            }
        }

        if (empty($sets)) {
            http_response_code(422);
            echo json_encode(['message' => 'No fields to update']);
            exit;
        }

        $pdo->prepare("UPDATE announcement SET " . implode(', ', $sets) . " WHERE id = :id")
            ->execute($params);
        echo json_encode(['message' => 'Updated']);

    } elseif ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'Missing id']);
            exit;
        }
        $pdo->prepare("DELETE FROM announcement WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['message' => 'Deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
