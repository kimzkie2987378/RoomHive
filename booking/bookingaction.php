<?php
/* =========================================================
   ROOMHIVE — BOOKING ACTION
   bookingaction.php

   POST-only handler. A host clicks "Accept" or "Decline" on a
   pending booking card in hostbookings.php; this file updates
   that booking's status and redirects back. It is never
   navigated to directly — there is nothing to render here.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   METHOD GUARD
   This endpoint only ever makes sense as a form POST from
   hostbookings.php. Reject anything else instead of silently
   doing nothing.
----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /webprogg/host/hostbookings.php");
    exit;
}

/* -----------------------------------------------------
   CSRF GUARD
   Uses the same $_SESSION['csrf_token'] that hostbookings.php
   prints into each Accept/Decline form as a hidden field.
----------------------------------------------------- */
$submittedToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    header("Location: /webprogg/host/hostbookings.php?msg=error");
    exit;
}

/* -----------------------------------------------------
   INPUT
----------------------------------------------------- */
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$action    = $_POST['action'] ?? '';
$filter    = $_POST['filter'] ?? 'all'; // preserves whichever tab the host was on

$allowedFilters = ['all', 'pending', 'confirmed', 'cancelled'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$actionToStatus = [
    'accept'  => 'confirmed',
    'decline' => 'cancelled',
];

if (!$bookingId || !isset($actionToStatus[$action])) {
    header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
    exit;
}

$newStatus = $actionToStatus[$action];

/* -----------------------------------------------------
   HOST GUARD (ownership check happens in the same query as
   the update below — see WHERE clause. A host can only ever
   act on a booking that belongs to one of THEIR listings.)
----------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT is_host FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser || !$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

/* -----------------------------------------------------
   UPDATE — scoped to bookings that:
     1. match the given booking id
     2. belong to a listing owned by this host
     3. are currently 'pending'
   That third condition stops a booking from being
   accepted/declined twice (e.g. two tabs open, or a
   resubmitted form), and stops any status other than
   'pending' from being overwritten by this endpoint.
----------------------------------------------------- */
$update = $pdo->prepare(
    "UPDATE bookings b
     INNER JOIN listings l ON l.id = b.listing_id
     SET b.status = :new_status
     WHERE b.id = :booking_id
       AND l.user_id = :host_id
       AND b.status = 'pending'"
);
$update->execute([
    'new_status' => $newStatus,
    'booking_id' => $bookingId,
    'host_id'    => $_SESSION['user_id'],
]);

// rowCount() === 0 means either the booking wasn't found, didn't belong
// to this host, or was no longer 'pending' — all three read as a
// generic error rather than a false "success".
$successMsg = ['accept' => 'accepted', 'decline' => 'declined'];
$msg = ($update->rowCount() > 0) ? $successMsg[$action] : 'error';

header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg={$msg}");
exit;
