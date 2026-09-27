<?php
/* =========================================================
   listing-detail.php

   === MONTHLY RENTAL + LONG TERM ===
   - NORMAL MODE: tenant picks a MOVE-IN date + RENTAL
     DURATION (1-12 months); move-out is auto-calculated:
       move-out = move-in + N months - 1 day
     Total rent = monthly price x months.
   - LONG TERM MODE (checkbox): open-ended month-to-month
     stay. No move-out, no duration picker. Pay is ONE
     month at a time. checkout_date is stored NULL, and
     while a long-term booking is active the space is
     UNAVAILABLE to everyone else.

   === HIVE CLUB REMOVED ===
   The membership discount system was deleted from this
   page. All pricing now uses the listing's base price:
     total = price x months, reserve = 50% of total.
   (If hiveclub.php is deleted from /config, make sure no
   other page still includes it or calls hive_* functions.)

   === ENQUIRY -> PAYMENT FLOW ===
   SEND ENQUIRY submits (GET) to
     /webprogg/booking/listingpayment.php
   — the payment-method picker (Flow A per process-payment.php's
   docblock: listing-detail -> listingpayment -> process-payment
   -> receipt) — carrying listing_id, checkin_date,
   checkout_date, guests, months, and long_term when checked.
   Guests whitelist mapped: capacities above 4 send "4+".
   The verification gate popup still intercepts unverified
   renters BEFORE the form ever submits.

   === SPACE DETAILS ===
   Lives in the RIGHT SIDEBAR as the last card
   (.rd-details-card) and flex-grows to occupy the leftover
   space under the booking card (see listing-detail.css).
========================================================= */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* =========================
   LOGIN STATUS
========================== */
 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

/* =========================
   USER
========================== */
 $userName = $_SESSION['user_name'] ?? 'Guest';

/* =========================
   FLAGS FROM REDIRECTS
========================== */
 $showUnavailableNotice     = isset($_GET['unavailable']);
 $showOwnBookingNotice      = isset($_GET['ownbooking']);
 $showAlreadyListedNotice   = isset($_GET['alreadylisted']);
 $showIncompleteDatesNotice = isset($_GET['incompletedates']);
 $showNeedVerifyNotice      = isset($_GET['needverify']);

/* =========================
   AMENITY ICON MAP
========================== */
 $amenityIcons = [
    'wifi'             => ['label' => 'Wi-fi',          'icon' => '/webprogg/images/wifiicon.png'],
    'aircon'           => ['label' => 'Aircon',         'icon' => '/webprogg/images/airconicon.png'],
    'pet-friendly'     => ['label' => 'Pet Friendly',   'icon' => '/webprogg/images/petsicon.png'],
    'free-water'       => ['label' => 'Free Water',     'icon' => '/webprogg/images/watericon.png'],
    'free-electricity' => ['label' => 'Free Electricity', 'icon' => '/webprogg/images/elcetricityicon.png'],
    'security'         => ['label' => '24/7 Security',  'icon' => '/webprogg/images/SecurityIcon.png'],
];

/* =========================
   CAPACITY LABELS
========================== */
 $capacityLabels = [
    "1"   => "1 Guest",
    "2"   => "2 Guests",
    "3"   => "3 Guests",
    "4"   => "4 Guests",
    "5"   => "5 Guests",
    "6"   => "6 Guests",
    "8"   => "8 Guests",
    "10"  => "10 Guests",
    "10+" => "More than 10 Guests"
];

/* =========================
   RESOLVE LISTING FROM ?id=
========================== */

 $listingId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

 $listingStmt = $pdo->prepare(
    "SELECT l.*, u.name AS host_name, u.email AS host_email, u.created_at AS host_created_at,
            u.avatar_path AS host_avatar_path
     FROM listings l
     JOIN users u ON u.id = l.user_id
     WHERE l.id = :id
     LIMIT 1"
);
 $listingStmt->execute(['id' => $listingId]);
 $listingRow = $listingStmt->fetch();

if ($listingRow === false) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

/* Photos — cover first, then additional in sort_order */
 $photosStmt = $pdo->prepare(
    "SELECT photo_path, photo_type
     FROM listing_photos
     WHERE listing_id = :id
     ORDER BY (photo_type = 'cover') DESC, sort_order ASC"
);
 $photosStmt->execute(['id' => $listingId]);
 $photoRows = $photosStmt->fetchAll();

 $galleryImages = array_map(function ($row) {

    $path = trim($row['photo_path'] ?? '');

    if ($path === '') {
        return '/webprogg/images/ListingPlaceholder.png';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    if (stripos($path, 'webprogg/') === 0) {
        return '/' . $path;
    }

    if (stripos($path, 'host/uploads/') === 0) {
        return '/webprogg/' . $path;
    }

    if (stripos($path, 'uploads/listing_photos/') === 0) {
        return '/webprogg/' . $path;
    }

    if (stripos($path, 'cover_') === 0) {
        return '/webprogg/uploads/listing_photos/cover/' . basename($path);
    }

    return '/webprogg/uploads/listing_photos/' . $path;

}, $photoRows);

if (empty($galleryImages)) {
    $galleryImages = ['/webprogg/images/ListingPlaceholder.png'];
}

/* Reviews — average rating + count for this listing */
 $reviewsStmt = $pdo->prepare(
    "SELECT rating FROM reviews WHERE listing_id = :id"
);
 $reviewsStmt->execute(['id' => $listingId]);
 $reviewRatings = array_map('floatval', array_column($reviewsStmt->fetchAll(), 'rating'));

 $listingRating  = count($reviewRatings) > 0 ? round(array_sum($reviewRatings) / count($reviewRatings), 1) : 0;
 $listingReviews = count($reviewRatings);

/* Host's own review stats, across all their listings */
 $hostReviewsStmt = $pdo->prepare(
    "SELECT r.rating
     FROM reviews r
     JOIN listings l2 ON l2.id = r.listing_id
     WHERE l2.user_id = :host_id"
);
 $hostReviewsStmt->execute(['host_id' => $listingRow['user_id']]);
 $hostReviewRatings = array_map('floatval', array_column($hostReviewsStmt->fetchAll(), 'rating'));

 $hostRating  = count($hostReviewRatings) > 0 ? round(array_sum($hostReviewRatings) / count($hostReviewRatings), 1) : 0;
 $hostReviews = count($hostReviewRatings);

/* =========================================================
   LISTING AVAILABILITY
========================================================= */

 $availabilityStmt = $pdo->prepare(
    "SELECT 1
     FROM listings l
     WHERE l.id = :id
       AND l.status = 'approved'
     LIMIT 1"
);

 $availabilityStmt->execute([
    'id' => $listingId
]);

 $isBookable = (bool) $availabilityStmt->fetchColumn();

/* =========================================================
   BOOKED DATE RANGES (for occupancy + calendar blocking)
========================================================= */

 $unavailableDatesStmt = $pdo->prepare(
    "SELECT checkin_date, checkout_date, status
     FROM bookings
     WHERE listing_id = :listing_id
       AND status IN ('confirmed', 'pending')
       AND checkin_date IS NOT NULL
     ORDER BY checkin_date ASC"
);

 $unavailableDatesStmt->execute([
    'listing_id' => $listingId
]);

 $unavailableRanges = $unavailableDatesStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   LONG-TERM OCCUPANCY
========================================================= */

 $today = date('Y-m-d');

 $isLongTermOccupied = false;

foreach ($unavailableRanges as $range) {
    $co = $range['checkout_date'] ?? null;

    if (empty($co) && !empty($range['checkin_date'])) {
        $isLongTermOccupied = true;
        break;
    }
}

/* While a long-term stay occupies the space, nobody else can book it */
if ($isLongTermOccupied) {
    $isBookable = false;
}

/* Build the JS payload for the calendar */
 $unavailableRangesJs = [];

foreach ($unavailableRanges as $range) {
    $unavailableRangesJs[] = [
        'start' => !empty($range['checkin_date'])  ? $range['checkin_date']  : null,
        'end'   => !empty($range['checkout_date']) ? $range['checkout_date'] : null,
    ];
}

 $isOwnListing = $isLoggedIn && (int) $listingRow['user_id'] === (int) ($_SESSION['user_id'] ?? 0);

/* =========================================================
   LISTING PUBLISH STATUS
========================================================= */

 $listingStatus   = $listingRow['status'] ?? '';
 $isAlreadyListed = ($listingStatus === 'approved');

/* =========================================================
   NUDGE — verify suggestion flag.
   Logged-in RENTERS only (never hosts).
========================================================= */
 $viewerIsHost    = !empty($_SESSION['is_host']);
 $showVerifyNudge = false;

if ($isLoggedIn && !$viewerIsHost && !$isOwnListing) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/verification_gate.php';
    $showVerifyNudge = !is_user_verified($pdo, $_SESSION['user_id']);
}

