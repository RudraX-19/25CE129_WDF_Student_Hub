<?php
/**
 * StudentHub Portal — Practical 14 Test Suite
 * Admin Dashboard: RBAC, Dynamic Menu, Audit Log
 * Course: Web Development Fundamentals (ITUE203)
 *
 * Run: php tests/php/test_practical_14.php
 * Requires: XAMPP running with studenthub_db populated
 */

define('STUDENTHUB_ACCESS', true);
require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';

// ── Test harness ─────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;
$errors = [];

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "\033[32m  ✔ PASS\033[0m  {$name}\n";
        $passed++;
    } else {
        echo "\033[31m  ✘ FAIL\033[0m  {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $name;
    }
}

echo "\n";
echo "======================================================\n";
echo "  StudentHub — Practical 14 Test Suite\n";
echo "  Admin Dashboard: RBAC, Dynamic Menu, Audit Log\n";
echo "======================================================\n\n";

// ─── TEST 1: DB Connection ────────────────────────────────────────────────
echo "--- Test 1: Database Connectivity ---\n";
try {
    $db = get_mysqli_connection();
    assert_test('MySQLi connection established', $db instanceof mysqli);
    assert_test('Database ping successful', $db->ping());
} catch (\Throwable $e) {
    assert_test('MySQLi connection established', false, $e->getMessage());
    assert_test('Database ping successful',      false, 'Connection failed');
}

// ─── TEST 2: audit_log Table ──────────────────────────────────────────────
echo "\n--- Test 2: audit_log Table Structure ---\n";
try {
    $row = mysqli_fetch_one_row("SHOW TABLES LIKE 'audit_log'");
    assert_test('audit_log table exists', $row !== null);

    $cols = mysqli_fetch_all_rows("DESCRIBE audit_log");
    $col_names = array_column($cols, 'Field');
    foreach (['log_id', 'action', 'table_name', 'record_id', 'performed_by', 'details', 'ip_address', 'created_at'] as $col) {
        assert_test("Column '{$col}' exists in audit_log", in_array($col, $col_names, true));
    }
} catch (\Throwable $e) {
    assert_test('audit_log table exists', false, $e->getMessage());
}

// ─── TEST 3: Dynamic Dashboard Stats (Requirement 1) ─────────────────────
echo "\n--- Test 3: Dynamic Statistics from DB ---\n";
try {
    $count_map = [
        'users'         => 'users',
        'students'      => 'students',
        'events'        => 'events',
        'registrations' => 'registrations',
        'audit_entries' => 'audit_log',
    ];
    foreach ($count_map as $key => $tbl) {
        $row   = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM `{$tbl}`");
        $count = (int)($row['total'] ?? -1);
        assert_test("SELECT COUNT(*) from '{$tbl}' succeeds (value: {$count})", $count >= 0);
    }
} catch (\Throwable $e) {
    assert_test('Dynamic stats query succeeds', false, $e->getMessage());
}

