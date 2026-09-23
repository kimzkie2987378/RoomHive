<?php
/* =========================================================
   togglewishlist.php
   Add/remove a listing from the logged-in user's wishlist.
   Used by listing.php (heart buttons) and userwishlist.php
   (remove buttons) — the SINGLE shared endpoint, so
   favorites and My Wishlist are always the same thing.

   Returns JSON:
   { success: true, saved: true|false }        — ok
   { success: false, login: true, message }    — session expired
   { success: false, message }                 — error
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
    respond(['success' => false, 'message' => 'Invalid request.']);
}

if (!isset($_SESSION['user_id'])) {
    respond([
        'success' => false,
        'login'   => true,
        'message' => 'Please log in to save listings.',
    ]);
}

/* =========================================================
   SELF-HEAL — create the wishlist table if it's missing
========================================================= */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            listing_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_listing (user_id, listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    respond([
        'success' => false,
        'message' => 'Database error while preparing wishlist.',
    ]);
}

 $listingId = (int) ($_POST['listing_id'] ?? 0);

if ($listingId <= 0) {
    respond(['success' => false, 'message' => 'Invalid listing.']);
}

/* Listing must exist */
 $chk = $pdo->prepare("SELECT id FROM listings WHERE id = :id LIMIT 1");
 $chk->execute(['id' => $listingId]);

if (!$chk->fetch()) {
    respond(['success' => false, 'message' => 'Listing not found.']);
}

 $uid = (int) $_SESSION['user_id'];

/* Try remove first — if a row was deleted, it was saved */
 $del = $pdo->prepare(
    "DELETE FROM wishlist WHERE user_id = :u AND listing_id = :l"
);
 $del->execute(['u' => $uid, 'l' => $listingId]);

if ($del->rowCount() > 0) {
    respond([
        'success' => true,
        'saved'   => false,
        'message' => 'Removed from your wishlist.',
    ]);
}

/* Otherwise insert */
 $ins = $pdo->prepare(
    "INSERT INTO wishlist (user_id, listing_id, created_at)
     VALUES (:u, :l, NOW())"
);

try {
    $ins->execute(['u' => $uid, 'l' => $listingId]);
} catch (PDOException $e) {
    /* Race condition: row inserted between DELETE and INSERT */
    respond(['success' => true, 'saved' => true]);
}

respond([
    'success' => true,
    'saved'   => true,
    'message' => 'Saved to your wishlist.',
]);