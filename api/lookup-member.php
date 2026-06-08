<?php
/**
 * GET /api/lookup-member.php?email=...
 *
 * Looks up a registered member by exact email address (case-insensitive).
 * Used by ask-guruji.php to verify the submitter is a registered member.
 *
 * Returns a single-element array on match, empty array if not found.
 * Email is never returned in the response (display info only).
 *
 * Response on match:
 * [{ "id": 42, "display_name": "Priya Devi", "spiritual_name": "...", "city": "Atlanta", "country": "US" }]
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$email = trim($_GET['email'] ?? '');

if ($email === '' || mb_strlen($email) > 200) {
    echo json_encode([]);
    exit;
}

// Basic email format check before hitting the DB
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([]);
    exit;
}

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare("
        SELECT id,
               CONCAT(first_name, ' ', last_name) AS display_name,
               spiritual_name,
               city,
               country
        FROM registration
        WHERE active = 1
          AND LOWER(email) = LOWER(:email)
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([]);
        exit;
    }

    $row['id'] = (int) $row['id'];
    echo json_encode([$row], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('lookup-member.php error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}
