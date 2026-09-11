<?php
/* =========================================================
   ROOMHIVE — REJECT BOOKING
   reject-booking.php

   Called via fetch() from mylistings.php / hostprofile.php /
   pendingtenants.php when a host clicks "Reject" on a tenant's
   application. Moves bookings.status from 'pending' to
   'rejected' AND refunds whatever the tenant has paid into this
   booking so far (bookings.amount_paid) back to the tenant, as a
   wallet credit.

   REQUIRES (run once, same as accept-booking.php):
     ALTER TABLE users
       ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00;

     ALTER TABLE bookings
       ADD COLUMN refunded_amount DECIMAL(10,2) NULL AFTER amount_paid,
       ADD COLUMN refunded_at DATETIME NULL AFTER refunded_amount;

   NOTE: there's no real payment gateway wired up here (see
   process-payment.php's own note) — GCash/Maya/card were never
   actually charged, so this can't push money back out to a real
   mobile wallet or card. It refunds into the tenant's RoomHive
   wallet balance instead, the same "simulated money" model
   process-payment.php already uses for charging in the first
   place. Swap for a real gateway refund call when one exists.

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
       transaction — a double-click or a race with an accept click
       on the same booking can't both go through, and can't both
       trigger a refund/payout. */
    $ownershipStmt = $pdo->prepare(
        "SELECT b.id, b.status, b.amount_paid, b.user_id AS tenant_id, l.user_id AS host_id
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
        // Someone else / another tab already decided this booking
        // between our SELECT and our UPDATE.
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    /* Refund whatever was paid — normally the ₱1,000 reservation
       fee — back into the tenant's RoomHive wallet balance. */
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
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not reject this application. Please try again.']);
    exit;
}

echo json_encode(['success' => true]);