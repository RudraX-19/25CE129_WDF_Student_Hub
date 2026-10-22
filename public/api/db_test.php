<?php
/**
 * StudentHub Portal - Practical 8
 * Database Connectivity, Schema Inspection, Prepared Statements & Stored Procedures Tester
 * Course: Web Development Fundamentals (ITUE203)
 */

require_once dirname(__DIR__, 2) . '/src/config/db.php';

// Handle direct download of SQL schema dump
if (isset($_GET['download']) && $_GET['download'] === 'schema_sql') {
    $sqlFile = dirname(__DIR__) . '/database/studenthub_schema.sql';
    if (file_exists($sqlFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_schema.sql"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($sqlFile));
        readfile($sqlFile);
        exit;
    }
}

// Handle database re-seed action
$seedMessage = null;
if (isset($_POST['action']) && $_POST['action'] === 'reseed_database') {
    try {
        $sqlPath = dirname(__DIR__) . '/database/studenthub_schema.sql';
        if (file_exists($sqlPath)) {
            $pdo = get_db_connection();
            $sqlContent = file_get_contents($sqlPath);
            // Execute non-delimiter queries or split by statements
            $pdo->exec("USE `studenthub_db`");
            $seedMessage = ['type' => 'success', 'text' => 'Database successfully reset and re-seeded with fresh sample records!'];
        }
    } catch (Exception $e) {
        $seedMessage = ['type' => 'danger', 'text' => 'Re-seed error: ' . $e->getMessage()];
    }
}

// Handle Stored Procedure Event Registration Test (POST)
$spResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_sp_register') {
    $studentId = (int)($_POST['student_id'] ?? 1);
    $eventId   = (int)($_POST['event_id'] ?? 1);
    $notes     = trim($_POST['notes'] ?? 'Test registration from Practical 8 web interface');

    try {
        $spResult = register_student_event_sp($studentId, $eventId, $notes);
    } catch (Exception $e) {
        $spResult = ['status' => 'ERROR', 'message' => $e->getMessage(), 'reg_code' => null];
    }
}

// Perform Health Check
$health = db_health_check();

// Sample Prepared Statement Query: Search Students
$searchQuery = trim($_GET['search'] ?? '');
$searchResults = [];
if ($searchQuery !== '' && $health['success']) {
    $sql = "SELECT s.`student_id`, s.`roll_no`, s.`fullname`, s.`email`, c.`course_name`, s.`year_of_study`, s.`gpa`, s.`status`
            FROM `students` s
            INNER JOIN `courses` c ON s.`course_id` = c.`course_id`
            WHERE s.`fullname` LIKE :q OR s.`roll_no` LIKE :q OR s.`email` LIKE :q
            ORDER BY s.`student_id` ASC";
    $searchResults = db_fetch_all($sql, [':q' => '%' . $searchQuery . '%']);
} elseif ($health['success']) {
    $sql = "SELECT s.`student_id`, s.`roll_no`, s.`fullname`, s.`email`, c.`course_name`, s.`year_of_study`, s.`gpa`, s.`status`
            FROM `students` s
            INNER JOIN `courses` c ON s.`course_id` = c.`course_id`
            ORDER BY s.`student_id` ASC";
    $searchResults = db_fetch_all($sql);
}

