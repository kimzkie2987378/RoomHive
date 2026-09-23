<?php
/* =========================================================
   listing-detail.php

   === NUDGE (NEW) ===
   When an UNVERIFIED RENTER clicks "Send Inquiry", a
   suggestion modal appears: "Get Verified Now" (→
   editprofile#verify-card) or "Continue without verifying"
   (proceeds normally). Suggestion only — never a block.
   Hosts never see it. Verified users never see it.
========================================================= */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php'; /* HIVE CLUB */

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
   FLAGS FROM book.php REDIRECTS
========================== */
 $showUnavailableNotice     = isset($_GET['unavailable']);
 $showOwnBookingNotice      = isset($_GET['ownbooking']);
 $showAlreadyListedNotice   = isset($_GET['alreadylisted']);
 $showIncompleteDatesNotice = isset($_GET['incompletedates']);

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
   GET UNAVAILABLE DATES
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

    if (empty($co) && !empty($range['checkin_date']) && $range['checkin_date'] <= $today) {
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
   Logged-in RENTERS only (never hosts). True when the
   viewer is not yet verified (ID + complete profile).
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
   HIVE CLUB — member price preview (Phase 4 consistency)
========================================================= */
 $hiveDiscountPct = 0;
 $hiveTierLabel   = null;
 $hiveDiscountAmt = 0.0;

if ($isLoggedIn && !$isOwnListing) {
    hive_expiry_sweep($pdo);
    $hiveMember      = hive_member($pdo, $_SESSION['user_id']);
    $hiveDiscountPct = hive_discount_pct($hiveMember);

    if ($hiveDiscountPct > 0) {
        $hiveTierLabel   = (string) $hiveMember['tier'];
        $hiveDiscountAmt = round(((float) $listingRow['price']) * ($hiveDiscountPct / 100), 2);
    }
}

 $memberPrice    = round(((float) $listingRow['price']) - $hiveDiscountAmt, 2);
 $reserveFee     = round($memberPrice * 0.5, 2);
 $reserveBalance = round($memberPrice - $reserveFee, 2);

/* Assemble into the shape the template below expects */
 $listing = [
    'id'             => (int) $listingRow['id'],
    'title'          => $listingRow['title'],
    'gallery'        => $galleryImages,
    'location_label' => $listingRow['location'],
    'location_full'  => $listingRow['exact_address'] . ', ' . $listingRow['location'],
    'category_label' => $listingRow['category'],
    'price'          => (float) $listingRow['price'],
    'member_price'    => $memberPrice,
    'reserve_fee'     => $reserveFee,
    'reserve_balance' => $reserveBalance,
    'amenities'      => json_decode($listingRow['amenities'] ?? '[]', true) ?? [],
    'bedrooms'       => (int) $listingRow['bedrooms'],
    'bedrooms_label' => $listingRow['bedrooms'] . ' ' . ($listingRow['bedrooms'] == 1 ? 'Bedroom' : 'Bedrooms'),
    'bathrooms'      => (int) $listingRow['bathrooms'],
    'size_sqm'       => (float) $listingRow['size_sqm'],
    'floor'          => $listingRow['floor'],
    'parking'        => $listingRow['parking'],

    /* MAP PIN */
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

            font-weight: 600 !important;
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

        /* ===== HIVE CLUB member price + 50% reserve breakdown ===== */
        .rd-hive-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 12px;
            padding: 10px 12px;
            background: linear-gradient(120deg, #FFF6E9, #FFFDF6);
            border: 1px solid #F5C77E;
            border-radius: 12px;
        }
        .rd-hive-chip .rd-hive-pct {
            flex-shrink: 0;
            background: linear-gradient(135deg, #f6b93b, #eda423);
            color: #fff;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
        }
        .rd-hive-chip span.rd-hive-txt {
            font-size: 12px;
            font-weight: 700;
            color: #8A5A10;
        }
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

        .rd-long-term-label {
            cursor: pointer;

            transition: color 0.15s ease;
        }

        .rd-long-term-label:hover {
            color: #b07708;
        }

        .rd-long-term-label input {
            accent-color: var(--rd-honey);

            cursor: pointer;
        }

        .rd-date-summary-field strong {
            color: var(--rd-ink) !important;
        }

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

        .rd-pin-icon {
            background: transparent;
            border: none;
        }

        .rd-pin {
            font-size: 30px;
            line-height: 1;
            filter: drop-shadow(0 3px 3px rgba(0, 0, 0, 0.35));
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

        .flatpickr-day.inRange {
            background: rgba(237, 164, 35, 0.14) !important;

            border-color: transparent !important;

            box-shadow: -5px 0 0 rgba(237, 164, 35, 0.14),
                        5px 0 0 rgba(237, 164, 35, 0.14) !important;
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

        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month {
            color: var(--rd-ink) !important;
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

    <?php if ($showUnavailableNotice): ?>
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
            &#9888;&#65039; Please complete your dates before continuing
            (&mdash; for Long Term, just choose your move-in date).
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
                            &#10095;
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

                <!-- HIVE CLUB member price chip (members only) -->
                <?php if ($hiveDiscountPct > 0): ?>
                <div class="rd-hive-chip">
                    <span class="rd-hive-pct"><?= (int) $hiveDiscountPct ?>% OFF</span>
                    <span class="rd-hive-txt">Hive Club <?= htmlspecialchars($hiveTierLabel) ?> member price applied</span>
                </div>
                <?php endif; ?>

                <!-- 50% RESERVE BREAKDOWN -->
                <div class="rd-reserve-rows">
                    <div class="rd-reserve-row">
                        <span>Monthly price</span>
                        <strong<?php echo $hiveDiscountPct > 0 ? ' style="text-decoration:line-through; color:#8B93A6;"' : ''; ?>>&#8369; <?= number_format((float) $listing['price'], 2) ?></strong>
                    </div>
                    <?php if ($hiveDiscountPct > 0): ?>
                    <div class="rd-reserve-row rd-reserve-now">
                        <span>Member price (<?= (int) $hiveDiscountPct ?>% off)</span>
                        <strong>&#8369; <?= number_format($memberPrice, 2) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="rd-reserve-row rd-reserve-now">
                        <span>Reserve now (50%)</span>
                        <strong>&#8369; <?= number_format($reserveFee, 2) ?></strong>
                    </div>
                    <div class="rd-reserve-row">
                        <span>Balance after accept</span>
                        <strong>&#8369; <?= number_format($reserveBalance, 2) ?></strong>
                    </div>
                </div>

                <div class="rd-dates">

                    <label>Select dates</label>

                    <!-- LONG TERM -->
                    <div class="rd-long-term">
                        <label class="rd-long-term-label">
                            <input
                                type="checkbox"
                                id="rd-long-term"
                                name="long_term"
                                value="1"
                                form="rd-inquiry-form"
                            >
                            <span>Long Term</span>
                        </label>
                    </div>

                    <div class="rd-date-summary" id="rd-date-summary">

                        <div class="rd-date-summary-field">
                            <span>Check-in</span>
                            <strong id="rd-checkin-display">Select date</strong>
                        </div>

                        <span class="rd-date-sep" id="rd-date-sep">&ndash;</span>

                        <div class="rd-date-summary-field" id="rd-checkout-summary-field">
                            <span>Check-out</span>
                            <strong id="rd-checkout-display">Select date</strong>
                        </div>

                    </div>

                    <div id="rd-calendar"></div>

                    <input type="hidden" id="rd-checkin" name="checkin_date" form="rd-inquiry-form">
                    <input type="hidden" id="rd-checkout" name="checkout_date" form="rd-inquiry-form">

                </div>

                <!-- GUESTS (fixed to the host-set capacity) -->

                <div class="rd-guests">

                    <label>Guests</label>

                    <div class="rd-guests-display" id="rd-guests-display">
                        <?= htmlspecialchars($listing['capacity_label'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <span class="rd-guests-note">
                        Max capacity person
                    </span>

                    <input
                        type="hidden"
                        id="rd-guests-value"
                        name="guests"
                        value="<?= htmlspecialchars($listing['capacity'], ENT_QUOTES, 'UTF-8') ?>"
                        form="rd-inquiry-form"
                    >

                </div>

                <?php if (!$isLoggedIn): ?>

                    <a href="/webprogg/auth/loginform.php" class="rd-btn rd-btn-primary" style="text-decoration:none; text-align:center;">
                        Log In to Send Inquiry
                    </a>

                <?php elseif ($isOwnListing && $isAlreadyListed): ?>

                    <button type="button" class="rd-btn rd-btn-primary" disabled>
                        &#10003; Already Listed
                    </button>

                    <p class="rd-listed-note">
                        This space is live &mdash; renters can now send inquiries.
                    </p>

                <?php elseif ($isOwnListing): ?>

                    <!-- Space uploaded but not yet published — host can pay & list -->
                    <form action="/webprogg/booking/listingpayment.php" method="GET">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <button type="submit" class="rd-btn rd-btn-primary">
                            List Now
                        </button>
                    </form>

                <?php elseif (!$isBookable): ?>

                    <button type="button" class="rd-btn rd-btn-primary" disabled>
                        <?= $isLongTermOccupied
                                ? '&#128336; Occupied &mdash; Long Term Stay'
                                : 'Already Booked' ?>
                    </button>

                    <?php if ($isLongTermOccupied): ?>
                        <p class="rd-listed-note">
                            This space is under a long-term stay.
                            It will be available again once the stay is finished.
                        </p>
                    <?php endif; ?>

                <?php else: ?>

                    <!-- Inquiry form — button DISABLED until dates are complete -->
                    <form id="rd-inquiry-form" action="/webprogg/booking/listingpayment.php" method="GET">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <button
                            type="submit"
                            class="rd-btn rd-btn-primary"
                            id="rd-inquiry-submit"
                            disabled
                        >
                            Send Inquiry
                        </button>

                        <p class="rd-date-hint" id="rd-date-hint">
                            Select check-in and check-out to continue
                        </p>
                    </form>

                <?php endif; ?>

                <!-- MESSAGE HOST — wired to start-conversation.php -->
                <?php if (!$isLoggedIn): ?>
                    <a href="/webprogg/auth/loginform.php" class="rd-btn rd-btn-outline" style="text-decoration:none; text-align:center; display:block;">
                        Message Host
                    </a>
                <?php elseif ($isOwnListing): ?>
                    <button type="button" class="rd-btn rd-btn-outline" disabled>
                        This is your listing
                    </button>
                <?php else: ?>
                    <a href="/webprogg/user/start-conversation.php?host_id=<?= (int) $listing['host']['id'] ?>&listing_id=<?= (int) $listing['id'] ?>"
                       class="rd-btn rd-btn-outline" style="text-decoration:none; text-align:center; display:block;">
                        Message Host
                    </a>
                <?php endif; ?>

                <?php if (!($isOwnListing && $isAlreadyListed)): ?>
                <p class="rd-charge-note">
                    &#128274; You won't be charged here &mdash; the next step is a 50% reserve to lock your dates
                </p>
                <?php endif; ?>

            </div>

            <!-- PROPERTY DETAILS CARD -->

            <div class="rd-card rd-details-card rd-reveal" style="--d: .28s;">

                <div class="rd-detail-row">
                    <img src="/webprogg/images/houselogo.png" alt="">
                    <span>Property Type</span>
                    <strong><?= htmlspecialchars($listing['category_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="rd-detail-row">
                    <img src="/webprogg/images/bedicon.png" alt="">
                    <span>Bedrooms</span>
                    <strong><?= htmlspecialchars($listing['bedrooms_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="rd-detail-row">
                    <img src="/webprogg/images/showericon.png" alt="">
                    <span>Bathrooms</span>
                    <strong><?= (int) $listing['bathrooms'] ?></strong>
                </div>

                <div class="rd-detail-row">
                    <img src="/webprogg/images/sizeicon.png" alt="">
                    <span>Size</span>
                    <strong><?= (int) $listing['size_sqm'] ?> m&sup2;</strong>
                </div>

                <div class="rd-detail-row">
                    <img src="/webprogg/images/flooricon.png" alt="">
                    <span>Floor</span>
                    <strong><?= htmlspecialchars($listing['floor'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="rd-detail-row">
                    <img src="/webprogg/images/caricon.png" alt="">
                    <span>Parking Lot</span>
                    <strong><?= htmlspecialchars($listing['parking'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

            </div>

            <!-- LOCATION CARD -->

            <div class="rd-card rd-location-card rd-reveal" style="--d: .34s;">

                <h3>Location</h3>

                <p><?= htmlspecialchars($listing['location_full'], ENT_QUOTES, 'UTF-8') ?></p>

                <?php if (!is_null($listing['latitude']) && !is_null($listing['longitude'])): ?>

                    <div
                        class="rd-map-embed"
                        id="rdMapEmbed"
                        data-lat="<?= htmlspecialchars($listing['latitude'], ENT_QUOTES, 'UTF-8') ?>"
                        data-lng="<?= htmlspecialchars($listing['longitude'], ENT_QUOTES, 'UTF-8') ?>"
                    ></div>

                    <p class="rd-map-exact-note">
                        &#128205; Exact pin dropped by the host
                    </p>

                <?php else: ?>

                    <div class="rd-map-placeholder">
                        <img src="/webprogg/images/MapPlaceholder.png" alt="Map preview">
                        <span class="rd-map-pin">&#128205;</span>
                    </div>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</main>

<!-- =========================================================
     NUDGE — VERIFY SUGGESTION MODAL
     Shown once per click, unverified renters only.
========================================================== -->
<div id="verifyNudgeModal" style="position:fixed;inset:0;z-index:3000;display:none;align-items:center;justify-content:center;background:rgba(15,25,20,.72);padding:20px;">
  <div style="background:#ffffff;border-radius:18px;max-width:400px;width:100%;padding:28px 24px 24px;text-align:center;box-shadow:0 30px 70px rgba(0,0,0,.4);">
    <div style="width:64px;height:64px;border-radius:50%;background:#FFF1DC;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 14px;">&#128737;&#65039;</div>
    <h3 style="margin:0 0 8px;font-size:18px;font-weight:800;color:#1c2a38;">Get Verified before you inquire</h3>
    <p style="margin:0 0 18px;font-size:13.5px;color:#5d6875;line-height:1.6;">
      Verified renters get <strong>priority approval</strong> from hosts.
      It's free and automatic &mdash; complete your profile (name, phone,
      age, location) and upload one valid ID.
    </p>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <a href="/webprogg/user/editprofile.php#verify-card"
         style="display:block;padding:13px;border-radius:10px;background:linear-gradient(135deg,#f6b93b,#eda423);color:#1c2a38;font-weight:800;font-size:13.5px;text-decoration:none;">
        &#10003; Get Verified Now
      </a>
      <button type="button" id="verifyNudgeSkip"
              style="padding:12px;border:1px solid #e3e7ec;border-radius:10px;background:#ffffff;color:#1c2a38;font-family:inherit;font-weight:700;font-size:13px;cursor:pointer;">
        Continue without verifying
      </button>
    </div>
  </div>
</div>

<!-- Leaflet + flatpickr libraries -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
/* =========================
   SERVER DATA FOR JS
========================== */
window.rhUnavailable = <?php
    echo json_encode(
        $unavailableRangesJs,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
?>;

/* =========================
   MAP — Leaflet
========================== */
(function () {
    var embed = document.getElementById('rdMapEmbed');
    if (!embed || typeof L === 'undefined') { return; }

    var lat = parseFloat(embed.getAttribute('data-lat'));
    var lng = parseFloat(embed.getAttribute('data-lng'));

    if (isNaN(lat) || isNaN(lng)) { return; }

    var map = L.map(embed, { scrollWheelZoom: false }).setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
})();

/* =========================
   DATE PICKER — flatpickr
   (range for normal stays, single date for Long Term)
========================== */
(function () {
    var calEl      = document.getElementById('rd-calendar');
    var longTerm   = document.getElementById('rd-long-term');
    var checkinEl  = document.getElementById('rd-checkin');
    var checkoutEl = document.getElementById('rd-checkout');
    var inDisp     = document.getElementById('rd-checkin-display');
    var outDisp    = document.getElementById('rd-checkout-display');
    var outField   = document.getElementById('rd-checkout-summary-field');
    var sep        = document.getElementById('rd-date-sep');
    var submitBtn  = document.getElementById('rd-inquiry-submit');
    var hint       = document.getElementById('rd-date-hint');

    if (!calEl || typeof flatpickr === 'undefined') { return; }

    /* build the disable list from unavailable ranges */
    var disabledRanges = [];
    (window.rhUnavailable || []).forEach(function (r) {
        if (r.start && r.end) {
            disabledRanges.push({ from: r.start, to: r.end });
        } else if (r.start) {
            /* long-term occupancy: block from check-in onward */
            disabledRanges.push({ from: r.start, to: '2100-12-31' });
        }
    });

    var picker = null;

    function refreshState() {
        var hasIn  = !!(checkinEl && checkinEl.value);
        var hasOut = !!(checkoutEl && checkoutEl.value);
        var long   = !!(longTerm && longTerm.checked);

        if (inDisp && hasIn) {
            inDisp.textContent = flatpickr.formatDate(
                flatpickr.parseDate(checkinEl.value, 'Y-m-d'), 'M j, Y'
            );
        } else if (inDisp) {
            inDisp.textContent = 'Select date';
        }

        if (outDisp && hasOut && !long) {
            outDisp.textContent = flatpickr.formatDate(
                flatpickr.parseDate(checkoutEl.value, 'Y-m-d'), 'M j, Y'
            );
        } else if (outDisp) {
            outDisp.textContent = long ? 'Open-ended' : 'Select date';
        }

        var ok = long ? hasIn : (hasIn && hasOut);

        if (submitBtn) { submitBtn.disabled = !ok; }
        if (hint) {
            hint.textContent = ok
                ? '\u2713 Dates ready \u2014 continue to reserve'
                : (long
                    ? 'Select your move-in date to continue'
                    : 'Select check-in and check-out to continue');
            hint.className = 'rd-date-hint' + (ok ? ' rd-hint-ok' : '');
        }
    }

    function initPicker() {
        var long = !!(longTerm && longTerm.checked);

        if (picker) { picker.destroy(); picker = null; }

        if (!long) {
            picker = flatpickr(calEl, {
                inline: true,
                mode: 'range',
                minDate: 'today',
                dateFormat: 'Y-m-d',
                disable: disabledRanges,
                onChange: function (selectedDates) {
                    if (selectedDates.length >= 1) {
                        checkinEl.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                    }
                    if (selectedDates.length >= 2) {
                        checkoutEl.value = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else {
                        checkoutEl.value = '';
                    }
                    refreshState();
                }
            });
        } else {
            picker = flatpickr(calEl, {
                inline: true,
                mode: 'single',
                minDate: 'today',
                dateFormat: 'Y-m-d',
                disable: disabledRanges,
                onChange: function (selectedDates) {
                    if (selectedDates.length >= 1) {
                        checkinEl.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                    } else {
                        checkinEl.value = '';
                    }
                    checkoutEl.value = '';
                    refreshState();
                }
            });
        }

        refreshState();
    }

    /* long-term toggle: swap modes + hide the checkout field */
    if (longTerm) {
        longTerm.addEventListener('change', function () {
            var long = longTerm.checked;

            if (outField) { outField.classList.toggle('rd-date-hidden', long); }
            if (sep)      { sep.classList.toggle('rd-date-hidden', long); }
            if (checkoutEl) { checkoutEl.value = ''; }

            initPicker();
        });
    }

    initPicker();
})();

/* =========================
   GALLERY
========================== */
(function () {
    var img    = document.getElementById('rd-gallery-image');
    var count  = document.getElementById('rd-gallery-count');
    var prev   = document.getElementById('rd-gallery-prev');
    var next   = document.getElementById('rd-gallery-next');
    var thumbs = document.getElementById('rd-gallery-thumbs');

    if (!img) { return; }

    /* the PHP page already renders the list; rebuild from DOM */
    var sources = [];
    if (thumbs) {
        thumbs.querySelectorAll('img.rd-thumb').forEach(function (t) {
            sources.push(t.getAttribute('src'));
        });
    } else {
        sources.push(img.getAttribute('src'));
    }

    var index = 0;

    function show(i) {
        index = (i + sources.length) % sources.length;
        img.src = sources[index];
        if (count) { count.textContent = (index + 1) + ' / ' + sources.length; }
        if (thumbs) {
            thumbs.querySelectorAll('img.rd-thumb').forEach(function (t, ti) {
                t.classList.toggle('active', ti === index);
            });
        }
    }

    if (prev) { prev.addEventListener('click', function () { show(index - 1); }); }
    if (next) { next.addEventListener('click', function () { show(index + 1); }); }

    if (thumbs) {
        thumbs.querySelectorAll('img.rd-thumb').forEach(function (t) {
            t.addEventListener('click', function () {
                show(parseInt(t.getAttribute('data-index'), 10) || 0);
            });
        });
    }
})();

/* =========================
   ABOUT — SHOW MORE / LESS
========================== */
(function () {
    var btn  = document.getElementById('rd-show-more');
    var text = document.getElementById('rd-about-text');

    if (!btn || !text) { return; }

    text.style.maxHeight = '120px';
    text.style.overflow = 'hidden';
    text.style.transition = 'max-height .3s ease';

    var open = false;

    btn.addEventListener('click', function () {
        open = !open;
        text.style.maxHeight = open ? 'none' : '120px';
        btn.innerHTML = open
            ? 'Show less &#9652;'
            : 'Show more &#9662;';
    });
})();

/* =========================
   SAVE (wishlist) + SHARE
========================== */
(function () {
    var saveBtn = document.getElementById('rd-save-btn');

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var listingId = new URLSearchParams(window.location.search).get('id');
            if (!listingId) { return; }

            fetch('/webprogg/user/togglewishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId),
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var saved = !!data.saved;
                    saveBtn.classList.toggle('active', saved);
                    var heart = saveBtn.querySelector('.rd-heart-icon');
                    if (heart) {
                        heart.innerHTML = saved ? '&#9829;' : '&#9825;';
                    }
                } else if (data && data.login) {
                    window.location.href = '/webprogg/auth/loginform.php';
                } else {
                    alert((data && data.message) || 'Could not update your wishlist.');
                }
            })
            .catch(function () {
                alert('Something went wrong. Please try again.');
            });
        });
    }

    var shareBtn = document.getElementById('rd-share-btn');

    if (shareBtn) {
        shareBtn.addEventListener('click', function () {
            var url = window.location.href;
            var title = document.title;

            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    alert('Link copied to clipboard!');
                }).catch(function () {
                    prompt('Copy this link:', url);
                });
            } else {
                prompt('Copy this link:', url);
            }
        });
    }
})();
</script>

<!-- =========================================================
     NUDGE — VERIFY SUGGESTION SCRIPT
     Intercepts Send Inquiry ONCE for unverified renters.
========================================================== -->
<script>
(function () {
    "use strict";

    /* PHP decides: only unverified renters get true */
    var nudgeEnabled = <?php echo $showVerifyNudge ? 'true' : 'false'; ?>;

    var form    = document.getElementById('rd-inquiry-form');
    var modal   = document.getElementById('verifyNudgeModal');
    var skipBtn = document.getElementById('verifyNudgeSkip');
    var nudged  = false;

    if (!nudgeEnabled || !form || !modal || !skipBtn) { return; }

    /* First click: suggest verification instead of navigating */
    form.addEventListener('submit', function (e) {
        if (nudged) { return; }   /* second click proceeds */
        e.preventDefault();
        e.stopPropagation();
        modal.style.display = 'flex';
    }, true);

    /* "Continue without verifying" — submit for real this time */
    skipBtn.addEventListener('click', function () {
        nudged = true;
        modal.style.display = 'none';
        form.submit();
    });

    /* Backdrop click closes the suggestion */
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });
})();
</script>

</body>

</html>