<?php

/* =========================================================
   ROOMHIVE - CHECKOUT / SEND INQUIRY
   book.php

   Called from listing-detail.php's "Send Inquiry" button
   (and now also from the "List Now" button a host sees on
   their own listing). Creates a `bookings` row for the
   current user against the requested listing. Since
   listing.php's query excludes any listing with a
   pending/confirmed booking, this is what makes the listing
   disappear from the public Listings page the moment
   checkout happens.
   ========================================================= */

session_start();
require_once 'db_connect.php';

/* Only logged-in users can book. */
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit;
}

/* Only accept POST — this action changes data, so it should
   never run from a plain link/GET request. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: listing.php");
    exit;
}

$listingId = isset($_POST['listing_id']) && is_numeric($_POST['listing_id'])
    ? (int) $_POST['listing_id']
    : 0;

if ($listingId <= 0) {
    header("Location: listing.php");
    exit;
}

/* Re-check availability server-side — never trust that the
   listing is still bookable just because the page loaded
   with a "Send Inquiry" button on it. Someone else could have
   booked it in the meantime. */
$stmt = $pdo->prepare(
    "SELECT id, price
     FROM listings
     WHERE id = :id
       AND status = 'approved'
       AND NOT EXISTS (
           SELECT 1 FROM bookings b
           WHERE b.listing_id = listings.id
             AND b.status IN ('pending', 'confirmed')
       )
     LIMIT 1"
);
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch();

/* Listing doesn't exist, isn't approved, or was just booked
   by someone else — bounce back to the listing page with a
   flag the detail page can use to show a message. */
if (!$listing) {
    header("Location: listing-detail.php?id=" . $listingId . "&unavailable=1");
    exit;
}

/* Create the booking.
   NOTE: the previous "prevent a host from booking their own
   listing" check has been intentionally removed so the host
   can use the "List Now" button on their own listing detail
   page to mark it as taken/booked. */
$insertStmt = $pdo->prepare(
    "INSERT INTO bookings (listing_id, user_id, total, status)
     VALUES (:listing_id, :user_id, :total, 'confirmed')"
);
$insertStmt->execute([
    'listing_id' => $listingId,
    'user_id'    => $_SESSION['user_id'],
    'total'      => $listing['price'],
]);

header("Location: userprofile.php?booked=1");
exit;