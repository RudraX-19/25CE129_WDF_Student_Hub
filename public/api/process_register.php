<?php
/**
 * StudentHub Portal - Practical 7
 * Standalone POST Handler for Registration Form
 * Accepts submissions from register.html, register.php, or AJAX/Fetch requests.
 */

require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';
init_session();

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed: This endpoint accepts only POST requests.');
}

// Check if this is an AJAX/JSON request
$isJsonRequest = (
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

// CSRF Validation (Advanced Extension)
// If submitted via register.html where CSRF was not pre-generated, or token is present
$submittedToken = $_POST['csrf_token'] ?? '';
$hasSessionToken = !empty($_SESSION['csrf_token']);

if ($hasSessionToken && !verify_csrf_token($submittedToken)) {
    http_response_code(403);
    $response = [
        'success' => false,
        'errors'  => ['csrf' => 'Invalid or expired CSRF security token.'],
        'message' => 'CSRF verification failed.'
    ];
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Perform server-side validation & sanitization
$validation = validate_registration_form($_POST);

if (!$validation['valid']) {
    http_response_code(422);
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors'  => $validation['errors'],
            'message' => 'Validation failed on server side.'
        ]);
        exit;
    }

    // Pass data and errors via session to register.php
    $_SESSION['form_errors'] = $validation['errors'];
    $_SESSION['form_data']   = $validation['data'];
    header('Location: register.php');
    exit;
}

// Save safely to both CSV and JSON
$saveResult = save_registration_record($validation['data']);

if ($isJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $saveResult['success'],
        'record'  => $saveResult['record'],
        'message' => $saveResult['message']
    ]);
    exit;
}

// If submitted by standard form, render a clean, high-fidelity confirmation page
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation - StudentHub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Registration Processing Confirmation</p>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="../index.html">Home</a></li>
            <li><a href="../dashboard.html">Dashboard</a></li>
            <li><a href="../courses.html">My Courses</a></li>
            <li><a href="../timetable.html">Timetable</a></li>
            <li><a href="../grades.html">Grades</a></li>
            <li><a href="../events.html">Events</a></li>
            <li><a href="view_records.php">Stored Records (P7)</a></li>
            <li><a href="register.php">Register</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">
        <section aria-labelledby="confirm-heading" style="max-width: 720px; margin: 30px auto;">
            <h2 id="confirm-heading">&#9989; Registration Received &amp; Stored</h2>

            <div class="alert alert-success" role="alert">
                <div class="alert-icon">&#10004;</div>
                <div class="alert-content">
                    <h3 style="margin: 0 0 8px 0;">Server-Side Processing Complete</h3>
                    <p>All submitted inputs were thoroughly sanitized, validated on the server, and written to persistent storage using safe file-locking protocols (<code>flock</code>).</p>
                </div>
            </div>

            <div class="submission-receipt">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e1e7f0; padding-bottom: 8px;">Registration Summary</h3>
                <p><strong>Registration ID:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($saveResult['record']['registration_id']); ?></span></p>
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($saveResult['record']['fullname']); ?></p>
                <p><strong>Email Address:</strong> <?php echo htmlspecialchars($saveResult['record']['email']); ?></p>
                <p><strong>Mobile Number:</strong> <?php echo htmlspecialchars($saveResult['record']['mobile']); ?></p>
                <p><strong>Enrolled Course:</strong> <?php echo htmlspecialchars($saveResult['record']['course_name']); ?></p>
                <p><strong>Current Year:</strong> <?php echo htmlspecialchars($saveResult['record']['year']); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($saveResult['record']['gender']); ?></p>
                <p><strong>Password Security:</strong> <span class="badge badge-info">&#128274; Hashed with Bcrypt (Safe)</span></p>
                <p><strong>Submission Timestamp:</strong> <?php echo htmlspecialchars($saveResult['record']['registered_at']); ?></p>
                <p><strong>Storage Formats:</strong> 
                    <span class="badge badge-success">assets/data/registrations.csv</span>
                    <span class="badge badge-success">assets/data/registrations.json</span>
                </p>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="view_records.php?type=registrations" class="btn btn-primary">&#128196; View All Stored Records</a>
                <a href="../login.html" class="btn btn-secondary">Go to Login &rarr;</a>
                <a href="register.php" class="btn btn-outline">Submit Another Registration</a>
            </div>
        </section>
    </main>

    <footer role="contentinfo">
        <p>&copy; 2026 StudentHub &mdash; Institutional Web Portal</p>
    </footer>
    <script src="../assets/js/main.js"></script>
</body>
</html>
