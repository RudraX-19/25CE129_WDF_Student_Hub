<?php
/**
 * StudentHub Portal - Practical 9
 * Secure User Registration: MySQLi Insert, Duplicate Email/Roll-No Check,
 * Password Hashing with password_hash(), Frontend + Backend Validation,
 * Prepared Statements (Intermediate Extension),
 * CSRF Token + Audit Log (Advanced Extension).
 *
 * Course: Web Development Fundamentals (ITUE203)
 * Form Target: POST → register_secure.php (self-processing)
 */

define('STUDENTHUB_ACCESS', true);
require_once __DIR__ . '/php/mysqli_db.php';
require_once __DIR__ . '/php/form_functions.php'; // reuse CSRF + session helpers

// ─── Session & CSRF ────────────────────────────────────────────────────────
init_session();
$csrf_token = get_csrf_token();

// ─── Supported Courses (mirrors DB seed) ──────────────────────────────────
$course_map = [
    'ce'      => ['id' => 1, 'label' => 'Computer Engineering'],
    'cs'      => ['id' => 2, 'label' => 'Computer Science'],
    'math'    => ['id' => 3, 'label' => 'Mathematics & Computing'],
    'physics' => ['id' => 4, 'label' => 'Applied Physics'],
    'biology' => ['id' => 5, 'label' => 'Bioinformatics & Biotechnology'],
    'arts'    => ['id' => 6, 'label' => 'Digital Arts & Humanities'],
];

// ─── State Variables ───────────────────────────────────────────────────────
$errors       = [];
$success      = null;          // holds inserted record info on success
$form         = [              // sticky form values on error
    'roll_no'  => '',
    'fullname' => '',
    'email'    => '',
    'mobile'   => '',
    'course'   => '',
    'year'     => '',
    'gender'   => '',
];

