<?php
/**
 * StudentHub Portal - Practical 10
 * Session Guard: Secure session management, role-based access control,
 * session timeout, and remember-me token verification.
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);
require_once __DIR__ . '/mysqli_db.php';

// ─── Constants ─────────────────────────────────────────────────────────────
define('SESSION_TIMEOUT_SECONDS', 1800);          // 30 minutes idle timeout
define('REMEMBER_ME_COOKIE',      'sh_remember');  // cookie name
define('REMEMBER_ME_DAYS',        30);             // cookie lifetime
define('LOGIN_URL',               'login.php');

// ─── Secure Session Init ───────────────────────────────────────────────────
/**
 * Starts a secure PHP session with hardened cookie settings.
 * Must be called before any output.
 */
function session_secure_start(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,                             // browser session only (not persistent)
        'path'     => $cookieParams['path'] ?: '/',
        'domain'   => $cookieParams['domain'] ?: '',
        'secure'   => isset($_SERVER['HTTPS']),      // HTTPS-only when on HTTPS
        'httponly' => true,                          // JS cannot access session cookie
        'samesite' => 'Lax',                         // CSRF mitigation
    ]);

    session_start();
}

// ─── Session Timeout ───────────────────────────────────────────────────────
/**
 * Checks for session idle timeout and destroys stale sessions.
 *
 * @return bool false if session was expired and destroyed
 */
function check_session_timeout(): bool {
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }

    $idle = time() - (int)$_SESSION['last_activity'];

    if ($idle > SESSION_TIMEOUT_SECONDS) {
        // Session expired — destroy cleanly
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        return false;
    }

    // Refresh last activity timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

// ─── Authentication Check ──────────────────────────────────────────────────
/**
 * Returns true if a user is currently authenticated (valid session).
 */
function is_authenticated(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Returns the current user's role or null.
 */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Returns the current user's ID or null.
 */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// ─── RBAC Guard ────────────────────────────────────────────────────────────
/**
 * Restricts page access to authenticated users only.
 * Redirects to login page with an optional flash message if not authenticated.
 * Also handles session timeout check.
 *
 * @param string|null $requiredRole  If set ('admin'|'student'), enforces role check too.
 */
function require_login(?string $requiredRole = null): void {
    session_secure_start();

    // Try remember-me token auto-login before checking session
    if (!is_authenticated()) {
        _attempt_remember_me_login();
    }

    if (!is_authenticated()) {
        $_SESSION['flash_error'] = 'You must be logged in to access this page.';
        redirect_to(LOGIN_URL);
    }

    // Timeout check
    if (!check_session_timeout()) {
        $_SESSION['flash_error'] = 'Your session has expired due to inactivity. Please log in again.';
        redirect_to(LOGIN_URL);
    }

    // Role check
    if ($requiredRole !== null && current_role() !== $requiredRole) {
        // Redirect to appropriate dashboard rather than hard 403
        redirect_to_dashboard();
    }
}

/**
 * Redirects to the role-appropriate dashboard.
 */
function redirect_to_dashboard(): void {
    $role = current_role();
    if ($role === 'admin') {
        redirect_to('admin_dashboard.php');
    } else {
        redirect_to('student_dashboard.php');
    }
}

/**
 * Safe redirect helper.
 */
function redirect_to(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ─── Remember-Me (Advanced Extension) ────────────────────────────────────
/**
 * Issues a remember-me cookie and stores its token hash in the DB.
 *
 * @param int    $userId
 * @param string $username
 * @param string $role
 */
function issue_remember_me_token(int $userId, string $username, string $role): void {
    $token     = bin2hex(random_bytes(32)); // 64-char random token
    $tokenHash = hash('sha256', $token);    // store hash, not plaintext
    $expires   = time() + (REMEMBER_ME_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expires);

    // Store hash in DB (advanced extension uses remember_tokens table)
    try {
        // Delete old tokens for this user first
        mysqli_execute_stmt(
            'DELETE FROM remember_tokens WHERE user_id = ?',
            'i', [$userId]
        );

        mysqli_execute_stmt(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            'iss', [$userId, $tokenHash, $expiresAt]
        );

        // Cookie value = userId:plainToken  (split on login to verify)
        $cookieValue = $userId . ':' . $token;
        setcookie(
            REMEMBER_ME_COOKIE,
            $cookieValue,
            [
                'expires'  => $expires,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']),
            ]
        );
    } catch (\Throwable $e) {
        error_log('Remember-me token issue error: ' . $e->getMessage());
    }
}

/**
 * Attempts to log in a user from a valid remember-me cookie.
 * Rotates the token on each use (token rotation prevents replay attacks).
 */
function _attempt_remember_me_login(): void {
    $cookie = $_COOKIE[REMEMBER_ME_COOKIE] ?? '';
    if (!$cookie) return;

    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) { _clear_remember_cookie(); return; }

    [$userId, $plainToken] = $parts;
    $userId    = (int)$userId;
    $tokenHash = hash('sha256', $plainToken);

    try {
        $row = mysqli_fetch_one_row(
            'SELECT rt.token_id, rt.expires_at, u.user_id, u.username, u.role, u.full_name, u.is_active
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.user_id
             WHERE rt.user_id = ? AND rt.token_hash = ?
             LIMIT 1',
            'is', [$userId, $tokenHash]
        );

        if (!$row) { _clear_remember_cookie(); return; }
        if (!$row['is_active']) { _clear_remember_cookie(); return; }
        if (strtotime($row['expires_at']) < time()) {
            // Expired — clean up
            mysqli_execute_stmt('DELETE FROM remember_tokens WHERE user_id = ?', 'i', [$userId]);
            _clear_remember_cookie();
            return;
        }

        // Valid — establish session
        session_regenerate_id(true);
        $_SESSION['user_id']       = $row['user_id'];
        $_SESSION['username']      = $row['username'];
        $_SESSION['role']          = $row['role'];
        $_SESSION['full_name']     = $row['full_name'];
        $_SESSION['last_activity'] = time();
        $_SESSION['auth_via']      = 'remember_me';

        // Rotate token (security: each remember-me use gets a fresh token)
        mysqli_execute_stmt('DELETE FROM remember_tokens WHERE token_id = ?', 'i', [$row['token_id']]);
        issue_remember_me_token($row['user_id'], $row['username'], $row['role']);

    } catch (\Throwable $e) {
        error_log('Remember-me login error: ' . $e->getMessage());
    }
}

/**
 * Clears the remember-me cookie from the browser.
 */
function _clear_remember_cookie(): void {
    setcookie(REMEMBER_ME_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_ME_COOKIE]);
}

// ─── Flash Messages ────────────────────────────────────────────────────────
/**
 * Retrieves and clears a flash message from the session.
 *
 * @param string $key  'flash_error' | 'flash_success' | 'flash_info'
 * @return string|null
 */
function get_flash(string $key): ?string {
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
