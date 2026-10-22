# Practical 14: Admin Dashboard with Role-Based Access Control, Dynamic Menu, and Audit Log

> **Course:** Web Development Fundamentals (ITUE203)
> **Topic:** Practical 14 — Admin Dashboard: RBAC, Dynamic Menu, Live Statistics, Audit Log, User Status Toggle, and Monthly Registration Analytics Chart
> **Student:** Rudra Vaghasiya (Roll No: 25CE129)
> **Tech Stack:** PHP 8.2, MySQL / MariaDB 10.4, JavaScript (Fetch API, Chart.js 4), HTML5, CSS3
> **Backend Files:** [`public/api/admin_dashboard.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/public/api/admin_dashboard.php) · [`public/api/audit_log_api.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/public/api/audit_log_api.php)
> **Database:** [`database/studenthub_schema.sql`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/database/studenthub_schema.sql)

---

## 1. Problem Definition & Objectives

### Problem Definition
Develop an admin dashboard that displays user statistics, student count, event count, and latest registrations. Restrict access to admin users only and show dynamic menu options based on role. Important admin actions must be recorded in an `audit_log` table.

### Key Objectives
1. **Dynamic Dashboard Stats (Requirement 1):** Load all counts (users, students, events, registrations, inquiries, audit entries) live from the database using `COUNT(*)` queries.
2. **RBAC — Admin-Only Access (Requirement 2):** Enforce admin-only access via `require_login('admin')` from `session_guard.php`. Non-admins are redirected to their own dashboard.
3. **Role-Based Dynamic Menu (Requirement 3):** Menu items are filtered based on `$_SESSION['role']` using a PHP config array. Admin users see admin-only links; student users would only see student-appropriate links.
4. **Audit Log Recording (Requirement 4):** Every page view and status toggle is written to the `audit_log` table, including `action`, `table_name`, `record_id`, `performed_by`, `details`, and `ip_address`.
5. **Intermediate Extension:** AJAX-powered Active/Inactive status toggle for user accounts with real-time DOM update and audit log write.
6. **Advanced Extension:** Chart.js bar chart displaying event registrations grouped by month (last 6 months).

---

## 2. Key Questions & Architectural Analysis

### Q1. Are dashboard values loaded dynamically from the database?
**Yes.** All stat cards on the dashboard are populated by server-side PHP using live `SELECT COUNT(*) AS total FROM <table>` queries before page render:

```php
foreach ($count_map as $key => $tbl) {
    $row = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM `{$tbl}`");
    $stats[$key] = (int)($row['total'] ?? 0);
}
```

Counts displayed: `users`, `students`, `events`, `registrations`, `contact_inquiries`, `audit_log`.

### Q2. Is access restricted to admin users?
**Yes.** The very first executable line after the `require_once` calls is:

```php
require_login('admin');  // from session_guard.php
```

`require_login()` calls `session_secure_start()`, checks `is_authenticated()`, runs `check_session_timeout()`, and then verifies `current_role() === 'admin'`. If any check fails, the visitor is redirected — never shown any HTML.

### Q3. Are role-based menus working?
**Yes.** A PHP array defines all possible menu items with a `roles` key. At render time, `array_filter()` keeps only items whose `roles` array includes the current session role:

```php
$dynamic_menu = array_filter($all_menu_items, fn($item) => in_array($admin_role, $item['roles'], true));
```

| Role | Menu Items Shown |
|---|---|
| `admin` | Manage Students, Manage Events, AJAX Event Hub, View Records, DB Connection, Add Student, Portal Home, Logout |
| `student` | Student Dashboard, Portal Home, Logout |

### Q4. Are important admin actions recorded in audit_logs?
**Yes.** The `audit_log` table captures:

| Column | Example Value |
|---|---|
| `action` | `VIEW_DASHBOARD`, `TOGGLE_STATUS`, `CREATE`, `DELETE` |
| `table_name` | `admin_dashboard`, `users`, `events`, `students` |
| `record_id` | User/event ID of the affected record |
| `performed_by` | Admin `username` from session |
| `details` | Human-readable description |
| `ip_address` | `$_SERVER['REMOTE_ADDR']` |
| `created_at` | Auto-set `TIMESTAMP` |

