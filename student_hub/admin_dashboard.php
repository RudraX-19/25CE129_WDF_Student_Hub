<?php
/**
 * StudentHub Portal - Practical 10
 * Admin Dashboard — Access-restricted to 'admin' role only.
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);
require_once __DIR__ . '/php/session_guard.php';

// Enforce admin-only access
require_login('admin');

$flash_success = get_flash('flash_success');

$username    = htmlspecialchars($_SESSION['username']  ?? '', ENT_QUOTES, 'UTF-8');
$full_name   = htmlspecialchars($_SESSION['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$login_time  = isset($_SESSION['login_time'])
    ? date('D, d M Y H:i:s', (int)$_SESSION['login_time'])
    : 'Unknown';
$session_id  = session_id();

$idle_since    = (int)($_SESSION['last_activity'] ?? time());
$remaining_sec = max(0, SESSION_TIMEOUT_SECONDS - (time() - $idle_since));
$remaining_min = floor($remaining_sec / 60);
$remaining_s   = $remaining_sec % 60;

// Fetch DB stats for admin overview
$stats = ['users' => 0, 'students' => 0, 'events' => 0, 'registrations' => 0, 'contacts' => 0];
$recent_users = [];
try {
    require_once __DIR__ . '/php/mysqli_db.php';

    foreach (array_keys($stats) as $tbl) {
        $map = [
            'users'         => 'users',
            'students'      => 'students',
            'events'        => 'events',
            'registrations' => 'registrations',
            'contacts'      => 'contact_inquiries',
        ];
        $row = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM `{$map[$tbl]}`");
        $stats[$tbl] = (int)($row['total'] ?? 0);
    }

    $recent_users = mysqli_fetch_all_rows(
        'SELECT user_id, username, email, role, full_name, is_active, created_at, last_login_at
         FROM users
         ORDER BY created_at DESC
         LIMIT 10'
    );
} catch (\Throwable $e) {
    // Non-fatal
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Admin Dashboard (Practical 10)</title>
    <meta name="description" content="Admin dashboard with role-based access control, session security, and system overview.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f0f4f8; }
        .dash-header { background: linear-gradient(135deg, #2c0d1b, #5c1a2e); color: #fff; }
        .dash-nav {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            max-width: 1200px; margin: 0 auto; padding: 14px 24px;
        }
        .dash-brand { font-size: 1.2rem; font-weight: 800; }
        .dash-brand span { color: #ff6b9d; }
        .dash-user { display: flex; align-items: center; gap: 14px; font-size: .85rem; }
        .role-badge {
            background: rgba(255,107,157,.2); border: 1px solid rgba(255,107,157,.4);
            color: #ff6b9d; padding: 3px 10px; border-radius: 14px;
            font-size: .72rem; font-weight: 700; letter-spacing: .04em;
        }
        .logout-btn {
            background: rgba(220,53,69,.8); color: #fff; border: none;
            padding: 6px 16px; border-radius: 6px; font-size: .82rem; font-weight: 700;
            cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .logout-btn:hover { background: #dc3545; }
        .timeout-info {
            background: #1a0810; color: rgba(255,255,255,.65);
            font-size: .72rem; text-align: center; padding: 4px;
        }
        .timeout-bar { height: 4px; background: linear-gradient(90deg, #ff6b9d, #ffc107); }

        .dash-main { max-width: 1200px; margin: 28px auto; padding: 0 24px; }

        .welcome-card {
            background: linear-gradient(135deg, #5c1a2e, #2c0d1b);
            color: #fff; border-radius: 14px; padding: 26px 30px; margin-bottom: 22px;
            box-shadow: 0 6px 24px rgba(92,26,46,.25);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        }
        .welcome-card h2 { margin: 0 0 4px; font-size: 1.45rem; }
        .welcome-card p  { margin: 0; opacity: .85; font-size: .88rem; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px; margin-bottom: 22px;
        }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            display: flex; align-items: center; gap: 12px;
        }
        .stat-icon { font-size: 1.7rem; width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .si-red    { background: #f8d7da; }
        .si-blue   { background: #cce5ff; }
        .si-green  { background: #d4edda; }
        .si-orange { background: #fff3cd; }
        .si-purple { background: #e8d8f8; }
        .stat-label { font-size: .72rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #0d1b2a; }

        .section-card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,.07); margin-bottom: 18px; }
        .section-card h3 { font-size: .95rem; font-weight: 700; color: #0d1b2a; margin: 0 0 14px; padding-bottom: 9px; border-bottom: 2px solid #e9ecef; }

        .users-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .users-table th { background: #f8f9fa; padding: 8px 10px; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-bottom: 2px solid #e9ecef; }
        .users-table td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; color: #333; vertical-align: middle; }
        .users-table tr:last-child td { border-bottom: none; }
        .users-table tr:hover td { background: #f8f9fa; }

        .role-chip {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: .7rem; font-weight: 700;
        }
        .rc-admin   { background: #f8d7da; color: #842029; }
        .rc-student { background: #d1e7dd; color: #0f5132; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .dot-green { background: #198754; }
        .dot-red   { background: #dc3545; }

        .p10-badge { display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;font-size:.68rem;font-weight:700;padding:2px 9px;border-radius:14px;letter-spacing:.05em;text-transform:uppercase; }

        .session-box { background:#f8f9fa;border:1px solid #dee2e6;border-radius:10px;padding:14px;font-size:.82rem; }
        .srow { display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dashed #e9ecef;flex-wrap:wrap;gap:4px; }
        .srow:last-child { border-bottom:none; }
        .sk { color:#6c757d;font-weight:600;font-size:.73rem;text-transform:uppercase;letter-spacing:.04em; }
        .sv { color:#0d1b2a;font-weight:700;font-family:monospace;font-size:.78rem; }
        .sv-green { color:#198754!important; }
        .sv-red   { color:#dc3545!important; }

        .quick-links { display:flex;flex-wrap:wrap;gap:10px; }
        .ql-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:.84rem;font-weight:600;text-decoration:none;transition:transform .15s; }
        .ql-btn:hover { transform:translateY(-1px); }
        .ql-primary { background:#0d6efd;color:#fff; }
        .ql-secondary { background:#6c757d;color:#fff; }
        .ql-danger { background:#dc3545;color:#fff; }

        .flash { padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:18px; }
        .flash-success { background:#d1e7dd;border-left:4px solid #198754;color:#0f5132; }

        @media (max-width:700px) { .dash-main { padding: 0 12px; } }
    </style>
</head>

<body>

    <a href="#dash-main" class="skip-link">Skip to main content</a>

    <header class="dash-header" role="banner">
        <nav class="dash-nav" aria-label="Admin navigation">
            <div class="dash-brand">Student<span>Hub</span> <small style="font-size:.6rem;opacity:.6;font-weight:400;">Admin</small></div>
            <div class="dash-user">
                <span class="role-badge">&#128737; Admin</span>
                <span style="opacity:.8;">&#128100; <?= $username ?></span>
                <a href="logout.php" class="logout-btn" id="admin-logout">&#128274; Logout</a>
            </div>
        </nav>
        <div class="timeout-bar" id="timeout-bar" aria-hidden="true"></div>
        <div class="timeout-info" aria-live="polite">
            &#9201; Admin session expires in <strong id="timeout-countdown"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</strong>
        </div>
    </header>

    <main class="dash-main" id="dash-main" role="main">

        <?php if ($flash_success): ?>
        <div class="flash flash-success" role="alert">&#127881; <?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- Welcome card -->
        <div class="welcome-card">
            <div>
                <h2>&#128737; Admin Control Panel</h2>
                <p>Welcome, <strong><?= $full_name ?></strong>. You have full administrative access.</p>
                <div style="margin-top:8px;font-size:.75rem;opacity:.7;">
                    &#128336; Signed in at <?= $login_time ?> &nbsp;|&nbsp; Session ID: <?= substr($session_id, 0, 10) ?>…
                </div>
            </div>
            <span style="font-size:3.5rem;" aria-hidden="true">&#128737;</span>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-icon si-blue">&#128100;</div><div><div class="stat-label">Users</div><div class="stat-value"><?= $stats['users'] ?></div></div></div>
            <div class="stat-card"><div class="stat-icon si-green">&#127891;</div><div><div class="stat-label">Students</div><div class="stat-value"><?= $stats['students'] ?></div></div></div>
            <div class="stat-card"><div class="stat-icon si-orange">&#127895;</div><div><div class="stat-label">Events</div><div class="stat-value"><?= $stats['events'] ?></div></div></div>
            <div class="stat-card"><div class="stat-icon si-purple">&#128203;</div><div><div class="stat-label">Registrations</div><div class="stat-value"><?= $stats['registrations'] ?></div></div></div>
            <div class="stat-card"><div class="stat-icon si-red">&#128231;</div><div><div class="stat-label">Inquiries</div><div class="stat-value"><?= $stats['contacts'] ?></div></div></div>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;flex-wrap:wrap;">

            <!-- Users table -->
            <div class="section-card">
                <h3>&#128100; Registered Users <span class="p10-badge">RBAC: Admin Only</span></h3>
                <?php if ($recent_users): ?>
                <div style="overflow-x:auto;">
                    <table class="users-table" aria-label="Registered users">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td>#<?= (int)$u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="role-chip <?= $u['role'] === 'admin' ? 'rc-admin' : 'rc-student' ?>"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <span class="status-dot <?= $u['is_active'] ? 'dot-green' : 'dot-red' ?>"></span>
                                    <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                </td>
                                <td><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at'], ENT_QUOTES, 'UTF-8') : '<em style="color:#aaa;">Never</em>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color:#6c757d;font-size:.85rem;">No users found or database not yet seeded.</p>
                <?php endif; ?>
            </div>

            <!-- Session Security -->
            <div>
                <div class="section-card" style="margin-bottom:18px;">
                    <h3>&#128274; Session Security <span class="p10-badge">Practical 10</span></h3>
                    <div class="session-box">
                        <div class="srow"><span class="sk">Role</span><span class="sv" style="color:#dc3545;">admin (RBAC enforced)</span></div>
                        <div class="srow"><span class="sk">Session ID (short)</span><span class="sv sv-blue"><?= substr($session_id, 0, 12) ?>…</span></div>
                        <div class="srow"><span class="sk">ID Regenerated</span><span class="sv sv-green">&#10004; Yes</span></div>
                        <div class="srow"><span class="sk">Timeout</span><span class="sv"><?= SESSION_TIMEOUT_SECONDS / 60 ?> min idle</span></div>
                        <div class="srow"><span class="sk">Remaining</span><span class="sv sv-green" id="sess-remaining"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</span></div>
                        <div class="srow"><span class="sk">HTTPOnly Cookie</span><span class="sv sv-green">&#10004;</span></div>
                        <div class="srow"><span class="sk">password_verify()</span><span class="sv sv-green">&#10004; Used on login</span></div>
                        <div class="srow"><span class="sk">Unauthorized block</span><span class="sv sv-green">&#10004; redirect_to_dashboard()</span></div>
                        <div class="srow"><span class="sk">Destroy on logout</span><span class="sv sv-green">&#10004; session_destroy()</span></div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="section-card">
                    <h3>&#9889; Admin Actions</h3>
                    <div class="quick-links" style="flex-direction:column;">
                        <a href="view_records.php"  class="ql-btn ql-primary">&#128196; View CSV/JSON Records</a>
                        <a href="db_test.php"        class="ql-btn ql-secondary">&#128268; DB Connection Test</a>
                        <a href="register_secure.php" class="ql-btn ql-secondary">&#128274; Add Student (Practical 9)</a>
                        <a href="index.html"          class="ql-btn ql-secondary">&#127968; Portal Home</a>
                        <a href="logout.php"          class="ql-btn ql-danger">&#128274; Logout</a>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <footer style="text-align:center;padding:18px;color:#6c757d;font-size:.78rem;">
        &copy; 2026 StudentHub &mdash; Practical 10: Role-Based Access Control (Admin View)
    </footer>

    <script>
    (function () {
        let rem = <?= $remaining_sec ?>;
        const cd = document.getElementById('timeout-countdown');
        const sr = document.getElementById('sess-remaining');
        setInterval(function () {
            if (rem <= 0) { window.location.href = 'login.php'; return; }
            rem--;
            const m = Math.floor(rem/60), s = String(rem%60).padStart(2,'0');
            const t = `${m}m ${s}s`;
            if (cd) cd.textContent = t;
            if (sr) sr.textContent = t;
        }, 1000);
    })();
    </script>

</body>
</html>
