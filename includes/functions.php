<?php
/* =========================================================
   ROOMHIVE — SHARED HELPERS
   functions.php

   Pulled out of editprofile.php / helpcenter.php /
   usernotificationsettings.php / savedsearches.php /
   userreviews.php / userprofile.php / userbookings.php /
   userwishlist.php, which each previously defined their own
   (slightly different) copy of h() and resolve_photo().

   Include this ONCE per request, before any page-specific
   logic runs:

       require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
       require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

   Guarded with function_exists() so an accidental double
   require_once (or a future page that still has its own
   copy hanging around) doesn't fatal-error with "cannot
   redeclare function".
========================================================= */

if (!function_exists('h')) {
    /**
     * Escape a value for safe HTML output.
     */
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('resolve_photo')) {
    /**
     * Normalize a stored photo path into a correct site-root
     * absolute path, regardless of how it was originally saved:
     *   - full remote URL ("https://...")            -> left alone
     *   - already correct ("/webprogg/uploads/...")   -> left alone
     *   - missing the webprogg segment ("/uploads/...") -> fixed
     *   - relative, no leading slash ("uploads/...")  -> fixed
     *
     * This is the fixed version from userprofile.php's own
     * comments (the old version trusted any leading "/" as
     * proof of a correct path, which broke on rows saved as
     * "/uploads/listing_photos/xyz.jpg").
     */
    function resolve_photo($path, $fallback = '/webprogg/images/ListingPlaceholder.png') {
        if (empty($path)) {
            return $fallback;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        if (stripos($normalized, 'webprogg/') === 0) {
            $normalized = substr($normalized, strlen('webprogg/'));
        }
        return '/webprogg/' . $normalized;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Return the current session's CSRF token, creating one
     * if it doesn't exist yet. Call after session_start().
     */
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Echo-ready hidden input for forms.
     * Usage inside a <form>: <?php echo csrf_field(); ?>
     */
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Call at the top of any POST handler, before touching the
     * database. Returns true/false rather than dying outright,
     * so each page can decide how to surface the failure.
     */
    function csrf_verify() {
        $submitted = $_POST['csrf_token'] ?? '';
        $expected  = $_SESSION['csrf_token'] ?? '';
        return $submitted !== '' && $expected !== '' && hash_equals($expected, $submitted);
    }
}

if (!function_exists('csrf_verify_or_json_fail')) {
    /**
     * Convenience guard for fetch()/AJAX endpoints: answers with
     * the same JSON shape the front-end scripts already expect
     * ({success:false, message:...}) and stops the request.
     * Call after session_start() + requires, before reading POST.
     */
    function csrf_verify_or_json_fail() {
        if (csrf_verify()) {
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please reload the page and try again.',
        ]);
        exit;
    }
}

if (!function_exists('sync_user_session')) {
    /**
     * Every /my-account page pulls the same handful of fields
     * from `users` and needs the same session bookkeeping
     * afterward (nav avatar cache + is_host mirrored into the
     * session so any older code that still checks
     * $_SESSION['is_host'] reflects reality). Previously this
     * was copy-pasted per page, and userbookings.php /
     * userwishlist.php still had the STALE pre-fix version
     * ($_SESSION['is_host'] never actually set) — this closes
     * that gap for every page that calls it.
     *
     * Pass the $dbUser row (must include avatar_path, is_host).
     * Returns the nav avatar path to render in the header.
     */
    function sync_user_session(array $dbUser) {
        $_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
        $_SESSION['is_host']     = (bool) $dbUser['is_host'];
        return $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
    }
}

if (!function_exists('require_user')) {
    /**
     * Standard guard for /my-account pages. Replaces the ~20
     * lines every account page used to repeat by hand:
     *
     *   1. Guests            -> redirect to login
     *   2. Fetch the user row by $_SESSION['user_id']
     *   3. Row missing       -> destroy session, redirect to login
     *                           (deleted-account cleanup)
     *   4. is_host === 1     -> redirect to the host dashboard
     *                           (userprofile.php is the
     *                           regular-user account page)
     *   5. sync_user_session() so the navbar avatar + is_host
     *      flag can never go stale
     *
     * Returns the fetched user row. Because steps 3–5 are now
     * inseparable from step 1, the whole class of "forgot to
     * set is_host / avatar" bugs your older pages had can't
     * come back on new pages.
     *
     * Usage (after session_start() + db_connect.php +
     * functions.php):
     *
     *   $dbUser   = require_user();                  // default columns
     *   $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
     *
     * Or, when a page needs extra columns (userprofile.php):
     *
     *   $dbUser = require_user([
     *       'id', 'name', 'email', 'phone', 'age',
     *       'location', 'avatar_path', 'is_host', 'created_at',
     *   ]);
     *
     * $columns is whitelisted below even though it comes from
     * code, not user input — defense in depth.
     */
    function require_user(array $columns = [
        'id', 'name', 'email', 'avatar_path', 'is_host', 'created_at',
    ]) {
        /* 1. Must be logged in. */
        if (empty($_SESSION['user_id'])) {
            header("Location: /webprogg/auth/loginform.php");
            exit;
        }

        /* db_connect.php runs in global scope; pull $pdo in. */
        global $pdo;

        /* Only known column names are allowed. */
        $allowed = [
            'id', 'name', 'email', 'phone', 'age', 'location',
            'avatar_path', 'is_host', 'created_at',
        ];
        $safe = array_intersect($columns, $allowed);

        /* Guarantee the columns sync_user_session() needs are
           always selected, no matter what the caller passed. */
        foreach (['avatar_path', 'is_host'] as $required) {
            if (!in_array($required, $safe, true)) {
                $safe[] = $required;
            }
        }

        /* 2. Fetch the row. */
        $stmt = $pdo->prepare(
            "SELECT " . implode(', ', $safe) . " FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        /* 3. Deleted account — clean the session up. */
        if (!$user) {
            session_destroy();
            header("Location: /webprogg/auth/loginform.php");
            exit;
        }

        /* 4. Hosts belong on the host dashboard. */
        if ((int) $user['is_host'] === 1) {
            header("Location: /webprogg/host/hostprofile.php");
            exit;
        }

        /* 5. Keep the session in sync with the DB. */
        sync_user_session($user);

        return $user;
    }
}