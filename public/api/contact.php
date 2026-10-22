<?php
/**
 * StudentHub Portal - Practical 7
 * Server-Side Contact Form Processor and Interface
 * Features: POST submission, server-side validation & sanitization, CSRF token validation,
 * safe CSV/JSON storage using flock(), error & success message display.
 */

require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';
init_session();

$csrf_token = get_csrf_token();
$errors = [];
$successRecord = null;
$formData = [
    'name'    => '',
    'email'   => '',
    'subject' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Advanced Extension: Verify CSRF Token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        http_response_code(403);
        $errors['csrf'] = 'Security Alert: CSRF token validation failed or expired. Please refresh the page.';
    } else {
        // 2. Validate and sanitize inputs on server side
        $validation = validate_contact_form($_POST);
        $formData   = array_merge($formData, $validation['data']);
        $errors     = $validation['errors'];

        // 3. Store safely in CSV and JSON
        if ($validation['valid']) {
            $saveResult = save_contact_record($validation['data']);
            if ($saveResult['success']) {
                $successRecord = $saveResult['record'];
                // Reset form values on success
                $formData = [
                    'name'    => '',
                    'email'   => '',
                    'subject' => '',
                    'message' => ''
                ];
                // Refresh CSRF token for next request
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
            } else {
                $errors['storage'] = 'System Error: Failed to write contact message to file storage.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Contact Us (PHP Form Processing)</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <div class="user-info">
            <span>Welcome, Student!</span>
            <a href="../login.html" aria-label="Logout from StudentHub">Logout</a>
        </div>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="../index.html">Home</a></li>
            <li><a href="../dashboard.html">Dashboard</a></li>
            <li><a href="../courses.html">My Courses</a></li>
            <li><a href="../timetable.html">Timetable</a></li>
            <li><a href="../grades.html">Grades &amp; Transcripts</a></li>
            <li><a href="../events.html">Events &amp; Clubs</a></li>
            <li><a href="../library.html">Library</a></li>
            <li><a href="view_records.php">Stored Records (P7)</a></li>
            <li><a href="db_test.php">Database (P8)</a></li>
            <li><a href="contact.php" aria-current="page">Contact Us</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">

        <nav aria-label="Breadcrumb">
            <ol>
                <li><a href="../index.html">Home</a></li>
                <li aria-current="page">Contact Us</li>
            </ol>
        </nav>

        <section aria-labelledby="contact-heading">
            <h2 id="contact-heading">Contact Us &mdash; Support &amp; Enquiries</h2>
            <p>Submit your message or support request with secure server-side processing.</p>

            <!-- Server-Side Success Message -->
            <?php if ($successRecord): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <div class="alert-icon">&#10004;</div>
                    <div class="alert-content">
                        <h3 style="margin: 0 0 8px 0; color: #155724;">Message Sent Successfully!</h3>
                        <p style="margin: 0 0 10px 0;">Thank you, <strong><?php echo htmlspecialchars($successRecord['name']); ?></strong>! Your query has been safely written to both <strong>contacts.csv</strong> and <strong>contacts.json</strong>.</p>
                        <div class="submission-receipt">
                            <p><strong>Ticket ID:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($successRecord['ticket_id']); ?></span></p>
                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($successRecord['subject_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($successRecord['email']); ?></p>
                            <p><strong>Status:</strong> <span class="badge badge-info"><?php echo htmlspecialchars($successRecord['status']); ?></span></p>
                            <p><strong>Submitted At:</strong> <?php echo htmlspecialchars($successRecord['submitted_at']); ?></p>
                            <p><strong>Storage Status:</strong> <span class="badge badge-success">&#10003; CSV Appended</span> &nbsp; <span class="badge badge-success">&#10003; JSON Appended</span></p>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="view_records.php?type=contacts" class="btn btn-primary">&#128065; View Stored Enquiries</a>
                            <a href="contact.php" class="btn btn-outline">Send Another Message</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Server-Side Error Alert Box -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive">
                    <div class="alert-icon">&#9888;</div>
                    <div class="alert-content">
                        <h3 style="margin: 0 0 8px 0; color: #721c24;">Please correct the following errors:</h3>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($errors as $field => $msg): ?>
                                <li><strong><?php echo ucfirst($field); ?>:</strong> <?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form action="contact.php" method="POST" aria-label="Contact Form" novalidate id="contactForm">
                
                <!-- Advanced Extension: CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div>
                    <label for="contact-name">Your Name: <span class="required-star">*</span></label>
                    <input type="text" id="contact-name" name="name" autocomplete="name" required aria-required="true"
                           placeholder="e.g. Ananya Shah"
                           value="<?php echo htmlspecialchars($formData['name']); ?>"
                           class="<?php echo isset($errors['name']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['name'])): ?>
                        <span class="error-msg" aria-live="polite"><?php echo htmlspecialchars($errors['name']); ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="contact-email">Your Email: <span class="required-star">*</span></label>
                    <input type="email" id="contact-email" name="email" autocomplete="email" required aria-required="true"
                           placeholder="e.g. ananya@college.edu"
                           value="<?php echo htmlspecialchars($formData['email']); ?>"
                           class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-msg" aria-live="polite"><?php echo htmlspecialchars($errors['email']); ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="contact-subject">Subject: <span class="required-star">*</span></label>
                    <select id="contact-subject" name="subject" required aria-required="true"
                            class="<?php echo isset($errors['subject']) ? 'input-error' : ''; ?>">
                        <option value="">-- Select Subject --</option>
                        <?php foreach (get_subjects_map() as $code => $title): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($formData['subject'] === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['subject'])): ?>
                        <span class="error-msg" aria-live="polite"><?php echo htmlspecialchars($errors['subject']); ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="contact-message">Message: <span class="required-star">*</span></label>
                    <textarea id="contact-message" name="message" rows="5" cols="40"
                              placeholder="Describe your query in detail (minimum 10 characters)..."
                              required aria-required="true"
                              class="<?php echo isset($errors['message']) ? 'input-error' : ''; ?>"><?php echo htmlspecialchars($formData['message']); ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <span class="error-msg" aria-live="polite"><?php echo htmlspecialchars($errors['message']); ?></span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary" id="submitContactBtn">Send Message (POST)</button>
                    &nbsp;
                    <a href="view_records.php?type=contacts" class="btn btn-outline">&#128196; View Stored Queries</a>
                </div>
            </form>
        </section>

        <aside aria-labelledby="office-heading">
            <h3 id="office-heading">Office Details</h3>
            <address>
                StudentHub Administration Office<br>
                Room 001, Main Building<br>
                College Campus, City &mdash; 380015<br>
                <a href="mailto:support@studenthub.edu">support@studenthub.edu</a><br>
                Phone: <a href="tel:+912345678900">+91 23456 78900</a>
            </address>
        </aside>

    </main>

    <footer role="contentinfo">
        <nav aria-label="Footer Navigation">
            <ul>
                <li><a href="../about.html">About</a></li>
                <li><a href="contact.php" aria-current="page">Contact</a></li>
                <li><a href="../faq.html">FAQ</a></li>
                <li><a href="../feedback.html">Feedback</a></li>
                <li><a href="view_records.php">Stored Records</a></li>
            </ul>
        </nav>
        <p>&copy; 2026 StudentHub &mdash; Institutional Web Portal</p>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>

</html>
