<?php
/**
 * StudentHub Portal - Practical 7
 * Standalone POST Handler for Contact Form
 * Accepts submissions from contact.html, contact.php, or AJAX/Fetch requests.
 */

require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';
init_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed: This endpoint accepts only POST requests.');
}

$isJsonRequest = (
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

// CSRF check
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

$validation = validate_contact_form($_POST);

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

    $_SESSION['contact_errors'] = $validation['errors'];
    $_SESSION['contact_data']   = $validation['data'];
    header('Location: contact.php');
    exit;
}

$saveResult = save_contact_record($validation['data']);

if ($isJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $saveResult['success'],
        'record'  => $saveResult['record'],
        'message' => $saveResult['message']
    ]);
    exit;
}

// Render confirmation for standard form submit
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Enquiry Received - StudentHub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Inquiry Submission Confirmation</p>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="../index.html">Home</a></li>
            <li><a href="../dashboard.html">Dashboard</a></li>
            <li><a href="view_records.php">Stored Records (P7)</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">
        <section aria-labelledby="confirm-heading" style="max-width: 720px; margin: 30px auto;">
            <h2 id="confirm-heading">&#9989; Message Received &amp; Stored</h2>

            <div class="alert alert-success" role="alert">
                <div class="alert-icon">&#10004;</div>
                <div class="alert-content">
                    <h3 style="margin: 0 0 8px 0;">Server-Side Processing Complete</h3>
                    <p>Your message was validated on the backend and saved to both <strong>contacts.csv</strong> and <strong>contacts.json</strong> safely with <code>flock</code> file locking.</p>
                </div>
            </div>

            <div class="submission-receipt">
                <h3 style="margin-top: 0; color: #0057b7; border-bottom: 2px solid #e1e7f0; padding-bottom: 8px;">Ticket Summary</h3>
                <p><strong>Ticket ID:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($saveResult['record']['ticket_id']); ?></span></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($saveResult['record']['name']); ?></p>
                <p><strong>Email Address:</strong> <?php echo htmlspecialchars($saveResult['record']['email']); ?></p>
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($saveResult['record']['subject_name']); ?></p>
                <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($saveResult['record']['message'])); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-info"><?php echo htmlspecialchars($saveResult['record']['status']); ?></span></p>
                <p><strong>Submission Timestamp:</strong> <?php echo htmlspecialchars($saveResult['record']['submitted_at']); ?></p>
                <p><strong>Storage Formats:</strong> 
                    <span class="badge badge-success">assets/data/contacts.csv</span>
                    <span class="badge badge-success">assets/data/contacts.json</span>
                </p>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="view_records.php?type=contacts" class="btn btn-primary">&#128196; View Stored Queries</a>
                <a href="contact.php" class="btn btn-outline">Send Another Query</a>
                <a href="../index.html" class="btn btn-secondary">&larr; Return to Home</a>
            </div>
        </section>
    </main>

    <footer role="contentinfo">
        <p>&copy; 2026 StudentHub &mdash; Institutional Web Portal</p>
    </footer>
    <script src="../assets/js/main.js"></script>
</body>
</html>
