<?php
/**
 * Session status check — called by dashboard JS to verify session server-side.
 * Returns { authenticated: true, email, name } or { authenticated: false }.
 */

session_start();

require __DIR__ . '/../_cors.php';
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$ttl     = defined('ADMIN_SESSION_TTL') ? ADMIN_SESSION_TTL : 28800;
$session = $_SESSION['admin'] ?? null;

if (
    !$session ||
    empty($session['email']) ||
    (time() - ($session['at'] ?? 0)) > $ttl
) {
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'email'         => $session['email'],
    'name'          => $session['name'],
    'picture'       => $session['picture'],
]);
