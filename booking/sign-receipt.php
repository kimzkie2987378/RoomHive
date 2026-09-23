<?php
/* =========================================================
   ROOMHIVE — SIGN RECEIPT (host)
   sign-receipt.php

   Called from pendingtenants.php "Sign & Accept". Saves the
   host's drawn signature for a booking BEFORE the booking is
   accepted. payment-confirmation.php reads the saved file and
   stamps it onto the guest's receipt.

   Storage (no DB changes needed):
     /webprogg/uploads/signatures/booking-{id}.png   <- signature
     /webprogg/uploads/signatures/booking-{id}.json  <- signed_at, host_id

   Rules enforced server-side:
     - must be a logged-in host
     - booking must be PENDING and on the host's own listing
     - payload must be a real PNG (magic-byte checked)
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json');

function sr_out($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sr_out(false, 'Please log in again.');
}

 $bookingId = isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])
    ? (int) $_POST['booking_id']
    : 0;
 $signature = isset($_POST['signature']) ? trim((string) $_POST['signature']) : '';

if ($bookingId <= 0) {
    sr_out(false, 'Invalid booking.');
}

/* Validate the data-URL envelope */
if (strpos($signature, 'data:image/png;base64,') !== 0) {
    sr_out(false, 'Signature data missing or invalid.');
}

 $b64 = substr($signature, strlen('data:image/png;base64,'));
 $b64 = str_replace(' ', '+', $b64); /* safety for unencoded plus signs */
 $bin = base64_decode($b64, true);

if ($bin === false || strlen($bin) < 200 || strlen($bin) > 1500000) {
    sr_out(false, 'Signature image invalid.');
}

/* Real PNG? (magic bytes: 89 50 4E 47 0D 0A 1A 0A) */
if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    sr_out(false, 'Signature must be a PNG image.');
}

/* Booking must be PENDING and belong to THIS host's listing */
 $stmt = $pdo->prepare(
    "SELECT b.id
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE b.id = :id
       AND l.user_id = :host
       AND b.status = 'pending'
     LIMIT 1"
);
 $stmt->execute(['id' => $bookingId, 'host' => $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    sr_out(false, 'This application can no longer be signed.');
}

/* Save to disk (folder is auto-created) */
 $dir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/signatures';
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    sr_out(false, 'Could not create signature storage.');
}

 $pngFile  = $dir . '/booking-' . $bookingId . '.png';
 $jsonFile = $dir . '/booking-' . $bookingId . '.json';

if (@file_put_contents($pngFile, $bin, LOCK_EX) === false) {
    sr_out(false, 'Could not save signature.');
}

 $meta = json_encode([
    'booking_id' => $bookingId,
    'host_id'    => (int) $_SESSION['user_id'],
    'signed_at'  => date('c'),
]);
@file_put_contents($jsonFile, $meta, LOCK_EX);

sr_out(true, 'Signature saved.');