/* =========================
   MY APPLICATION STATUS
========================== */
 $myApplicationStatus = null;

if ($isLoggedIn && !$isOwnListing) {
    $myApplicationStmt = $pdo->prepare(
        "SELECT status
         FROM bookings
         WHERE listing_id = :listing_id AND user_id = :user_id
         ORDER BY booked_at DESC
         LIMIT 1"
    );
    $myApplicationStmt->execute([
        'listing_id' => $listingId,
        'user_id'    => $_SESSION['user_id'],
    ]);
    $statusResult = $myApplicationStmt->fetchColumn();
    $myApplicationStatus = $statusResult !== false ? $statusResult : null;
}

/* =========================================================
   BASE PRICE — Hive Club removed.
   Every calculation uses the listing's plain monthly price.
========================================================= */
 $basePrice = (float) $listingRow['price'];

/* =========================================================
   MONTHLY RENTAL SYSTEM — duration-driven + LONG TERM
========================================================= */
 $allowedDurations = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
 $durationMonths = isset($_GET['months']) && in_array((int) $_GET['months'], $allowedDurations, true)
    ? (int) $_GET['months']
    : 1;

 $isLongTermSelected = isset($_GET['long_term']) && $_GET['long_term'] === '1';

if ($isLongTermSelected) {
    $durationMonths = 1;
}

 $listingTotal   = round($basePrice * $durationMonths, 2);
 $reserveFee     = round($listingTotal * 0.5, 2);
 $reserveBalance = round($listingTotal - $reserveFee, 2);

 $durationLabel = $isLongTermSelected
    ? 'Long Term'
    : ($durationMonths === 1 ? '1 Month' : $durationMonths . ' Months');

/* =========================================================
   ENQUIRY -> PAYMENT FLOW (CONNECTED)
   SEND ENQUIRY submits (GET) to listingpayment.php — the
   payment-method picker — which then POSTs to
   process-payment.php. This matches process-payment.php's
   documented Flow A and its own $changeMethodUrl pattern.

   GUESTS MAPPING: process-payment.php whitelists
   guests as '1' | '2' | '3' | '4+'. Host capacities of
   5 / 6 / 8 / 10 / 10+ map to '4+'; 1-4 pass through.
========================================================= */
 $paymentStartUrl = '/webprogg/booking/listingpayment.php';

 $rawCapacity = (string) $listingRow['capacity'];
if (in_array($rawCapacity, ['1', '2', '3', '4+'], true)) {
    $guestsValue = $rawCapacity;
} elseif (ctype_digit($rawCapacity) && (int) $rawCapacity >= 4) {
    $guestsValue = '4+';
} else {
    $guestsValue = '1';
}

