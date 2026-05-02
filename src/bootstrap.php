<?php
/**
 * bootstrap.php — loaded first by every PHP entry point under public/.
 *
 * Responsibilities:
 *   1. Load config/config.php (DB creds, secrets, paths). Hard-fails
 *      with a 500 if the file is missing — that's intentional, it's
 *      the loudest possible signal that the deployment is incomplete.
 *   2. Set the PHP default timezone to TIMEZONE (America/Phoenix).
 *      Note: this matters for PHP date/time formatting; the MySQL
 *      session timezone is set separately in src/db.php.
 *   3. Configure error visibility — display_errors only on localhost,
 *      log_errors always. (Production errors land in Apache's PHP log;
 *      IONOS exposes those in the panel.)
 *   4. Configure secure session cookies (HttpOnly + Secure + SameSite=Lax)
 *      and start the session if not already started.
 *   5. Pull in helpers.php and db.php so they're available everywhere.
 *
 * If you add a new entry point under public/, the first two lines
 * should always be:
 *
 *     require_once __DIR__ . '/../src/bootstrap.php';
 *     require_once __DIR__ . '/../src/<whatever-else-you-need>.php';
 */

declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Configuration missing. Copy config/config.example.php to config/config.php and fill it in.');
}
require_once $configPath;

date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'America/Phoenix');

// Error visibility: on during dev (localhost), off in production
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');

// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('sw_events');
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
