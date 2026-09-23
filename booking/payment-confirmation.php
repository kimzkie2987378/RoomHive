<?php
/* =========================================================
   ROOMHIVE — PAYMENT CONFIRMATION (Step 3) — OFFICIAL RECEIPT
   payment-confirmation.php

   Flow: process-payment.php -> THIS FILE -> userbookings.php

   STAMP LOGIC:
     - Guest pays only the reservation fee (e.g. ₱1,000 of a
       ₱1,200 total)  -> receipt stamps "ADVANCE PAID" (amber),
       shows Total Paid ₱1,000 and a "Balance Left ₱200" box.
     - Guest pays the last remaining amount -> receipt stamps
       "FULLY PAID" (green) and the balance box disappears.

   HOST ACCESS: the listing's host may also view this receipt.
   Host view: no countdown, no next-steps, primary button =
   "Back to Pending Tenants". Payer name always from DB.

   HOST SIGNATURE:
     - If a signature exists (uploads/signatures/booking-{id}.png,
       saved via the Sign & Accept pad or signed right here),
       the receipt shows "Signed & Accepted by Host".
     - If unsigned and the HOST is viewing, an inline signing
       pad appears on this page: draw -> save (sign-receipt.php)
       -> and if the booking is still pending, accept it
       (accept-booking.php). Guests never see the pad; the pad
       is excluded from print.

   BRANDING: the receipt uses the real logo image
   /webprogg/images/RoomHiveLogos.png (navbar + store header)
   instead of the old house SVG + text.

   SECURITY: access = booking's guest OR listing's host only.
   "amt" and "pm" are DISPLAY-ONLY.
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
   RESOLVE BOOKING (server-side source of truth)
----------------------------------------------------- */
 $bookingId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

 $wasBalancePayment = isset($_GET['balance']);

/* gu = guest (booking owner), u = host (listing owner) */
 $stmt = $pdo->prepare(
    "SELECT b.id, b.user_id, b.total, b.amount_paid, b.status,
            b.checkin_date, b.checkout_date, b.guests,
            b.booked_at, b.paid_at,
            l.id AS listing_id, l.title, l.location, l.exact_address,
            l.property_type, l.user_id AS host_id,
            u.name AS host_name, u.avatar_path AS host_avatar,
            gu.name AS guest_name,
            p.photo_path AS cover_photo
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = l.user_id
     JOIN users gu ON gu.id = b.user_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE b.id = :id
     LIMIT 1"
);
 $stmt->execute(['id' => $bookingId]);
 $booking = $stmt->fetch();

/* ACCESS: guest who owns the booking OR host who owns the listing */
 $viewerId      = (int) $_SESSION['user_id'];
 $isGuestViewer = ($booking !== false && (int) ($booking['user_id'] ?? 0) === $viewerId);
 $isHostViewer  = ($booking !== false && (int) ($booking['host_id'] ?? 0) === $viewerId);

if ($booking === false || (!$isGuestViewer && !$isHostViewer)) {
    header('Location: /webprogg/booking/userbookings.php');
    exit;
}

/* Host-only view = host opened the guest's receipt */
 $isHostOnlyView = ($isHostViewer && !$isGuestViewer);

/* -----------------------------------------------------
   FIGURES
----------------------------------------------------- */
 $total      = (float) $booking['total'];
 $amountPaid = (float) $booking['amount_paid'];
 $remaining  = round($total - $amountPaid, 2);

 $justPaidRaw = isset($_GET['amt']) && is_numeric($_GET['amt'])
    ? (float) $_GET['amt']
    : null;
 $justPaid = $justPaidRaw !== null
    ? min(max(0.0, $justPaidRaw), $amountPaid)
    : $amountPaid;

 $checkin    = $booking['checkin_date']  ?? null;
 $checkout   = $booking['checkout_date'] ?? null;
 $isLongTerm = ($checkin !== null && $checkout === null);

 $isFullyPaid      = ($remaining <= 0.005);
 $hasAnyPayment    = ($amountPaid > 0.005);
 $isAdvancePayment = (!$isFullyPaid && $hasAnyPayment);

/* -----------------------------------------------------
   RECEIPT FIELDS
----------------------------------------------------- */
 $paidAt   = !empty($booking['paid_at']) ? $booking['paid_at'] : $booking['booked_at'];
 $paidAtTs = $paidAt ? strtotime($paidAt) : time();
 $receiptNo = 'RH-' . str_pad((string) $booking['id'], 6, '0', STR_PAD_LEFT)
           . '-' . date('ymd', $paidAtTs);

 $paidDate = date('M j, Y', $paidAtTs);
 $paidTime = date('g:i A', $paidAtTs);

 $paymentMethodRaw    = $_GET['pm'] ?? '';
 $paymentMethodLabels = [
    'gcash' => 'GCash',
    'maya'  => 'Maya',
    'card'  => 'Credit / Debit Card',
];
 $paymentMethodLabel = $paymentMethodLabels[$paymentMethodRaw] ?? 'Online Payment';

/* Payer from DB — correct even when the HOST is viewing */
 $payerName = !empty($booking['guest_name'])
    ? $booking['guest_name']
    : ($_SESSION['user_name'] ?? 'Guest');

/* -----------------------------------------------------
   HOST SIGNATURE — saved by sign-receipt.php
----------------------------------------------------- */
 $sigDir  = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/signatures';
 $sigFile = $sigDir . '/booking-' . $bookingId . '.png';
 $sigJson = $sigDir . '/booking-' . $bookingId . '.json';

 $hostSigned = file_exists($sigFile);
 $sigUrl     = '';
 $sigDate    = '';

