<?php
declare(strict_types=1);

/**
 * Single-password session auth for the admin area.
 *
 * - Password is bcrypt-hashed in config/config.php (ADMIN_PASSWORD_HASH)
 * - On successful login, session is regenerated and two timestamps are set:
 *     admin_since: absolute login time (for max-session cap)
 *     admin_seen:  last seen time (for idle timeout)
 * - A small brute-force delay kicks in after repeated failures from the
 *   same session (since we have no user rows to lock).
 */

function auth_attempt(string $password): bool
{
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

    // Exponential backoff (max ~8s) after 3rd attempt in same session.
    if ($_SESSION['login_attempts'] > 3) {
        sleep(min(8, (int)pow(2, $_SESSION['login_attempts'] - 3)));
    }

    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        return false;
    }

    // Success → rotate session ID to prevent fixation.
    session_regenerate_id(true);
    $_SESSION['admin']       = true;
    $_SESSION['admin_since'] = time();
    $_SESSION['admin_seen']  = time();
    unset($_SESSION['login_attempts']);
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_check(): bool
{
    if (empty($_SESSION['admin'])) {
        return false;
    }
    $now = time();
    $since = (int)($_SESSION['admin_since'] ?? 0);
    $seen  = (int)($_SESSION['admin_seen']  ?? 0);

    if (($now - $since) > ADMIN_SESSION_MAX_SECONDS
     || ($now - $seen)  > ADMIN_SESSION_IDLE_SECONDS) {
        auth_logout();
        return false;
    }
    $_SESSION['admin_seen'] = $now;
    return true;
}

function auth_require(): void
{
    if (!auth_check()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php');
        exit;
    }
}
