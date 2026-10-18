<?php
/**
 * StudentHub Portal - Practical 10
 * Secure Login with PHP Sessions, password_verify(), Session ID Regeneration,
 * Role-Based Redirection, Session Timeout, and Remember-Me Token (Advanced).
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';
require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';  // CSRF helpers

session_secure_start();

// If already logged in, go straight to dashboard
if (is_authenticated() && check_session_timeout()) {
    redirect_to_dashboard();
}

// ─── State ────────────────────────────────────────────────────────────────
$csrf_token  = get_csrf_token();
$errors      = [];
$form_user   = '';                          // sticky username on error
$flash_error = get_flash('flash_error');
$flash_info  = get_flash('flash_info');

// ─── POST Handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF Validation
    $submitted_token = trim($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($submitted_token)) {
        http_response_code(403);
        $errors['csrf'] = 'Security token mismatch. Please reload the page and try again.';
    } else {

        // 2. Collect & sanitize inputs
        $username    = trim(strip_tags($_POST['username'] ?? ''));
        $password    = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        $form_user   = $username;

        // 3. Backend validation
        if ($username === '') {
            $errors['username'] = 'Username or email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        // 4. Lookup user if no validation errors
        if (empty($errors)) {
            try {
                // Lookup by username OR email
                $user = mysqli_fetch_one_row(
                    'SELECT user_id, username, email, password_hash, role, full_name, is_active
                     FROM users
                     WHERE (username = ? OR email = ?)
                     LIMIT 1',
                    'ss', [$username, $username]
                );

                if (!$user) {
                    // Deliberate vague message to prevent user enumeration
                    $errors['auth'] = 'Invalid username/email or password.';
                } elseif (!(bool)$user['is_active']) {
                    $errors['auth'] = 'Your account has been deactivated. Please contact support.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    // password_verify() compares plaintext against bcrypt hash
                    $errors['auth'] = 'Invalid username/email or password.';
                } else {
                    // ── 5. Login Success ───────────────────────────────────
                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    $_SESSION['user_id']       = (int)$user['user_id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['login_time']    = time();
                    $_SESSION['auth_via']      = 'password';

                    // Intermediate Extension: Update last_login timestamp
                    try {
                        mysqli_execute_stmt(
                            'UPDATE users SET last_login_at = NOW() WHERE user_id = ?',
                            'i', [(int)$user['user_id']]
                        );
                    } catch (\Throwable $e) {
                        // Non-fatal — log but continue
                        error_log('last_login_at update failed: ' . $e->getMessage());
                    }

                    // Advanced Extension: Issue remember-me token
                    if ($remember_me) {
                        try {
                            issue_remember_me_token(
                                (int)$user['user_id'],
                                $user['username'],
                                $user['role']
                            );
                        } catch (\Throwable $e) {
                            error_log('Remember-me issue failed: ' . $e->getMessage());
                        }
                    }

                    // Refresh CSRF token post-login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // 6. Role-Based Redirection
                    $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!';
                    redirect_to_dashboard();
                }

            } catch (\RuntimeException $e) {
                $errors['db'] = 'A database error occurred. Please try again later.';
                error_log('Login DB error: ' . $e->getMessage());
            }
        }
    } // end CSRF valid
} // end POST

// ─── Helper ───────────────────────────────────────────────────────────────
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Secure Login (Practical 10)</title>
    <meta name="description" content="Secure login with PHP sessions, password_verify(), role-based redirection, session timeout, and remember-me token.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Practical 10 Login Page Styles ─────────────────────────── */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: linear-gradient(135deg, #0d1b2a 0%, #1e3a5f 60%, #0d6efd22 100%);
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            width: 100%;
            max-width: 440px;
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #0d6efd, #198754, #0dcaf0);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand .brand-icon {
            font-size: 2.6rem;
            display: block;
            margin-bottom: 6px;
        }
        .login-brand h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0d1b2a;
            margin: 0 0 4px;
        }
        .login-brand p {
            font-size: .82rem;
            color: #6c757d;
            margin: 0;
        }
        .p10-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-top: 6px;
        }
        /* Security Pills */
        .sec-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 20px;
            justify-content: center;
        }
        .sec-pill {
            font-size: .67rem;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 12px;
        }
        .sp-green  { background:#d4edda; color:#155724; }
        .sp-blue   { background:#cce5ff; color:#004085; }
        .sp-orange { background:#fff3cd; color:#856404; }

        /* Flash Messages */
        .flash {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .flash-error   { background:#fff5f5; border:1.5px solid #f5c2c7; border-left:4px solid #dc3545; color:#842029; }
        .flash-success { background:#d1e7dd; border:1.5px solid #badbcc; border-left:4px solid #198754; color:#0f5132; }
        .flash-info    { background:#cff4fc; border:1.5px solid #b6effb; border-left:4px solid #0dcaf0; color:#055160; }

        /* Form Fields */
        .form-grp {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-bottom: 14px;
        }
        .form-grp label {
            font-size: .82rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-grp input {
            padding: 10px 13px;
            border: 1.5px solid #cdd6e0;
            border-radius: 8px;
            font-size: .92rem;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
            box-sizing: border-box;
        }
        .form-grp input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,.15);
            outline: none;
        }
        .form-grp input.ferr { border-color: #dc3545; }
        .err-msg {
            font-size: .73rem;
            color: #dc3545;
            min-height: 1em;
        }
        /* Password toggle */
        .pass-wrap {
            position: relative;
        }
        .pass-wrap input { padding-right: 44px; }
        .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #6c757d;
            padding: 2px;
            line-height: 1;
        }
        /* Remember me row */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            font-size: .82rem;
        }
        .remember-row label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: #444;
        }
        .remember-row a { color: #0d6efd; text-decoration: none; }
        .remember-row a:hover { text-decoration: underline; }

        /* Submit button */
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .03em;
            transition: transform .15s, box-shadow .15s;
        }
        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(13,110,253,.35);
        }
        .login-btn:active { transform: translateY(0); }

        .login-footer {
            margin-top: 22px;
            text-align: center;
            font-size: .82rem;
            color: #6c757d;
        }
        .login-footer a { color: #0d6efd; font-weight: 600; text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }

        /* Test credentials box */
        .test-creds {
            margin-top: 20px;
            background: #f8f9fa;
            border: 1px dashed #cdd6e0;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: .75rem;
            color: #495057;
        }
        .test-creds strong { display: block; margin-bottom: 4px; color: #343a40; }
        .test-creds code { background: #e9ecef; padding: 1px 5px; border-radius: 3px; }

        /* Session timeout countdown */
        .timeout-bar-wrap {
            height: 3px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 16px;
            overflow: hidden;
        }
        .timeout-bar {
            height: 100%;
            background: linear-gradient(90deg, #198754, #0dcaf0);
            border-radius: 3px;
            width: 100%;
            transition: width 60s linear;
        }
    </style>
</head>

<body style="margin:0;padding:0;background:#0d1b2a;">

    <a href="#login-form" class="skip-link">Skip to login form</a>

    <div class="login-wrap">
        <div class="login-card" role="main">

            <!-- Brand -->
            <div class="login-brand">
                <span class="brand-icon">&#127979;</span>
                <h1>StudentHub</h1>
                <p>Academic & Campus Portal</p>
                <span class="p10-badge">&#128274; Practical 10 &mdash; Secure Session Auth</span>
            </div>

            <!-- Security pills -->
            <div class="sec-pills" role="list" aria-label="Security features">
                <span class="sec-pill sp-green" role="listitem">&#10004; password_verify()</span>
                <span class="sec-pill sp-blue" role="listitem">&#128257; session_regenerate_id()</span>
                <span class="sec-pill sp-orange" role="listitem">&#9201; 30-min Timeout</span>
                <span class="sec-pill sp-green" role="listitem">&#128203; CSRF Token</span>
                <span class="sec-pill sp-blue" role="listitem">&#127381; Role-Based Redirect</span>
                <span class="sec-pill sp-orange" role="listitem">&#128273; Remember-Me</span>
            </div>

            <!-- Flash messages -->
            <?php if ($flash_error): ?>
            <div class="flash flash-error" role="alert" aria-live="polite">
                <span>&#9888;</span>
                <span><?= e($flash_error) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($flash_info): ?>
            <div class="flash flash-info" role="status" aria-live="polite">
                <span>&#8505;</span>
                <span><?= e($flash_info) ?></span>
            </div>
            <?php endif; ?>

            <!-- Error summary -->
            <?php if (!empty($errors)): ?>
            <div class="flash flash-error" role="alert" aria-live="polite">
                <span>&#9888;</span>
                <div>
                    <?php foreach ($errors as $msg): ?>
                        <div><?= e($msg) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form
                id="login-form"
                action="login.php"
                method="POST"
                novalidate
                aria-label="Student Login Form"
            >
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <!-- Username / Email -->
                <div class="form-grp">
                    <label for="l10-username">Username or Email</label>
                    <input
                        type="text"
                        id="l10-username"
                        name="username"
                        value="<?= e($form_user) ?>"
                        required
                        aria-required="true"
                        autocomplete="username"
                        placeholder="Enter username or email"
                        class="<?= isset($errors['username']) || isset($errors['auth']) ? 'ferr' : '' ?>"
                        aria-describedby="l10-username-err"
                    >
                    <span class="err-msg" id="l10-username-err" aria-live="polite">
                        <?= isset($errors['username']) ? e($errors['username']) : '' ?>
                    </span>
                </div>

                <!-- Password -->
                <div class="form-grp">
                    <label for="l10-password">Password</label>
                    <div class="pass-wrap">
                        <input
                            type="password"
                            id="l10-password"
                            name="password"
                            required
                            aria-required="true"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="<?= isset($errors['password']) || isset($errors['auth']) ? 'ferr' : '' ?>"
                            aria-describedby="l10-password-err"
                        >
                        <button
                            type="button"
                            class="toggle-pass"
                            id="l10-toggle-pass"
                            aria-label="Show/hide password"
                            aria-pressed="false"
                        >&#128065;</button>
                    </div>
                    <span class="err-msg" id="l10-password-err" aria-live="polite">
                        <?= isset($errors['password']) ? e($errors['password']) : '' ?>
                    </span>
                </div>

                <!-- Remember Me + Forgot -->
                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember_me" value="1" id="l10-remember">
                        Remember me for <?= REMEMBER_ME_DAYS ?> days
                    </label>
                    <a href="../faq.html">Forgot password?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="login-btn" id="l10-submit-btn">
                    &#128274; Sign In Securely
                </button>
            </form>

            <!-- Footer links -->
            <div class="login-footer">
                <p style="margin:12px 0 4px;">
                    Don't have an account?
                    <a href="register_secure.php">Register here</a>
                </p>
                <p style="margin:0;">
                    <a href="../index.html">&#8592; Back to Home</a>
                    &nbsp;|&nbsp;
                    <a href="register.php">Practical 7 version</a>
                </p>
            </div>

            <!-- Test Credentials (for viva / demonstration) -->
            <div class="test-creds">
                <strong>&#127381; Test Credentials (Practical 10 Viva)</strong>
                <div><strong>Admin:</strong> <code>admin</code> / <code>Admin@123</code> &rarr; Admin Dashboard</div>
                <div><strong>Student:</strong> <code>25ce129</code> / <code>Student@123</code> &rarr; Student Dashboard</div>
                <div style="margin-top:4px;color:#868e96;">Session timeout: <?= SESSION_TIMEOUT_SECONDS / 60 ?> minutes of inactivity</div>
            </div>

        </div>
    </div>

    <script>
    /* ── Practical 10: Frontend validation + password toggle ─────────── */
    (function () {
        'use strict';

        const form    = document.getElementById('login-form');
        const userEl  = document.getElementById('l10-username');
        const passEl  = document.getElementById('l10-password');
        const toggleBtn = document.getElementById('l10-toggle-pass');

        // Password visibility toggle
        if (toggleBtn && passEl) {
            toggleBtn.addEventListener('click', function () {
                const showing = passEl.type === 'text';
                passEl.type = showing ? 'password' : 'text';
                toggleBtn.setAttribute('aria-pressed', String(!showing));
                toggleBtn.textContent = showing ? '👁' : '🙈';
            });
        }

        // Frontend guard before POST
        if (form) {
            form.addEventListener('submit', function (e) {
                let ok = true;

                const uErr = document.getElementById('l10-username-err');
                const pErr = document.getElementById('l10-password-err');

                if (!userEl.value.trim()) {
                    userEl.classList.add('ferr');
                    if (uErr) uErr.textContent = 'Username or email is required.';
                    ok = false;
                } else {
                    userEl.classList.remove('ferr');
                    if (uErr) uErr.textContent = '';
                }

                if (!passEl.value) {
                    passEl.classList.add('ferr');
                    if (pErr) pErr.textContent = 'Password is required.';
                    ok = false;
                } else {
                    passEl.classList.remove('ferr');
                    if (pErr) pErr.textContent = '';
                }

                if (!ok) e.preventDefault();
            });
        }
    })();
    </script>
</body>

</html>
