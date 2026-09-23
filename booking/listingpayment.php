<?php
/* =========================================================
   ROOMHIVE — LISTING PAYMENT (Inquiry Step 2: Payment)
   listingpayment.php

   === CHANGES (this version) ===
   - NAVBAR REMOVED — the custom lp-nav header is deleted;
     the page is a focused payment step between Details and
     Confirmation (back links handle navigation).
   - PAY FULL PRICE BUTTON — under "Pay Reserve", guests can
     pay the ENTIRE discounted total up front. Posts the same
     form with payment_purpose_full=1; process-payment.php
     reads the flag and charges the full amount instead of
     the 50% reserve. Hive discount still applies once.
   - KEPT: Hive Club discount, balance mode, owner "List Now"
     flow, guards, map pin, same POST target/field names.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $isLoggedIn = isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true;
 $navAvatar  = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
 $notification_count = 0;

/* Real unread bell count */
 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
);
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* -----------------------------------------------------
   HIVE CLUB — expire lapsed memberships, load this member
----------------------------------------------------- */
hive_expiry_sweep($pdo);
 $hiveMember   = hive_member($pdo, $_SESSION['user_id']);
 $hiveActive   = hive_active($hiveMember);
 $hiveDiscount = hive_discount_pct($hiveMember);
 $hiveTier     = $hiveMember ? (string) $hiveMember['tier'] : null;
 $hiveDaysLeft = hive_days_until_expiry($hiveMember);

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('resolve_photo')) {
    function resolve_photo($path, $fallback) {
        if (empty($path)) {
            return $fallback;
        }
        if (preg_match('#^(https?://|/)#i', $path)) {
            return $path;
        }
        return '/webprogg/' . ltrim((string) $path, '/');
    }
}

/* -----------------------------------------------------
   RESOLVE LISTING + INQUIRY DETAILS FROM STEP 1
----------------------------------------------------- */
 $listingId = isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])
    ? (int) $_GET['listing_id']
    : 0;

if (!function_exists('payment_valid_date')) {
    function payment_valid_date($value) {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }
}

 $checkin  = payment_valid_date($_GET['checkin_date'] ?? null) ?? '';
 $checkout = payment_valid_date($_GET['checkout_date'] ?? null) ?? '';

 $longTerm = isset($_GET['long_term']) && $_GET['long_term'] === '1';

if ($longTerm) {
    $checkout = '';
}

 $allowedGuestOptions = ['1', '2', '3', '4+'];
 $guestsInput = $_GET['guests'] ?? null;
 $guests = in_array($guestsInput, $allowedGuestOptions, true) ? $guestsInput : '1';

/* -----------------------------------------------------
   PAY-REMAINING-BALANCE MODE
----------------------------------------------------- */
 $payBalanceBookingId = isset($_GET['pay_balance']) && is_numeric($_GET['pay_balance'])
    ? (int) $_GET['pay_balance']
    : null;

 $balanceBooking = null;

if ($payBalanceBookingId !== null) {
    $balanceStmt = $pdo->prepare(
        "SELECT id, user_id, listing_id, total, amount_paid, status,
                checkin_date, checkout_date, guests
         FROM bookings
         WHERE id = :id
         LIMIT 1"
    );
    $balanceStmt->execute(['id' => $payBalanceBookingId]);
    $balanceBooking = $balanceStmt->fetch();

    $balanceIsValid = $balanceBooking !== false
        && (int) $balanceBooking['user_id'] === (int) $_SESSION['user_id']
        && (int) $balanceBooking['listing_id'] === $listingId
        && in_array($balanceBooking['status'], ['pending', 'confirmed'], true);

    if (!$balanceIsValid) {
        header('Location: /webprogg/booking/userbookings.php');
        exit;
    }

    $remaining = round(
        (float) $balanceBooking['total'] - (float) $balanceBooking['amount_paid'],
        2
    );

    if ($remaining <= 0.005) {
        header('Location: /webprogg/booking/booking-details.php?id=' . $payBalanceBookingId);
        exit;
    }

    $checkin  = $balanceBooking['checkin_date']  ?? $checkin;
    $checkout = $balanceBooking['checkout_date'] ?? $checkout;
    $guests   = in_array($balanceBooking['guests'] ?? '', $allowedGuestOptions, true)
        ? $balanceBooking['guests']
        : $guests;
}

/* =====================================================
   LISTING FETCH
===================================================== */
 $listingStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.location, l.exact_address, l.category, l.property_type, l.price,
            l.user_id AS host_id,
            l.status,
            l.latitude, l.longitude,
            l.bedrooms, l.bathrooms, l.size_sqm, l.floor, l.parking,
            u.name AS host_name, u.avatar_path AS host_avatar, u.created_at AS host_since,
            p.photo_path AS cover_photo
    FROM listings l
    JOIN users u ON u.id = l.user_id
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

/* =========================================================
   LISTING STATUS GUARDS
========================================================= */
 $isListingOwner = ((int) $listing['host_id'] === (int) $_SESSION['user_id']);
 $listingStatus  = $listing['status'] ?? '';

