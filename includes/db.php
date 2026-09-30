<?php
/**
 * db.php
 * Creates a single shared PDO connection using prepared-statement-safe defaults.
 * Include this AFTER config.php: require_once 'config.php'; require_once 'db.php';
 */

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on SQL errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // return associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // use REAL prepared statements (was "true", which
                                                                 // is emulated mode - the opposite of what the old
                                                                 // comment claimed; false = driver-native prepares)
    ]);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    die('A database error occurred. Please try again later.');
}
