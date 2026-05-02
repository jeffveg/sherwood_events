<?php
declare(strict_types=1);

/**
 * Single-password session auth for the admin area.
 *
 * - Password is bcrypt-hashed in config/config.php (ADMIN_PASSWORD_HASH)
 * - On successful login the session ID is regenerated (anti-fixation) and
 *   two timestamps are set:
 *     admin_since: absolute login time (for max-session cap)
 *     admin_seen:  last seen time (for idle timeout)
 * - Brute-force protection lives in the DB (login_attempts table), keyed
 *   by HMAC of the client IP. Per-session counters were trivially
 *   bypassable by clearing cookies between attempts.
 */

const LOGIN_FAIL_WINDOW_MIN = 15;   // minutes
const LOGIN_FAIL_LIMIT      = 5;    // failures within window → reject further attempts

function login_recent_failures(string $ipHash): int
{
    $sql = "SELECT COUNT(*) AS c FROM login_attempts
            WHERE ip_hash = :h
              AND succeeded = 0
              AND attempted_at > NOW() - INTERVAL " . (int)LOGIN_FAIL_WINDOW_MIN . " MINUTE";
    $st = db()->prepare($sql);
    $st->execute(['h' => $ipHash]);
    return (int)($st->fetch()['c'] ?? 0);
}

function login_record_attempt(string $ipHash, bool $succeeded): void
{
    $st = db()->prepare("INSERT INTO login_attempts (ip_hash, succeeded) VALUES (:h, :s)");
    $st->execute(['h' => $ipHash, 's' => $succeeded ? 1 : 0]);
}

function auth_attempt(string $password): bool
{
    $ipHash = client_ip_hash();

    // Hard cap: if this IP has too many recent failures, reject without
    // even checking the password. Still record the attempt so a botnet
    // grinding doesn't reset its own clock.
    if (login_recent_failures($ipHash) >= LOGIN_FAIL_LIMIT) {
        login_record_attempt($ipHash, false);
        sleep(2);   // mild friction; doesn't affect legitimate users
        return false;
    }

    $ok = password_verify($password, ADMIN_PASSWORD_HASH);
    login_record_attempt($ipHash, $ok);

    if (!$ok) {
        return false;
    }

    // Success → rotate session ID to prevent fixation.
    session_regenerate_id(true);
    $_SESSION['admin']       = true;
    $_SESSION['admin_since'] = time();
    $_SESSION['admin_seen']  = time();
    return true;
}

function auth_logout(): void
{
    csrf_rotate();   // discard the current CSRF token so it can't be reused
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
        // Sanitize REQUEST_URI before storing it as the post-login redirect
        // target. A crafted URI like "//evil.com/" would otherwise become
        // a protocol-relative redirect after login.
        $req = $_SERVER['REQUEST_URI'] ?? '/admin/';
        if (!is_string($req)
            || $req === ''
            || $req[0] !== '/'
            || (isset($req[1]) && $req[1] === '/')) {
            $req = '/admin/';
        }
        $_SESSION['after_login'] = $req;
        header('Location: /admin/login.php');
        exit;
    }
}
