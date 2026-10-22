<?php
/**
 * StudentHub Portal — Practical 14
 * Audit Log & User Status Toggle API Endpoint
 * Course: Web Development Fundamentals (ITUE203)
 *
 * Endpoints (POST):
 *   action=toggle_status  — Toggle user is_active and write audit_log entry
 *   action=get_audit_log  — Return latest audit_log rows as JSON
 *
 * Endpoint (GET):
 *   action=stats          — Return live dashboard stats as JSON
 */

define('STUDENTHUB_ACCESS', true);
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';
require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';

// JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── RBAC: must be admin ──────────────────────────────────────────────────
session_secure_start();
if (!is_authenticated() || current_role() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'status_code' => 403, 'message' => 'Admin access required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = match($method) {
    'GET'  => $_GET['action']  ?? '',
    'POST' => $_POST['action'] ?? '',
    default => '',
};

$admin_user = htmlspecialchars($_SESSION['username'] ?? 'admin', ENT_QUOTES, 'UTF-8');
$ip         = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// ── Helper: write to audit_log ───────────────────────────────────────────
function write_audit_log(string $action, string $table, int $record_id, string $performed_by, string $details, string $ip): void {
    try {
        mysqli_execute_stmt(
            "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            'ssisss',
            [$action, $table, $record_id, $performed_by, $details, $ip]
        );
    } catch (\Throwable $e) {
        error_log('audit_log write error: ' . $e->getMessage());
    }
}

// ── Router ───────────────────────────────────────────────────────────────
try {

    // ---- POST: toggle_status ----------------------------------------
    if ($method === 'POST' && $action === 'toggle_status') {
        $user_id   = (int)($_POST['user_id']   ?? 0);
        $is_active = (int)($_POST['is_active']  ?? 0);
        $is_active = $is_active === 1 ? 1 : 0;

        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'status_code' => 400, 'message' => 'Invalid user ID.']);
            exit;
        }

        // Prevent admin from deactivating their own account
        if ($user_id === current_user_id()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'status_code' => 422, 'message' => 'You cannot deactivate your own account.']);
            exit;
        }

        // Verify user exists
        $existing = mysqli_fetch_one_row('SELECT user_id, username, is_active FROM users WHERE user_id = ?', 'i', [$user_id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'status_code' => 404, 'message' => 'User not found.']);
            exit;
        }

        // Update status
        $result = mysqli_execute_stmt('UPDATE users SET is_active = ? WHERE user_id = ?', 'ii', [$is_active, $user_id]);

        if ($result['affected_rows'] >= 0) {
            $status_label = $is_active === 1 ? 'Active' : 'Inactive';
            $details      = "User '{$existing['username']}' (ID:{$user_id}) status set to {$status_label}";

            // Write audit log entry (Practical 14 Requirement 4)
            write_audit_log('TOGGLE_STATUS', 'users', $user_id, $admin_user, $details, $ip);

            echo json_encode([
                'success'     => true,
                'status_code' => 200,
                'message'     => "User status updated to {$status_label}.",
                'data'        => ['user_id' => $user_id, 'is_active' => $is_active],
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'status_code' => 500, 'message' => 'Update failed.']);
        }
        exit;
    }

    // ---- POST / GET: get_audit_log ----------------------------------
    if ($action === 'get_audit_log') {
        $limit = max(1, min(50, (int)(($_GET['limit'] ?? $_POST['limit'] ?? 20))));
        $rows  = mysqli_fetch_all_rows(
            'SELECT log_id, action, table_name, record_id, performed_by, details, ip_address, created_at
             FROM audit_log
             ORDER BY created_at DESC
             LIMIT ?',
            'i', [$limit]
        );

        echo json_encode([
            'success'     => true,
            'status_code' => 200,
            'message'     => 'Audit log retrieved.',
            'data'        => $rows,
            'meta'        => ['count' => count($rows)],
            'timestamp'   => date('c'),
        ]);
        exit;
    }

    // ---- GET: stats --------------------------------------------------
    if ($method === 'GET' && $action === 'stats') {
        $count_map = [
            'users'         => 'users',
            'students'      => 'students',
            'events'        => 'events',
            'registrations' => 'registrations',
            'contacts'      => 'contact_inquiries',
            'audit_entries' => 'audit_log',
        ];
        $stats = [];
        foreach ($count_map as $key => $tbl) {
            $row = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM `{$tbl}`");
            $stats[$key] = (int)($row['total'] ?? 0);
        }

        // Chart: registrations by month (last 6 months)
        $chart_rows = mysqli_fetch_all_rows(
            "SELECT DATE_FORMAT(registration_date, '%b %Y') AS month_label,
                    COUNT(*) AS reg_count
             FROM registrations
             WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(registration_date, '%Y-%m'), month_label
             ORDER BY DATE_FORMAT(registration_date, '%Y-%m') ASC"
        );

        echo json_encode([
            'success'     => true,
            'status_code' => 200,
            'message'     => 'Dashboard stats retrieved.',
            'data'        => [
                'stats'       => $stats,
                'chart_labels' => array_column($chart_rows, 'month_label'),
                'chart_data'   => array_map('intval', array_column($chart_rows, 'reg_count')),
            ],
            'timestamp'   => date('c'),
        ]);
        exit;
    }

    // ---- Unknown action ---------------------------------------------
    http_response_code(400);
    echo json_encode(['success' => false, 'status_code' => 400, 'message' => "Unknown action: '{$action}'."]);

} catch (\Throwable $e) {
    error_log('audit_log_api.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'status_code' => 500, 'message' => 'Internal server error.']);
}
