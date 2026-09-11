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
