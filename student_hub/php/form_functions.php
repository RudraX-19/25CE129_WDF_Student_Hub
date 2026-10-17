<?php
/**
 * StudentHub Portal - Practical 7
 * PHP Form Processing, Server-Side Validation, Sanitization, CSRF Protection, and Safe File Storage (CSV & JSON)
 * Course: Web Development Fundamentals (ITUE203)
 */

if (!defined('STUDENTHUB_ACCESS')) {
    define('STUDENTHUB_ACCESS', true);
}

// --------------------------------------------------------------------------
// 1. Session & CSRF Token Management (Advanced Extension)
// --------------------------------------------------------------------------

/**
 * Initializes a secure session if not already active.
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            @ini_set('session.cookie_httponly', 1);
            @ini_set('session.use_only_cookies', 1);
        }
        @session_start();
    }
}

/**
 * Generates or retrieves the active CSRF token.
 *
 * @return string 64-character hexadecimal token
 */
function get_csrf_token() {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies submitted CSRF token against session token using timing-safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token($token) {
    init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// --------------------------------------------------------------------------
// 2. Sanitization Helpers
// --------------------------------------------------------------------------

/**
 * Strips whitespace, removes slashes, and escapes HTML entities to prevent XSS.
 *
 * @param mixed $data
 * @return string
 */
function sanitize_text($data) {
    if ($data === null) {
        return '';
    }
    $data = trim((string)$data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizes an email address.
 *
 * @param string $email
 * @return string
 */
function sanitize_email($email) {
    $email = trim((string)$email);
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitizes digits-only input (e.g. mobile number).
 *
 * @param string $digits
 * @return string
 */
function sanitize_digits($digits) {
    return preg_replace('/[^0-9]/', '', (string)$digits);
}

// --------------------------------------------------------------------------
// 3. Validation Rules and Dictionaries
// --------------------------------------------------------------------------

function get_courses_map() {
    return [
        'ce'      => 'Computer Engineering',
        'cs'      => 'Computer Science',
        'math'    => 'Mathematics',
        'physics' => 'Physics',
        'biology' => 'Biology',
        'arts'    => 'Arts & Humanities'
    ];
}

function get_years_map() {
    return [
        '1' => '1st Year',
        '2' => '2nd Year',
        '3' => '3rd Year',
        '4' => '4th Year'
    ];
}

function get_subjects_map() {
    return [
        'academic'   => 'Academic Enquiry',
        'technical'  => 'Technical Support',
        'admission'  => 'Admission Related',
        'other'      => 'Other'
    ];
}

/**
 * Validates Registration Form inputs on the server side.
 *
 * @param array $post Raw $_POST array
 * @return array ['valid' => bool, 'errors' => array, 'data' => array]
 */
function validate_registration_form($post) {
    $errors = [];
    $data = [];

    // 1. Full Name: 3-50 characters, letters and spaces only
    $rawName = $post['fullname'] ?? '';
    $name = trim($rawName);
    if ($name === '') {
        $errors['fullname'] = 'Full Name is required.';
    } elseif (!preg_match('/^[A-Za-z\s]{3,50}$/', $name)) {
        $errors['fullname'] = 'Name must be 3-50 characters long and contain only letters and spaces.';
    }
    $data['fullname'] = sanitize_text($name);

    // 2. Email: valid RFC format
    $rawEmail = $post['email'] ?? '';
    $cleanEmail = sanitize_email($rawEmail);
    if ($cleanEmail === '') {
        $errors['email'] = 'Student Email is required.';
    } elseif (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (e.g., student@college.edu).';
    }
    $data['email'] = $cleanEmail;

    // 3. Mobile: exactly 10 digits
    $rawMobile = $post['mobile'] ?? '';
    $cleanMobile = sanitize_digits($rawMobile);
    if ($cleanMobile === '') {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $cleanMobile)) {
        $errors['mobile'] = 'Mobile number must be exactly 10 digits.';
    }
    $data['mobile'] = $cleanMobile;

    // 4. Course: must exist in whitelist
    $courses = get_courses_map();
    $rawCourse = $post['course'] ?? '';
    if ($rawCourse === '' || !array_key_exists($rawCourse, $courses)) {
        $errors['course'] = 'Please select a valid course from the list.';
        $data['course'] = '';
        $data['course_label'] = '';
    } else {
        $data['course'] = $rawCourse;
        $data['course_label'] = $courses[$rawCourse];
    }

    // 5. Year: must exist in whitelist
    $years = get_years_map();
    $rawYear = $post['year'] ?? '';
    if ($rawYear === '' || !array_key_exists($rawYear, $years)) {
        $errors['year'] = 'Please select a valid year.';
        $data['year'] = '';
        $data['year_label'] = '';
    } else {
        $data['year'] = $rawYear;
        $data['year_label'] = $years[$rawYear];
    }

    // 6. Gender: male, female, or other
    $rawGender = $post['gender'] ?? '';
    $validGenders = ['male', 'female', 'other'];
    if ($rawGender === '' || !in_array($rawGender, $validGenders, true)) {
        $errors['gender'] = 'Please select your gender.';
        $data['gender'] = '';
    } else {
        $data['gender'] = ucfirst($rawGender);
    }

    // 7. Password: min 8 characters, letters & numbers/symbols
    $rawPassword = $post['password'] ?? '';
    $rawConfirmPassword = $post['confirm-password'] ?? ($post['confirm_password'] ?? '');

    if ($rawPassword === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($rawPassword) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } else {
        $hasUpper = preg_match('/[A-Z]/', $rawPassword);
        $hasLower = preg_match('/[a-z]/', $rawPassword);
        $hasNum   = preg_match('/[0-9]/', $rawPassword);
        $hasSpec  = preg_match('/[^A-Za-z0-9]/', $rawPassword);
        $checks   = ($hasUpper ? 1 : 0) + ($hasLower ? 1 : 0) + ($hasNum ? 1 : 0) + ($hasSpec ? 1 : 0);
        if ($checks < 2) {
            $errors['password'] = 'Password should contain a mix of uppercase, lowercase, numbers, or symbols.';
        }
    }

    // 8. Confirm Password: must match
    if ($rawConfirmPassword === '') {
        $errors['confirm-password'] = 'Please confirm your password.';
    } elseif ($rawPassword !== $rawConfirmPassword) {
        $errors['confirm-password'] = 'Passwords do not match.';
    }

    // 9. Terms and Conditions: must be accepted
    $rawTerms = $post['terms'] ?? '';
    if (empty($rawTerms)) {
        $errors['terms'] = 'You must agree to the Student Terms and Conditions.';
        $data['terms'] = false;
    } else {
        $data['terms'] = true;
    }

    // Passwords are never stored in plain text; create bcrypt hash
    if (empty($errors['password']) && empty($errors['confirm-password'])) {
        $data['password_hash'] = password_hash($rawPassword, PASSWORD_DEFAULT);
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
        'data'   => $data
    ];
}

/**
 * Validates Contact Form inputs on the server side.
 *
 * @param array $post Raw $_POST array
 * @return array ['valid' => bool, 'errors' => array, 'data' => array]
 */
function validate_contact_form($post) {
    $errors = [];
    $data = [];

    // 1. Name: 3-50 chars
    $name = trim($post['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Your Name is required.';
    } elseif (strlen($name) < 3 || strlen($name) > 50) {
        $errors['name'] = 'Name must be between 3 and 50 characters.';
    }
    $data['name'] = sanitize_text($name);

    // 2. Email: valid RFC format
    $cleanEmail = sanitize_email($post['email'] ?? '');
    if ($cleanEmail === '') {
        $errors['email'] = 'Your Email is required.';
    } elseif (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $data['email'] = $cleanEmail;

    // 3. Subject: must exist in whitelist
    $subjects = get_subjects_map();
    $rawSubject = $post['subject'] ?? '';
    if ($rawSubject === '' || !array_key_exists($rawSubject, $subjects)) {
        $errors['subject'] = 'Please select a valid subject category.';
        $data['subject'] = '';
        $data['subject_label'] = '';
    } else {
        $data['subject'] = $rawSubject;
        $data['subject_label'] = $subjects[$rawSubject];
    }

    // 4. Message: 10-1000 chars
    $message = trim($post['message'] ?? '');
    if ($message === '') {
        $errors['message'] = 'Message is required.';
    } elseif (strlen($message) < 10) {
        $errors['message'] = 'Message must be at least 10 characters long.';
    } elseif (strlen($message) > 1000) {
        $errors['message'] = 'Message cannot exceed 1000 characters.';
    }
    $data['message'] = sanitize_text($message);

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
        'data'   => $data
    ];
}

// --------------------------------------------------------------------------
// 4. Safe File Writing with Exclusive Locking (flock)
// --------------------------------------------------------------------------

/**
 * Safely appends a row to a CSV file using flock(LOCK_EX).
 * Automatically creates headers if the file is new or empty.
 *
 * @param string $filepath Absolute or relative file path
 * @param array $headers Column header list
 * @param array $row Associative array or indexed array of row values
 * @return bool True on success, false on failure
 */
function append_to_csv_safe($filepath, array $headers, array $row) {
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log("Failed to create directory: {$dir}");
            return false;
        }
    }

    $isNewOrEmpty = !file_exists($filepath) || (filesize($filepath) === 0);
    $fp = fopen($filepath, 'a+');
    if (!$fp) {
        error_log("Could not open CSV file for writing: {$filepath}");
        return false;
    }

    // Acquire exclusive lock
    if (flock($fp, LOCK_EX)) {
        // If file was empty, write headers first
        if ($isNewOrEmpty) {
            fputcsv($fp, $headers);
        }

        // Format row to match headers order if associative
        $line = [];
        foreach ($headers as $header) {
            $line[] = $row[$header] ?? '';
        }

        fputcsv($fp, $line);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        error_log("Could not acquire exclusive lock on: {$filepath}");
        fclose($fp);
        return false;
    }
}

/**
 * Safely appends a record to a JSON array file using flock(LOCK_EX).
 *
 * @param string $filepath
 * @param array $record Associative array of the new record
 * @return bool True on success, false on failure
 */
function append_to_json_safe($filepath, array $record) {
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log("Failed to create directory: {$dir}");
            return false;
        }
    }

    $fp = fopen($filepath, 'c+');
    if (!$fp) {
        error_log("Could not open JSON file for writing: {$filepath}");
        return false;
    }

    // Acquire exclusive lock
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($filepath);
        $records = [];

        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $records = $decoded;
            }
        }

        $records[] = $record;

        // Truncate and write complete formatted JSON array
        ftruncate($fp, 0);
        rewind($fp);
        $jsonString = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        fwrite($fp, $jsonString);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        error_log("Could not acquire exclusive lock on: {$filepath}");
        fclose($fp);
        return false;
    }
}

