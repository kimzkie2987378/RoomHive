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

   NOTE ON EXPIRY: a 'pending' booking created here has
   paid_at = NULL. If the user never completes payment within
   the hold window, roomhive_expire_stale_bookings() (called
   below, and also from listing.php) will flip it to
   'cancelled' automatically, freeing the listing back up.
   Once payment succeeds (process-payment.php), paid_at gets
   set and the hold no longer expires on its own — see
   booking_helpers.php for the full rule.
   ========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/booking_helpers.php';

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

/* -----------------------------------------------------
   LONG TERM
   If Long Term is checked, checkout date is allowed
   to be empty.
----------------------------------------------------- */

$longTerm = isset($_POST['long_term']) && $_POST['long_term'] === '1';

if ($longTerm) {

    // Long-term inquiry does not need a checkout date.
    $checkoutDate = null;

    // Check-in is still required.
    if ($checkinDate === null) {
        $checkinDate = null;
    }

} else {

    /* Normal booking:
       Check-out must come after check-in.
    */
    if (
        $checkinDate === null ||
        $checkoutDate === null ||
        $checkoutDate <= $checkinDate
    ) {
        $checkinDate  = null;
        $checkoutDate = null;
    }

}

$allowedGuestOptions = ['1', '2', '3', '4+'];
$guestsInput = $_POST['guests'] ?? null;
$guests = in_array($guestsInput, $allowedGuestOptions, true) ? $guestsInput : null;

/* -----------------------------------------------------
   AVAILABILITY CHECK + BOOKING CREATION — done inside a single
   transaction, with the listing row locked via FOR UPDATE.

   Why: a plain SELECT-then-INSERT (the old code) has a race
   window. If User A and User B both click "Send Inquiry" at
   nearly the same moment, both requests can run the SELECT
   before either has inserted a booking — both see the listing
   as available, both insert a 'pending' row, and the listing
   ends up double-booked.

   SELECT ... FOR UPDATE inside a transaction closes that
   window: it takes a row lock on the matching `listings` row,
   so if two requests hit this at once, the second one BLOCKS
   at the SELECT until the first request's transaction commits
   (or rolls back). By the time the second request's SELECT
   actually runs, the first request's booking already exists,
   so the date-conflict check below correctly finds it and the
   second request is rejected. Only one request can ever win.

   This requires InnoDB (RoomHive's tables already use it) —
   FOR UPDATE locking has no effect on MyISAM.
----------------------------------------------------- */
try {
    $pdo->beginTransaction();

    /* Release any stale, unpaid holds (10-minute window) before
       checking whether this listing is currently taken — an
       inquiry someone abandoned without paying shouldn't block
       a fresh one indefinitely. Paid holds and confirmed
       bookings are left alone. Safe to run inside this
       transaction: it only touches bookings rows that already
       qualify as expired, it never contends with the FOR UPDATE
       lock taken below. */
    roomhive_expire_stale_bookings($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, price, user_id AS host_id
         FROM listings
         WHERE id = :id
           AND status = 'approved'
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(['id' => $listingId]);
    $listing = $stmt->fetch();

    /* Listing doesn't exist, isn't approved, or was just booked
       by someone else — release the lock and bounce back with
       a flag the detail page can use to show a message. */
    if (!$listing) {
        $pdo->rollBack();
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

    /* =========================================
       DATE CONFLICT CHECK

       Checks against BOTH 'confirmed' and 'pending' bookings —
       not just 'confirmed'. A pending booking means someone
       else is already mid-inquiry/mid-checkout for those dates;
       if we only blocked on 'confirmed' here, two guests could
       both land a 'pending' row for overlapping dates and the
       conflict would only surface later when the host tries to
       accept the second one, instead of being caught here.
       (Stale, unpaid pending rows were already cleared above,
       so any 'pending' row seen here is either a real paid hold
       or still inside its 10-minute grace window.)

       This check happens INSIDE the same FOR UPDATE-locked
       transaction as the insert below, so it's race-safe: the
       lock above already serializes concurrent requests for
       this listing, and by the time a second request's SELECT
       runs, the first request's row is committed and visible.
    ========================================= */

    if (!$longTerm && $checkinDate && $checkoutDate) {

        $conflictStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM bookings
             WHERE listing_id = :listing_id
               AND status IN ('confirmed', 'pending')
               AND checkin_date IS NOT NULL
               AND checkout_date IS NOT NULL
               AND (
                    :checkin < checkout_date
                    AND :checkout > checkin_date
               )"
        );

        $conflictStmt->execute([
            'listing_id' => $listingId,
            'checkin'    => $checkinDate,
            'checkout'   => $checkoutDate,
        ]);

        $hasConflict = $conflictStmt->fetchColumn();

        if ($hasConflict) {

            $pdo->rollBack();

            header(
                "Location: /webprogg/Listings/listing-detail.php?id="
                . $listingId .
                "&unavailable=1"
            );

            exit;
        }
    }

    /* Create the booking. Still inside the same transaction/lock,
       so no other request can slip a booking in between our check
       above and this insert.
       paid_at is left NULL here — this is an unpaid inquiry hold,
       so it's eligible for the 10-minute stale-hold expiry until
       process-payment.php sets paid_at on it (or the host
       accepts it directly for an own-listing "List Now" case,
       which also counts as no payment needed since it's already
       'confirmed').
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

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: /webprogg/Listings/listing-detail.php?id=" . $listingId . "&unavailable=1");
    exit;
}

header("Location: /webprogg/user/userprofile.php?booked=1");
exit;