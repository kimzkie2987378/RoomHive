<?php
/* =========================
   listing-detail.php
========================== */
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
   FLAGS FROM book.php REDIRECTS
========================== */
$showUnavailableNotice = isset($_GET['unavailable']);
$showOwnBookingNotice  = isset($_GET['ownbooking']);

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
   (mirrors $capacityOptions in host-step2.php so the value
   the host picked there renders identically here)
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
   Pulled from the real `listings` table, joined against the
   owning host's user row and their photos/reviews.
========================== */

$listingId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

/* FIX: also pull u.avatar_path (aliased host_avatar_path) so the
   "Meet your host" card can show the host's REAL profile photo
   instead of a hardcoded default image, same as hostprofile.php /
   userprofile.php already do for the logged-in user's own avatar. */
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

    // Full URL
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Normalize Windows paths
    $path = str_replace('\\', '/', $path);

    // Remove leading slash
    $path = ltrim($path, '/');

    /*
     * Your actual listing image folders:
     *
     * /webprogg/uploads/listing_photos/cover/
     * /webprogg/uploads/listing_photos/additional/
     *
     * (host-step3.php writes here using an ABSOLUTE path built
     * from $_SERVER['DOCUMENT_ROOT'] and stores the RELATIVE
     * path "uploads/listing_photos/..." in the DB — no leading
     * "/webprogg/" and no "host/" segment.)
     */

    // If database already contains the full web path
    if (stripos($path, 'webprogg/') === 0) {
        return '/' . $path;
    }

    // If database contains host/uploads/... (legacy rows saved
    // before the host-step3.php path fix — those files really do
    // live under /webprogg/host/uploads/...)
    if (stripos($path, 'host/uploads/') === 0) {
        return '/webprogg/' . $path;
    }

    // If database contains uploads/listing_photos/...
    // FIX: this must resolve to /webprogg/uploads/listing_photos/...
    // to match where host-step3.php actually writes the file on
    // disk. The old code prepended "/webprogg/host/" here, which
    // pointed at a directory that doesn't exist, so every newly
    // uploaded photo 404'd on this page.
    if (stripos($path, 'uploads/listing_photos/') === 0) {
        return '/webprogg/' . $path;
    }

    // If database contains only the filename
    if (stripos($path, 'cover_') === 0) {
        return '/webprogg/uploads/listing_photos/cover/' . basename($path);
    }

    // Fallback
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

/* Whether this listing is currently bookable (approved AND
   no active booking) — used to decide whether to show the
   "Send Inquiry" button or an "Already booked" state. */
/* =========================================================
   LISTING AVAILABILITY
   The listing remains available for inquiry if it is approved.
   Individual confirmed/pending booking dates are disabled in
   the calendar below.
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
   Both CONFIRMED bookings and PENDING holds block dates on
   the calendar — a pending booking means someone else is
   mid-checkout for those dates, so they shouldn't look free.
   The authoritative double-booking guard still lives in
   book.php (transaction + row lock at insert time); this
   query is only for what the calendar displays.
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

$isOwnListing = $isLoggedIn && (int) $listingRow['user_id'] === (int) ($_SESSION['user_id'] ?? 0);

/* =========================
   MY APPLICATION STATUS
   If the logged-in visitor has ever applied (booked/sent an
   inquiry) for this specific listing, pull the status of
   their most recent application so we can show them where
   it stands with the host (pending / accepted / rejected).
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

/* Assemble into the shape the template below expects */
$listing = [
    'id'             => (int) $listingRow['id'],
    'title'          => $listingRow['title'],
    'gallery'        => $galleryImages,
    'location_label' => $listingRow['location'],
    'location_full'  => $listingRow['exact_address'] . ', ' . $listingRow['location'],
    'category_label' => $listingRow['category'],
    'price'          => (float) $listingRow['price'],
    'amenities'      => json_decode($listingRow['amenities'] ?? '[]', true) ?? [],
    'bedrooms'       => (int) $listingRow['bedrooms'],
    'bedrooms_label' => $listingRow['bedrooms'] . ' ' . ($listingRow['bedrooms'] == 1 ? 'Bedroom' : 'Bedrooms'),
    'bathrooms'      => (int) $listingRow['bathrooms'],
    'size_sqm'       => (float) $listingRow['size_sqm'],
    'floor'          => $listingRow['floor'],
    'parking'        => $listingRow['parking'],

    // Guest capacity — pulled straight from the value the host
    // picked on host-step2.php (the `capacity` column on
    // `listings`), so the detail page can never disagree with
    // what the host actually set.
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

        // FIX: use the host's real saved avatar_path (same column
        // hostprofile.php / userprofile.php read) instead of a
        // hardcoded default image. avatar_path is stored as a full
        // "/webprogg/..." path by uploadavatar.php, so it's already
        // web-resolvable as-is — no resolve_photo()-style rewrite
        // needed like the listing cover photos above.
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/listing-detail.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

.rd-notice {
    max-width: 900px;
    margin: 16px auto 0;
    padding: 12px 18px;
    border-radius: 10px;
    background: #fff4e8;
    border: 1px solid #f7941d;
    color: #8a5a10;
    font-size: 0.9rem;
}

.rd-application-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    width: fit-content;
}

