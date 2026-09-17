<?php
/* =========================================================
   ROOMHIVE — PROCESS PAYMENT
   process-payment.php

   Flow A (new inquiry): listingpayment.php (Step 2: choose
   method) -> THIS FILE -> payment-confirmation.php (Step 3).

   Flow B (pay remaining balance): booking-details.php's "Pay
   Remaining Balance" button -> listingpayment.php (pay_balance
   mode) -> THIS FILE -> payment-confirmation.php (balance=1).

   Flow C (owner "List Now"): listing-detail.php "List Now" ->
   listingpayment.php -> THIS FILE -> the listing is PUBLISHED
   (status = 'approved'). No booking row is created.

   Two stages via the hidden "stage" field:
     Stage 1 "review"  — method-specific mock screen
     Stage 2 "confirm" — validates input, then either inserts a
     booking (reservation), tops up amount_paid (balance), or
     publishes the listing (owner list fee).

   SERVER-SIDE ENFORCEMENT:
   - Listing status guards (no re-listing approved spaces,
     no booking unlisted spaces).
   - Date completion guard: check-in AND check-out required,
     check-in only when long_term = 1. Skipped for the owner's
     List Now flow (no dates involved).
   - Date-conflict guard: overlapping pending/confirmed holds
     are rejected — including open-ended long-term stays
     (checkout NULL blocks from move-in onward until finished).
   - "long_term" carried through Stage 1 -> Stage 2.

   EXPIRY NOTE: new bookings get paid_at = NOW() so they're
   exempt from the 10-minute unpaid-hold sweep.

   RECEIPT NOTE: even a PARTIAL payment (e.g. only the ₱1,000
   reservation fee) redirects to payment-confirmation.php —
   the receipt shows the advance payment and the amount left,
   so the guest can present it to the host. The chosen method
   is forwarded via &pm= so the receipt prints the real method.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* Strict Y-m-d validator — never trust client dates */
