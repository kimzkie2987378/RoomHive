<?php
/* =========================================================
   ROOMHIVE — UPLOAD AVATAR (hardened)
   /webprogg/user/uploadavatar.php

   Called via fetch() from userprofile.php / hostprofile.php
   (the camera-icon button on the profile photo). Always
   responds with JSON — even on failure — because the
   frontend does `res.json()` unconditionally; ANY stray
   output before our json_encode() (a PHP notice, a debug
   echo left in db_connect.php, etc.) corrupts the response
   body and makes res.json() throw, which is exactly what
   produces that generic "Something went wrong" alert.

   To guarantee this file only ever outputs valid JSON, we
   buffer everything from the very first line and discard
   whatever landed in that buffer right before we send our
   real response.

   CHANGES IN THIS REVISION
   ------------------------
   1. Every failure path is error_log()'d (was: silent).
   2. Internal paths / raw fatal messages no longer leak to
      the client — logged instead, generic text returned.
   3. json_encode() failure falls back to a literal valid
      JSON body, so the "always JSON" guarantee is airtight.
   4. Sec-Fetch-Site cross-origin check (explicit CSRF layer;
      SameSite=Lax cookies remain the fallback for browsers
      that don't send the header).
   5. "User row missing" (stale session + deleted account) is
      detected — previously a silent UPDATE no-op that saved
      an orphaned file and reported success.
   6. Old-avatar deletion is realpath()-contained, so a
      tampered avatar_path can never escape the uploads dir.
   7. Size limit = min(5MB, upload_max_filesize); UPLOAD_ERR
      codes are mapped to human-readable messages.
   8. getimagesize() verifies the image actually decodes as
      the sniffed type, not just that the magic bytes match.
========================================================= */

ob_start();

/* Don't let PHP print warnings/notices as HTML into the
   buffer either — log them instead, keep the response clean. */
ini_set('display_errors', '0');
error_reporting(E_ALL);

/* str_starts_with() is native in PHP 8; polyfill for 7.x.
   (File overall requires PHP >= 7.1: void return types,
   IMAGETYPE_WEBP. Runs unchanged on PHP 8.x.) */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos((string)$haystack, (string)$needle) === 0;
    }
}

/* Single logging funnel — every failure below goes through here.
   Grep your logs for "[uploadavatar]" to see everything from
   this endpoint. */
function uploadavatar_log(string $message): void {
    error_log('[uploadavatar] ' . $message);
}

/* php.ini shorthand ("2M", "512K", "1G") → bytes. 0 if unset/unparseable. */
function ini_bytes_setting(string $setting): int {
    $val = trim((string)ini_get($setting));
    if ($val === '') {
        return 0;
    }
    $num = (float)$val;
    switch (strtolower(substr($val, -1))) {
        case 'g': $num *= 1024; // fallthrough — descending units
        case 'm': $num *= 1024; // fallthrough
        case 'k': $num *= 1024;
    }
    return (int)$num;
}

function human_bytes(int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024)) . 'MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . 'KB';
    }
    return $bytes . ' bytes';
}

/* Our app limit (matches the client-side 5MB check), capped by the
   server's upload_max_filesize. Whichever is smaller is what can
   actually succeed — that's the number we quote to users, so a 2M
   php.ini default never surfaces as a cryptic "error code 1". */
function effective_max_bytes(): int {
    $desired = 5 * 1024 * 1024;
    $server  = ini_bytes_setting('upload_max_filesize');
    return ($server > 0) ? min($desired, $server) : $desired;
}

/* Every response from this file is JSON, no matter what.
   Defined BEFORE anything else runs (including the
   db_connect.php require below) so that even a failure
   during config loading gets caught by the shutdown
   handler right after it. */
function respond($success, $data = []) {
    // Discard ANY stray output that snuck into the buffer
    // (debug echoes, notices, BOM/whitespace, etc.) before
    // we send the real, clean JSON body.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Covers the edge case where something already flushed
    // output behind our back — the body is still valid JSON.
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    $json = json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // Only reachable if $data somehow contains invalid UTF-8 —
        // none of our payloads include user input — but an empty
        // body would make res.json() throw, which is the exact
        // failure this whole file exists to prevent. Close the loop.
        $json = '{"success":false,"error":"Response encoding failed."}';
    }
    echo $json;
    exit;
}

/* Last-resort net: if something fatal happens ANYWHERE below
   (a missing file, a missing class, a typo, etc.) PHP would
   otherwise print an HTML error — or nothing at all — instead
   of JSON. Registered first, before db_connect.php even loads,
   so a fatal error during that require is caught too.
   Details go to the log ONLY: raw fatal messages routinely
   contain absolute server paths and must not reach the client. */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        uploadavatar_log('fatal: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        respond(false, ['error' => 'Server error while uploading. Please try again.']);
    }
});

session_start();

/* -----------------------------------------------------
   CSRF GUARD
   Browsers that send Sec-Fetch-Site (Chrome/Edge 76+,
   Firefox 90+) let us reject cross-origin posts outright.
   SameSite=Lax session cookies remain the fallback for
   browsers that don't send the header (notably older
   Safari), which is why an absent header is allowed.
----------------------------------------------------- */
 $secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($secFetchSite !== '' && $secFetchSite !== 'same-origin' && $secFetchSite !== 'none') {
    respond(false, ['error' => 'Cross-site request blocked.']);
}

/* -----------------------------------------------------
   CONFIG
----------------------------------------------------- */
 $dbConnectPath = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
if (!file_exists($dbConnectPath)) {
    // Log the real path for ops; the client gets nothing internal.
    uploadavatar_log('db_connect.php not found, expected at ' . $dbConnectPath);
    respond(false, ['error' => 'Server misconfiguration. Please contact support.']);
}
require_once $dbConnectPath;

