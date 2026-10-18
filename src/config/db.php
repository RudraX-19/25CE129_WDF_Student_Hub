<?php
/**
 * StudentHub Portal - Practical 8
 * Secure MySQL Database Connection with PDO, Prepared Statements, and Stored Procedures
 * Course: Web Development Fundamentals (ITUE203)
 */

if (!defined('STUDENTHUB_ACCESS')) {
    define('STUDENTHUB_ACCESS', true);
}

// ----------------------------------------------------------------------------
// 1. Database Configuration
// ----------------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'studenthub_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared or new PDO database connection.
 *
 * @param bool $fresh If true, creates a new instance instead of returning the singleton.
 * @return PDO
 * @throws PDOException
 */
function get_db_connection($fresh = false) {
    static $pdoInstance = null;

    if ($pdoInstance !== null && !$fresh) {
        return $pdoInstance;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        // Throw exceptions on SQL errors for robust try/catch error handling
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Fetch rows as associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Disable emulated prepares to enforce native database-level prepared statements (prevents SQL injection)
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Persistent connection can be enabled if desired
        PDO::ATTR_PERSISTENT         => false,
        // Ensure connection uses UTF-8 MB4
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        if (!$fresh) {
            $pdoInstance = $pdo;
        }
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
}

// ----------------------------------------------------------------------------
// 2. Intermediate Extension: Prepared Statement Helpers
// ----------------------------------------------------------------------------

/**
 * Executes a parameterized SQL statement with bound values.
 *
 * @param string $sql Parameterized SQL query (using ? or :name placeholders)
 * @param array $params Values to bind
 * @return PDOStatement
 */
function db_query($sql, array $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetches all rows from a prepared query.
 *
 * @param string $sql
 * @param array $params
 * @return array
 */
function db_fetch_all($sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Fetches a single row from a prepared query.
 *
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function db_fetch_one($sql, array $params = []) {
    $stmt = db_query($sql, $params);
    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

/**
 * Executes an INSERT statement and returns the auto-generated primary key.
 *
 * @param string $sql
 * @param array $params
 * @return string|int
 */
function db_insert($sql, array $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Executes an UPDATE or DELETE query and returns the number of affected rows.
 *
 * @param string $sql
 * @param array $params
 * @return int
 */
function db_execute($sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->rowCount();
}

// ----------------------------------------------------------------------------
// 3. Advanced Extension: Stored Procedures Helpers
// ----------------------------------------------------------------------------

/**
 * Calls the `sp_register_student_for_event` stored procedure.
 * Demonstrates transactional execution, IN parameters, and OUT parameters.
 *
 * @param int $studentId
 * @param int $eventId
 * @param string $notes
 * @return array ['status' => string, 'message' => string, 'reg_code' => string|null]
 */
function register_student_event_sp($studentId, $eventId, $notes = '') {
    $pdo = get_db_connection();

    // Call stored procedure with user session variables for OUT parameters
    $sql = "CALL sp_register_student_for_event(?, ?, ?, @p_status, @p_message, @p_reg_code);";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$studentId, (int)$eventId, (string)$notes]);
    $stmt->closeCursor();

    // Retrieve the OUT parameters
    $outputStmt = $pdo->query("SELECT @p_status AS status, @p_message AS message, @p_reg_code AS reg_code");
    return $outputStmt->fetch() ?: [
        'status'   => 'ERROR',
        'message'  => 'Failed to retrieve stored procedure output.',
        'reg_code' => null
    ];
}

/**
 * Calls `sp_get_student_summary` stored procedure.
 *
 * @param int $studentId
 * @return array|null
 */
function get_student_summary_sp($studentId) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("CALL sp_get_student_summary(?)");
    $stmt->execute([(int)$studentId]);
    $result = $stmt->fetch();
    $stmt->closeCursor();
    return $result ?: null;
}

/**
 * Calls `sp_get_upcoming_events` stored procedure.
 *
 * @return array
 */
function get_upcoming_events_sp() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("CALL sp_get_upcoming_events()");
    $events = $stmt->fetchAll();
    $stmt->closeCursor();
    return $events;
}

// ----------------------------------------------------------------------------
// 4. Diagnostic & Health Check Utility
// ----------------------------------------------------------------------------

/**
 * Performs a comprehensive database health and connectivity check.
 *
 * @return array
 */
function db_health_check() {
    $startTime = microtime(true);
    try {
        $pdo = get_db_connection();
        $latency = round((microtime(true) - $startTime) * 1000, 2);

        // Fetch MySQL/MariaDB server version
        $versionStmt = $pdo->query("SELECT VERSION() AS ver, DATABASE() AS db_name, USER() AS db_user");
        $serverInfo = $versionStmt->fetch();

        // Fetch tables and counts
        $tables = [
            'courses'           => 'Academic courses catalog (Parent table)',
            'students'          => 'Student directory with foreign key to courses',
            'events'            => 'Campus events catalog',
            'registrations'     => 'M:N relationship connecting students & events',
            'contact_inquiries' => 'User queries and contact messages'
        ];

        $tableStats = [];
        foreach ($tables as $tbl => $desc) {
            try {
                $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM `{$tbl}`");
                $rowCount = $countStmt->fetch()['total'] ?? 0;
                $tableStats[$tbl] = [
                    'exists'      => true,
                    'row_count'   => (int)$rowCount,
                    'description' => $desc
                ];
            } catch (PDOException $ex) {
                $tableStats[$tbl] = [
                    'exists'      => false,
                    'row_count'   => 0,
                    'description' => $desc,
                    'error'       => $ex->getMessage()
                ];
            }
        }

        // Check Stored Procedures
        $procStmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = " . $pdo->quote(DB_NAME));
        $procs = $procStmt->fetchAll();
        $procNames = array_column($procs, 'Name');

        return [
            'status'         => 'Connected',
            'success'        => true,
            'driver'         => 'PDO MySQL (pdo_mysql)',
            'server_version' => $serverInfo['ver'] ?? 'Unknown',
            'database_name'  => $serverInfo['db_name'] ?? DB_NAME,
            'database_user'  => $serverInfo['db_user'] ?? DB_USER,
            'host'           => DB_HOST . ':' . DB_PORT,
            'latency_ms'     => $latency,
            'tables'         => $tableStats,
            'procedures'     => $procNames,
            'error'          => null
        ];
    } catch (PDOException $e) {
        return [
            'status'         => 'Disconnected',
            'success'        => false,
            'driver'         => 'PDO MySQL (pdo_mysql)',
            'server_version' => 'N/A',
            'database_name'  => DB_NAME,
            'database_user'  => DB_USER,
            'host'           => DB_HOST . ':' . DB_PORT,
            'latency_ms'     => 0,
            'tables'         => [],
            'procedures'     => [],
            'error'          => $e->getMessage()
        ];
    }
}
