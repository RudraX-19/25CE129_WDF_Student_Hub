<?php
/**
 * StudentHub Portal - Practical 7
 * Intermediate Extension: Display Stored CSV / JSON Records on a Webpage
 * Course: Web Development Fundamentals (ITUE203)
 */

require_once __DIR__ . '/php/form_functions.php';

$baseDir = dirname(__DIR__);
$regCsvPath  = $baseDir . '/assets/data/registrations.csv';
$regJsonPath = $baseDir . '/assets/data/registrations.json';
$conCsvPath  = $baseDir . '/assets/data/contacts.csv';
$conJsonPath = $baseDir . '/assets/data/contacts.json';

// Handle direct file download if requested
if (isset($_GET['download'])) {
    $dl = $_GET['download'];
    $allowedDownloads = [
        'reg_csv'  => ['path' => $regCsvPath,  'name' => 'registrations.csv', 'mime' => 'text/csv'],
        'reg_json' => ['path' => $regJsonPath, 'name' => 'registrations.json', 'mime' => 'application/json'],
        'con_csv'  => ['path' => $conCsvPath,  'name' => 'contacts.csv', 'mime' => 'text/csv'],
        'con_json' => ['path' => $conJsonPath, 'name' => 'contacts.json', 'mime' => 'application/json'],
    ];

    if (isset($allowedDownloads[$dl]) && file_exists($allowedDownloads[$dl]['path'])) {
        $fileInfo = $allowedDownloads[$dl];
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $fileInfo['mime']);
        header('Content-Disposition: attachment; filename="' . $fileInfo['name'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fileInfo['path']));
        readfile($fileInfo['path']);
        exit;
    }
}

// Active view tabs
$activeType   = $_GET['type'] ?? 'registrations'; // 'registrations' or 'contacts'
$activeFormat = $_GET['format'] ?? 'csv';         // 'csv' or 'json'

if (!in_array($activeType, ['registrations', 'contacts'], true)) {
    $activeType = 'registrations';
}
if (!in_array($activeFormat, ['csv', 'json'], true)) {
    $activeFormat = 'csv';
}

// Load records
$registrationsCsv  = read_csv_records($regCsvPath);
$registrationsJson = read_json_records($regJsonPath);
$contactsCsv       = read_csv_records($conCsvPath);
$contactsJson      = read_json_records($conJsonPath);

$activeRecords = [];
if ($activeType === 'registrations') {
    $activeRecords = ($activeFormat === 'csv') ? $registrationsCsv : $registrationsJson;
} else {
    $activeRecords = ($activeFormat === 'csv') ? $contactsCsv : $contactsJson;
}