/* Google Maps deep-link */
 $mapsQuery = ($listingRow['latitude'] !== null && $listingRow['longitude'] !== null)
    ? $listingRow['latitude'] . ',' . $listingRow['longitude']
    : trim($listingRow['exact_address'] . ', ' . $listingRow['location']);
 $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery);

 $listing = [
    'id'             => (int) $listingRow['id'],
    'title'          => $listingRow['title'],
    'gallery'        => $galleryImages,
    'location_label' => $listingRow['location'],
    'location_full'  => $listingRow['exact_address'] . ', ' . $listingRow['location'],
    'category_label' => $listingRow['category'],
    'price'          => $basePrice,
    'duration_months' => $durationMonths,
    'total_rent'      => $listingTotal,
    'reserve_fee'     => $reserveFee,
    'reserve_balance' => $reserveBalance,
    'amenities'      => json_decode($listingRow['amenities'] ?? '[]', true) ?? [],
    'bedrooms'       => (int) $listingRow['bedrooms'],
    'bedrooms_label' => $listingRow['bedrooms'] . ' ' . ($listingRow['bedrooms'] == 1 ? 'Bedroom' : 'Bedrooms'),
    'bathrooms'      => (int) $listingRow['bathrooms'],
    'size_sqm'       => (float) $listingRow['size_sqm'],
    'floor'          => $listingRow['floor'],
    'parking'        => $listingRow['parking'],

    'latitude'       => isset($listingRow['latitude']) && $listingRow['latitude'] !== null
                            ? (float) $listingRow['latitude'] : null,
    'longitude'      => isset($listingRow['longitude']) && $listingRow['longitude'] !== null
                            ? (float) $listingRow['longitude'] : null,

    'capacity'       => $listingRow['capacity'],
    'capacity_label' => $capacityLabels[$listingRow['capacity']] ?? ($listingRow['capacity'] . ' Guests'),

    'rating'         => $listingRating,
    'reviews'        => $listingReviews,
    'verified'       => false,
    'description'    => $listingRow['description'],
    'house_rules'    => $listingRow['house_rules'],
    'host'           => [
        'id'            => (int) $listingRow['user_id'],
        'name'          => $listingRow['host_name'],
        'avatar'        => !empty($listingRow['host_avatar_path'])
                                ? $listingRow['host_avatar_path']
                                : '/webprogg/images/default-avatar.png',
        'superhost'     => false,
        'member_since'  => date('F Y', strtotime($listingRow['host_created_at'])),
        'rating'        => $hostRating,
        'reviews'       => $hostReviews,
        'response_time' => 'within a day',
    ],
];

 $galleryCount = count($listing['gallery']);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RoomHive - <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Poppins Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/listing-detail.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- FREE MAP — Leaflet + OpenStreetMap (no API key) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <script>document.documentElement.classList.add("js");</script>

    <style>

        .listing-detail-page {
            --rd-honey: #eda423;
            --rd-honey-light: #f6c04e;
            --rd-honey-dark: #d99218;
            --rd-moss: #2f9e5b;
            --rd-ink: #1c2a38;
            --rd-ink-soft: #5d6875;
            --rd-line: rgba(28, 42, 56, 0.08);
            --rd-gold-shadow: 0 14px 28px rgba(237, 164, 35, 0.16);
        }

        @keyframes rdRise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .rd-reveal {
            opacity: 0;
            animation: rdRise 0.6s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) forwards;
        }

        .rd-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--rd-ink-soft);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease, transform 0.15s ease;
        }

        .rd-back-link:hover {
            color: var(--rd-honey-dark);
            transform: translateX(-3px);
        }

        .rd-action-btn {
            transition:
                border-color 0.15s ease,
                color 0.15s ease,
                background 0.15s ease,
                transform 0.15s ease;
        }

        .rd-action-btn:hover {
            border-color: var(--rd-honey) !important;
            color: #b07708 !important;
            transform: translateY(-1px);
        }

        .rd-action-btn.active .rd-heart-icon {
            color: #e0524d;
            animation: rdHeartPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes rdHeartPop {
            0%   { transform: scale(0.7); }
            60%  { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .rd-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px !important;
            background: #fff4e8 !important;
            border: 1px solid rgba(237, 164, 35, 0.5) !important;
            border-radius: 14px !important;
            color: #8a5a10 !important;
            font-weight: 500;
            animation: rdRise 0.4s ease both;
        }

        .rd-notice-success {
            background: #e8f8f1 !important;
            border-color: #b9e3c5 !important;
            color: #1e7a3d !important;
        }

        .rd-listed-note {
            text-align: center;
            margin-top: 10px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--rd-moss) !important;
        }

        .rd-date-hint {
            text-align: center;
            margin: 8px 0 0;
            font-size: 12px;
            font-weight: 600;
            color: #b07708 !important;
        }

        .rd-date-hint.rd-hint-ok {
            color: var(--rd-moss) !important;
        }

        .rd-date-hidden {
            display: none !important;
        }

        .rd-application-status {
            padding: 7px 15px !important;
            border-radius: 999px !important;
            font-size: 12.5px !important;
            font-weight: 700 !important;
            animation: rdRise 0.4s ease 0.15s both;
        }

        .rd-application-status-pending {
            background: #fff4e0 !important;
            color: #8a5a10 !important;
            border: 1px solid rgba(237, 164, 35, 0.5) !important;
        }

        .rd-application-status-confirmed {
            background: #e8f8f1 !important;
            color: #1e7a3d !important;
            border: 1px solid #b9e3c5 !important;
        }

        .rd-application-status-rejected {
            background: #fdecec !important;
            color: #a1332e !important;
            border: 1px solid #f3b9b9 !important;
        }

        .rd-application-status-cancelled {
            background: #f4f6f8 !important;
            color: #5d6875 !important;
            border: 1px solid #e3e7ec !important;
        }

        .rd-gallery-arrow,
        .rd-thumbs-arrow {
            transition:
                background 0.15s ease,
                transform 0.15s ease;
        }

        .rd-gallery-arrow:hover {
            transform: scale(1.08);
        }

        .rd-thumb {
            transition:
                border-color 0.15s ease,
                opacity 0.15s ease,
                transform 0.15s ease;
        }

        .rd-thumb:hover {
            transform: translateY(-2px);
        }

        .rd-title {
            color: var(--rd-ink) !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px;
        }

        .rd-rating {
            color: #b07708 !important;
            font-weight: 600;
        }

        .rd-amenity {
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .rd-amenity:hover {
            transform: translateY(-3px);
            border-color: rgba(237, 164, 35, 0.45) !important;
            background: #fff8ec !important;
            box-shadow: var(--rd-gold-shadow);
        }

        .rd-about h2,
        .rd-host h2,
        .rd-location-card h3 {
            position: relative;
            display: inline-block;
            color: var(--rd-ink) !important;
            font-weight: 800 !important;
        }

        .rd-about h2::after,
        .rd-host h2::after {
            content: "";
            position: absolute;
            width: 36px;
            height: 3px;
            left: 0;
            bottom: -7px;
            background: linear-gradient(90deg, #f6b93b, var(--rd-honey));
            border-radius: 2px;
        }

        .rd-about-text {
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rd-about-text.expanded {
            display: block;
            -webkit-line-clamp: unset;
            -webkit-box-orient: unset;
            overflow: visible;
        }

        .rd-show-more {
            background: none;
            border: none;
            color: var(--rd-honey-dark) !important;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.15s ease;
        }

        .rd-show-more:hover {
            color: #b07708 !important;
        }

        .rd-host-card {
            transition:
                border-color 0.25s ease,
                box-shadow 0.25s ease,
                transform 0.25s ease;
        }

        .rd-host-card:hover {
            border-color: rgba(237, 164, 35, 0.4) !important;
            box-shadow: var(--rd-gold-shadow) !important;
            transform: translateY(-3px);
        }

        .rd-host-avatar {
            border: 3px solid #ffffff;
            box-shadow:
                0 0 0 2.5px var(--rd-honey),
                0 8px 18px rgba(237, 164, 35, 0.3);
        }

        .rd-host-profile-btn {
            display: inline-block;
            padding: 10px 18px;
            background: #ffffff;
            border: 1.5px solid var(--rd-honey);
            border-radius: 10px;
            color: #b07708 !important;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .rd-host-profile-btn:hover {
            background: linear-gradient(135deg, #f6b93b, var(--rd-honey));
            color: var(--rd-ink) !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
        }

        .rd-booking-card {
            border-top: 4px solid var(--rd-honey) !important;
        }

        .rd-peso {
            color: var(--rd-honey-dark) !important;
        }

        /* ===== 50% reserve breakdown ===== */
        .rd-reserve-rows {
            border-top: 1px dashed var(--rd-line);
            margin-top: 14px;
            padding-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .rd-reserve-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--rd-ink-soft);
        }
        .rd-reserve-row strong {
            color: var(--rd-ink);
            font-weight: 600;
        }
        .rd-reserve-row.rd-reserve-now strong {
            color: #C77800;
            font-weight: 800;
        }

        .rd-btn-primary {
            background: linear-gradient(135deg, #f6b93b, var(--rd-honey)) !important;
            border: none !important;
            color: var(--rd-ink) !important;
            font-weight: 700 !important;
            box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35) !important;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease !important;
        }

        .rd-btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45) !important;
        }

        .rd-btn-primary:active:not(:disabled) {
            transform: translateY(0) scale(0.98);
        }

        .rd-btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .rd-btn-outline {
            transition:
                border-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease !important;
        }

        .rd-btn-outline:hover {
            border-color: var(--rd-honey) !important;
            color: #b07708 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(28, 42, 56, 0.08) !important;
        }

        .rd-charge-note {
            color: var(--rd-ink-soft) !important;
        }

        /* ===== LONG TERM checkbox ===== */
        .rd-long-term {
            margin-top: 4px;
        }
        .rd-long-term-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--rd-ink-soft);
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .rd-long-term-label:hover { color: #b07708; }
        .rd-long-term-label input { accent-color: var(--rd-honey); cursor: pointer; }

        /* ===== MONTHLY RENTAL — move-in + duration ===== */
        .rd-movein-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--rd-line);
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            color: var(--rd-ink);
            background: #fff;
            cursor: pointer;
        }
        .rd-movein-input:focus {
            outline: none;
            border-color: var(--rd-honey);
            box-shadow: 0 0 0 3px rgba(237, 164, 35, 0.15);
        }
        .rd-movein-input.rd-input-error {
            border-color: #d64545 !important;
            box-shadow: 0 0 0 3px rgba(214, 69, 69, 0.15) !important;
        }

        .rd-duration-picker {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1.5px solid var(--rd-line);
            border-radius: 12px;
            padding: 10px 12px;
            margin-top: 6px;
        }
        .rd-dur-btn {
            width: 34px; height: 34px;
            border-radius: 50%;
            border: 1.5px solid var(--rd-honey);
            background: #fff;
            color: #b07708;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .15s ease, transform .15s ease;
        }
        .rd-dur-btn:hover:not(:disabled) { background: #fff8ec; transform: scale(1.06); }
        .rd-dur-btn:disabled { opacity: .35; cursor: not-allowed; }
        .rd-dur-value { flex: 1; text-align: center; display: flex; flex-direction: column; gap: 1px; }
        .rd-dur-value strong { font-size: 15px; color: var(--rd-ink); }
        .rd-dur-value span { font-size: 11px; color: var(--rd-ink-soft); }

        .rd-detail-row {
            transition:
                background 0.15s ease,
                transform 0.15s ease;
            border-radius: 10px;
        }

        .rd-detail-row:hover {
            background: #fff8ec;
            transform: translateX(3px);
        }

        .rd-detail-row strong {
            color: var(--rd-ink) !important;
        }

        .rd-map-pin {
            display: inline-block;
            animation: rdPinBounce 2.2s ease-in-out infinite;
        }

        @keyframes rdPinBounce {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-7px); }
        }

        .rd-map-embed {
            position: relative;
            z-index: 1;
            height: 220px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--rd-line);
            margin-bottom: 14px;
            box-shadow: 0 8px 20px -12px rgba(28, 42, 56, 0.3);
        }

        .rd-map-exact-note {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 0 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--rd-moss);
        }

        /* ===== WHERE YOU'LL BE (left column) ===== */
        .rd-where-address {
            font-size: 0.9rem;
            color: var(--rd-ink-soft);
            margin: 0 0 12px;
        }

        /* ===== VERIFY GATE POPUP ===== */
        .rd-verify-popup {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            background: rgba(28, 42, 56, 0.55);
            padding: 20px;
        }
        .rd-verify-popup.open { display: flex; }
        .rd-verify-popup-card {
            background: #fff;
            border-radius: 18px;
            max-width: 380px;
            width: 100%;
            padding: 26px 24px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
            animation: rdRise 0.3s ease both;
        }
        .rd-verify-popup-card h4 {
            font-size: 17px;
            font-weight: 800;
            color: var(--rd-ink);
            margin: 0 0 8px;
        }
        .rd-verify-popup-card p {
            font-size: 13px;
            line-height: 1.6;
            color: var(--rd-ink-soft);
            margin: 0 0 18px;
        }
        .rd-verify-popup-actions { display: flex; gap: 10px; }
        .rd-verify-popup-actions .rd-btn { width: auto; flex: 1; margin-bottom: 0; }

        .flatpickr-calendar {
            box-shadow: 0 14px 34px rgba(28, 42, 56, 0.16) !important;
            border-radius: 14px !important;
            font-family: "Poppins", sans-serif !important;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: var(--rd-honey) !important;
            border-color: var(--rd-honey) !important;
            color: #ffffff !important;
        }

        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover {
            background: var(--rd-honey-dark) !important;
        }

        .flatpickr-day.today {
            border-color: var(--rd-honey) !important;
        }

        .flatpickr-day.today:hover {
            background: rgba(237, 164, 35, 0.14) !important;
            color: var(--rd-ink) !important;
        }

        .flatpickr-day:hover {
            background: rgba(237, 164, 35, 0.14) !important;
            border-color: transparent !important;
        }

        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.prevMonthDay.flatpickr-disabled,
        .flatpickr-day.nextMonthDay.flatpickr-disabled {
            text-decoration: line-through;
            opacity: .35;
        }

        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month {
            color: var(--rd-ink) !important;
        }

        /* ===== VERIFY NUDGE (inline hint above the form) ===== */
        .rd-verify-nudge {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin: 0 0 14px;
            padding: 11px 13px;

            background: #fff4e8;
            border: 1px dashed rgba(237, 164, 35, 0.55);
            border-radius: 12px;

            font-size: 12px;
            line-height: 1.55;
            color: #8a5a10;
        }

        .rd-verify-nudge a {
            color: #b07708;
            font-weight: 800;
            text-decoration: none;
        }

        .rd-verify-nudge a:hover {
            text-decoration: underline;
        }

        @media (prefers-reduced-motion: reduce) {
            .js .rd-reveal,
            .rd-notice,
            .rd-application-status {
                animation: none !important;
                opacity: 1 !important;
            }

            .rd-map-pin {
                animation: none !important;
            }

            .rd-amenity,
            .rd-host-card,
            .rd-detail-row,
            .rd-btn-primary,
            .rd-btn-outline,
            .rd-back-link,
            .rd-thumb,
            .rd-gallery-arrow,
            .rd-action-btn {
                transition: none !important;
            }
        }

    </style>