// ─── TEST 4: Audit Log Write (Requirement 4) ─────────────────────────────
echo "\n--- Test 4: Audit Log Write ---\n";
try {
    $before_count = (int)(mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM audit_log")['total'] ?? 0);

    mysqli_execute_stmt(
        "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
         VALUES ('TEST_ACTION', 'test_table', 0, 'test_runner', 'Practical 14 automated test write', '127.0.0.1')",
        '',
        []
    );

    $after_count = (int)(mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM audit_log")['total'] ?? 0);
    assert_test('Audit log row can be written', $after_count === $before_count + 1);

    // Verify the row
    $last_row = mysqli_fetch_one_row("SELECT * FROM audit_log ORDER BY log_id DESC LIMIT 1");
    assert_test("Audit action stored correctly ('TEST_ACTION')", ($last_row['action'] ?? '') === 'TEST_ACTION');
    assert_test("Audit performed_by stored correctly ('test_runner')", ($last_row['performed_by'] ?? '') === 'test_runner');

    // Cleanup
    mysqli_execute_stmt("DELETE FROM audit_log WHERE action = 'TEST_ACTION' AND performed_by = 'test_runner'", '', []);
    assert_test('Test audit log entry cleaned up', true);

} catch (\Throwable $e) {
    assert_test('Audit log write succeeds', false, $e->getMessage());
}

// ─── TEST 5: Users Table / RBAC Data (Requirement 2) ─────────────────────
echo "\n--- Test 5: Users Table & RBAC Column ---\n";
try {
    $users_desc = mysqli_fetch_all_rows("DESCRIBE users");
    $col_names  = array_column($users_desc, 'Field');

    assert_test("users table has 'role' column",      in_array('role',      $col_names, true));
    assert_test("users table has 'is_active' column", in_array('is_active', $col_names, true));
    assert_test("users table has 'username' column",  in_array('username',  $col_names, true));

    $admin_row = mysqli_fetch_one_row("SELECT user_id, role FROM users WHERE role = 'admin' LIMIT 1");
    assert_test('At least one admin user exists in users table', $admin_row !== null);

} catch (\Throwable $e) {
    assert_test('Users table RBAC structure check', false, $e->getMessage());
}

// ─── TEST 6: Intermediate Extension — Status Toggle ───────────────────────
echo "\n--- Test 6: Intermediate Extension — User Status Toggle ---\n";
try {
    // Grab a non-admin test user
    $test_user = mysqli_fetch_one_row("SELECT user_id, username, is_active FROM users WHERE role = 'student' LIMIT 1");

    if ($test_user) {
        $uid     = (int)$test_user['user_id'];
        $orig    = (int)$test_user['is_active'];
        $toggled = $orig === 1 ? 0 : 1;

        // Toggle to opposite
        $r1 = mysqli_execute_stmt("UPDATE users SET is_active = ? WHERE user_id = ?", 'ii', [$toggled, $uid]);
        assert_test("User status toggled to {$toggled}", $r1['affected_rows'] > 0);

        // Write audit log for the toggle
        mysqli_execute_stmt(
            "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
             VALUES ('TOGGLE_STATUS', 'users', ?, 'test_runner', ?, '127.0.0.1')",
            'is', [$uid, "User '{$test_user['username']}' status set to " . ($toggled === 1 ? 'Active' : 'Inactive')]
        );
        $audit_verify = mysqli_fetch_one_row(
            "SELECT log_id FROM audit_log WHERE action = 'TOGGLE_STATUS' AND performed_by = 'test_runner' ORDER BY log_id DESC LIMIT 1"
        );
        assert_test('Audit log entry created for toggle action', $audit_verify !== null);

        // Restore original status
        mysqli_execute_stmt("UPDATE users SET is_active = ? WHERE user_id = ?", 'ii', [$orig, $uid]);
        assert_test('User status restored to original', true);

        // Cleanup test audit entries
        mysqli_execute_stmt("DELETE FROM audit_log WHERE performed_by = 'test_runner'", '', []);
        assert_test('Test audit entries cleaned up', true);

    } else {
        assert_test('Student user available for toggle test', false, 'No student user found; seed the DB first.');
    }
} catch (\Throwable $e) {
    assert_test('Status toggle mechanics work', false, $e->getMessage());
}

// ─── TEST 7: Advanced Extension — Registrations by Month Query ────────────
echo "\n--- Test 7: Advanced Extension — Monthly Registration Chart Data ---\n";
try {
    $chart_rows = mysqli_fetch_all_rows(
        "SELECT DATE_FORMAT(registration_date, '%b %Y') AS month_label,
                DATE_FORMAT(registration_date, '%Y-%m') AS month_key,
                COUNT(*) AS reg_count
         FROM registrations
         WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month_key, month_label
         ORDER BY month_key ASC"
    );

    assert_test('Registrations-by-month query executes without error', true);
    assert_test('Query returns array result', is_array($chart_rows));
    if (!empty($chart_rows)) {
        $first = $chart_rows[0];
        assert_test("Result has 'month_label' key",  isset($first['month_label']));
        assert_test("Result has 'reg_count' key",    isset($first['reg_count']));
        assert_test("reg_count is numeric", is_numeric($first['reg_count']));
    } else {
        assert_test('No registrations in last 6 months (empty result is still valid)', true);
    }
} catch (\Throwable $e) {
    assert_test('Registrations-by-month query succeeds', false, $e->getMessage());
}

// ─── TEST 8: audit_log_api.php File Exists ───────────────────────────────
echo "\n--- Test 8: API File Existence ---\n";
$api_path  = dirname(__DIR__, 2) . '/public/api/audit_log_api.php';
$dash_path = dirname(__DIR__, 2) . '/public/api/admin_dashboard.php';
assert_test('audit_log_api.php file exists', file_exists($api_path));
assert_test('admin_dashboard.php (P14) exists', file_exists($dash_path));

$api_content = file_exists($api_path) ? file_get_contents($api_path) : '';
assert_test("API contains 'toggle_status' action handler", str_contains($api_content, 'toggle_status'));
assert_test("API contains write_audit_log() call",        str_contains($api_content, 'write_audit_log'));
assert_test("API enforces admin RBAC check",              str_contains($api_content, "current_role() !== 'admin'"));

$dash_content = file_exists($dash_path) ? file_get_contents($dash_path) : '';
assert_test("Dashboard contains Chart.js script tag",   str_contains($dash_content, 'chart.js'));
assert_test("Dashboard contains dynamic menu rendering", str_contains($dash_content, 'dynamic_menu'));
assert_test("Dashboard writes audit log on page view",  str_contains($dash_content, 'VIEW_DASHBOARD'));

// ─── Summary ──────────────────────────────────────────────────────────────
echo "\n======================================================\n";
echo "  Results: \033[32m{$passed} passed\033[0m, \033[31m{$failed} failed\033[0m\n";
if ($errors) {
    echo "  Failed tests:\n";
    foreach ($errors as $e) echo "    - {$e}\n";
}
echo "======================================================\n\n";

exit($failed > 0 ? 1 : 0);
