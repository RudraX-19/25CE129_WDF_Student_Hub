<?php
/**
 * StudentHub Portal - Practical 10
 * Secure Logout: Destroys session, clears remember-me cookie, redirects to login.
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);
require_once __DIR__ . '/php/session_guard.php';

session_secure_start();

// Clear remember-me cookie & DB token (Advanced Extension)
if (isset($_COOKIE[REMEMBER_ME_COOKIE])) {
    $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE], 2);
    if (count($parts) === 2) {
        $userId = (int)$parts[0];
        if ($userId > 0) {
            try {
                require_once __DIR__ . '/php/mysqli_db.php';
                mysqli_execute_stmt(
                    'DELETE FROM remember_tokens WHERE user_id = ?',
                    'i', [$userId]
                );
            } catch (\Throwable $e) {
                error_log('Logout: failed to delete remember tokens: ' . $e->getMessage());
            }
        }
    }
    _clear_remember_cookie();
}

// Destroy the session completely
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

// Start a fresh session to hold the flash message
session_secure_start();
$_SESSION['flash_info'] = 'You have been logged out successfully. See you soon!';

header('Location: login.php');
exit;
