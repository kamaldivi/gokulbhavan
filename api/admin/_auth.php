<?php
/**
 * JSON auth guard for admin API endpoints.
 * Include at the top of any admin API file.
 * Returns 401 JSON (not a redirect) so fetch() callers can handle it.
 * Sets $adminUser on success.
 */

session_start();
require_once __DIR__ . '/../../api/config.php';

$ttl     = defined('ADMIN_SESSION_TTL') ? ADMIN_SESSION_TTL : 28800;
$session = $_SESSION['admin'] ?? null;

if (
    !$session ||
    empty($session['email']) ||
    (time() - ($session['at'] ?? 0)) > $ttl
) {
    http_response_code(401);
    echo json_encode(['message' => 'Not authenticated']);
    exit;
}

$adminUser = $session;