$totalRegs = count($registrationsCsv);
$totalCons = count($contactsCsv);
$syncStatus = (count($registrationsCsv) === count($registrationsJson)) && (count($contactsCsv) === count($contactsJson));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Stored Records Viewer (Practical 7)</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <h1>StudentHub Portal</h1>
        <p>Practical 7: File-Based Form Processing &amp; Storage Viewer</p>
        <div class="user-info">
            <span>Server Storage: <strong>Active</strong></span>
            <a href="login.html">Logout</a>
        </div>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="dashboard.html">Dashboard</a></li>
            <li><a href="courses.html">My Courses</a></li>
            <li><a href="timetable.html">Timetable</a></li>
            <li><a href="grades.html">Grades</a></li>
            <li><a href="events.html">Events</a></li>
            <li><a href="library.html">Library</a></li>
            <li><a href="students.html">Students</a></li>
            <li><a href="view_records.php" aria-current="page">Stored Records (P7)</a></li>
            <li><a href="db_test.php">Database (P8)</a></li>
            <li><a href="register.php">Register Form</a></li>
            <li><a href="contact.php">Contact Form</a></li>
        </ul>
    </nav>

    <main id="main-content" role="main">

        <nav aria-label="Breadcrumb">
            <ol>
                <li><a href="index.html">Home</a></li>
                <li aria-current="page">Stored File Records (Practical 7)</li>
            </ol>
        </nav>

        <section aria-labelledby="records-heading">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                <div>
                    <h2 id="records-heading" style="margin: 0;">File-Based Stored Records</h2>
                    <p style="margin: 4px 0 0 0; color: #555;">Live records saved directly from PHP form submissions via safe <code>flock</code> file locking.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="register.php" class="btn btn-primary">+ New Registration</a>
                    <a href="contact.php" class="btn btn-outline">+ Send Query</a>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="stat-card" style="background: #f0f7ff; border: 1px solid #cce3ff; border-radius: 8px; padding: 15px;">
                    <div style="font-size: 0.85rem; color: #0057b7; font-weight: bold; text-transform: uppercase;">Total Registrations</div>
                    <div style="font-size: 1.8rem; font-weight: bold; color: #1a237e; margin: 5px 0;"><?php echo $totalRegs; ?></div>
                    <div style="font-size: 0.8rem; color: #666;">CSV: <?php echo count($registrationsCsv); ?> | JSON: <?php echo count($registrationsJson); ?></div>
                </div>

                <div class="stat-card" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px;">
                    <div style="font-size: 0.85rem; color: #166534; font-weight: bold; text-transform: uppercase;">Contact Inquiries</div>
                    <div style="font-size: 1.8rem; font-weight: bold; color: #14532d; margin: 5px 0;"><?php echo $totalCons; ?></div>
                    <div style="font-size: 0.8rem; color: #666;">CSV: <?php echo count($contactsCsv); ?> | JSON: <?php echo count($contactsJson); ?></div>
                </div>

                <div class="stat-card" style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 15px;">
                    <div style="font-size: 0.85rem; color: #6b21a8; font-weight: bold; text-transform: uppercase;">Storage Consistency</div>
                    <div style="font-size: 1.2rem; font-weight: bold; color: #581c87; margin: 8px 0;">
                        <?php if ($syncStatus): ?>
                            <span class="badge badge-success">&#10003; 100% In Sync</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Sync Warning</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #666;">Concurrent flock locking</div>
                </div>

                <div class="stat-card" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 15px;">
                    <div style="font-size: 0.85rem; color: #92400e; font-weight: bold; text-transform: uppercase;">Quick Downloads</div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
                        <a href="?download=reg_csv" class="btn-chip" title="Download Registrations CSV">&#128196; Regs.csv</a>
                        <a href="?download=reg_json" class="btn-chip" title="Download Registrations JSON">&#123; &#125; Regs.json</a>
                        <a href="?download=con_csv" class="btn-chip" title="Download Contacts CSV">&#128196; Con.csv</a>
                        <a href="?download=con_json" class="btn-chip" title="Download Contacts JSON">&#123; &#125; Con.json</a>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation (Registrations vs Contact Inquiries) -->
            <div class="view-controls" style="background: #ffffff; border: 1px solid #e1e7f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <!-- Dataset tabs -->
                    <div class="tab-group" role="tablist" style="display: flex; gap: 8px;">
                        <a href="?type=registrations&format=<?php echo $activeFormat; ?>"
                           class="tab-btn <?php echo ($activeType === 'registrations') ? 'active' : ''; ?>"
                           role="tab" aria-selected="<?php echo ($activeType === 'registrations') ? 'true' : 'false'; ?>">
                           &#127891; Student Registrations (<?php echo $totalRegs; ?>)
                        </a>
                        <a href="?type=contacts&format=<?php echo $activeFormat; ?>"
                           class="tab-btn <?php echo ($activeType === 'contacts') ? 'active' : ''; ?>"
                           role="tab" aria-selected="<?php echo ($activeType === 'contacts') ? 'true' : 'false'; ?>">
                           &#128233; Contact Inquiries (<?php echo $totalCons; ?>)
                        </a>
                    </div>

                    <!-- Format toggle (CSV vs JSON) -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.9rem; font-weight: 600; color: #444;">Storage Source:</span>
                        <div class="format-toggle" style="display: inline-flex; border: 1px solid #0057b7; border-radius: 6px; overflow: hidden;">
                            <a href="?type=<?php echo $activeType; ?>&format=csv"
                               class="format-btn <?php echo ($activeFormat === 'csv') ? 'active' : ''; ?>"
                               style="padding: 6px 14px; text-decoration: none; font-size: 0.85rem; font-weight: bold;">
                               CSV Storage
                            </a>
                            <a href="?type=<?php echo $activeType; ?>&format=json"
                               class="format-btn <?php echo ($activeFormat === 'json') ? 'active' : ''; ?>"
                               style="padding: 6px 14px; text-decoration: none; font-size: 0.85rem; font-weight: bold;">
                               JSON Storage
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Instant Filter Search Bar -->
                <div style="margin-top: 15px;">
                    <label for="recordSearch" style="font-size: 0.85rem; font-weight: bold; color: #555; display: block; margin-bottom: 5px;">
                        Search Records in Real-Time:
                    </label>
                    <input type="search" id="recordSearch" placeholder="Type to filter by name, ID, email, course, status..."
                           style="width: 100%; max-width: 450px; padding: 8px 12px; border: 1px solid #ccd5e0; border-radius: 6px;">
                    <span id="searchResultCount" style="font-size: 0.85rem; color: #666; margin-left: 10px;"></span>
                </div>
            </div>

            <!-- Stored Records Table -->
            <div class="data-table-wrapper" style="overflow-x: auto; background: #fff; border: 1px solid #e1e7f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #e1e7f0; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: bold; color: #333;">
                        Showing <?php echo count($activeRecords); ?> records loaded from 
                        <code>assets/data/<?php echo ($activeType === 'registrations') ? 'registrations.' . $activeFormat : 'contacts.' . $activeFormat; ?></code>
                    </span>
                    <span class="badge badge-info"><?php echo strtoupper($activeFormat); ?> Mode</span>
                </div>

                <?php if (empty($activeRecords)): ?>
                    <div style="padding: 40px; text-align: center; color: #777;">
                        <p style="font-size: 1.1rem; margin-bottom: 10px;">No records found in this storage file.</p>
                        <a href="register.php" class="btn btn-primary">Submit First Registration</a>
                    </div>
                <?php else: ?>
                    <table class="records-table" id="recordsTable" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                                <?php if ($activeType === 'registrations'): ?>
                                    <th style="padding: 12px 15px;">#</th>
                                    <th style="padding: 12px 15px;">Reg. ID</th>
                                    <th style="padding: 12px 15px;">Full Name</th>
                                    <th style="padding: 12px 15px;">Email</th>
                                    <th style="padding: 12px 15px;">Mobile</th>
                                    <th style="padding: 12px 15px;">Course</th>
                                    <th style="padding: 12px 15px;">Year</th>
                                    <th style="padding: 12px 15px;">Gender</th>
                                    <th style="padding: 12px 15px;">Password Hash</th>
                                    <th style="padding: 12px 15px;">Registered At</th>
                                <?php else: ?>
                                    <th style="padding: 12px 15px;">#</th>
                                    <th style="padding: 12px 15px;">Ticket ID</th>
                                    <th style="padding: 12px 15px;">Name</th>
                                    <th style="padding: 12px 15px;">Email</th>
                                    <th style="padding: 12px 15px;">Subject</th>
                                    <th style="padding: 12px 15px;">Message</th>
                                    <th style="padding: 12px 15px;">Status</th>
                                    <th style="padding: 12px 15px;">Submitted At</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($activeRecords as $rec): ?>
                                <tr class="record-row" style="border-bottom: 1px solid #e2e8f0;">
                                    <?php if ($activeType === 'registrations'): ?>
                                        <td style="padding: 10px 15px; color: #888;"><?php echo $counter++; ?></td>
                                        <td style="padding: 10px 15px; font-weight: bold; color: #0057b7;">
                                            <?php echo htmlspecialchars($rec['registration_id'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px; font-weight: 600;">
                                            <?php echo htmlspecialchars($rec['fullname'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <a href="mailto:<?php echo htmlspecialchars($rec['email'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($rec['email'] ?? ''); ?>
                                            </a>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <?php echo htmlspecialchars($rec['mobile'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <span class="badge badge-course"><?php echo htmlspecialchars($rec['course_name'] ?? ($rec['course_code'] ?? '')); ?></span>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <?php echo htmlspecialchars($rec['year'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <?php echo htmlspecialchars($rec['gender'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px; font-family: monospace; font-size: 0.75rem; color: #64748b;" title="<?php echo htmlspecialchars($rec['password_hash'] ?? ''); ?>">
                                            <?php 
                                                $hash = $rec['password_hash'] ?? '';
                                                echo htmlspecialchars(strlen($hash) > 16 ? substr($hash, 0, 14) . '...' : $hash);
                                            ?>
                                        </td>
                                        <td style="padding: 10px 15px; font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                                            <?php echo htmlspecialchars($rec['registered_at'] ?? ''); ?>
                                        </td>
                                    <?php else: ?>
                                        <td style="padding: 10px 15px; color: #888;"><?php echo $counter++; ?></td>
                                        <td style="padding: 10px 15px; font-weight: bold; color: #0057b7;">
                                            <?php echo htmlspecialchars($rec['ticket_id'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px; font-weight: 600;">
                                            <?php echo htmlspecialchars($rec['name'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <a href="mailto:<?php echo htmlspecialchars($rec['email'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($rec['email'] ?? ''); ?>
                                            </a>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <span class="badge badge-subject"><?php echo htmlspecialchars($rec['subject_name'] ?? ($rec['subject_code'] ?? '')); ?></span>
                                        </td>
                                        <td style="padding: 10px 15px; max-width: 280px; word-break: break-word;">
                                            <?php echo htmlspecialchars($rec['message'] ?? ''); ?>
                                        </td>
                                        <td style="padding: 10px 15px;">
                                            <?php 
                                                $status = $rec['status'] ?? 'Pending';
                                                $badgeClass = ($status === 'Resolved') ? 'badge-success' : (($status === 'In Progress') ? 'badge-warning' : 'badge-info');
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td style="padding: 10px 15px; font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                                            <?php echo htmlspecialchars($rec['submitted_at'] ?? ''); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Footer Details & Implementation Notes -->
            <div style="margin-top: 25px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h4 style="margin: 0 0 8px 0; color: #0057b7;">Practical 7 Technical Architecture Highlights:</h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: #4b5563; line-height: 1.6;">
                    <li><strong>POST Submission:</strong> Sensitive student and message data is submitted through HTTP POST only, avoiding exposure in browser URL history.</li>
                    <li><strong>Server-Side Validation:</strong> Enforces length, regex patterns, email validity, whitelist checks, and password rules on the PHP server regardless of client-side state.</li>
                    <li><strong>Safe File Writing:</strong> Concurrency issues are handled using PHP's <code>flock($fp, LOCK_EX)</code> to guarantee safe atomic writes.</li>
                    <li><strong>Dual CSV/JSON Storage:</strong> All submissions are automatically recorded in both CSV and structured JSON files simultaneously.</li>
                    <li><strong>CSRF Protection (Advanced Extension):</strong> Cryptographically random 64-hex tokens protect forms from cross-site request forgery attacks.</li>
                </ul>
            </div>
        </section>
    </main>

    <footer role="contentinfo">
        <nav aria-label="Footer Navigation">
            <ul>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="faq.html">FAQ</a></li>
                <li><a href="feedback.html">Feedback</a></li>
                <li><a href="view_records.php">Stored Records (P7)</a></li>
            </ul>
        </nav>
        <p>&copy; 2026 StudentHub &mdash; WDF Practical 7 Submission</p>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        // Real-time table search filter
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('recordSearch');
            const table = document.getElementById('recordsTable');
            const countDisplay = document.getElementById('searchResultCount');
            
            if (!searchInput || !table) return;

            const rows = table.querySelectorAll('tbody tr.record-row');
            const total = rows.length;

            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                let matchCount = 0;

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                        matchCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (query.length > 0) {
                    countDisplay.textContent = `Showing ${matchCount} of ${total} records`;
                } else {
                    countDisplay.textContent = '';
                }
            });
        });
    </script>
</body>

</html>
