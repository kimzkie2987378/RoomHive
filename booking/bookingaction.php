<?php
/* =========================================================
   ROOMHIVE — BOOKING ACTION (host)
   bookingaction.php

   POST-only handler. A host clicks "Accept" or "Decline" on a
   pending booking card in hostbookings.php.

   REWRITTEN — UNIFIED WITH accept/reject-booking.php:
   The old version:
     - accept  -> set 'confirmed' with NO payout split, NO
                  wallet credit, NO notification
     - decline -> set 'cancelled' (reject-booking.php uses
                  'rejected'), NO refund, NO notification
   Now both actions run the exact same transactional logic as
   the fetch endpoints, so a booking behaves identically no
   matter which host page it was actioned from:
     - Accept  -> confirmed + 97/3 split + host wallet credit
                  + guest notification
     - Decline -> rejected + full refund of amount_paid to the
                  guest's wallet + guest notification

   NOTE: hostbookings.php's tabs should include 'rejected' now
   (declined bookings land there, not in 'cancelled' —
   'cancelled' is tenant-initiated only).
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
 $filter    = $_POST['filter'] ?? 'all';

 $allowedFilters = ['all', 'pending', 'confirmed', 'cancelled', 'rejected'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

if (!$bookingId || !in_array($action, ['accept', 'decline'], true)) {
    header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
    exit;
}

const HOST_PAYOUT_RATE = 0.97;

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
 $stmt = $pdo->prepare("SELECT is_host FROM users WHERE id = :id LIMIT 1");
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser || !$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

/* -----------------------------------------------------
   PERFORM THE ACTION
----------------------------------------------------- */
 $decided       = false;
 $notifyMessage = '';

 $pdo->beginTransaction();

try {
    $lockStmt = $pdo->prepare(
        "SELECT b.id, b.status, b.amount_paid,
                b.user_id AS tenant_id,
                l.user_id AS host_id, l.title AS listing_title
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE b.id = :booking_id
         FOR UPDATE"
    );
    $lockStmt->execute(['booking_id' => $bookingId]);
    $booking = $lockStmt->fetch();

    /* Not found, or belongs to another host's listing */
    if (!$booking || (int) $booking['host_id'] !== (int) $_SESSION['user_id']) {
        $pdo->rollBack();
        header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
        exit;
    }

    /* Only pending bookings can be decided — stops double
       submits, race with the fetch endpoints, etc. */
    if ($booking['status'] !== 'pending') {
        $pdo->rollBack();
        header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
        exit;
    }

    $amountPaid = (float) $booking['amount_paid'];

    if ($action === 'accept') {

        $hostShare     = round($amountPaid * HOST_PAYOUT_RATE, 2);
        $platformShare = round($amountPaid - $hostShare, 2);

        $upd = $pdo->prepare(
            "UPDATE bookings
             SET status = 'confirmed',
                 host_payout_amount = :host_share,
                 platform_fee_amount = :platform_share,
                 payout_at = NOW()
             WHERE id = :id AND status = 'pending'"
        );
        $upd->execute([
            'host_share'     => $hostShare,
            'platform_share' => $platformShare,
            'id'             => $bookingId,
        ]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
            exit;
        }

        if ($hostShare > 0) {
            $pdo->prepare(
                "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :host_id"
            )->execute([
                'amount'  => $hostShare,
                'host_id' => $booking['host_id'],
            ]);
        }

        $pdo->commit();
        $decided = true;

        $notifyMessage = ($_SESSION['user_name'] ?? 'The host')
            . ' accepted your booking for "' . $booking['listing_title'] . '".'
            . ' The host has signed your official receipt.';

    } else { /* decline */

        $upd = $pdo->prepare(
            "UPDATE bookings
             SET status = 'rejected',
                 refunded_amount = :refunded_amount,
                 refunded_at = NOW()
             WHERE id = :id AND status = 'pending'"
        );
        $upd->execute([
            'refunded_amount' => $amountPaid,
            'id'              => $bookingId,
        ]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
            exit;
        }

        if ($amountPaid > 0) {
            $pdo->prepare(
                "UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :tenant_id"
            )->execute([
                'amount'    => $amountPaid,
                'tenant_id' => $booking['tenant_id'],
            ]);
        }

        $pdo->commit();
        $decided = true;

        $notifyMessage = ($_SESSION['user_name'] ?? 'The host')
            . ' declined your booking for "' . $booking['listing_title'] . '".';
        if ($amountPaid > 0) {
            $notifyMessage .= ' Your ₱' . number_format($amountPaid, 2)
                . ' payment has been refunded to your RoomHive wallet.';
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('bookingaction failed: ' . $e->getMessage());
    header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg=error");
    exit;
}

/* -----------------------------------------------------
   NOTIFY THE GUEST (best effort, after COMMIT)
----------------------------------------------------- */
if ($decided) {
    try {
        $guestId = (int) $booking['tenant_id'];
        $link    = '/webprogg/booking/booking-details.php?id=' . $bookingId;

        if ($guestId > 0) {
            $notifyHelper = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
            if (file_exists($notifyHelper)) {
                include_once $notifyHelper;
            }

            $notified = false;
            if (function_exists('roomhive_notify')) {
                $notified = roomhive_notify($pdo, $guestId, $notifyMessage, $link);
            }

            if (!$notified) {
                $n = $pdo->prepare(
                    "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                     VALUES (:u, :m, :l, 0, NOW())"
                );
                $n->execute([
                    'u' => $guestId,
                    'm' => mb_substr($notifyMessage, 0, 240),
                    'l' => $link,
                ]);
            }
        }
    } catch (Exception $e) {
        error_log('bookingaction guest notification failed: ' . $e->getMessage());
    }

    $msg = ($action === 'accept') ? 'accepted' : 'declined';
    header("Location: /webprogg/host/hostbookings.php?filter={$filter}&msg={$msg}");
    exit;
}