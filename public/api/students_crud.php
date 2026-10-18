<?php
/**
 * StudentHub Portal - Practical 11
 * Student Management CRUD with Search, Filter, Soft Delete & CSV Export
 *
 * Requirements Met:
 * 1. SELECT, INSERT, UPDATE, DELETE using MySQLi prepared statements.
 * 2. Search by name / email / roll no / course.
 * 3. Filter by Course, Year of Study, and Status.
 * 4. Interactive client-side & server-side Delete Confirmation.
 * 5. Intermediate Extension: Export filtered student directory to CSV.
 * 6. Advanced Extension: Soft delete using is_deleted & status column with one-click Restore.
 * 7. Flash success/error feedback messages and CSRF protection.
 *
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);

// Load core dependencies
require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';
require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';

session_secure_start();

$csrf_token = get_csrf_token();
$errors = [];
$success_msg = get_flash('crud_success');
$error_msg   = get_flash('crud_error');

// Get all courses for dropdowns
$courses = [];
try {
    $courses = mysqli_fetch_all_rows("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code ASC");
} catch (\Throwable $e) {
    $error_msg = "Database warning: Could not fetch courses list. " . $e->getMessage();
}

// -----------------------------------------------------------------------------
// POST HANDLERS: CREATE, UPDATE, SOFT DELETE, RESTORE, HARD DELETE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $token  = trim($_POST['csrf_token'] ?? '');

    if (!verify_csrf_token($token)) {
        set_flash('crud_error', 'Security verification failed (CSRF token mismatch). Please retry.');
        header('Location: students_crud.php');
        exit;
    }

    // --- 1. ADD STUDENT ---
    if ($action === 'add') {
        $roll_no   = strtoupper(trim(strip_tags($_POST['roll_no'] ?? '')));
        $fullname  = trim(strip_tags($_POST['fullname'] ?? ''));
        $email     = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $mobile    = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $course_id = (int)($_POST['course_id'] ?? 0);
        $year      = (int)($_POST['year_of_study'] ?? 0);
        $gender    = trim($_POST['gender'] ?? 'Male');
        $gpa       = (float)($_POST['gpa'] ?? 0.0);
        $status    = trim($_POST['status'] ?? 'Active');

        // Validation
        if (empty($roll_no) || strlen($roll_no) < 3 || strlen($roll_no) > 30) {
            $errors['roll_no'] = 'Valid Roll Number is required (3 to 30 characters).';
        }
        if (empty($fullname) || strlen($fullname) < 2) {
            $errors['fullname'] = 'Full Name is required (minimum 2 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        }
        if (strlen($mobile) !== 10) {
            $errors['mobile'] = 'Mobile number must be exactly 10 digits.';
        }
        if ($course_id <= 0) {
            $errors['course_id'] = 'Please select a valid enrolled course.';
        }
        if ($year < 1 || $year > 4) {
            $errors['year_of_study'] = 'Year of study must be between 1 and 4.';
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $gender = 'Male';
        }
        if ($gpa < 0.0 || $gpa > 10.0) {
            $errors['gpa'] = 'GPA must be between 0.00 and 10.00.';
        }
        if (!in_array($status, ['Active', 'Graduated', 'Suspended'], true)) {
            $status = 'Active';
        }

        // Uniqueness checks
        if (empty($errors)) {
            $existing_roll = mysqli_fetch_one_row("SELECT student_id FROM students WHERE roll_no = ?", "s", [$roll_no]);
            if ($existing_roll) {
                $errors['roll_no'] = "Roll Number '{$roll_no}' is already registered.";
            }

            $existing_email = mysqli_fetch_one_row("SELECT student_id FROM students WHERE email = ?", "s", [$email]);
            if ($existing_email) {
                $errors['email'] = "Email '{$email}' is already in use by another student.";
            }
        }

        if (empty($errors)) {
            try {
                $default_pw_hash = password_hash('Student@123', PASSWORD_BCRYPT);
                $sql = "INSERT INTO students (roll_no, fullname, email, mobile, course_id, year_of_study, gender, password_hash, gpa, status, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $types = "ssssiissds";
                $params = [$roll_no, $fullname, $email, $mobile, $course_id, $year, $gender, $default_pw_hash, $gpa, $status];

                $res = mysqli_execute_stmt($sql, $types, $params);
                set_flash('crud_success', "Student '{$fullname}' ({$roll_no}) successfully enrolled with ID #{$res['insert_id']}!");
                header('Location: students_crud.php');
                exit;
            } catch (\Throwable $e) {
                $errors['db'] = "Failed to add student: " . $e->getMessage();
            }
        }
    }

    // --- 2. EDIT / UPDATE STUDENT ---
    if ($action === 'edit') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $roll_no    = strtoupper(trim(strip_tags($_POST['roll_no'] ?? '')));
        $fullname   = trim(strip_tags($_POST['fullname'] ?? ''));
        $email      = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $mobile     = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $course_id  = (int)($_POST['course_id'] ?? 0);
        $year       = (int)($_POST['year_of_study'] ?? 0);
        $gender     = trim($_POST['gender'] ?? 'Male');
        $gpa        = (float)($_POST['gpa'] ?? 0.0);
        $status     = trim($_POST['status'] ?? 'Active');

        if ($student_id <= 0) {
            $errors['edit_id'] = 'Invalid Student ID provided for edit.';
        }
        if (empty($roll_no)) {
            $errors['roll_no'] = 'Roll Number is required.';
        }
        if (empty($fullname)) {
            $errors['fullname'] = 'Full Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        }
        if (strlen($mobile) !== 10) {
            $errors['mobile'] = 'Mobile number must be 10 digits.';
        }
        if ($course_id <= 0) {
            $errors['course_id'] = 'Select a valid course.';
        }
        if ($year < 1 || $year > 4) {
            $errors['year_of_study'] = 'Year must be 1 to 4.';
        }
        if ($gpa < 0.0 || $gpa > 10.0) {
            $errors['gpa'] = 'GPA must be 0.00 to 10.00.';
        }

        // Uniqueness check excluding current record
        if (empty($errors)) {
            $roll_dup = mysqli_fetch_one_row(
                "SELECT student_id FROM students WHERE roll_no = ? AND student_id != ?",
                "si",
                [$roll_no, $student_id]
            );
            if ($roll_dup) {
                $errors['roll_no'] = "Roll Number '{$roll_no}' belongs to another student.";
            }

            $email_dup = mysqli_fetch_one_row(
                "SELECT student_id FROM students WHERE email = ? AND student_id != ?",
                "si",
                [$email, $student_id]
            );
            if ($email_dup) {
                $errors['email'] = "Email '{$email}' belongs to another student.";
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE students
                        SET roll_no = ?, fullname = ?, email = ?, mobile = ?, course_id = ?,
                            year_of_study = ?, gender = ?, gpa = ?, status = ?
                        WHERE student_id = ?";
                $types = "ssssiisdsi";
                $params = [$roll_no, $fullname, $email, $mobile, $course_id, $year, $gender, $gpa, $status, $student_id];

                mysqli_execute_stmt($sql, $types, $params);
                set_flash('crud_success', "Student '{$roll_no}' updated successfully!");
                header('Location: students_crud.php');
                exit;
            } catch (\Throwable $e) {
                $errors['db'] = "Failed to update student: " . $e->getMessage();
            }
        }
    }

    // --- 3. SOFT DELETE (Advanced Extension) ---
    if ($action === 'soft_delete') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            try {
                $target = mysqli_fetch_one_row("SELECT roll_no, fullname FROM students WHERE student_id = ?", "i", [$student_id]);
                if ($target) {
                    mysqli_execute_stmt(
                        "UPDATE students SET is_deleted = 1, status = 'Suspended' WHERE student_id = ?",
                        "i",
                        [$student_id]
                    );
                    set_flash('crud_success', "Student {$target['fullname']} ({$target['roll_no']}) has been archived (soft-deleted). You can restore them anytime.");
                } else {
                    set_flash('crud_error', "Student record not found.");
                }
            } catch (\Throwable $e) {
                set_flash('crud_error', "Could not soft delete student: " . $e->getMessage());
            }
        }
        header('Location: students_crud.php');
        exit;
    }

    // --- 4. RESTORE (Advanced Extension) ---
    if ($action === 'restore') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            try {
                $target = mysqli_fetch_one_row("SELECT roll_no, fullname FROM students WHERE student_id = ?", "i", [$student_id]);
                if ($target) {
                    mysqli_execute_stmt(
                        "UPDATE students SET is_deleted = 0, status = 'Active' WHERE student_id = ?",
                        "i",
                        [$student_id]
                    );
                    set_flash('crud_success', "Student {$target['fullname']} ({$target['roll_no']}) restored to Active status!");
                }
            } catch (\Throwable $e) {
                set_flash('crud_error', "Could not restore student: " . $e->getMessage());
            }
        }
        header('Location: students_crud.php?view=archived');
        exit;
    }

    // --- 5. PERMANENT DELETE ---
    if ($action === 'hard_delete') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            try {
                $target = mysqli_fetch_one_row("SELECT roll_no FROM students WHERE student_id = ?", "i", [$student_id]);
                // Delete child registrations first if any
                mysqli_execute_stmt("DELETE FROM registrations WHERE student_id = ?", "i", [$student_id]);
                mysqli_execute_stmt("DELETE FROM students WHERE student_id = ?", "i", [$student_id]);
                set_flash('crud_success', "Student {$target['roll_no']} permanently removed from the database.");
            } catch (\Throwable $e) {
                set_flash('crud_error', "Could not permanently delete: " . $e->getMessage());
            }
        }
        header('Location: students_crud.php');
        exit;
    }
}

// -----------------------------------------------------------------------------
// GET PARAMS: SEARCH, FILTER, VIEW, SORT, PAGINATION
// -----------------------------------------------------------------------------
$search_query   = trim($_GET['q'] ?? '');
$filter_course  = (int)($_GET['course'] ?? 0);
$filter_year    = (int)($_GET['year'] ?? 0);
$filter_status  = trim($_GET['status'] ?? '');
$view_mode      = trim($_GET['view'] ?? 'active'); // 'active', 'archived', 'all'
$sort_by        = trim($_GET['sort'] ?? 'roll_no');
$sort_order     = strtoupper(trim($_GET['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 10;
$offset         = ($page - 1) * $per_page;

// Validate sort column to avoid SQL injection
$allowed_sorts = [
    'roll_no'       => 's.roll_no',
    'fullname'      => 's.fullname',
    'email'         => 's.email',
    'gpa'           => 's.gpa',
    'year_of_study' => 's.year_of_study',
    'created_at'    => 's.created_at',
    'course_code'   => 'c.course_code',
];
$order_col = $allowed_sorts[$sort_by] ?? 's.roll_no';

// Build dynamic WHERE clause
$where_clauses = [];
$types_arr     = [];
$params_arr    = [];

// Soft delete filter
if ($view_mode === 'active') {
    $where_clauses[] = "s.is_deleted = 0";
} elseif ($view_mode === 'archived') {
    $where_clauses[] = "s.is_deleted = 1";
}

// Search filter
if ($search_query !== '') {
    $where_clauses[] = "(s.fullname LIKE ? OR s.roll_no LIKE ? OR s.email LIKE ? OR s.mobile LIKE ?)";
    $like_term = "%{$search_query}%";
    $types_arr[] = "ssss";
    $params_arr[] = $like_term;
    $params_arr[] = $like_term;
    $params_arr[] = $like_term;
    $params_arr[] = $like_term;
}

// Course filter
if ($filter_course > 0) {
    $where_clauses[] = "s.course_id = ?";
    $types_arr[] = "i";
    $params_arr[] = $filter_course;
}

// Year filter
if ($filter_year >= 1 && $filter_year <= 4) {
    $where_clauses[] = "s.year_of_study = ?";
    $types_arr[] = "i";
    $params_arr[] = $filter_year;
}

// Status filter
if (!empty($filter_status) && in_array($filter_status, ['Active', 'Graduated', 'Suspended'], true)) {
    $where_clauses[] = "s.status = ?";
    $types_arr[] = "s";
    $params_arr[] = $filter_status;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
$types_str = implode('', $types_arr);

// -----------------------------------------------------------------------------
// INTERMEDIATE EXTENSION: CSV EXPORT
// -----------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "SELECT s.student_id, s.roll_no, s.fullname, s.email, s.mobile,
                          c.course_code, c.course_name, s.year_of_study, s.gender,
                          s.gpa, s.status, s.is_deleted, s.created_at
                   FROM students s
                   LEFT JOIN courses c ON s.course_id = c.course_id
                   {$where_sql}
                   ORDER BY {$order_col} {$sort_order}";

    $export_data = mysqli_fetch_all_rows($export_sql, $types_str, $params_arr);

    $filename = "students_export_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Microsoft Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, [
        'ID', 'Roll Number', 'Full Name', 'Email Address', 'Mobile',
        'Course Code', 'Course Name', 'Year', 'Gender', 'GPA', 'Status', 'Is Deleted', 'Enrollment Date'
    ]);

    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['student_id'],
            $row['roll_no'],
            $row['fullname'],
            $row['email'],
            $row['mobile'],
            $row['course_code'],
            $row['course_name'],
            $row['year_of_study'],
            $row['gender'],
            number_format((float)$row['gpa'], 2),
            $row['status'],
            $row['is_deleted'] ? 'Yes' : 'No',
            $row['created_at'],
        ]);
    }
    fclose($output);
    exit;
}

// -----------------------------------------------------------------------------
// QUERY PAGINATED RECORDS & METRICS
// -----------------------------------------------------------------------------
$total_records = 0;
$students = [];
$stats = [
    'total'    => 0,
    'active'   => 0,
    'archived' => 0,
    'avg_gpa'  => 0.00
];

try {
    // Overall Stats
    $total_row = mysqli_fetch_one_row("SELECT COUNT(*) as c FROM students");
    $stats['total'] = (int)($total_row['c'] ?? 0);

    $act_row = mysqli_fetch_one_row("SELECT COUNT(*) as c, AVG(gpa) as avg_gpa FROM students WHERE is_deleted = 0");
    $stats['active']  = (int)($act_row['c'] ?? 0);
    $stats['avg_gpa'] = round((float)($act_row['avg_gpa'] ?? 0.0), 2);

    $arc_row = mysqli_fetch_one_row("SELECT COUNT(*) as c FROM students WHERE is_deleted = 1");
    $stats['archived'] = (int)($arc_row['c'] ?? 0);

    // Filtered count
    $count_sql = "SELECT COUNT(*) as filtered_count
                  FROM students s
                  LEFT JOIN courses c ON s.course_id = c.course_id
                  {$where_sql}";
    $count_row = mysqli_fetch_one_row($count_sql, $types_str, $params_arr);
    $total_records = (int)($count_row['filtered_count'] ?? 0);
    $total_pages   = max(1, (int)ceil($total_records / $per_page));

    // Fetch page records
    $data_sql = "SELECT s.student_id, s.roll_no, s.fullname, s.email, s.mobile,
                        s.course_id, c.course_code, c.course_name, s.year_of_study,
                        s.gender, s.gpa, s.status, s.is_deleted, s.created_at
                 FROM students s
                 LEFT JOIN courses c ON s.course_id = c.course_id
                 {$where_sql}
                 ORDER BY {$order_col} {$sort_order}
                 LIMIT ? OFFSET ?";

    $page_types  = $types_str . "ii";
    $page_params = array_merge($params_arr, [$per_page, $offset]);
    $students    = mysqli_fetch_all_rows($data_sql, $page_types, $page_params);
} catch (\Throwable $e) {
    $error_msg = "Database query error: " . $e->getMessage();
}

// Helper to preserve query strings on sort/pagination
function build_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    unset($params['export']);
    return 'students_crud.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Management CRUD (Practical 11)</title>
    <meta name="description" content="Practical 11: Comprehensive Student Management CRUD with Search, Multi-Filter, Soft Delete, and CSV Export.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-accent: #0284c7;
            --primary-dark: #0369a1;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
        }

        body {
            background-color: var(--bg-light);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
        }

        .crud-container {
            max-width: 1280px;
            margin: 24px auto;
            padding: 0 20px;
        }

        /* Hero / Header Card */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            margin: 0 0 6px 0;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-p11 {
            background: #0284c7;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* KPI Metric Cards */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: #ffffff;
            padding: 18px 22px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-card h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin: 0 0 4px 0;
        }

        .metric-card .value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
        }

        .metric-icon {
            font-size: 2rem;
            opacity: 0.85;
        }

        /* Control Panel / Filter Form */
        .controls-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .controls-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: flex-end;
        }

        @media (max-width: 992px) {
            .controls-form {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 600px) {
            .controls-form {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
        }

        .form-input, .form-select {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            background-color: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-group-actions {
            display: flex;
            gap: 8px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: var(--primary-accent);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success-color);
            color: #ffffff;
        }
        .btn-success:hover {
            background-color: #059669;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: #ffffff;
        }
        .btn-warning:hover {
            background-color: #d97706;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.78rem;
            border-radius: 4px;
        }

        /* Tab Switcher for Active vs Archived (Soft Delete) */
        .view-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 8px;
        }

        .view-tab {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .view-tab.active {
            background-color: #0f172a;
            color: #ffffff;
        }

        .view-tab:hover:not(.active) {
            background-color: #e2e8f0;
            color: #0f172a;
        }

        /* Data Table */
        .table-responsive {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }

        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .data-table th a:hover {
            color: var(--primary-accent);
        }

        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: #334155;
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: #f1f5f9;
        }

        /* Status & Course Chips */
        .chip {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .chip-active { background: #dcfce7; color: #166534; }
        .chip-graduated { background: #dbeafe; color: #1e40af; }
        .chip-suspended { background: #fef3c7; color: #92400e; }
        .chip-archived { background: #fee2e2; color: #991b1b; }

        .chip-course {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            font-family: monospace;
        }

        /* GPA Indicator */
        .gpa-tag {
            font-weight: 700;
            font-size: 0.88rem;
        }
        .gpa-high { color: #16a34a; }
        .gpa-mid  { color: #0284c7; }
        .gpa-low  { color: #d97706; }

        /* Pagination */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .pagination-nav {
            display: flex;
            gap: 4px;
        }

        .page-link {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            text-decoration: none;
            background: #fff;
            transition: all 0.15s ease;
        }

        .page-link:hover:not(.disabled) {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        .page-link.active {
            background: var(--primary-accent);
            color: #fff;
            border-color: var(--primary-accent);
        }

        .page-link.disabled {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Modal Overlay */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-dialog {
            background: #ffffff;
            border-radius: 12px;
            width: 92%;
            max-width: 620px;
            padding: 24px 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #0f172a;
        }

        .close-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 600px) {
            .modal-grid { grid-template-columns: 1fr; }
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 14px;
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>

<body>

    <div class="crud-container">

        <!-- Top Breadcrumb & Return Link -->
        <div style="margin-bottom: 14px; display:flex; justify-content:space-between; align-items:center;">
            <a href="../dashboard.html" style="color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight:600;">
                &larr; Back to StudentHub Portal
            </a>
            <div style="font-size: 0.85rem; color:#64748b;">
                Database: <strong>MySQLi (studenthub_db)</strong> | Course: <strong>ITUE203 WDF</strong>
            </div>
        </div>

        <!-- Hero Header -->
        <div class="page-header">
            <div>
                <h1>
                    <span>&#128101; Student Directory CRUD</span>
                    <span class="badge-p11">Practical 11</span>
                </h1>
                <p style="margin:0; opacity:0.85; font-size:0.9rem;">
                    Full Create, Read, Update, Delete with Multi-Field Search, Dynamic Filtering, Soft Delete &amp; CSV Export.
                </p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <span>&#10010;</span> Add New Student
                </button>
                <a href="<?php echo htmlspecialchars(build_url(['export' => 'csv'])); ?>" class="btn btn-secondary" title="Export current search/filtered data to CSV">
                    <span>&#128196;</span> Export to CSV
                </a>
                <a href="admin_dashboard.php" class="btn btn-primary" style="background:#334155; border-color:#475569;">
                    <span>&#128737;</span> Admin Panel
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <span><strong>&#10003; Success:</strong> <?php echo htmlspecialchars($success_msg); ?></span>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg) || !empty($errors)): ?>
            <div class="alert alert-danger">
                <div>
                    <strong>&#9888; Error:</strong>
                    <?php if (!empty($error_msg)) echo htmlspecialchars($error_msg); ?>
                    <?php if (!empty($errors)): ?>
                        <ul style="margin:4px 0 0 16px; padding:0;">
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Cards -->
        <div class="metric-grid">
            <div class="metric-card">
                <div>
                    <h3>Total Enrolled</h3>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="metric-icon">&#127891;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Active Students</h3>
                    <div class="value" style="color:#10b981;"><?php echo $stats['active']; ?></div>
                </div>
                <div class="metric-icon">&#9989;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Average GPA</h3>
                    <div class="value" style="color:#0284c7;"><?php echo number_format($stats['avg_gpa'], 2); ?></div>
                </div>
                <div class="metric-icon">&#11088;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Soft-Deleted (Archived)</h3>
                    <div class="value" style="color:#ef4444;"><?php echo $stats['archived']; ?></div>
                </div>
                <div class="metric-icon">&#128465;</div>
            </div>
        </div>

        <!-- View Mode Tabs (Active vs Soft-Deleted) -->
        <div class="view-tabs">
            <a href="<?php echo htmlspecialchars(build_url(['view' => 'active', 'page' => 1])); ?>"
               class="view-tab <?php echo $view_mode === 'active' ? 'active' : ''; ?>">
                &#9989; Active Directory (<?php echo $stats['active']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_url(['view' => 'archived', 'page' => 1])); ?>"
               class="view-tab <?php echo $view_mode === 'archived' ? 'active' : ''; ?>">
                &#128465; Archived / Soft-Deleted (<?php echo $stats['archived']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_url(['view' => 'all', 'page' => 1])); ?>"
               class="view-tab <?php echo $view_mode === 'all' ? 'active' : ''; ?>">
                &#128196; All Records (<?php echo $stats['total']; ?>)
            </a>
        </div>

        <!-- Search & Filter Controls -->
        <div class="controls-card">
            <form method="GET" action="students_crud.php" class="controls-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">

                <div class="form-group">
                    <label for="search-input">&#128269; Search Students</label>
                    <input type="search" id="search-input" name="q" class="form-input"
                           placeholder="Search by name, roll no, email, or mobile…"
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>

                <div class="form-group">
                    <label for="filter-course">Course</label>
                    <select id="filter-course" name="course" class="form-select">
                        <option value="0">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['course_id']; ?>" <?php echo $filter_course === (int)$c['course_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-year">Year of Study</label>
                    <select id="filter-year" name="year" class="form-select">
                        <option value="0">All Years</option>
                        <option value="1" <?php echo $filter_year === 1 ? 'selected' : ''; ?>>1st Year (Freshman)</option>
                        <option value="2" <?php echo $filter_year === 2 ? 'selected' : ''; ?>>2nd Year (Sophomore)</option>
                        <option value="3" <?php echo $filter_year === 3 ? 'selected' : ''; ?>>3rd Year (Junior)</option>
                        <option value="4" <?php echo $filter_year === 4 ? 'selected' : ''; ?>>4th Year (Senior)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Graduated" <?php echo $filter_status === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="Suspended" <?php echo $filter_status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

                <div class="btn-group-actions">
                    <button type="submit" class="btn btn-primary" style="height: 38px;">
                        <span>&#128065;</span> Filter
                    </button>
                    <a href="students_crud.php?view=<?php echo htmlspecialchars($view_mode); ?>" class="btn btn-secondary" style="height: 38px;" title="Reset filters">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Student Data Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo htmlspecialchars(build_url(['sort' => 'roll_no', 'order' => ($sort_by === 'roll_no' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                Roll No <?php echo $sort_by === 'roll_no' ? ($sort_order === 'ASC' ? '&#9650;' : '&#9660;') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo htmlspecialchars(build_url(['sort' => 'fullname', 'order' => ($sort_by === 'fullname' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                Full Name <?php echo $sort_by === 'fullname' ? ($sort_order === 'ASC' ? '&#9650;' : '&#9660;') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo htmlspecialchars(build_url(['sort' => 'course_code', 'order' => ($sort_by === 'course_code' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                Course <?php echo $sort_by === 'course_code' ? ($sort_order === 'ASC' ? '&#9650;' : '&#9660;') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo htmlspecialchars(build_url(['sort' => 'year_of_study', 'order' => ($sort_by === 'year_of_study' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                Year <?php echo $sort_by === 'year_of_study' ? ($sort_order === 'ASC' ? '&#9650;' : '&#9660;') : ''; ?>
                            </a>
                        </th>
                        <th>Contact</th>
                        <th>
                            <a href="<?php echo htmlspecialchars(build_url(['sort' => 'gpa', 'order' => ($sort_by === 'gpa' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                GPA <?php echo $sort_by === 'gpa' ? ($sort_order === 'ASC' ? '&#9650;' : '&#9660;') : ''; ?>
                            </a>
                        </th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 36px 14px; color: #64748b;">
                                <div style="font-size: 2.2rem; margin-bottom: 8px;">&#128269;</div>
                                <strong style="font-size: 1rem; color: #334155;">No student records found</strong>
                                <p style="margin: 4px 0 0 0; font-size: 0.85rem;">Try adjusting your search keywords or clearing filter criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <?php
                                $gpa_val = (float)$s['gpa'];
                                $gpa_class = $gpa_val >= 8.5 ? 'gpa-high' : ($gpa_val >= 7.0 ? 'gpa-mid' : 'gpa-low');
                                $status_chip = 'chip-' . strtolower($s['status']);
                                if ($s['is_deleted']) $status_chip = 'chip-archived';
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a; font-family: monospace; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($s['roll_no']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($s['fullname']); ?></div>
                                    <div style="font-size: 0.78rem; color: #64748b;"><?php echo htmlspecialchars($s['gender']); ?></div>
                                </td>
                                <td>
                                    <span class="chip chip-course"><?php echo htmlspecialchars($s['course_code'] ?? 'N/A'); ?></span>
                                    <span style="font-size: 0.78rem; color: #64748b; display: block; margin-top: 2px;">
                                        <?php echo htmlspecialchars($s['course_name'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>Yr <?php echo (int)$s['year_of_study']; ?></strong>
                                </td>
                                <td>
                                    <div style="font-size: 0.82rem;"><?php echo htmlspecialchars($s['email']); ?></div>
                                    <div style="font-size: 0.78rem; color: #64748b;"><?php echo htmlspecialchars($s['mobile']); ?></div>
                                </td>
                                <td>
                                    <span class="gpa-tag <?php echo $gpa_class; ?>"><?php echo number_format($gpa_val, 2); ?></span>
                                </td>
                                <td>
                                    <?php if ($s['is_deleted']): ?>
                                        <span class="chip chip-archived">&#128465; Soft-Deleted</span>
                                    <?php else: ?>
                                        <span class="chip <?php echo $status_chip; ?>"><?php echo htmlspecialchars($s['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($s['is_deleted']): ?>
                                        <!-- Restore Button -->
                                        <form method="POST" action="students_crud.php" style="display:inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Restore student to active directory">
                                                &#9851; Restore
                                            </button>
                                        </form>

                                        <!-- Permanent Delete -->
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmHardDelete(<?php echo $s['student_id']; ?>, '<?php echo htmlspecialchars(addslashes($s['fullname'])); ?>', '<?php echo htmlspecialchars($s['roll_no']); ?>')">
                                            &#10060; Purge
                                        </button>
                                    <?php else: ?>
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                onclick='openEditModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                title="Edit student record">
                                            &#9998; Edit
                                        </button>

                                        <!-- Soft Delete Button (Key Question 3: Confirmed Before Execution) -->
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmSoftDelete(<?php echo $s['student_id']; ?>, '<?php echo htmlspecialchars(addslashes($s['fullname'])); ?>', '<?php echo htmlspecialchars($s['roll_no']); ?>')"
                                                title="Soft-delete (archive) this student">
                                            &#128465; Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div class="pagination-bar">
            <div style="font-size: 0.85rem; color: #64748b;">
                Showing <strong><?php echo $total_records > 0 ? ($offset + 1) : 0; ?></strong> to
                <strong><?php echo min($offset + $per_page, $total_records); ?></strong> of
                <strong><?php echo $total_records; ?></strong> student entries
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination-nav" aria-label="Students Table Pagination">
                    <a href="<?php echo htmlspecialchars(build_url(['page' => $page - 1])); ?>"
                       class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo; Prev</a>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="<?php echo htmlspecialchars(build_url(['page' => $p])); ?>"
                           class="page-link <?php echo $page === $p ? 'active' : ''; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <a href="<?php echo htmlspecialchars(build_url(['page' => $page + 1])); ?>"
                       class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &raquo;</a>
                </nav>
            <?php endif; ?>
        </div>

    </div><!-- /.crud-container -->

    <!-- ===================================================================== -->
    <!-- ADD STUDENT MODAL -->
    <!-- ===================================================================== -->
    <div id="addModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="addModalTitle">&#10010; Enroll New Student</h3>
                <button type="button" class="close-btn" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="students_crud.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="modal-grid">
                    <div class="form-group">
                        <label for="add_roll">Roll Number *</label>
                        <input type="text" id="add_roll" name="roll_no" class="form-input" required
                               placeholder="e.g. 25CE145" pattern="[A-Za-z0-9]{3,30}"
                               title="Alpha-numeric Roll Number (3 to 30 characters)">
                    </div>

                    <div class="form-group">
                        <label for="add_name">Full Name *</label>
                        <input type="text" id="add_name" name="fullname" class="form-input" required
                               placeholder="e.g. Rahul Sharma" minlength="2">
                    </div>

                    <div class="form-group">
                        <label for="add_email">Email Address *</label>
                        <input type="email" id="add_email" name="email" class="form-input" required
                               placeholder="student@college.edu">
                    </div>

                    <div class="form-group">
                        <label for="add_mobile">Mobile Number *</label>
                        <input type="tel" id="add_mobile" name="mobile" class="form-input" required
                               placeholder="10 digit number" pattern="[0-9]{10}">
                    </div>

                    <div class="form-group">
                        <label for="add_course">Course *</label>
                        <select id="add_course" name="course_id" class="form-select" required>
                            <option value="">Select Department Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>">
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_year">Year of Study *</label>
                        <select id="add_year" name="year_of_study" class="form-select" required>
                            <option value="1">1st Year (Freshman)</option>
                            <option value="2">2nd Year (Sophomore)</option>
                            <option value="3" selected>3rd Year (Junior)</option>
                            <option value="4">4th Year (Senior)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_gender">Gender *</label>
                        <select id="add_gender" name="gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_gpa">GPA (0.00 - 10.00)</label>
                        <input type="number" id="add_gpa" name="gpa" class="form-input"
                               min="0" max="10" step="0.01" value="8.00">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="add_status">Enrollment Status</label>
                        <select id="add_status" name="status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Graduated">Graduated</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">&#10003; Save &amp; Enroll</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- EDIT STUDENT MODAL -->
    <!-- ===================================================================== -->
    <div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="editModalTitle">&#9998; Edit Student Record</h3>
                <button type="button" class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="students_crud.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" id="edit_id" name="student_id" value="">

                <div class="modal-grid">
                    <div class="form-group">
                        <label for="edit_roll">Roll Number *</label>
                        <input type="text" id="edit_roll" name="roll_no" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_name">Full Name *</label>
                        <input type="text" id="edit_name" name="fullname" class="form-input" required minlength="2">
                    </div>

                    <div class="form-group">
                        <label for="edit_email">Email Address *</label>
                        <input type="email" id="edit_email" name="email" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_mobile">Mobile Number *</label>
                        <input type="tel" id="edit_mobile" name="mobile" class="form-input" required pattern="[0-9]{10}">
                    </div>

                    <div class="form-group">
                        <label for="edit_course">Course *</label>
                        <select id="edit_course" name="course_id" class="form-select" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>">
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_year">Year of Study *</label>
                        <select id="edit_year" name="year_of_study" class="form-select" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_gender">Gender *</label>
                        <select id="edit_gender" name="gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_gpa">GPA (0.00 - 10.00)</label>
                        <input type="number" id="edit_gpa" name="gpa" class="form-input"
                               min="0" max="10" step="0.01">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_status">Enrollment Status</label>
                        <select id="edit_status" name="status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Graduated">Graduated</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">&#10003; Update Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- DELETE CONFIRMATION MODAL (Key Question 3: Confirmed Before Execution) -->
    <!-- ===================================================================== -->
    <div id="deleteModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="delModalTitle">
        <div class="modal-dialog" style="max-width: 480px;">
            <div class="modal-header">
                <h3 id="delModalTitle" style="color: var(--danger-color); display:flex; align-items:center; gap:8px;">
                    <span>&#9888;</span> Confirm Deletion
                </h3>
                <button type="button" class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form id="deleteForm" method="POST" action="students_crud.php">
                <input type="hidden" id="del_action" name="action" value="soft_delete">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" id="del_id" name="student_id" value="">

                <p style="font-size: 0.95rem; line-height: 1.5; color: #334155;">
                    Are you sure you want to delete <strong id="del_student_name">Student</strong>
                    (<code id="del_roll_no" style="font-weight:700;">ROLL</code>)?
                </p>

                <div id="del_note" style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; font-size:0.82rem; color:#991b1b; margin-bottom:16px;">
                    &#128712; <strong>Soft Delete (Advanced Extension):</strong> This record will be archived and hidden from the active directory. You can restore it at any time.
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="del_confirm_btn">&#128465; Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function openEditModal(student) {
            document.getElementById('edit_id').value     = student.student_id;
            document.getElementById('edit_roll').value   = student.roll_no;
            document.getElementById('edit_name').value   = student.fullname;
            document.getElementById('edit_email').value  = student.email;
            document.getElementById('edit_mobile').value = student.mobile;
            document.getElementById('edit_course').value = student.course_id;
            document.getElementById('edit_year').value   = student.year_of_study;
            document.getElementById('edit_gender').value = student.gender;
            document.getElementById('edit_gpa').value    = parseFloat(student.gpa).toFixed(2);
            document.getElementById('edit_status').value = student.status;

            document.getElementById('editModal').classList.add('show');
        }

        function confirmSoftDelete(id, name, roll) {
            document.getElementById('del_id').value           = id;
            document.getElementById('del_action').value       = 'soft_delete';
            document.getElementById('del_student_name').innerText = name;
            document.getElementById('del_roll_no').innerText      = roll;
            document.getElementById('del_note').innerHTML =
                '&#128712; <strong>Soft Delete (Advanced Extension):</strong> This record will be archived. You can view or restore it anytime from the <em>Archived</em> tab.';
            document.getElementById('del_confirm_btn').innerHTML = '&#128465; Soft Delete Student';
            document.getElementById('deleteModal').classList.add('show');
        }

        function confirmHardDelete(id, name, roll) {
            document.getElementById('del_id').value           = id;
            document.getElementById('del_action').value       = 'hard_delete';
            document.getElementById('del_student_name').innerText = name;
            document.getElementById('del_roll_no').innerText      = roll;
            document.getElementById('del_note').innerHTML =
                '&#9888; <strong>Warning (Permanent Purge):</strong> This action cannot be undone. All linked registration data for this student will also be removed.';
            document.getElementById('del_confirm_btn').innerHTML = '&#10060; Permanently Purge';
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close on escape key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(function(m) {
                    m.classList.remove('show');
                });
            }
        });

        // Close on clicking backdrop
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>
</body>

</html>
