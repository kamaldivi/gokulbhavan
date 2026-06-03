<?php
/**
 * POST /api/ask-guruji.php
 *
 * Accepts an "Ask Guruji" question submission.
 * Validates reCAPTCHA v3 (score >= 0.5), honeypot, and required fields.
 * Inserts into `question` table and sends email notification.
 *
 * Expected JSON body:
 * {
 *   "name":      "Priya Devi",       // required
 *   "email":     "p@example.com",    // required unless whatsapp provided
 *   "whatsapp":  "+1-555-...",        // required unless email provided
 *   "location":  "Atlanta, GA",      // optional
 *   "question":  "Guruji, ...",      // required
 *   "recaptcha":       "<v3 token>", // required
 *   "registration_id": 42,           // required — verified registered member
 *   "website":         ""            // honeypot -- must be empty
 * }
 *
 * Requires in config.php:
 *   RECAPTCHA_SECRET, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, NOTIFY_EMAIL
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON']);
    exit;
}

// ── Honeypot ──────────────────────────────────────────────────
// Real users never fill the hidden "website" field — bots do.
// Return a silent 200 so bots think they succeeded.
if (!empty($body['website'])) {
    echo json_encode(['message' => 'Thank you. Hare Krsna!']);
    exit;
}

// ── reCAPTCHA v3 ──────────────────────────────────────────────
$token = trim($body['recaptcha'] ?? '');
if ($token === '') {
    http_response_code(400);
    echo json_encode(['message' => 'reCAPTCHA token missing']);
    exit;
}

$verifyCtx = stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => 'Content-Type: application/x-www-form-urlencoded',
    'content' => http_build_query([
        'secret'   => RECAPTCHA_SECRET,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]),
    'timeout' => 5,
]]);

$rcRaw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $verifyCtx);
$rc    = $rcRaw ? json_decode($rcRaw, true) : null;

if (!$rc || empty($rc['success']) || ($rc['score'] ?? 0) < 0.5) {
    http_response_code(429);
    echo json_encode(['message' => 'Spam check failed. Please try again.']);
    exit;
}

// ── Validate fields ───────────────────────────────────────────
$registrationId = (int) ($body['registration_id'] ?? 0);
$question       = trim($body['question'] ?? '');

if ($registrationId <= 0) {
    http_response_code(422);
    echo json_encode(['message' => 'Please find your profile before submitting']);
    exit;
}
if (mb_strlen($question) < 10) {
    http_response_code(422);
    echo json_encode(['message' => 'Please write your question (at least 10 characters)']);
    exit;
}

// ── Verify registration exists and is active ──────────────────
try {
    $pdo  = get_db();
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name,' ',last_name) AS full_name,
               email, whatsapp, city, country
        FROM registration WHERE id = ? AND active = 1 LIMIT 1
    ");
    $stmt->execute([$registrationId]);
    $member = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    error_log('ask-guruji.php DB lookup error: ' . $e->getMessage());
    echo json_encode(['message' => 'Could not verify your profile. Please try again.']);
    exit;
}

if (!$member) {
    http_response_code(422);
    echo json_encode(['message' => 'Registered profile not found. Please register first.']);
    exit;
}

$name     = $member['full_name'];
$email    = $member['email']    ?? '';
$whatsapp = $member['whatsapp'] ?? '';
$location = trim(implode(', ', array_filter([$member['city'], $member['country']])));

// ── Insert ────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO question (registration_id, name, email, whatsapp, location, question)
        VALUES (:reg_id, :name, :email, :whatsapp, :location, :question)
    ");
    $stmt->execute([
        ':reg_id'   => $registrationId,
        ':name'     => $name,
        ':email'    => $email    ?: null,
        ':whatsapp' => $whatsapp ?: null,
        ':location' => $location ?: null,
        ':question' => $question,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('ask-guruji.php DB error: ' . $e->getMessage());
    echo json_encode(['message' => 'Could not save your question. Please try again.']);
    exit;
}

// ── Email notification ────────────────────────────────────────
send_notification($name, $email, $whatsapp, $location, $question);

echo json_encode(['message' => 'Your question has been submitted. Hare Krsna!']);

// ─────────────────────────────────────────────────────────────
function send_notification(string $name, string $email, string $whatsapp,
                            string $location, string $question): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('ask-guruji: PHPMailer vendor/autoload.php not found — skipping email');
        return;
    }
    require_once $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, 'Gokul Bhavan');
        foreach (array_map('trim', explode(',', NOTIFY_EMAIL)) as $addr) {
            if ($addr !== '') $mail->addAddress($addr);
        }

        $contact = $email ?: $whatsapp;
        $locLine = $location ? "Location: $location\n" : '';
        $locTag  = $location ? " ($location)" : '';

        $mail->Subject = "Ask Guruji — new question from $name$locTag";
        $mail->isHTML(false);
        $mail->Body =
            "A new question was submitted on gokulbhavan.org\n\n" .
            "Name:     $name\n" .
            "Contact:  $contact\n" .
            $locLine .
            "\nQuestion:\n$question\n\n" .
            "---\nView all: https://gokulbhavan.org/admin/questions";

        $mail->send();
    } catch (Throwable $e) {
        error_log('ask-guruji mailer error: ' . $e->getMessage());
    }
}