if ($balanceBooking === null) {

    if ($isListingOwner && $listingStatus === 'approved') {
        header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&alreadylisted=1');
        exit;
    }

    if (!$isListingOwner && $listingStatus !== 'approved') {
        header('Location: /webprogg/Listings/listing-detail.php?id=' . $listingId . '&unavailable=1');
        exit;
    }
}

/* -----------------------------------------------------
   RATINGS
----------------------------------------------------- */
 $listingReviewsStmt = $pdo->prepare(
    "SELECT rating FROM reviews WHERE listing_id = :id"
);
 $listingReviewsStmt->execute(['id' => $listingId]);
 $listingRatings = array_map('floatval', array_column($listingReviewsStmt->fetchAll(), 'rating'));

 $listing_rating_avg   = count($listingRatings) > 0 ? round(array_sum($listingRatings) / count($listingRatings), 1) : 0;
 $listing_rating_count = count($listingRatings);

 $hostReviewsStmt = $pdo->prepare(
    "SELECT r.rating
     FROM reviews r
     JOIN listings l2 ON l2.id = r.listing_id
     WHERE l2.user_id = :host_id"
);
 $hostReviewsStmt->execute(['host_id' => $listing['host_id']]);
 $hostRatings = array_map('floatval', array_column($hostReviewsStmt->fetchAll(), 'rating'));

 $host_rating_avg   = count($hostRatings) > 0 ? round(array_sum($hostRatings) / count($hostRatings), 1) : 0;
 $host_rating_count = count($hostRatings);

 $isSuperhost = $host_rating_count >= 5 && $host_rating_avg >= 4.8;

/* =========================================================
   HIVE CLUB PRICING (Phase 4)
========================================================= */
 $monthlyRent = (float) $listing['price'];

if ($balanceBooking !== null) {
    $hiveDiscount = 0;
}

 $discountAmount = round($monthlyRent * ($hiveDiscount / 100), 2);
 $discountedTotal = round($monthlyRent - $discountAmount, 2);

 $reservationFee  = round($discountedTotal * 0.5, 2);
 $reserveBalance  = round($discountedTotal - $reservationFee, 2);

if ($balanceBooking !== null) {
    $totalDueToday   = $remaining;
    $bookingTotal    = (float) $balanceBooking['total'];
    $amountPaidSoFar = (float) $balanceBooking['amount_paid'];
} else {
    $totalDueToday   = $reservationFee;
    $bookingTotal    = null;
    $amountPaidSoFar = null;
}

 $paidPct = ($balanceBooking !== null && $bookingTotal > 0)
    ? max(0, min(100, (int) round(($amountPaidSoFar / $bookingTotal) * 100)))
    : 0;

/* -----------------------------------------------------
   MAP PIN COORDINATES
----------------------------------------------------- */
 $pinLatitude  = isset($listing['latitude'])  && $listing['latitude']  !== null
    ? (float) $listing['latitude']
    : null;
 $pinLongitude = isset($listing['longitude']) && $listing['longitude'] !== null
    ? (float) $listing['longitude']
    : null;
 $hasMapPin = ($pinLatitude !== null && $pinLongitude !== null);

