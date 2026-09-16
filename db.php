<?php
/**
 * Database bootstrap + small wpdb-style helper functions.
 *
 * This intentionally mirrors the shape of WordPress's $wpdb helper methods
 * (get_results / get_row / get_var / insert / update / delete) since the
 * original site's logic was written against that API. Keeping the same
 * shape here made porting the business logic mechanical and low-risk.
 */

require_once __DIR__ . '/config.php';

date_default_timezone_set(SITE_TIMEZONE);

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        )
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. ' . (APP_DEBUG ? $e->getMessage() : 'Please check config.php.'));
}

/**
 * Run a query and return all rows as an array of objects.
 * @param string $sql
 * @param array $params
 * @return array
 */
function db_get_results($sql, $params = array()) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run a query and return the first row as an object, or null.
 */
function db_get_row($sql, $params = array()) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Run a query and return a single scalar value, or null.
 */
function db_get_var($sql, $params = array()) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

/**
 * Run a write query (no result rows expected). Returns affected row count.
 */
function db_query($sql, $params = array()) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Insert a row. $data is an assoc array of column => value.
 * Returns the new row's id, or false on failure.
 */
function db_insert($table, $data) {
    global $pdo;
    $columns = array_keys($data);
    $placeholders = array_map(function ($c) { return ':' . $c; }, $columns);

    $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    foreach ($data as $col => $val) {
        $stmt->bindValue(':' . $col, $val);
    }

    $ok = $stmt->execute();
    return $ok ? $pdo->lastInsertId() : false;
}

/**
 * Update rows matching $where (assoc array, AND-combined) with $data.
 * Returns number of affected rows, or false on failure.
 */
function db_update($table, $data, $where) {
    global $pdo;

    $set = array();
    foreach (array_keys($data) as $col) {
        $set[] = "`$col` = :set_$col";
    }

    $cond = array();
    foreach (array_keys($where) as $col) {
        $cond[] = "`$col` = :where_$col";
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $cond);
    $stmt = $pdo->prepare($sql);

    foreach ($data as $col => $val) {
        $stmt->bindValue(':set_' . $col, $val);
    }
    foreach ($where as $col => $val) {
        $stmt->bindValue(':where_' . $col, $val);
    }

    $ok = $stmt->execute();
    return $ok ? $stmt->rowCount() : false;
}

/**
 * Delete rows matching $where (assoc array, AND-combined).
 * Returns number of affected rows, or false on failure.
 */
function db_delete($table, $where) {
    global $pdo;

    $cond = array();
    foreach (array_keys($where) as $col) {
        $cond[] = "`$col` = :$col";
    }

    $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $cond);
    $stmt = $pdo->prepare($sql);

    foreach ($where as $col => $val) {
        $stmt->bindValue(':' . $col, $val);
    }

    $ok = $stmt->execute();
    return $ok ? $stmt->rowCount() : false;
}

/**
 * Send a JSON success response and exit (mirrors wp_send_json_success).
 */
function json_success($data = array()) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'data' => $data));
    exit;
}

/**
 * Send a JSON error response and exit (mirrors wp_send_json_error).
 */
function json_fail($data = array()) {
    header('Content-Type: application/json');
    if (is_string($data)) {
        $data = array('message' => $data);
    }
    echo json_encode(array('success' => false, 'data' => $data));
    exit;
}

/**
 * Get a Central-Time DateTime, mirroring the original site's get_central_time().
 * - 'now'  -> current datetime
 * - a date -> that date, with the current time attached (unless $include_time is false)
 */
function central_time($date_string = 'now', $include_time = true) {
    $tz = new DateTimeZone(SITE_TIMEZONE);

    if ($date_string === 'now') {
        return new DateTime('now', $tz);
    }

    $now = new DateTime('now', $tz);
    $target = new DateTime($date_string, $tz);

    if ($include_time) {
        $target->setTime((int)$now->format('H'), (int)$now->format('i'), (int)$now->format('s'));
    }

    return $target;
}

/**
 * Generate a unique random 12-digit barcode for a given table/column.
 */
function generate_unique_barcode($table) {
    global $pdo;
    do {
        $barcode = '';
        for ($i = 0; $i < 12; $i++) {
            $barcode .= mt_rand(0, 9);
        }
        $exists = db_get_var("SELECT COUNT(*) FROM `$table` WHERE barcode = ?", array($barcode));
    } while ($exists > 0);

    return $barcode;
}