// --------------------------------------------------------------------------
// 5. High-Level Record Persisters (Registrations & Contacts)
// --------------------------------------------------------------------------

/**
 * Persists a validated student registration to both CSV and JSON.
 *
 * @param array $data Validated data array
 * @param string|null $csvPath Custom path or default
 * @param string|null $jsonPath Custom path or default
 * @return array ['success' => bool, 'record' => array, 'message' => string]
 */
function save_registration_record(array $data, $csvPath = null, $jsonPath = null) {
    $baseDir = dirname(__DIR__, 2);
    $csvPath  = $csvPath  ?: $baseDir . '/assets/data/registrations.csv';
    $jsonPath = $jsonPath ?: $baseDir . '/assets/data/registrations.json';

    // Generate unique Registration ID e.g. REG-2026-1042
    $regId = 'REG-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $record = [
        'registration_id' => $regId,
        'fullname'        => $data['fullname'],
        'email'           => $data['email'],
        'mobile'          => $data['mobile'],
        'course_code'     => $data['course'],
        'course_name'     => $data['course_label'],
        'year'            => $data['year_label'],
        'gender'          => $data['gender'],
        'password_hash'   => $data['password_hash'] ?? '',
        'registered_at'   => $timestamp,
        'ip_address'      => $ip
    ];

    $csvHeaders = [
        'registration_id',
        'fullname',
        'email',
        'mobile',
        'course_code',
        'course_name',
        'year',
        'gender',
        'password_hash',
        'registered_at',
        'ip_address'
    ];

    $csvOk  = append_to_csv_safe($csvPath, $csvHeaders, $record);
    $jsonOk = append_to_json_safe($jsonPath, $record);

    if ($csvOk && $jsonOk) {
        return [
            'success' => true,
            'record'  => $record,
            'message' => 'Registration successfully saved to CSV and JSON storage.'
        ];
    } else {
        return [
            'success' => false,
            'record'  => $record,
            'message' => 'Error writing registration to file storage.'
        ];
    }
}

