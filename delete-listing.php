<?php
/* =========================================================
   ROOMHIVE — DELETE LISTING
   delete-listing.php

   Called via fetch() from hostprofile.php / mylistings.php
   when a host clicks the 3-dot menu -> Delete on one of their
   own listings. Always responds with JSON.

   Security: a listing is only ever deleted if
   listings.user_id matches the LOGGED-IN user's session id —
   nobody can delete a listing that isn't theirs just by
   guessing/sending a different listing_id.
========================================================= */

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

function respond($ok, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

/* ---------------------------------------------------------
   AUTH GUARD
--------------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    respond(false, 'You must be logged in to do that.');
}

/* ---------------------------------------------------------
   METHOD + INPUT
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Invalid request method.');
}

$listingId = (int) ($_POST['listing_id'] ?? 0);

if ($listingId <= 0) {
    http_response_code(400);
    respond(false, 'Missing or invalid listing id.');
}

/* ---------------------------------------------------------
   CONFIRM OWNERSHIP
   Only the host who owns this listing can delete it.
--------------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1"
);
$stmt->execute([
    'id'      => $listingId,
    'user_id' => $_SESSION['user_id'],
]);

if (!$stmt->fetch()) {
    http_response_code(404);
    respond(false, 'Listing not found, or it does not belong to your account.');
}

/* ---------------------------------------------------------
   DELETE
   Remove dependent rows first (listing_photos) so we don't
   hit a foreign key constraint, then remove the listing
   itself. Wrapped in a transaction so a failure partway
   through can't leave orphaned photo rows behind.
--------------------------------------------------------- */
try {
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM listing_photos WHERE listing_id = :id")
        ->execute(['id' => $listingId]);

    $pdo->prepare("DELETE FROM listings WHERE id = :id AND user_id = :user_id")
        ->execute([
            'id'      => $listingId,
            'user_id' => $_SESSION['user_id'],
        ]);

    $pdo->commit();

    respond(true, 'Listing deleted.', ['listing_id' => $listingId]);

} catch (Exception $e) {
    $pdo->rollBack();

    /*
     * Most likely cause: a foreign key constraint from another
     * table (e.g. bookings) still references this listing, so
     * the database refuses the delete. Surface a clear message
     * instead of a raw SQL error.
     */
    http_response_code(409);
    respond(false, 'This listing could not be deleted — it may still have bookings or other records attached to it.');
}