function pp_valid_date($value) {
    if (!is_string($value) || $value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

/* -----------------------------------------------------
   INBOUND DATA
----------------------------------------------------- */
 $listingId      = isset($_POST['listing_id']) && is_numeric($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
 $guests         = $_POST['guests']   ?? '1';
 $paymentMethod  = $_POST['payment_method'] ?? '';
 $stage          = $_POST['stage'] ?? 'review';
 $paymentPurpose = ($_POST['payment_purpose'] ?? 'reservation') === 'balance' ? 'balance' : 'reservation';
 $bookingId      = isset($_POST['booking_id']) && is_numeric($_POST['booking_id']) ? (int) $_POST['booking_id'] : null;

/* Long-term flag, carried from listingpayment.php */
 $longTerm = ($_POST['long_term'] ?? '0') === '1';

 $checkinRaw  = $_POST['checkin_date']  ?? '';
 $checkoutRaw = $_POST['checkout_date'] ?? '';

 $checkin  = pp_valid_date($checkinRaw) ?? '';
 $checkout = (!$longTerm) ? (pp_valid_date($checkoutRaw) ?? '') : '';

 $validMethods = ['gcash', 'maya', 'card'];
if (!in_array($paymentMethod, $validMethods, true)) {
    header('Location: /webprogg/booking/listingpayment.php?listing_id=' . $listingId);
    exit;
}

 $errors = [];

/* -----------------------------------------------------
   RELOAD THE LISTING (server-side source of truth)
----------------------------------------------------- */
 $listingStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.price, l.location, l.user_id AS host_id, l.status,
            p.photo_path AS cover_photo
     FROM listings l
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE l.id = :id
     LIMIT 1"
);
 $listingStmt->execute(['id' => $listingId]);
 $listing = $listingStmt->fetch();

if ($listing === false) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

/* Same reservation fee as listingpayment.php "Total Due Today" */
 $reservationFee = 1000.00;

/* -----------------------------------------------------
   LISTING STATUS + DATE GUARDS (reservation flow only)
----------------------------------------------------- */
 $isListingOwner = ((int) $listing['host_id'] === (int) $_SESSION['user_id']);
 $listingStatus  = $listing['status'] ?? '';

if ($paymentPurpose === 'reservation') {

    /* GUARD 1 — host cannot re-list an already-listed space */
    if ($isListingOwner && $listingStatus === 'approved') {
        header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&alreadylisted=1');
        exit;
    }

    /* GUARD 2 — renter cannot pay for an unlisted space */
    if (!$isListingOwner && $listingStatus !== 'approved') {
        header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&unavailable=1');
        exit;
    }

    /* GUARD 3 — dates must be complete (renters only; the
       host's List Now flow carries no dates by design) */
    if (!$isListingOwner) {
        if ($checkin === '' || (!$longTerm && $checkout === '')) {
            header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&incompletedates=1');
            exit;
        }
        if (!$longTerm && $checkout !== '' && $checkout < $checkin) {
            header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&incompletedates=1');
            exit;
        }
    }
}

/* -----------------------------------------------------
   BALANCE MODE — load + validate the existing booking
----------------------------------------------------- */
 $balanceBooking = null;
 $balanceDueNow  = null;

if ($paymentPurpose === 'balance') {

    if ($bookingId === null) {
        header('Location: /webprogg/booking/userbookings.php');
        exit;
    }

    $balanceStmt = $pdo->prepare(
        "SELECT id, user_id, listing_id, total, amount_paid, status,
                checkin_date, checkout_date, guests
         FROM bookings
         WHERE id = :id
         LIMIT 1"
    );
    $balanceStmt->execute(['id' => $bookingId]);
    $balanceBooking = $balanceStmt->fetch();

    $balanceIsValid = $balanceBooking !== false
        && (int) $balanceBooking['user_id'] === (int) $_SESSION['user_id']
        && (int) $balanceBooking['listing_id'] === $listingId
        && in_array($balanceBooking['status'], ['pending', 'confirmed'], true);

    if (!$balanceIsValid) {
        header('Location: /webprogg/booking/userbookings.php');
        exit;
    }

    $balanceDueNow = round((float) $balanceBooking['total'] - (float) $balanceBooking['amount_paid'], 2);

    if ($balanceDueNow <= 0.005) {
        header('Location: /webprogg/booking/booking-details.php?id=' . $bookingId);
        exit;
    }

    /* Show the booking's real dates on the payment screen */
    $checkin  = pp_valid_date($balanceBooking['checkin_date']  ?? '') ?? '';
    $checkout = pp_valid_date($balanceBooking['checkout_date'] ?? '') ?? '';
    $longTerm = ($checkin !== '' && $checkout === '');
    $guests   = $balanceBooking['guests'] ?? $guests;
}

 $amountDue = $paymentPurpose === 'balance' ? $balanceDueNow : $reservationFee;

/* =========================================================
   STAGE 2 — CONFIRM
========================================================= */
if ($stage === 'confirm') {

    if ($paymentMethod === 'gcash' || $paymentMethod === 'maya') {
        $mobileNumber = trim($_POST['mobile_number'] ?? '');
        if (!preg_match('/^09\d{9}$/', $mobileNumber)) {
            $errors[] = 'Enter a valid 11-digit mobile number starting with 09.';
        }
    }

    if ($paymentMethod === 'card') {
        $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $cardName   = trim($_POST['card_name'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvv    = trim($_POST['card_cvv'] ?? '');

        if (!preg_match('/^\d{16}$/', $cardNumber)) {
            $errors[] = 'Enter a valid 16-digit card number.';
        }
        if ($cardName === '') {
            $errors[] = 'Enter the name on the card.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) {
            $errors[] = 'Enter expiry as MM/YY.';
        }
        if (!preg_match('/^\d{3,4}$/', $cardCvv)) {
            $errors[] = 'Enter a valid CVV.';
        }
    }

    if (empty($errors)) {

        /* TODO: real gateway call goes here — only proceed once
           THEY confirm the charge succeeded. Simulated for now. */

        if ($paymentPurpose === 'balance') {

            /* ---------------------------------------------
               PAY REMAINING BALANCE — top up the existing
               booking's amount_paid. Re-lock + re-check the
               remaining balance right before writing.
            --------------------------------------------- */
            $pdo->beginTransaction();

            $lockStmt = $pdo->prepare(
                "SELECT id, user_id, listing_id, total, amount_paid, status
                 FROM bookings
                 WHERE id = :id
                 FOR UPDATE"
            );
            $lockStmt->execute(['id' => $bookingId]);
            $lockedBooking = $lockStmt->fetch();

            $stillValid = $lockedBooking !== false
                && (int) $lockedBooking['user_id'] === (int) $_SESSION['user_id']
                && (int) $lockedBooking['listing_id'] === $listingId
                && in_array($lockedBooking['status'], ['pending', 'confirmed'], true);

            if (!$stillValid) {
                $pdo->rollBack();
                header('Location: /webprogg/booking/userbookings.php');
                exit;
            }

            $remainingNow = round((float) $lockedBooking['total'] - (float) $lockedBooking['amount_paid'], 2);

            if ($remainingNow <= 0.005) {
                $pdo->rollBack();
                header('Location: /webprogg/booking/booking-details.php?id=' . $bookingId . '&paid=1');
                exit;
            }

            $updateStmt = $pdo->prepare(
                "UPDATE bookings
                 SET amount_paid = amount_paid + :amount
                 WHERE id = :id"
            );
            $updateStmt->execute([
                'amount' => $remainingNow,
                'id'     => $bookingId,
            ]);

            $pdo->commit();

            /* ===== STEP 3: go to the confirmation page ===== */
            header('Location: /webprogg/booking/payment-confirmation.php?id=' . $bookingId
                . '&paid=1&balance=1&pm=' . rawurlencode($paymentMethod)
                . '&amt=' . rawurlencode(number_format($remainingNow, 2, '.', '')));
            exit;

        } elseif ($isListingOwner) {

            /* ---------------------------------------------
               OWNER "LIST NOW" FLOW — the host paid the
               listing fee, publish the space. No booking
               row is created.

               NOTE: if your project uses ADMIN approval
               instead of pay-to-publish, DELETE the UPDATE
               below and keep only the redirect.
            --------------------------------------------- */
            $approveStmt = $pdo->prepare(
                "UPDATE listings
                 SET status = 'approved'
                 WHERE id = :id AND user_id = :user_id"
            );
            $approveStmt->execute([
                'id'      => $listingId,
                'user_id' => $_SESSION['user_id'],
            ]);

            header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&listed=1');
            exit;

        } else {

            /* ---------------------------------------------
               NEW INQUIRY — insert a fresh booking, with a
               date-conflict guard inside a transaction.

               Overlap rule (NULL end = open-ended long-term):
                 requested [ci, co|∞] overlaps existing
                 [eci, eco|∞] when  ci <= eco|∞  AND  co|∞ >= eci
            --------------------------------------------- */
            $pdo->beginTransaction();

            $conflictStmt = $pdo->prepare(
                "SELECT 1
                 FROM bookings
                 WHERE listing_id = :listing_id
                   AND status IN ('pending', 'confirmed')
                   AND checkin_date IS NOT NULL
                   AND :checkin <= COALESCE(checkout_date, '9999-12-31')
                   AND :range_end >= checkin_date
                 LIMIT 1
                 FOR UPDATE"
            );
            $conflictStmt->execute([
                'listing_id' => $listingId,
                'checkin'    => $checkin,
                'range_end'  => $checkout !== '' ? $checkout : '9999-12-31',
            ]);

            if ($conflictStmt->fetch()) {
                $pdo->rollBack();
                header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&unavailable=1');
                exit;
            }

            $roomTotal = (float) $listing['price'];

            $insertStmt = $pdo->prepare(
                "INSERT INTO bookings
                    (listing_id, user_id, total, amount_paid, status, booked_at, paid_at, checkin_date, checkout_date, guests)
                 VALUES
                    (:listing_id, :user_id, :total, :amount_paid, 'pending', NOW(), NOW(), :checkin_date, :checkout_date, :guests)"
            );
            $insertStmt->execute([
                'listing_id'    => $listingId,
                'user_id'       => $_SESSION['user_id'],
                'total'         => $roomTotal,
                'amount_paid'   => $reservationFee,
                'checkin_date'  => $checkin ?: null,
                'checkout_date' => $checkout ?: null,
                'guests'        => $guests,
            ]);

            $pdo->commit();

            $newBookingId = (int) $pdo->lastInsertId();

            /* ===== STEP 3: go to the confirmation page =====
               Even though only the ₱1,000 reservation fee was
               paid, the guest ALWAYS gets an official receipt
               showing the advance payment + amount left. */
            header('Location: /webprogg/booking/payment-confirmation.php?id=' . $newBookingId
                . '&paid=1&pm=' . rawurlencode($paymentMethod)
                . '&amt=' . rawurlencode(number_format($reservationFee, 2, '.', '')));
            exit;
        }
    }

    /* Validation failed — re-render Stage 1 with errors */
    $stage = 'review';
}

 $methodLabels = [
    'gcash' => 'GCash',
    'maya'  => 'Maya',
    'card'  => 'Credit/Debit Card',
];

/* "Change payment method" destination — carries long_term */
if ($paymentPurpose === 'balance') {
    $changeMethodUrl = '/webprogg/booking/listingpayment.php'
        . '?listing_id=' . rawurlencode((string) $listingId)
        . '&pay_balance=' . rawurlencode((string) $bookingId);
} else {
    $changeMethodUrl = '/webprogg/booking/listingpayment.php'
        . '?listing_id=' . rawurlencode((string) $listingId)
        . '&checkin_date=' . rawurlencode($checkin)
        . '&checkout_date=' . rawurlencode($checkout)
        . '&guests=' . rawurlencode($guests)
        . ($longTerm ? '&long_term=1' : '');
}

/* Summary line (what is being paid for) */
if ($isListingOwner && $paymentPurpose === 'reservation') {
    $summaryMeta = 'One-time listing fee &middot; publishes your space';
} elseif ($checkin !== '' && $checkout !== '') {
    $summaryMeta = date('M j, Y', strtotime($checkin)) . ' &rarr; ' . date('M j, Y', strtotime($checkout))
        . ' &middot; ' . h($guests) . ' guest' . ($guests === '1' ? '' : 's');
} elseif ($checkin !== '' && $longTerm) {
    $summaryMeta = 'Move-in ' . date('M j, Y', strtotime($checkin)) . ' &middot; Long Term';
} else {
    $summaryMeta = h($listing['location']);
}

 $chargeLabel = $paymentPurpose === 'balance'
    ? 'Balance to Pay'
    : ($isListingOwner ? 'Listing Fee' : 'Amount to Pay');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Payment — RoomHive</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<style>
    :root {
        --pp-ink: #14142B;
        --pp-soft: #8B93A6;
        --pp-line: #EEF1F6;
        --pp-honey: #F5A623;
        --pp-honey-light: #FFB94E;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #F7F8FA;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--pp-ink);
    }

    .pp-nav {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #fff;
        border-bottom: 1px solid var(--pp-line);
    }
    .pp-nav-inner {
        max-width: 560px;
        margin: 0 auto;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pp-brand {
        display: flex;
        align-items: center;
        gap: 9px;
        text-decoration: none;
    }
    .pp-brand-mark {
        width: 30px; height: 30px;
        border-radius: 9px;
        background: linear-gradient(135deg, var(--pp-honey-light), var(--pp-honey));
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        box-shadow: 0 4px 10px rgba(245,166,35,.35);
    }
    .pp-brand-mark svg { width: 16px; height: 16px; }
    .pp-brand-text { font-size: 14px; font-weight: 400; color: var(--pp-ink); }
    .pp-brand-text b { font-weight: 800; }
    .pp-secure-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #1E7A3D;
        background: #EAF9F0;
        border: 1px solid #C9EDD7;
        padding: 5px 11px;
        border-radius: 999px;
    }

    .pp-page { max-width: 480px; margin: 0 auto; padding: 24px 20px 60px; }

    .pp-back-link {
        display: inline-block;
        margin-bottom: 16px;
        color: #5B6172;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: color .15s;
    }
    .pp-back-link:hover { color: var(--pp-honey); }

    .pp-balance-heading {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        color: var(--pp-ink);
        margin: 0 0 20px;
    }

    .pp-steps { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 26px; }
    .pp-step { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 70px; }
    .pp-step-circle {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--pp-line); color: var(--pp-soft);
        font-weight: 700; font-size: 12.5px;
        transition: all .2s;
    }
    .pp-step-circle svg { width: 14px; height: 14px; }
    .pp-step-label { font-size: 11px; color: var(--pp-soft); font-weight: 600; }
    .pp-step-done .pp-step-circle { background: #E7F7EC; color: #2FA84F; }
    .pp-step-done .pp-step-label { color: #2FA84F; }
    .pp-step-active .pp-step-circle {
        background: linear-gradient(135deg, var(--pp-honey-light), var(--pp-honey));
        color: #fff;
        box-shadow: 0 4px 12px rgba(245,166,35,.45);
    }
    .pp-step-active .pp-step-label { color: var(--pp-ink); font-weight: 700; }
    .pp-step-line { width: 44px; height: 2.5px; background: var(--pp-line); border-radius: 99px; margin-bottom: 18px; }
    .pp-step-line-done { background: #A8DFBC; }

    .pp-card {
        background: #fff;
        border: 1px solid var(--pp-line);
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(20,20,43,0.05);
    }

    .pp-summary {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #F6F7FB;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 18px;
    }
    .pp-summary-photo {
        width: 56px; height: 56px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
        background: #F0EEE6;
    }
    .pp-summary-title {
        margin: 0;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--pp-ink);
        line-height: 1.3;
    }
    .pp-summary-meta {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--pp-soft);
        line-height: 1.4;
    }

    .pp-errors {
        background: #FDECEC;
        border: 1px solid #F5B5B5;
        color: #A3282E;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
    }
    .pp-errors ul { margin: 0; padding-left: 18px; }

    .pp-brand-row { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
    .pp-brand-icon { width: 44px; height: 44px; object-fit: contain; }
    .pp-brand-row h1 { margin: 0; font-size: 18px; color: var(--pp-ink); letter-spacing: -0.2px; }
    .pp-subtext { margin: 2px 0 0; font-size: 12.5px; color: var(--pp-soft); }

    .pp-amount-box {
        display: flex; align-items: center; justify-content: space-between;
        border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px;
        font-weight: 600;
    }
    .pp-amount-box strong { font-size: 19px; letter-spacing: -0.3px; }
    .pp-amount-gcash { background: #E7F0FF; color: #0B57D0; }
    .pp-amount-maya  { background: #E6F6EC; color: #1E7A3D; }
    .pp-amount-card  { background: #F0F1F6; color: var(--pp-ink); }
    .pp-amount-owner { background: #FFF6E9; color: #C77800; border: 1px dashed #F5C77E; }

    .pp-form { display: flex; flex-direction: column; gap: 14px; }
    .pp-field { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: #555; font-weight: 600; }
    .pp-field input {
        padding: 12px 14px;
        border: 1px solid #DADEE6;
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color .15s, box-shadow .15s;
    }
    .pp-field input:focus {
        outline: none;
        border-color: var(--pp-honey);
        box-shadow: 0 0 0 3px rgba(245,166,35,0.18);
    }
    .pp-field-row { display: flex; gap: 12px; }
    .pp-field-row .pp-field { flex: 1; }

    .pp-hint { margin: -4px 0 0; font-size: 11.5px; color: var(--pp-soft); }

    .pp-btn-confirm {
        display: block; width: 100%;
        padding: 14px; border-radius: 12px; border: none;
        color: #fff; font-size: 15px; font-weight: 800; cursor: pointer;
        font-family: inherit;
        box-shadow: 0 6px 16px rgba(20,20,43,0.14);
        transition: transform .12s, box-shadow .12s, filter .12s;
    }
    .pp-btn-confirm:hover:not(:disabled) { filter: brightness(1.05); transform: translateY(-1px); }
    .pp-btn-confirm:active:not(:disabled) { transform: translateY(0); }
    .pp-btn-confirm:disabled { opacity: 0.7; cursor: default; transform: none; }
    .pp-btn-gcash { background: #0B57D0; }
    .pp-btn-maya  { background: #1E7A3D; }
    .pp-btn-card  { background: #14142B; }

    .pp-secure-note {
        margin: 18px 0 0;
        text-align: center;
        font-size: 11.5px;
        color: var(--pp-soft);
    }
</style>
</head>
<body>

<header class="pp-nav">
    <div class="pp-nav-inner">
        <a class="pp-brand" href="/webprogg/Listings/listing.php">
            <span class="pp-brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>
                </svg>
            </span>
            <span class="pp-brand-text">Room<b>Hive</b> Checkout</span>
        </a>
        <span class="pp-secure-chip">
            &#128274; Secured
        </span>
    </div>
</header>

<main class="pp-page">

    <a href="<?php echo h($changeMethodUrl); ?>" class="pp-back-link">
        &#8592; Change payment method
    </a>

    <?php if ($paymentPurpose !== 'balance'): ?>
    <div class="pp-steps">
        <div class="pp-step pp-step-done">
            <span class="pp-step-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12.5 4.5 4.5L19 7.5"/>
                </svg>
            </span>
            <span class="pp-step-label">Details</span>
        </div>
        <div class="pp-step-line pp-step-line-done"></div>
        <div class="pp-step pp-step-active"><span class="pp-step-circle">2</span><span class="pp-step-label">Payment</span></div>
        <div class="pp-step-line"></div>
        <div class="pp-step"><span class="pp-step-circle">3</span><span class="pp-step-label">Confirmation</span></div>
    </div>
    <?php else: ?>
    <p class="pp-balance-heading">Paying remaining balance on booking #<?php echo h($bookingId); ?></p>
    <?php endif; ?>

    <div class="pp-card">

        <div class="pp-summary">
            <img
                class="pp-summary-photo"
                src="<?php
                    $cover = $listing['cover_photo'] ?? '';
                    echo h(preg_match('#^(https?://|/)#i', (string) $cover) || $cover === ''
                        ? ($cover !== '' ? $cover : '/webprogg/images/ListingPlaceholder.png')
                        : '/webprogg/' . ltrim((string) $cover, '/'));
                ?>"
                alt=""
                onerror="this.onerror=null;this.src='/webprogg/images/ListingPlaceholder.png';"
            >
            <div>
                <p class="pp-summary-title"><?php echo h($listing['title']); ?></p>
                <p class="pp-summary-meta"><?php echo $summaryMeta; ?></p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="pp-errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($paymentMethod === 'gcash'): ?>

            <!-- ===================== GCASH MOCK SCREEN ===================== -->
            <div class="pp-brand-row">
                <img src="/webprogg/images/GCashIcon.png" alt="GCash" class="pp-brand-icon">
                <div>
                    <h1>Pay with GCash</h1>
                    <p class="pp-subtext">You'll get a payment prompt in the GCash app.</p>
                </div>
            </div>

            <div class="pp-amount-box <?php echo ($isListingOwner && $paymentPurpose === 'reservation') ? 'pp-amount-owner' : 'pp-amount-gcash'; ?>">
                <span><?php echo h($chargeLabel); ?></span>
                <strong>&#8369; <?php echo h(number_format($amountDue, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="long_term" value="<?php echo $longTerm ? '1' : '0'; ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="payment_purpose" value="<?php echo h($paymentPurpose); ?>">
                <?php if ($paymentPurpose === 'balance'): ?>
                    <input type="hidden" name="booking_id" value="<?php echo h($bookingId); ?>">
                <?php endif; ?>
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>GCash Mobile Number</span>
                    <input
                        type="tel"
                        name="mobile_number"
                        placeholder="09XX XXX XXXX"
                        value="<?php echo h($_POST['mobile_number'] ?? ''); ?>"
                        maxlength="11"
                        inputmode="numeric"
                        required
                    >
                </label>

                <p class="pp-hint">Enter the mobile number linked to your GCash account. You'll be asked to approve this payment in-app.</p>

                <button type="submit" class="pp-btn-confirm pp-btn-gcash">
                    Send Payment Request
                </button>
            </form>

        <?php elseif ($paymentMethod === 'maya'): ?>

            <!-- ===================== MAYA MOCK SCREEN ===================== -->
            <div class="pp-brand-row">
                <img src="/webprogg/images/MayaIcon.png" alt="Maya" class="pp-brand-icon">
                <div>
                    <h1>Pay with Maya</h1>
                    <p class="pp-subtext">You'll get a payment prompt in the Maya app.</p>
                </div>
            </div>

            <div class="pp-amount-box <?php echo ($isListingOwner && $paymentPurpose === 'reservation') ? 'pp-amount-owner' : 'pp-amount-maya'; ?>">
                <span><?php echo h($chargeLabel); ?></span>
                <strong>&#8369; <?php echo h(number_format($amountDue, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="long_term" value="<?php echo $longTerm ? '1' : '0'; ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="payment_purpose" value="<?php echo h($paymentPurpose); ?>">
                <?php if ($paymentPurpose === 'balance'): ?>
                    <input type="hidden" name="booking_id" value="<?php echo h($bookingId); ?>">
                <?php endif; ?>
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>Maya Mobile Number</span>
                    <input
                        type="tel"
                        name="mobile_number"
                        placeholder="09XX XXX XXXX"
                        value="<?php echo h($_POST['mobile_number'] ?? ''); ?>"
                        maxlength="11"
                        inputmode="numeric"
                        required
                    >
                </label>

                <p class="pp-hint">Enter the mobile number linked to your Maya account. You'll be asked to approve this payment in-app.</p>

                <button type="submit" class="pp-btn-confirm pp-btn-maya">
                    Send Payment Request
                </button>
            </form>

        <?php else: ?>

            <!-- ===================== CARD MOCK SCREEN ===================== -->
            <div class="pp-brand-row">
                <img src="/webprogg/images/CardIcon.png" alt="Card" class="pp-brand-icon">
                <div>
                    <h1>Pay with Card</h1>
                    <p class="pp-subtext">Visa, Mastercard, JCB and more.</p>
                </div>
            </div>

            <div class="pp-amount-box <?php echo ($isListingOwner && $paymentPurpose === 'reservation') ? 'pp-amount-owner' : 'pp-amount-card'; ?>">
                <span><?php echo h($chargeLabel); ?></span>
                <strong>&#8369; <?php echo h(number_format($amountDue, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="long_term" value="<?php echo $longTerm ? '1' : '0'; ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="payment_purpose" value="<?php echo h($paymentPurpose); ?>">
                <?php if ($paymentPurpose === 'balance'): ?>
                    <input type="hidden" name="booking_id" value="<?php echo h($bookingId); ?>">
                <?php endif; ?>
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>Card Number</span>
                    <input
                        type="text"
                        name="card_number"
                        placeholder="1234 5678 9012 3456"
                        maxlength="19"
                        inputmode="numeric"
                        value="<?php echo h($_POST['card_number'] ?? ''); ?>"
                        required
                    >
                </label>

                <label class="pp-field">
                    <span>Name on Card</span>
                    <input
                        type="text"
                        name="card_name"
                        placeholder="Juan Dela Cruz"
                        value="<?php echo h($_POST['card_name'] ?? ''); ?>"
                        required
                    >
                </label>

                <div class="pp-field-row">
                    <label class="pp-field">
                        <span>Expiry (MM/YY)</span>
                        <input
                            type="text"
                            name="card_expiry"
                            placeholder="MM/YY"
                            maxlength="5"
                            inputmode="numeric"
                            value="<?php echo h($_POST['card_expiry'] ?? ''); ?>"
                            required
                        >
                    </label>
                    <label class="pp-field">
                        <span>CVV</span>
                        <input
                            type="password"
                            name="card_cvv"
                            placeholder="123"
                            maxlength="4"
                            inputmode="numeric"
                            value="<?php echo h($_POST['card_cvv'] ?? ''); ?>"
                            required
                        >
                    </label>
                </div>

                <button type="submit" class="pp-btn-confirm pp-btn-card">
                    Pay &#8369; <?php echo h(number_format($amountDue, 0)); ?>
                </button>
            </form>

        <?php endif; ?>

        <p class="pp-secure-note">&#128274; Payments are simulated for this demo — no real charge is made.</p>

    </div>

</main>

<script src="/webprogg/assets/javaScript.js"></script>

<script>
(function () {
    var form = document.querySelector('form.pp-form');
    if (!form) return;

    /* Submit — loading state + double-submit guard */
    form.addEventListener('submit', function () {
        var btn = form.querySelector('.pp-btn-confirm');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.textContent = 'Processing\u2026';
        }
    });

    /* Card number — auto-space every 4 digits */
    var cardNum = form.querySelector('input[name="card_number"]');
    if (cardNum) {
        cardNum.addEventListener('input', function () {
            var d = this.value.replace(/\D/g, '').slice(0, 16);
            this.value = d.replace(/(\d{4})(?=\d)/g, '$1 ');
        });
    }

    /* Expiry — auto MM/YY */
    var cardExp = form.querySelector('input[name="card_expiry"]');
    if (cardExp) {
        cardExp.addEventListener('input', function () {
            var d = this.value.replace(/\D/g, '').slice(0, 4);
            this.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        });
    }

    /* CVV — digits only */
    var cardCvv = form.querySelector('input[name="card_cvv"]');
    if (cardCvv) {
        cardCvv.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    }

    /* Mobile — digits only */
    var mobile = form.querySelector('input[name="mobile_number"]');
    if (mobile) {
        mobile.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });
    }
})();
</script>

</body>
</html>