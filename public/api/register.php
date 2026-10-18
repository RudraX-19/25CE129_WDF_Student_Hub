<?php
/**
 * StudentHub Portal - Practical 7
 * Server-Side Registration Form Processor and Interface
 * Features: POST submission, server-side validation & sanitization, CSRF token validation,
 * safe CSV/JSON storage using flock(), error & success message display.
 */

require_once dirname(__DIR__, 2) . '/src/core/form_functions.php';
init_session();

$csrf_token = get_csrf_token();
$errors = [];
$successRecord = null;
$formData = [
    'fullname' => '',
    'email'    => '',
    'mobile'   => '',
    'course'   => '',
    'year'     => '',
    'gender'   => '',
    'terms'    => false
];

// Handle POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Advanced Extension: Validate CSRF Token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        http_response_code(403);
        $errors['csrf'] = 'Security Alert: CSRF token validation failed or expired. Please reload the page.';
    } else {
        // 2. Validate and sanitize inputs on server side
        $validation = validate_registration_form($_POST);
        $formData   = array_merge($formData, $validation['data']);
        $errors     = $validation['errors'];

        // 3. If validation passed, store safely in CSV and JSON
        if ($validation['valid']) {
            $saveResult = save_registration_record($validation['data']);
            if ($saveResult['success']) {
                $successRecord = $saveResult['record'];
                // Reset form values on success
                $formData = [
                    'fullname' => '',
                    'email'    => '',
                    'mobile'   => '',
                    'course'   => '',
                    'year'     => '',
                    'gender'   => '',
                    'terms'    => false
                ];
                // Refresh CSRF token for next request
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
            } else {
                $errors['storage'] = 'System Error: Failed to write record to file storage. Please try again.';
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
    <title>StudentHub - Register (PHP Form Processing)</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <!-- Skip to main content (Accessibility) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Join the centralized platform for all your academic and campus needs.</p>
        <div class="user-info">
            <a href="view_records.php" class="records-link">&#128196; View Stored Records</a>
            <a href="../login.html">Login</a>
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
            <li><a href="contact.php">Contact Us</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">

        <nav aria-label="Breadcrumb">
            <ol>
                <li><a href="../index.html">Home</a></li>
                <li aria-current="page">Student Registration (Practical 7)</li>
            </ol>
        </nav>

        <section aria-labelledby="register-heading">
            <h2 id="register-heading">Student Registration &mdash; Server-Side Processing</h2>
            <p>Practical 7: PHP POST processing, server-side sanitization, dual CSV/JSON storage, and CSRF protection.</p>

            <!-- Server-Side Success Message -->
            <?php if ($successRecord): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <div class="alert-icon">&#10004;</div>
                    <div class="alert-content">
                        <h3 style="margin: 0 0 8px 0; color: #155724;">Registration Successful!</h3>
                        <p style="margin: 0 0 10px 0;">Your registration has been validated and safely saved to both <strong>CSV</strong> and <strong>JSON</strong> file storage.</p>
                        <div class="submission-receipt">
                            <p><strong>Registration ID:</strong> <span class="badge badge-success"><?php echo htmlspecialchars($successRecord['registration_id']); ?></span></p>
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($successRecord['fullname']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($successRecord['email']); ?></p>
                            <p><strong>Mobile:</strong> <?php echo htmlspecialchars($successRecord['mobile']); ?></p>
                            <p><strong>Course &amp; Year:</strong> <?php echo htmlspecialchars($successRecord['course_name']); ?> (<?php echo htmlspecialchars($successRecord['year']); ?>)</p>
                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($successRecord['gender']); ?></p>
                            <p><strong>Password Security:</strong> <span class="badge badge-info">Encrypted (Bcrypt Hash)</span></p>
                            <p><strong>Registered On:</strong> <?php echo htmlspecialchars($successRecord['registered_at']); ?></p>
                            <p><strong>Storage Status:</strong> <span class="badge badge-success">&#10003; CSV Appended</span> &nbsp; <span class="badge badge-success">&#10003; JSON Appended</span></p>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="view_records.php?type=registrations" class="btn btn-primary">&#128065; View Stored Records</a>
                            <a href="../login.html" class="btn btn-secondary">Proceed to Login &rarr;</a>
                            <a href="register.php" class="btn btn-outline">Register Another Student</a>
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
                                <li><strong><?php echo ucfirst(str_replace('-', ' ', $field)); ?>:</strong> <?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- The Registration Form -->
            <form action="register.php" method="POST" aria-label="Student Registration Form" novalidate id="registrationForm">
                
                <!-- Advanced Extension: CSRF Token Hidden Input -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <table cellpadding="8" cellspacing="0" border="0" role="presentation">
                    <tr>
                        <td><label for="fullname">Full Name <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <input type="text" id="fullname" name="fullname" autocomplete="name" required aria-required="true"
                                   placeholder="e.g. Rudra Vaghasiya"
                                   value="<?php echo htmlspecialchars($formData['fullname']); ?>"
                                   class="<?php echo isset($errors['fullname']) ? 'input-error' : ''; ?>">
                            <span class="error-msg" id="fullname-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['fullname'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="email">Student Email <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <input type="email" id="email" name="email" autocomplete="email" required aria-required="true"
                                   placeholder="e.g. student@college.edu"
                                   value="<?php echo htmlspecialchars($formData['email']); ?>"
                                   class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                            <span class="error-msg" id="email-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['email'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="mobile">Mobile Number <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <input type="tel" id="mobile" name="mobile" autocomplete="tel" placeholder="e.g. 9876543210" required aria-required="true"
                                   value="<?php echo htmlspecialchars($formData['mobile']); ?>"
                                   class="<?php echo isset($errors['mobile']) ? 'input-error' : ''; ?>">
                            <span class="error-msg" id="mobile-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['mobile'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="course">Course <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <select id="course" name="course" required aria-required="true" class="<?php echo isset($errors['course']) ? 'input-error' : ''; ?>">
                                <option value="">-- Select Course --</option>
                                <?php foreach (get_courses_map() as $code => $title): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($formData['course'] === $code) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error-msg" id="course-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['course'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="year">Year <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <select id="year" name="year" required aria-required="true" class="<?php echo isset($errors['year']) ? 'input-error' : ''; ?>">
                                <option value="">-- Select Year --</option>
                                <?php foreach (get_years_map() as $yr => $label): ?>
                                    <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($formData['year'] == $yr) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error-msg" id="year-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['year'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label>Gender <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td role="group" aria-label="Gender">
                            <input type="radio" id="gender-male" name="gender" value="male" required <?php echo (strtolower($formData['gender']) === 'male') ? 'checked' : ''; ?>>
                            <label for="gender-male">Male</label>
                            &nbsp;&nbsp;
                            <input type="radio" id="gender-female" name="gender" value="female" <?php echo (strtolower($formData['gender']) === 'female') ? 'checked' : ''; ?>>
                            <label for="gender-female">Female</label>
                            &nbsp;&nbsp;
                            <input type="radio" id="gender-other" name="gender" value="other" <?php echo (strtolower($formData['gender']) === 'other') ? 'checked' : ''; ?>>
                            <label for="gender-other">Other</label>
                            <br><span class="error-msg" id="gender-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['gender'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="password">Create Password <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <input type="password" id="password" name="password" autocomplete="new-password" required aria-required="true"
                                   class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                            <span class="error-msg" id="password-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['password'] ?? ''); ?>
                            </span>
                            <div class="password-strength" id="password-strength" aria-live="polite"></div>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="confirm-password">Confirm Password <span class="required-star">*</span></label></td>
                        <td aria-hidden="true">:</td>
                        <td>
                            <input type="password" id="confirm-password" name="confirm-password" autocomplete="new-password" required aria-required="true"
                                   class="<?php echo isset($errors['confirm-password']) ? 'input-error' : ''; ?>">
                            <span class="error-msg" id="confirm-password-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['confirm-password'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <input type="checkbox" id="terms" name="terms" required aria-required="true" <?php echo !empty($formData['terms']) ? 'checked' : ''; ?>>
                            <label for="terms">I agree to the <a href="../faq.html">Student Terms and Conditions</a></label>
                            <br><span class="error-msg" id="terms-error" aria-live="polite">
                                <?php echo htmlspecialchars($errors['terms'] ?? ''); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: center; padding-top: 15px;">
                            <button type="submit" class="btn btn-primary" id="submitRegisterBtn">Create Account (POST)</button>
                        </td>
                    </tr>
                </table>
            </form>

            <p style="margin-top: 20px;">Already have an account? <a href="../login.html">Log in here</a> | <a href="view_records.php">View Stored Registrations (CSV/JSON)</a></p>
        </section>
    </main>

    <footer role="contentinfo">
        <nav aria-label="Footer Navigation">
            <ul>
                <li><a href="../about.html">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="../faq.html">FAQ</a></li>
                <li><a href="../feedback.html">Feedback</a></li>
                <li><a href="view_records.php">Data Storage (P7)</a></li>
            </ul>
        </nav>
        <p>&copy; 2026 StudentHub &mdash; WDF Practical 7 Submission</p>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/register.js"></script>
</body>

</html>
