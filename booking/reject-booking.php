<?php
/* =========================================================
   ROOMHIVE — REJECT BOOKING
   reject-booking.php

   Called via fetch() from mylistings.php / hostprofile.php /
   pendingtenants.php when a host clicks "Reject" on a tenant's
   application. Moves bookings.status from 'pending' to
   'rejected' AND refunds whatever the tenant has paid into this
   booking so far (bookings.amount_paid) back to the tenant, as
   a wallet credit.

   GUEST NOTIFICATION: after the reject + refund COMMIT
   succeeds, the guest is notified:
     "[Host] declined your booking for "[Listing]". Your ₱X
      payment has been refunded to your RoomHive wallet."
   linked to their Booking Details page. The notification is
   written OUTSIDE the transaction and wrapped in its own
   try/catch — a failed notification can never roll back the
   refund or break the rejection response.

   SCHEMA (already present in your DB):
     users.wallet_balance,
     bookings.refunded_amount / refunded_at

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

 $pdo->beginTransaction();

try {
    /* FOR UPDATE locks this row for the duration of the
       transaction — a double-click or a race with an accept
       click on the same booking can't both go through. */
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
       reject it. */
    if ((int) $booking['host_id'] !== (int) $_SESSION['user_id']) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this application.']);
        exit;
    }

    /* ...and only while it's still pending, so rejecting twice
       can't double-refund. */
    if ($booking['status'] !== 'pending') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    $amountPaid = (float) $booking['amount_paid'];

    $updateStmt = $pdo->prepare(
        "UPDATE bookings
         SET status = 'rejected',
             refunded_amount = :refunded_amount,
             refunded_at = NOW()
         WHERE id = :booking_id AND status = 'pending'"
    );
    $updateStmt->execute([
        'refunded_amount' => $amountPaid,
        'booking_id'      => $bookingId,
    ]);

    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    /* Refund whatever was paid — normally the 50% reserve —
       back into the tenant's RoomHive wallet balance. */
    if ($amountPaid > 0) {
        $pdo->prepare(
            "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :tenant_id"
        )->execute([
            'amount'    => $amountPaid,
            'tenant_id' => $booking['tenant_id'],
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not reject this application. Please try again.']);
    exit;
}

/* -----------------------------------------------------
   NOTIFY THE GUEST (after the refund COMMIT; failure here
   can never affect the rejection above)
----------------------------------------------------- */
try {
    $guestId      = (int) $booking['tenant_id'];
    $hostName     = $_SESSION['user_name'] ?? 'The host';
    $listingTitle = (string) $booking['listing_title'];

    $message = $hostName . ' declined your booking for "' . $listingTitle . '".';
    if ($amountPaid > 0) {
        $message .= ' Your ₱' . number_format($amountPaid, 2) .
                    ' payment has been refunded to your RoomHive wallet.';
    }
    $link = '/webprogg/booking/booking-details.php?id=' . $bookingId;

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
    error_log('reject-booking guest notification failed: ' . $e->getMessage());
}

echo json_encode(['success' => true]);