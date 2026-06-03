<?php
/**
 * GET    /api/admin/programs.php       — list all (with derived status)
 * POST   /api/admin/programs.php       — create
 * PUT    /api/admin/programs.php?id=N  — update
 * DELETE /api/admin/programs.php?id=N  — delete
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$FIELDS = [
    'title', 'description', 'teacher', 'language',
    'day_of_week', 'time_est',
    'event_date', 'event_time',
    'zoom_url', 'youtube_live_url', 'video_playlist', 'platform', 'duration_min',
    'site_id', 'start_date', 'end_date', 'order_num',
];

$INT_FIELDS = ['duration_min', 'order_num'];

try {
    $pdo    = get_db();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT * FROM program ORDER BY order_num ASC, start_date DESC, id DESC"
        )->fetchAll();

        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $r['id']          = (int) $r['id'];
            $r['duration_min'] = (int) $r['duration_min'];
            $r['order_num']   = (int) $r['order_num'];
            $r['status']      = deriveStatus($r, $today);
        }
        unset($r);

        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'POST') {
        $body  = json_decode(file_get_contents('php://input'), true);
        $title = trim($body['title'] ?? '');
        if ($title === '') {
            http_response_code(422);
            echo json_encode(['message' => 'title is required']);
            exit;
        }

        $cols   = [];
        $vals   = [];
        $params = [];
        foreach ($FIELDS as $f) {
            if (array_key_exists($f, $body)) {
                $cols[]        = $f;
                $vals[]        = ":$f";
                $v             = $body[$f];
                $params[":$f"] = ($v === '' || $v === null)
                    ? null
                    : (in_array($f, $INT_FIELDS) ? (int) $v : trim((string) $v));
            }
        }

        if (empty($cols)) {
            http_response_code(422);
            echo json_encode(['message' => 'No fields provided']);
            exit;
        }

        $pdo->prepare(
            "INSERT INTO program (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")"
        )->execute($params);

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
        foreach ($FIELDS as $f) {
            if (array_key_exists($f, $body)) {
                $sets[]        = "$f = :$f";
                $v             = $body[$f];
                $params[":$f"] = ($v === '' || $v === null)
                    ? null
                    : (in_array($f, $INT_FIELDS) ? (int) $v : trim((string) $v));
            }
        }

        if (empty($sets)) {
            http_response_code(422);
            echo json_encode(['message' => 'No fields to update']);
            exit;
        }

        $pdo->prepare("UPDATE program SET " . implode(', ', $sets) . " WHERE id = :id")
            ->execute($params);
        echo json_encode(['message' => 'Updated']);

    } elseif ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'Missing id']);
            exit;
        }
        $pdo->prepare("DELETE FROM program WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['message' => 'Deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}

function deriveStatus(array $row, string $today): string {
    $sd = $row['start_date'] ?? null;
    $ed = $row['end_date']   ?? null;

    // One-off: same start and end date
    if ($sd && $ed && $sd === $ed) return 'one-off';

    // Completed: end_date is set and in the past
    if ($ed && $ed < $today) return 'completed';

    // Upcoming: start_date is in the future
    if ($sd && $sd > $today) return 'upcoming';

    // Active: started and not yet ended
    return 'active';
}
