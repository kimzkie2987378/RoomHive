<?php
/*
 * =========================================================
 * db_connect.php
 * =========================================================
 * Shared PDO database connection for RoomHive.
 * Include this at the top of any page that needs the DB:
 *
 *     require_once 'db_connect.php';
 *
 * Update the constants below to match your environment.
 * Defaults are set for a typical local XAMPP/MAMP setup.
 * =========================================================
 */

$dbHost = 'localhost';
$dbName = 'roomhive';
$dbUser = 'root';
$dbPass = '';        // XAMPP/MAMP default is usually blank
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // Don't leak connection details to the browser.
    error_log('Database connection failed: ' . $e->getMessage());
    die('Sorry, something went wrong. Please try again later.');
}