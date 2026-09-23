<?php
/* =========================================================
   ROOMHIVE — MARK STAY COMPLETED (host)
   complete-booking.php

   Called via fetch() from hostbookings.php ("Mark Completed").
   Moves a CONFIRMED booking to 'completed' and immediately
   awards the tenant Hive Club points for what they paid
   (1 pt per P10). The ledger's UNIQUE key makes this safe to
   race with the auto-sweep — only one award ever lands.

   Long-term stays (checkout_date IS NULL) are completed here —
   that's their ONLY completion path.

   Money: nothing moves at completion — the 97/3 payout already
   happened when the host accepted. Completion only flips the
   status and awards points.

   Expects POST: booking_id
   Responds JSON: { success, message?, points? }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

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
    /* Lock + verify: booking must belong to THIS host's listing
       and still be confirmed. */
    $lockStmt = $pdo->prepare(
        "SELECT b.id, b.status, b.amount_paid,
                b.user_id AS tenant_id,
                l.user_id AS host_id, l.title AS listing_title
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE b.id = :id
         FOR UPDATE"
    );
    $lockStmt->execute(['id' => $bookingId]);
    $booking = $lockStmt->fetch();

    if (!$booking || (int) $booking['host_id'] !== (int) $_SESSION['user_id']) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    if ($booking['status'] !== 'confirmed') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Only confirmed bookings can be marked completed.']);
        exit;
    }

    $upd = $pdo->prepare(
        "UPDATE bookings SET status = 'completed'
         WHERE id = :id AND status = 'confirmed'"
    );
    $upd->execute(['id' => $bookingId]);

    if ($upd->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This booking can no longer be updated.']);
        exit;
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not complete this booking. Please try again.']);
    exit;
}

/* -----------------------------------------------------
   AWARD POINTS (after COMMIT; the ledger unique key makes
   this a safe no-op if the auto-sweep got here first).
----------------------------------------------------- */
 $pointsAwarded = 0;

try {
    $points = hive_points_for_peso((float) $booking['amount_paid']);
    if ($points < 1 && (float) $booking['amount_paid'] > 0) { $points = 1; }

    if ($points > 0) {
        $result = hive_award_points(
            $pdo,
            (int) $booking['tenant_id'],
            $points,
            'Stay completed: ' . $booking['listing_title'],
            'booking',
            $bookingId
        );

        if (!empty($result['ok']) && empty($result['already'])) {
            $pointsAwarded = $points;
        }
    }
} catch (Exception $e) {
    /* Not fatal — the sweep's Stage B backfills this later. */
    error_log('complete-booking award failed (booking ' . $bookingId . '): ' . $e->getMessage());
}

/* -----------------------------------------------------
   NOTIFY THE TENANT (host initiated, so no host notify)
----------------------------------------------------- */
try {
    $msg = '🍯 Your stay at "' . $booking['listing_title'] . '" is complete.';
    if ($pointsAwarded > 0) {
        $msg .= ' You earned ' . number_format($pointsAwarded) . ' Hive Club points!';
    }

    $nh = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
    if (file_exists($nh)) { require_once $nh; }

    if (function_exists('roomhive_notify')) {
        roomhive_notify($pdo, (int) $booking['tenant_id'], $msg,
            '/webprogg/booking/booking-details.php?id=' . $bookingId);
    } else {
        $n = $pdo->prepare(
            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
             VALUES (:u, :m, :l, 0, NOW())"
        );
        $n->execute([
            'u' => (int) $booking['tenant_id'],
            'm' => mb_substr($msg, 0, 240),
            'l' => '/webprogg/booking/booking-details.php?id=' . $bookingId,
        ]);
    }
} catch (PDOException $e) {
    error_log('complete-booking tenant notify failed: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'points'  => $pointsAwarded,
]);