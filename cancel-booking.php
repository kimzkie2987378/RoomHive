<?php
/* =========================================================
   ROOMHIVE — CANCEL BOOKING
   cancel-booking.php

   Called via fetch() from booking-details.php when a tenant
   clicks "Cancel Booking". Moves their own booking from
   'pending' or 'confirmed' to 'cancelled'. Once cancelled,
   the listing's availability check (listing.php / listing-
   detail.php both check for status IN ('pending','confirmed'))
   automatically frees the listing back up for other tenants.

   Expects POST: booking_id
   Responds JSON: { success: bool, message?: string }
========================================================= */

session_start();
require_once 'db_connect.php';

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

/* Only the tenant who made this booking may cancel it — and
   only while it's still pending or confirmed. */
$bookingStmt = $pdo->prepare(
    "SELECT id, user_id, status FROM bookings WHERE id = :id LIMIT 1"
);
$bookingStmt->execute(['id' => $bookingId]);
$booking = $bookingStmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if ((int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel this booking.']);
    exit;
}

if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
    echo json_encode(['success' => false, 'message' => 'This booking can no longer be cancelled.']);
    exit;
}

$updateStmt = $pdo->prepare(
    "UPDATE bookings
     SET status = 'cancelled'
     WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'confirmed')"
);
$updateStmt->execute([
    'id'      => $bookingId,
    'user_id' => $_SESSION['user_id'],
]);

if ($updateStmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'This booking can no longer be cancelled.']);
    exit;
}

echo json_encode(['success' => true]);