/* -----------------------------------------------------
   AUTH GUARD
   No redirect here (this is an AJAX endpoint, not a page) —
   just fail the JSON response.
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    respond(false, ['error' => 'You must be logged in to update your photo.']);
}

/* -----------------------------------------------------
   BASIC UPLOAD CHECKS
----------------------------------------------------- */
try {

if (!isset($_FILES['avatar']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
    respond(false, ['error' => 'No photo was received. Please try again.']);
}

 $file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            respond(false, ['error' => 'That image is too large. Please choose one under ' . human_bytes(effective_max_bytes()) . '.']);
        case UPLOAD_ERR_PARTIAL:
            respond(false, ['error' => 'The upload was interrupted. Please try again.']);
        case UPLOAD_ERR_NO_FILE:
            respond(false, ['error' => 'No photo was received. Please try again.']);
        default:
            respond(false, ['error' => 'Upload failed (error code ' . $file['error'] . '). Please try again.']);
    }
}

 $maxBytes = effective_max_bytes();
if ($file['size'] > $maxBytes) {
    respond(false, ['error' => 'That image is too large. Please choose one under ' . human_bytes($maxBytes) . '.']);
}

/* -----------------------------------------------------
   VALIDATE THE ACTUAL FILE CONTENT
   Never trust the client-supplied MIME type or the file
   extension alone — inspect the real bytes.
----------------------------------------------------- */
 $allowedTypes = [
    'image/jpeg' => ['ext' => 'jpg', 'imagetype' => IMAGETYPE_JPEG],
    'image/png'  => ['ext' => 'png', 'imagetype' => IMAGETYPE_PNG],
    'image/webp' => ['ext' => 'webp', 'imagetype' => IMAGETYPE_WEBP],
];

 $finfo    = new finfo(FILEINFO_MIME_TYPE);
 $realMime = $finfo->file($file['tmp_name']);

if (!isset($allowedTypes[$realMime])) {
    respond(false, ['error' => 'Please upload a JPG, PNG, or WEBP image.']);
}

/* The magic bytes look right — now confirm the file actually
   DECODES as that same type. This catches payloads with a valid
   header wrapped around corrupt or non-image contents. */
 $imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false || (int)$imageInfo[2] !== $allowedTypes[$realMime]['imagetype']) {
    respond(false, ['error' => "That file doesn't look like a valid image. Please try another one."]);
}

 $extension = $allowedTypes[$realMime]['ext'];

/* -----------------------------------------------------
   SAVE THE FILE
----------------------------------------------------- */
 $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/avatars';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        uploadavatar_log('could not create upload dir: ' . $uploadDir . ' (check permissions)');
        respond(false, ['error' => 'Server could not create the upload folder. Contact support.']);
    }
}

 $filename   = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
 $targetPath = $uploadDir . '/' . $filename;
 $publicPath = '/webprogg/uploads/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    uploadavatar_log('move_uploaded_file failed for ' . $targetPath);
    respond(false, ['error' => 'Could not save the uploaded photo. Please try again.']);
}

/* -----------------------------------------------------
   UPDATE THE DATABASE
----------------------------------------------------- */
try {
    // Fetch the ROW (not just the column) so "avatar_path is NULL"
    // is distinguishable from "this user no longer exists". With a
    // stale session and a deleted account, the UPDATE below would
    // silently no-op and we'd strand an orphan file while reporting
    // success.
    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        @unlink($targetPath);
        respond(false, ['error' => 'Account not found. Please log in again.']);
    }
    $previous = $row['avatar_path'] ?? null;

    $updateStmt = $pdo->prepare("UPDATE users SET avatar_path = :avatar_path WHERE id = :id");
    $updateStmt->execute([
        'avatar_path' => $publicPath,
        'id'          => $_SESSION['user_id'],
    ]);
} catch (Throwable $e) {
    uploadavatar_log('DB error: ' . $e);
    /* DB update failed — remove the file we just saved so we
       don't leave an orphaned upload with nothing pointing to it. */
    @unlink($targetPath);
    respond(false, ['error' => 'Could not update your profile. Please try again.']);
}

/* -----------------------------------------------------
   CLEAN UP THE OLD AVATAR FILE
   Two gates: the stored path must sit under our uploads
   prefix (cheap first check), AND its realpath() must resolve
   inside the real uploads directory. realpath() collapses any
   '../' sequences, so even a tampered avatar_path can never
   trick us into unlinking something outside the folder.
----------------------------------------------------- */
if (!empty($previous) && str_starts_with($previous, '/webprogg/uploads/avatars/')) {
    $realUploadDir    = realpath($uploadDir);
    $previousFullPath = realpath($_SERVER['DOCUMENT_ROOT'] . $previous);
    if ($previousFullPath !== false
        && $realUploadDir !== false
        && str_starts_with($previousFullPath, $realUploadDir . DIRECTORY_SEPARATOR)) {
        @unlink($previousFullPath);
    }
}

/* Keep the session's cached avatar path in sync too, same as
   the pattern already used at the top of userprofile.php /
   hostprofile.php. */
 $_SESSION['avatar_path'] = $publicPath;

respond(true, ['avatar_url' => $publicPath]);

} catch (Throwable $e) {
    // Any unexpected exception (e.g. the `fileinfo` PHP extension
    // not being enabled, so `finfo` doesn't exist) lands here
    // instead of printing an HTML fatal error — and this time it
    // actually lands in the log, so it's diagnosable.
    uploadavatar_log('uncaught: ' . $e);
    respond(false, ['error' => 'Server error while uploading. Please try again.']);
}