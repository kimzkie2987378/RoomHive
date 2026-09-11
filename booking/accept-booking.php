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

   REQUIRES (run once):
     ALTER TABLE users
       ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00;

     ALTER TABLE bookings
       ADD COLUMN host_payout_amount DECIMAL(10,2) NULL AFTER amount_paid,
       ADD COLUMN platform_fee_amount DECIMAL(10,2) NULL AFTER host_payout_amount,
       ADD COLUMN payout_at DATETIME NULL AFTER platform_fee_amount;

   NOTE: this pays out whatever amount_paid is AT THE MOMENT OF
   ACCEPTANCE — for the normal flow that's the ₱1,000 reservation
   fee (see process-payment.php). If the tenant later pays the
   remaining balance on an already-confirmed booking,
   process-payment.php's "balance" branch is what collects that
   payment — it is NOT covered by this file, since accept-booking.php
   only ever runs once, at the pending -> confirmed transition. If
   balance payments should also split 97/3 to the host as they come
   in, that split needs to be added over there too.

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

/* Platform split — 97% to the host, 3% platform fee. Keep this in
   one place; process-payment.php's balance-payment branch should
   reuse the same rate if/when it starts splitting balance payments
   too, rather than hardcoding 0.97 a second time somewhere else. */
const HOST_PAYOUT_RATE = 0.97;

$pdo->beginTransaction();

try {
    /* FOR UPDATE locks this row for the duration of the
       transaction — same reasoning as process-payment.php's
       balance-payment branch: a double-click or a race with a
       reject click on the same booking can't both go through, and
       can't both trigger a payout/refund. */
    $ownershipStmt = $pdo->prepare(
        "SELECT b.id, b.status, b.amount_paid, l.user_id AS host_id
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
       whatever's left over — so host_share + platform_share always
       adds back up to amount_paid exactly, instead of each side
       rounding independently and drifting by a centavo. */
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
        // Someone else / another tab already decided this booking
        // between our SELECT and our UPDATE.
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
        exit;
    }

    /* Credit the host's wallet with their 97% share. The 3%
       platform fee isn't credited to any user row — it's recorded
       on the booking itself (platform_fee_amount above), which is
       what admin.php's Payouts panel should SUM(platform_fee_amount)
       from once that panel gets built out. */
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
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not accept this application. Please try again.']);
    exit;
}

echo json_encode(['success' => true]);