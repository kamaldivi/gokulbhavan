<?php
/**
 * Logout — destroys the admin session and redirects to /admin.
 * GET /api/auth/logout.php
 */

session_start();
session_destroy();
header('Location: /admin');
exit;
