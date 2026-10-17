<?php
/**
 * StudentHub Portal - Practical 10
 * Student Dashboard — Access-restricted to authenticated students.
 * Demonstrates: session guard, RBAC, timeout display, session data, logout.
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);
require_once __DIR__ . '/php/session_guard.php';

// Require login as 'student' role
require_login('student');

$flash_success = get_flash('flash_success');
$flash_error   = get_flash('flash_error');

// Session info for display
$username    = htmlspecialchars($_SESSION['username']    ?? '', ENT_QUOTES, 'UTF-8');
$full_name   = htmlspecialchars($_SESSION['full_name']   ?? 'Student', ENT_QUOTES, 'UTF-8');
$login_time  = isset($_SESSION['login_time'])
    ? date('D, d M Y H:i:s', (int)$_SESSION['login_time'])
    : 'Unknown';
$auth_via    = htmlspecialchars($_SESSION['auth_via'] ?? 'password', ENT_QUOTES, 'UTF-8');
$session_id  = session_id();
$csrf_token  = get_csrf_token();

// Remaining timeout
$idle_since    = (int)($_SESSION['last_activity'] ?? time());
$elapsed       = time() - $idle_since;
$remaining_sec = max(0, SESSION_TIMEOUT_SECONDS - $elapsed);
$remaining_min = floor($remaining_sec / 60);
$remaining_s   = $remaining_sec % 60;

// Fetch student record from DB (Intermediate: last_login_at)
$student_record = null;
try {
    require_once __DIR__ . '/php/mysqli_db.php';
    $student_record = mysqli_fetch_one_row(
        'SELECT u.username, u.email, u.role, u.last_login_at, u.created_at,
                s.roll_no, s.fullname, s.gpa, s.status,
                c.course_name, c.department,
                s.year_of_study, s.gender
         FROM users u
         LEFT JOIN students s ON u.email = s.email
         LEFT JOIN courses  c ON s.course_id = c.course_id
         WHERE u.user_id = ?
         LIMIT 1',
        'i', [(int)($_SESSION['user_id'] ?? 0)]
    );
} catch (\Throwable $e) {
    // Not fatal — page still renders with session data
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Dashboard (Practical 10)</title>
    <meta name="description" content="Secure student dashboard with session-based access control, timeout display, and role-based redirection.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Dashboard Styles ──────────────────────────────────── */
        body { background: #f0f4f8; }
        .dash-header {
            background: linear-gradient(135deg, #0d1b2a, #1e3a5f);
            color: #fff;
            padding: 0;
        }
        .dash-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 24px;
        }
        .dash-brand { font-size: 1.2rem; font-weight: 800; letter-spacing: -.01em; }
        .dash-brand span { color: #0dcaf0; }
        .dash-user  { display: flex; align-items: center; gap: 14px; font-size: .85rem; }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,.15);
            padding: 3px 10px;
            border-radius: 14px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: #0dcaf0;
            border: 1px solid rgba(13,202,240,.3);
        }
        .logout-btn {
            background: rgba(220,53,69,.8);
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background .2s;
        }
        .logout-btn:hover { background: #dc3545; }

        /* Session timeout ribbon */
        .timeout-ribbon {
            background: linear-gradient(90deg, #198754, #0dcaf0);
            height: 4px;
            width: 100%;
        }
        .timeout-info {
            background: #0d1b2a;
            color: rgba(255,255,255,.7);
            font-size: .72rem;
            text-align: center;
            padding: 4px;
        }

        /* Main content */
        .dash-main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 24px;
        }

        /* Welcome card */
        .welcome-card {
            background: linear-gradient(135deg, #198754, #0f5132);
            color: #fff;
            border-radius: 14px;
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(25,135,84,.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .welcome-card h2 { margin: 0 0 4px; font-size: 1.5rem; }
        .welcome-card p  { margin: 0; opacity: .85; font-size: .9rem; }
        .welcome-meta { font-size: .78rem; opacity: .75; margin-top: 8px; }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            font-size: 1.8rem;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .si-blue   { background: #cce5ff; }
        .si-green  { background: #d4edda; }
        .si-orange { background: #fff3cd; }
        .si-purple { background: #e8d8f8; }
        .stat-label { font-size: .75rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .stat-value { font-size: 1.35rem; font-weight: 800; color: #0d1b2a; }

        /* Section card */
        .section-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }
        .section-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #0d1b2a;
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .info-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .info-table tr td:first-child {
            font-weight: 600;
            color: #6c757d;
            width: 42%;
            padding: 7px 0;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .info-table tr td:last-child { color: #0d1b2a; font-weight: 500; }

        /* Session security box */
        .session-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 16px;
            font-size: .82rem;
        }
        .session-box .srow {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed #e9ecef;
            flex-wrap: wrap;
            gap: 4px;
        }
        .session-box .srow:last-child { border-bottom: none; }
        .session-box .sk { color: #6c757d; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        .session-box .sv { color: #0d1b2a; font-weight: 700; font-family: monospace; font-size: .8rem; }
        .sv-green { color: #198754 !important; }
        .sv-red   { color: #dc3545 !important; }
        .sv-blue  { color: #0d6efd !important; }

        .p10-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: #fff; font-size: .68rem; font-weight: 700;
            padding: 2px 9px; border-radius: 14px; letter-spacing: .05em; text-transform: uppercase;
        }

        /* Flash */
        .flash { padding: 12px 16px; border-radius: 8px; font-size: .85rem; margin-bottom: 18px; }
        .flash-success { background:#d1e7dd; border-left:4px solid #198754; color:#0f5132; }
        .flash-error   { background:#fff5f5; border-left:4px solid #dc3545; color:#842029; }

        /* Quick links */
        .quick-links { display: flex; flex-wrap: wrap; gap: 10px; }
        .ql-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            border-radius: 8px; font-size: .84rem; font-weight: 600;
            text-decoration: none; transition: transform .15s, box-shadow .15s;
        }
        .ql-btn:hover { transform: translateY(-1px); box-shadow: 0 3px 12px rgba(0,0,0,.15); }
        .ql-primary  { background: #0d6efd; color: #fff; }
        .ql-secondary { background: #6c757d; color: #fff; }
        .ql-success  { background: #198754; color: #fff; }
        .ql-danger   { background: #dc3545; color: #fff; }

        @media (max-width: 600px) {
            .dash-main { padding: 0 12px; }
            .welcome-card { padding: 20px; }
        }
    </style>
</head>

<body>

    <a href="#dash-main" class="skip-link">Skip to main content</a>

    <!-- ── Dashboard Header / Nav ──────────────────────────────────── -->
    <header class="dash-header" role="banner">
        <nav class="dash-nav" aria-label="Dashboard navigation">
            <div class="dash-brand">Student<span>Hub</span> <small style="font-size:.65rem;opacity:.7;font-weight:400;">Portal</small></div>
            <div class="dash-user">
                <span class="role-badge">&#127891; Student</span>
                <span style="opacity:.8;">&#128100; <?= $username ?></span>
                <a href="logout.php" class="logout-btn" id="logout-btn">&#128274; Logout</a>
            </div>
        </nav>
        <div class="timeout-ribbon" id="timeout-bar" aria-hidden="true"></div>
        <div class="timeout-info" aria-live="polite" id="timeout-text">
            &#9201; Session expires in <strong id="timeout-countdown"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</strong>
            &nbsp;|&nbsp; <a href="login.php" style="color:#0dcaf0;">Refresh session</a>
        </div>
    </header>

    <!-- ── Main ────────────────────────────────────────────────────── -->
    <main class="dash-main" id="dash-main" role="main">

        <?php if ($flash_success): ?>
        <div class="flash flash-success" role="alert" aria-live="polite">
            &#127881; <?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
        <div class="flash flash-error" role="alert" aria-live="polite">
            &#9888; <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <div>
                <h2>&#128075; Welcome back, <?= $full_name ?>!</h2>
                <p>You are logged in as a <strong>Student</strong>. Your session is active and secure.</p>
                <div class="welcome-meta">
                    &#128336; Signed in at <?= $login_time ?> &nbsp;|&nbsp; Auth method: <?= $auth_via ?>
                    <?php if ($student_record && $student_record['last_login_at']): ?>
                        &nbsp;|&nbsp; &#128336; Last login: <?= htmlspecialchars($student_record['last_login_at'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
            <span style="font-size:3.5rem;" aria-hidden="true">&#127891;</span>
        </div>

        <!-- Stats Row -->
        <?php if ($student_record): ?>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon si-blue">&#127891;</div>
                <div>
                    <div class="stat-label">Course</div>
                    <div class="stat-value" style="font-size:1rem;"><?= htmlspecialchars($student_record['course_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-green">&#127942;</div>
                <div>
                    <div class="stat-label">GPA</div>
                    <div class="stat-value"><?= htmlspecialchars($student_record['gpa'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-orange">&#128197;</div>
                <div>
                    <div class="stat-label">Year</div>
                    <div class="stat-value">Year <?= htmlspecialchars((string)($student_record['year_of_study'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-purple">&#9989;</div>
                <div>
                    <div class="stat-label">Status</div>
                    <div class="stat-value" style="font-size:1rem;"><?= htmlspecialchars($student_record['status'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">

            <!-- Profile Info -->
            <div class="section-card">
                <h3>&#128100; My Profile</h3>
                <table class="info-table">
                    <tr><td>Username</td><td><?= $username ?></td></tr>
                    <tr><td>Full Name</td><td><?= $full_name ?></td></tr>
                    <tr><td>Email</td><td><?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php if ($student_record): ?>
                    <tr><td>Roll No</td><td><?= htmlspecialchars($student_record['roll_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td>Department</td><td><?= htmlspecialchars($student_record['department'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td>Gender</td><td><?= htmlspecialchars($student_record['gender'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Role</td><td><?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
                </table>
            </div>

            <!-- Session Security Info (Practical 10 key feature display) -->
            <div class="section-card">
                <h3>&#128274; Session Security <span class="p10-badge">Practical 10</span></h3>
                <div class="session-box">
                    <div class="srow">
                        <span class="sk">Session ID (truncated)</span>
                        <span class="sv sv-blue"><?= substr($session_id, 0, 16) ?>…</span>
                    </div>
                    <div class="srow">
                        <span class="sk">ID Regenerated on Login</span>
                        <span class="sv sv-green">&#10004; Yes (session_regenerate_id)</span>
                    </div>
                    <div class="srow">
                        <span class="sk">Timeout Policy</span>
                        <span class="sv"><?= SESSION_TIMEOUT_SECONDS / 60 ?> minutes idle</span>
                    </div>
                    <div class="srow">
                        <span class="sk">Time Remaining</span>
                        <span class="sv sv-green" id="sess-remaining"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</span>
                    </div>
                    <div class="srow">
                        <span class="sk">Remember-Me Active</span>
                        <span class="sv <?= isset($_COOKIE[REMEMBER_ME_COOKIE]) ? 'sv-green' : 'sv-red' ?>">
                            <?= isset($_COOKIE[REMEMBER_ME_COOKIE]) ? '&#10004; Yes' : '&#10060; No' ?>
                        </span>
                    </div>
                    <div class="srow">
                        <span class="sk">Auth Method</span>
                        <span class="sv"><?= $auth_via ?></span>
                    </div>
                    <div class="srow">
                        <span class="sk">Password Verified</span>
                        <span class="sv sv-green">password_verify() &#10004;</span>
                    </div>
                    <div class="srow">
                        <span class="sk">Cookie HTTPOnly</span>
                        <span class="sv sv-green">&#10004; Enabled</span>
                    </div>
                    <div class="srow">
                        <span class="sk">CSRF Protection</span>
                        <span class="sv sv-green">&#10004; Active</span>
                    </div>
                    <?php if ($student_record && $student_record['last_login_at']): ?>
                    <div class="srow">
                        <span class="sk">Last Login (Intermediate)</span>
                        <span class="sv"><?= htmlspecialchars($student_record['last_login_at'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <h3>&#9889; Quick Actions</h3>
            <div class="quick-links">
                <a href="index.html"   class="ql-btn ql-primary">&#127968; Home</a>
                <a href="students.html" class="ql-btn ql-secondary">&#128218; Students</a>
                <a href="events.html"  class="ql-btn ql-success">&#127895; Events</a>
                <a href="courses.html" class="ql-btn ql-secondary">&#128218; Courses</a>
                <a href="grades.html"  class="ql-btn ql-secondary">&#127942; Grades</a>
                <a href="register_secure.php" class="ql-btn ql-secondary">&#128100; Update Profile</a>
                <a href="logout.php"   class="ql-btn ql-danger" id="dash-logout">&#128274; Logout</a>
            </div>
        </div>

    </main>

    <footer style="text-align:center;padding:20px;color:#6c757d;font-size:.78rem;">
        &copy; 2026 StudentHub &mdash; Practical 10: Secure Session Authentication
    </footer>

    <script>
    /* ── Session Countdown Timer ────────────────────────────────── */
    (function () {
        let remaining = <?= $remaining_sec ?>;
        const countdown = document.getElementById('timeout-countdown');
        const sessRemaining = document.getElementById('sess-remaining');
        const bar = document.getElementById('timeout-bar');
        const maxWidth = 100;

        function update() {
            if (remaining <= 0) {
                if (countdown) countdown.textContent = 'Expired — redirecting…';
                window.location.href = 'login.php';
                return;
            }
            remaining--;
            const m = Math.floor(remaining / 60);
            const s = String(remaining % 60).padStart(2, '0');
            const text = `${m}m ${s}s`;
            if (countdown)    countdown.textContent = text;
            if (sessRemaining) sessRemaining.textContent = text;

            // Shrink bar as time runs out
            const pct = (remaining / <?= SESSION_TIMEOUT_SECONDS ?>) * maxWidth;
            if (bar) {
                const hue = Math.round(pct * 1.2); // green → red
                bar.style.background = `linear-gradient(90deg, hsl(${hue},70%,40%), hsl(${Math.min(hue+30, 200)},70%,50%))`;
                bar.style.width = pct + '%';
            }

            // Warn when <5 minutes remain
            if (remaining === 300 || remaining === 60) {
                const t = remaining >= 60 ? `${Math.floor(remaining/60)} minute(s)` : `${remaining} seconds`;
                if (window.Notification && Notification.permission === 'granted') {
                    new Notification('StudentHub Session', { body: `Session expires in ${t}. Save your work!` });
                }
            }
        }

        setInterval(update, 1000);
        update();
    })();
    </script>

</body>
</html>
