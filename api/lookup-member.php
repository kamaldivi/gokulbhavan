<?php
/**
 * GET /api/lookup-member.php?q=...
 *
 * Public endpoint to look up a registered member by name or email.
 * Used by ask-guruji.php to verify the submitter is a registered member.
 *
 * Returns up to 5 matches — display info only (no phone/email returned).
 * Requires at least 3 characters to prevent full enumeration.
 *
 * Response:
 * [{ "id": 42, "display_name": "Priya Devi", "city": "Atlanta", "country": "US" }, ...]
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// Sanitise — only allow alphanumeric, spaces, @, ., -
if (!preg_match('/^[\w\s@.\-]{3,80}$/u', $q)) {
    echo json_encode([]);
    exit;
}

try {
    $pdo  = get_db();
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT id,
               CONCAT(first_name, ' ', last_name) AS display_name,
               spiritual_name,
               city,
               country
        FROM registration
        WHERE active = 1
          AND (
              email          LIKE :exact
           OR first_name     LIKE :like1
           OR last_name      LIKE :like2
           OR CONCAT(first_name, ' ', last_name) LIKE :like3
           OR spiritual_name LIKE :like4
          )
        ORDER BY first_name ASC
        LIMIT 5
    ");
    $stmt->execute([':exact' => $q, ':like1' => $like, ':like2' => $like, ':like3' => $like, ':like4' => $like]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('lookup-member.php error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
