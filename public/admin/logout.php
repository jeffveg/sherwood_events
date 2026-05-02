<?php
/**
 * Admin logout — /admin/logout.php
 *
 * Clears the session, expires the cookie, rotates the CSRF token,
 * and bounces back to the login page.
 */

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';

auth_logout();
header('Location: /admin/login.php');
exit;
