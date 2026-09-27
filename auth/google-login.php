<?php
/* =========================================================
   ROOMHIVE — GOOGLE LOGIN (step 1: go to Google)
   auth/google-login.php

   The "Continue with Google" button lands here. We stash a
   CSRF state + optional whitelisted redirect in the session,
   then bounce the user to Google's consent screen.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/google_oauth.php';

/* Not configured yet? Send them back to the login page with
   a clear error instead of a confusing Google error screen. */
if (GOOGLE_CLIENT_ID === '' || strpos(GOOGLE_CLIENT_ID, 'PASTE_') === 0) {
    header('Location: /webprogg/auth/loginform.php?google_error=config');
    exit;
}

/* Remember where to go after login (whitelisted only). */
 $redirectParam = $_GET['redirect'] ?? '';
if (
    is_string($redirectParam) && $redirectParam !== '' &&
    in_array($redirectParam, $GLOBALS['GOOGLE_ALLOWED_REDIRECTS'], true)
) {
    $_SESSION['google_oauth_redirect'] = $redirectParam;
} else {
    unset($_SESSION['google_oauth_redirect']);
}

/* CSRF state — Google echoes it back; the callback verifies it. */
 $state = bin2hex(random_bytes(16));
 $_SESSION['google_oauth_state'] = $state;

 $params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account', /* always show account picker */
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;