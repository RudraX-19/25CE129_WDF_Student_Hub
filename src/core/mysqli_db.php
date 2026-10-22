<?php
/**
 * StudentHub Portal - Practical 9
 * MySQLi Database Connection Helper
 * Course: Web Development Fundamentals (ITUE203)
 *
 * Provides a shared MySQLi connection using the same credentials as db.php (PDO).
 * Uses MySQLi as required by Practical 9 (MySQLi extension).
 */

if (!defined('STUDENTHUB_ACCESS')) {
    define('STUDENTHUB_ACCESS', true);
}

// -----------------------------------------------------------------------
// Database Configuration (mirrors db.php constants if not already defined)
// -----------------------------------------------------------------------
if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')    ?: 'studenthub_db');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')    ?: 'root');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared MySQLi connection (singleton pattern).
 *
 * @return mysqli
 * @throws RuntimeException on connection failure
 */
function get_mysqli_connection(): mysqli {
    static $mysqli = null;

    if ($mysqli !== null && $mysqli->ping()) {
        return $mysqli;
    }

    // Enable MySQLi error reporting (throws mysqli_sql_exception on error)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        $mysqli->set_charset(DB_CHARSET);
        return $mysqli;
    } catch (mysqli_sql_exception $e) {
        // Fallback: If 127.0.0.1 failed, try localhost socket connection
        if (DB_HOST === '127.0.0.1') {
            try {
                $mysqli = new mysqli('localhost', DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
                $mysqli->set_charset(DB_CHARSET);
                return $mysqli;
            } catch (\Throwable $ignored) {}
        }
        error_log('MySQLi Connection Error: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed (' . $e->getMessage() . '). Please verify MySQL is running in XAMPP and studenthub_db exists.');
    }
}

/**
 * Executes a MySQLi prepared SELECT query and returns all rows as associative arrays.
 *
 * @param string $sql    Parameterized SQL with ? placeholders
 * @param string $types  Bind types string (e.g. 'si', 'ss')
 * @param array  $params Values to bind (in order)
 * @return array
 */
function mysqli_fetch_all_rows(string $sql, string $types = '', array $params = []): array {
    $db   = get_mysqli_connection();
    $stmt = $db->prepare($sql);

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Executes a MySQLi prepared SELECT query and returns a single row or null.
 *
 * @param string $sql
 * @param string $types
 * @param array  $params
 * @return array|null
 */
function mysqli_fetch_one_row(string $sql, string $types = '', array $params = []): ?array {
    $db   = get_mysqli_connection();
    $stmt = $db->prepare($sql);

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Executes a MySQLi prepared INSERT/UPDATE/DELETE and returns affected rows + insert_id.
 *
 * @param string $sql
 * @param string $types
 * @param array  $params
 * @return array ['affected_rows' => int, 'insert_id' => int]
 */
function mysqli_execute_stmt(string $sql, string $types = '', array $params = []): array {
    $db   = get_mysqli_connection();
    $stmt = $db->prepare($sql);

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = [
        'affected_rows' => $stmt->affected_rows,
        'insert_id'     => (int)$stmt->insert_id,
    ];
    $stmt->close();
    return $result;
}
