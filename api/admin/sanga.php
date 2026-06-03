<?php
/**
 * GET    /api/admin/sanga.php        — list all sangas
 * POST   /api/admin/sanga.php        — create
 * PUT    /api/admin/sanga.php?id=N   — update
 * DELETE /api/admin/sanga.php?id=N   — delete
 */
require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$FIELDS = ['sanga_name','contact_person','description','region','flag',
           'address_line1','address_line2','city','state','postal_code','country',
           'contacts_list','phone','email','map_url','service_times','sort_order','active'];

try {
    $pdo    = get_db();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM sanga ORDER BY sort_order ASC, id ASC")->fetchAll();
        foreach ($rows as &$r) {
            $r['id']         = (int) $r['id'];
            $r['sort_order'] = (int) $r['sort_order'];
            $r['active']     = (int) $r['active'];
        }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $name = trim($body['sanga_name'] ?? '');
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['message' => 'sanga_name is required']);
            exit;
        }

        $cols   = [];
        $vals   = [];
        $params = [];
        foreach ($FIELDS as $f) {
            if (array_key_exists($f, $body)) {
                $cols[]    = $f;
                $vals[]    = ":$f";
                $v = $body[$f];
                $params[":$f"] = ($v === '' || $v === null) ? null
                    : (in_array($f, ['sort_order','active']) ? (int)$v : trim((string)$v));
            }
        }
        $cols[]    = 'updated_at';
        $vals[]    = 'NOW()';

        $pdo->prepare(
            "INSERT INTO sanga (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")"
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
                $sets[]      = "$f = :$f";
                $v = $body[$f];
                $params[":$f"] = ($v === '' || $v === null) ? null
                    : (in_array($f, ['sort_order','active']) ? (int)$v : trim((string)$v));
            }
        }
        if (empty($sets)) {
            http_response_code(422);
            echo json_encode(['message' => 'No fields to update']);
            exit;
        }
        $sets[] = 'updated_at = NOW()';
        $pdo->prepare("UPDATE sanga SET " . implode(', ', $sets) . " WHERE id = :id")
            ->execute($params);
        echo json_encode(['message' => 'Updated']);

    } elseif ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'Missing id']);
            exit;
        }
        $pdo->prepare("DELETE FROM sanga WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['message' => 'Deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
