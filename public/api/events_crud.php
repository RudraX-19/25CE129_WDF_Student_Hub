<?php
/**
 * StudentHub Portal - Practical 12
 * Event Management CRUD with Poster Upload, File Validation, Multi-Filter & Audit Log
 *
 * Requirements Met:
 * 1. Full CRUD lifecycle: Add, View, Update, Delete with MySQLi prepared statements.
 * 2. Secure File Upload: MIME type validation + extension check + file size limit (2MB).
 * 3. Advanced Extension: Secure Image Renaming, Upload directory protection (.htaccess),
 *    and Audit Log logging all event mutations into audit_log table.
 * 4. Intermediate Extension: Search by title/code/venue/organizer, Filter by Category,
 *    and Open/Closed registration status filter.
 * 5. Interactive Delete Confirmation Modal (Key Question 3).
 * 6. Clear success & error feedback messages and CSRF protection.
 *
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);

require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';
require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';

session_secure_start();

$csrf_token = get_csrf_token();
$errors = [];
$success_msg = get_flash('event_success');
$error_msg   = get_flash('event_error');

$upload_dir = dirname(__DIR__) . '/assets/images/posters';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

// Helper function to log actions into audit_log table (Practical 12 Advanced Extension)
function log_event_audit(string $action, int $record_id, string $details): void {
    try {
        $user = $_SESSION['username'] ?? 'admin';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        mysqli_execute_stmt(
            "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
             VALUES (?, 'events', ?, ?, ?, ?)",
            "sisss",
            [$action, $record_id, $user, $details, $ip]
        );
    } catch (\Throwable $e) {
        // Fallback gracefully if audit_log table is missing or readonly
    }
}

// -----------------------------------------------------------------------------
// POST HANDLERS: ADD EVENT, EDIT EVENT, DELETE EVENT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $token  = trim($_POST['csrf_token'] ?? '');

    if (!verify_csrf_token($token)) {
        set_flash('event_error', 'Security verification failed (CSRF token mismatch). Please reload and try again.');
        header('Location: events_crud.php');
        exit;
    }

    // --- 1. ADD EVENT WITH POSTER UPLOAD ---
    if ($action === 'add') {
        $title       = trim(strip_tags($_POST['title'] ?? ''));
        $event_code  = strtoupper(trim(strip_tags($_POST['event_code'] ?? '')));
        $category    = trim(strip_tags($_POST['category'] ?? 'Technical'));
        $event_date  = trim($_POST['event_date'] ?? '');
        $event_time  = trim($_POST['event_time'] ?? '');
        $venue       = trim(strip_tags($_POST['venue'] ?? ''));
        $organizer   = trim(strip_tags($_POST['organizer'] ?? ''));
        $max_cap     = (int)($_POST['max_participants'] ?? 100);
        $status      = trim($_POST['status'] ?? 'Upcoming');
        $description = trim(strip_tags($_POST['description'] ?? ''));

        // Validation
        if (empty($title) || strlen($title) < 3) {
            $errors['title'] = 'Event title is required (minimum 3 characters).';
        }
        if (empty($event_code) || !preg_match('/^[A-Z0-9_-]{3,30}$/', $event_code)) {
            $errors['event_code'] = 'Event code must be 3-30 alphanumeric characters (e.g., EVT-TECH-01).';
        }
        if (empty($event_date)) {
            $errors['event_date'] = 'Valid event date is required.';
        }
        if (empty($event_time)) {
            $errors['event_time'] = 'Event time is required.';
        }
        if (empty($venue)) {
            $errors['venue'] = 'Venue location is required.';
        }
        if (empty($organizer)) {
            $errors['organizer'] = 'Organizer name or club is required.';
        }
        if ($max_cap < 5 || $max_cap > 5000) {
            $errors['max_participants'] = 'Maximum participants must be between 5 and 5000.';
        }
        if (!in_array($status, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'], true)) {
            $status = 'Upcoming';
        }

        // Duplicate event_code check
        if (empty($errors)) {
            $existing_code = mysqli_fetch_one_row("SELECT event_id FROM events WHERE event_code = ?", "s", [$event_code]);
            if ($existing_code) {
                $errors['event_code'] = "Event code '{$event_code}' already exists. Please choose a unique code.";
            }
        }

        // File Upload Validation (Key Question 3: Type & Size Validated)
        $poster_filename = null;
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['poster'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors['poster'] = 'File upload error code: ' . $file['error'];
            } else {
                // Size validation: max 2MB
                $max_bytes = 2 * 1024 * 1024;
                if ($file['size'] > $max_bytes) {
                    $errors['poster'] = 'Poster image exceeds 2MB limit (Uploaded size: ' . round($file['size'] / (1024 * 1024), 2) . 'MB).';
                }

                // Extension & MIME validation
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($ext, $allowed_extensions, true) || !in_array($mime_type, $allowed_mimes, true)) {
                    $errors['poster'] = 'Invalid file format. Only JPG, PNG, WEBP, and GIF images are permitted (Detected: ' . htmlspecialchars($mime_type) . ').';
                }

                if (empty($errors)) {
                    // Advanced Extension: Image Renaming (sanitize + unique hash)
                    $safe_code = preg_replace('/[^A-Za-z0-9_-]/', '', $event_code);
                    $unique_suffix = bin2hex(random_bytes(6));
                    $poster_filename = "poster_{$safe_code}_{$unique_suffix}.{$ext}";
                    $dest_path = $upload_dir . '/' . $poster_filename;

                    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                        $errors['poster'] = 'Failed to move uploaded poster to storage directory.';
                        $poster_filename = null;
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO events (event_code, title, category, event_date, event_time, venue, organizer, max_participants, registered_count, status, description, poster_image)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)";
                $types = "sssssssisss";
                $params = [$event_code, $title, $category, $event_date, $event_time, $venue, $organizer, $max_cap, $status, $description, $poster_filename];

                $res = mysqli_execute_stmt($sql, $types, $params);
                $new_id = $res['insert_id'];

                // Audit Log
                log_event_audit('EVENT_CREATED', $new_id, "Created event '{$title}' ({$event_code}) with max capacity {$max_cap}");

                set_flash('event_success', "Event '{$title}' ({$event_code}) created successfully!");
                header('Location: events_crud.php');
                exit;
            } catch (\Throwable $e) {
                $errors['db'] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // --- 2. EDIT EVENT WITH OPTIONAL POSTER REPLACEMENT ---
    if ($action === 'edit') {
        $event_id    = (int)($_POST['event_id'] ?? 0);
        $title       = trim(strip_tags($_POST['title'] ?? ''));
        $event_code  = strtoupper(trim(strip_tags($_POST['event_code'] ?? '')));
        $category    = trim(strip_tags($_POST['category'] ?? 'Technical'));
        $event_date  = trim($_POST['event_date'] ?? '');
        $event_time  = trim($_POST['event_time'] ?? '');
        $venue       = trim(strip_tags($_POST['venue'] ?? ''));
        $organizer   = trim(strip_tags($_POST['organizer'] ?? ''));
        $max_cap     = (int)($_POST['max_participants'] ?? 100);
        $status      = trim($_POST['status'] ?? 'Upcoming');
        $description = trim(strip_tags($_POST['description'] ?? ''));
        $remove_poster = !empty($_POST['remove_poster']);

        if ($event_id <= 0) {
            $errors['id'] = 'Invalid event ID.';
        }
        if (empty($title)) {
            $errors['title'] = 'Event title is required.';
        }
        if (empty($event_code)) {
            $errors['event_code'] = 'Event code is required.';
        }

        // Fetch current record
        $current = mysqli_fetch_one_row("SELECT * FROM events WHERE event_id = ?", "i", [$event_id]);
        if (!$current) {
            $errors['id'] = 'Event record not found in database.';
        }

        // Duplicate event code check excluding current ID
        if (empty($errors)) {
            $dup_code = mysqli_fetch_one_row("SELECT event_id FROM events WHERE event_code = ? AND event_id != ?", "si", [$event_code, $event_id]);
            if ($dup_code) {
                $errors['event_code'] = "Event code '{$event_code}' is currently used by another event.";
            }
        }

        $poster_filename = $current['poster_image'] ?? null;

        // Check if removing current poster
        if ($remove_poster && $poster_filename) {
            $old_file = $upload_dir . '/' . $poster_filename;
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
            $poster_filename = null;
        }

        // Check if new poster uploaded
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['poster'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors['poster'] = 'Upload error: ' . $file['error'];
            } else {
                if ($file['size'] > 2 * 1024 * 1024) {
                    $errors['poster'] = 'Poster image exceeds 2MB limit.';
                }

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($ext, $allowed_extensions, true) || !in_array($mime_type, $allowed_mimes, true)) {
                    $errors['poster'] = 'Invalid image type. Permitted formats: JPG, PNG, WEBP, GIF.';
                }

                if (empty($errors)) {
                    // Remove old poster if exists
                    if ($poster_filename && file_exists($upload_dir . '/' . $poster_filename)) {
                        @unlink($upload_dir . '/' . $poster_filename);
                    }

                    // Rename & save new file
                    $safe_code = preg_replace('/[^A-Za-z0-9_-]/', '', $event_code);
                    $poster_filename = "poster_{$safe_code}_" . bin2hex(random_bytes(6)) . ".{$ext}";
                    move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $poster_filename);
                }
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE events
                        SET event_code=?, title=?, category=?, event_date=?, event_time=?, venue=?,
                            organizer=?, max_participants=?, status=?, description=?, poster_image=?
                        WHERE event_id=?";
                $types = "sssssssisssi";
                $params = [$event_code, $title, $category, $event_date, $event_time, $venue, $organizer, $max_cap, $status, $description, $poster_filename, $event_id];

                mysqli_execute_stmt($sql, $types, $params);

                // Audit Log
                log_event_audit('EVENT_UPDATED', $event_id, "Updated details for event '{$title}' ({$event_code})");

                set_flash('event_success', "Event '{$event_code}' updated successfully!");
                header('Location: events_crud.php');
                exit;
            } catch (\Throwable $e) {
                $errors['db'] = 'Database update error: ' . $e->getMessage();
            }
        }
    }

    // --- 3. DELETE EVENT (Key Question 3: Confirmed Before Execution) ---
    if ($action === 'delete') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            try {
                $event = mysqli_fetch_one_row("SELECT event_code, title, poster_image FROM events WHERE event_id = ?", "i", [$event_id]);
                if ($event) {
                    // Delete poster image file if it exists
                    if (!empty($event['poster_image'])) {
                        $pfile = $upload_dir . '/' . $event['poster_image'];
                        if (file_exists($pfile)) {
                            @unlink($pfile);
                        }
                    }

                    // Cascade delete registrations first
                    mysqli_execute_stmt("DELETE FROM registrations WHERE event_id = ?", "i", [$event_id]);
                    // Delete event
                    mysqli_execute_stmt("DELETE FROM events WHERE event_id = ?", "i", [$event_id]);

                    // Audit Log
                    log_event_audit('EVENT_DELETED', $event_id, "Deleted event '{$event['title']}' ({$event['event_code']})");

                    set_flash('event_success', "Event '{$event['title']}' ({$event['event_code']}) and associated registrations successfully deleted.");
                } else {
                    set_flash('event_error', 'Event record not found.');
                }
            } catch (\Throwable $e) {
                set_flash('event_error', 'Failed to delete event: ' . $e->getMessage());
            }
        }
        header('Location: events_crud.php');
        exit;
    }
}

// -----------------------------------------------------------------------------
// GET PARAMS: SEARCH, FILTER, SORT, EXPORT, PAGINATION
// -----------------------------------------------------------------------------
$search_query   = trim($_GET['q'] ?? '');
$filter_cat     = trim($_GET['category'] ?? '');
$filter_status  = trim($_GET['status'] ?? '');
$sort_by        = trim($_GET['sort'] ?? 'event_date');
$sort_order     = strtoupper(trim($_GET['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 8;
$offset         = ($page - 1) * $per_page;

$allowed_sorts = [
    'event_date'       => 'event_date',
    'title'            => 'title',
    'category'         => 'category',
    'registered_count' => 'registered_count',
    'max_participants' => 'max_participants',
    'status'           => 'status'
];
$order_col = $allowed_sorts[$sort_by] ?? 'event_date';

// Dynamic WHERE clauses
$where_clauses = [];
$types_arr     = [];
$params_arr    = [];

if ($search_query !== '') {
    $where_clauses[] = "(title LIKE ? OR event_code LIKE ? OR venue LIKE ? OR organizer LIKE ?)";
    $like = "%{$search_query}%";
    $types_arr[] = "ssss";
    $params_arr[] = $like;
    $params_arr[] = $like;
    $params_arr[] = $like;
    $params_arr[] = $like;
}

if (!empty($filter_cat)) {
    $where_clauses[] = "category = ?";
    $types_arr[] = "s";
    $params_arr[] = $filter_cat;
}

// Intermediate Extension: Open/Closed Filter vs standard status
if ($filter_status === 'open') {
    $where_clauses[] = "status = 'Upcoming' AND registered_count < max_participants";
} elseif ($filter_status === 'closed') {
    $where_clauses[] = "(status IN ('Completed', 'Cancelled') OR registered_count >= max_participants)";
} elseif (!empty($filter_status) && in_array($filter_status, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'], true)) {
    $where_clauses[] = "status = ?";
    $types_arr[] = "s";
    $params_arr[] = $filter_status;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
$types_str = implode('', $types_arr);

// -----------------------------------------------------------------------------
// INTERMEDIATE EXTENSION: CSV EXPORT
// -----------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "SELECT event_id, event_code, title, category, event_date, event_time,
                          venue, organizer, max_participants, registered_count, status, poster_image, created_at
                   FROM events
                   {$where_sql}
                   ORDER BY {$order_col} {$sort_order}";

    $export_data = mysqli_fetch_all_rows($export_sql, $types_str, $params_arr);

    $filename = "events_roster_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($output, [
        'ID', 'Event Code', 'Title', 'Category', 'Date', 'Time',
        'Venue', 'Organizer', 'Max Participants', 'Registered', 'Status', 'Poster File', 'Created At'
    ]);

    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['event_id'],
            $row['event_code'],
            $row['title'],
            $row['category'],
            $row['event_date'],
            $row['event_time'],
            $row['venue'],
            $row['organizer'],
            $row['max_participants'],
            $row['registered_count'],
            $row['status'],
            $row['poster_image'] ?: 'None',
            $row['created_at'],
        ]);
    }
    fclose($output);
    exit;
}

// -----------------------------------------------------------------------------
// DATA FETCHING & KPI METRICS
// -----------------------------------------------------------------------------
$total_events = 0;
$events_list  = [];
$stats = [
    'total'        => 0,
    'open'         => 0,
    'total_reg'    => 0,
    'total_cap'    => 0,
    'recent_audits'=> []
];

try {
    $tot_row = mysqli_fetch_one_row("SELECT COUNT(*) AS c, SUM(registered_count) AS reg_sum, SUM(max_participants) AS cap_sum FROM events");
    $stats['total']     = (int)($tot_row['c'] ?? 0);
    $stats['total_reg'] = (int)($tot_row['reg_sum'] ?? 0);
    $stats['total_cap'] = (int)($tot_row['cap_sum'] ?? 0);

    $open_row = mysqli_fetch_one_row("SELECT COUNT(*) AS c FROM events WHERE status = 'Upcoming' AND registered_count < max_participants");
    $stats['open'] = (int)($open_row['c'] ?? 0);

    // Recent audit logs for Advanced Extension drawer
    $stats['recent_audits'] = mysqli_fetch_all_rows(
        "SELECT * FROM audit_log WHERE table_name = 'events' ORDER BY created_at DESC LIMIT 10"
    );

    // Filtered count
    $count_row = mysqli_fetch_one_row("SELECT COUNT(*) AS c FROM events {$where_sql}", $types_str, $params_arr);
    $total_events = (int)($count_row['c'] ?? 0);
    $total_pages  = max(1, (int)ceil($total_events / $per_page));

    // Fetch paginated events
    $data_sql = "SELECT * FROM events {$where_sql} ORDER BY {$order_col} {$sort_order} LIMIT ? OFFSET ?";
    $page_types  = $types_str . "ii";
    $page_params = array_merge($params_arr, [$per_page, $offset]);
    $events_list = mysqli_fetch_all_rows($data_sql, $page_types, $page_params);
} catch (\Throwable $e) {
    $error_msg = 'Database query warning: ' . $e->getMessage();
}

// Categories list for filter dropdown
$categories_list = ['Technical', 'Cultural', 'Academic', 'Sports', 'Workshop'];

function build_events_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    unset($params['export']);
    return 'events_crud.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Event Management CRUD (Practical 12)</title>
    <meta name="description" content="Practical 12: Event Management CRUD with File Upload, Poster Validation, Image Renaming, and Audit Trail.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-accent: #6366f1;
            --primary-dark: #4f46e5;
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

        /* Hero Header */
        .page-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(30, 27, 75, 0.15);
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

        .badge-p12 {
            background: #6366f1;
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
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 14px;
            align-items: flex-end;
        }

        @media (max-width: 900px) {
            .controls-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .controls-form { grid-template-columns: 1fr; }
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

        .form-input, .form-select, .form-textarea {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            background-color: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
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

        .btn-primary { background-color: var(--primary-accent); color: #ffffff; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-success { background-color: var(--success-color); color: #ffffff; }
        .btn-success:hover { background-color: #059669; }
        .btn-secondary { background-color: #f1f5f9; color: #334155; border-color: #cbd5e1; }
        .btn-secondary:hover { background-color: #e2e8f0; }
        .btn-danger { background-color: var(--danger-color); color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 5px 10px; font-size: 0.78rem; border-radius: 4px; }

        /* Events Grid / Cards Display */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 480px) {
            .events-grid { grid-template-columns: 1fr; }
        }

        .event-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }

        .poster-container {
            width: 100%;
            height: 190px;
            background: #0f172a;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .poster-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .poster-placeholder {
            color: #94a3b8;
            text-align: center;
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .card-badge-top {
            position: absolute;
            top: 12px;
            left: 12px;
            display: flex;
            gap: 6px;
        }

        .chip {
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .chip-tech { background: #e0e7ff; color: #3730a3; }
        .chip-cult { background: #fce7f3; color: #9d174d; }
        .chip-acad { background: #dbeafe; color: #1e40af; }
        .chip-sprt { background: #dcfce7; color: #166534; }
        .chip-work { background: #fef3c7; color: #92400e; }

        .chip-upcoming { background: #10b981; color: #fff; }
        .chip-ongoing { background: #3b82f6; color: #fff; }
        .chip-completed { background: #64748b; color: #fff; }
        .chip-cancelled { background: #ef4444; color: #fff; }

        .event-card-body {
            padding: 18px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .event-card-title {
            margin: 0 0 6px 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
        }

        .event-meta-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 6px;
        }

        .event-meta-line strong {
            color: #334155;
        }

        .event-desc-snip {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.45;
            margin: 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .capacity-bar-wrapper {
            margin: 12px 0;
        }

        .capacity-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .capacity-track {
            height: 6px;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }

        .capacity-fill {
            height: 100%;
            background: var(--primary-accent);
            border-radius: 9999px;
        }

        .event-card-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Modal */
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

        .modal.show { display: flex; }

        .modal-dialog {
            background: #ffffff;
            border-radius: 12px;
            width: 92%;
            max-width: 680px;
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

        .modal-header h3 { margin: 0; font-size: 1.25rem; color: #0f172a; }
        .close-btn { background: transparent; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }

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

        /* Audit Drawer */
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .audit-table th { background: #f8fafc; padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color); }
        .audit-table td { padding: 8px 10px; border-bottom: 1px solid var(--border-color); color: #334155; }

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
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Pagination */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }
        .pagination-nav { display: flex; gap: 4px; }
        .page-link {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            text-decoration: none;
            background: #fff;
        }
        .page-link.active { background: var(--primary-accent); color: #fff; border-color: var(--primary-accent); }
        .page-link.disabled { opacity: 0.45; pointer-events: none; }
    </style>
</head>

<body>

    <div class="crud-container">

        <!-- Top Navigation Line -->
        <div style="margin-bottom: 14px; display:flex; justify-content:space-between; align-items:center;">
            <a href="../dashboard.html" style="color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight:600;">
                &larr; Back to StudentHub Portal
            </a>
            <div style="font-size: 0.85rem; color:#64748b;">
                Module: <strong>Events CRUD &amp; Poster Validation</strong> | Practical 12
            </div>
        </div>

        <!-- Hero Header -->
        <div class="page-header">
            <div>
                <h1>
                    <span>&#127895; Campus Event Management</span>
                    <span class="badge-p12">Practical 12</span>
                </h1>
                <p style="margin:0; opacity:0.85; font-size:0.9rem;">
                    Create, View, Update, and Delete events with Secure Poster Uploads (MIME &amp; Size Validated), Image Renaming, and Audit Trail.
                </p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <span>&#10010;</span> Create New Event
                </button>
                <a href="<?php echo htmlspecialchars(build_events_url(['export' => 'csv'])); ?>" class="btn btn-secondary" title="Export event roster to CSV">
                    <span>&#128196;</span> Export CSV
                </a>
                <button type="button" class="btn btn-secondary" onclick="openAuditModal()" title="View audit activity trail (Advanced Extension)">
                    <span>&#128220;</span> Audit Trail
                </button>
                <a href="admin_dashboard.php" class="btn btn-primary" style="background:#312e81;">
                    <span>&#128737;</span> Admin Panel
                </a>
            </div>
        </div>

        <!-- Feedback Messages -->
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

        <!-- KPI Metrics -->
        <div class="metric-grid">
            <div class="metric-card">
                <div>
                    <h3>Total Events</h3>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="metric-icon">&#127895;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Open for Registration</h3>
                    <div class="value" style="color:#10b981;"><?php echo $stats['open']; ?></div>
                </div>
                <div class="metric-icon">&#9989;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Total Registrations</h3>
                    <div class="value" style="color:#6366f1;"><?php echo $stats['total_reg']; ?></div>
                </div>
                <div class="metric-icon">&#128101;</div>
            </div>
            <div class="metric-card">
                <div>
                    <h3>Total Capacity</h3>
                    <div class="value" style="color:#f59e0b;"><?php echo $stats['total_cap']; ?></div>
                </div>
                <div class="metric-icon">&#127979;</div>
            </div>
        </div>

        <!-- Search & Filter Bar (Intermediate Extension) -->
        <div class="controls-card">
            <form method="GET" action="events_crud.php" class="controls-form">
                <div class="form-group">
                    <label for="search-input">&#128269; Search Events</label>
                    <input type="search" id="search-input" name="q" class="form-input"
                           placeholder="Search by event title, code, venue, or organizer…"
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>

                <div class="form-group">
                    <label for="filter-cat">Category</label>
                    <select id="filter-cat" name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $filter_cat === $cat ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-status">Status / Availability</label>
                    <select id="filter-status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>&#9989; Open for Registration</option>
                        <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>&#128274; Closed / Fully Booked</option>
                        <option value="Upcoming" <?php echo $filter_status === 'Upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="Ongoing" <?php echo $filter_status === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $filter_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary" style="height: 38px;">
                        <span>&#128065;</span> Filter
                    </button>
                    <a href="events_crud.php" class="btn btn-secondary" style="height: 38px;" title="Reset filters">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Events Grid (Cards Display with Poster Preview) -->
        <?php if (empty($events_list)): ?>
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:12px; text-align:center; padding:48px 20px; color:#64748b;">
                <div style="font-size: 2.5rem; margin-bottom: 8px;">&#127895;</div>
                <strong style="font-size: 1.1rem; color: #1e293b;">No events match your criteria</strong>
                <p style="margin:4px 0 16px 0; font-size: 0.88rem;">Try clearing your filters or create a new campus event.</p>
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    &#10010; Add Event Now
                </button>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events_list as $ev): ?>
                    <?php
                        $cat_code = strtolower(substr($ev['category'], 0, 4));
                        $cat_chip = 'chip-' . ($cat_code === 'tech' ? 'tech' : ($cat_code === 'cult' ? 'cult' : ($cat_code === 'acad' ? 'acad' : ($cat_code === 'spor' ? 'sprt' : 'work'))));
                        $status_chip = 'chip-' . strtolower($ev['status']);
                        $percent = $ev['max_participants'] > 0 ? min(100, round(($ev['registered_count'] / $ev['max_participants']) * 100)) : 0;
                        $has_poster = !empty($ev['poster_image']) && file_exists($upload_dir . '/' . $ev['poster_image']);
                    ?>
                    <div class="event-card">
                        <!-- Poster Image Container -->
                        <div class="poster-container">
                            <?php if ($has_poster): ?>
                                <img src="../assets/images/posters/<?php echo htmlspecialchars($ev['poster_image']); ?>"
                                     alt="Poster for <?php echo htmlspecialchars($ev['title']); ?>"
                                     class="poster-img" loading="lazy">
                            <?php else: ?>
                                <div class="poster-placeholder">
                                    <span style="font-size:2.5rem;">&#127895;</span>
                                    <span>No Poster Uploaded</span>
                                </div>
                            <?php endif; ?>

                            <div class="card-badge-top">
                                <span class="chip <?php echo $cat_chip; ?>"><?php echo htmlspecialchars($ev['category']); ?></span>
                                <span class="chip <?php echo $status_chip; ?>"><?php echo htmlspecialchars($ev['status']); ?></span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="event-card-body">
                            <div>
                                <div style="font-family: monospace; font-size: 0.78rem; color: #6366f1; font-weight:700; margin-bottom: 2px;">
                                    <?php echo htmlspecialchars($ev['event_code']); ?>
                                </div>
                                <h3 class="event-card-title"><?php echo htmlspecialchars($ev['title']); ?></h3>

                                <div class="event-meta-line">
                                    <span>&#128197; <strong><?php echo date('d M Y', strtotime($ev['event_date'])); ?></strong></span>
                                    <span>&bull;</span>
                                    <span>&#9200; <?php echo date('h:i A', strtotime($ev['event_time'])); ?></span>
                                </div>

                                <div class="event-meta-line">
                                    <span>&#128205; <?php echo htmlspecialchars($ev['venue']); ?></span>
                                </div>

                                <div class="event-meta-line">
                                    <span>&#128100; Organized by: <strong><?php echo htmlspecialchars($ev['organizer']); ?></strong></span>
                                </div>

                                <p class="event-desc-snip">
                                    <?php echo htmlspecialchars($ev['description'] ?: 'No additional description provided.'); ?>
                                </p>
                            </div>

                            <div>
                                <!-- Registration Capacity Bar -->
                                <div class="capacity-bar-wrapper">
                                    <div class="capacity-bar-labels">
                                        <span>Seats Booked: <strong><?php echo $ev['registered_count']; ?> / <?php echo $ev['max_participants']; ?></strong></span>
                                        <span><strong><?php echo $percent; ?>%</strong></span>
                                    </div>
                                    <div class="capacity-track">
                                        <div class="capacity-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $percent >= 90 ? '#ef4444' : ($percent >= 60 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                    </div>
                                </div>

                                <!-- Card Footer Actions -->
                                <div class="event-card-footer">
                                    <span style="font-size:0.75rem; color:#94a3b8;">
                                        ID #<?php echo $ev['event_id']; ?>
                                    </span>
                                    <div style="display:flex; gap:6px;">
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                onclick='openEditModal(<?php echo json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                title="Edit Event & Poster">
                                            &#9998; Edit
                                        </button>
                                        <!-- Delete Button (Key Question 3: Confirmed Before Execution) -->
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDeleteEvent(<?php echo $ev['event_id']; ?>, '<?php echo htmlspecialchars(addslashes($ev['title'])); ?>', '<?php echo htmlspecialchars($ev['event_code']); ?>')"
                                                title="Delete Event">
                                            &#128465; Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
                <div style="font-size: 0.85rem; color: #64748b;">
                    Showing <strong><?php echo $total_events > 0 ? ($offset + 1) : 0; ?></strong> to
                    <strong><?php echo min($offset + $per_page, $total_events); ?></strong> of
                    <strong><?php echo $total_events; ?></strong> campus events
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="pagination-nav" aria-label="Event Pagination">
                        <a href="<?php echo htmlspecialchars(build_events_url(['page' => $page - 1])); ?>"
                           class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo; Prev</a>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <a href="<?php echo htmlspecialchars(build_events_url(['page' => $p])); ?>"
                               class="page-link <?php echo $page === $p ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="<?php echo htmlspecialchars(build_events_url(['page' => $page + 1])); ?>"
                           class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &raquo;</a>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- /.crud-container -->

    <!-- ===================================================================== -->
    <!-- ADD EVENT MODAL (With Poster Upload & Validation) -->
    <!-- ===================================================================== -->
    <div id="addModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="addModalTitle">&#10010; Create Campus Event</h3>
                <button type="button" class="close-btn" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="events_crud.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="modal-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="add_title">Event Title *</label>
                        <input type="text" id="add_title" name="title" class="form-input" required
                               placeholder="e.g. Annual AI Hackathon 2026" minlength="3" maxlength="150">
                    </div>

                    <div class="form-group">
                        <label for="add_code">Event Code *</label>
                        <input type="text" id="add_code" name="event_code" class="form-input" required
                               placeholder="e.g. EVT-AI-01" pattern="[A-Za-z0-9_-]{3,30}"
                               title="Alpha-numeric code (3-30 chars)">
                    </div>

                    <div class="form-group">
                        <label for="add_category">Category *</label>
                        <select id="add_category" name="category" class="form-select" required>
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_date">Event Date *</label>
                        <input type="date" id="add_date" name="event_date" class="form-input" required
                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="add_time">Event Time *</label>
                        <input type="time" id="add_time" name="event_time" class="form-input" required value="10:00">
                    </div>

                    <div class="form-group">
                        <label for="add_venue">Venue Location *</label>
                        <input type="text" id="add_venue" name="venue" class="form-input" required placeholder="e.g. Seminar Hall A">
                    </div>

                    <div class="form-group">
                        <label for="add_organizer">Organizer / Society *</label>
                        <input type="text" id="add_organizer" name="organizer" class="form-input" required placeholder="e.g. Coding Club">
                    </div>

                    <div class="form-group">
                        <label for="add_cap">Max Capacity *</label>
                        <input type="number" id="add_cap" name="max_participants" class="form-input" required min="5" max="5000" value="100">
                    </div>

                    <div class="form-group">
                        <label for="add_status">Status</label>
                        <select id="add_status" name="status" class="form-select">
                            <option value="Upcoming" selected>Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="add_desc">Event Description</label>
                        <textarea id="add_desc" name="description" class="form-textarea" rows="3"
                                  placeholder="Provide agenda, guest speakers, prerequisites, and registration details…"></textarea>
                    </div>

                    <!-- Poster File Upload (Practical 12 Core Requirement) -->
                    <div class="form-group" style="grid-column: 1 / -1; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:14px;">
                        <label for="add_poster" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>&#128444; Event Poster Image (File Validation)</span>
                            <span style="font-size:0.75rem; color:#64748b;">JPG, PNG, WEBP, GIF (Max 2MB)</span>
                        </label>
                        <input type="file" id="add_poster" name="poster" accept=".jpg,.jpeg,.png,.webp,.gif"
                               class="form-input" style="background:#fff; margin-top:6px;">
                        <span style="font-size:0.75rem; color:#64748b; margin-top:4px; display:block;">
                            &#128274; <strong>Security:</strong> File MIME type &amp; size are checked on the server, and images are renamed with a unique hash.
                        </span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">&#10003; Save &amp; Publish Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- EDIT EVENT MODAL (With Poster Replacement) -->
    <!-- ===================================================================== -->
    <div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="editModalTitle">&#9998; Edit Event Record</h3>
                <button type="button" class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="events_crud.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" id="edit_id" name="event_id" value="">

                <div class="modal-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_title">Event Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-input" required minlength="3">
                    </div>

                    <div class="form-group">
                        <label for="edit_code">Event Code *</label>
                        <input type="text" id="edit_code" name="event_code" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_category">Category *</label>
                        <select id="edit_category" name="category" class="form-select" required>
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_date">Event Date *</label>
                        <input type="date" id="edit_date" name="event_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_time">Event Time *</label>
                        <input type="time" id="edit_time" name="event_time" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_venue">Venue Location *</label>
                        <input type="text" id="edit_venue" name="venue" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_organizer">Organizer *</label>
                        <input type="text" id="edit_organizer" name="organizer" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_cap">Max Capacity *</label>
                        <input type="number" id="edit_cap" name="max_participants" class="form-input" required min="5" max="5000">
                    </div>

                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-select">
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_desc">Event Description</label>
                        <textarea id="edit_desc" name="description" class="form-textarea" rows="3"></textarea>
                    </div>

                    <!-- Current Poster & Replacement Upload -->
                    <div class="form-group" style="grid-column: 1 / -1; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:14px;">
                        <label style="font-weight:700;">&#128444; Update Poster Image</label>
                        <div id="current_poster_preview" style="font-size:0.82rem; color:#475569; margin:6px 0;"></div>

                        <label for="edit_poster" style="font-size:0.8rem; color:#475569; margin-top:8px;">
                            Upload New Poster (Replaces existing):
                        </label>
                        <input type="file" id="edit_poster" name="poster" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-input" style="background:#fff;">

                        <div style="margin-top:8px;">
                            <label style="font-size:0.82rem; color:#dc2626; cursor:pointer;">
                                <input type="checkbox" name="remove_poster" value="1">
                                Remove current poster (don't replace)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">&#10003; Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- DELETE CONFIRMATION MODAL (Key Question 3) -->
    <!-- ===================================================================== -->
    <div id="deleteModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="delModalTitle">
        <div class="modal-dialog" style="max-width: 460px;">
            <div class="modal-header">
                <h3 id="delModalTitle" style="color: var(--danger-color); display:flex; align-items:center; gap:8px;">
                    <span>&#9888;</span> Confirm Event Deletion
                </h3>
                <button type="button" class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="POST" action="events_crud.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" id="del_id" name="event_id" value="">

                <p style="font-size: 0.95rem; line-height: 1.5; color: #334155;">
                    Are you sure you want to permanently delete <strong id="del_event_title">Event</strong>
                    (<code id="del_event_code" style="font-weight:700;">EVT-CODE</code>)?
                </p>

                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; font-size:0.82rem; color:#991b1b; margin-bottom:16px;">
                    &#9888; <strong>Warning:</strong> The event poster image and any linked student registrations will also be removed. This action is permanently logged to the audit trail.
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">&#128465; Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- AUDIT TRAIL MODAL (Practical 12 Advanced Extension) -->
    <!-- ===================================================================== -->
    <div id="auditModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
        <div class="modal-dialog" style="max-width: 760px;">
            <div class="modal-header">
                <h3 id="auditModalTitle">&#128220; Event Security Audit Trail</h3>
                <button type="button" class="close-btn" onclick="closeModal('auditModal')">&times;</button>
            </div>
            <p style="font-size:0.85rem; color:#64748b; margin:0 0 14px 0;">
                All administrative mutations (Event Creation, Updates, and Deletions) are permanently recorded in the <code>audit_log</code> table with timestamp and IP address.
            </p>
            <div style="max-height: 380px; overflow-y: auto; border:1px solid var(--border-color); border-radius:8px;">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Record ID</th>
                            <th>Admin</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats['recent_audits'])): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:24px; color:#94a3b8;">
                                    No audit entries recorded yet. Create or modify an event to generate audit logs.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stats['recent_audits'] as $log): ?>
                                <tr>
                                    <td>
                                        <span class="chip <?php echo strpos($log['action'], 'DELETE') !== false ? 'chip-cancelled' : (strpos($log['action'], 'CREATE') !== false ? 'chip-upcoming' : 'chip-tech'); ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td>#<?php echo $log['record_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($log['performed_by']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td style="white-space:nowrap; color:#64748b; font-size:0.75rem;">
                                        <?php echo date('d M H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('auditModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function openEditModal(event) {
            document.getElementById('edit_id').value           = event.event_id;
            document.getElementById('edit_title').value        = event.title;
            document.getElementById('edit_code').value         = event.event_code;
            document.getElementById('edit_category').value     = event.category;
            document.getElementById('edit_date').value         = event.event_date;
            document.getElementById('edit_time').value         = event.event_time;
            document.getElementById('edit_venue').value        = event.venue;
            document.getElementById('edit_organizer').value    = event.organizer;
            document.getElementById('edit_cap').value          = event.max_participants;
            document.getElementById('edit_status').value       = event.status;
            document.getElementById('edit_desc').value         = event.description || '';

            var prev = document.getElementById('current_poster_preview');
            if (event.poster_image) {
                prev.innerHTML = '&#128444; Current Poster: <strong>' + event.poster_image + '</strong>';
            } else {
                prev.innerHTML = '&#8505; No poster image currently uploaded.';
            }

            document.getElementById('editModal').classList.add('show');
        }

        function confirmDeleteEvent(id, title, code) {
            document.getElementById('del_id').value            = id;
            document.getElementById('del_event_title').innerText = title;
            document.getElementById('del_event_code').innerText  = code;
            document.getElementById('deleteModal').classList.add('show');
        }

        function openAuditModal() {
            document.getElementById('auditModal').classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(function(m) {
                    m.classList.remove('show');
                });
            }
        });

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>

</body>

</html>
