<?php
/* =========================================================
   ROOMHIVE — PROCESS PAYMENT
   process-payment.php

   Flow: listingpayment.php (Step 2: choose method) -> THIS FILE
   -> booking-details.php (booking created).

   THIS FILE HAS TWO STAGES, controlled by the hidden "stage"
   field, so it doesn't need a second physical file:

   STAGE 1 — "review"  (arrives here from listingpayment.php)
     Shows a payment-method-specific mock screen:
       - gcash -> GCash mobile-prompt screen (blue)
       - maya  -> Maya mobile-prompt screen (green)
       - card  -> card entry form (dark navy)
     Each screen's form posts back to this SAME file with
     stage=confirm plus whatever that method collected.

   STAGE 2 — "confirm" (the method-specific form's submit)
     Validates input for the chosen method, inserts a row into
     `bookings` (status starts 'pending' — matches the actual
     schema enum('pending','confirmed','cancelled','completed')),
     then redirects to booking-details.php for that new booking.

   NOTE: There is no real payment gateway wired up here (no
   GCash/Maya/card-processor API calls) — this simulates the
   UX so the booking flow is complete end-to-end. Swap the
   "TODO: real gateway call" block for actual API calls when
   you're ready to integrate one.

   EXPIRY NOTE: the booking inserted here has paid_at set to
   NOW() at the same moment as booked_at, since this is the
   point a (simulated) charge actually succeeds. That's what
   exempts it from roomhive_expire_stale_bookings()'s 10-minute
   unpaid-hold sweep (see booking_helpers.php) — once a tenant
   gets this far, the listing stays reserved for them until the
   host explicitly accepts or cancels it, not on a timer.

   FIX (checkin/checkout field-name mismatch):
   listingpayment.php sends hidden fields named checkin_date /
   checkout_date. This file previously read $_POST['checkin']
   and $_POST['checkout'] — keys that never existed in the
   POST body — so $checkin/$checkout were always empty and
   every booking was inserted with null dates. All reads,
   the "change payment method" link, and the three
   method-specific forms below now consistently use
   checkin_date / checkout_date end-to-end.
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

/* -----------------------------------------------------
   INBOUND DATA (carried through both stages as hidden
   fields — listing_id/checkin_date/checkout_date/guests/
   payment_method never change once Stage 1 renders)
----------------------------------------------------- */
$listingId     = isset($_POST['listing_id']) && is_numeric($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
$checkin       = $_POST['checkin_date']  ?? '';
$checkout      = $_POST['checkout_date'] ?? '';
$guests        = $_POST['guests']   ?? '1';
$paymentMethod = $_POST['payment_method'] ?? '';
$stage         = $_POST['stage'] ?? 'review';

$validMethods = ['gcash', 'maya', 'card'];
if (!in_array($paymentMethod, $validMethods, true)) {
    header('Location: /webprogg/booking/listingpayment.php?listing_id=' . $listingId);
    exit;
}

$errors = [];

/* -----------------------------------------------------
   RELOAD THE LISTING (never trust price/title from the
   client — always re-fetch server-side)
----------------------------------------------------- */
$listingStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.price, l.user_id AS host_id, p.photo_path AS cover_photo
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

/* Same reservation fee shown as "Total Due Today" on
   listingpayment.php — keep these in sync, or better, move
   this to a shared config/settings table. */
$totalDueToday = 1000.00;

/* =========================================================
   STAGE 2 — CONFIRM: validate method-specific input, then
   create the booking and redirect.
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

        /* TODO: real gateway call goes here — call the GCash /
           Maya / card processor API with these details, and
           only proceed past this point once THEY confirm the
           charge succeeded. Right now we simulate an instant
           successful charge. */

        /* paid_at = NOW() alongside booked_at: this is the
           moment payment actually clears (simulated), so this
           hold is exempt from the 10-minute unpaid-hold expiry
           from here on — see booking_helpers.php. */
        $insertStmt = $pdo->prepare(
            "INSERT INTO bookings (listing_id, user_id, total, status, booked_at, paid_at, checkin_date, checkout_date, guests)
             VALUES (:listing_id, :user_id, :total, 'pending', NOW(), NOW(), :checkin_date, :checkout_date, :guests)"
        );
        $insertStmt->execute([
            'listing_id'    => $listingId,
            'user_id'       => $_SESSION['user_id'],
            'total'         => $totalDueToday,
            'checkin_date'  => $checkin ?: null,
            'checkout_date' => $checkout ?: null,
            'guests'        => $guests,
        ]);

        $newBookingId = (int) $pdo->lastInsertId();

        header('Location: /webprogg/booking/booking-details.php?id=' . $newBookingId . '&paid=1');
        exit;
    }

    /* Validation failed — fall through and re-render Stage 1
       for the same method, with the errors shown. */
    $stage = 'review';
}

