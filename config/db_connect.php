<?php
/*
 * =========================================================
 * db_connect.php
 * =========================================================
 * Shared PDO database connection for RoomHive.
 * Include this at the top of any page that needs the DB:
 *
 *     require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
 *
 * Defaults are set for a typical local XAMPP/MAMP setup.
 *
 * NEW (Hive Club Phase 3 integration hardening):
 *   1. require-once guard  — double-include (e.g. hiveclub.php
 *      required by both a page AND booking_autocomplete.php)
 *      no longer warns "constant DB_ already defined" or
 *      re-creates the PDO object.
 *   2. Charset enforcement — SET NAMES utf8mb4 on connect, so
 *      the peso sign and emoji in Hive Club notifications
 *      (🎉 🍯 ✅) store correctly regardless of server defaults.
 *
 * NEW (PERSISTENT LOGIN + ANTI-STALE-CACHE):
 *   3. No-cache headers on every PHP page — the browser can no
 *      longer serve a stale cached copy that made a logged-in
 *      user LOOK logged out after reopening the browser.
 *   4. Session bootstrap + 30-day lifetime — server-side session
 *      data survives 30 days instead of the 24-minute default,
 *      and the session cookie is re-issued as a PERSISTENT
 *      30-day cookie so closing the browser no longer ends
 *      the login.
 * =========================================================
 */

/* =========================================================
   3+4. SESSION BOOTSTRAP — PERSISTENT LOGIN
   Runs before anything else. If the page already called
   session_start(), this is a no-op. If it forgot, we start
   the session here so the cookie logic below always works.
========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    /* Keep server-side session data alive for 30 days.
       PHP's default (1440s = 24 minutes) silently wipes
       logins during idle time. */
    ini_set('session.gc_maxlifetime', 2592000);   /* 30 days */
    ini_set('session.cookie_lifetime', 2592000);  /* 30 days */

    session_start();
}

/* ---- No-cache headers (fixes "stale page shows logged-out") ----
   Without these, the browser restores the GUEST version of a
   page from disk cache when the browser reopens — no request
   reaches the server until the user hits refresh. */
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/* ---- Re-issue the session cookie as PERSISTENT (30 days) ----
   PHP's default cookie dies the moment the browser closes.
   For anyone logged in (user or admin), re-send the cookie
   with a 30-day expiry so the login survives restarts. */
if (session_status() === PHP_SESSION_ACTIVE
    && (!empty($_SESSION['logged_in']) || !empty($_SESSION['admin_logged_in']))
    && !headers_sent()) {

    setcookie(session_name(), session_id(), [
        'expires'  => time() + 60 * 60 * 24 * 30, /* 30 days */
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

/* =========================================================
   PDO CONNECTION
========================================================= */

/* ---- Require-once guard ---- */
if (isset($pdo) && $pdo instanceof PDO) {
    return; /* connection already established in this request */
}

 $dbHost    = 'localhost';
 $dbName    = 'roomhive';
 $dbUser    = 'root';
 $dbPass    = '';        // XAMPP/MAMP default is usually blank
 $dbCharset = 'utf8mb4';

 $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    /* Make the intended charset authoritative, not just a DSN
       suggestion — covers MariaDB/MySQL servers whose
       init_connect / collation defaults differ. */
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

} catch (PDOException $e) {
    // Don't leak connection details to the browser.
    error_log('Database connection failed: ' . $e->getMessage());
    die('Sorry, something went wrong. Please try again later.');
}