if ($hostSigned) {
    $sigUrl = '/webprogg/uploads/signatures/booking-' . $bookingId . '.png?v=' . filemtime($sigFile);
    $sigTs  = filemtime($sigFile);
    if (is_file($sigJson)) {
        $j = json_decode((string) file_get_contents($sigJson), true);
        if (!empty($j['signed_at'])) {
            $t = strtotime($j['signed_at']);
            if ($t) { $sigTs = $t; }
        }
    }
    $sigDate = date('M j, Y \a\t g:i A', $sigTs);
}

/* Host can sign right on this receipt while it is unsigned */
 $isPendingBooking = ($booking['status'] === 'pending');
 $canHostSign = ($isHostViewer && !$hostSigned
    && in_array($booking['status'], ['pending', 'confirmed'], true));

/* -----------------------------------------------------
   PRE-COMPUTED DISPLAY STRINGS (echoed RAW, never h())
----------------------------------------------------- */

/* STAMP — "ADVANCE PAID" (amber) or "FULLY PAID" (green) */
if ($isFullyPaid) {
    $stampText  = 'Fully Paid';
    $stampClass = '';
} elseif ($hasAnyPayment) {
    $stampText  = 'Advance Paid';
    $stampClass = 'rc-stamp-partial';
} else {
    $stampText  = 'Unpaid';
    $stampClass = 'rc-stamp-partial';
}

/* Caption under the stamp */
if ($isFullyPaid) {
    $paymentStatusLine = 'ALL &#8369;' . number_format($total, 2) . ' SETTLED';
} elseif ($isAdvancePayment) {
    $paymentStatusLine = '&#8369;' . number_format($amountPaid, 2)
                       . ' PAID &middot; &#8369;' . number_format($remaining, 2) . ' LEFT TO PAY';
} else {
    $paymentStatusLine = 'NO PAYMENT YET &mdash; &#8369;' . number_format($remaining, 2) . ' LEFT';
}

/* Success strip */
if ($isHostOnlyView) {
    $successTitle = 'Guest Payment Receipt';
    $successText  = 'Official receipt issued to ' . h($payerName) . ' for this booking.';
} elseif ($isFullyPaid) {
    $successTitle = $wasBalancePayment ? 'Balance Settled!' : 'Fully Paid!';
    $successText  = $wasBalancePayment
        ? 'The booking is now fully paid.'
        : 'This booking is fully paid.';
} elseif ($isAdvancePayment) {
    $successTitle = 'Advance Payment Received!';
    $successText  = '&#8369;' . number_format($justPaid, 2)
                 . ' advance payment received &mdash; balance left: &#8369;'
                 . number_format($remaining, 2) . '.';
} else {
    $successTitle = 'Payment Successful!';
    $successText  = 'Your official receipt is below.';
}

/* Payment Type row in the receipt meta */
if ($isFullyPaid) {
    $paymentTypeLabel = 'Fully Paid';
} elseif ($isAdvancePayment) {
    $paymentTypeLabel = 'Advance Paid (Partial)';
} else {
    $paymentTypeLabel = 'Not Paid Yet';
}

/* Line item (reservation vs balance) */
if ($wasBalancePayment) {
    $itemName = 'Balance Payment';
    $itemDesc = 'Settlement of remaining balance for booking #'
              . str_pad((string) $booking['id'], 6, '0', STR_PAD_LEFT);
    $nowLabel = 'Paid This Transaction';
} else {
    $itemName = 'Reservation Fee';
    $itemDesc = 'Advance payment to lock in your booking inquiry';
    $nowLabel = 'Advance Payment (Now)';
}

/* Date range appended to the item description */
if ($checkin && $checkout) {
    $dateRangeText = ' &middot; ' . date('M j', strtotime($checkin))
                   . ' &rarr; ' . date('M j, Y', strtotime($checkout));
} elseif ($isLongTerm) {
    $dateRangeText = ' &middot; Move-in ' . date('M j, Y', strtotime($checkin)) . ' &middot; Long Term';
} else {
    $dateRangeText = '';
}

/* Footer thank-you line */
if ($isAdvancePayment) {
    $thanksLine = 'This receipt is proof of your advance payment &mdash; please show it to your host.';
} else {
    $thanksLine = 'This receipt serves as proof of your payment.';
}

/* "Host will see" sentence (guest view only, may be empty) */
if ($isHostOnlyView) {
    $hostSeeLine = '';
} elseif ($isAdvancePayment) {
    $hostSeeLine = 'They will see your &#8369;' . number_format($amountPaid, 2) . ' advance payment on this booking.';
} elseif ($isFullyPaid && $hasAnyPayment) {
    $hostSeeLine = "They'll see this booking is already fully paid.";
} else {
    $hostSeeLine = '';
}

/* Stay line */
if ($checkin && $checkout) {
    $stayLine = 'Your stay runs ' . date('M j', strtotime($checkin))
              . ' &rarr; ' . date('M j, Y', strtotime($checkout)) . '.';
} elseif ($isLongTerm) {
    $stayLine = 'Your long-term stay starts ' . date('l, F j, Y', strtotime($checkin)) . '.';
} else {
    $stayLine = 'Your dates are locked in once the host accepts.';
}