$methodLabels = [
    'gcash' => 'GCash',
    'maya'  => 'Maya',
    'card'  => 'Credit/Debit Card',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Payment — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
</head>
<body>

<main class="pp-page">

    <a
        href="/webprogg/booking/listingpayment.php?listing_id=<?php echo h($listingId); ?>&checkin_date=<?php echo h($checkin); ?>&checkout_date=<?php echo h($checkout); ?>&guests=<?php echo h($guests); ?>"
        class="pp-back-link"
    >
        &#8592; Change payment method
    </a>

    <!-- STEP TRACKER (still Step 2 — this is "make payment" within it) -->
    <div class="pp-steps">
        <div class="pp-step pp-step-done"><span class="pp-step-circle">1</span><span class="pp-step-label">Details</span></div>
        <div class="pp-step-line pp-step-line-done"></div>
        <div class="pp-step pp-step-active"><span class="pp-step-circle">2</span><span class="pp-step-label">Payment</span></div>
        <div class="pp-step-line"></div>
        <div class="pp-step"><span class="pp-step-circle">3</span><span class="pp-step-label">Confirmation</span></div>
    </div>

    <div class="pp-card pp-card-<?php echo h($paymentMethod); ?>">

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

            <div class="pp-amount-box pp-amount-gcash">
                <span>Amount to Pay</span>
                <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>GCash Mobile Number</span>
                    <input
                        type="tel"
                        name="mobile_number"
                        placeholder="09XX XXX XXXX"
                        value="<?php echo h($_POST['mobile_number'] ?? ''); ?>"
                        maxlength="11"
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

            <div class="pp-amount-box pp-amount-maya">
                <span>Amount to Pay</span>
                <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>Maya Mobile Number</span>
                    <input
                        type="tel"
                        name="mobile_number"
                        placeholder="09XX XXX XXXX"
                        value="<?php echo h($_POST['mobile_number'] ?? ''); ?>"
                        maxlength="11"
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

            <div class="pp-amount-box pp-amount-card">
                <span>Amount to Pay</span>
                <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
            </div>

            <form method="POST" action="/webprogg/booking/process-payment.php" class="pp-form">
                <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                <input type="hidden" name="payment_method" value="<?php echo h($paymentMethod); ?>">
                <input type="hidden" name="stage" value="confirm">

                <label class="pp-field">
                    <span>Card Number</span>
                    <input
                        type="text"
                        name="card_number"
                        placeholder="1234 5678 9012 3456"
                        maxlength="19"
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
                            value="<?php echo h($_POST['card_cvv'] ?? ''); ?>"
                            required
                        >
                    </label>
                </div>

                <button type="submit" class="pp-btn-confirm pp-btn-card">
                    Pay &#8369; <?php echo h(number_format($totalDueToday, 0)); ?>
                </button>
            </form>

        <?php endif; ?>

        <p class="pp-secure-note">&#128274; Payments are simulated for this demo — no real charge is made.</p>

    </div>

</main>

<script src="/webprogg/assets/javaScript.js"></script>

<style>
    .pp-page { max-width: 480px; margin: 0 auto; padding: 24px 20px 60px; }

    .pp-back-link {
        display: inline-block;
        margin-bottom: 16px;
        color: #14142B;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
    }
    .pp-back-link:hover { text-decoration: underline; }

    .pp-steps { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px; }
    .pp-step { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .pp-step-circle {
        width: 26px; height: 26px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #EEF1F6; color: #999; font-weight: 700; font-size: 12px;
    }
    .pp-step-label { font-size: 11px; color: #999; font-weight: 600; }
    .pp-step-done .pp-step-circle, .pp-step-active .pp-step-circle { background: #FFA726; color: #fff; }
    .pp-step-done .pp-step-label, .pp-step-active .pp-step-label { color: #14142B; }
    .pp-step-line { width: 44px; height: 2px; background: #EEF1F6; }
    .pp-step-line-done { background: #FFA726; }

    .pp-card {
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 14px;
        padding: 24px;
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
    .pp-brand-row h1 { margin: 0; font-size: 18px; color: #14142B; }
    .pp-subtext { margin: 2px 0 0; font-size: 12.5px; color: #777; }

    .pp-amount-box {
        display: flex; align-items: center; justify-content: space-between;
        border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px;
    }
    .pp-amount-box strong { font-size: 18px; }
    .pp-amount-gcash { background: #E7F0FF; color: #0B57D0; }
    .pp-amount-maya  { background: #E6F6EC; color: #1E7A3D; }
    .pp-amount-card  { background: #EEF1F6; color: #14142B; }

    .pp-form { display: flex; flex-direction: column; gap: 14px; }
    .pp-field { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: #555; font-weight: 600; }
    .pp-field input {
        padding: 12px 14px;
        border: 1px solid #DADEE6;
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
    }
    .pp-field input:focus { outline: none; border-color: #FFA726; }
    .pp-field-row { display: flex; gap: 12px; }
    .pp-field-row .pp-field { flex: 1; }

    .pp-hint { margin: -4px 0 0; font-size: 11.5px; color: #999; }

    .pp-btn-confirm {
        display: block; width: 100%;
        padding: 14px; border-radius: 10px; border: none;
        color: #fff; font-size: 15px; font-weight: 700; cursor: pointer;
    }
    .pp-btn-gcash { background: #0B57D0; }
    .pp-btn-gcash:hover { background: #0A4CB8; }
    .pp-btn-maya  { background: #1E7A3D; }
    .pp-btn-maya:hover  { background: #196A34; }
    .pp-btn-card  { background: #14142B; }
    .pp-btn-card:hover  { background: #24243F; }

    .pp-secure-note { margin: 18px 0 0; text-align: center; font-size: 11.5px; color: #999; }
</style>

</body>
</html>