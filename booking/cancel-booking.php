<?php
/* =========================================================
   ROOMHIVE — CANCEL BOOKING (tenant)
   cancel-booking.php

   Called via fetch() from booking-details.php when a tenant
   clicks "Cancel Booking". Moves their own booking from
   'pending' or 'confirmed' to 'cancelled'.

   === NEW — 2% CANCELLATION FEE ===
   When the tenant cancels a booking they have PAID into:
     - 2% of bookings.amount_paid is credited to the HOST's
       wallet as a cancellation fee.
     - The tenant is refunded the remaining 98% to their wallet.
     - refunded_amount records what the tenant actually
       received (98%), refunded_at the timestamp.

   Confirmed bookings: the host was already paid 97% at
   acceptance. We claw back (host_payout_amount - fee) from
   the host's wallet so the host still nets exactly the 2%.
   A clawback can push the host wallet negative if they
   already withdrew — same behaviour as before.

   Unpaid bookings (amount_paid = 0): nothing moves; the
   booking is simply cancelled.

   Host-reject (reject-booking.php) is UNCHANGED — a host
   declining still refunds the tenant 100%.

   After a successful cancel the host is notified, including
   the fee amount. A failed notification never breaks the
   cancellation.

   Expects POST: booking_id
   Responds JSON: { success, message?, fee, refund }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.', 'fee' => 0, 'refund' => 0]);
    exit;
}

 $bookingId = isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])
    ? (int) $_POST['booking_id']
    : 0;

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid booking id.', 'fee' => 0, 'refund' => 0]);
    exit;
}

/* Cancellation fee: 2% of whatever the tenant paid, paid to the host. */
const CANCELLATION_FEE_RATE = 0.02;

 $pdo->beginTransaction();

try {
    /* FOR UPDATE — a double-click or a host accepting at the
       same moment can't both go through. */
    $bookingStmt = $pdo->prepare(
        "SELECT b.id, b.user_id, b.status, b.amount_paid,
                b.host_payout_amount,
                l.user_id AS host_id, l.title AS listing_title,
                u.name    AS guest_name
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         JOIN users u    ON u.id = b.user_id
         WHERE b.id = :id
         FOR UPDATE"
    );
    $bookingStmt->execute(['id' => $bookingId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.', 'fee' => 0, 'refund' => 0]);
        exit;
    }

    if ((int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel this booking.', 'fee' => 0, 'refund' => 0]);
        exit;
    }

    if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This booking can no longer be cancelled.', 'fee' => 0, 'refund' => 0]);
        exit;
    }

    $amountPaid   = round((float) $booking['amount_paid'], 2);
    $fee          = round($amountPaid * CANCELLATION_FEE_RATE, 2);
    $refund       = round($amountPaid - $fee, 2);
    $wasConfirmed = ($booking['status'] === 'confirmed');
    $hostPayout   = round((float) ($booking['host_payout_amount'] ?? 0), 2);

    /* -----------------------------------------------------
       HOST SIDE OF THE FEE
       - Pending cancel: host receives the 2% fee directly.
       - Confirmed cancel: the host already holds the 97%
         payout — claw back everything except the 2% fee so
         they net exactly the fee.
    ----------------------------------------------------- */
    if ($wasConfirmed && $hostPayout > 0) {
        $clawback = round($hostPayout - $fee, 2);
        if ($clawback > 0) {
            $pdo->prepare(
                "UPDATE users SET wallet_balance = wallet_balance - :amount WHERE id = :host_id"
            )->execute([
                'amount'  => $clawback,
                'host_id' => $booking['host_id'],
            ]);
        }
    } elseif ($fee > 0) {
        $pdo->prepare(
            "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :host_id"
        )->execute([
            'amount'  => $fee,
            'host_id' => $booking['host_id'],
        ]);
    }

    /* -----------------------------------------------------
       TENANT REFUND — 98% of what they paid
    ----------------------------------------------------- */
    if ($refund > 0) {
        $pdo->prepare(
            "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :tenant_id"
        )->execute([
            'amount'    => $refund,
            'tenant_id' => $booking['user_id'],
        ]);
    }

    $updateStmt = $pdo->prepare(
        "UPDATE bookings
         SET status = 'cancelled',
             refunded_amount = :refunded_amount,
             refunded_at = NOW()
         WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'confirmed')"
    );
    $updateStmt->execute([
        'refunded_amount' => $refund,
        'id'              => $bookingId,
        'user_id'         => $_SESSION['user_id'],
    ]);

    if ($updateStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This booking can no longer be cancelled.', 'fee' => 0, 'refund' => 0]);
        exit;
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not cancel this booking. Please try again.', 'fee' => 0, 'refund' => 0]);
    exit;
}

/* -----------------------------------------------------
   NOTIFY THE HOST (best effort, after COMMIT) — includes
   the cancellation fee they just earned.
----------------------------------------------------- */
try {
    $hostId    = (int) $booking['host_id'];
    $guestName = (string) $booking['guest_name'];
    $title     = (string) $booking['listing_title'];

    $message = $guestName . ' cancelled their booking for "' . $title . '".' .
               ' The dates are now free again.';
    if ($fee > 0) {
        $message .= ' A ₱' . number_format($fee, 2) .
                    ' cancellation fee (2%) has been credited to your wallet.';
    }

    $notifyHelper = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
    if (file_exists($notifyHelper)) {
        include_once $notifyHelper;
    }

    $notified = false;
    if (function_exists('roomhive_notify')) {
        $notified = roomhive_notify($pdo, $hostId, $message, '/webprogg/host/hostbookings.php');
    }

    if (!$notified) {
        $notifyStmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
             VALUES (:u, :m, :l, 0, NOW())"
        );
        $notifyStmt->execute([
            'u' => $hostId,
            'm' => mb_substr($message, 0, 240),
            'l' => '/webprogg/host/hostbookings.php',
        ]);
    }
} catch (PDOException $e) {
    error_log('cancel-booking host notification failed: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'fee'     => $fee,
    'refund'  => $refund,
]);