/**
 * Persists a validated contact inquiry to both CSV and JSON.
 *
 * @param array $data Validated data array
 * @param string|null $csvPath Custom path or default
 * @param string|null $jsonPath Custom path or default
 * @return array ['success' => bool, 'record' => array, 'message' => string]
 */
function save_contact_record(array $data, $csvPath = null, $jsonPath = null) {
    $baseDir = dirname(__DIR__, 2);
    $csvPath  = $csvPath  ?: $baseDir . '/assets/data/contacts.csv';
    $jsonPath = $jsonPath ?: $baseDir . '/assets/data/contacts.json';

    $ticketId = 'TKT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $record = [
        'ticket_id'    => $ticketId,
        'name'         => $data['name'],
        'email'        => $data['email'],
        'subject_code' => $data['subject'],
        'subject_name' => $data['subject_label'],
        'message'      => $data['message'],
        'status'       => 'Pending Review',
        'submitted_at' => $timestamp,
        'ip_address'   => $ip
    ];

    $csvHeaders = [
        'ticket_id',
        'name',
        'email',
        'subject_code',
        'subject_name',
        'message',
        'status',
        'submitted_at',
        'ip_address'
    ];

    $csvOk  = append_to_csv_safe($csvPath, $csvHeaders, $record);
    $jsonOk = append_to_json_safe($jsonPath, $record);

    if ($csvOk && $jsonOk) {
        return [
            'success' => true,
            'record'  => $record,
            'message' => 'Contact message successfully saved to CSV and JSON storage.'
        ];
    } else {
        return [
            'success' => false,
            'record'  => $record,
            'message' => 'Error writing contact message to file storage.'
        ];
    }
}

// --------------------------------------------------------------------------
// 6. Data Readers (Intermediate Extension - Webpage View)
// --------------------------------------------------------------------------

/**
 * Reads all rows from a CSV file into an array of associative arrays.
 *
 * @param string $filepath
 * @return array
 */
function read_csv_records($filepath) {
    if (!file_exists($filepath) || filesize($filepath) === 0) {
        return [];
    }

    $records = [];
    $fp = fopen($filepath, 'r');
    if (!$fp) {
        return [];
    }

    if (flock($fp, LOCK_SH)) {
        $headers = fgetcsv($fp);
        if ($headers) {
            while (($row = fgetcsv($fp)) !== false) {
                if (count($row) === count($headers)) {
                    $records[] = array_combine($headers, $row);
                }
            }
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $records;
}

/**
 * Reads all records from a JSON file into an array.
 *
 * @param string $filepath
 * @return array
 */
function read_json_records($filepath) {
    if (!file_exists($filepath) || filesize($filepath) === 0) {
        return [];
    }

    $records = [];
    $fp = fopen($filepath, 'r');
    if (!$fp) {
        return [];
    }

    if (flock($fp, LOCK_SH)) {
        $content = fread($fp, filesize($filepath));
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $records = $decoded;
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $records;
}
