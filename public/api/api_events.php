<?php
/**
 * StudentHub Portal - Practical 13
 * RESTful PHP JSON API for Event Management
 *
 * Requirements Met:
 * 1. Returns standard valid JSON responses with appropriate HTTP status codes (200, 201, 400, 404, 409, 422, 500).
 * 2. Handles GET (list, single, stats) and POST (create, update, delete).
 * 3. Centralized JSON response handler & error formatting (Advanced Extension).
 * 4. Comprehensive server-side validation before any database transaction.
 * 5. Supports search, category filter, and open/closed status filter.
 *
 * Course: Web Development Fundamentals (ITUE203)
 */

define('STUDENTHUB_ACCESS', true);

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/core/mysqli_db.php';
require_once dirname(__DIR__, 2) . '/src/core/session_guard.php';

session_secure_start();

/**
 * Standardized JSON API Response Generator (Practical 13 Advanced Extension)
 *
 * @param bool $success
 * @param int $status_code
 * @param string $message
 * @param mixed $data
 * @param array $errors
 * @param array $meta
 */
function send_json_response(bool $success, int $status_code, string $message, $data = null, array $errors = [], array $meta = []): void {
    http_response_code($status_code);
    $response = [
        'success'     => $success,
        'status_code' => $status_code,
        'message'     => $message,
        'timestamp'   => date('c')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    if (!empty($meta)) {
        $response['meta'] = $meta;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Parse request payload (supports JSON body or standard form POST)
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];
$request_params = array_merge($_GET, $_POST, $json_data);

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($request_params['action'] ?? ($method === 'GET' ? 'list' : ''));

try {
    // -------------------------------------------------------------------------
    // 1. GET: LIST EVENTS (With Search, Multi-Filter & Pagination)
    // -------------------------------------------------------------------------
    if ($method === 'GET' && ($action === 'list' || $action === '')) {
        $search   = trim($request_params['q'] ?? '');
        $category = trim($request_params['category'] ?? '');
        $status   = trim($request_params['status'] ?? '');
        $sort_by  = trim($request_params['sort'] ?? 'event_date');
        $order    = strtoupper(trim($request_params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $page     = max(1, (int)($request_params['page'] ?? 1));
        $limit    = max(1, min(50, (int)($request_params['limit'] ?? 10)));
        $offset   = ($page - 1) * $limit;

        $allowed_sorts = ['event_date', 'title', 'category', 'registered_count', 'max_participants', 'status'];
        $order_col = in_array($sort_by, $allowed_sorts, true) ? $sort_by : 'event_date';

        $where = [];
        $types = "";
        $params = [];

        if ($search !== '') {
            $where[] = "(title LIKE ? OR event_code LIKE ? OR venue LIKE ? OR organizer LIKE ?)";
            $like = "%{$search}%";
            $types .= "ssss";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($category !== '') {
            $where[] = "category = ?";
            $types .= "s";
            $params[] = $category;
        }

        if ($status === 'open') {
            $where[] = "status = 'Upcoming' AND registered_count < max_participants";
        } elseif ($status === 'closed') {
            $where[] = "(status IN ('Completed', 'Cancelled') OR registered_count >= max_participants)";
        } elseif ($status !== '' && in_array($status, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'], true)) {
            $where[] = "status = ?";
            $types .= "s";
            $params[] = $status;
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Total count
        $count_res = mysqli_fetch_one_row("SELECT COUNT(*) AS total FROM events {$where_sql}", $types, $params);
        $total_count = (int)($count_res['total'] ?? 0);
        $total_pages = max(1, (int)ceil($total_count / $limit));

        // Fetch records
        $sql = "SELECT event_id, event_code, title, category, event_date, event_time,
                       venue, organizer, max_participants, registered_count, status, description, poster_image, created_at
                FROM events
                {$where_sql}
                ORDER BY {$order_col} {$order}
                LIMIT ? OFFSET ?";
        $page_types = $types . "ii";
        $page_params = array_merge($params, [$limit, $offset]);
        $rows = mysqli_fetch_all_rows($sql, $page_types, $page_params);

        // Enhance with availability flags & poster URLs
        foreach ($rows as &$r) {
            $r['is_open'] = ($r['status'] === 'Upcoming' && (int)$r['registered_count'] < (int)$r['max_participants']);
            $r['seats_left'] = max(0, (int)$r['max_participants'] - (int)$r['registered_count']);
            $r['percent_booked'] = $r['max_participants'] > 0 ? round(((int)$r['registered_count'] / (int)$r['max_participants']) * 100) : 0;
            $r['poster_url'] = !empty($r['poster_image']) ? "../assets/images/posters/{$r['poster_image']}" : null;
        }
        unset($r);

        send_json_response(true, 200, "Events retrieved successfully", $rows, [], [
            'total'       => $total_count,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $total_pages,
            'filters'     => ['q' => $search, 'category' => $category, 'status' => $status]
        ]);
    }

    // -------------------------------------------------------------------------
    // 2. GET: SINGLE EVENT DETAILS
    // -------------------------------------------------------------------------
    if ($method === 'GET' && $action === 'get') {
        $id = (int)($request_params['id'] ?? 0);
        if ($id <= 0) {
            send_json_response(false, 400, "Invalid or missing event ID parameter.");
        }

        $row = mysqli_fetch_one_row("SELECT * FROM events WHERE event_id = ?", "i", [$id]);
        if (!$row) {
            send_json_response(false, 404, "Event with ID #{$id} was not found.");
        }

        $row['poster_url'] = !empty($row['poster_image']) ? "../assets/images/posters/{$row['poster_image']}" : null;
        send_json_response(true, 200, "Event details retrieved", $row);
    }

    // -------------------------------------------------------------------------
    // 3. GET: REAL-TIME EVENT KPI STATS
    // -------------------------------------------------------------------------
    if ($method === 'GET' && $action === 'stats') {
        $tot_row  = mysqli_fetch_one_row("SELECT COUNT(*) AS c, SUM(registered_count) AS reg, SUM(max_participants) AS cap FROM events");
        $open_row = mysqli_fetch_one_row("SELECT COUNT(*) AS c FROM events WHERE status = 'Upcoming' AND registered_count < max_participants");
        $cat_rows = mysqli_fetch_all_rows("SELECT category, COUNT(*) AS c FROM events GROUP BY category");

        $stats = [
            'total_events'       => (int)($tot_row['c'] ?? 0),
            'open_events'        => (int)($open_row['c'] ?? 0),
            'total_registrations'=> (int)($tot_row['reg'] ?? 0),
            'total_capacity'     => (int)($tot_row['cap'] ?? 0),
            'by_category'        => $cat_rows
        ];
        send_json_response(true, 200, "Real-time stats loaded", $stats);
    }

    // -------------------------------------------------------------------------
    // 4. POST: CREATE EVENT
    // -------------------------------------------------------------------------
    if ($method === 'POST' && $action === 'create') {
        $title       = trim(strip_tags($request_params['title'] ?? ''));
        $event_code  = strtoupper(trim(strip_tags($request_params['event_code'] ?? '')));
        $category    = trim(strip_tags($request_params['category'] ?? 'Technical'));
        $event_date  = trim($request_params['event_date'] ?? '');
        $event_time  = trim($request_params['event_time'] ?? '');
        $venue       = trim(strip_tags($request_params['venue'] ?? ''));
        $organizer   = trim(strip_tags($request_params['organizer'] ?? ''));
        $max_cap     = (int)($request_params['max_participants'] ?? 100);
        $status      = trim($request_params['status'] ?? 'Upcoming');
        $description = trim(strip_tags($request_params['description'] ?? ''));

        $errors = [];
        if (empty($title) || strlen($title) < 3) {
            $errors['title'] = "Title is required (minimum 3 characters).";
        }
        if (empty($event_code) || !preg_match('/^[A-Z0-9_-]{3,30}$/', $event_code)) {
            $errors['event_code'] = "Event code must be 3-30 alphanumeric characters (e.g. EVT-AI-01).";
        }
        if (empty($event_date)) {
            $errors['event_date'] = "Valid date is required.";
        }
        if (empty($event_time)) {
            $errors['event_time'] = "Event time is required.";
        }
        if (empty($venue)) {
            $errors['venue'] = "Venue is required.";
        }
        if (empty($organizer)) {
            $errors['organizer'] = "Organizer is required.";
        }
        if ($max_cap < 5 || $max_cap > 5000) {
            $errors['max_participants'] = "Capacity must be between 5 and 5000.";
        }
        if (!in_array($status, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'], true)) {
            $status = 'Upcoming';
        }

        if (!empty($errors)) {
            send_json_response(false, 422, "Input validation failed", null, $errors);
        }

        // Duplicate check
        $dup = mysqli_fetch_one_row("SELECT event_id FROM events WHERE event_code = ?", "s", [$event_code]);
        if ($dup) {
            send_json_response(false, 409, "Event code '{$event_code}' is already registered.", null, [
                'event_code' => "Event code already exists."
            ]);
        }

        $sql = "INSERT INTO events (event_code, title, category, event_date, event_time, venue, organizer, max_participants, registered_count, status, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";
        $res = mysqli_execute_stmt($sql, "sssssssisss", [$event_code, $title, $category, $event_date, $event_time, $venue, $organizer, $max_cap, $status, $description]);
        $new_id = $res['insert_id'];

        // Audit log
        try {
            $user = $_SESSION['username'] ?? 'api_user';
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            mysqli_execute_stmt(
                "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
                 VALUES ('API_EVENT_CREATED', 'events', ?, ?, ?, ?)",
                "isss",
                [$new_id, $user, "Created event {$title} via JSON API", $ip]
            );
        } catch (\Throwable $e) {}

        $created_event = mysqli_fetch_one_row("SELECT * FROM events WHERE event_id = ?", "i", [$new_id]);
        send_json_response(true, 201, "Event '{$title}' created successfully via AJAX API!", $created_event);
    }

    // -------------------------------------------------------------------------
    // 5. POST: UPDATE EVENT
    // -------------------------------------------------------------------------
    if ($method === 'POST' && $action === 'update') {
        $id          = (int)($request_params['event_id'] ?? 0);
        $title       = trim(strip_tags($request_params['title'] ?? ''));
        $event_code  = strtoupper(trim(strip_tags($request_params['event_code'] ?? '')));
        $category    = trim(strip_tags($request_params['category'] ?? 'Technical'));
        $event_date  = trim($request_params['event_date'] ?? '');
        $event_time  = trim($request_params['event_time'] ?? '');
        $venue       = trim(strip_tags($request_params['venue'] ?? ''));
        $organizer   = trim(strip_tags($request_params['organizer'] ?? ''));
        $max_cap     = (int)($request_params['max_participants'] ?? 100);
        $status      = trim($request_params['status'] ?? 'Upcoming');
        $description = trim(strip_tags($request_params['description'] ?? ''));

        if ($id <= 0) {
            send_json_response(false, 400, "Invalid event ID provided for update.");
        }

        $existing = mysqli_fetch_one_row("SELECT * FROM events WHERE event_id = ?", "i", [$id]);
        if (!$existing) {
            send_json_response(false, 404, "Event record #{$id} does not exist.");
        }

        $errors = [];
        if (empty($title))      $errors['title'] = "Title cannot be empty.";
        if (empty($event_code)) $errors['event_code'] = "Event code cannot be empty.";
        if ($max_cap < 5)       $errors['max_participants'] = "Capacity must be at least 5.";

        if (!empty($errors)) {
            send_json_response(false, 422, "Validation failed", null, $errors);
        }

        // Duplicate event code check excluding current ID
        $dup = mysqli_fetch_one_row("SELECT event_id FROM events WHERE event_code = ? AND event_id != ?", "si", [$event_code, $id]);
        if ($dup) {
            send_json_response(false, 409, "Event code '{$event_code}' is in use by another event.", null, [
                'event_code' => "Code in use."
            ]);
        }

        $sql = "UPDATE events
                SET event_code=?, title=?, category=?, event_date=?, event_time=?, venue=?, organizer=?, max_participants=?, status=?, description=?
                WHERE event_id=?";
        mysqli_execute_stmt($sql, "sssssssisssi", [$event_code, $title, $category, $event_date, $event_time, $venue, $organizer, $max_cap, $status, $description, $id]);

        // Audit log
        try {
            $user = $_SESSION['username'] ?? 'api_user';
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            mysqli_execute_stmt(
                "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
                 VALUES ('API_EVENT_UPDATED', 'events', ?, ?, ?, ?)",
                "isss",
                [$id, $user, "Updated event {$event_code} via JSON API", $ip]
            );
        } catch (\Throwable $e) {}

        $updated_event = mysqli_fetch_one_row("SELECT * FROM events WHERE event_id = ?", "i", [$id]);
        send_json_response(true, 200, "Event '{$event_code}' updated successfully!", $updated_event);
    }

    // -------------------------------------------------------------------------
    // 6. POST: DELETE EVENT
    // -------------------------------------------------------------------------
    if ($method === 'POST' && $action === 'delete') {
        $id = (int)($request_params['event_id'] ?? 0);
        if ($id <= 0) {
            send_json_response(false, 400, "Invalid event ID provided for deletion.");
        }

        $event = mysqli_fetch_one_row("SELECT title, event_code, poster_image FROM events WHERE event_id = ?", "i", [$id]);
        if (!$event) {
            send_json_response(false, 404, "Event record #{$id} not found.");
        }

        // Cascade delete registrations first
        mysqli_execute_stmt("DELETE FROM registrations WHERE event_id = ?", "i", [$id]);
        mysqli_execute_stmt("DELETE FROM events WHERE event_id = ?", "i", [$id]);

        // Audit log
        try {
            $user = $_SESSION['username'] ?? 'api_user';
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            mysqli_execute_stmt(
                "INSERT INTO audit_log (action, table_name, record_id, performed_by, details, ip_address)
                 VALUES ('API_EVENT_DELETED', 'events', ?, ?, ?, ?)",
                "isss",
                [$id, $user, "Deleted event {$event['title']} ({$event['event_code']}) via JSON API", $ip]
            );
        } catch (\Throwable $e) {}

        send_json_response(true, 200, "Event '{$event['title']}' ({$event['event_code']}) permanently deleted via AJAX.");
    }

    // Default: Invalid action / method
    send_json_response(false, 400, "Unknown API action or unsupported request method.");

} catch (\Throwable $e) {
    send_json_response(false, 500, "Internal Server Error: " . $e->getMessage());
}
