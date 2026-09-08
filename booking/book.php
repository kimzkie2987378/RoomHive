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
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* Only logged-in users can book. */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* Only accept POST — this action changes data, so it should
   never run from a plain link/GET request. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /webprogg/Listings/listing.php");
    exit;
}

$listingId = isset($_POST['listing_id']) && is_numeric($_POST['listing_id'])
    ? (int) $_POST['listing_id']
    : 0;

if ($listingId <= 0) {
    header("Location: /webprogg/Listings/listing.php");
    exit;
}

/* -----------------------------------------------------
   CHECK-IN / CHECK-OUT / GUESTS
   Sent from listing-detail.php's booking card
   (#rd-checkin, #rd-checkout, #rd-guests-select). All three
   are optional server-side (the "List Now" form a host uses
   on their own listing doesn't include them at all), but
   when they ARE sent, validate them before trusting them.
----------------------------------------------------- */

function book_valid_date($value) {
    if (!is_string($value) || $value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

$checkinDate  = book_valid_date($_POST['checkin_date'] ?? null);
$checkoutDate = book_valid_date($_POST['checkout_date'] ?? null);

/* Check-out must come after check-in — if either date is
   missing or out of order, drop both rather than save a
   booking with a nonsensical range. */
if ($checkinDate === null || $checkoutDate === null || $checkoutDate <= $checkinDate) {
    $checkinDate  = null;
    $checkoutDate = null;
}

$allowedGuestOptions = ['1', '2', '3', '4+'];
$guestsInput = $_POST['guests'] ?? null;
$guests = in_array($guestsInput, $allowedGuestOptions, true) ? $guestsInput : null;

/* Re-check availability server-side — never trust that the
   listing is still bookable just because the page loaded
   with a "Send Inquiry" button on it. Someone else could have
   booked it in the meantime. */
$stmt = $pdo->prepare(
    "SELECT id, price, user_id AS host_id
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
    header("Location: /webprogg/Listings/listing-detail.php?id=" . $listingId . "&unavailable=1");
    exit;
}

/* A host clicking "List Now" on their OWN listing marks it as
   taken immediately — no approval needed since they're not
   applying to anything. Everyone else is a tenant sending an
   inquiry, which needs the host to accept it first, so it
   starts out 'pending' and only becomes 'confirmed' via
   accept-booking.php. */
$isOwnListing = (int) $listing['host_id'] === (int) $_SESSION['user_id'];
$bookingStatus = $isOwnListing ? 'confirmed' : 'pending';

/* Create the booking.
   NOTE: the previous "prevent a host from booking their own
   listing" check has been intentionally removed so the host
   can use the "List Now" button on their own listing detail
   page to mark it as taken/booked. */
$insertStmt = $pdo->prepare(
    "INSERT INTO bookings (listing_id, user_id, total, status, checkin_date, checkout_date, guests)
     VALUES (:listing_id, :user_id, :total, :status, :checkin_date, :checkout_date, :guests)"
);
$insertStmt->execute([
    'listing_id'    => $listingId,
    'user_id'       => $_SESSION['user_id'],
    'total'         => $listing['price'],
    'status'        => $bookingStatus,
    'checkin_date'  => $checkinDate,
    'checkout_date' => $checkoutDate,
    'guests'        => $guests,
]);

header("Location: /webprogg/user/userprofile.php?booked=1");
exit;