/* Visibility flags — the ONLY if/endif blocks in the HTML */
 $showCountdown      = !$isHostOnlyView;
 $showNextSteps      = !$isHostOnlyView;
 $showDetailsLink    = !$isHostOnlyView;
 $showPreviouslyPaid = (($amountPaid - $justPaid) > 0.005);
 $showBalanceBox     = !$isFullyPaid;
 $showSettleStep     = (!$isFullyPaid && !$isHostOnlyView);
 $enjoyNum           = $isFullyPaid ? '2' : '3';

/* Primary action button */
 $primaryActionUrl   = $isHostOnlyView ? '/webprogg/booking/pendingtenants.php' : '/webprogg/booking/userbookings.php';
 $primaryActionLabel = $isHostOnlyView ? 'Back to Pending Tenants' : 'Go to My Bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Official Receipt <?php echo h($receiptNo); ?> — RoomHive</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<style>
    :root {
        --rc-ink: #14142B;
        --rc-soft: #8B93A6;
        --rc-line: #EEF1F6;
        --rc-honey: #F5A623;
        --rc-honey-light: #FFB94E;
        --rc-green: #2FA84F;
        --rc-mono: 'JetBrains Mono', 'Courier New', monospace;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #F7F8FA;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--rc-ink);
    }

    .rc-nav {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #fff;
        border-bottom: 1px solid var(--rc-line);
    }
    .rc-nav-inner {
        max-width: 560px;
        margin: 0 auto;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .rc-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }

    /* REAL LOGO — navbar brand */
    .rc-brand-logo {
        height: 30px;
        width: auto;
        max-width: 130px;
        object-fit: contain;
        display: block;
    }

    .rc-page { max-width: 460px; margin: 0 auto; padding: 26px 20px 70px; }

    .rc-success {
        display: flex;
        align-items: center;
        gap: 14px;
        background: linear-gradient(135deg, #43C868, var(--rc-green));
        border-radius: 16px;
        padding: 16px 18px;
        margin-bottom: 14px;
        color: #fff;
        box-shadow: 0 10px 24px rgba(47, 168, 79, 0.3);
        animation: rcPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes rcPop {
        from { transform: scale(0.94); opacity: 0; }
        to   { transform: scale(1);    opacity: 1; }
    }
    .rc-success-check {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.22);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .rc-success-check svg { width: 24px; height: 24px; }
    .rc-success-check svg path {
        stroke: #fff; stroke-width: 3;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none;
        stroke-dasharray: 40; stroke-dashoffset: 40;
        animation: rcDraw 0.45s ease 0.3s forwards;
    }
    @keyframes rcDraw { to { stroke-dashoffset: 0; } }

    .rc-success h1 {
        margin: 0 0 2px;
        font-size: 16.5px;
        font-weight: 800;
        letter-spacing: -0.2px;
    }
    .rc-success p {
        margin: 0;
        font-size: 12px;
        opacity: 0.92;
        line-height: 1.4;
    }

    .rc-autoredirect {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        background: #EAF9F0;
        border: 1px solid #C9EDD7;
        border-radius: 12px;
        padding: 11px 14px;
        margin-bottom: 18px;
        font-size: 12.5px;
        color: #14532D;
        animation: rcRise 0.4s ease 0.25s both;
    }
    .rc-autoredirect b { font-weight: 800; }
    .rc-ar-count { font-family: var(--rc-mono); font-weight: 700; }
    .rc-ar-actions { display: flex; gap: 8px; }
    .rc-ar-btn {
        font-family: inherit;
        font-size: 11.5px;
        font-weight: 700;
        border-radius: 8px;
        padding: 7px 12px;
        cursor: pointer;
        border: 1.5px solid #C9EDD7;
        background: #fff;
        color: #14532D;
        transition: background .15s, border-color .15s, transform .15s;
    }
    .rc-ar-btn:hover { border-color: var(--rc-green); transform: translateY(-1px); }
    .rc-ar-go {
        background: linear-gradient(135deg, #43C868, var(--rc-green));
        border: none;
        color: #fff;
    }
    .rc-ar-cancelled { opacity: 0.55; }

    .rc-receipt {
        position: relative;
        background: #fff;
        padding: 26px 24px 30px;
        filter: drop-shadow(0 10px 26px rgba(20, 20, 43, 0.12));
        animation: rcRise 0.5s ease 0.15s both;
    }
    @keyframes rcRise {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .rc-receipt::before,
    .rc-receipt::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        height: 10px;
        background-size: 18px 10px;
        background-repeat: repeat-x;
    }
    .rc-receipt::before {
        top: -9px;
        background-image:
            linear-gradient(45deg,  transparent 33.333%, #fff 33.333%, #fff 66.667%, transparent 66.667%),
            linear-gradient(-45deg, transparent 33.333%, #fff 33.333%, #fff 66.667%, transparent 66.667%);
    }
    .rc-receipt::after {
        bottom: -9px;
        background-image:
            linear-gradient(45deg,  #fff 33.333%, transparent 33.333%, transparent 66.667%, #fff 66.667%),
            linear-gradient(-45deg, #fff 33.333%, transparent 33.333%, transparent 66.667%, #fff 66.667%);
    }

    .rc-head { text-align: center; padding-bottom: 16px; }

    /* REAL LOGO — receipt store header */
    .rc-store-logo {
        display: block;
        max-height: 70px;
        max-width: 200px;
        margin: 0 auto 8px;
        object-fit: contain;
    }

    .rc-store-tag {
        margin: 3px 0 0;
        font-size: 10.5px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--rc-soft);
    }
    .rc-store-contact {
        margin: 6px 0 0;
        font-family: var(--rc-mono);
        font-size: 10px;
        color: var(--rc-soft);
        line-height: 1.6;
    }
    .rc-title {
        display: inline-block;
        margin-top: 14px;
        padding: 6px 18px;
        border-top: 1.5px dashed var(--rc-line);
        border-bottom: 1.5px dashed var(--rc-line);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: var(--rc-ink);
    }

    .rc-meta {
        padding: 14px 0;
        border-bottom: 1.5px dashed var(--rc-line);
        font-family: var(--rc-mono);
        font-size: 11px;
        line-height: 1.9;
        color: #4A5160;
    }
    .rc-meta div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }
    .rc-meta span:first-child { color: var(--rc-soft); }
    .rc-meta span:last-child {
        font-weight: 600;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .rc-billed {
        padding: 14px 0;
        border-bottom: 1.5px dashed var(--rc-line);
    }
    .rc-billed-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--rc-soft);
        margin: 0 0 6px;
    }
    .rc-billed-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
        font-size: 13px;
        line-height: 1.9;
    }
    .rc-billed-row span:first-child { color: #5B6172; }
    .rc-billed-row strong { font-weight: 700; text-align: right; }

    .rc-items-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--rc-soft);
        margin: 16px 0 10px;
    }
    .rc-item {
        display: flex;
        gap: 10px;
        padding: 9px 0;
    }
    .rc-item-qty {
        font-family: var(--rc-mono);
        font-size: 11px;
        font-weight: 700;
        color: var(--rc-honey);
        padding-top: 2px;
        flex-shrink: 0;
    }
    .rc-item-body { flex: 1; min-width: 0; }
    .rc-item-name {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.35;
    }
    .rc-item-desc {
        margin: 2px 0 0;
        font-size: 11.5px;
        color: var(--rc-soft);
        line-height: 1.5;
    }
    .rc-item-amt {
        font-family: var(--rc-mono);
        font-size: 12.5px;
        font-weight: 700;
        padding-top: 2px;
        flex-shrink: 0;
    }

    .rc-line {
        display: flex;
        align-items: baseline;
        gap: 8px;
        font-size: 12.5px;
        padding: 5px 0;
    }
    .rc-line-label { color: #5B6172; flex-shrink: 0; }
    .rc-line-dots {
        flex: 1;
        border-bottom: 1.5px dotted #C9CDD6;
        transform: translateY(-3px);
        min-width: 20px;
    }
    .rc-line-val {
        font-family: var(--rc-mono);
        font-weight: 600;
        flex-shrink: 0;
    }

    .rc-totals { padding: 14px 0 4px; }
    .rc-total-grand {
        display: flex;
        align-items: baseline;
        gap: 8px;
        padding: 4px 0 2px;
    }
    .rc-total-grand .rc-line-label {
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--rc-ink);
    }
    .rc-total-grand .rc-line-val {
        font-size: 19px;
        font-weight: 700;
        color: var(--rc-ink);
    }
    .rc-total-due {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #FFF6E9;
        border: 1px dashed #F5C77E;
        border-radius: 10px;
        padding: 11px 14px;
        margin-top: 10px;
        font-size: 12.5px;
        font-weight: 700;
        color: #8A5A10;
    }
    .rc-total-due strong {
        font-family: var(--rc-mono);
        font-size: 16px;
        color: #C77800;
    }
    .rc-total-due small {
        display: block;
        font-weight: 600;
        font-size: 10px;
        color: #B98A2F;
        letter-spacing: .02em;
        margin-top: 2px;
    }

    /* PAID STAMP — fits the longer "ADVANCE PAID" text */
    .rc-stamp-wrap {
        position: relative;
        text-align: center;
        padding: 20px 0 14px;
    }
    .rc-stamp {
        display: inline-block;
        position: relative;
        padding: 8px 20px;
        border: 3px solid var(--rc-green);
        border-radius: 8px;
        color: var(--rc-green);
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        transform: rotate(-7deg);
        opacity: 0.85;
        -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='40'%3E%3Cfilter id='n'%3E%3CfeTurbulence baseFrequency='0.85' numOctaves='2'/%3E%3CfeColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 0.92 0.08'/%3E%3C/filter%3E%3Crect width='120' height='40' filter='url(%23n)'/%3E%3C/svg%3E");
        mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='40'%3E%3Cfilter id='n'%3E%3CfeTurbulence baseFrequency='0.85' numOctaves='2'/%3E%3CfeColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 0.92 0.08'/%3E%3C/filter%3E%3Crect width='120' height='40' filter='url(%23n)'/%3E%3C/svg%3E");
    }
    .rc-stamp-partial {
        border-color: var(--rc-honey);
        color: #C77800;
    }
    .rc-stamp-caption {
        margin: 10px 0 0;
        font-family: var(--rc-mono);
        font-size: 10.5px;
        color: var(--rc-soft);
        letter-spacing: 0.04em;
    }

    /* HOST SIGNATURE block */
    .rc-sig {
        text-align: center;
        padding: 14px 0 6px;
        border-top: 1.5px dashed var(--rc-line);
    }
    .rc-sig-label {
        margin: 0 0 8px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--rc-soft);
    }
    .rc-sig-img {
        height: 64px;
        max-width: 80%;
        object-fit: contain;
        border-bottom: 1.5px solid #C9CDD6;
        padding: 0 10px 2px;
    }
    .rc-sig-name {
        margin: 6px 0 0;
        font-family: var(--rc-mono);
        font-size: 11px;
        font-weight: 700;
        color: var(--rc-ink);
    }
    .rc-sig-date {
        margin: 2px 0 0;
        font-family: var(--rc-mono);
        font-size: 9.5px;
        color: var(--rc-soft);
    }
    .rc-sig-empty {
        font-family: var(--rc-mono);
        font-size: 11px;
        color: #AAB1BE;
        border: 1.5px dashed #E3E7EF;
        border-radius: 10px;
        padding: 14px 10px;
    }

    /* HOST SIGNING PAD (on the receipt, host only) */
    .rc-signpad {
        margin-top: 12px;
        text-align: left;
        background: #FAFAFD;
        border: 1px solid var(--rc-line);
        border-radius: 12px;
        padding: 14px;
    }
    .rc-signpad-title {
        margin: 0 0 2px;
        font-size: 12.5px;
        font-weight: 800;
        color: var(--rc-ink);
    }
    .rc-signpad-sub {
        margin: 0 0 10px;
        font-size: 11.5px;
        color: var(--rc-soft);
        line-height: 1.5;
    }
    .rc-sign-canvas-wrap { position: relative; }
    #rcSignCanvas {
        display: block;
        width: 100%;
        height: 180px;
        background: #fff;
        border: 1.5px dashed #C9CDD6;
        border-radius: 10px;
        touch-action: none;
        cursor: crosshair;
    }
    .rc-sign-hint {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        color: #AAB1BE;
        font-size: 12.5px;
        font-weight: 600;
        transition: opacity .2s ease;
    }
    .rc-sign-hint.hidden { opacity: 0; }
    .rc-sign-tools {
        display: flex;
        justify-content: flex-end;
        margin: 8px 0 10px;
    }
    #rcSignSave { width: 100%; }
    #rcSignSave[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    .rc-footer {
        text-align: center;
        padding-top: 14px;
        border-top: 1.5px dashed var(--rc-line);
    }
    .rc-barcode {
        width: 210px;
        height: 44px;
        margin: 0 auto 8px;
        background: repeating-linear-gradient(
            90deg,
            var(--rc-ink) 0 2px, transparent 2px 4px,
            var(--rc-ink) 4px 5px, transparent 5px 9px,
            var(--rc-ink) 9px 12px, transparent 12px 14px,
            var(--rc-ink) 14px 15px, transparent 15px 19px,
            var(--rc-ink) 19px 21px, transparent 21px 24px,
            var(--rc-ink) 24px 27px, transparent 27px 29px
        );
        border-radius: 2px;
    }
    .rc-barcode-no {
        font-family: var(--rc-mono);
        font-size: 11px;
        letter-spacing: 0.24em;
        color: #4A5160;
        font-weight: 600;
    }
    .rc-thanks {
        margin: 12px 0 0;
        font-size: 11px;
        color: var(--rc-soft);
        line-height: 1.7;
    }
    .rc-thanks b { color: var(--rc-ink); }
    .rc-note {
        margin: 8px 0 0;
        font-family: var(--rc-mono);
        font-size: 9.5px;
        color: #AAB1BE;
        line-height: 1.6;
    }

    .rc-next {
        background: #fff;
        border: 1px solid var(--rc-line);
        border-radius: 18px;
        padding: 20px;
        margin-top: 22px;
        animation: rcRise 0.5s ease 0.3s both;
    }
    .rc-next-title {
        display: flex; align-items: center; gap: 8px;
        font-size: 14px; font-weight: 700;
        margin: 0 0 14px;
    }
    .rc-next-title svg { width: 17px; height: 17px; color: var(--rc-honey); }
    .rc-next-item {
        display: flex; gap: 12px;
        position: relative;
        padding-bottom: 16px;
    }
    .rc-next-item:last-child { padding-bottom: 0; }
    .rc-next-item::before {
        content: "";
        position: absolute;
        left: 11px; top: 26px; bottom: 2px;
        width: 2px; background: var(--rc-line);
    }
    .rc-next-item:last-child::before { display: none; }
    .rc-next-num {
        width: 24px; height: 24px;
        border-radius: 50%;
        background: #FFF6E9;
        border: 1.5px solid var(--rc-honey);
        color: #C77800;
        font-size: 11.5px; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        position: relative; z-index: 1;
    }
    .rc-next-item strong { display: block; font-size: 12.5px; margin: 2px 0 2px; }
    .rc-next-item p { margin: 0; font-size: 12px; color: var(--rc-soft); line-height: 1.5; }

    .rc-actions {
        display: flex; flex-direction: column; gap: 10px;
        margin-top: 22px;
        animation: rcRise 0.5s ease 0.4s both;
    }
    .rc-btn-primary {
        display: block; width: 100%; padding: 14px;
        border-radius: 14px; border: none;
        background: linear-gradient(135deg, var(--rc-honey-light), var(--rc-honey));
        color: #fff; font-size: 14.5px; font-weight: 800;
        cursor: pointer; text-align: center; text-decoration: none;
        font-family: inherit;
        box-shadow: 0 8px 20px rgba(245,166,35,0.4);
        transition: transform .15s, box-shadow .15s, filter .15s;
    }
    .rc-btn-primary:hover { filter: brightness(1.04); transform: translateY(-2px); }
    .rc-btn-outline {
        display: block; width: 100%; padding: 13px;
        border-radius: 14px; border: 1.5px solid var(--rc-line);
        background: #fff; color: var(--rc-ink);
        font-size: 13px; font-weight: 700;
        text-align: center; text-decoration: none;
        transition: border-color .15s, color .15s;
    }
    .rc-btn-outline:hover { border-color: var(--rc-honey); color: #C77800; }

    /* PRINT STYLES — receipt only */
    @media print {
        body { background: #fff; }
        .rc-nav,
        .rc-autoredirect,
        .rc-actions,
        .rc-next,
        .rc-success,
        .rc-signpad { display: none !important; }
        .rc-page { max-width: 100%; padding: 10mm 0; }
        .rc-receipt { filter: none; }
        .rc-receipt::before,
        .rc-receipt::after { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .rc-success, .rc-success-check svg path,
        .rc-receipt, .rc-next, .rc-actions,
        .rc-autoredirect { animation: none !important; }
        .rc-success-check svg path { stroke-dashoffset: 0; }
    }
</style>
</head>
<body>

<header class="rc-nav">
    <div class="rc-nav-inner">
        <a class="rc-brand" href="/webprogg/Listings/listing.php">
            <img class="rc-brand-logo" src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive">
        </a>
        <button type="button" class="rc-btn-outline" style="width:auto;padding:8px 16px;" onclick="window.print()">
            &#128424; Print
        </button>
    </div>
</header>

<main class="rc-page">

    <!-- SUCCESS STRIP -->
    <div class="rc-success">
        <span class="rc-success-check">
            <svg viewBox="0 0 24 24">
                <path d="m5.5 12.5 4.5 4.5 8.5-9.5"/>
            </svg>
        </span>
        <div>
            <h1><?php echo $successTitle; ?></h1>
            <p><?php echo $successText; ?></p>
        </div>
    </div>

    <?php if ($showCountdown): ?>
    <!-- AUTO-REDIRECT COUNTDOWN BAR (guest only) -->
    <div class="rc-autoredirect" id="rcAutoRedirect">
        <span>
            Taking you to <b>My Bookings</b> in
            <span class="rc-ar-count"><b id="rcCountdown">10</b>s</span>
        </span>
        <span class="rc-ar-actions">
            <button type="button" class="rc-ar-btn rc-ar-go" id="rcArNow">
                Go now &rarr;
            </button>
            <button type="button" class="rc-ar-btn" id="rcArCancel">
                Stay on this page
            </button>
        </span>
    </div>
    <?php endif; ?>

    <!-- OFFICIAL RECEIPT (identical for guest and host) -->
    <div class="rc-receipt" id="rc-receipt">

        <!-- STORE HEADER — real logo image -->
        <div class="rc-head">
            <img class="rc-store-logo" src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive">
            <p class="rc-store-tag">Find your next space</p>
            <p class="rc-store-contact">
                0917 156 3974<br>
                iamroomhivehost@gmail.com
            </p>
            <span class="rc-title">Official Receipt</span>
        </div>

        <!-- META -->
        <div class="rc-meta">
            <div><span>Receipt No.</span><span><?php echo h($receiptNo); ?></span></div>
            <div><span>Booking Ref.</span><span>#<?php echo h(str_pad((string) $booking['id'], 6, '0', STR_PAD_LEFT)); ?></span></div>
            <div><span>Date Paid</span><span><?php echo h($paidDate); ?></span></div>
            <div><span>Time Paid</span><span><?php echo h($paidTime); ?></span></div>
            <div><span>Payment Method</span><span><?php echo h($paymentMethodLabel); ?></span></div>
            <div><span>Payment Type</span><span><?php echo $paymentTypeLabel; ?></span></div>
            <div><span>Host Signature</span><span><?php echo $hostSigned ? 'Signed &middot; ' . h($sigDate) : 'Pending'; ?></span></div>
        </div>

        <!-- BILLED TO -->
        <div class="rc-billed">
            <p class="rc-billed-label">Billed To</p>
            <div class="rc-billed-row">
                <span>Payer</span>
                <strong><?php echo h($payerName); ?></strong>
            </div>
            <div class="rc-billed-row">
                <span>Host</span>
                <strong><?php echo h($booking['host_name']); ?></strong>
            </div>
        </div>

        <!-- ITEMS -->
        <p class="rc-items-label">Description</p>

        <div class="rc-item">
            <span class="rc-item-qty">1&times;</span>
            <div class="rc-item-body">
                <p class="rc-item-name"><?php echo h($booking['title']); ?></p>
                <p class="rc-item-desc">
                    <?php echo h($booking['location']); ?><?php echo $dateRangeText; ?>
                    &middot; <?php echo h((string) ($booking['guests'] ?? '1')); ?> guest(s)
                </p>
            </div>
            <span class="rc-item-amt">&#8369;<?php echo h(number_format($total, 2)); ?></span>
        </div>

        <div class="rc-item">
            <span class="rc-item-qty">1&times;</span>
            <div class="rc-item-body">
                <p class="rc-item-name"><?php echo $itemName; ?></p>
                <p class="rc-item-desc"><?php echo $itemDesc; ?></p>
            </div>
            <span class="rc-item-amt">&#8369;<?php echo h(number_format($justPaid, 2)); ?></span>
        </div>

        <!-- TOTALS -->
        <div class="rc-totals">
            <div class="rc-line">
                <span class="rc-line-label">Booking Total</span>
                <span class="rc-line-dots"></span>
                <span class="rc-line-val">&#8369;<?php echo h(number_format($total, 2)); ?></span>
            </div>

            <?php if ($showPreviouslyPaid): ?>
            <div class="rc-line">
                <span class="rc-line-label">Previously Paid</span>
                <span class="rc-line-dots"></span>
                <span class="rc-line-val">&#8369;<?php echo h(number_format($amountPaid - $justPaid, 2)); ?></span>
            </div>
            <?php endif; ?>

            <div class="rc-line">
                <span class="rc-line-label"><?php echo $nowLabel; ?></span>
                <span class="rc-line-dots"></span>
                <span class="rc-line-val">&#8369;<?php echo h(number_format($justPaid, 2)); ?></span>
            </div>

            <div class="rc-total-grand">
                <span class="rc-line-label">Total Paid</span>
                <span class="rc-line-dots"></span>
                <span class="rc-line-val">&#8369;<?php echo h(number_format($amountPaid, 2)); ?></span>
            </div>

            <?php if ($showBalanceBox): ?>
            <div class="rc-total-due">
                <div>
                    <span>Balance Left</span>
                    <small>Advance payment &mdash; show this receipt to your host</small>
                </div>
                <strong>&#8369; <?php echo h(number_format($remaining, 2)); ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <!-- PAID STAMP — "ADVANCE PAID" (amber) or "FULLY PAID" (green) -->
        <div class="rc-stamp-wrap">
            <span class="rc-stamp <?php echo $stampClass; ?>">
                <?php echo $stampText; ?>
            </span>
            <p class="rc-stamp-caption"><?php echo $paymentStatusLine; ?></p>
        </div>

        <!-- HOST SIGNATURE — saved signature, or the on-receipt
             signing pad when the HOST is viewing -->
        <div class="rc-sig">
            <?php if ($hostSigned): ?>
                <p class="rc-sig-label">Signed &amp; Accepted by Host</p>
                <img class="rc-sig-img" src="<?php echo h($sigUrl); ?>" alt="Host signature">
                <p class="rc-sig-name"><?php echo h($booking['host_name']); ?></p>
                <p class="rc-sig-date">Signed on <?php echo h($sigDate); ?></p>
            <?php else: ?>
                <p class="rc-sig-label">Host Signature</p>
                <div class="rc-sig-empty">Awaiting host signature</div>

                <?php if ($canHostSign): ?>
                <!-- ON-RECEIPT SIGNING PAD (host only, hidden on print) -->
                <div class="rc-signpad" id="rcSignPad">
                    <p class="rc-signpad-title">You're viewing this receipt as the host.</p>
                    <p class="rc-signpad-sub">
                        Draw your signature below and press
                        <b><?php echo $isPendingBooking ? 'Sign &amp; Accept Application' : 'Sign Receipt'; ?></b>.
                        <?php if ($isPendingBooking): ?>
                            Signing also accepts this application.
                        <?php endif; ?>
                    </p>

                    <div class="rc-sign-canvas-wrap">
                        <canvas id="rcSignCanvas"></canvas>
                        <span class="rc-sign-hint" id="rcSignHint">&#9996; Draw your signature here</span>
                    </div>

                    <div class="rc-sign-tools">
                        <button type="button" class="rc-ar-btn" id="rcSignClear">Clear</button>
                    </div>

                    <button type="button" class="rc-btn-primary" id="rcSignSave" disabled>
                        <?php echo $isPendingBooking ? 'Sign &amp; Accept Application' : 'Sign Receipt'; ?>
                    </button>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- FOOTER -->
        <div class="rc-footer">
            <div class="rc-barcode" aria-hidden="true"></div>
            <div class="rc-barcode-no"><?php echo h($receiptNo); ?></div>
            <p class="rc-thanks">
                Thank you for choosing <b>RoomHive</b>.<br>
                <?php echo $thanksLine; ?>
            </p>
            <p class="rc-note">
                This is a system-generated receipt. No signature required.<br>
                Keep this for your records.
            </p>
        </div>

    </div>

    <?php if ($showNextSteps): ?>
    <!-- WHAT HAPPENS NEXT (guest only) -->
    <div class="rc-next">
        <h2 class="rc-next-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
            </svg>
            What happens next
        </h2>
        <div class="rc-next-item">
            <span class="rc-next-num">1</span>
            <div>
                <strong>Host reviews your booking</strong>
                <p><?php echo h($booking['host_name']); ?> will accept or decline, usually within a few hours. <?php echo $hostSeeLine; ?></p>
            </div>
        </div>

        <?php if ($showSettleStep): ?>
        <div class="rc-next-item">
            <span class="rc-next-num">2</span>
            <div>
                <strong>Settle the remaining balance</strong>
                <p>Pay the &#8369;<?php echo h(number_format($remaining, 2)); ?> left anytime via My Bookings &rarr; Booking Details.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="rc-next-item">
            <span class="rc-next-num"><?php echo $enjoyNum; ?></span>
            <div>
                <strong><?php echo $isLongTerm ? 'Move in on your date' : 'Enjoy your stay'; ?></strong>
                <p><?php echo $stayLine; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTIONS -->
    <div class="rc-actions">
        <button type="button" class="rc-btn-primary" onclick="window.print()">
            &#128424; Print / Save Receipt
        </button>
        <a href="<?php echo h($primaryActionUrl); ?>" class="rc-btn-outline">
            <?php echo h($primaryActionLabel); ?>
        </a>
        <?php if ($showDetailsLink): ?>
        <a href="/webprogg/booking/booking-details.php?id=<?php echo (int) $booking['id']; ?>" class="rc-btn-outline">
            View Booking Details
        </a>
        <?php endif; ?>
    </div>

</main>

<script src="/webprogg/assets/javaScript.js"></script>

<!-- AUTO-REDIRECT COUNTDOWN (guest only — safe no-op if bar is absent) -->
<script>
(function () {
    var REDIRECT_URL = '/webprogg/booking/userbookings.php';
    var SECONDS = 10;

    var bar        = document.getElementById('rcAutoRedirect');
    var counterEl  = document.getElementById('rcCountdown');
    var cancelBtn  = document.getElementById('rcArCancel');
    var nowBtn     = document.getElementById('rcArNow');

    if (!bar || !counterEl) return;

    var remaining = SECONDS;
    var stopped   = false;

    var timer = setInterval(function () {
        if (stopped) return;

        remaining--;

        if (remaining <= 0) {
            clearInterval(timer);
            window.location.href = REDIRECT_URL;
            return;
        }

        counterEl.textContent = remaining;
    }, 1000);

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            stopped = true;
            clearInterval(timer);
            counterEl.textContent = '\u2014';
            bar.classList.add('rc-ar-cancelled');
        });
    }

    if (nowBtn) {
        nowBtn.addEventListener('click', function () {
            stopped = true;
            clearInterval(timer);
            window.location.href = REDIRECT_URL;
        });
    }

    var printButtons = document.querySelectorAll('button[onclick*="window.print"]');
    for (var i = 0; i < printButtons.length; i++) {
        printButtons[i].addEventListener('click', function () {
            stopped = true;
            clearInterval(timer);
            counterEl.textContent = '\u2014';
            bar.classList.add('rc-ar-cancelled');
        });
    }
})();
</script>