.rd-application-status-pending {
    background: #fff4e0;
    color: #8a5a10;
    border: 1px solid #f7941d;
}

.rd-application-status-confirmed {
    background: #e6f6ec;
    color: #1e7a3d;
    border: 1px solid #2ecc71;
}

.rd-application-status-rejected {
    background: #fdecec;
    color: #a3282e;
    border: 1px solid #e14b4b;
}

.rd-application-status-cancelled {
    background: #f0f0f0;
    color: #666666;
    border: 1px solid #cccccc;
}
</style>

<?php endif; ?>

<!-- =========================
     LISTING DETAIL PAGE
========================== -->

<main class="listing-detail-page">

    <!-- TOP BAR -->

    <div class="rd-topbar">

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
            Sorry, this space was just booked by someone else. Browse other available spaces below.
        </div>
    <?php elseif ($showOwnBookingNotice): ?>
        <div class="rd-notice">
            You can't book your own listing.
        </div>
    <?php endif; ?>

    <div class="rd-layout">

        <!-- =========================
             LEFT COLUMN
        ========================== -->

        <div class="rd-main">

            <!-- GALLERY -->

            <div class="rd-gallery">

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

            <h1 class="rd-title">
                <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <div class="rd-subline">

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

            <div class="rd-amenities">

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

            <section class="rd-about">

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
            <section class="rd-about">
                <h2>House Rules</h2>
                <p class="rd-about-text">
                    <?= nl2br(htmlspecialchars($listing['house_rules'], ENT_QUOTES, 'UTF-8')) ?>
                </p>
            </section>
            <?php endif; ?>

            <!-- MEET YOUR HOST -->

            <section class="rd-host">

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

                    <!-- FIX: was href="#" (dead link). Point at the
                         public host-profile route, keyed by host id.
                         Rename the target file/path below to match
                         whatever this project's actual public host
                         profile page is called. -->
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

            <div class="rd-card rd-booking-card">

                <div class="rd-price">
                    <span class="rd-peso">&#8369;</span>
                    <?= number_format($listing['price']) ?>
                    <span class="rd-per">/ month</span>
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

                        <span class="rd-date-sep">&ndash;</span>

                        <div class="rd-date-summary-field" id="rd-checkout-summary-field">
                            <span>Check-out</span>
                            <strong id="rd-checkout-display">Select date</strong>
                        </div>

                    </div>

                    <div id="rd-calendar"></div>

                    <input type="hidden" id="rd-checkin" name="checkin_date" form="rd-inquiry-form">
                    <input type="hidden" id="rd-checkout" name="checkout_date" form="rd-inquiry-form">

                </div>

                <!-- =============================================
                     GUESTS
                     Was previously a free <select> the guest could
                     pick any number from — that let a renter choose
                     more guests than the space's actual capacity.
                     Now it's a fixed, read-only display driven by
                     the `capacity` value the host set on
                     host-step2.php, with a hidden field so the
                     value still posts to listingpayment.php exactly
                     like before.
                ============================================== -->

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

                <?php elseif ($isOwnListing): ?>

                    <form action="/webprogg/booking/listingpayment.php" method="GET">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <button type="submit" class="rd-btn rd-btn-primary">
                            List Now
                        </button>
                    </form>

                <?php elseif (!$isBookable): ?>

                    <button type="button" class="rd-btn rd-btn-primary" disabled>
                        Already Booked
                    </button>

                <?php else: ?>

                    <form id="rd-inquiry-form" action="/webprogg/booking/listingpayment.php" method="GET">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <button type="submit" class="rd-btn rd-btn-primary">
                            Send Inquiry
                        </button>
                    </form>

                <?php endif; ?>

                <button type="button" class="rd-btn rd-btn-outline">
                    Message Host
                </button>

                <p class="rd-charge-note">
                    &#128274; Don't worry, you won't be charged yet
                </p>

            </div>

            <!-- PROPERTY DETAILS CARD -->

            <div class="rd-card rd-details-card">

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

            <div class="rd-card rd-location-card">

                <h3>Location</h3>

                <p><?= htmlspecialchars($listing['location_full'], ENT_QUOTES, 'UTF-8') ?></p>

                <div class="rd-map-placeholder">
                    <img src="/webprogg/images/MapPlaceholder.png" alt="Map preview">
                    <span class="rd-map-pin">&#128205;</span>
                </div>

                <a
                    href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($listing['location_full']) ?>"
                    target="_blank"
                    rel="noopener"
                    class="rd-btn rd-btn-outline rd-map-btn"
                >
                    View on Google Maps &#8599;
                </a>

            </div>

        </aside>

    </div>

