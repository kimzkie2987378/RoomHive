<?php
/* =========================================================
   ROOMHIVE — HOST DASHBOARD SHARED BOOTSTRAP
   Required at the top of every host sub-page:
     require_once __DIR__ . '/host_init.php';
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* ---- Auth guard ---- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $userStmt = $pdo->prepare(
    "SELECT id, name, email, phone, age, location, avatar_path, is_host,
            two_factor_enabled, created_at
     FROM users WHERE id = :id LIMIT 1"
);
 $userStmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $userStmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $host = [
    'name'         => $dbUser['name'],
    'avatar'       => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

 $notification_count = 0;

/* ---- Pending tenants badge ---- */
 $pendingStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id AND b.status = 'pending'"
);
 $pendingStmt->execute(['id' => $_SESSION['user_id']]);
 $pending_tenants_count = (int) $pendingStmt->fetchColumn();

/* ---- Helpers ---- */

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolve_photo($path, $fallback) {
    if (empty($path)) return $fallback;
    if (preg_match('#^https?://#i', $path)) return $path;
    $normalized = ltrim($path, '/');
    if (stripos($normalized, 'webprogg/') === 0) {
        $normalized = substr($normalized, strlen('webprogg/'));
    }
    return '/webprogg/' . $normalized;
}

function hp_flash_set($type, $message) {
    $_SESSION['hp_flash'] = ['type' => $type, 'message' => $message];
}

function hp_flash_take() {
    $flash = $_SESSION['hp_flash'] ?? null;
    unset($_SESSION['hp_flash']);
    return $flash;
}

/* Total host earnings = host_payout_amount (net of fees),
   falling back to amount_paid where the split wasn't recorded.
   Schema: bookings has NO total / paid_at columns. */
function hp_total_earnings(PDO $pdo, $userId) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(COALESCE(b.host_payout_amount, b.amount_paid)), 0)
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE l.user_id = :id
           AND b.status IN ('confirmed','completed')"
    );
    $stmt->execute(['id' => $userId]);
    return (float) $stmt->fetchColumn();
}