<?php
/**
 * StudentHub Portal — Practical 14
 * Admin Dashboard: Role-Based Access Control, Dynamic Menu, Audit Log
 * Course: Web Development Fundamentals (ITUE203)
 *
 * Key requirements (Practical 14):
 *  1. Dashboard values loaded dynamically from DB (users, students, events, registrations)
 *  2. Access restricted to admin users only (RBAC via session_guard.php)
 *  3. Role-based dynamic menu — menu items rendered based on session role
 *  4. Important admin actions recorded in audit_log table
 *
 * Extensions:
 *  Intermediate: Active/Inactive user status toggle (AJAX)
 *  Advanced:     Chart.js analytics chart — event registrations by month
 */

define('STUDENTHUB_ACCESS', true);
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';

// ── 1. RBAC: Admin-only gate ──────────────────────────────────────────────
require_login('admin');

// ── 2. Flash messages ─────────────────────────────────────────────────────
$flash_success = get_flash('flash_success');
$flash_error   = get_flash('flash_error');

// ── 3. Session meta ───────────────────────────────────────────────────────
$admin_username = htmlspecialchars($_SESSION['username']  ?? '',      ENT_QUOTES, 'UTF-8');
$admin_name     = htmlspecialchars($_SESSION['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$admin_role     = $_SESSION['role'] ?? 'admin';
$login_time     = isset($_SESSION['login_time'])
    ? date('D, d M Y H:i:s', (int)$_SESSION['login_time']) : 'Unknown';
$session_id     = session_id();

$idle_since     = (int)($_SESSION['last_activity'] ?? time());
$remaining_sec  = max(0, SESSION_TIMEOUT_SECONDS - (time() - $idle_since));
$remaining_min  = floor($remaining_sec / 60);
$remaining_s    = $remaining_sec % 60;

// ── 4. DB: Dynamic Stats + Recent Users + Audit Log + Chart Data ──────────
$stats = [
    'users'         => 0,
    'students'      => 0,
    'events'        => 0,
    'registrations' => 0,
    'contacts'      => 0,
    'audit_entries' => 0,
];
$recent_users   = [];
$audit_log_rows = [];
$chart_labels   = [];
$chart_data     = [];

try {
    require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';

    // 4a. Aggregate counts
    $count_map = [
        'users'         => 'users',
        'students'      => 'students',
        'events'        => 'events',
        'registrations' => 'registrations',
        'contacts'      => 'contact_inquiries',
        'audit_entries' => 'audit_log',
    ];
    foreach ($count_map as $key => $tbl) {
        $row = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM `{$tbl}`");
        $stats[$key] = (int)($row['total'] ?? 0);
    }

    // 4b. Latest 10 registered users
    $recent_users = mysqli_fetch_all_rows(
        'SELECT user_id, username, email, role, full_name, is_active, created_at, last_login_at
         FROM users
         ORDER BY created_at DESC
         LIMIT 10'
    );

    // 4c. Latest 15 audit log entries
    $audit_log_rows = mysqli_fetch_all_rows(
        'SELECT log_id, action, table_name, record_id, performed_by, details, ip_address, created_at
         FROM audit_log
         ORDER BY created_at DESC
         LIMIT 15'
    );

    // 4d. Advanced Extension — Registrations by month (last 6 months) for Chart.js
    $chart_rows = mysqli_fetch_all_rows(
        "SELECT DATE_FORMAT(registration_date, '%b %Y') AS month_label,
                DATE_FORMAT(registration_date, '%Y-%m') AS month_key,
                COUNT(*) AS reg_count
         FROM registrations
         WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month_key, month_label
         ORDER BY month_key ASC"
    );
    foreach ($chart_rows as $r) {
        $chart_labels[] = $r['month_label'];
        $chart_data[]   = (int)$r['reg_count'];
    }
    // Ensure at least placeholder data if empty
    if (empty($chart_labels)) {
        $chart_labels = ['No Data'];
        $chart_data   = [0];
    }

    // 4e. Log this admin page view into audit_log (Practical 14 requirement)
    mysqli_execute_stmt(
        "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
         VALUES ('VIEW_DASHBOARD', 'admin_dashboard', 0, ?, 'Admin viewed dashboard', ?)",
        'ss',
        [
            $admin_username,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]
    );

} catch (\Throwable $e) {
    error_log('Practical 14 admin_dashboard error: ' . $e->getMessage());
}

// ── 5. Role-based dynamic menu config ────────────────────────────────────
//  Each item: [label, href, icon, roles_allowed, highlight_color]
$all_menu_items = [
    ['label' => 'Manage Students',  'href' => 'students_crud.php',   'icon' => '👥', 'roles' => ['admin'],   'color' => '#0284c7'],
    ['label' => 'Manage Events',    'href' => 'events_crud.php',     'icon' => '🎪', 'roles' => ['admin'],   'color' => '#6366f1'],
    ['label' => 'AJAX Event Hub',   'href' => '../ajax_events.html', 'icon' => '⚡', 'roles' => ['admin'],   'color' => '#059669'],
    ['label' => 'View Records',     'href' => 'view_records.php',    'icon' => '📄', 'roles' => ['admin'],   'color' => '#0d6efd'],
    ['label' => 'DB Connection',    'href' => 'db_test.php',         'icon' => '🔌', 'roles' => ['admin'],   'color' => '#6c757d'],
    ['label' => 'Add Student',      'href' => 'register_secure.php', 'icon' => '🔐', 'roles' => ['admin'],   'color' => '#6c757d'],
    ['label' => 'Student Dashboard','href' => 'student_dashboard.php','icon'=> '🎓', 'roles' => ['student'], 'color' => '#198754'],
    ['label' => 'Portal Home',      'href' => '../index.html',       'icon' => '🏠', 'roles' => ['admin', 'student'], 'color' => '#6c757d'],
    ['label' => 'Logout',           'href' => 'logout.php',          'icon' => '🔒', 'roles' => ['admin', 'student'], 'color' => '#dc3545', 'danger' => true],
];
// Filter menu items to show only those allowed for current role
$dynamic_menu = array_filter($all_menu_items, fn($item) => in_array($admin_role, $item['roles'], true));

// Encode chart data as JSON for JS
$chart_labels_json = json_encode($chart_labels);
$chart_data_json   = json_encode($chart_data);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub — Admin Dashboard (Practical 14)</title>
    <meta name="description" content="Admin dashboard with role-based access control, dynamic menu, live statistics, audit log, and analytics chart.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Chart.js CDN (Advanced Extension) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <style>
        /* ── Base ───────────────────────────────────────────────── */
        :root {
            --brand-dark:   #1e0a18;
            --brand-mid:    #5c1a2e;
            --brand-light:  #ff6b9d;
            --bg:           #f0f4f8;
            --card:         #ffffff;
            --radius:       12px;
            --shadow:       0 2px 14px rgba(0,0,0,.08);
        }
        body        { background: var(--bg); font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; }

        /* ── Header ─────────────────────────────────────────────── */
        .dash-header { background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid)); color: #fff; }
        .dash-nav {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            max-width: 1280px; margin: 0 auto; padding: 14px 24px;
        }
        .dash-brand      { font-size: 1.2rem; font-weight: 800; }
        .dash-brand span { color: var(--brand-light); }
        .dash-user       { display: flex; align-items: center; gap: 14px; font-size: .85rem; }
        .role-badge {
            background: rgba(255,107,157,.2); border: 1px solid rgba(255,107,157,.4);
            color: var(--brand-light); padding: 3px 10px; border-radius: 14px;
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
        .timeout-bar { height: 4px; background: linear-gradient(90deg, var(--brand-light), #ffc107); }

        /* ── Layout ─────────────────────────────────────────────── */
        .dash-main { max-width: 1280px; margin: 28px auto; padding: 0 24px; }

        /* ── Flash ──────────────────────────────────────────────── */
        .flash { padding: 12px 16px; border-radius: 8px; font-size: .85rem; margin-bottom: 18px; }
        .flash-success { background: #d1e7dd; border-left: 4px solid #198754; color: #0f5132; }
        .flash-error   { background: #f8d7da; border-left: 4px solid #dc3545; color: #842029; }

        /* ── Welcome banner ─────────────────────────────────────── */
        .welcome-card {
            background: linear-gradient(135deg, var(--brand-mid), var(--brand-dark));
            color: #fff; border-radius: var(--radius); padding: 26px 30px; margin-bottom: 22px;
            box-shadow: 0 6px 24px rgba(92,26,46,.25);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        }
        .welcome-card h2 { margin: 0 0 4px; font-size: 1.45rem; }
        .welcome-card p  { margin: 0; opacity: .85; font-size: .88rem; }
        .p14-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            color: #fff; font-size: .68rem; font-weight: 700;
            padding: 3px 10px; border-radius: 14px; letter-spacing: .05em; text-transform: uppercase;
        }

        /* ── Stat cards ─────────────────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px; margin-bottom: 22px;
        }
        .stat-card {
            background: var(--card); border-radius: var(--radius); padding: 18px 20px;
            box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 12px;
            transition: transform .15s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { font-size: 1.7rem; width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .si-red    { background: #f8d7da; }
        .si-blue   { background: #cce5ff; }
        .si-green  { background: #d4edda; }
        .si-orange { background: #fff3cd; }
        .si-purple { background: #e8d8f8; }
        .si-teal   { background: #d1ecf1; }
        .stat-label { font-size: .72rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #0d1b2a; }

        /* ── Section card ───────────────────────────────────────── */
        .section-card { background: var(--card); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow); margin-bottom: 18px; }
        .section-card h3 { font-size: .95rem; font-weight: 700; color: #0d1b2a; margin: 0 0 14px; padding-bottom: 9px; border-bottom: 2px solid #e9ecef; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        /* ── Two-column layout ──────────────────────────────────── */
        .grid-2-1 { display: grid; grid-template-columns: 2fr 1fr; gap: 18px; }
        .grid-1-1 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media (max-width: 900px) {
            .grid-2-1, .grid-1-1 { grid-template-columns: 1fr; }
        }

        /* ── Users table ────────────────────────────────────────── */
        .users-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .users-table th {
            background: #f8f9fa; padding: 8px 10px; text-align: left;
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: #6c757d; border-bottom: 2px solid #e9ecef;
        }
        .users-table td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; color: #333; vertical-align: middle; }
        .users-table tr:last-child td { border-bottom: none; }
        .users-table tr:hover td { background: #f8f9fa; }

        /* ── Chips / badges ─────────────────────────────────────── */
        .role-chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .7rem; font-weight: 700; }
        .rc-admin   { background: #f8d7da; color: #842029; }
        .rc-student { background: #d1e7dd; color: #0f5132; }

        /* ── Status toggle (Intermediate extension) ─────────────── */
        .toggle-btn {
            background: none; border: 1.5px solid currentColor; border-radius: 20px;
            padding: 3px 10px; font-size: .72rem; font-weight: 700; cursor: pointer;
            transition: all .18s; display: inline-flex; align-items: center; gap: 4px;
        }
        .toggle-btn.active   { color: #198754; border-color: #198754; }
        .toggle-btn.active:hover  { background: #198754; color: #fff; }
        .toggle-btn.inactive { color: #dc3545; border-color: #dc3545; }
        .toggle-btn.inactive:hover { background: #dc3545; color: #fff; }
        .toggle-spinner { display: none; width: 12px; height: 12px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Audit log table ────────────────────────────────────── */
        .audit-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
        .audit-table th {
            background: #f8f9fa; padding: 7px 9px; text-align: left;
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: #6c757d; border-bottom: 2px solid #e9ecef;
        }
        .audit-table td { padding: 7px 9px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .audit-table tr:last-child td { border-bottom: none; }
        .audit-action {
            display: inline-block; padding: 1px 7px; border-radius: 8px;
            font-size: .67rem; font-weight: 700;
        }
        .aa-view      { background: #d1ecf1; color: #0c5460; }
        .aa-create    { background: #d4edda; color: #155724; }
        .aa-update    { background: #fff3cd; color: #856404; }
        .aa-delete    { background: #f8d7da; color: #721c24; }
        .aa-toggle    { background: #e2d9f3; color: #4a235a; }
        .aa-login     { background: #cce5ff; color: #004085; }

        /* ── Dynamic menu ───────────────────────────────────────── */
        .dynamic-menu { display: flex; flex-wrap: wrap; gap: 10px; }
        .menu-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border-radius: 8px; font-size: .84rem;
            font-weight: 600; text-decoration: none; transition: all .15s;
            color: #fff;
        }
        .menu-btn:hover { opacity: .88; transform: translateY(-1px); }
        .menu-btn.danger { background: #dc3545 !important; }

        /* ── Chart canvas ───────────────────────────────────────── */
        .chart-wrap { position: relative; height: 260px; }

        /* ── Session info box ───────────────────────────────────── */
        .session-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; padding: 14px; font-size: .82rem; }
        .srow { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #e9ecef; flex-wrap: wrap; gap: 4px; }
        .srow:last-child { border-bottom: none; }
        .sk { color: #6c757d; font-weight: 600; font-size: .73rem; text-transform: uppercase; letter-spacing: .04em; }
        .sv { color: #0d1b2a; font-weight: 700; font-family: monospace; font-size: .78rem; }
        .sv-green { color: #198754 !important; }
        .sv-red   { color: #dc3545 !important; }

        /* ── Toast notification ─────────────────────────────────── */
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast {
            background: #0d1b2a; color: #fff; padding: 12px 18px; border-radius: 10px;
            font-size: .84rem; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,.2);
            opacity: 0; transform: translateX(20px); transition: all .3s;
            display: flex; align-items: center; gap: 8px;
        }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast.success { border-left: 4px solid #198754; }
        .toast.error   { border-left: 4px solid #dc3545; }

        @media (max-width: 700px) { .dash-main { padding: 0 12px; } }
    </style>
</head>

<body>

<a href="#dash-main" class="skip-link">Skip to main content</a>

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<header class="dash-header" role="banner">
    <nav class="dash-nav" aria-label="Admin navigation">
        <div class="dash-brand">Student<span>Hub</span> <small style="font-size:.6rem;opacity:.6;font-weight:400;">Admin</small></div>
        <div class="dash-user">
            <span class="role-badge">🛡 <?= htmlspecialchars(ucfirst($admin_role), ENT_QUOTES, 'UTF-8') ?></span>
            <span style="opacity:.8;">👤 <?= $admin_username ?></span>
            <a href="logout.php" class="logout-btn" id="admin-logout">🔒 Logout</a>
        </div>
    </nav>
    <div class="timeout-bar" id="timeout-bar" aria-hidden="true"></div>
    <div class="timeout-info" aria-live="polite">
        ⏱ Admin session expires in <strong id="timeout-countdown"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</strong>
    </div>
</header>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<main class="dash-main" id="dash-main" role="main">

    <?php if ($flash_success): ?>
    <div class="flash flash-success" role="alert">🎉 <?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="flash flash-error" role="alert">⚠️ <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- ── Welcome ─────────────────────────────────────────────────────── -->
    <div class="welcome-card">
        <div>
            <h2>🛡 Admin Control Panel
                <span class="p14-badge" style="margin-left:8px;">Practical 14</span>
            </h2>
            <p>Welcome, <strong><?= $admin_name ?></strong>. You have full administrative access.</p>
            <div style="margin-top:8px;font-size:.75rem;opacity:.7;">
                🕐 Signed in at <?= $login_time ?> &nbsp;|&nbsp; Session: <?= substr($session_id, 0, 10) ?>…
            </div>
        </div>
        <span style="font-size:3.5rem;" aria-hidden="true">🛡</span>
    </div>

    <!-- ── 1. Dynamic Stats (Requirement 1) ────────────────────────────── -->
    <div class="stats-row" aria-label="Dashboard statistics">
        <div class="stat-card">
            <div class="stat-icon si-blue">👤</div>
            <div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value" id="stat-users"><?= $stats['users'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green">🎓</div>
            <div>
                <div class="stat-label">Students</div>
                <div class="stat-value" id="stat-students"><?= $stats['students'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-orange">🎪</div>
            <div>
                <div class="stat-label">Events</div>
                <div class="stat-value" id="stat-events"><?= $stats['events'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-purple">📋</div>
            <div>
                <div class="stat-label">Registrations</div>
                <div class="stat-value" id="stat-regs"><?= $stats['registrations'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red">📬</div>
            <div>
                <div class="stat-label">Inquiries</div>
                <div class="stat-value" id="stat-contacts"><?= $stats['contacts'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-teal">📝</div>
            <div>
                <div class="stat-label">Audit Entries</div>
                <div class="stat-value" id="stat-audit"><?= $stats['audit_entries'] ?></div>
            </div>
        </div>
    </div>

    <!-- ── Main grid: Users table + Sidebar ────────────────────────────── -->
    <div class="grid-2-1">

        <!-- Users table (Requirement 2 + Intermediate status toggle) -->
        <div class="section-card">
            <h3>👥 Registered Users
                <span class="p14-badge">RBAC: Admin Only</span>
            </h3>
            <?php if ($recent_users): ?>
            <div style="overflow-x:auto;">
                <table class="users-table" aria-label="Registered users" id="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr id="user-row-<?= (int)$u['user_id'] ?>">
                            <td>#<?= (int)$u['user_id'] ?></td>
                            <td><?= htmlspecialchars($u['username'],  ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="font-size:.78rem;"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="role-chip <?= $u['role'] === 'admin' ? 'rc-admin' : 'rc-student' ?>"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td id="status-cell-<?= (int)$u['user_id'] ?>">
                                <span id="status-text-<?= (int)$u['user_id'] ?>" style="font-size:.78rem;font-weight:700;color:<?= $u['is_active'] ? '#198754' : '#dc3545' ?>">
                                    <?= $u['is_active'] ? '● Active' : '● Inactive' ?>
                                </span>
                            </td>
                            <td style="font-size:.75rem;"><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at'], ENT_QUOTES, 'UTF-8') : '<em style="color:#aaa;">Never</em>' ?></td>
                            <td>
                                <!-- Intermediate Extension: Status Toggle Button -->
                                <button
                                    class="toggle-btn <?= $u['is_active'] ? 'active' : 'inactive' ?>"
                                    id="toggle-btn-<?= (int)$u['user_id'] ?>"
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-current="<?= (int)$u['is_active'] ?>"
                                    onclick="toggleUserStatus(this)"
                                    aria-label="Toggle status for <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <span class="toggle-spinner" id="spinner-<?= (int)$u['user_id'] ?>"></span>
                                    <span id="toggle-label-<?= (int)$u['user_id'] ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:#6c757d;font-size:.85rem;">No users found or database not yet seeded.</p>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Session Security -->
            <div class="section-card" style="margin-bottom:18px;">
                <h3>🔒 Session Security</h3>
                <div class="session-box">
                    <div class="srow"><span class="sk">Role</span><span class="sv sv-red">admin (RBAC enforced)</span></div>
                    <div class="srow"><span class="sk">Session ID</span><span class="sv"><?= substr($session_id, 0, 12) ?>…</span></div>
                    <div class="srow"><span class="sk">ID Regenerated</span><span class="sv sv-green">✔ Yes</span></div>
                    <div class="srow"><span class="sk">Timeout</span><span class="sv"><?= SESSION_TIMEOUT_SECONDS / 60 ?> min idle</span></div>
                    <div class="srow"><span class="sk">Remaining</span><span class="sv sv-green" id="sess-remaining"><?= $remaining_min ?>m <?= str_pad($remaining_s, 2, '0', STR_PAD_LEFT) ?>s</span></div>
                    <div class="srow"><span class="sk">HTTPOnly Cookie</span><span class="sv sv-green">✔</span></div>
                    <div class="srow"><span class="sk">password_verify()</span><span class="sv sv-green">✔ Used on login</span></div>
                    <div class="srow"><span class="sk">Unauthorized block</span><span class="sv sv-green">✔ redirect_to_dashboard()</span></div>
                </div>
            </div>

            <!-- Dynamic Role-Based Menu (Requirement 3) -->
            <div class="section-card">
                <h3>⚡ Dynamic Menu <small style="font-size:.7rem;color:#6c757d;">(role: <?= htmlspecialchars($admin_role, ENT_QUOTES, 'UTF-8') ?>)</small></h3>
                <div class="dynamic-menu">
                    <?php foreach ($dynamic_menu as $item): ?>
                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                       class="menu-btn<?= isset($item['danger']) && $item['danger'] ? ' danger' : '' ?>"
                       style="background:<?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>;">
                        <?= $item['icon'] ?> <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Second row: Chart + Audit Log ───────────────────────────────── -->
    <div class="grid-1-1">

        <!-- Advanced Extension: Event registrations by month chart -->
        <div class="section-card">
            <h3>📊 Registrations by Month <span class="p14-badge">Advanced</span></h3>
            <div class="chart-wrap">
                <canvas id="registrations-chart" aria-label="Event registrations by month"></canvas>
            </div>
        </div>

        <!-- Audit Log (Requirement 4) -->
        <div class="section-card">
            <h3>📝 Audit Log <span class="p14-badge">Practical 14</span></h3>
            <?php if ($audit_log_rows): ?>
            <div style="overflow-x:auto;max-height:280px;overflow-y:auto;">
                <table class="audit-table" aria-label="Audit log">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>By</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody">
                        <?php foreach ($audit_log_rows as $log): ?>
                        <?php
                            $action_upper = strtoupper($log['action']);
                            $action_class = 'aa-view';
                            if (str_contains($action_upper, 'CREATE') || str_contains($action_upper, 'INSERT')) $action_class = 'aa-create';
                            elseif (str_contains($action_upper, 'UPDATE') || str_contains($action_upper, 'EDIT')) $action_class = 'aa-update';
                            elseif (str_contains($action_upper, 'DELETE')) $action_class = 'aa-delete';
                            elseif (str_contains($action_upper, 'TOGGLE') || str_contains($action_upper, 'STATUS')) $action_class = 'aa-toggle';
                            elseif (str_contains($action_upper, 'LOGIN')) $action_class = 'aa-login';
                        ?>
                        <tr>
                            <td>#<?= (int)$log['log_id'] ?></td>
                            <td><span class="audit-action <?= $action_class ?>"><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="font-size:.72rem;"><?= htmlspecialchars($log['table_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="font-size:.72rem;"><?= htmlspecialchars($log['performed_by'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="font-size:.70rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(substr($log['details'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8') ?><?= strlen($log['details'] ?? '') > 40 ? '…' : '' ?></td>
                            <td style="font-size:.70rem;white-space:nowrap;"><?= htmlspecialchars(date('d M H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:#6c757d;font-size:.85rem;">No audit entries yet. Actions you perform will be recorded here.</p>
            <?php endif; ?>
        </div>
    </div>

</main>

<footer style="text-align:center;padding:18px;color:#6c757d;font-size:.78rem;">
    &copy; 2026 StudentHub &mdash; Practical 14: Admin Dashboard with RBAC, Dynamic Menu &amp; Audit Log
</footer>

<!-- Toast container -->
<div id="toast-container" aria-live="polite"></div>

<script>
/* ─────────────────────────────────────────────────────────────
   Practical 14 — Admin Dashboard JavaScript
   1. Session countdown timer
   2. Status toggle (Intermediate Extension) via Fetch API
   3. Chart.js — registrations by month (Advanced Extension)
   4. Toast notification helper
───────────────────────────────────────────────────────────── */

// 1. Session countdown
(function () {
    let rem = <?= $remaining_sec ?>;
    const cd = document.getElementById('timeout-countdown');
    const sr = document.getElementById('sess-remaining');
    setInterval(function () {
        if (rem <= 0) { window.location.href = 'login.php'; return; }
        rem--;
        const m = Math.floor(rem / 60), s = String(rem % 60).padStart(2, '0');
        const t = `${m}m ${s}s`;
        if (cd) cd.textContent = t;
        if (sr) sr.textContent = t;
    }, 1000);
})();

// 2. Toast helper
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = (type === 'success' ? '✅ ' : '❌ ') + message;
    container.appendChild(toast);
    // Animate in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('show'));
    });
    // Auto-dismiss after 3.5s
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// 3. Intermediate Extension — User Status Toggle
async function toggleUserStatus(btn) {
    const userId  = parseInt(btn.dataset.userId, 10);
    const current = parseInt(btn.dataset.current, 10);
    const newStatus = current === 1 ? 0 : 1;

    // Show spinner
    const spinner = document.getElementById(`spinner-${userId}`);
    const label   = document.getElementById(`toggle-label-${userId}`);
    if (spinner) spinner.style.display = 'inline-block';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action',    'toggle_status');
        formData.append('user_id',   userId);
        formData.append('is_active', newStatus);

        const response = await fetch('audit_log_api.php', {
            method: 'POST',
            body:   formData,
        });

        const json = await response.json();

        if (json.success) {
            // Update button state
            btn.dataset.current = newStatus;
            btn.className = `toggle-btn ${newStatus === 1 ? 'active' : 'inactive'}`;
            if (label) label.textContent = newStatus === 1 ? 'Active' : 'Inactive';

            // Update status text cell
            const statusText = document.getElementById(`status-text-${userId}`);
            if (statusText) {
                statusText.style.color = newStatus === 1 ? '#198754' : '#dc3545';
                statusText.textContent = newStatus === 1 ? '● Active' : '● Inactive';
            }

            showToast(json.message || 'Status updated successfully.', 'success');
        } else {
            showToast(json.message || 'Failed to update status.', 'error');
        }
    } catch (err) {
        console.error('Toggle error:', err);
        showToast('Network error. Please try again.', 'error');
    } finally {
        if (spinner) spinner.style.display = 'none';
        btn.disabled = false;
    }
}

// 4. Advanced Extension — Chart.js registrations by month
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('registrations-chart');
    if (!ctx) return;

    const labels = <?= $chart_labels_json ?>;
    const data   = <?= $chart_data_json ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Event Registrations',
                data:  data,
                backgroundColor: 'rgba(92, 26, 46, 0.75)',
                borderColor:     '#5c1a2e',
                borderWidth:     1.5,
                borderRadius:    6,
                hoverBackgroundColor: '#ff6b9d',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} registration${ctx.parsed.y !== 1 ? 's' : ''}`,
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: 'rgba(0,0,0,.06)' },
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

</body>
</html>
