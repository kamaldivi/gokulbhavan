<?php
/**
 * Session check helper — include at the top of any protected admin PHP page.
 *
 * Usage:
 *   require __DIR__ . '/../../api/auth/session-check.php';
 *
 * If session is valid:  sets $adminUser array and continues execution.
 * If session is absent: redirects to /admin and exits.
 * Session expires after ADMIN_SESSION_TTL seconds (default 8 hours).
 */

session_start();

require_once __DIR__ . '/../config.php';

$ttl = defined('ADMIN_SESSION_TTL') ? ADMIN_SESSION_TTL : 28800; // 8 hours

$session = $_SESSION['admin'] ?? null;

if (
    !$session ||
    empty($session['email']) ||
    (time() - ($session['at'] ?? 0)) > $ttl
) {
    session_destroy();
    header('Location: /admin?error=session_expired');
    exit;
}

// Expose to the including script
$adminUser = $session;
