<?php
/**
 * Database Configuration
 * SQLite Database Connection
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('DB_PATH', DATA_PATH . '/sfguilds.sqlite');
define('UPLOADS_PATH', DATA_PATH . '/uploads');

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec('PRAGMA journal_mode=WAL;');
            $db->exec('PRAGMA synchronous=NORMAL;');
            $db->exec('PRAGMA cache_size=-8000;'); // 8MB Cache
            $db->exec('PRAGMA foreign_keys=ON;');
            $db->exec('PRAGMA busy_timeout=5000;'); // 5s warten bei Locks statt sofort Fehler
        } catch (PDOException $e) {
            // Logger might not be loaded yet, use both
            if (function_exists('logError')) {
                logError('Database connection failed', ['error' => $e->getMessage()]);
            }
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed');
        }
    }
    
    return $db;
}

/**
 * Execute a query and return results
 * @param string $sql
 * @param array $params
 * @return array
 */
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a query and return single row
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function queryOne($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Execute an update/delete query
 * @param string $sql
 * @param array $params
 * @return int Affected rows
 */
function execute($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Execute an INSERT query and return the new row ID
 * @param string $sql
 * @param array $params
 * @return int Last insert ID
 */
function insert($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$db->lastInsertId();
}