</main>

<!-- =========================
     FOOTER
     (identical to listing.php)
========================== -->

<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->

        <div class="footer-brand">

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive Logo"
                class="footer-logo"
            >

            <p class="footer-tagline">
                Find your next room, studio, or shared space —
                verified listings, no hidden fees.
            </p>

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt=""
                >

                <span>
                    0917 156 3974
                </span>

            </div>

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt=""
                >

                <span>
                    iamroomhivehost@gmail.com
                </span>

            </div>

        </div>

        <!-- LISTINGS -->

        <div class="footer-links">

            <span class="footer-heading">
                LISTINGS
            </span>

            <a href="/webprogg/Listings/listing.php?category=studioloft">
                Studios
            </a>

            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">
                Shared Rooms
            </a>

            <a href="/webprogg/Listings/listing.php?category=entirehouse">
                Entire House
            </a>

            <a href="/webprogg/Listings/listing.php">
                Featured Stays
            </a>

        </div>

        <!-- QUICK LINKS -->

        <div class="footer-links">

            <span class="footer-heading">
                QUICK LINKS
            </span>

            <a href="/webprogg/index.php">
                About Us
            </a>

            <a href="/webprogg/misc/contacts.php">
                Contact
            </a>

            <a href="/webprogg/host/becomeahost.php">
                Become a Host
            </a>

            <a href="/webprogg/hiveclub.php">
                Hive Club
            </a>

        </div>

        <!-- GET THE APP -->

        <div class="footer-contact">

            <span class="footer-heading">
                GET THE APP
            </span>

            <div class="footer-app-badges">

                <img
                    src="/webprogg/images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >

                <img
                    src="/webprogg/images/AppStore.jpg"
                    alt="Download on the App Store"
                >

            </div>

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            &copy; <?= date('Y') ?>
            RoomHive. All rights reserved.
        </p>

    </div>

</footer>

<!-- =========================
     PAGE JAVASCRIPT
========================== -->

<!-- Flatpickr must load BEFORE the inline script below, since
     that script calls flatpickr() as soon as it runs. -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
/* =========================================================
   ROOMHIVE — UNAVAILABLE BOOKING DATES
   Includes both confirmed bookings and pending holds.
========================================================= */

