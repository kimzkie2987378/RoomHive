<?php
/* =========================================================
   ROOMHIVE — GOOGLE LOGIN (step 2: callback)
   auth/google-callback.php

   1. Verify the CSRF state (blocks forged callbacks).
   2. Exchange ?code for an access token (cURL).
   3. Fetch the Google profile (id, email, name, picture).
   4. Match-or-create the RoomHive account:
        a) google_id match            -> log in
        b) same email (Google-verified) -> link + log in
        c) no account                 -> create + log in
   5. Session identical to loginform.php's, then redirect
      (whitelisted ?redirect from session, else usershome).
   Any failure -> back to loginform.php?google_error=...
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/google_oauth.php';

function google_fail($code) {
    header('Location: /webprogg/auth/loginform.php?google_error=' . urlencode($code));
    exit;
}

/* ---- User cancelled / Google returned an error ---- */
if (isset($_GET['error'])) {
    google_fail('cancelled');
}

/* ---- CSRF: state must match what we set in google-login.php ---- */
 $state = $_GET['state'] ?? '';
if (
    empty($_SESSION['google_oauth_state']) ||
    !is_string($state) ||
    !hash_equals($_SESSION['google_oauth_state'], $state)
) {
    google_fail('state');
}
unset($_SESSION['google_oauth_state']);

 $code = $_GET['code'] ?? '';
if (!is_string($code) || $code === '') {
    google_fail('code');
}

try {

    /* ---- 1. Exchange the code for tokens ---- */
    $token = google_http_post('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($token['access_token'])) {
        error_log('google-callback: token exchange failed: ' . print_r($token, true));
        google_fail('token');
    }

    /* ---- 2. Fetch the Google profile ---- */
    $me = google_http_get('https://www.googleapis.com/oauth2/v2/userinfo', $token['access_token']);

    $googleId = (string) ($me['id'] ?? '');
    $email    = strtolower(trim((string) ($me['email'] ?? '')));
    $name     = trim((string) ($me['name'] ?? '')) ?: 'RoomHive User';
    $picture  = (string) ($me['picture'] ?? '');
    $verified = !empty($me['verified_email']);

    if ($googleId === '' || $email === '' || !$verified) {
        google_fail('profile');
    }

    google_ensure_schema($pdo);

    /* ---- 3a. Existing user by google_id -> log in ---- */
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $googleId]);
    $user = $stmt->fetch();

    /* ---- 3b. Same email -> link the Google account, log in ---- */
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            try {
                $pdo->prepare(
                    "UPDATE users SET google_id = :gid WHERE id = :id"
                )->execute([':gid' => $googleId, ':id' => $user['id']]);

                /* first Google login: adopt the Google photo if the
                   account has none yet */
                if (empty($user['avatar_path']) && $picture !== '') {
                    $pdo->prepare(
                        "UPDATE users SET avatar_path = :pic WHERE id = :id"
                    )->execute([':pic' => $picture, ':id' => $user['id']]);
                    $user['avatar_path'] = $picture;
                }
            } catch (PDOException $e) {
                /* 23000 = google_id taken by another row (extremely
                   unlikely); treat as a normal failure */
                if ($e->getCode() === '23000') { google_fail('taken'); }
                throw $e;
            }
        }
    }

    /* ---- 3c. No account at all -> create one ---- */
    if (!$user) {
        /* Random unusable password — this account signs in via
           Google; "Forgot Password" can set a real one later. */
        $unusable = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

        try {
            $ins = $pdo->prepare(
                "INSERT INTO users (name, email, password, google_id, avatar_path)
                 VALUES (:name, :email, :pass, :gid, :pic)"
            );
            $ins->execute([
                ':name' => $name,
                ':email' => $email,
                ':pass' => $unusable,
                ':gid' => $googleId,
                ':pic' => $picture !== '' ? $picture : null,
            ]);
        } catch (PDOException $e) {
            /* race: email unique collision between our SELECT and
               INSERT — re-link instead of failing hard */
            if ($e->getCode() === '23000') {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    $pdo->prepare("UPDATE users SET google_id = :gid WHERE id = :id")
                        ->execute([':gid' => $googleId, ':id' => $user['id']]);
                } else {
                    google_fail('taken');
                }
            } else {
                throw $e;
            }
        }

        if (!$user) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :gid LIMIT 1");
            $stmt->execute([':gid' => $googleId]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            google_fail('create');
        }
    }

    /* ---- 4. Log the user in (session shape identical to loginform.php) ---- */
    session_regenerate_id(true);

    $_SESSION["user_id"]    = $user['id'];
    $_SESSION["user_name"]  = $user['name'];
    $_SESSION["user_email"] = $user['email'];
    $_SESSION["logged_in"]  = true;
    $_SESSION['avatar_path'] = $user['avatar_path'] ?? null;

    /* ---- 5. Redirect: whitelisted target from the flow, else default ---- */
    $redirectTo = $_SESSION['google_oauth_redirect'] ?? GOOGLE_DEFAULT_REDIRECT;
    unset($_SESSION['google_oauth_redirect']);

    header('Location: ' . $redirectTo);
    exit;

} catch (Exception $e) {
    error_log('google-callback failed: ' . $e->getMessage());
    google_fail('exception');
}