// ─── POST Handler ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Advanced Extension: CSRF Validation
    $submitted_token = trim($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($submitted_token)) {
        http_response_code(403);
        $errors['csrf'] = 'Security Alert: CSRF token mismatch. Please reload the page and try again.';
    } else {

        // ── 2. Collect & Sanitize Inputs ─────────────────────────────────
        $roll_no  = strtoupper(trim(strip_tags($_POST['roll_no']  ?? '')));
        $fullname = trim(strip_tags($_POST['fullname'] ?? ''));
        $email    = strtolower(trim(strip_tags($_POST['email']    ?? '')));
        $mobile   = trim(strip_tags($_POST['mobile']   ?? ''));
        $course   = strtolower(trim($_POST['course']   ?? ''));
        $year     = (int)($_POST['year'] ?? 0);
        $gender   = ucfirst(strtolower(trim($_POST['gender'] ?? '')));
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $terms    = isset($_POST['terms']) && $_POST['terms'] === '1';

        // Sticky form values (never store password)
        $form = compact('roll_no', 'fullname', 'email', 'mobile', 'course', 'year', 'gender');

        // ── 3. Backend (Server-Side) Validation ──────────────────────────

        // Roll No
        if ($roll_no === '') {
            $errors['roll_no'] = 'Roll number is required.';
        } elseif (!preg_match('/^[A-Z0-9]{4,20}$/', $roll_no)) {
            $errors['roll_no'] = 'Roll number must be 4–20 uppercase alphanumeric characters (e.g. 25CE129).';
        }

        // Full Name
        if ($fullname === '') {
            $errors['fullname'] = 'Full name is required.';
        } elseif (strlen($fullname) < 3 || strlen($fullname) > 100) {
            $errors['fullname'] = 'Full name must be between 3 and 100 characters.';
        } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $fullname)) {
            $errors['fullname'] = 'Full name may only contain letters, spaces, hyphens, apostrophes, or dots.';
        }

        // Email
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 150) {
            $errors['email'] = 'Email must not exceed 150 characters.';
        }

        // Mobile
        if ($mobile === '') {
            $errors['mobile'] = 'Mobile number is required.';
        } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors['mobile'] = 'Enter a valid 10-digit Indian mobile number (starts with 6–9).';
        }

        // Course
        if ($course === '' || !array_key_exists($course, $course_map)) {
            $errors['course'] = 'Please select a valid course.';
        }

        // Year
        if ($year < 1 || $year > 4) {
            $errors['year'] = 'Please select a valid year of study (1–4).';
        }

        // Gender
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Please select a valid gender.';
        }

        // Password (min 8 chars, 1 uppercase, 1 digit, 1 special)
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one digit.';
        } elseif (!preg_match('/[\W_]/', $password)) {
            $errors['password'] = 'Password must contain at least one special character (!@#$%^&* …).';
        }

        // Confirm Password
        if (!isset($errors['password']) && $confirm !== $password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        // Terms
        if (!$terms) {
            $errors['terms'] = 'You must agree to the Terms and Conditions to register.';
        }

        // ── 4. Duplicate Check (only if no validation errors so far) ────
        if (empty($errors)) {
            try {
                // 4a. Check duplicate email
                $dup_email = mysqli_fetch_one_row(
                    'SELECT student_id FROM students WHERE email = ? LIMIT 1',
                    's',
                    [$email]
                );
                if ($dup_email) {
                    $errors['email'] = 'This email address is already registered. Please use a different email or log in.';
                }

                // 4b. Check duplicate roll_no
                $dup_roll = mysqli_fetch_one_row(
                    'SELECT student_id FROM students WHERE roll_no = ? LIMIT 1',
                    's',
                    [$roll_no]
                );
                if ($dup_roll) {
                    $errors['roll_no'] = 'This roll number is already registered in the system.';
                }
            } catch (RuntimeException $e) {
                $errors['db'] = 'Database error during duplicate check. Please try again. (' . htmlspecialchars($e->getMessage()) . ')';
            }
        }

        // ── 5. Insert into Database ──────────────────────────────────────
        if (empty($errors)) {
            try {
                // Hash password using PASSWORD_DEFAULT (bcrypt)
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $course_id     = $course_map[$course]['id'];

                $result = mysqli_execute_stmt(
                    'INSERT INTO students (roll_no, fullname, email, mobile, course_id, year_of_study, gender, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'ssssiiss',
                    [$roll_no, $fullname, $email, $mobile, $course_id, $year, $gender, $password_hash]
                );

                if ($result['affected_rows'] > 0) {
                    $new_id = $result['insert_id'];

                    // Advanced Extension: Audit Log Entry
                    _write_audit_log('REGISTER', $new_id, $roll_no, $email);

                    $success = [
                        'student_id' => $new_id,
                        'roll_no'    => $roll_no,
                        'fullname'   => $fullname,
                        'email'      => $email,
                        'course'     => $course_map[$course]['label'],
                        'year'       => $year,
                        'gender'     => $gender,
                    ];

                    // Clear form and rotate CSRF token
                    $form = ['roll_no'=>'','fullname'=>'','email'=>'','mobile'=>'','course'=>'','year'=>'','gender'=>''];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } else {
                    $errors['db'] = 'Registration failed: no rows were inserted. Please try again.';
                }

            } catch (mysqli_sql_exception $e) {
                // Duplicate entry (MySQL error 1062) — graceful handling
                if ($e->getCode() === 1062) {
                    $errors['email'] = 'A student with this email or roll number already exists.';
                } else {
                    error_log('MySQLi Insert Error: ' . $e->getMessage());
                    $errors['db'] = 'A database error occurred during registration. Please try again.';
                }
            } catch (RuntimeException $e) {
                $errors['db'] = 'Database connection error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } // end CSRF valid
} // end POST

// ─── Advanced Extension: Audit Log Writer ─────────────────────────────────
function _write_audit_log(string $action, int $student_id, string $roll_no, string $email): void {
    $log_dir  = __DIR__ . '/../assets/data/';
    $log_file = $log_dir . 'audit_log.csv';

    if (!is_dir($log_dir)) {
        return; // silently skip if data dir missing (not a PHP server context)
    }

    $entry = implode(',', [
        date('Y-m-d H:i:s'),
        $action,
        $student_id,
        $roll_no,
        $email,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]) . "\n";

    $fp = @fopen($log_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $entry);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// ─── Helper: e() to HTML-encode safely ────────────────────────────────────
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Secure Registration (Practical 9)</title>
    <meta name="description" content="Secure student registration with server-side validation, duplicate email check, and bcrypt password hashing using PHP MySQLi and Prepared Statements.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Practical 9 Enhancement Styles ─────────────────────────── */
        .p9-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #1e3a5f, #0d6efd);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: .06em;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .p9-info-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #f0f6ff;
            border: 1px solid #c3daff;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #333;
        }
        .p9-info-bar a {
            color: #0d6efd;
            font-weight: 600;
            text-decoration: none;
        }
        .p9-info-bar a:hover { text-decoration: underline; }

        /* Security feature pills */
        .security-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 8px 0 18px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 14px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: .04em;
        }
        .pill-green  { background: #d4edda; color: #155724; }
        .pill-blue   { background: #cce5ff; color: #004085; }
        .pill-orange { background: #fff3cd; color: #856404; }
        .pill-purple { background: #e8d8f8; color: #5a1e8c; }

        /* Success card */
        .success-card {
            background: linear-gradient(135deg, #1a6e38, #198754);
            color: #fff;
            border-radius: 12px;
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(25,135,84,.25);
        }
        .success-card h3 {
            margin: 0 0 4px;
            font-size: 1.3rem;
        }
        .success-card p { margin: 0 0 16px; opacity: .9; font-size: .9rem; }
        .success-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px 18px;
            background: rgba(255,255,255,.12);
            border-radius: 8px;
            padding: 16px;
            font-size: .88rem;
        }
        .success-detail-grid dt { opacity: .75; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; }
        .success-detail-grid dd { margin: 0; font-weight: 700; }

        /* Error summary box */
        .error-summary {
            background: #fff5f5;
            border: 1.5px solid #f5c2c7;
            border-left: 5px solid #dc3545;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 18px;
        }
        .error-summary h4 {
            margin: 0 0 8px;
            color: #842029;
            font-size: .95rem;
        }
        .error-summary ul {
            margin: 0;
            padding-left: 20px;
            color: #842029;
            font-size: .85rem;
        }

        /* Password strength meter */
        .strength-bar-wrap {
            height: 5px;
            background: #e0e0e0;
            border-radius: 4px;
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            border-radius: 4px;
            transition: width .35s, background .35s;
        }
        .strength-label {
            font-size: .72rem;
            margin-top: 3px;
        }

        /* Form layout upgrade */
        .reg9-form .form-row {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-bottom: 14px;
        }
        .reg9-form label {
            font-weight: 600;
            font-size: .85rem;
            color: #2c3e50;
        }
        .reg9-form input[type="text"],
        .reg9-form input[type="email"],
        .reg9-form input[type="tel"],
        .reg9-form input[type="password"],
        .reg9-form select {
            padding: 9px 12px;
            border: 1.5px solid #cdd6e0;
            border-radius: 7px;
            font-size: .92rem;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
            box-sizing: border-box;
        }
        .reg9-form input:focus,
        .reg9-form select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,.15);
            outline: none;
        }
        .reg9-form input.field-error,
        .reg9-form select.field-error { border-color: #dc3545; }
        .reg9-form input.field-ok     { border-color: #198754; }
        .field-err-msg {
            font-size: .75rem;
            color: #dc3545;
            min-height: 1.1em;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 20px;
        }
        @media (max-width: 560px) { .two-col { grid-template-columns: 1fr; } }
        .gender-group {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .gender-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 400;
            cursor: pointer;
        }
        .reg9-form .submit-btn {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: .03em;
            transition: transform .15s, box-shadow .15s;
            margin-top: 6px;
        }
        .reg9-form .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(13,110,253,.35);
        }
        .reg9-form .submit-btn:active { transform: translateY(0); }
        .divider { border: none; border-top: 1px solid #e0e0e0; margin: 20px 0; }
        .section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6c757d;
            margin: 20px 0 8px;
        }
    </style>
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Secure student registration with database-backed account creation.</p>
    </header>

    <main id="main-content" role="main">
        <section aria-labelledby="reg9-heading">
            <h2 id="reg9-heading">Create Your Account <span class="p9-badge">&#128274; Practical 9</span></h2>

            <!-- Info bar -->
            <div class="p9-info-bar">
                <span>
                    <strong>Practical 9</strong>: MySQLi INSERT + Duplicate Check + <code>password_hash()</code> &mdash; bcrypt secured registration.
                </span>
                <a href="register.php">&#8592; Back to Practical 7 (File Storage)</a>
            </div>

            <!-- Security feature pills -->
            <div class="security-pills" role="list" aria-label="Security features">
                <span class="pill pill-green" role="listitem">&#10004; password_hash() bcrypt</span>
                <span class="pill pill-blue" role="listitem">&#128274; Prepared Statements</span>
                <span class="pill pill-orange" role="listitem">&#10006; Duplicate Email Check</span>
                <span class="pill pill-purple" role="listitem">&#128203; CSRF Protection</span>
                <span class="pill pill-green" role="listitem">&#10004; Audit Log</span>
            </div>

            <?php if ($success): ?>
            <!-- ── Success Card ──────────────────────────────────────── -->
            <div class="success-card" role="alert" aria-live="polite">
                <h3>&#127881; Registration Successful!</h3>
                <p>Your account has been created and securely stored in the database.</p>
                <dl class="success-detail-grid">
                    <div>
                        <dt>Student ID</dt>
                        <dd>#<?= e((string)$success['student_id']) ?></dd>
                    </div>
                    <div>
                        <dt>Roll No</dt>
                        <dd><?= e($success['roll_no']) ?></dd>
                    </div>
                    <div>
                        <dt>Full Name</dt>
                        <dd><?= e($success['fullname']) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($success['email']) ?></dd>
                    </div>
                    <div>
                        <dt>Course</dt>
                        <dd><?= e($success['course']) ?></dd>
                    </div>
                    <div>
                        <dt>Year</dt>
                        <dd>Year <?= e((string)$success['year']) ?></dd>
                    </div>
                    <div>
                        <dt>Gender</dt>
                        <dd><?= e($success['gender']) ?></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd>Stored as bcrypt hash &#9989;</dd>
                    </div>
                </dl>
                <div style="margin-top:18px; display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="login.html" style="background:#fff;color:#198754;padding:9px 20px;border-radius:7px;font-weight:700;text-decoration:none;">&#8594; Go to Login</a>
                    <a href="register_secure.php" style="background:rgba(255,255,255,.2);color:#fff;padding:9px 20px;border-radius:7px;font-weight:700;text-decoration:none;">Register Another</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <!-- ── Error Summary ────────────────────────────────────── -->
            <div class="error-summary" role="alert" aria-live="polite">
                <h4>&#9888; Please fix the following errors before submitting:</h4>
                <ul>
                    <?php foreach ($errors as $field => $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- ── Registration Form ────────────────────────────────── -->
            <?php if (!$success): ?>
            <form
                id="regSecureForm"
                class="reg9-form"
                action="register_secure.php"
                method="POST"
                novalidate
                aria-label="Secure Student Registration Form"
            >
                <!-- CSRF Token (Advanced Extension) -->
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <p class="section-title">Personal Information</p>
                <div class="two-col">
                    <!-- Roll No -->
                    <div class="form-row">
                        <label for="p9-roll_no">Roll Number <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <input
                            type="text" id="p9-roll_no" name="roll_no"
                            value="<?= e($form['roll_no']) ?>"
                            required aria-required="true"
                            placeholder="e.g. 25CE129"
                            autocomplete="off"
                            maxlength="20"
                            pattern="[A-Za-z0-9]{4,20}"
                            class="<?= isset($errors['roll_no']) ? 'field-error' : ($form['roll_no'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-roll_no-err"
                        >
                        <span class="field-err-msg" id="p9-roll_no-err" aria-live="polite">
                            <?= isset($errors['roll_no']) ? e($errors['roll_no']) : '' ?>
                        </span>
                    </div>

                    <!-- Full Name -->
                    <div class="form-row">
                        <label for="p9-fullname">Full Name <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <input
                            type="text" id="p9-fullname" name="fullname"
                            value="<?= e($form['fullname']) ?>"
                            required aria-required="true"
                            placeholder="e.g. Rudra Vaghasiya"
                            autocomplete="name"
                            maxlength="100"
                            class="<?= isset($errors['fullname']) ? 'field-error' : ($form['fullname'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-fullname-err"
                        >
                        <span class="field-err-msg" id="p9-fullname-err" aria-live="polite">
                            <?= isset($errors['fullname']) ? e($errors['fullname']) : '' ?>
                        </span>
                    </div>
                </div>

                <div class="two-col">
                    <!-- Email -->
                    <div class="form-row">
                        <label for="p9-email">Email Address <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <input
                            type="email" id="p9-email" name="email"
                            value="<?= e($form['email']) ?>"
                            required aria-required="true"
                            placeholder="student@college.edu"
                            autocomplete="email"
                            maxlength="150"
                            class="<?= isset($errors['email']) ? 'field-error' : ($form['email'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-email-err"
                        >
                        <span class="field-err-msg" id="p9-email-err" aria-live="polite">
                            <?= isset($errors['email']) ? e($errors['email']) : '' ?>
                        </span>
                    </div>

                    <!-- Mobile -->
                    <div class="form-row">
                        <label for="p9-mobile">Mobile Number <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <input
                            type="tel" id="p9-mobile" name="mobile"
                            value="<?= e($form['mobile']) ?>"
                            required aria-required="true"
                            placeholder="9876543210"
                            autocomplete="tel"
                            maxlength="10"
                            pattern="[6-9][0-9]{9}"
                            class="<?= isset($errors['mobile']) ? 'field-error' : ($form['mobile'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-mobile-err"
                        >
                        <span class="field-err-msg" id="p9-mobile-err" aria-live="polite">
                            <?= isset($errors['mobile']) ? e($errors['mobile']) : '' ?>
                        </span>
                    </div>
                </div>

                <hr class="divider">
                <p class="section-title">Academic Details</p>
                <div class="two-col">
                    <!-- Course -->
                    <div class="form-row">
                        <label for="p9-course">Course <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <select
                            id="p9-course" name="course"
                            required aria-required="true"
                            class="<?= isset($errors['course']) ? 'field-error' : ($form['course'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-course-err"
                        >
                            <option value="">-- Select Course --</option>
                            <?php foreach ($course_map as $key => $info): ?>
                            <option value="<?= e($key) ?>" <?= $form['course'] === $key ? 'selected' : '' ?>>
                                <?= e($info['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-err-msg" id="p9-course-err" aria-live="polite">
                            <?= isset($errors['course']) ? e($errors['course']) : '' ?>
                        </span>
                    </div>

                    <!-- Year -->
                    <div class="form-row">
                        <label for="p9-year">Year of Study <span aria-hidden="true" style="color:#dc3545">*</span></label>
                        <select
                            id="p9-year" name="year"
                            required aria-required="true"
                            class="<?= isset($errors['year']) ? 'field-error' : ($form['year'] ? 'field-ok' : '') ?>"
                            aria-describedby="p9-year-err"
                        >
                            <option value="">-- Select Year --</option>
                            <?php foreach ([1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year'] as $v => $lbl): ?>
                            <option value="<?= $v ?>" <?= (int)$form['year'] === $v ? 'selected' : '' ?>>
                                <?= e($lbl) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-err-msg" id="p9-year-err" aria-live="polite">
                            <?= isset($errors['year']) ? e($errors['year']) : '' ?>
                        </span>
                    </div>
                </div>

                <!-- Gender -->
                <div class="form-row">
                    <label>Gender <span aria-hidden="true" style="color:#dc3545">*</span></label>
                    <div class="gender-group" role="group" aria-labelledby="p9-gender-label" aria-describedby="p9-gender-err">
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <label>
                            <input
                                type="radio" name="gender" value="<?= strtolower($g) ?>"
                                <?= strtolower($form['gender']) === strtolower($g) ? 'checked' : '' ?>
                                required
                            >
                            <?= e($g) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="field-err-msg" id="p9-gender-err" aria-live="polite">
                        <?= isset($errors['gender']) ? e($errors['gender']) : '' ?>
                    </span>
                </div>

                <hr class="divider">
                <p class="section-title">Security</p>

                <!-- Password -->
                <div class="form-row">
                    <label for="p9-password">
                        Create Password <span aria-hidden="true" style="color:#dc3545">*</span>
                        <small style="font-weight:400;color:#6c757d;">(min 8 chars, 1 uppercase, 1 digit, 1 special)</small>
                    </label>
                    <input
                        type="password" id="p9-password" name="password"
                        required aria-required="true"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="<?= isset($errors['password']) ? 'field-error' : '' ?>"
                        aria-describedby="p9-password-err p9-strength-label"
                    >
                    <div class="strength-bar-wrap" aria-hidden="true">
                        <div class="strength-bar" id="p9-strength-bar" style="width:0%;background:#dc3545;"></div>
                    </div>
                    <span class="strength-label" id="p9-strength-label" aria-live="polite" style="color:#6c757d;"></span>
                    <span class="field-err-msg" id="p9-password-err" aria-live="polite">
                        <?= isset($errors['password']) ? e($errors['password']) : '' ?>
                    </span>
                </div>

                <!-- Confirm Password -->
                <div class="form-row">
                    <label for="p9-confirm_password">Confirm Password <span aria-hidden="true" style="color:#dc3545">*</span></label>
                    <input
                        type="password" id="p9-confirm_password" name="confirm_password"
                        required aria-required="true"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="<?= isset($errors['confirm_password']) ? 'field-error' : '' ?>"
                        aria-describedby="p9-confirm-err"
                    >
                    <span class="field-err-msg" id="p9-confirm-err" aria-live="polite">
                        <?= isset($errors['confirm_password']) ? e($errors['confirm_password']) : '' ?>
                    </span>
                </div>

                <!-- Terms -->
                <div class="form-row" style="flex-direction:row;align-items:flex-start;gap:10px;">
                    <input
                        type="checkbox" id="p9-terms" name="terms" value="1"
                        style="margin-top:3px;flex-shrink:0;"
                        required aria-required="true"
                        aria-describedby="p9-terms-err"
                        <?= ($form['fullname'] === '' && empty($errors)) ? '' : (isset($_POST['terms']) ? 'checked' : '') ?>
                    >
                    <label for="p9-terms" style="font-weight:400;font-size:.88rem;">
                        I agree to the <a href="faq.html">Student Terms &amp; Conditions</a>.
                        I understand my password will be stored as a secure bcrypt hash.
                    </label>
                </div>
                <span class="field-err-msg" id="p9-terms-err" aria-live="polite" style="margin-top:-10px;display:block;">
                    <?= isset($errors['terms']) ? e($errors['terms']) : '' ?>
                </span>

                <!-- Submit -->
                <button type="submit" id="p9-submit-btn" class="submit-btn">
                    &#128274; Create Secure Account
                </button>
            </form>
            <?php endif; ?>

            <p style="text-align:center;margin-top:18px;font-size:.88rem;">
                Already have an account? <a href="login.html"><strong>Log in here</strong></a>
                &nbsp;|&nbsp;
                <a href="register.php">Practical 7 File-Based Version</a>
            </p>
        </section>
    </main>

    <footer role="contentinfo">
        <nav aria-label="Footer Navigation">
            <ul>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="faq.html">FAQ</a></li>
                <li><a href="feedback.html">Feedback</a></li>
            </ul>
        </nav>
        <p>&copy; 2026 StudentHub</p>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
    /* ── Practical 9: Frontend Validation + Password Strength Meter ─── */
    (function () {
        'use strict';

        const form    = document.getElementById('regSecureForm');
        if (!form) return;

        const rollEl  = document.getElementById('p9-roll_no');
        const nameEl  = document.getElementById('p9-fullname');
        const emailEl = document.getElementById('p9-email');
        const mobEl   = document.getElementById('p9-mobile');
        const passEl  = document.getElementById('p9-password');
        const confEl  = document.getElementById('p9-confirm_password');
        const termsEl = document.getElementById('p9-terms');

        const bar   = document.getElementById('p9-strength-bar');
        const label = document.getElementById('p9-strength-label');

        // ── Password Strength Meter ──────────────────────────────────
        function calcStrength(pw) {
            let score = 0;
            if (pw.length >= 8)  score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[\W_]/.test(pw)) score++;
            return score; // 0–5
        }

        passEl.addEventListener('input', function () {
            const pw    = passEl.value;
            const score = calcStrength(pw);
            const pct   = (score / 5) * 100;
            bar.style.width = pct + '%';
            if (score <= 1)      { bar.style.background = '#dc3545'; label.textContent = pw ? 'Weak'      : ''; label.style.color = '#dc3545'; }
            else if (score <= 2) { bar.style.background = '#fd7e14'; label.textContent = 'Fair';   label.style.color = '#fd7e14'; }
            else if (score <= 3) { bar.style.background = '#ffc107'; label.textContent = 'Good';   label.style.color = '#856404'; }
            else if (score <= 4) { bar.style.background = '#20c997'; label.textContent = 'Strong'; label.style.color = '#0f5132'; }
            else                 { bar.style.background = '#198754'; label.textContent = 'Very Strong'; label.style.color = '#0f5132'; }
        });

        // ── Inline Field Validation Helpers ─────────────────────────
        function setErr(el, errId, msg) {
            el.classList.add('field-error');
            el.classList.remove('field-ok');
            const span = document.getElementById(errId);
            if (span) span.textContent = msg;
        }
        function clearErr(el, errId) {
            el.classList.remove('field-error');
            el.classList.add('field-ok');
            const span = document.getElementById(errId);
            if (span) span.textContent = '';
        }

        rollEl.addEventListener('blur', function () {
            const v = rollEl.value.trim().toUpperCase();
            if (!v) return setErr(rollEl, 'p9-roll_no-err', 'Roll number is required.');
            if (!/^[A-Z0-9]{4,20}$/.test(v)) return setErr(rollEl, 'p9-roll_no-err', 'Must be 4–20 uppercase alphanumeric characters.');
            clearErr(rollEl, 'p9-roll_no-err');
        });

        nameEl.addEventListener('blur', function () {
            const v = nameEl.value.trim();
            if (!v) return setErr(nameEl, 'p9-fullname-err', 'Full name is required.');
            if (v.length < 3) return setErr(nameEl, 'p9-fullname-err', 'Name must be at least 3 characters.');
            clearErr(nameEl, 'p9-fullname-err');
        });

        emailEl.addEventListener('blur', function () {
            const v = emailEl.value.trim();
            if (!v) return setErr(emailEl, 'p9-email-err', 'Email is required.');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return setErr(emailEl, 'p9-email-err', 'Enter a valid email address.');
            clearErr(emailEl, 'p9-email-err');
        });

        mobEl.addEventListener('blur', function () {
            const v = mobEl.value.trim();
            if (!v) return setErr(mobEl, 'p9-mobile-err', 'Mobile number is required.');
            if (!/^[6-9]\d{9}$/.test(v)) return setErr(mobEl, 'p9-mobile-err', 'Enter a valid 10-digit Indian mobile number.');
            clearErr(mobEl, 'p9-mobile-err');
        });

        confEl.addEventListener('blur', function () {
            if (confEl.value !== passEl.value) {
                return setErr(confEl, 'p9-confirm-err', 'Passwords do not match.');
            }
            clearErr(confEl, 'p9-confirm-err');
        });

        // ── Form Submit Guard ────────────────────────────────────────
        form.addEventListener('submit', function (e) {
            let ok = true;

            if (!rollEl.value.trim()) { setErr(rollEl, 'p9-roll_no-err', 'Roll number is required.'); ok = false; }
            if (!nameEl.value.trim()) { setErr(nameEl, 'p9-fullname-err', 'Full name is required.'); ok = false; }
            if (!emailEl.value.trim()) { setErr(emailEl, 'p9-email-err', 'Email is required.'); ok = false; }
            if (!mobEl.value.trim()) { setErr(mobEl, 'p9-mobile-err', 'Mobile is required.'); ok = false; }
            if (passEl.value.length < 8) { setErr(passEl, 'p9-password-err', 'Password must be at least 8 characters.'); ok = false; }
            if (confEl.value !== passEl.value) { setErr(confEl, 'p9-confirm-err', 'Passwords do not match.'); ok = false; }
            if (!termsEl.checked) {
                const tErr = document.getElementById('p9-terms-err');
                if (tErr) tErr.textContent = 'You must accept the Terms and Conditions.';
                ok = false;
            }

            if (!ok) {
                e.preventDefault();
                // Scroll to first error
                const firstErr = form.querySelector('.field-error');
                if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    })();
    </script>
</body>

</html>