const roomHiveUnavailableRanges = <?= json_encode(
    $unavailableRanges,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;
/* =========================
   BACK BUTTON
========================== */

(function () {

    const backLink = document.getElementById('rd-back-link');

    if (!backLink) {
        return;
    }

    backLink.addEventListener('click', function (event) {

        const cameFromListings = document.referrer &&
            document.referrer.indexOf('/webprogg/Listings/listing.php') !== -1;

        if (cameFromListings && window.history.length > 1) {
            event.preventDefault();
            window.history.back();
        }

        // otherwise let it fall through to href="/webprogg/Listings/listing.php"

    });

})();
                    
/* =========================
   GALLERY
========================== */

(function () {

    const images = <?= json_encode(array_values($galleryImages)) ?>;

    const mainImage = document.getElementById('rd-gallery-image');
    const counter = document.getElementById('rd-gallery-count');
    const prevBtn = document.getElementById('rd-gallery-prev');
    const nextBtn = document.getElementById('rd-gallery-next');
    const thumbs = document.querySelectorAll('.rd-thumb');
    const thumbsTrack = document.getElementById('rd-gallery-thumbs');
    const thumbsPrevBtn = document.getElementById('rd-thumbs-prev');
    const thumbsNextBtn = document.getElementById('rd-thumbs-next');
    const galleryMain = document.querySelector('.rd-gallery-main');

    let currentIndex = 0;

    function scrollActiveThumbIntoView() {

        const activeThumb = document.querySelector('.rd-thumb.active');

        if (activeThumb && thumbsTrack) {
            activeThumb.scrollIntoView({
                behavior: 'smooth',
                inline: 'center',
                block: 'nearest'
            });
        }

    }

    function showImage(index) {

        if (!images.length) {
            return;
        }

        currentIndex = (index + images.length) % images.length;

        mainImage.src = images[currentIndex];

        if (counter) {
            counter.textContent = (currentIndex + 1) + ' / ' + images.length;
        }

        thumbs.forEach(function (thumb) {
            thumb.classList.toggle(
                'active',
                Number(thumb.dataset.index) === currentIndex
            );
        });

        scrollActiveThumbIntoView();

    }

    /* MAIN IMAGE SLIDE BUTTONS */

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            showImage(currentIndex - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            showImage(currentIndex + 1);
        });
    }

    /* KEYBOARD ARROWS (when the gallery has focus) */

    if (galleryMain) {

        galleryMain.setAttribute('tabindex', '0');

        galleryMain.addEventListener('keydown', function (event) {

            if (event.key === 'ArrowLeft') {
                showImage(currentIndex - 1);
            } else if (event.key === 'ArrowRight') {
                showImage(currentIndex + 1);
            }

        });

    }

    /* SWIPE ON MAIN IMAGE (touch devices) */

    if (galleryMain) {

        let touchStartX = 0;

        galleryMain.addEventListener('touchstart', function (event) {
            touchStartX = event.changedTouches[0].screenX;
        }, { passive: true });

        galleryMain.addEventListener('touchend', function (event) {

            const touchEndX = event.changedTouches[0].screenX;
            const delta = touchEndX - touchStartX;

            if (Math.abs(delta) > 40) {
                showImage(delta < 0 ? currentIndex + 1 : currentIndex - 1);
            }

        }, { passive: true });

    }

    /* THUMBNAIL CLICKS */

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            showImage(Number(thumb.dataset.index));
        });
    });

    /* THUMBNAIL STRIP SLIDE BUTTONS
       (scrolls the strip itself, independent from
       which image is currently shown) */

    function scrollThumbsBy(amount) {

        if (thumbsTrack) {
            thumbsTrack.scrollBy({
                left: amount,
                behavior: 'smooth'
            });
        }

    }

    if (thumbsPrevBtn) {
        thumbsPrevBtn.addEventListener('click', function () {
            scrollThumbsBy(-220);
        });
    }

    if (thumbsNextBtn) {
        thumbsNextBtn.addEventListener('click', function () {
            scrollThumbsBy(220);
        });
    }

})();

/* =========================================================
   ROOMHIVE — FLATPICKR RANGE CALENDAR
   A single inline calendar. Guests click a start date and an
   end date to select a range (or one date, in Long Term mode).
   Disables dates already occupied by confirmed bookings or
   pending holds. The final availability check still happens
   server-side in book.php — this is display/UX only.
========================================================= */

