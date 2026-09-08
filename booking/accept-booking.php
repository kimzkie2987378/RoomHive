<?php
/* =========================================================
   ROOMHIVE — ACCEPT BOOKING
   accept-booking.php

   Called via fetch() from mylistings.php / hostprofile.php
   when a host clicks "Accept Tenant" in a listing's 3-dot
   menu. Moves a tenant's application (bookings.status) from
   'pending' to 'confirmed'.

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

/* Only the host who owns the listing behind this booking may
   accept it — and only while it's still pending, so accepting
   twice (or a race with a reject click) can't double-fire. */
$ownershipStmt = $pdo->prepare(
    "SELECT b.id, b.status, l.user_id AS host_id
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE b.id = :booking_id
     LIMIT 1"
);
$ownershipStmt->execute(['booking_id' => $bookingId]);
$booking = $ownershipStmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit;
}

if ((int) $booking['host_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update this application.']);
    exit;
}

if ($booking['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
    exit;
}

$updateStmt = $pdo->prepare(
    "UPDATE bookings
     SET status = 'confirmed'
     WHERE id = :booking_id AND status = 'pending'"
);
$updateStmt->execute(['booking_id' => $bookingId]);

if ($updateStmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'This application has already been decided.']);
    exit;
}

echo json_encode(['success' => true]);
