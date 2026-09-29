<?php
/* =========================================================
   ROOMHIVE — LOGOUT
   =========================================================
   Must fully clear BOTH halves of the login:
     1. the server-side session data (uniset + destroy)
     2. the browser cookie — including the 30-day PERSISTENT
        one db_connect.php issues. Without step 2 the old
        session ID survives in the cookie and the next
        session_start() re-adopts it = logout doesn't stick.
   ========================================================= */

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* 1. Wipe session data */
 $_SESSION = [];

/* 2. Expire the session cookie (works for the 30-day
      persistent cookie too — we overwrite it with a past
      expiry using the same name/path). */
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => (bool) $p['secure'],
        'httponly' => (bool) $p['httponly'],
        'samesite' => 'Lax',
    ]);
}

/* 3. Destroy the server-side session */
session_destroy();

/* 4. Back to the public homepage */
header("Location: /webprogg/index.php");
exit;