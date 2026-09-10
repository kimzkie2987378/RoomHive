<?php
/* =========================================================
   ROOMHIVE — UPLOAD AVATAR
   uploadavatar.php

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
========================================================= */

ob_start();

/* Don't let PHP print warnings/notices as HTML into the
   buffer either — log them instead, keep the response clean. */
ini_set('display_errors', '0');
error_reporting(E_ALL);

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
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

/* Last-resort net: if something fatal happens ANYWHERE below
   (a missing file, a missing class, a typo, etc.) PHP would
   otherwise print an HTML error — or nothing at all — instead
   of JSON. Registered first, before db_connect.php even loads,
   so a fatal error during that require is caught too. */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        respond(false, ['error' => 'Server error while uploading: ' . $error['message']]);
    }
});

session_start();

$dbConnectPath = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
if (!file_exists($dbConnectPath)) {
    respond(false, ['error' => 'Server misconfiguration: db_connect.php not found at ' . $dbConnectPath]);
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
    respond(false, ['error' => 'Upload failed (error code ' . $file['error'] . '). Please try again.']);
}

$maxBytes = 5 * 1024 * 1024; // 5MB, matches the client-side check
if ($file['size'] > $maxBytes) {
    respond(false, ['error' => 'That image is too large. Please choose one under 5MB.']);
}

/* -----------------------------------------------------
   VALIDATE THE ACTUAL FILE CONTENT
   Never trust the client-supplied MIME type or the file
   extension alone — inspect the real bytes.
----------------------------------------------------- */
$allowedMimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$realMime = $finfo->file($file['tmp_name']);

if (!isset($allowedMimeToExt[$realMime])) {
    respond(false, ['error' => 'Please upload a JPG, PNG, or WEBP image.']);
}

$extension = $allowedMimeToExt[$realMime];

/* -----------------------------------------------------
   SAVE THE FILE
----------------------------------------------------- */
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/avatars';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        respond(false, ['error' => 'Server could not create the upload folder. Contact support.']);
    }
}

$filename   = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$targetPath = $uploadDir . '/' . $filename;
$publicPath = '/webprogg/uploads/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    respond(false, ['error' => 'Could not save the uploaded photo. Please try again.']);
}

/* -----------------------------------------------------
   UPDATE THE DATABASE
----------------------------------------------------- */
try {
    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $previous = $stmt->fetchColumn();

    $updateStmt = $pdo->prepare("UPDATE users SET avatar_path = :avatar_path WHERE id = :id");
    $updateStmt->execute([
        'avatar_path' => $publicPath,
        'id'          => $_SESSION['user_id'],
    ]);
} catch (Throwable $e) {
    /* DB update failed — remove the file we just saved so we
       don't leave an orphaned upload with nothing pointing to it. */
    @unlink($targetPath);
    respond(false, ['error' => 'Could not update your profile. Please try again.']);
}

/* -----------------------------------------------------
   CLEAN UP THE OLD AVATAR FILE
   Only delete files that live in our own uploads folder —
   never touch /webprogg/images/default-avatar.png or
   anything else outside that directory.
----------------------------------------------------- */
if (!empty($previous) && str_starts_with($previous, '/webprogg/uploads/avatars/')) {
    $previousFullPath = $_SERVER['DOCUMENT_ROOT'] . $previous;
    if (is_file($previousFullPath)) {
        @unlink($previousFullPath);
    }
}

/* Keep the session's cached avatar path in sync too, same as
   the pattern already used at the top of userprofile.php /
   hostprofile.php. */
$_SESSION['avatar_path'] = $publicPath;

respond(true, ['avatar_url' => $publicPath]);

} catch (Throwable $e) {
    // Any unexpected exception (e.g. the `fileinfo` PHP
    // extension not being enabled, so `finfo` doesn't exist)
    // lands here instead of printing an HTML fatal error.
    respond(false, ['error' => 'Server error while uploading. Please try again.']);
}