<?php
/**
 * Google OAuth Login Initiator — gokulbhavan.org/api/auth/google-login.php
 *
 * Called by the admin login page JS.
 * Generates a CSRF state token, stores it in the PHP session,
 * and returns the Google OAuth URL for the browser to redirect to.
 *
 * Response: { "url": "https://accounts.google.com/o/oauth2/v2/auth?..." }
 */

session_start();

require __DIR__ . '/../_cors.php';
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID_HERE') {
    http_response_code(503);
    echo json_encode(['error' => 'OAuth not configured']);
    exit;
}

// Generate CSRF state token
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

echo json_encode(['url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $params]);
