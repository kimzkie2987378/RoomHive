<?php
/* =========================================================
   ROOMHIVE — AVATAR UPLOAD
   uploadavatar.php
   Handles the AJAX call from userprofile.php's camera icon.
   Validates the file, stores it, updates users.avatar_path,
   deletes the old uploaded avatar (if any), returns JSON.
========================================================= */

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file received.']);
    exit;
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload failed. Please try again.']);
    exit;
}

/* ---- Size limit: 5MB ---- */
$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'error' => 'Image must be under 5MB.']);
    exit;
}

/* ---- Real MIME check (never trust the client-sent type) ---- */
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if (!isset($allowed[$mimeType])) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed.']);
    exit;
}

/* ---- Re-encode through GD to strip anything malicious ---- */
switch ($mimeType) {
    case 'image/jpeg':
        $img = @imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'image/png':
        $img = @imagecreatefrompng($file['tmp_name']);
        break;
    case 'image/webp':
        $img = @imagecreatefromwebp($file['tmp_name']);
        break;
}

if (!$img) {
    echo json_encode(['success' => false, 'error' => 'That file isn\'t a valid image.']);
    exit;
}

/* ---- Resize down to a sane max (square-ish avatar, 500px) ---- */
$targetSize = 500;
$width  = imagesx($img);
$height = imagesy($img);
$size   = min($width, $height);
$srcX   = (int) (($width - $size) / 2);
$srcY   = (int) (($height - $size) / 2);

$canvas = imagecreatetruecolor($targetSize, $targetSize);
imagealphablending($canvas, false);
imagesavealpha($canvas, true);
imagecopyresampled($canvas, $img, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $size, $size);

$userId   = (int) $_SESSION['user_id'];
$ext      = $allowed[$mimeType];
$filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
$destDir  = __DIR__ . '/uploads/avatars/';
$destPath = $destDir . $filename;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$saved = false;
switch ($ext) {
    case 'jpg':
        $saved = imagejpeg($canvas, $destPath, 85);
        break;
    case 'png':
        $saved = imagepng($canvas, $destPath, 6);
        break;
    case 'webp':
        $saved = imagewebp($canvas, $destPath, 85);
        break;
}

imagedestroy($img);
imagedestroy($canvas);

if (!$saved) {
    echo json_encode(['success' => false, 'error' => 'Could not save the image.']);
    exit;
}

$newAvatarPath = 'uploads/avatars/' . $filename;

/* ---- Fetch old avatar so we can delete it after a successful DB update ---- */
$oldStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
$oldStmt->execute(['id' => $userId]);
$oldAvatar = $oldStmt->fetchColumn();

$updateStmt = $pdo->prepare("UPDATE users SET avatar_path = :avatar WHERE id = :id");
$updateStmt->execute(['avatar' => $newAvatarPath, 'id' => $userId]);

/* Clean up the old uploaded file (skip if they never had one) */
if ($oldAvatar && strpos($oldAvatar, 'uploads/avatars/') === 0) {
    $oldFullPath = __DIR__ . '/' . $oldAvatar;
    if (is_file($oldFullPath)) {
        @unlink($oldFullPath);
    }
}

echo json_encode([
    'success'     => true,
    'avatar_url'  => $newAvatarPath . '?v=' . time(), // cache-bust
]);