</head>

<body>

<?php if ($isLoggedIn): ?>

<style>
.account-dropdown {
    position: relative;
}

.account-dropdown .my-account {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    font: inherit;
    color: inherit;
}

.account-dropdown .dropdown-caret {
    font-size: 0.7em;
    transition: transform 0.15s ease;
}

.account-dropdown.open .dropdown-caret {
    transform: rotate(180deg);
}

.account-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 160px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    z-index: 1000;
    margin-top: 8px;
}

.account-dropdown.open .account-dropdown-menu {
    display: block;
}

.account-dropdown-menu a {
    display: block;
    padding: 10px 16px;
    text-decoration: none;
    color: #333;
    white-space: nowrap;
}

.account-dropdown-menu a:hover {
    background: #f5f5f5;
}
</style>

<?php endif; ?>

<!-- NOTE: if your original file included the site navbar here
     (fixed ~90px navbar from style.css), keep that include
     at this exact spot. -->

<!-- =========================
     LISTING DETAIL PAGE
========================== -->

<main class="listing-detail-page">

    <!-- TOP BAR -->

    <div class="rd-topbar rd-reveal" style="--d: .05s;">

        <a href="/webprogg/Listings/listing.php" class="rd-back-link" id="rd-back-link">
            &#8592; Back to Listings
        </a>

        <div class="rd-topbar-actions">

            <button type="button" class="rd-action-btn" id="rd-save-btn">
                <span class="rd-heart-icon">&#9825;</span> Save
            </button>

            <button type="button" class="rd-action-btn" id="rd-share-btn">
                <span class="rd-share-icon">&#8599;</span> Share
            </button>

        </div>

    </div>

    <?php if ($showNeedVerifyNotice): ?>
        <div class="rd-notice">
            &#128274; Your enquiry wasn't sent — verify your identity in
            <a href="/webprogg/user/editprofile.php#verify-card" style="font-weight:800; color:#8a5a10; text-decoration:none;">Edit Profile</a> first.
        </div>
    <?php elseif ($showUnavailableNotice): ?>
        <div class="rd-notice">
            &#9888;&#65039; Sorry, this space was just booked by someone else. Browse other available spaces below.
        </div>
    <?php elseif ($showOwnBookingNotice): ?>
        <div class="rd-notice">
            &#8505;&#65039; You can't book your own listing.
        </div>
    <?php elseif ($showAlreadyListedNotice): ?>
        <div class="rd-notice rd-notice-success">
            &#10003; This space is already listed. It's live and visible to renters.
        </div>
    <?php elseif ($showIncompleteDatesNotice): ?>
        <div class="rd-notice">
            &#9888;&#65039; Please choose your move-in date before continuing.
        </div>
    <?php endif; ?>

    <div class="rd-layout">

        <!-- =========================
             LEFT COLUMN
        ========================== -->

        <div class="rd-main">

            <!-- GALLERY -->

            <div class="rd-gallery rd-reveal" style="--d: .1s;">

                <div class="rd-gallery-main">

                    <img
                        src="<?= htmlspecialchars($galleryImages[0], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
                        id="rd-gallery-image"
                    >

                    <?php if ($galleryCount > 1): ?>

                        <button type="button" class="rd-gallery-arrow rd-gallery-prev" id="rd-gallery-prev" aria-label="Previous image">
                            &#10094;
                        </button>

                        <button type="button" class="rd-gallery-arrow rd-gallery-next" id="rd-gallery-next" aria-label="Next image">
                            &#10095;
                        </button>

                    <?php endif; ?>

                    <span class="rd-gallery-count" id="rd-gallery-count">
                        1 / <?= $galleryCount ?>
                    </span>

                </div>

                <?php if ($galleryCount > 1): ?>

                    <div class="rd-gallery-thumbs-wrap">

                        <button type="button" class="rd-thumbs-arrow rd-thumbs-prev" id="rd-thumbs-prev" aria-label="Scroll thumbnails left">
                            &#10094;
                        </button>

                        <div class="rd-gallery-thumbs" id="rd-gallery-thumbs">

                            <?php foreach ($galleryImages as $index => $image): ?>

                                <img
                                    src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Thumbnail <?= $index + 1 ?>"
                                    class="rd-thumb <?= $index === 0 ? 'active' : '' ?>"
                                    data-index="<?= $index ?>"
                                >

                            <?php endforeach; ?>

                        </div>

                        <button type="button" class="rd-thumbs-arrow rd-thumbs-next" id="rd-thumbs-next" aria-label="Scroll thumbnails right">
                            &#10094;
                        </button>

                    </div>

                <?php endif; ?>

            </div>

            <!-- TITLE / RATING -->

            <h1 class="rd-title rd-reveal" style="--d: .15s;">
                <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <div class="rd-subline rd-reveal" style="--d: .18s;">

                <span class="rd-location">
                    <img src="/webprogg/images/GPSIcon.png" alt="">
                    <?= htmlspecialchars($listing['location_full'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <?php if ($listing['reviews'] > 0): ?>
                    <span class="rd-rating">
                        &#9733; <?= number_format($listing['rating'], 1) ?>
                        (<?= (int) $listing['reviews'] ?> reviews)
                    </span>
                <?php else: ?>
                    <span class="rd-rating">
                        No reviews yet
                    </span>
                <?php endif; ?>

            </div>

            <?php if ($myApplicationStatus): ?>
                <div class="rd-application-status rd-application-status-<?= htmlspecialchars($myApplicationStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($myApplicationStatus === 'confirmed'): ?>
                        &#9989; Accepted by Host &mdash; you're good to go!
                    <?php elseif ($myApplicationStatus === 'rejected'): ?>
                        &#10060; Not Accepted by Host
                    <?php elseif ($myApplicationStatus === 'cancelled'): ?>
                        Application Cancelled
                    <?php else: ?>
                        &#8987; Application Pending Host Approval
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- AMENITIES ROW -->

            <div class="rd-amenities rd-reveal" style="--d: .22s;">

                <?php foreach ($listing['amenities'] as $amenityKey): ?>

                    <?php if (!isset($amenityIcons[$amenityKey])) { continue; } ?>

                    <div class="rd-amenity">

                        <img
                            src="<?= htmlspecialchars($amenityIcons[$amenityKey]['icon'], ENT_QUOTES, 'UTF-8') ?>"
                            alt=""
                        >

                        <span>
                            <?= htmlspecialchars($amenityIcons[$amenityKey]['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- ABOUT THIS SPACE -->

            <section class="rd-about rd-reveal" style="--d: .26s;">

                <h2>About this space</h2>

                <p id="rd-about-text" class="rd-about-text">
                    <?= htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8') ?>
                </p>

                <button type="button" class="rd-show-more" id="rd-show-more">
                    Show more &#9662;
                </button>

            </section>

            <?php if (!empty($listing['house_rules'])): ?>
            <!-- HOUSE RULES -->
            <section class="rd-about rd-reveal" style="--d: .3s;">
                <h2>House Rules</h2>
                <p class="rd-about-text">
                    <?= nl2br(htmlspecialchars($listing['house_rules'], ENT_QUOTES, 'UTF-8')) ?>
                </p>
            </section>
            <?php endif; ?>

            <!-- MEET YOUR HOST -->

            <section class="rd-host rd-reveal" style="--d: .34s;">

                <h2>Meet your host</h2>

                <div class="rd-host-card">

                    <img
                        src="<?= htmlspecialchars($listing['host']['avatar'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($listing['host']['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="rd-host-avatar"
                    >

                    <div class="rd-host-info">

                        <div class="rd-host-name-row">

                            <strong><?= htmlspecialchars($listing['host']['name'], ENT_QUOTES, 'UTF-8') ?></strong>

                            <?php if ($listing['host']['superhost']): ?>
                                <span class="rd-superhost-badge">Superhost</span>
                            <?php endif; ?>

                        </div>

                        <p>Member since <?= htmlspecialchars($listing['host']['member_since'], ENT_QUOTES, 'UTF-8') ?></p>

                        <?php if ($listing['host']['reviews'] > 0): ?>
                            <p>
                                &#9733; <?= number_format($listing['host']['rating'], 1) ?>
                                (<?= (int) $listing['host']['reviews'] ?> reviews)
                            </p>
                        <?php else: ?>
                            <p>No reviews yet</p>
                        <?php endif; ?>

                        <p>Response time: <?= htmlspecialchars($listing['host']['response_time'], ENT_QUOTES, 'UTF-8') ?></p>

                    </div>

                    <a href="/webprogg/host/hostpublicprofile.php?id=<?= (int) $listing['host']['id'] ?>" class="rd-host-profile-btn">
                        View Host Profile
                    </a>

                </div>

            </section>

            <!-- WHERE YOU'LL BE -->

            <section class="rd-about rd-reveal" style="--d: .38s;">

                <h2>Where you'll be</h2>

                <p class="rd-where-address">
                    <?= htmlspecialchars($listing['location_full'], ENT_QUOTES, 'UTF-8') ?>
                </p>

                <?php if ($listing['latitude'] !== null && $listing['longitude'] !== null): ?>

                    <div class="rd-map-embed" id="rd-map"></div>

                    <p class="rd-map-exact-note">
                        &#10003; Exact location shown on this map
                    </p>

                <?php else: ?>

                    <div class="rd-map-placeholder">
                        <img src="/webprogg/images/MapPlaceholder.png" alt="Map placeholder">
                        <span class="rd-map-pin">&#128205;</span>
                    </div>

                <?php endif; ?>

                <a
                    class="rd-btn rd-btn-outline rd-map-btn"
                    href="<?= htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') ?>"
                    target="_blank"
                    rel="noopener"
                >
                    Open in Google Maps
                </a>

            </section>

        </div>

        <!-- =========================
             RIGHT COLUMN (SIDEBAR)
        ========================== -->

        <aside class="rd-sidebar">

            <!-- BOOKING CARD -->

            <div class="rd-card rd-booking-card rd-reveal" style="--d: .2s;">

                <div class="rd-price">
                    <span class="rd-peso">&#8369;</span>
                    <?= number_format($listing['price']) ?>
                    <span class="rd-per">/ month</span>
                </div>

                <!-- RESERVE BREAKDOWN (monthly price x duration) -->
                <div class="rd-reserve-rows">
                    <div class="rd-reserve-row">
                        <span>Monthly price</span>
                        <strong>&#8369; <?= number_format((float) $listing['price'], 2) ?></strong>
                    </div>
                    <div class="rd-reserve-row">
                        <span>Rental duration</span>
                        <strong id="rd-sum-duration"><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="rd-reserve-row rd-reserve-now">
                        <span id="rd-sum-total-label"><?= $isLongTermSelected ? 'First month rent' : 'Total rent (' . (int) $durationMonths . ' month' . ($durationMonths === 1 ? '' : 's') . ')' ?></span>
                        <strong id="rd-sum-total">&#8369; <?= number_format($listingTotal, 2) ?></strong>
                    </div>
                    <div class="rd-reserve-row rd-reserve-now">
                        <span>Reserve now (50%)</span>
                        <strong id="rd-sum-reserve">&#8369; <?= number_format($reserveFee, 2) ?></strong>
                    </div>
                    <div class="rd-reserve-row">
                        <span>Balance after accept</span>
                        <strong id="rd-sum-balance">&#8369; <?= number_format($reserveBalance, 2) ?></strong>
                    </div>
                </div>

                <div class="rd-dates">

                    <label>Move-in date</label>

                    <input
                        type="text"
                        id="rd-movein"
                        class="rd-movein-input"
                        placeholder="Select your move-in date"
                        readonly
                    >

                    <!-- LONG TERM — open-ended month-to-month stay -->
                    <div class="rd-long-term" style="margin:10px 0 0;">
                        <label class="rd-long-term-label">
                            <input
                                type="checkbox"
                                id="rd-long-term"
                                name="long_term"
                                value="1"
                                form="rd-inquiry-form"
                            >
                            <span>Long Term (month-to-month)</span>
                        </label>
                    </div>

                    <label style="margin-top:12px;" id="rd-duration-label">Rental duration</label>

                    <div class="rd-duration-picker" id="rd-duration-picker">
                        <button type="button" class="rd-dur-btn" id="rd-dur-dec" aria-label="Shorter stay">&minus;</button>
                        <div class="rd-dur-value">
                            <strong id="rd-duration-display"><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span id="rd-dur-help">Move-out is set automatically</span>
                        </div>
                        <button type="button" class="rd-dur-btn" id="rd-dur-inc" aria-label="Longer stay">+</button>
                    </div>

                    <div class="rd-date-summary" id="rd-date-summary">

                        <div class="rd-date-summary-field">
                            <span>Move-in</span>
                            <strong id="rd-movein-display">Select date</strong>
                        </div>

                        <span class="rd-date-sep">&rarr;</span>

                        <div class="rd-date-summary-field" id="rd-moveout-field">
                            <span>Move-out</span>
                            <strong id="rd-moveout-display">&mdash;</strong>
                        </div>

                    </div>

                    <p class="rd-date-hint" id="rd-date-hint">Select a move-in date to continue</p>

                    <div class="rd-guests">
                        <label>Guests</label>
                        <div class="rd-guests-display">
                            <?= htmlspecialchars($listing['capacity_label'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <span class="rd-guests-note">Max capacity per booking</span>
                    </div>

                    <!-- ENQUIRY FORM -> listingpayment.php (Flow A) -->
                    <form
                        id="rd-inquiry-form"
                        action="<?= htmlspecialchars($paymentStartUrl, ENT_QUOTES, 'UTF-8') ?>"
                        method="get"
                    >

                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <input type="hidden" name="checkin_date" id="rd-checkin" value="">
                        <input type="hidden" name="checkout_date" id="rd-checkout" value="">
                        <input type="hidden" name="guests" value="<?= htmlspecialchars($guestsValue, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="months" id="rd-months" value="<?= (int) $durationMonths ?>">

                        <?php if ($showVerifyNudge): ?>
                        <div class="rd-verify-nudge">
                            <span>&#128274;</span>
                            <span>
                                Quick heads-up: verify your identity in
                                <a href="/webprogg/user/editprofile.php#verify-card">Edit Profile</a>
                                before sending an enquiry.
                            </span>
                        </div>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="rd-btn rd-btn-primary"
                            id="rd-submit-btn"
                            <?= (!$isBookable || $isOwnListing) ? 'disabled' : '' ?>
                        >
                            <?php if ($isOwnListing): ?>
                                THIS IS YOUR LISTING
                            <?php elseif (!$isBookable): ?>
                                CURRENTLY UNAVAILABLE
                            <?php else: ?>
                                SEND ENQUIRY
                            <?php endif; ?>
                        </button>

                    </form>

                    <p class="rd-charge-note">
                        You won't be charged yet &mdash; the host reviews your enquiry first.
                    </p>

                </div>

            </div>

            <!-- ============================================
                 SPACE DETAILS — last card in the sidebar.
                 .rd-details-card flex-grows to occupy all
                 leftover space under the booking card
                 (see listing-detail.css).
            ============================================== -->
            <section class="rd-card rd-details-card rd-reveal" style="--d: .3s;">

                <h3>Space details</h3>

                <div class="rd-detail-row">
                    <span>Bedroom</span>
                    <strong><?= (int) $listing['bedrooms'] ?> Included</strong>
                </div>

                <div class="rd-detail-row">
                    <span>Bathroom</span>
                    <strong><?= (int) $listing['bathrooms'] ?> Included</strong>
                </div>

                <div class="rd-detail-row">
                    <span>Floor area</span>
                    <strong><?= htmlspecialchars((string) $listing['size_sqm'], ENT_QUOTES, 'UTF-8') ?> sqm</strong>
                </div>

                <div class="rd-detail-row">
                    <span>Floor</span>
                    <strong><?= ($listing['floor'] !== null && $listing['floor'] !== '') ? htmlspecialchars((string) $listing['floor'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></strong>
                </div>

                <div class="rd-detail-row">
                    <span>Parking</span>
                    <strong><?= ($listing['parking'] !== null && $listing['parking'] !== '') ? htmlspecialchars((string) $listing['parking'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></strong>
                </div>

                <div class="rd-detail-row">
                    <span>Max capacity</span>
                    <strong><?= htmlspecialchars($listing['capacity_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </section>

        </aside><!-- /rd-sidebar -->

    </div>

</main>

<!-- =========================
     VERIFY GATE POPUP
     intercepts unverified renters BEFORE the form submits
========================== -->
<div class="rd-verify-popup" id="rd-verify-popup">
    <div class="rd-verify-popup-card">
        <h4>Verify your identity first</h4>
        <p>
            Before sending an enquiry, please verify your identity in
            Edit Profile. It only takes a minute &mdash; hosts only accept
            verified renters.
        </p>
        <div class="rd-verify-popup-actions">
            <a href="/webprogg/user/editprofile.php#verify-card" class="rd-btn rd-btn-primary">
                Verify now
            </a>
            <button type="button" class="rd-btn rd-btn-outline" id="rd-verify-close">
                Maybe later
            </button>
        </div>
    </div>
</div>

<!-- NOTE: if your original file included the site footer here,
     keep that include at this exact spot. -->

<!-- Libraries -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
(function () {
    'use strict';

    /* ---------- data injected from PHP ---------- */
    var RD_GALLERY      = <?= json_encode($galleryImages) ?>;
    var RD_BASE_PRICE   = <?= json_encode($basePrice) ?>;
    var RD_NEED_VERIFY  = <?= $showVerifyNudge ? 'true' : 'false' ?>;
    var RD_DISABLED     = <?= json_encode($unavailableRangesJs) ?>;
    var MAX_MONTHS      = 12;
    var currentMonths   = <?= (int) $durationMonths ?>;
    var moveinDate      = null;

    /* ---------- helpers ---------- */
    function peso(n) {
        return '\u20B1 ' + Number(n).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function ymd(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function fmtDate(d) {
        return d.toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric'
        });
    }

    /* move-out = move-in + N months - 1 day (handles month overflow) */
    function calcMoveOut(start, months) {
        var out = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        out.setMonth(out.getMonth() + months);
        if (out.getDate() !== start.getDate()) {
            out.setDate(0); /* clamp to last day of the target month */
        }
        out.setDate(out.getDate() - 1);
        return out;
    }

    /* ---------- element handles ---------- */
    var moveinInput   = document.getElementById('rd-movein');
    var longTermCb    = document.getElementById('rd-long-term');
    var durDisplay    = document.getElementById('rd-duration-display');
    var durHelp       = document.getElementById('rd-dur-help');
    var durDec        = document.getElementById('rd-dur-dec');
    var durInc        = document.getElementById('rd-dur-inc');
    var sumDuration   = document.getElementById('rd-sum-duration');
    var sumTotalLabel = document.getElementById('rd-sum-total-label');
    var sumTotal      = document.getElementById('rd-sum-total');
    var sumReserve    = document.getElementById('rd-sum-reserve');
    var sumBalance    = document.getElementById('rd-sum-balance');
    var moveinDisplay = document.getElementById('rd-movein-display');
    var moveoutField  = document.getElementById('rd-moveout-field');
    var moveoutDisplay= document.getElementById('rd-moveout-display');
    var dateHint      = document.getElementById('rd-date-hint');
    var checkinInput  = document.getElementById('rd-checkin');
    var checkoutInput = document.getElementById('rd-checkout');
    var monthsInput   = document.getElementById('rd-months');
    var form          = document.getElementById('rd-inquiry-form');
    var verifyPopup   = document.getElementById('rd-verify-popup');
    var verifyClose   = document.getElementById('rd-verify-close');

    /* ---------- central booking updater ---------- */
    function updateBooking() {
        var lt     = longTermCb && longTermCb.checked;
        var months = lt ? 1 : currentMonths;

        if (monthsInput) { monthsInput.value = months; }

        var label = lt
            ? 'Long Term'
            : (currentMonths === 1 ? '1 Month' : currentMonths + ' Months');

        if (durDisplay)  { durDisplay.textContent  = label; }
        if (sumDuration) { sumDuration.textContent = label; }
        if (durHelp) {
            durHelp.textContent = lt
                ? 'No fixed move-out \u2014 pay month to month'
                : 'Move-out is set automatically';
        }

        if (durDec) { durDec.disabled = currentMonths <= 1; }
        if (durInc) { durInc.disabled = currentMonths >= MAX_MONTHS; }

        var total = RD_BASE_PRICE * months;

        if (sumTotalLabel) {
            sumTotalLabel.textContent = lt
                ? 'First month rent'
                : 'Total rent (' + currentMonths + (currentMonths === 1 ? ' month' : ' months') + ')';
        }
        if (sumTotal)    { sumTotal.textContent    = peso(total); }
        if (sumReserve)  { sumReserve.textContent  = peso(total * 0.5); }
        if (sumBalance)  { sumBalance.textContent  = peso(total - total * 0.5); }

        if (moveinDisplay) {
            moveinDisplay.textContent = moveinDate ? fmtDate(moveinDate) : 'Select date';
        }
        if (checkinInput) {
            checkinInput.value = moveinDate ? ymd(moveinDate) : '';
        }

        if (moveoutField) {
            if (lt) {
                moveoutField.classList.add('rd-date-hidden');
                if (moveoutDisplay) { moveoutDisplay.textContent = 'Open-ended'; }
                if (checkoutInput)  { checkoutInput.value = ''; }
            } else {
                moveoutField.classList.remove('rd-date-hidden');
                if (moveinDate) {
                    var out = calcMoveOut(moveinDate, currentMonths);
                    if (moveoutDisplay) { moveoutDisplay.textContent = fmtDate(out); }
                    if (checkoutInput)  { checkoutInput.value = ymd(out); }
                } else {
                    if (moveoutDisplay) { moveoutDisplay.textContent = '\u2014'; }
                    if (checkoutInput)  { checkoutInput.value = ''; }
                }
            }
        }

        if (dateHint) {
            if (moveinDate) {
                dateHint.textContent = lt
                    ? 'Long term selected \u2014 only the move-in date is needed'
                    : 'Dates set. Move-out is calculated automatically.';
                dateHint.classList.add('rd-hint-ok');
            } else {
                dateHint.textContent = 'Select a move-in date to continue';
                dateHint.classList.remove('rd-hint-ok');
            }
        }
    }

    /* ---------- move-in calendar (flatpickr, blocked days crossed out) ---------- */
    var disabledRanges = [];
    RD_DISABLED.forEach(function (r) {
        if (r.start && r.end) {
            disabledRanges.push({ from: r.start, to: r.end });
        } else if (r.start) {
            /* long-term occupancy — block everything from move-in onward */
            disabledRanges.push({ from: r.start, to: '2099-12-31' });
        }
    });

    if (moveinInput && typeof flatpickr !== 'undefined') {
        flatpickr(moveinInput, {
            dateFormat: 'Y-m-d',
            minDate: 'today',
            disable: disabledRanges,
            onChange: function (selectedDates) {
                moveinDate = selectedDates[0] || null;
                moveinInput.classList.remove('rd-input-error');
                updateBooking();
            }
        });
    }

    /* ---------- duration picker ---------- */
    if (durDec) {
        durDec.addEventListener('click', function () {
            currentMonths = Math.max(1, currentMonths - 1);
            updateBooking();
        });
    }
    if (durInc) {
        durInc.addEventListener('click', function () {
            currentMonths = Math.min(MAX_MONTHS, currentMonths + 1);
            updateBooking();
        });
    }
    if (longTermCb) {
        longTermCb.addEventListener('change', updateBooking);
    }

    /* ---------- enquiry form gate ---------- */
    if (form) {
        form.addEventListener('submit', function (e) {

            /* verification gate popup — intercepts BEFORE submit */
            if (RD_NEED_VERIFY) {
                e.preventDefault();
                if (verifyPopup) { verifyPopup.classList.add('open'); }
                return;
            }

            /* require a move-in date */
            if (!checkinInput || !checkinInput.value) {
                e.preventDefault();
                if (moveinInput) {
                    moveinInput.classList.add('rd-input-error');
                    moveinInput.focus();
                }
                if (dateHint) { dateHint.textContent = 'Please choose your move-in date first'; }
                return;
            }
        });
    }

    if (verifyClose) {
        verifyClose.addEventListener('click', function () {
            if (verifyPopup) { verifyPopup.classList.remove('open'); }
        });
    }
    if (verifyPopup) {
        verifyPopup.addEventListener('click', function (e) {
            if (e.target === verifyPopup) { verifyPopup.classList.remove('open'); }
        });
    }

    /* ---------- gallery ---------- */
    var mainImg  = document.getElementById('rd-gallery-image');
    var countEl  = document.getElementById('rd-gallery-count');
    var prevBtn  = document.getElementById('rd-gallery-prev');
    var nextBtn  = document.getElementById('rd-gallery-next');
    var thumbEls = Array.prototype.slice.call(document.querySelectorAll('.rd-thumb'));
    var gIndex   = 0;

    function showImage(i) {
        if (!mainImg || RD_GALLERY.length === 0) { return; }
        gIndex = ((i % RD_GALLERY.length) + RD_GALLERY.length) % RD_GALLERY.length;
        mainImg.src = RD_GALLERY[gIndex];
        if (countEl) { countEl.textContent = (gIndex + 1) + ' / ' + RD_GALLERY.length; }
        thumbEls.forEach(function (t, ti) {
            t.classList.toggle('active', ti === gIndex);
        });
        var act = thumbEls[gIndex];
        if (act && act.scrollIntoView) {
            act.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }
    }

    if (prevBtn) { prevBtn.addEventListener('click', function () { showImage(gIndex - 1); }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { showImage(gIndex + 1); }); }
    thumbEls.forEach(function (t) {
        t.addEventListener('click', function () {
            showImage(parseInt(t.getAttribute('data-index'), 10) || 0);
        });
    });

    /* ---------- about "show more" ---------- */
    var aboutText  = document.getElementById('rd-about-text');
    var showMoreBtn= document.getElementById('rd-show-more');
    if (aboutText && showMoreBtn) {
        showMoreBtn.addEventListener('click', function () {
            var isExp = aboutText.classList.contains('expanded') ||
                        aboutText.classList.contains('rd-expanded');
            aboutText.classList.toggle('expanded', !isExp);
            aboutText.classList.toggle('rd-expanded', !isExp);
            showMoreBtn.innerHTML = !isExp ? 'Show less \u25B4' : 'Show more \u25BE';
        });
    }

    /* ---------- save (visual toggle) ---------- */
    var saveBtn = document.getElementById('rd-save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            this.classList.toggle('active');
            var heart = this.querySelector('.rd-heart-icon');
            if (heart) {
                heart.innerHTML = this.classList.contains('active') ? '&#9829;' : '&#9825;';
            }
        });
    }

    /* ---------- share ---------- */
    var shareBtn = document.getElementById('rd-share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function () {
            var url   = window.location.href;
            var title = document.title;
            var btn   = this;
            var original = btn.innerHTML;
            function copied() {
                btn.innerHTML = '&#10003; Link copied!';
                setTimeout(function () { btn.innerHTML = original; }, 1600);
            }
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(copied).catch(function () {});
            }
        });
    }

    /* ---------- account dropdown (navbar) ---------- */
    document.querySelectorAll('.account-dropdown').forEach(function (dd) {
        var trigger = dd.querySelector('.my-account');
        if (!trigger) { return; }
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dd.classList.toggle('open');
        });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.account-dropdown.open').forEach(function (dd) {
            if (!dd.contains(e.target)) { dd.classList.remove('open'); }
        });
    });

    /* ---------- Leaflet map — Where you'll be ---------- */
    <?php if ($listing['latitude'] !== null && $listing['longitude'] !== null): ?>
    (function () {
        var el = document.getElementById('rd-map');
        if (!el || typeof L === 'undefined') { return; }
        var lat = <?= json_encode($listing['latitude']) ?>;
        var lng = <?= json_encode($listing['longitude']) ?>;
        var map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'rd-pin-icon',
                html: '<div class="rd-pin">\u{1F4CD}</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 28]
            })
        }).addTo(map);
    })();
    <?php endif; ?>

    /* ---------- initial paint ---------- */
    updateBooking();

})();
</script>

</body>

</html>