/* -----------------------------------------------------
   PAYMENT METHODS OFFERED
----------------------------------------------------- */
 $paymentMethods = [
    [
        'id'          => 'gcash',
        'label'       => 'GCash',
        'description' => 'Pay securely with GCash',
        'icon'        => '/webprogg/images/GCashIcon.png',
        'recommended' => true,
    ],
    [
        'id'          => 'maya',
        'label'       => 'Maya',
        'description' => 'Pay with Maya',
        'icon'        => '/webprogg/images/paymayaicon.png',
        'recommended' => false,
    ],
    [
        'id'          => 'card',
        'label'       => 'Credit/Debit Card',
        'description' => 'Visa, Mastercard, JCB and more.',
        'icon'        => '/webprogg/images/paymentsicon-userprofile.png',
        'recommended' => false,
    ],
];

 $mapsQuery = $hasMapPin
    ? $pinLatitude . ',' . $pinLongitude
    : trim($listing['exact_address'] . ', ' . $listing['location']);
 $mapsUrl   = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $balanceBooking !== null ? 'Pay Balance' : 'Payment Method'; ?> — RoomHive</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        background: #F7F8FA;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #14142B;
    }

    /* ===== PAGE SHELL ===== */
    .bp-page { max-width: 1100px; margin: 0 auto; padding: 30px 20px 70px; }

    .bp-back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 18px;
        color: #5B6172;
        text-decoration: none;
        font-weight: 600;
        font-size: 13.5px;
        transition: color .15s;
    }
    .bp-back-link:hover { color: #F5A623; }

    /* ===== STEP TRACKER ===== */
    .bp-steps {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        gap: 10px;
        margin-bottom: 30px;
    }
    .bp-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 7px;
        width: 86px;
    }
    .bp-step-circle {
        width: 34px; height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #EEF1F6;
        color: #8B93A6;
        font-weight: 700;
        font-size: 13px;
        transition: all .2s;
    }
    .bp-step-circle svg { width: 15px; height: 15px; }
    .bp-step-label { font-size: 12px; color: #8B93A6; font-weight: 600; text-align: center; }
    .bp-step-done .bp-step-circle { background: #E7F7EC; color: #2FA84F; }
    .bp-step-done .bp-step-label { color: #2FA84F; }
    .bp-step-active .bp-step-circle {
        background: linear-gradient(135deg, #FFB94E, #F5A623);
        color: #fff;
        box-shadow: 0 4px 12px rgba(245,166,35,.45);
    }
    .bp-step-active .bp-step-label { color: #14142B; font-weight: 700; }
    .bp-step-line {
        flex: 1;
        max-width: 110px;
        height: 2.5px;
        background: #EEF1F6;
        border-radius: 99px;
        margin-top: 16px;
    }
    .bp-step-line-done { background: #A8DFBC; }

    /* ===== LAYOUT ===== */
    .bp-layout { display: flex; gap: 22px; align-items: flex-start; flex-wrap: wrap; }
    .bp-main { flex: 1.7; min-width: 300px; }
    .bp-sidebar {
        flex: 1;
        min-width: 280px;
        max-width: 380px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        position: sticky;
        top: 20px;
    }
    @media (max-width: 900px) {
        .bp-sidebar { position: static; max-width: none; }
    }

    /* ===== CARDS ===== */
    .bp-card {
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 2px 10px rgba(20,20,43,0.04);
    }

    .bp-main h1 { margin: 0 0 5px; font-size: 22px; color: #14142B; letter-spacing: -0.3px; }
    .bp-subtext { margin: 0 0 20px; font-size: 13.5px; color: #8B93A6; }

    /* ===== BANNERS ===== */
    .bp-secure-banner {
        display: flex;
        gap: 12px;
        align-items: center;
        background: #EAF9F0;
        border: 1px solid #C9EDD7;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 22px;
    }
    .bp-secure-icon {
        width: 38px; height: 38px;
        border-radius: 12px;
        background: #D3F3E0;
        display: flex; align-items: center; justify-content: center;
        color: #2FA84F;
        flex-shrink: 0;
    }
    .bp-secure-icon svg { width: 18px; height: 18px; }
    .bp-secure-banner strong { display: block; color: #14532D; font-size: 13.5px; }
    .bp-secure-banner p { margin: 2px 0 0; color: #4C8A63; font-size: 12.5px; }

    .bp-reserve-note {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        background: #FFF6E9;
        border: 1px dashed #F5C77E;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 22px;
    }
    .bp-reserve-note .bp-secure-icon { background: #FDE8C8; color: #C77800; }
    .bp-reserve-note strong { display: block; color: #8A5A10; font-size: 13.5px; }
    .bp-reserve-note p { margin: 2px 0 0; color: #A97B2F; font-size: 12.5px; line-height: 1.5; }

    /* ===== HIVE CLUB MEMBER CHIP (sidebar) ===== */
    .bp-hive-chip {
        display: flex;
        gap: 12px;
        align-items: center;
        background: linear-gradient(120deg, #FFF6E9 0%, #FFFDF6 100%);
        border: 1px solid #F5C77E;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 16px;
    }
    .bp-hive-chip .bp-secure-icon { background: #FDE8C8; color: #C77800; font-size: 18px; }
    .bp-hive-chip-body { flex: 1; min-width: 0; }
    .bp-hive-chip-body strong { display: block; color: #8A5A10; font-size: 13.5px; }
    .bp-hive-chip-body p { margin: 2px 0 0; color: #A97B2F; font-size: 12px; line-height: 1.5; }
    .bp-hive-chip a {
        flex-shrink: 0;
        font-size: 11.5px;
        font-weight: 800;
        color: #C77800;
        text-decoration: none;
        white-space: nowrap;
    }
    .bp-hive-chip a:hover { text-decoration: underline; }

    /* ===== DISCOUNT ROW (summary) ===== */
    .bp-summary-row-disc strong { color: #1FA971; }
    .bp-summary-row-disc span::before {
        content: "";
        display: inline-block;
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #2FA84F;
        margin-right: 6px;
        vertical-align: middle;
    }

    /* ===== PAYMENT METHODS ===== */
    .bp-methods-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8B93A6;
        margin: 0 0 10px;
    }
    .bp-methods { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
    .bp-method {
        display: flex;
        align-items: center;
        gap: 14px;
        border: 1.5px solid #EEF1F6;
        border-radius: 14px;
        padding: 15px 16px;
        cursor: pointer;
        position: relative;
        transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .bp-method:hover { border-color: #FFD9A0; }
    .bp-method input { position: absolute; opacity: 0; pointer-events: none; }
    .bp-method-selected {
        border-color: #F5A623;
        background: #FFF9F0;
        box-shadow: 0 2px 10px rgba(245,166,35,0.15);
    }
    .bp-method-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        background: #F6F7FB;
        border: 1px solid #EEF1F6;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }
    .bp-method-selected .bp-method-icon { background: #fff; border-color: #FFE3B3; }
    .bp-method-icon img { max-width: 34px; max-height: 34px; object-fit: contain; }
    .bp-method-text { flex: 1; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .bp-method-name {
        font-weight: 700; color: #14142B; font-size: 14.5px;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .bp-method-desc { font-size: 12.5px; color: #8B93A6; }
    .bp-badge-recommended {
        font-size: 10px; font-weight: 800;
        background: #F5A623; color: #fff;
        padding: 3px 9px; border-radius: 999px;
        letter-spacing: 0.03em;
    }
    .bp-method-radio {
        width: 20px; height: 20px;
        border-radius: 50%;
        border: 2px solid #D8DCE5;
        flex-shrink: 0;
        transition: all .15s;
    }
    .bp-method-selected .bp-method-radio {
        border-color: #F5A623;
        background: radial-gradient(#F5A623 0 42%, transparent 46%);
    }

    /* ===== HOW IT WORKS ===== */
    .bp-howitworks {
        background: #F6F7FB;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 24px;
    }
    .bp-howitworks-title {
        display: flex; align-items: center; gap: 8px;
        font-weight: 700; color: #14142B; font-size: 13.5px; margin-bottom: 14px;
    }
    .bp-howitworks-icon {
        width: 20px; height: 20px;
        border-radius: 50%;
        background: #14142B; color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px;
        font-style: normal;
    }
    .bp-howitworks-steps { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
    .bp-hiw-step { flex: 1; min-width: 140px; }
    .bp-hiw-num {
        width: 24px; height: 24px;
        border-radius: 50%;
        background: #fff; border: 1.5px solid #E3E7EF;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11.5px; font-weight: 800; color: #14142B;
        margin-bottom: 7px;
    }
    .bp-hiw-step strong { display: block; font-size: 13px; color: #14142B; margin-bottom: 3px; }
    .bp-hiw-step p { margin: 0; font-size: 12px; color: #8B93A6; line-height: 1.45; }
    .bp-hiw-arrow { color: #C9CDD6; font-size: 16px; padding-top: 4px; }

    /* ===== PAY BUTTONS ===== */
    .bp-btn-pay {
        display: block;
        width: 100%;
        padding: 15px;
        border-radius: 14px;
        border: none;
        background: linear-gradient(135deg, #FFB94E, #F5A623);
        color: #fff;
        font-size: 15.5px;
        font-weight: 800;
        cursor: pointer;
        margin-bottom: 8px;
        box-shadow: 0 6px 16px rgba(245,166,35,0.4);
        transition: transform .12s, box-shadow .12s, filter .12s;
        font-family: inherit;
    }
    .bp-btn-pay:hover { filter: brightness(1.04); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(245,166,35,0.45); }
    .bp-btn-pay:active { transform: translateY(0); }
    .bp-btn-pay:disabled { opacity: 0.7; cursor: default; transform: none; }

    /* ===== PAY FULL BUTTON (NEW) ===== */
    .bp-btn-full {
        background: #ffffff;
        color: #14142B;
        border: 2px solid #F5A623;
        box-shadow: none;
        margin-bottom: 14px;
    }
    .bp-btn-full:hover {
        background: #FFF9F0;
        box-shadow: 0 6px 16px rgba(245,166,35,0.25);
    }

    .bp-secure-note {
        text-align: center;
        font-size: 11.5px;
        color: #8B93A6;
        margin: 0 0 14px;
    }

    .bp-back-inquiry {
        display: block;
        text-align: center;
        color: #8B93A6;
        font-size: 13px;
        text-decoration: none;
        font-weight: 600;
    }
    .bp-back-inquiry:hover { color: #14142B; text-decoration: underline; }

    /* ===== SIDEBAR: LISTING SUMMARY ===== */
    .bp-listing-photo {
        width: 100%;
        height: 170px;
        object-fit: cover;
        border-radius: 12px;
        margin-bottom: 14px;
        background: #F6F4EE;
    }
    .bp-listing-title { margin: 0 0 5px; font-size: 16px; color: #14142B; letter-spacing: -0.2px; }
    .bp-listing-location {
        display: flex; align-items: center; gap: 5px;
        margin: 0 0 6px; font-size: 12.5px; color: #8B93A6;
    }
    .bp-listing-location img { width: 13px; height: 13px; }
    .bp-listing-rating { margin: 0 0 16px; font-size: 13px; color: #14142B; font-weight: 700; }
    .bp-listing-rating .bp-star { color: #F5B301; }
    .bp-listing-rating span { color: #8B93A6; font-weight: 400; }

    .bp-specs {
        border-top: 1px solid #F1F3F8;
        border-bottom: 1px solid #F1F3F8;
        padding: 14px 0;
        margin-bottom: 16px;
        display: flex; flex-direction: column; gap: 9px;
    }
    .bp-spec-row { display: flex; justify-content: space-between; font-size: 13px; }
    .bp-spec-row span { color: #8B93A6; }
    .bp-spec-row strong { color: #14142B; font-weight: 600; }

    .bp-booking-summary h4 { margin: 0 0 12px; font-size: 14px; color: #14142B; }
    .bp-summary-row {
        display: flex; justify-content: space-between;
        font-size: 13.5px; margin-bottom: 9px; color: #5B6172;
    }
    .bp-summary-row strong { color: #14142B; }

    .bp-progress {
        height: 8px;
        border-radius: 999px;
        background: #EEF1F6;
        overflow: hidden;
        margin: 2px 0 6px;
    }
    .bp-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #FFC96B, #F5A623);
        transition: width .4s ease;
    }
    .bp-progress-caption {
        font-size: 11.5px;
        color: #8B93A6;
        margin: 0 0 12px;
    }
    .bp-progress-caption b { color: #E8960F; }

    .bp-summary-row-total {
        display: flex; justify-content: space-between; align-items: center;
        background: #FFF6E9;
        border: 1px dashed #F5C77E;
        border-radius: 12px;
        padding: 12px 14px;
        margin-top: 4px;
        color: #14142B;
        font-weight: 700;
        font-size: 14px;
    }
    .bp-summary-row-total strong { font-size: 17px; color: #C77800; }

    /* ===== HOST CARD ===== */
    .bp-host-card h3 { margin: 0 0 14px; font-size: 15px; color: #14142B; }
    .bp-host-row { display: flex; gap: 12px; margin-bottom: 12px; }
    .bp-host-avatar {
        width: 48px; height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid #FFE3B3;
        background: #F6F4EE;
    }
    .bp-host-name {
        margin: 0; font-weight: 700; color: #14142B; font-size: 14px;
        display: flex; align-items: center; gap: 7px; flex-wrap: wrap;
    }
    .bp-badge-superhost {
        font-size: 10px; font-weight: 800;
        background: #EAF2FE; color: #1A56DB;
        padding: 3px 9px; border-radius: 999px;
    }
    .bp-host-since { margin: 3px 0 0; font-size: 12px; color: #8B93A6; }
    .bp-host-rating { margin: 3px 0 0; font-size: 12px; color: #14142B; font-weight: 600; }
    .bp-host-rating .bp-star { color: #F5B301; }
    .bp-host-rating span { color: #8B93A6; font-weight: 400; }
    .bp-host-response {
        margin: 0;
        font-size: 12px;
        color: #5B6172;
        background: #F6F7FB;
        border-radius: 10px;
        padding: 9px 12px;
    }

    /* ===== LOCATION CARD ===== */
    .bp-location-card h3 { margin: 0 0 10px; font-size: 15px; color: #14142B; }
    .bp-location-address { margin: 0 0 12px; font-size: 13px; color: #5B6172; }
    .bp-map-embed {
        position: relative;
        z-index: 1;
        height: 150px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #EEF1F6;
        margin-bottom: 10px;
        box-shadow: 0 4px 14px -8px rgba(20, 20, 43, 0.3);
        background: #EAF2FB;
    }
    .bp-map-embed iframe { width: 100%; height: 100%; border: 0; display: block; }
    .bp-map-exact-note {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 12px;
        font-size: 11.5px;
        font-weight: 600;
        color: #2FA84F;
    }
    .bp-btn-outline {
        display: block;
        width: 100%;
        padding: 11px 16px;
        border-radius: 12px;
        border: 1.5px solid #EEF1F6;
        background: #fff;
        color: #14142B;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
        text-decoration: none;
        transition: background .15s, border-color .15s;
    }
    .bp-btn-outline:hover { background: #F6F7FB; border-color: #E3E7EF; }
</style>
</head>

<body>

<main class="bp-page">

    <?php if ($balanceBooking !== null): ?>
        <a href="/webprogg/booking/booking-details.php?id=<?php echo h($payBalanceBookingId); ?>" class="bp-back-link">
            &#8592; Back to Booking
        </a>
    <?php else: ?>
        <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($listingId); ?>" class="bp-back-link">
            &#8592; Back to Listing
        </a>
    <?php endif; ?>

    <?php if ($balanceBooking === null): ?>
    <div class="bp-steps">
        <div class="bp-step bp-step-done">
            <span class="bp-step-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12.5 4.5 4.5L19 7.5"/>
                </svg>
            </span>
            <span class="bp-step-label">Details</span>
        </div>
        <span class="bp-step-line bp-step-line-done"></span>
        <div class="bp-step bp-step-active">
            <span class="bp-step-circle">2</span>
            <span class="bp-step-label">Payment</span>
        </div>
        <span class="bp-step-line"></span>
        <div class="bp-step">
            <span class="bp-step-circle">3</span>
            <span class="bp-step-label">Confirmation</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="bp-layout">

        <!-- =====================================================
             MAIN: PAYMENT METHOD SELECTION
        ====================================================== -->
        <div class="bp-main">

            <div class="bp-card">

                <?php if ($balanceBooking !== null): ?>
                    <h1>Pay Remaining Balance</h1>
                    <p class="bp-subtext">
                        Settle the remaining &#8369;<?php echo h(number_format($totalDueToday, 2)); ?>
                        owed on this booking. Once paid, the booking is fully paid and you can enjoy your stay.
                    </p>
                <?php else: ?>
                    <h1>Payment Method</h1>
                    <p class="bp-subtext">Choose your preferred payment method to reserve your dates.</p>
                <?php endif; ?>

                <div class="bp-secure-banner">
                    <span class="bp-secure-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4.5" y="10.5" width="15" height="10" rx="2"/>
                            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Your payment is secure and encrypted</strong>
                        <p>We use trusted payment providers to keep your information safe.</p>
                    </div>
                </div>

                <?php if ($balanceBooking === null): ?>
                <div class="bp-reserve-note">
                    <span class="bp-secure-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                    </span>
                    <div>
                        <strong>24-hour reservation</strong>
                        <p>
                            This &#8369;<?php echo h(number_format($reservationFee, 2)); ?> payment (50% of the price)
                            locks your dates for <b>24 hours</b> while the host reviews your application.
                            If the host doesn't accept within 24 hours, the reserve is automatically declined
                            and your payment is refunded to your RoomHive wallet. Pay the remaining
                            &#8369;<?php echo h(number_format($reserveBalance, 2)); ?> after acceptance to
                            fully enjoy your stay. Prefer to settle everything up front? Use
                            <b>Pay Full Price</b> below.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <form id="bp-payment-form" method="POST" action="/webprogg/booking/process-payment.php">

                    <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                    <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                    <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                    <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                    <input type="hidden" name="long_term" value="<?php echo $longTerm ? '1' : '0'; ?>">

                    <?php if ($balanceBooking !== null): ?>
                        <input type="hidden" name="booking_id" value="<?php echo h($payBalanceBookingId); ?>">
                        <input type="hidden" name="payment_purpose" value="balance">
                    <?php else: ?>
                        <input type="hidden" name="payment_purpose" value="reservation">
                    <?php endif; ?>
                    <!-- Full-payment toggle — flipped by the Pay Full Price button -->
                    <input type="hidden" name="payment_purpose_full" id="payment_purpose_full" value="0">

                    <p class="bp-methods-label">Select a payment method</p>

                    <div class="bp-methods">
                        <?php foreach ($paymentMethods as $i => $method): ?>
                            <label class="bp-method <?php echo $i === 0 ? 'bp-method-selected' : ''; ?>">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="<?php echo h($method['id']); ?>"
                                    <?php echo $i === 0 ? 'checked' : ''; ?>
                                >
                                <span class="bp-method-icon">
                                    <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>"
                                         onerror="this.onerror=null;this.style.display='none';">
                                </span>
                                <span class="bp-method-text">
                                    <span class="bp-method-name">
                                        <?php echo h($method['label']); ?>
                                        <?php if ($method['recommended']): ?>
                                            <span class="bp-badge-recommended">Recommended</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="bp-method-desc"><?php echo h($method['description']); ?></span>
                                </span>
                                <span class="bp-method-radio"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($balanceBooking === null): ?>
                    <div class="bp-howitworks">
                        <div class="bp-howitworks-title">
                            <span class="bp-howitworks-icon">i</span>
                            How it works
                        </div>
                        <div class="bp-howitworks-steps">
                            <div class="bp-hiw-step">
                                <span class="bp-hiw-num">1</span>
                                <strong>Reserve (50%) or Pay Full</strong>
                                <p>Pay 50% to lock your dates, or pay the full price up front.</p>
                            </div>
                            <span class="bp-hiw-arrow">&#8594;</span>
                            <div class="bp-hiw-step">
                                <span class="bp-hiw-num">2</span>
                                <strong>Host Reviews (24h)</strong>
                                <p>The host has 24 hours to accept. Otherwise it's auto-declined and refunded.</p>
                            </div>
                            <span class="bp-hiw-arrow">&#8594;</span>
                            <div class="bp-hiw-step">
                                <span class="bp-hiw-num">3</span>
                                <strong>Enjoy Your Stay</strong>
                                <p>After acceptance you're all set — or just enjoy, if you paid in full.</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="bp-btn-pay">
                        <?php echo $balanceBooking !== null ? 'Pay Balance' : 'Pay Reserve'; ?>
                        &#8369; <?php echo h(number_format($totalDueToday, 2)); ?>
                    </button>

                    <?php if ($balanceBooking === null): ?>
                    <!-- FULL PAYMENT — pay the entire discounted total now -->
                    <button type="submit" class="bp-btn-pay bp-btn-full" id="bp-btn-full">
                        Pay Full Price
                        &#8369; <?php echo h(number_format($discountedTotal, 2)); ?>
                    </button>
                    <?php endif; ?>

                    <p class="bp-secure-note">
                        <?php if ($balanceBooking !== null): ?>
                            This payment will be added to booking #<?php echo h($payBalanceBookingId); ?>.
                        <?php else: ?>
                            Paying 50% holds your dates for 24 hours while the host reviews.
                            Pay Full Price settles everything now — still refundable if declined.
                        <?php endif; ?>
                    </p>

                    <?php if ($balanceBooking !== null): ?>
                        <a href="/webprogg/booking/booking-details.php?id=<?php echo h($payBalanceBookingId); ?>"
                           class="bp-back-inquiry">
                            &#8592; Back to Booking
                        </a>
                    <?php else: ?>
                        <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($listingId); ?>"
                           class="bp-back-inquiry">
                            &#8592; Back to Inquiry
                        </a>
                    <?php endif; ?>

                </form>

            </div>

        </div>

        <!-- =====================================================
             SIDEBAR: LISTING / SUMMARY / HOST / LOCATION
        ====================================================== -->
        <aside class="bp-sidebar">

            <?php if ($hiveActive && $hiveTier !== null): ?>
            <!-- HIVE CLUB MEMBER CHIP -->
            <div class="bp-hive-chip">
                <span class="bp-secure-icon">&#127858;</span>
                <div class="bp-hive-chip-body">
                    <strong><?php echo h($hiveTier); ?> Member &middot; <?php echo (int) $hiveDiscount; ?>% off</strong>
                    <p>
                        <?php if ($hiveDaysLeft === null): ?>
                            Membership never expires — discount applied below.
                        <?php elseif ($hiveDaysLeft > 0): ?>
                            Active for <?php echo (int) $hiveDaysLeft; ?> more day<?php echo $hiveDaysLeft === 1 ? '' : 's'; ?> — discount applied below.
                        <?php else: ?>
                            Renewing soon.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <!-- UPSELL CHIP -->
            <div class="bp-hive-chip">
                <span class="bp-secure-icon">&#127858;</span>
                <div class="bp-hive-chip-body">
                    <strong>Save up to 15% with Hive Club</strong>
                    <p>Members get 5&ndash;15% off every stay, plus points back on completion.</p>
                </div>
                <a href="/webprogg/hiveclub.php">Join &rarr;</a>
            </div>
            <?php endif; ?>

            <div class="bp-card">

                <img
                    class="bp-listing-photo"
                    src="<?php echo h(resolve_photo($listing['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>"
                    alt="<?php echo h($listing['title']); ?>"
                    onerror="this.onerror=null;this.src='/webprogg/images/ListingPlaceholder.png';"
                >

                <h3 class="bp-listing-title"><?php echo h($listing['title']); ?></h3>
                <p class="bp-listing-location">
                    <img src="/webprogg/images/GPSIcon.png" alt="">
                    <?php echo h($listing['location']); ?>
                </p>
                <p class="bp-listing-rating">
                    <span class="bp-star">&#9733;</span> <?php echo h($listing_rating_avg); ?>
                    <span>(<?php echo h($listing_rating_count); ?> reviews)</span>
                </p>

                <?php if ($checkin): ?>
                    <div class="bp-specs">
                        <div class="bp-spec-row">
                            <span>Check-in</span>
                            <strong><?php echo h(date('M j, Y', strtotime($checkin))); ?></strong>
                        </div>
                        <?php if ($checkout): ?>
                            <div class="bp-spec-row">
                                <span>Check-out</span>
                                <strong><?php echo h(date('M j, Y', strtotime($checkout))); ?></strong>
                            </div>
                        <?php elseif ($longTerm): ?>
                            <div class="bp-spec-row">
                                <span>Duration</span>
                                <strong>Long Term</strong>
                            </div>
                        <?php endif; ?>
                        <div class="bp-spec-row">
                            <span>Guests</span>
                            <strong><?php echo h($guests); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bp-specs">
                    <div class="bp-spec-row">
                        <span>Property Type</span>
                        <strong><?php echo h($listing['property_type'] ?: 'Not specified'); ?></strong>
                    </div>
                    <div class="bp-spec-row">
                        <span>Bedrooms</span>
                        <strong><?php echo h($listing['bedrooms'] ?? 'Not specified'); ?></strong>
                    </div>
                    <div class="bp-spec-row">
                        <span>Bathrooms</span>
                        <strong><?php echo h($listing['bathrooms'] ?? 'Not specified'); ?></strong>
                    </div>
                    <div class="bp-spec-row">
                        <span>Size</span>
                        <strong><?php echo isset($listing['size_sqm']) ? h($listing['size_sqm']) . ' m&sup2;' : 'Not specified'; ?></strong>
                    </div>
                    <div class="bp-spec-row">
                        <span>Floor</span>
                        <strong><?php echo h($listing['floor'] ?? 'Not specified'); ?></strong>
                    </div>
                    <div class="bp-spec-row">
                        <span>Parking</span>
                        <strong><?php echo h($listing['parking'] ?: 'Not specified'); ?></strong>
                    </div>
                </div>

                <div class="bp-booking-summary">
                    <h4><?php echo $balanceBooking !== null ? 'Payment Summary' : 'Booking Summary'; ?></h4>

                    <?php if ($balanceBooking !== null): ?>
                        <div class="bp-summary-row">
                            <span>Booking Total</span>
                            <strong>&#8369; <?php echo h(number_format($bookingTotal, 2)); ?></strong>
                        </div>
                        <div class="bp-summary-row">
                            <span>Already Paid</span>
                            <strong>&#8369; <?php echo h(number_format($amountPaidSoFar, 2)); ?></strong>
                        </div>

                        <div class="bp-progress" role="progressbar"
                             aria-valuenow="<?php echo $paidPct; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="bp-progress-fill" style="width: <?php echo $paidPct; ?>%;"></div>
                        </div>
                        <p class="bp-progress-caption"><b><?php echo $paidPct; ?>%</b> of the booking is already paid</p>

                        <div class="bp-summary-row-total">
                            <span>Due Today</span>
                            <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="bp-summary-row">
                            <span>Monthly Rent</span>
                            <strong>&#8369; <?php echo h(number_format($monthlyRent, 2)); ?></strong>
                        </div>

                        <?php if ($hiveDiscount > 0): ?>
                        <!-- Hive Club discount line -->
                        <div class="bp-summary-row bp-summary-row-disc">
                            <span>Hive Club <?php echo h($hiveTier); ?> (<?php echo (int) $hiveDiscount; ?>% off)</span>
                            <strong>&minus; &#8369; <?php echo h(number_format($discountAmount, 2)); ?></strong>
                        </div>
                        <div class="bp-summary-row">
                            <span>Member Price</span>
                            <strong>&#8369; <?php echo h(number_format($discountedTotal, 2)); ?></strong>
                        </div>
                        <?php endif; ?>

                        <div class="bp-summary-row">
                            <span>Reserve Now (50%)</span>
                            <strong>&#8369; <?php echo h(number_format($reservationFee, 2)); ?></strong>
                        </div>
                        <div class="bp-summary-row">
                            <span>Balance After Accept</span>
                            <strong>&#8369; <?php echo h(number_format($reserveBalance, 2)); ?></strong>
                        </div>

                        <div class="bp-summary-row-total">
                            <span>Due Today</span>
                            <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- HOST CARD -->
            <div class="bp-card bp-host-card">
                <h3>Meet Your Host</h3>
                <div class="bp-host-row">
                    <img
                        class="bp-host-avatar"
                        src="<?php echo h(resolve_photo($listing['host_avatar'], '/webprogg/images/default-avatar.png')); ?>"
                        alt="<?php echo h($listing['host_name']); ?>"
                    >
                    <div style="flex:1; min-width:0;">
                        <p class="bp-host-name">
                            <?php echo h($listing['host_name']); ?>
                            <?php if ($isSuperhost): ?>
                                <span class="bp-badge-superhost">Superhost</span>
                            <?php endif; ?>
                        </p>
                        <p class="bp-host-since">Hosting since <?php echo h(date('F Y', strtotime($listing['host_since']))); ?></p>
                        <?php if ($host_rating_count > 0): ?>
                            <p class="bp-host-rating">
                                <span class="bp-star">&#9733;</span> <?php echo h($host_rating_avg); ?>
                                <span>(<?php echo h($host_rating_count); ?> reviews)</span>
                            </p>
                        <?php else: ?>
                            <p class="bp-host-rating"><span>No reviews yet</span></p>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="bp-host-response">&#9203; Typically responds within a day</p>
            </div>

            <!-- LOCATION CARD -->
            <div class="bp-card bp-location-card">
                <h3>Location</h3>
                <p class="bp-location-address">
                    <?php echo h($listing['exact_address'] !== '' ? $listing['exact_address'] . ', ' : ''); ?>
                    <?php echo h($listing['location']); ?>
                </p>

                <?php if ($hasMapPin): ?>
                    <div class="bp-map-embed">
                        <iframe
                            src="https://www.google.com/maps?q=<?php echo h($pinLatitude . ',' . $pinLongitude); ?>&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Map of the listing"
                        ></iframe>
                    </div>
                    <p class="bp-map-exact-note">&#128205; Exact pin dropped by the host</p>
                <?php endif; ?>

                <a class="bp-btn-outline" href="<?php echo h($mapsUrl); ?>" target="_blank" rel="noopener">
                    Open in Google Maps
                </a>
            </div>

        </aside>

    </div>

</main>

<script>
/* =========================================================
   PAY FULL PRICE — flips the flag before the form posts.
   The Pay Reserve button leaves the flag at 0 (50% reserve).
========================================================== */
(function () {
    "use strict";

    var fullBtn  = document.getElementById('bp-btn-full');
    var fullFlag = document.getElementById('payment_purpose_full');

    if (!fullBtn || !fullFlag) { return; }

    fullBtn.addEventListener('click', function () {
        fullFlag.value = '1';
    });
})();
</script>

</body>
</html>