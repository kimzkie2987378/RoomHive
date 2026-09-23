<?php
/* =========================================================
   uploadpersonal.php
   PERSONAL PHOTO endpoint — completely separate from the
   profile picture (avatar_path). Saves to
   users.personal_photo (self-healed column).

   Returns JSON:
   { success: true, personal_url: "..." }
   { success: false, error: "..." }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

function respond($payload)
{
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['success' => false, 'error' => 'Invalid request.']);
}

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'Please log in first.']);
}

 $uid = (int) $_SESSION['user_id'];

/* Self-heal the personal_photo column BEFORE any prepare
   (EMULATE_PREPARES=false validates at prepare time). */
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'personal_photo'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE users ADD COLUMN personal_photo VARCHAR(255) NULL");
    }
} catch (PDOException $e) {
    error_log('uploadpersonal: column ensure failed: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Database error. Please try again.']);
}

 $file = $_FILES['personal_photo'] ?? $_FILES['avatar'] ?? null;

if (!$file || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'error' => 'No photo received. Please try again.']);
}

if ((int) $file['size'] > 5 * 1024 * 1024) {
    respond(['success' => false, 'error' => 'Image is too large. Maximum 5MB.']);
}

 $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
 $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
 $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

 $finfo = new finfo(FILEINFO_MIME_TYPE);
 $mime  = $finfo->file($file['tmp_name']);

if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
    respond(['success' => false, 'error' => 'Please choose a JPG, PNG, or WEBP image.']);
}

 $dir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/personal_photos';

if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

/* Old personal photo — remove so the folder stays clean */
 $oldPath = '';
try {
    $oldStmt = $pdo->prepare("SELECT personal_photo FROM users WHERE id = :id LIMIT 1");
    $oldStmt->execute([':id' => $uid]);
    $oldPath = (string) $oldStmt->fetchColumn();
} catch (PDOException $e) {
    /* non-fatal */
}

 $newName  = 'pp' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
 $destPath = $dir . '/' . $newName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respond(['success' => false, 'error' => 'Could not save the photo. Please try again.']);
}

try {
    $upd = $pdo->prepare("UPDATE users SET personal_photo = :p WHERE id = :id");
    $upd->execute([
        ':p'  => '/webprogg/uploads/personal_photos/' . $newName,
        ':id' => $uid,
    ]);

    if ($oldPath !== '' && strpos($oldPath, '/webprogg/uploads/personal_photos/') === 0) {
        $oldFile = $_SERVER['DOCUMENT_ROOT'] . $oldPath;
        if (is_file($oldFile)) { @unlink($oldFile); }
    }

    respond([
        'success'      => true,
        'personal_url' => '/webprogg/uploads/personal_photos/' . $newName,
    ]);

} catch (PDOException $e) {
    error_log('uploadpersonal: DB update failed: ' . $e->getMessage());
    @unlink($destPath);
    respond(['success' => false, 'error' => 'Could not save the photo. Please try again.']);
}