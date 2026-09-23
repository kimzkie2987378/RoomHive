<?php
/* =========================================================
   ROOMHIVE — SUBMIT REVIEW (endpoint)
   booking/submit-review.php

   Called by the post-cancel rating modal in
   booking-details.php. Saves a star rating + optional
   comment into `reviews`, then notifies the host.

   Rules:
     - logged-in user, own booking only
     - booking must be 'cancelled' or 'completed'
     - rating 1..5, comment optional (max 600 chars)
     - one review per user per listing: a second submission
       UPDATES the existing review instead of duplicating

   Expects POST: booking_id, rating, comment
   Responds JSON: { success: bool, message?: string }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json');

function rv_out($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    rv_out(false, 'Please log in again.');
}

 $bookingId = isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])
    ? (int) $_POST['booking_id']
    : 0;

 $rating = isset($_POST['rating']) && is_numeric($_POST['rating'])
    ? (int) $_POST['rating']
    : 0;

 $comment = trim((string) ($_POST['comment'] ?? ''));
 $comment = mb_substr($comment, 0, 600);

if ($bookingId <= 0) {
    rv_out(false, 'Invalid booking.');
}

if ($rating < 1 || $rating > 5) {
    rv_out(false, 'Please pick a star rating first.');
}

/* -----------------------------------------------------
   LOAD BOOKING + LISTING + HOST
----------------------------------------------------- */
 $stmt = $pdo->prepare(
    "SELECT b.id, b.user_id, b.status,
            l.id AS listing_id, l.user_id AS host_id, l.title AS listing_title
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE b.id = :id
     LIMIT 1"
);
 $stmt->execute(['id' => $bookingId]);
 $booking = $stmt->fetch();

if (!$booking) {
    rv_out(false, 'Booking not found.');
}

if ((int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
    rv_out(false, 'You can only rate your own bookings.');
}

/* Only rate a booking that actually ended */
if (!in_array($booking['status'], ['cancelled', 'completed'], true)) {
    rv_out(false, 'You can rate this stay once it is cancelled or completed.');
}

/* -----------------------------------------------------
   UPSERT — one review per user per listing
----------------------------------------------------- */
try {
    $existingStmt = $pdo->prepare(
        "SELECT id FROM reviews WHERE user_id = :u AND listing_id = :l LIMIT 1"
    );
    $existingStmt->execute([
        'u' => $_SESSION['user_id'],
        'l' => (int) $booking['listing_id'],
    ]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId) {
        $upd = $pdo->prepare(
            "UPDATE reviews
             SET rating = :r, comment = :c
             WHERE id = :id"
        );
        $upd->execute([
            'r'  => $rating,
            'c'  => $comment,
            'id' => (int) $existingId,
        ]);
    } else {
        $ins = $pdo->prepare(
            "INSERT INTO reviews (user_id, listing_id, rating, comment, created_at)
             VALUES (:u, :l, :r, :c, NOW())"
        );
        $ins->execute([
            'u' => $_SESSION['user_id'],
            'l' => (int) $booking['listing_id'],
            'r' => $rating,
            'c' => $comment,
        ]);
    }
} catch (PDOException $e) {
    error_log('submit-review save failed: ' . $e->getMessage());
    rv_out(false, 'Could not save your rating. Please try again.');
}

/* -----------------------------------------------------
   NOTIFY THE HOST
   "[Guest] left a N-star review on "[Listing]"."
   -> linked to their Reviews page. A failed notification
   never affects the saved review.
----------------------------------------------------- */
try {
    $guestName = $_SESSION['user_name'] ?? 'A guest';
    $hostId    = (int) $booking['host_id'];

    $message = $guestName . ' left a ' . $rating . '-star review on "'
             . $booking['listing_title'] . '".';
    $link    = '/webprogg/host/hostreviews.php';

    $notifyHelper = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
    if (file_exists($notifyHelper)) {
        include_once $notifyHelper;
    }

    $notified = false;
    if (function_exists('roomhive_notify')) {
        $notified = roomhive_notify($pdo, $hostId, $message, $link);
    }

    if (!$notified) {
        $n = $pdo->prepare(
            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
             VALUES (:u, :m, :l, 0, NOW())"
        );
        $n->execute([
            'u' => $hostId,
            'm' => mb_substr($message, 0, 240),
            'l' => $link,
        ]);
    }
} catch (PDOException $e) {
    error_log('submit-review host notification failed: ' . $e->getMessage());
}

rv_out(true, 'Review saved.');