<!-- HOST ON-RECEIPT SIGNING PAD (runs only if the pad is rendered) -->
<script>
(function () {
    var canvas = document.getElementById('rcSignCanvas');
    if (!canvas) return;

    var ctx        = canvas.getContext('2d');
    var saveBtn    = document.getElementById('rcSignSave');
    var clearBtn   = document.getElementById('rcSignClear');
    var hint       = document.getElementById('rcSignHint');

    var hasInk  = false;
    var drawing = false;
    var lastX   = 0;
    var lastY   = 0;

    var BOOKING_ID = <?php echo (int) $bookingId; ?>;
    var IS_PENDING = <?php echo $isPendingBooking ? 'true' : 'false'; ?>;

    function initCanvas() {
        var dpr  = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        var w    = Math.max(200, Math.round(rect.width));
        var h    = 180;

        canvas.width  = w * dpr;
        canvas.height = h * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        /* white background so the saved PNG is not transparent */
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, w, h);

        /* signature baseline */
        ctx.strokeStyle = '#C9CDD6';
        ctx.lineWidth = 1.2;
        ctx.setLineDash([4, 6]);
        ctx.beginPath();
        ctx.moveTo(16, h - 40);
        ctx.lineTo(w - 16, h - 40);
        ctx.stroke();
        ctx.setLineDash([]);

        /* ink */
        ctx.strokeStyle = '#14142B';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        hasInk = false;
        drawing = false;
        saveBtn.disabled = true;
        hint.classList.remove('hidden');
    }

    function canvasPos(event) {
        var rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    canvas.addEventListener('pointerdown', function (event) {
        event.preventDefault();
        drawing = true;
        try { canvas.setPointerCapture(event.pointerId); } catch (e) {}
        var p = canvasPos(event);
        lastX = p.x; lastY = p.y;

        if (!hasInk) {
            hasInk = true;
            saveBtn.disabled = false;
            hint.classList.add('hidden');
        }
        ctx.beginPath();
        ctx.arc(p.x, p.y, 1.2, 0, Math.PI * 2);
        ctx.fillStyle = '#14142B';
        ctx.fill();
    });

    canvas.addEventListener('pointermove', function (event) {
        if (!drawing) return;
        var p = canvasPos(event);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        lastX = p.x;
        lastY = p.y;
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
        canvas.addEventListener(evt, function () { drawing = false; });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () { initCanvas(); });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!hasInk) return;

            var dataUrl = canvas.toDataURL('image/png');

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving signature\u2026';

            /* STEP 1 — save the signature */
            fetch('/webprogg/booking/sign-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'booking_id=' + encodeURIComponent(BOOKING_ID)
                    + '&signature=' + encodeURIComponent(dataUrl)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        alert(data.message || 'Could not save your signature.');
                        saveBtn.disabled = false;
                        saveBtn.textContent = IS_PENDING ? 'Sign & Accept Application' : 'Sign Receipt';
                        return;
                    }

                    /* STEP 2 — if the booking is still pending, accept it too */
                    if (!IS_PENDING) {
                        window.location.reload();
                        return;
                    }

                    saveBtn.textContent = 'Accepting\u2026';
                    fetch('/webprogg/booking/accept-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'booking_id=' + encodeURIComponent(BOOKING_ID)
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (acc) {
                            if (!acc.success) {
                                alert(acc.message || 'Signature saved, but the application could not be accepted.');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            alert('Signature saved, but the application could not be accepted.');
                            window.location.reload();
                        });
                })
                .catch(function () {
                    alert('Something went wrong while saving your signature. Please try again.');
                    saveBtn.disabled = false;
                    saveBtn.textContent = IS_PENDING ? 'Sign & Accept Application' : 'Sign Receipt';
                });
        });
    }
})();
</script>

</body>
</html>