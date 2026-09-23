<?php
/* =========================================================
   ROOMHIVE — ACCEPT BOOKING
   accept-booking.php

   Called via fetch() from mylistings.php / hostprofile.php /
   pendingtenants.php when a host clicks "Accept" on a tenant's
   application. Moves bookings.status from 'pending' to
   'confirmed' AND pays out whatever the tenant has paid into
   this booking so far (bookings.amount_paid), split 97% to the
   host / 3% platform fee.

   FIXED: the ownership SELECT was missing b.user_id, so the
   guest notification read an undefined key and notified
   user 0 (or failed). The guest's id is now selected as
   tenant_id and used everywhere.

   REQUIRES (run once — your schema already has these):
     users.wallet_balance,
     bookings.host_payout_amount / platform_fee_amount / payout_at

   Expects POST: booking_id
   Responds JSON: { success: bool, message?: string }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

 $bookingId = isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])
    ? (int) $_POST['booking_id']
    : 0;

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid booking id.']);
    exit;
}

/* Platform split — 97% to the host, 3% platform fee. */
const HOST_PAYOUT_RATE = 0.97;

 $pdo->beginTransaction();

try {
    /* FOR UPDATE locks this row — a double-click or a race with
       a reject click on the same booking can't both go through. */
    $ownershipStmt = $pdo->prepare(
        "SELECT b.id, b.status, b.amount_paid,
                b.user_id AS tenant_id,
                l.user_id AS host_id, l.title AS listing_title
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE b.id = :booking_id
         FOR UPDATE"
    );
    $ownershipStmt->execute(['booking_id' => $bookingId]);
    $booking = $ownershipStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }

    /* Only the host who owns the listing behind this booking may
       accept it. */
    if ((int) $booking['host_id'] !== (int) $_SESSION['user_id']) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this application.']);
        exit;
    }

    /* ...and only while it's still pending, so accepting twice
       can't double-pay-out. */
    if ($booking['status'] !== 'pending') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    /* Split whatever has actually been paid into this booking so
       far. Round the host's share first, then give the platform
       whatever's left over. */
    $amountPaid    = (float) $booking['amount_paid'];
    $hostShare     = round($amountPaid * HOST_PAYOUT_RATE, 2);
    $platformShare = round($amountPaid - $hostShare, 2);

    $updateStmt = $pdo->prepare(
        "UPDATE bookings
         SET status = 'confirmed',
             host_payout_amount = :host_share,
             platform_fee_amount = :platform_share,
             payout_at = NOW()
         WHERE id = :booking_id AND status = 'pending'"
    );
    $updateStmt->execute([
        'host_share'     => $hostShare,
        'platform_share' => $platformShare,
        'booking_id'     => $bookingId,
    ]);

    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    /* Credit the host's wallet with their 97% share. */
    if ($hostShare > 0) {
        $pdo->prepare(
            "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :host_id"
        )->execute([
            'amount'  => $hostShare,
            'host_id' => $booking['host_id'],
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not accept this application. Please try again.']);
    exit;
}

/* -----------------------------------------------------
   NOTIFY THE GUEST (after the payout COMMIT; failure here
   can never affect the acceptance above). FIXED: reads
   tenant_id, which is now guaranteed to exist in $booking.
----------------------------------------------------- */
try {
    $guestId      = (int) $booking['tenant_id'];
    $hostName     = $_SESSION['user_name'] ?? 'The host';
    $listingTitle = (string) $booking['listing_title'];

    $message = $hostName . ' accepted your booking for "' . $listingTitle . '".' .
               ' The host has signed your official receipt.';
    $link    = '/webprogg/booking/booking-details.php?id=' . $bookingId;

    if ($guestId <= 0) {
        throw new RuntimeException('accept-booking: tenant_id missing for booking ' . $bookingId);
    }

    $notifyHelper = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
    if (file_exists($notifyHelper)) {
        include_once $notifyHelper;
    }

    $notified = false;
    if (function_exists('roomhive_notify')) {
        $notified = roomhive_notify($pdo, $guestId, $message, $link);
    }

    if (!$notified) {
        $n = $pdo->prepare(
            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
             VALUES (:u, :m, :l, 0, NOW())"
        );
        $n->execute([
            'u' => $guestId,
            'm' => mb_substr($message, 0, 240),
            'l' => $link,
        ]);
    }
} catch (Exception $e) {
    error_log('accept-booking guest notification failed: ' . $e->getMessage());
}

echo json_encode(['success' => true]);