(function () {

    const calendarEl = document.getElementById('rd-calendar');
    const checkinInput = document.getElementById('rd-checkin');
    const checkoutInput = document.getElementById('rd-checkout');
    const longTermInput = document.getElementById('rd-long-term');
    const checkinDisplay = document.getElementById('rd-checkin-display');
    const checkoutDisplay = document.getElementById('rd-checkout-display');
    const checkoutSummaryField = document.getElementById('rd-checkout-summary-field');

    if (!calendarEl || !checkinInput || !checkoutInput || !longTermInput) {
        return;
    }

    const unavailableRanges = Array.isArray(roomHiveUnavailableRanges)
        ? roomHiveUnavailableRanges
        : [];

    /* flatpickr's {from, to} disable range is INCLUSIVE of both
       ends. A guest only actually occupies the nights from
       check-in up to (but not including) checkout — they leave
       on the checkout day, so that day should stay bookable for
       someone else. Subtracting one day from checkout_date here
       keeps the checkout date itself selectable instead of
       blocking it along with the nights that were really taken. */
    function roomHiveSubtractOneDay(dateStr) {

        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() - 1);

        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');

        return year + '-' + month + '-' + day;

    }

    const disabledRanges = unavailableRanges
        .filter(function (range) {
            return range.checkin_date;
        })
        .map(function (range) {

            let to = range.checkin_date;

            if (range.checkout_date) {

                const adjusted = roomHiveSubtractOneDay(range.checkout_date);

                // Guard against a same-day or invalid checkout_date
                // collapsing the range below check-in.
                to = adjusted >= range.checkin_date
                    ? adjusted
                    : range.checkin_date;

            }

            return {
                from: range.checkin_date,
                to: to
            };

        });

    function formatDisplay(dateStr) {

        if (!dateStr) {
            return 'Select date';
        }

        const d = new Date(dateStr + 'T00:00:00');

        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });

    }

    function resetSelection() {

        checkinInput.value = '';
        checkoutInput.value = '';
        checkinDisplay.textContent = 'Select date';
        checkoutDisplay.textContent = 'Select date';

    }

    let calendar = null;

    function buildCalendar(isLongTerm) {

        if (calendar) {
            calendar.destroy();
        }

        resetSelection();

        calendar = flatpickr(calendarEl, {

            inline: true,
            mode: isLongTerm ? 'single' : 'range',
            minDate: 'today',
            dateFormat: 'Y-m-d',
            disable: disabledRanges,
            showMonths: 1,

            onChange: function (selectedDates) {

                if (isLongTerm) {

                    if (selectedDates.length) {
                        checkinInput.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        checkinDisplay.textContent = formatDisplay(checkinInput.value);
                    }

                    return;

                }

                if (selectedDates.length === 2) {

                    checkinInput.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                    checkoutInput.value = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    checkinDisplay.textContent = formatDisplay(checkinInput.value);
                    checkoutDisplay.textContent = formatDisplay(checkoutInput.value);

                } else if (selectedDates.length === 1) {

                    checkinInput.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                    checkoutInput.value = '';
                    checkinDisplay.textContent = formatDisplay(checkinInput.value);
                    checkoutDisplay.textContent = 'Select date';

                } else {

                    resetSelection();

                }

            }

        });

    }

    buildCalendar(false);

    longTermInput.addEventListener('change', function () {

        const isLongTerm = longTermInput.checked;

        if (checkoutSummaryField) {
            checkoutSummaryField.style.display = isLongTerm ? 'none' : '';
        }

        buildCalendar(isLongTerm);

    });

    /* =====================================================
       FORM VALIDATION
    ===================================================== */

    const inquiryForm = document.getElementById('rd-inquiry-form');

    if (inquiryForm) {

        inquiryForm.addEventListener('submit', function (event) {

            /*
             * Check-in is always required.
             */
            if (!checkinInput.value) {

                event.preventDefault();

                alert('Please select a check-in date.');

                return;

            }

            /*
             * Long Term does not need checkout.
             */
            if (longTermInput.checked) {
                checkoutInput.value = '';
                return;
            }

            /*
             * Normal booking requires checkout.
             */
            if (!checkoutInput.value) {

                event.preventDefault();

                alert('Please select a check-out date, or choose Long Term.');

                return;

            }

        });

    }

})();

/* =========================
   SAVE BUTTON
========================== */

const rdSaveBtn = document.getElementById('rd-save-btn');

if (rdSaveBtn) {

    rdSaveBtn.addEventListener('click', function () {

        rdSaveBtn.classList.toggle('active');

        const heart = rdSaveBtn.querySelector('.rd-heart-icon');

        if (heart) {
            heart.innerHTML = rdSaveBtn.classList.contains('active')
                ? '&#9829;'
                : '&#9825;';
        }

    });

}

/* =========================
   SHARE BUTTON
========================== */

const rdShareBtn = document.getElementById('rd-share-btn');

if (rdShareBtn) {

    rdShareBtn.addEventListener('click', function () {

        if (navigator.share) {

            navigator.share({
                title: document.title,
                url: window.location.href
            });

        } else {

            navigator.clipboard.writeText(window.location.href);
            alert('Link copied to clipboard!');

        }

    });

}

/* =========================
   ABOUT — SHOW MORE
========================== */

const rdAboutText = document.getElementById('rd-about-text');
const rdShowMoreBtn = document.getElementById('rd-show-more');

if (rdAboutText && rdShowMoreBtn) {

    rdShowMoreBtn.addEventListener('click', function () {

        rdAboutText.classList.toggle('rd-expanded');

        rdShowMoreBtn.innerHTML = rdAboutText.classList.contains('rd-expanded')
            ? 'Show less &#9652;'
            : 'Show more &#9662;';

    });

}       

</script>
<!-- MAIN JAVASCRIPT (handles account dropdown open/close) -->
<script src="/webprogg/assets/javaScript.js"></script>

</body>
</html>