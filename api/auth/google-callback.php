<?php
/**
 * Google OAuth 2.0 Callback — gokulbhavan.org/api/auth/google-callback.php
 *
 * Flow:
 *   1. Google redirects here with ?code=... after user approves
 *   2. Exchange code for access token
 *   3. Fetch user profile (email, name, picture)
 *   4. Check email against ADMIN_ALLOWLIST in config.php
 *   5. Set PHP session and redirect to /admin/dashboard
 *
 * On any failure: redirect to /admin?error=... for the login page to display.
 */

session_start();

require __DIR__ . '/../config.php';

// ── Helpers ───────────────────────────────────────────────────

function fail(string $reason): never {
    header('Location: /admin?error=' . urlencode($reason));
    exit;
}

function googlePost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}

function googleGet(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}

// ── Validate required constants ───────────────────────────────

if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET') || !defined('ADMIN_ALLOWLIST')) {
    fail('OAuth not configured');
}

// ── CSRF state check ──────────────────────────────────────────

$state = $_GET['state'] ?? '';
if (!$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
    fail('Invalid state — possible CSRF');
}
unset($_SESSION['oauth_state']);

// ── Error from Google ─────────────────────────────────────────

if (!empty($_GET['error'])) {
    fail($_GET['error']);
}

$code = $_GET['code'] ?? '';
if (!$code) {
    fail('No authorization code received');
}

// ── Exchange code for tokens ──────────────────────────────────

$tokens = googlePost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (empty($tokens['access_token'])) {
    fail('Token exchange failed');
}

// ── Fetch user profile ────────────────────────────────────────

$profile = googleGet('https://www.googleapis.com/oauth2/v2/userinfo', $tokens['access_token']);

$email = strtolower(trim($profile['email'] ?? ''));
if (!$email) {
    fail('Could not retrieve email from Google');
}

// ── Check allowlist ───────────────────────────────────────────

$allowlist = array_map('strtolower', ADMIN_ALLOWLIST);
if (!in_array($email, $allowlist, true)) {
    fail('Access denied — this account is not authorized');
}

// ── Set session ───────────────────────────────────────────────

session_regenerate_id(true);

$_SESSION['admin'] = [
    'email'   => $email,
    'name'    => $profile['name']    ?? $email,
    'picture' => $profile['picture'] ?? '',
    'at'      => time(),
];

header('Location: /admin/dashboard');
exit;
