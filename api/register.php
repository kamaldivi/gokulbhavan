<?php
/**
 * POST /api/register.php
 * Accepts a JSON registration submission and inserts into registration.
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON']);
    exit;
}

// ── Required fields ───────────────────────────────────────────
$required = ['first_name', 'last_name', 'email', 'gender', 'language_pref',
             'address_line1', 'city', 'postal_code', 'country',
             'country_code', 'phone_number'];

foreach ($required as $field) {
    if (empty(trim($body[$field] ?? ''))) {
        http_response_code(422);
        echo json_encode(['message' => "Missing required field: $field"]);
        exit;
    }
}

// ── Sanitise ──────────────────────────────────────────────────
$first_name     = trim($body['first_name']);
$last_name      = trim($body['last_name']);
$email          = filter_var(trim($body['email']), FILTER_VALIDATE_EMAIL);
$gender         = trim($body['gender']);
$language_pref  = trim($body['language_pref']);
$address1       = trim($body['address_line1']);
$address2       = trim($body['address_line2'] ?? '');
$city           = trim($body['city']);
$state_province = trim($body['state_province'] ?? '');
$postal_code    = trim($body['postal_code']);
$country        = trim($body['country']);
$phone          = trim($body['country_code']) . ' ' . trim($body['phone_number']);
$whatsapp       = isset($body['wa_country_code'], $body['wa_phone_number'])
                    ? trim($body['wa_country_code']) . ' ' . trim($body['wa_phone_number'])
                    : null;

if (!$email) {
    http_response_code(422);
    echo json_encode(['message' => 'Invalid email address']);
    exit;
}

if (!in_array($gender, ['Male', 'Female'], true)) {
    http_response_code(422);
    echo json_encode(['message' => 'Invalid gender value']);
    exit;
}

if (!in_array($language_pref, ['English', 'Tamil'], true)) {
    http_response_code(422);
    echo json_encode(['message' => 'Invalid language preference']);
    exit;
}

// ── Insert ────────────────────────────────────────────────────
try {
    $db = get_db();
    $spiritual_name = trim($body['spiritual_name'] ?? '');

    $stmt = $db->prepare("
        INSERT INTO registration
            (first_name, last_name, spiritual_name, email, phone, whatsapp,
             address1, address2, city, state_province, postal_code, country,
             language_pref, active, submitted_at)
        VALUES
            (:first_name, :last_name, :spiritual_name, :email, :phone, :whatsapp,
             :address1, :address2, :city, :state_province, :postal_code, :country,
             :language_pref, 1, NOW())
    ");

    $stmt->execute([
        ':first_name'      => $first_name,
        ':last_name'       => $last_name,
        ':spiritual_name'  => $spiritual_name ?: null,
        ':email'           => $email,
        ':phone'           => $phone,
        ':whatsapp'        => $whatsapp,
        ':address1'        => $address1,
        ':address2'        => $address2 ?: null,
        ':city'            => $city,
        ':state_province'  => $state_province ?: null,
        ':postal_code'     => $postal_code,
        ':country'         => $country,
        ':language_pref'   => $language_pref,
    ]);

    http_response_code(201);
    echo json_encode(['message' => 'Registration successful']);

} catch (PDOException $e) {
    http_response_code(500);
    // Do not expose DB error details to client
    error_log('register.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error. Please try again later.']);
}
