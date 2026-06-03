<?php
/**
 * EXAMPLE config — copy to config.php and fill in real values.
 * config.php is git-ignored. Upload config.php directly to IONOS via SFTP.
 */

define('DB_HOST', 'your-db-host.hosting-data.io');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Secret token for youtubesync.php — use a long random string
define('SYNC_TOKEN', 'your-secret-sync-token-here');

// Google OAuth 2.0 — from Google Cloud Console
define('GOOGLE_CLIENT_ID',     'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
define('GOOGLE_REDIRECT_URI',  'https://gokulbhavan.org/api/auth/google-callback.php');

// Emails allowed admin access
define('ADMIN_ALLOWLIST', [
    'your-email@gmail.com',
]);

// Session lifetime in seconds (28800 = 8 hours)
define('ADMIN_SESSION_TTL', 28800);

// ── reCAPTCHA v3 (https://www.google.com/recaptcha/admin) ────
// "Site key"   goes here AND in ask-guruji.php (sent to browser)
// "Secret key" stays server-side only
define('RECAPTCHA_SITE_KEY', 'your-recaptcha-v3-site-key');
define('RECAPTCHA_SECRET',   'your-recaptcha-v3-secret-key');

// ── IONOS SMTP (for Ask Guruji email notifications) ──────────
// Create noreply@yourdomain.com in IONOS webmail first.
// IONOS SMTP: smtp.ionos.com:587 (STARTTLS)
define('SMTP_HOST', 'smtp.ionos.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@gokulbhavan.org');
define('SMTP_PASS', 'your-ionos-email-password');

// Who receives the "new question" notification email
define('NOTIFY_EMAIL', 'bkdasa@gmail.com');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