Example audit entry written on every dashboard load:
```php
mysqli_execute_stmt(
    "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
     VALUES ('VIEW_DASHBOARD', 'admin_dashboard', 0, ?, 'Admin viewed dashboard', ?)",
    'ss', [$admin_username, $_SERVER['REMOTE_ADDR']]
);
```

---

## 3. Intermediate & Advanced Extensions

### Intermediate Extension: Active/Inactive User Status Toggle

Admins can click a status toggle button in the Users table. The click triggers a JavaScript `async` function that:

1. Shows a CSS spinner on the button
2. POSTs `action=toggle_status`, `user_id`, and `is_active` to `audit_log_api.php`
3. The API validates admin RBAC, prevents self-deactivation, updates `users.is_active`, and writes an `audit_log` entry
4. The JavaScript response handler updates the button text, color class, and status text cell in the DOM — **no page reload**
5. A sliding toast notification confirms success or error

```javascript
async function toggleUserStatus(btn) {
    const formData = new FormData();
    formData.append('action',    'toggle_status');
    formData.append('user_id',   userId);
    formData.append('is_active', newStatus);

    const response = await fetch('audit_log_api.php', { method: 'POST', body: formData });
    const json     = await response.json();

    if (json.success) {
        btn.className = `toggle-btn ${newStatus === 1 ? 'active' : 'inactive'}`;
        showToast(json.message, 'success');
    }
}
```

### Advanced Extension: Event Registrations by Month (Chart.js)

A `GROUP BY` SQL query fetches registration counts for the last 6 months:

```sql
SELECT DATE_FORMAT(registration_date, '%b %Y') AS month_label,
       COUNT(*) AS reg_count
FROM   registrations
WHERE  registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
ORDER BY DATE_FORMAT(registration_date, '%Y-%m') ASC
```

The data is JSON-encoded server-side and injected into Chart.js via PHP:
```javascript
const labels = <?= $chart_labels_json ?>;
const data   = <?= $chart_data_json ?>;

new Chart(ctx, { type: 'bar', data: { labels, datasets: [{ data, label: 'Event Registrations' }] } });
```

---

## 4. File Summary

| File | Purpose |
|---|---|
| [`public/api/admin_dashboard.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/public/api/admin_dashboard.php) | Full Practical 14 admin dashboard — RBAC, stats, dynamic menu, audit log table, Chart.js chart, status toggle UI |
| [`public/api/audit_log_api.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/public/api/audit_log_api.php) | JSON API for: `toggle_status` (POST), `get_audit_log` (GET/POST), `stats` (GET) |
| [`database/studenthub_schema.sql`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/database/studenthub_schema.sql) | `audit_log` table, indices, and 10 seeded audit entries for Practical 14 |
| [`tests/php/test_practical_14.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/tests/php/test_practical_14.php) | Automated test suite — 8 test groups, 25+ assertions |

---

## 5. Verification & Test Suite

The test suite at [`tests/php/test_practical_14.php`](file:///e:/SAM_3/WDF/Lab/25CE129_WDF_Student_Hub/tests/php/test_practical_14.php) validates:

| Test Group | What is Verified |
|---|---|
| Test 1 | DB connectivity + MySQLi ping |
| Test 2 | `audit_log` table structure and all required columns exist |
| Test 3 | Dynamic `COUNT(*)` stats queries for all 5 tables |
| Test 4 | Audit log write, field verification, and cleanup |
| Test 5 | `users` table has `role` and `is_active` columns; admin user exists |
| Test 6 | Status toggle mechanics — DB update + audit write + restore |
| Test 7 | Monthly chart `GROUP BY` query returns proper structure |
| Test 8 | File existence + key code patterns in `audit_log_api.php` and `admin_dashboard.php` |

**Run command:**
```bash
php tests/php/test_practical_14.php
```

---

## 6. Conclusion

Practical 14 successfully builds a production-quality admin dashboard implementing all four key requirements: live database-driven statistics, strict admin-only RBAC via session guards, role-filtered dynamic navigation menus, and a persistent audit trail that captures every significant admin action. The intermediate extension adds AJAX-powered user status toggling with real-time DOM feedback, while the advanced extension integrates a Chart.js bar chart showing event registration trends over the last six months.