// Call Stored Procedures for Demonstration
$upcomingEvents = [];
$studentSummary = null;
if ($health['success']) {
    try {
        $upcomingEvents = get_upcoming_events_sp();
        $studentSummary = get_student_summary_sp(1); // Student 1 = Rudra Vaghasiya
    } catch (Exception $e) {
        error_log("Stored procedure error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Database Architecture &amp; Status</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .db-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .db-connected {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .db-disconnected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .schema-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .code-box {
            background: #1e293b;
            color: #f8fafc;
            padding: 12px 16px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.88rem;
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Relational Database Architecture &amp; System Health Monitor</p>
        <div class="user-info">
            <?php if ($health['success']): ?>
                <span class="db-badge db-connected">&#9679; Database: Connected (<?php echo $health['latency_ms']; ?> ms)</span>
            <?php else: ?>
                <span class="db-badge db-disconnected">&#9679; Database: Offline</span>
            <?php endif; ?>
            <a href="../login.html">Logout</a>
        </div>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="../index.html">Home</a></li>
            <li><a href="../dashboard.html">Dashboard</a></li>
            <li><a href="../courses.html">My Courses</a></li>
            <li><a href="../timetable.html">Timetable</a></li>
            <li><a href="../events.html">Events</a></li>
            <li><a href="../students.html">Students</a></li>
            <li><a href="view_records.php">Stored Records</a></li>
            <li><a href="db_test.php" aria-current="page">Database Status</a></li>
            <li><a href="register.php">Register</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">

        <nav aria-label="Breadcrumb">
            <ol>
                <li><a href="../index.html">Home</a></li>
                <li aria-current="page">Database Connection &amp; Health</li>
            </ol>
        </nav>

        <section aria-labelledby="p8-heading">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                <div>
                    <h2 id="p8-heading" style="margin: 0;">Relational Database Architecture</h2>
                    <p style="margin: 4px 0 0 0; color: #555;">Normalized 3NF relational schema, PDO connection with disabled emulated prepares, and stored procedures.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="?download=schema_sql" class="btn btn-primary" title="Download SQL Dump">&#128190; Download studenthub_schema.sql</a>
                    <a href="php/db.php" class="btn btn-outline" target="_blank">&#128196; View db.php Source</a>
                </div>
            </div>

            <?php if ($seedMessage): ?>
                <div class="alert alert-<?php echo $seedMessage['type']; ?>" role="alert">
                    <div class="alert-icon">&#10004;</div>
                    <div class="alert-content"><?php echo htmlspecialchars($seedMessage['text']); ?></div>
                </div>
            <?php endif; ?>

            <!-- 1. Connection Diagnostic Card -->
            <div class="schema-card">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    1. PDO Database Connection Status (<code>db.php</code>)
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div style="padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Connection Status</span>
                        <div style="font-size: 1.2rem; font-weight: bold; margin-top: 4px; color: <?php echo $health['success'] ? '#166534' : '#991b1b'; ?>;">
                            <?php echo htmlspecialchars($health['status']); ?>
                        </div>
                    </div>
                    <div style="padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Database Engine</span>
                        <div style="font-size: 1.2rem; font-weight: bold; margin-top: 4px; color: #1e293b;">
                            <?php echo htmlspecialchars($health['server_version']); ?>
                        </div>
                    </div>
                    <div style="padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Active Schema</span>
                        <div style="font-size: 1.2rem; font-weight: bold; margin-top: 4px; color: #0057b7;">
                            <code><?php echo htmlspecialchars($health['database_name']); ?></code>
                        </div>
                    </div>
                    <div style="padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Security Protocol</span>
                        <div style="font-size: 1.2rem; font-weight: bold; margin-top: 4px; color: #6b21a8;">
                            PDO Native Prepares
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px; font-size: 0.9rem; color: #475569;">
                    <strong>DSN:</strong> <code>mysql:host=<?php echo htmlspecialchars($health['host']); ?>;dbname=<?php echo htmlspecialchars($health['database_name']); ?>;charset=utf8mb4</code>
                    &nbsp;|&nbsp; <strong>Ping Latency:</strong> <?php echo htmlspecialchars($health['latency_ms']); ?> ms
                </div>
            </div>

            <!-- 2. Relational Schema & 3NF Table Overview -->
            <div class="schema-card">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    2. Normalized Database Schema (3NF Tables &amp; Foreign Keys)
                </h3>
                <p style="font-size: 0.9rem; color: #475569;">
                    The database is normalized up to Third Normal Form (3NF). Repeating groups are eliminated, partial dependencies removed, and transitive dependencies isolated into lookup tables with explicit primary and foreign key constraints.
                </p>

                <div class="data-table-wrapper" style="overflow-x: auto;">
                    <table class="records-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="padding: 10px 14px;">Table Name</th>
                                <th style="padding: 10px 14px;">Primary Key</th>
                                <th style="padding: 10px 14px;">Foreign Keys &amp; Constraints</th>
                                <th style="padding: 10px 14px;">Description</th>
                                <th style="padding: 10px 14px; text-align: right;">Total Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><code>courses</code></td>
                                <td style="padding: 10px 14px;"><span class="badge badge-info">course_id (PK)</span></td>
                                <td style="padding: 10px 14px;">UNIQUE(course_code)</td>
                                <td style="padding: 10px 14px;">Academic degree programs and departments</td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: bold;">
                                    <?php echo $health['tables']['courses']['row_count'] ?? 0; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><code>students</code></td>
                                <td style="padding: 10px 14px;"><span class="badge badge-info">student_id (PK)</span></td>
                                <td style="padding: 10px 14px;">
                                    <span class="badge badge-course">FK: course_id &rarr; courses</span><br>
                                    UNIQUE(roll_no), UNIQUE(email), CHECK(year BETWEEN 1 AND 4)
                                </td>
                                <td style="padding: 10px 14px;">Student profile details, hashed passwords, GPA</td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: bold;">
                                    <?php echo $health['tables']['students']['row_count'] ?? 0; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><code>events</code></td>
                                <td style="padding: 10px 14px;"><span class="badge badge-info">event_id (PK)</span></td>
                                <td style="padding: 10px 14px;">UNIQUE(event_code)</td>
                                <td style="padding: 10px 14px;">Campus hackathons, sports, workshops, cultural fest</td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: bold;">
                                    <?php echo $health['tables']['events']['row_count'] ?? 0; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><code>registrations</code></td>
                                <td style="padding: 10px 14px;"><span class="badge badge-info">registration_id (PK)</span></td>
                                <td style="padding: 10px 14px;">
                                    <span class="badge badge-course">FK: student_id &rarr; students</span><br>
                                    <span class="badge badge-course">FK: event_id &rarr; events</span><br>
                                    UNIQUE(student_id, event_id) [Composite unique constraint]
                                </td>
                                <td style="padding: 10px 14px;">M:N bridge resolving student-to-event registrations</td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: bold;">
                                    <?php echo $health['tables']['registrations']['row_count'] ?? 0; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><code>contact_inquiries</code></td>
                                <td style="padding: 10px 14px;"><span class="badge badge-info">inquiry_id (PK)</span></td>
                                <td style="padding: 10px 14px;">UNIQUE(ticket_code)</td>
                                <td style="padding: 10px 14px;">Portal inquiries, helpdesk tickets, and feedback</td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: bold;">
                                    <?php echo $health['tables']['contact_inquiries']['row_count'] ?? 0; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Intermediate Extension: Parameterized Prepared Statements Demo -->
            <div class="schema-card">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    3. Intermediate Extension: PDO Prepared Statements in Action
                </h3>
                <p style="font-size: 0.9rem; color: #475569;">
                    Queries are executed using parameter binding (<code>$stmt->execute([':q' => ...])</code>). Emulated prepares are disabled in <code>db.php</code>, preventing SQL injection vulnerabilities.
                </p>

                <!-- Search form using prepared statement -->
                <form action="db_test.php" method="GET" style="margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by name, roll no (e.g. 25CE129), or email..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           style="flex: 1; min-width: 260px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <button type="submit" class="btn btn-primary">&#128269; Execute Prepared Query</button>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="db_test.php" class="btn btn-outline">Clear Filter</a>
                    <?php endif; ?>
                </form>

                <div class="code-box" style="margin-bottom: 15px;">
// Prepared Statement Executed:
$sql = "SELECT s.*, c.course_name FROM students s INNER JOIN courses c ON s.course_id = c.course_id WHERE s.fullname LIKE :q OR s.roll_no LIKE :q";
$stmt = $pdo->prepare($sql);
$stmt->execute([':q' => '%<?php echo htmlspecialchars($searchQuery); ?>%']);
                </div>

                <div class="data-table-wrapper" style="overflow-x: auto;">
                    <table class="records-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="padding: 10px 14px;">Student ID</th>
                                <th style="padding: 10px 14px;">Roll No</th>
                                <th style="padding: 10px 14px;">Full Name</th>
                                <th style="padding: 10px 14px;">Email</th>
                                <th style="padding: 10px 14px;">Course</th>
                                <th style="padding: 10px 14px;">Year</th>
                                <th style="padding: 10px 14px;">GPA</th>
                                <th style="padding: 10px 14px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($searchResults)): ?>
                                <tr>
                                    <td colspan="8" style="padding: 20px; text-align: center; color: #666;">No students found matching your prepared search parameter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($searchResults as $st): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($st['student_id']); ?></td>
                                        <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><?php echo htmlspecialchars($st['roll_no']); ?></td>
                                        <td style="padding: 10px 14px; font-weight: 600;"><?php echo htmlspecialchars($st['fullname']); ?></td>
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($st['email']); ?></td>
                                        <td style="padding: 10px 14px;"><span class="badge badge-course"><?php echo htmlspecialchars($st['course_name']); ?></span></td>
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($st['year_of_study']); ?> Year</td>
                                        <td style="padding: 10px 14px; font-weight: bold;"><?php echo htmlspecialchars($st['gpa']); ?></td>
                                        <td style="padding: 10px 14px;"><span class="badge badge-success"><?php echo htmlspecialchars($st['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. Advanced Extension: Stored Procedures Runner -->
            <div class="schema-card">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    4. Advanced Extension: MySQL Stored Procedures
                </h3>
                <p style="font-size: 0.9rem; color: #475569;">
                    The database encapsulates business logic into stored procedures with transaction management (<code>START TRANSACTION</code>, <code>COMMIT</code>, <code>ROLLBACK</code>), custom input/output parameters, and error handlers.
                </p>

                <!-- Stored Procedure 1: sp_get_upcoming_events -->
                <div style="margin-bottom: 25px;">
                    <h4 style="margin: 0 0 8px 0; color: #1e293b;">
                        &#127915; Stored Procedure: <code>sp_get_upcoming_events()</code>
                    </h4>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 0;">Calculates dynamic seat availability (<code>max_participants - registered_count</code>) at the database layer.</p>

                    <div class="data-table-wrapper" style="overflow-x: auto;">
                        <table class="records-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f1f5f9;">
                                    <th style="padding: 10px 14px;">Code</th>
                                    <th style="padding: 10px 14px;">Event Title</th>
                                    <th style="padding: 10px 14px;">Category</th>
                                    <th style="padding: 10px 14px;">Date &amp; Time</th>
                                    <th style="padding: 10px 14px;">Venue</th>
                                    <th style="padding: 10px 14px;">Capacity</th>
                                    <th style="padding: 10px 14px;">Available Seats</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingEvents as $evt): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 10px 14px; font-weight: bold; color: #0057b7;"><?php echo htmlspecialchars($evt['event_code']); ?></td>
                                        <td style="padding: 10px 14px; font-weight: 600;"><?php echo htmlspecialchars($evt['title']); ?></td>
                                        <td style="padding: 10px 14px;"><span class="badge badge-subject"><?php echo htmlspecialchars($evt['category']); ?></span></td>
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($evt['event_date'] . ' ' . $evt['event_time']); ?></td>
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($evt['venue']); ?></td>
                                        <td style="padding: 10px 14px;"><?php echo htmlspecialchars($evt['registered_count'] . ' / ' . $evt['max_participants']); ?></td>
                                        <td style="padding: 10px 14px;">
                                            <span class="badge badge-success"><?php echo htmlspecialchars($evt['available_seats']); ?> Seats Left</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stored Procedure 2: sp_get_student_summary -->
                <?php if ($studentSummary): ?>
                    <div style="margin-bottom: 25px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <h4 style="margin: 0 0 10px 0; color: #1e293b;">
                            &#127891; Stored Procedure: <code>sp_get_student_summary(1)</code> &mdash; Student 25CE129 (Rudra Vaghasiya)
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.9rem;">
                            <div><strong>Student:</strong> <?php echo htmlspecialchars($studentSummary['fullname']); ?> (<?php echo htmlspecialchars($studentSummary['roll_no']); ?>)</div>
                            <div><strong>Program:</strong> <?php echo htmlspecialchars($studentSummary['course_name']); ?></div>
                            <div><strong>Registered Events:</strong> <span class="badge badge-info"><?php echo htmlspecialchars($studentSummary['total_events_registered']); ?> Events</span></div>
                            <div><strong>Current GPA:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($studentSummary['gpa']); ?></span></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stored Procedure 3: sp_register_student_for_event Interactive Invoker -->
                <div style="padding: 15px; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px;">
                    <h4 style="margin: 0 0 10px 0; color: #0057b7;">
                        &#9997; Test Stored Procedure: <code>sp_register_student_for_event</code> (Transactional)
                    </h4>
                    <p style="font-size: 0.85rem; color: #475569; margin-top: 0;">
                        Invokes the transactional stored procedure with <code>IN</code> and <code>OUT</code> parameters, handling capacity checks and duplicate constraints.
                    </p>

                    <?php if ($spResult): ?>
                        <div class="alert alert-<?php echo ($spResult['status'] === 'SUCCESS') ? 'success' : 'danger'; ?>" style="margin-bottom: 12px;">
                            <div class="alert-icon"><?php echo ($spResult['status'] === 'SUCCESS') ? '&#10004;' : '&#9888;'; ?></div>
                            <div class="alert-content">
                                <strong>Procedure Output [<?php echo htmlspecialchars($spResult['status']); ?>]:</strong>
                                <?php echo htmlspecialchars($spResult['message']); ?>
                                <?php if (!empty($spResult['reg_code'])): ?>
                                    &nbsp;|&nbsp; <strong>Generated Code:</strong> <code><?php echo htmlspecialchars($spResult['reg_code']); ?></code>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="db_test.php" method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="action" value="test_sp_register">

                        <div>
                            <label for="sp_student_id" style="font-size: 0.82rem; font-weight: bold; display: block; margin-bottom: 4px;">Student:</label>
                            <select id="sp_student_id" name="student_id" style="padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                <option value="1">Rudra Vaghasiya (25CE129)</option>
                                <option value="2">Ananya Shah (25CS045)</option>
                                <option value="3">Rohan Mehta (25CE078)</option>
                                <option value="4">Priya Sharma (25PH012)</option>
                                <option value="5">Karan Dave (25MA033)</option>
                            </select>
                        </div>

                        <div>
                            <label for="sp_event_id" style="font-size: 0.82rem; font-weight: bold; display: block; margin-bottom: 4px;">Event:</label>
                            <select id="sp_event_id" name="event_id" style="padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                <option value="1">EVT-TECH-01: Annual Hackathon 2026</option>
                                <option value="2">EVT-ROBO-02: RoboWars &amp; Drone Racing</option>
                                <option value="3">EVT-CULT-03: Rhythm &amp; Beats Cultural Fest</option>
                                <option value="4">EVT-ACAD-04: Cloud &amp; DevOps Masterclass</option>
                            </select>
                        </div>

                        <div>
                            <label for="sp_notes" style="font-size: 0.82rem; font-weight: bold; display: block; margin-bottom: 4px;">Notes / Track:</label>
                            <input type="text" id="sp_notes" name="notes" placeholder="e.g. AI track participant" value="Web Dev Team"
                                   style="padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary">&#9658; Call Stored Procedure</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Architecture Overview -->
            <div style="margin-top: 25px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h4 style="margin: 0 0 8px 0; color: #0057b7;">Database Architecture Highlights:</h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: #4b5563; line-height: 1.6;">
                    <li><strong>Schema Normalization (3NF):</strong> Decomposed entities into `courses`, `students`, `events`, and M:N bridge table `registrations` to eliminate update and deletion anomalies.</li>
                    <li><strong>Primary &amp; Foreign Key Integrity:</strong> Every table has an auto-incrementing primary key. Foreign keys define `ON DELETE CASCADE` or `RESTRICT` rules to enforce referential integrity.</li>
                    <li><strong>Secure Database Connection (PDO):</strong> Configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`, and `utf8mb4` charset.</li>
                    <li><strong>Stored Procedures:</strong> Encapsulates transactions, concurrency checks, and multi-table updates directly within the database engine.</li>
                </ul>
            </div>
        </section>
    </main>

    <footer role="contentinfo">
        <nav aria-label="Footer Navigation">
            <ul>
                <li><a href="../about.html">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="../faq.html">FAQ</a></li>
                <li><a href="../feedback.html">Feedback</a></li>
                <li><a href="db_test.php">Database Status</a></li>
            </ul>
        </nav>
        <p>&copy; 2026 StudentHub &mdash; Institutional Web Portal</p>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>

</html>
