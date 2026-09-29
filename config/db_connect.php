<?php
/*
 * =========================================================
 * db_connect.php  (v2.1 — session persistence hardened,
 *                   order-safe session config)
 * =========================================================
 * Shared PDO database connection for RoomHive.
 * Include this at the top of any page that needs the DB:
 *
 *     require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
 *
 * Defaults are set for a typical local XAMPP/MAMP setup.
 *
 * ⚠ INCLUDE ORDER (IMPORTANT):
 *   This file OWNS session setup. Require it at the very top
 *   of every page, BEFORE any output and BEFORE any
 *   session_start(). NEVER call session_start() yourself in
 *   page files — this file starts the session for you, with
 *   the correct 30-day cookie parameters. Calling
 *   session_start() first triggers:
 *     Warning: ini_set(): Session ini settings cannot be
 *     changed when a session is active
 *   and the 30-day session lifetime silently fails to apply.
 *
 * PERSISTENT LOGIN + ANTI-STALE-CACHE:
 *   1. Session config runs ONLY while the session is not yet
 *      started (guarded by session_status()) — the ini_set()
 *      calls are inside the guard, so a page that (incorrectly)
 *      starts the session first can never trigger a warning.
 *   2. Cookie params set idiomatically via
 *      session_set_cookie_params() before session_start() —
 *      every visitor gets a proper 30-day cookie.
 *   3. No-cache headers on every PHP page — the browser can
 *      no longer serve a stale guest copy that made a
 *      logged-in user LOOK logged out after reopening it.
 *   4. Sliding 30-day re-issue of the cookie while logged in,
 *      so the login keeps renewing instead of dying 30 days
 *      after the ORIGINAL login.
 *
 * ⚠ PAIR WITH:
 *   - logout.php MUST expire the cookie (see the snippet that
 *     ships with this change) or "Log Out" won't stick.
 *   - loginform.php should call session_regenerate_id(true)
 *     after successful login (fixation protection — matters
 *     now that IDs live 30 days).
 * =========================================================
 */

/* =========================================================
   1+2. SESSION CONFIG — guarded so it only runs when the
   session has NOT been started yet. ini_set() on session.*
   settings throws a warning (and does nothing) once a
   session is active, so it lives inside the same guard as
   session_start().
========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.gc_maxlifetime', 2592000);   /* 30 days */
    ini_set('session.cookie_lifetime', 2592000);  /* 30 days */

    /* Idiomatic cookie control — applies the moment
       session_start() sends the cookie. Guests and logged-in
       users alike get a 30-day persistent cookie. */
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, /* 30 days */
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);

    session_start();

}
/* If the session was ALREADY active when this file was
   included, a page called session_start() before including
   db_connect.php. That page will run on PHP's default
   24-minute session lifetime — fix the page's include
   order rather than adding logic here. */

/* ---- No-cache headers (fixes "stale page shows logged-out") ----
   Without these, the browser restores the GUEST version of a
   page from disk cache when the browser reopens — no request
   reaches the server until the user hits refresh. */
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/* ---- Slide the cookie forward while logged in ----
   The session_set_cookie_params above gives a 30-day cookie at
   first visit. This re-issue keeps RENEWING it to 30 days from
   NOW on every request while the user (or admin) is logged in,
   so an active user is never logged out by expiry. */
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