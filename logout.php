<?php
/*logout.php*/
session_start();

/*
 * Clear all session variables.
 */
$_SESSION = [];

/*
 * Destroy the session cookie itself, if one is set.
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
 * Destroy the session data on the server.
 */
session_destroy();

/*
 * Send the user back to the homepage.
 */
header("Location: index.php");
exit();