<?php
/* =========================================================
   ROOMHIVE — GOOGLE OAUTH CONFIG
   config/google_oauth.php

   Fill in the two values from Google Cloud Console:
     Credentials -> OAuth client ID -> Web application.
   The redirect URI registered in Google Console must be
   EXACTLY:  http://localhost/webprogg/auth/google-callback.php
   (add your production URL there too when you deploy).
========================================================= */

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID',     'PASTE_YOUR_CLIENT_ID.apps.googleusercontent.com');
    define('GOOGLE_CLIENT_SECRET', 'PASTE_YOUR_CLIENT_SECRET');
    define('GOOGLE_REDIRECT_URI',  'http://localhost/webprogg/auth/google-callback.php');

    /* Where to send people after a successful Google login
       when the flow didn't start from a whitelisted page. */
    define('GOOGLE_DEFAULT_REDIRECT', '/webprogg/user/usershome.php');

    /* Same whitelist as loginform.php — pages may pass
       ?redirect= into the Google flow. */
    $GLOBALS['GOOGLE_ALLOWED_REDIRECTS'] = [
        '/webprogg/hiveclub.php',
        '/webprogg/user/membership.php',
    ];
}

/* ---------------------------------------------------------
   SELF-HEAL — users.google_id
   Nullable + unique: NULL rows (email signups) don't
   collide on a unique index in MySQL.
--------------------------------------------------------- */
if (!function_exists('google_ensure_schema')) {
    function google_ensure_schema(PDO $pdo) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'")->fetch();
            if (!$col) {
                $pdo->exec(
                    "ALTER TABLE users
                     ADD COLUMN google_id VARCHAR(64) NULL DEFAULT NULL,
                     ADD UNIQUE INDEX uq_users_google_id (google_id)"
                );
            }
        } catch (PDOException $e) {
            error_log('google_ensure_schema failed: ' . $e->getMessage());
        }
    }
}

/* ---------------------------------------------------------
   Minimal cURL wrappers (no Composer / SDK needed)
--------------------------------------------------------- */
if (!function_exists('google_http_post')) {
    function google_http_post($url, array $params) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Google token request failed: ' . $err);
        }
        return json_decode($body, true);
    }
}

if (!function_exists('google_http_get')) {
    function google_http_get($url, $accessToken) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Google userinfo request failed: ' . $err);
        }
        return json_decode($body, true);
    }
}