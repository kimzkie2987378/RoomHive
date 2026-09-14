<?php
    /* =========================
    listing.php
    ========================== */
    session_start();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

    /* =========================
    STALE BOOKING HOLD CLEANUP
    Free up any 'pending' bookings that were never paid within
    the hold window (10 minutes), so those listings reappear
    here automatically. Paid holds and confirmed bookings are
    left untouched — see booking_helpers.php for the full rule.
    Must run BEFORE the $allListings query below, since that
    query is what decides which listings currently count as
    "taken".
    ========================== */
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/booking_helpers.php';
    roomhive_expire_stale_bookings($pdo);

    /* =========================
    NEGROS ORIENTAL LOCATION DATA
    ========================== */
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/negros-oriental-locations.php';
    // Provides: $province (string) and $negrosOrientalLocations (array)

    /* =========================
    LOGIN STATUS
    ========================== */
    $isLoggedIn = (
        isset($_SESSION["logged_in"]) &&
        $_SESSION["logged_in"] === true
    );

    /* NEW — FLOATING LOGIN MODAL
    Guests get the login card popped over the page once per
    browser session. Flip to false to disable auto-open
    (the modal still opens from BECOME A HOST / Save-search links). */
    $autoOpenLoginPopup = !$isLoggedIn && empty($_SESSION['admin_logged_in']);

    /* =========================
    USER
    ========================== */
    $userName = $_SESSION['user_name'] ?? 'Guest';

    /* =========================
    KEEP is_host IN SYNC WITH THE DATABASE
    $_SESSION['is_host'] is only set at login time, so if a
    host application gets approved (or status otherwise
    changes) mid-session, the flag goes stale and the nav
    keeps showing a Host Profile link that's missing (or vice
    versa). Re-check the real column on every load.
    ========================== */
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
        $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
        $hostRow = $hostCheckStmt->fetch();
        $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
        $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
    }

    /* Same staleness reasoning as is_host above: the navbar's
    account icon should reflect a freshly-uploaded profile photo
    without requiring the user to log out and back in. */
    $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

    /* Notification bell badge count — same placeholder used across
       every logged-in page's navbar until real notifications land. */
    $notification_count = 0;

    /* =========================
    NAVIGATION
    ========================== */
    $navigation = [
        "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
        "LISTINGS" => "/webprogg/Listings/listing.php",
        "HOW IT WORKS" => "/webprogg/host/howitworks.php",
        "BECOME A HOST" => $isLoggedIn ? "/webprogg/host/becomeahost.php" : "/webprogg/auth/loginform.php",
        "HIVE CLUB" => "/webprogg/hiveclub.php",
        "CONTACTS" => "/webprogg/misc/contacts.php"
    ];
    $currentPage = $navigation['LISTINGS'];
    $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

    /* =========================
    LISTINGS DATA (category tiles at top of page — unrelated
    to the real $allListings query below, kept as-is)
    ========================== */
    $listings = [
        [
            'name'  => 'STUDIO LOFT',
            'image' => '/webprogg/images/StudioLoft.png',
            'slug'  => 'studioloft'
        ],
        [
            'name'  => 'SHARED ROOM',
            'image' => '/webprogg/images/SharedBedroom.png',
            'slug'  => 'sharedbedroom'
        ],
        [
            'name'  => 'ENTIRE HOUSE',
            'image' => '/webprogg/images/EntireHouse.png',
            'slug'  => 'entirehouse'
        ],
        [
            'name'  => 'PRIVATE ROOM',
            'image' => '/webprogg/images/PrivateRoom.png',
            'slug'  => 'privateroom'
        ],
        [
            'name'  => 'BOARDING HOUSE',
            'image' => '/webprogg/images/BoardingHouse.png',
            'slug'  => 'boardinghouse'
        ],
        [
            'name'  => 'APARTMENT',
            'image' => '/webprogg/images/Apartment.png',
            'slug'  => 'apartment'
        ],
    ];

    /* =========================
    ALL LISTINGS
    Pulled from the real `listings` table (joined against
    listing_photos for the cover image). Only approved
    listings with no active booking show up here — the
    moment a listing gets booked, it disappears from this
    page automatically. Stale unpaid holds were already
    swept above, so this NOT EXISTS check now only matches
    real (paid-pending or confirmed) holds.
    ========================== */

    $listingsStmt = $pdo->query(
        "SELECT l.id, l.title, l.category, l.location, l.exact_address, l.price,
                l.bedrooms, l.amenities, l.created_at,
                p.photo_path AS cover_photo
        FROM listings l
        LEFT JOIN listing_photos p
                ON p.listing_id = l.id AND p.photo_type = 'cover'
        WHERE l.status = 'approved'
        AND NOT EXISTS (
            SELECT 1 FROM bookings b
            WHERE b.listing_id = l.id
                AND b.status = 'pending'
        )
        ORDER BY l.created_at DESC"
    );
    $allListings = array_map(function ($row) {
        return [
            'id'             => (int) $row['id'],
            'title'          => $row['title'],
            /*
             * FIX: cover photos are written to disk by
             * host-step3.php using an ABSOLUTE path built from
             * $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos/cover/'
             * — there is no "host/" segment in that real
             * directory. The old code here hardcoded
             * '/webprogg/host/uploads/listing_photos/cover/',
             * which pointed at a folder that doesn't exist, so
             * every cover photo 404'd on this page (both the
             * "Explore More Spaces" carousel and the main
             * listing grid).
             */
            'image' => !empty($row['cover_photo'])
                ? '/webprogg/uploads/listing_photos/cover/' . basename($row['cover_photo'])
                : '/webprogg/images/ListingPlaceholder.png',
            'location'       => $row['location'],
            'location_label' => $row['location'],
            'category'       => $row['category'],
            'price'          => (float) $row['price'],
            'bedrooms'       => (int) $row['bedrooms'],
            'amenities'      => json_decode($row['amenities'] ?? '[]', true) ?? [],
            'rating'         => 0,
            'reviews'        => 0,
            'verified'       => false,
            'date_added'     => $row['created_at'],
        ];
    }, $listingsStmt->fetchAll());

    /* =========================
    LOCATIONS
    (derived from negros-oriental-locations.php — all 25
    cities/municipalities in the province, since RoomHive
    currently serves Negros Oriental only)
    ========================== */
    $locations = [];
    foreach ($negrosOrientalLocations as $citySlug => $cityData) {
        $locations[$citySlug] = ($cityData['type'] === 'city' ? 'City of ' : '') . $cityData['label'];
    }

    /* =========================
    CATEGORIES
    ========================== */
    $categories = [
        'apartment'     => 'Apartment',
        'boardinghouse' => 'Boarding House',
        'privateroom'   => 'Private Room',
        'entirehouse'   => 'Entire House',
        'sharedbedroom' => 'Shared Bedroom',
        'studioloft'    => 'Studio Loft',
    ];

    /* =========================
    AMENITIES
    ========================== */
    $amenityOptions = [
        'wifi'             => 'Wi-fi',
        'parking'          => 'Parking',
        'aircon'           => 'Aircon',
        'pet-friendly'     => 'Pet Friendly',
        'free-water'       => 'Free Water',
        'free-electricity' => 'Free Electricity',
    ];

    /* =========================
    FILTER INPUT
    ========================== */

    $selectedLocation = isset($_GET['location']) && is_string($_GET['location'])
        ? trim($_GET['location'])
        : '';

    $selectedCategory = isset($_GET['category']) && is_string($_GET['category'])
        ? trim($_GET['category'])
        : '';

    $searchQuery = isset($_GET['q']) && is_string($_GET['q'])
        ? trim($_GET['q'])
        : '';

    /* PRICE */
    $priceMin = isset($_GET['price_min']) && is_numeric($_GET['price_min'])
        ? (int) $_GET['price_min']
        : 0;

    $priceMax = isset($_GET['price_max']) && is_numeric($_GET['price_max'])
        ? (int) $_GET['price_max']
        : 20000;

    /* Keep price values inside allowed range */
    $priceMin = max(0, min($priceMin, 20000));
    $priceMax = max(0, min($priceMax, 20000));

    /* Prevent minimum from being greater than maximum */
    if ($priceMin > $priceMax) {
        $priceMin = 0;
    }

    /* =========================
    AMENITIES INPUT
    ---------------------------------------------------
    FIX: unchecked checkboxes are never sent by the
    browser, so "amenities[]" being absent from $_GET
    is ambiguous — it could mean "fresh page load" OR
    "user unchecked/cleared every amenity". Both forms
    that touch amenities send a hidden
    "amenities_submitted" flag, so we can tell those two
    cases apart:
        - flag NOT present  -> first visit, use the
                                default ('wifi' preselected)
        - flag present       -> trust exactly what was sent,
                                even if that's nothing at all
    ========================== */

    if (isset($_GET['amenities_submitted'])) {
        $selectedAmenities = $_GET['amenities'] ?? [];
    } else {
        $selectedAmenities = ['wifi'];
    }

    if (!is_array($selectedAmenities)) {
        $selectedAmenities = [$selectedAmenities];
    }

    /* Only allow valid amenities */
    $selectedAmenities = array_values(
        array_intersect(
            $selectedAmenities,
            array_keys($amenityOptions)
        )
    );

    /* =========================
    FILTER LOGIC
    ========================== */

    $filteredListings = array_filter(
        $allListings,
        function ($listing) use (
            $selectedLocation,
            $selectedCategory,
            $searchQuery,
            $priceMin,
            $priceMax,
            $selectedAmenities
        ) {

            /* LOCATION
            NOTE: listings.location is free text typed by the
            host on host-step2.php, while $selectedLocation is
            a city SLUG from the filter dropdown. These won't
            match with strict equality once real hosts start
            typing their own location text — switch host-step2.php's
            Location field to a <select> of the same slugs, or
            change this to a stripos() partial match, to make
            the location filter actually work end-to-end. */
            if (
                $selectedLocation !== '' &&
                $listing['location'] !== $selectedLocation
            ) {
                return false;
            }

            /* CATEGORY */
            if (
                $selectedCategory !== '' &&
                $listing['category'] !== $selectedCategory
            ) {
                return false;
            }

            /* PRICE */
            if (
                $listing['price'] < $priceMin ||
                $listing['price'] > $priceMax
            ) {
                return false;
            }

            /* SEARCH */
            if ($searchQuery !== '') {

                $searchText =
                    $listing['title'] . ' ' .
                    $listing['location_label'];

                if (
                    stripos($searchText, $searchQuery) === false
                ) {
                    return false;
                }
            }

            /* AMENITIES */
            if (!empty($selectedAmenities)) {

                $hasAllAmenities =
                    count(
                        array_diff(
                            $selectedAmenities,
                            $listing['amenities']
                        )
                    ) === 0;

                if (!$hasAllAmenities) {
                    return false;
                }
            }

            return true;
        }
    );

    /* =========================
    NORMALIZE FILTERED RESULTS
    ========================== */

    $filteredListings = array_values($filteredListings);

    /* =========================
    ACTIVE FILTER CHIPS
    (lets the user see and remove one filter at a time
    instead of hunting back through the whole bar)
    ========================== */

    $activeFilters = [];

    if ($selectedLocation !== '' && isset($locations[$selectedLocation])) {
        $activeFilters[] = [
            'label' => $locations[$selectedLocation],
            'url'   => roomhive_url(['location' => null]),
        ];
    }

    if ($selectedCategory !== '' && isset($categories[$selectedCategory])) {
        $activeFilters[] = [
            'label' => $categories[$selectedCategory],
            'url'   => roomhive_url(['category' => null]),
        ];
    }

    if ($searchQuery !== '') {
        $activeFilters[] = [
            'label' => '"' . $searchQuery . '"',
            'url'   => roomhive_url(['q' => null]),
        ];
    }

    if ($priceMax < 20000) {
        $activeFilters[] = [
            'label' => 'Up to ₱' . number_format($priceMax),
            'url'   => roomhive_url(['price_max' => null]),
        ];
    }

    foreach ($selectedAmenities as $amenity) {
        if (!isset($amenityOptions[$amenity])) {
            continue;
        }

        $remainingAmenities = array_values(
            array_diff($selectedAmenities, [$amenity])
        );

        $activeFilters[] = [
            'label' => $amenityOptions[$amenity],
            /* Always keep amenities_submitted=1 so an empty
            remaining list is respected as "cleared" rather
            than falling back to the wifi default. */
            'url'   => roomhive_url([
                'amenities'           => $remainingAmenities ?: null,
                'amenities_submitted' => 1,
            ]),
        ];
    }

    $hasActiveFilters = !empty($activeFilters);

    $clearFiltersUrl = '/webprogg/Listings/listing.php';

    /* =========================
    PAGINATION
    ========================== */

    $perPage = 6;

    $totalItems = count($filteredListings);

    $totalPages = max(
        1,
        (int) ceil($totalItems / $perPage)
    );

    $page = isset($_GET['page']) && is_numeric($_GET['page'])
        ? (int) $_GET['page']
        : 1;

    $page = max(1, $page);
    $page = min($page, $totalPages);

    $offset = ($page - 1) * $perPage;

    $pageListings = array_slice(
        $filteredListings,
        $offset,
        $perPage
    );

    /* "New" badge window: listings added in the last 14 days */
    $todayTimestamp = strtotime('2026-09-03');

    /* =========================
    QUERY URL HELPER
    ========================== */

    function roomhive_url($overrides = [])
    {
        $params = $_GET;

        /* Remove invalid pagination first */
        unset($params['page']);

        foreach ($overrides as $key => $value) {

            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        return '/webprogg/Listings/listing.php?' . http_build_query($params);
    }

    /* =========================
    LISTING DETAIL LINK HELPER
    Some listings have their own dedicated page
    (e.g. ShairaDumagureApartmentForRent.php); the rest
    fall back to the generic listing-detail.php?id=
    ========================== */

    function roomhive_detail_url($listing)
    {
        if (!empty($listing['detail_url'])) {
            return $listing['detail_url'];
        }

        return '/webprogg/Listings/listing-detail.php?id=' . urlencode($listing['id']);
    }

    /* =========================
    SAFE CATEGORY LABEL
    ========================== */

    $selectedCategoryLabel =
        $categories[$selectedCategory]
        ?? 'All Categories';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RoomHive - Listings</title>

    <!-- Poppins (UI type) + Fraunces (display type, used only
         for the greeting name and section titles) -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap"
        rel="stylesheet"
    >

    <!-- CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/listings-style.css">

    <!-- =========================
        LISTINGS PAGE ENHANCEMENTS
        (scoped here so nothing in style.css needs to
        change; the modernized base rules for this page —
        tokens, layout, cards, filter bar, etc. — now live
        in style.css's listings section.)

        NEW: this block now holds the self-contained
        FLOATING LOGIN MODAL styles (hive-styled).
    ========================== -->
    <style>

/* =========================================================
   FLOATING LOGIN MODAL — hive-styled, self-contained
========================================================= */

.lx-modal {
  position: fixed;
  inset: 0;
  z-index: 1200;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 24px;

  visibility: hidden;
  pointer-events: none;
}

.lx-modal.open {
  visibility: visible;
  pointer-events: auto;
}

.lx-modal-backdrop {
  position: absolute;
  inset: 0;

  background: rgba(22, 58, 48, 0.38);
  backdrop-filter: blur(9px);
  -webkit-backdrop-filter: blur(9px);

  opacity: 0;
  transition: opacity 0.3s ease;
}

.lx-modal.open .lx-modal-backdrop {
  opacity: 1;
}

.lx-modal-card {
  position: relative;
  z-index: 1;

  width: 362px;
  max-height: calc(100vh - 48px);
  overflow-y: auto;

  background: #ffffff;

  border-radius: 22px;

  padding: 32px 30px 26px;

  box-shadow: 0 30px 70px rgba(22, 58, 48, 0.35);

  opacity: 0;
  transform: translateY(26px) scale(0.96);

  transition:
    opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.lx-modal.open .lx-modal-card {
  opacity: 1;
  transform: translateY(0) scale(1);

  animation: lxFloat 5s ease-in-out 0.4s infinite;
}

/* Honey accent bar across the top */
.lx-modal-card::before {
  content: "";

  position: absolute;
  top: 0;
  left: 0;
  right: 0;

  height: 5px;

  background: linear-gradient(90deg, #dd930f, #fbf1dc, #dd930f);

  border-radius: 22px 22px 0 0;
}

.lx-modal-close {
  position: absolute;
  top: 12px;
  right: 14px;

  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  background: #f4f1e7;

  border: none;
  border-radius: 50%;

  color: #62705f;

  font-size: 17px;
  line-height: 1;

  cursor: pointer;

  transition:
    background 0.15s ease,
    color 0.15s ease,
    transform 0.15s ease;
}

.lx-modal-close:hover {
  background: #dd930f;

  color: #ffffff;

  transform: rotate(90deg);
}

.lx-modal-logo {
  text-align: center;

  margin-bottom: 10px;
}

.lx-modal-logo img {
  width: 96px;

  display: inline-block;
}

.lx-modal-title {
  margin: 0 0 16px;

  font-family: "Fraunces", serif;
  font-size: 1.45rem;
  font-weight: 600;

  text-align: center;

  color: #1c2b24;
}

.lx-error {
  padding: 11px 14px;

  margin-bottom: 14px;

  background: #fdecec;

  border: 1px solid #f3b9b9;
  border-radius: 11px;

  color: #a4302f;

  font-size: 0.82rem;
  font-weight: 500;

  text-align: center;
}

.lx-field {
  margin-bottom: 12px;
}

.lx-field label {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 6px;

  color: #1c2b24;

  font-size: 0.82rem;
  font-weight: 500;
}

.lx-field label img {
  width: 16px;
  height: 16px;

  object-fit: contain;
}

.lx-input {
  width: 100%;
  height: 44px;

  padding: 0 13px;

  background: #fdfcf8;

  border: 1.5px solid #e8e1cf;
  border-radius: 11px;

  outline: none;

  color: #1c2b24;

  font-family: "Poppins", sans-serif;
  font-size: 0.9rem;

  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background 0.2s ease;
}

.lx-input:focus {
  background: #ffffff;

  border-color: #dd930f;

  box-shadow: 0 0 0 4px rgba(221, 147, 15, 0.14);
}

.lx-forgot {
  text-align: center;

  margin: 4px 0 12px;
}

.lx-forgot a {
  color: #1c2b24;

  font-size: 0.8rem;
  font-weight: 600;

  text-decoration: none;
}

.lx-forgot a:hover {
  color: #b8760a;
}

.lx-submit {
  width: 100%;
  height: 46px;

  background: linear-gradient(135deg, #eda423, #dd930f);

  border: none;
  border-radius: 12px;

  color: #ffffff;

  font-family: "Poppins", sans-serif;
  font-size: 0.92rem;
  font-weight: 700;
  letter-spacing: 0.02em;

  cursor: pointer;

  box-shadow: 0 8px 18px rgba(221, 147, 15, 0.35);

  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.lx-submit:hover {
  transform: translateY(-2px);

  box-shadow: 0 12px 24px rgba(221, 147, 15, 0.45);
}

.lx-submit:disabled {
  opacity: 0.7;

  cursor: not-allowed;

  transform: none;
}

.lx-divider {
  display: flex;
  align-items: center;
  gap: 10px;

  margin: 16px 0;

  color: #62705f;

  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.lx-divider::before,
.lx-divider::after {
  content: "";

  flex: 1;
  height: 1px;

  background: #e8e1cf;
}

.lx-social {
  width: 100%;
  height: 44px;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;

  background: #ffffff;

  border: 1.5px solid #e8e1cf;
  border-radius: 11px;

  color: #1c2b24;

  font-family: "Poppins", sans-serif;
  font-size: 0.85rem;
  font-weight: 500;

  cursor: pointer;

  margin-bottom: 10px;

  transition:
    border-color 0.2s ease,
    background 0.2s ease,
    transform 0.2s ease;
}

.lx-social:hover {
  border-color: #dd930f;

  background: #fbf1dc;

  transform: translateY(-1px);
}

.lx-social img {
  width: 17px;
  height: 17px;

  object-fit: contain;
}

.lx-create {
  margin: 6px 0 0;

  text-align: center;

  color: #62705f;

  font-size: 0.83rem;
}

.lx-create a {
  color: #b8760a;

  font-weight: 700;

  text-decoration: none;
}

.lx-create a:hover {
  text-decoration: underline;
}

@keyframes lxFloat {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-8px);
  }
}

@media (max-width: 480px) {
  .lx-modal {
    padding: 14px;
  }

  .lx-modal-card {
    width: 100%;

    padding: 26px 20px 22px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .lx-modal-card,
  .lx-modal-backdrop {
    transition: none;
  }

  .lx-modal.open .lx-modal-card {
    animation: none;
  }
}

    </style>

</head>

<body>

    <!-- =========================
        NAVIGATION BAR
    ========================== -->

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>

    <!-- =========================
        LISTINGS PAGE
    ========================== -->

    <main class="listings-page">

        <!-- GREETING -->
        <div class="listings-greeting">

            <div class="greeting-text">

                <p>Hello,</p>

                <h1>
                    <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <p class="rh-hero-tagline">
                    A place to call home in <?= htmlspecialchars($province ?? 'Negros Oriental', ENT_QUOTES, 'UTF-8') ?> —
                    browse verified rooms, studios, and shared spaces near you.
                </p>

            </div>

            <img
                src="/webprogg/images/living_room_illustration.png"
                alt=""
                class="greeting-illustration"
            >

        </div>

        <!-- =========================
            EXPLORE MORE SPACES
            (horizontally scrollable strip of every listing,
            independent of the filters/pagination below —
            scroll or use the arrows to browse them all.
            Hidden entirely when there are no listings yet.)
        ========================== -->

        <?php if (!empty($allListings)): ?>

        <section class="rh-carousel-section">

            <div class="rh-carousel-header">

                <h3>Explore More Spaces</h3>

                <div class="rh-carousel-arrows">

                    <button
                        type="button"
                        class="rh-carousel-arrow"
                        id="rh-carousel-prev"
                        aria-label="Scroll left"
                    >
                        &#10094;
                    </button>

                    <button
                        type="button"
                        class="rh-carousel-arrow"
                        id="rh-carousel-next"
                        aria-label="Scroll right"
                    >
                        &#10095;
                    </button>

                </div>

            </div>

            <div class="rh-carousel-track" id="rh-carousel-track">

                <?php foreach ($allListings as $listing): ?>

                    <a
                        href="<?= htmlspecialchars(roomhive_detail_url($listing), ENT_QUOTES, 'UTF-8') ?>"
                        class="rh-carousel-card"
                    >

                        <img
                            src="<?= htmlspecialchars($listing['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <div class="rh-carousel-card-info">

                            <h4><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h4>

                            <p>
                                <?= htmlspecialchars($listing['location_label'], ENT_QUOTES, 'UTF-8') ?>
                                &middot; ₱<?= number_format($listing['price']) ?>/month
                            </p>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        </section>

        <?php endif; ?>

        <!-- =========================
            FILTER BAR
        ========================== -->

        <form
            class="filter-bar"
            method="get"
            action="/webprogg/Listings/listing.php"
            id="filter-form"
        >

            <!-- LOCATION -->

            <div class="filter-group">

                <img
                    src="/webprogg/images/GPSIcon.png"
                    alt=""
                    class="filter-icon"
                >

                <select
                    class="filter-select"
                    id="location-select"
                    name="location"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        Select a location
                    </option>

                    <?php foreach ($locations as $value => $label): ?>

                        <option
                            value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $selectedLocation === $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <img
                    src="/webprogg/images/DownwardArrow.png"
                    alt=""
                    class="filter-arrow"
                >

            </div>

            <div class="filter-divider"></div>

            <!-- CATEGORY -->

            <div
                class="filter-group category-filter"
                id="category-filter"
            >

                <img
                    src="/webprogg/images/HouseIcon.png"
                    alt=""
                    class="filter-icon"
                >

                <button
                    type="button"
                    class="category-dropdown-btn"
                    id="categoryDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <?= htmlspecialchars(
                        $selectedCategoryLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </button>

                <img
                    src="/webprogg/images/DownwardArrow.png"
                    alt=""
                    class="filter-arrow category-dropdown-caret"
                >

                <input
                    type="hidden"
                    name="category"
                    id="category-hidden-input"
                    value="<?= htmlspecialchars(
                        $selectedCategory,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div
                    class="category-dropdown-panel"
                    id="categoryDropdownPanel"
                >

                    <button
                        type="submit"
                        class="category-dropdown-item <?= $selectedCategory === '' ? 'active' : '' ?>"
                        name="category"
                        value=""
                    >
                        All Categories
                    </button>

                    <?php foreach ($categories as $value => $label): ?>

                        <button
                            type="submit"
                            class="category-dropdown-item <?= $selectedCategory === $value ? 'active' : '' ?>"
                            name="category"
                            value="<?= htmlspecialchars(
                                $value,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </button>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- PRICE RANGE -->

            <div class="filter-group price-filter">

                <span class="price-icon">
                    ₱
                </span>

                <span
                    class="price-value"
                    id="price-min"
                >
                    <?= number_format($priceMin) ?>
                </span>

                <input
                    type="range"
                    class="price-range"
                    id="price-range"
                    name="price_max"
                    min="0"
                    max="20000"
                    step="500"
                    value="<?= htmlspecialchars(
                        $priceMax,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <span
                    class="price-value"
                    id="price-max"
                >
                    <?= number_format($priceMax) ?>
                </span>

            </div>

            <div class="filter-divider"></div>

            <!-- SEARCH -->

            <div class="filter-group search-filter">

                <input
                    type="text"
                    class="search-input"
                    name="q"
                    placeholder="Search.."
                    value="<?= htmlspecialchars(
                        $searchQuery,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <button
                    class="search-button"
                    type="submit"
                >

                    <img
                        src="/webprogg/images/SearchIcon_orange.png"
                        alt="Search"
                    >

                </button>

            </div>

            <!-- PRESERVE AMENITIES STATE
                (marker flag first, then the actual selected
                amenities — see the AMENITIES INPUT block above
                for why the flag matters) -->

            <input type="hidden" name="amenities_submitted" value="1">

            <?php foreach ($selectedAmenities as $amenity): ?>

                <input
                    type="hidden"
                    name="amenities[]"
                    value="<?= htmlspecialchars(
                        $amenity,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            <?php endforeach; ?>

        </form>

        <!-- =========================
            MAIN LISTINGS + SIDEBAR
        ========================== -->

        <section class="listings-content">

            <div class="listings-main">

                <!-- =========================
                    SAVED-ONLY TOGGLE + SAVE THIS SEARCH
                ========================== -->

                <div class="rh-results-bar">

                    <!-- RESULTS TOOLBAR — live count, sort, view switch -->
                    <div
                        class="rh-toolbar"
                        data-shown="<?= count($pageListings) ?>"
                        data-total="<?= (int) $totalItems ?>"
                    >
                        <p class="rh-results-count" id="rh-results-count"></p>

                        <div class="rh-toolbar-actions">
                            <select class="rh-sort" id="rh-sort" aria-label="Sort listings">
                                <option value="featured">Featured</option>
                                <option value="price-asc">Price: Low &rarr; High</option>
                                <option value="price-desc">Price: High &rarr; Low</option>
                            </select>

                            <div class="rh-view-toggle" role="group" aria-label="Layout">
                                <button type="button" class="rh-view-btn active" data-view="grid" aria-label="Grid view">&#9638;</button>
                                <button type="button" class="rh-view-btn" data-view="list" aria-label="List view">&#9776;</button>
                            </div>
                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>

                        <!-- SAVE THIS SEARCH
                            Posts the currently-applied filters (read
                            straight from the same PHP variables the
                            filter bar above renders from) to
                            save-search.php. Guests never see this —
                            saved_searches.user_id is NOT NULL. -->

                        <button
                            type="button"
                            class="rh-saved-toggle rh-save-search-btn"
                            id="rh-save-search-btn"
                            data-location="<?= htmlspecialchars($selectedLocation, ENT_QUOTES, 'UTF-8') ?>"
                            data-category="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>"
                            data-q="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                            data-price-min="<?= htmlspecialchars($priceMin, ENT_QUOTES, 'UTF-8') ?>"
                            data-price-max="<?= htmlspecialchars($priceMax, ENT_QUOTES, 'UTF-8') ?>"
                            data-amenities="<?= htmlspecialchars(implode(',', $selectedAmenities), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <span class="rh-heart-icon">&#128190;</span>
                            Save this search
                        </button>

                    <?php else: ?>

                        <!-- NEW: for guests this used to navigate to
                             loginform.php — the floating modal's JS
                             catches this link and opens the card in
                             place instead (no markup change needed). -->
                        <a
                            class="rh-saved-toggle"
                            href="/webprogg/auth/loginform.php"
                        >
                            <span class="rh-heart-icon">&#128190;</span>
                            Save this search
                        </a>

                    <?php endif; ?>

                    <button
                        type="button"
                        class="rh-saved-toggle"
                        id="rh-saved-toggle"
                        aria-pressed="false"
                    >
                        <span class="rh-heart-icon">&#9825;</span>
                        Saved only
                    </button>

                </div>

                <!-- LISTING CARDS -->

                <div class="listings-results" id="rh-listings-results">

                    <?php if (empty($pageListings)): ?>

                        <div class="rh-empty-state">

                            <p class="rh-empty-title">No listings match your filters</p>

                            <p class="rh-empty-subtitle">
                                Try widening your price range, removing an amenity,
                                or searching a different area.
                            </p>

                            <?php if ($hasActiveFilters): ?>

                                <a class="rh-empty-clear-btn" href="<?= htmlspecialchars($clearFiltersUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    Clear all filters
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                    <?php foreach ($pageListings as $listing): ?>

                        <?php
                        $isNew = strtotime($listing['date_added']) >= $todayTimestamp - (14 * 86400);
                        ?>

                        <a
                            href="<?= htmlspecialchars(roomhive_detail_url($listing), ENT_QUOTES, 'UTF-8') ?>"
                            class="listing-box"
                            data-listing-id="<?= (int) $listing['id'] ?>"
                        >

                            <div class="rh-card-media">

                                <img
                                    src="<?= htmlspecialchars(
                                        $listing['image'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $listing['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <div class="rh-card-badges">

                                    <?php if ($listing['verified']): ?>
                                        <span class="rh-badge rh-badge-verified">Verified</span>
                                    <?php endif; ?>

                                    <?php if ($isNew): ?>
                                        <span class="rh-badge rh-badge-new">New</span>
                                    <?php endif; ?>

                                </div>

                                <button
                                    type="button"
                                    class="rh-save-btn"
                                    data-listing-id="<?= (int) $listing['id'] ?>"
                                    aria-pressed="false"
                                    aria-label="Save <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    &#9825;
                                </button>

                            </div>

                            <div class="listing-box-info">

                                <div class="rh-card-title-row">

                                    <h4 class="listing-box-title">
                                        <?= htmlspecialchars(
                                            $listing['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h4>

                                    <span class="rh-rating">
                                        &#9733; <?= number_format($listing['rating'], 1) ?>
                                        <span class="rh-rating-count">(<?= (int) $listing['reviews'] ?>)</span>
                                    </span>

                                </div>

                                <div class="listing-box-location">

                                    <img
                                        src="/webprogg/images/GPSIcon.png"
                                        alt=""
                                    >

                                    <span>
                                        <?= htmlspecialchars(
                                            $listing['location_label'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <span class="rh-bedrooms">
                                        &middot; <?= (int) $listing['bedrooms'] ?>
                                        <?= $listing['bedrooms'] === 1 ? 'bedroom' : 'bedrooms' ?>
                                    </span>

                                </div>

                                <div class="listing-box-price">

                                    <span class="peso">
                                        ₱
                                    </span>

                                    <?= number_format($listing['price']) ?>

                                    <span class="per">
                                        /month
                                    </span>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                </div>

                <!-- =========================
                    PAGINATION
                ========================== -->

                <?php if ($totalPages > 1): ?>

                    <div class="listings-pagination">

                        <!-- PREVIOUS -->

                        <?php if ($page > 1): ?>

                            <a
                                class="page-arrow"
                                href="<?= htmlspecialchars(
                                    roomhive_url([
                                        'page' => $page - 1
                                    ]),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <img
                                    src="/webprogg/images/LookingLeftArrow.png"
                                    alt="Previous"
                                >

                            </a>

                        <?php else: ?>

                            <button
                                class="page-arrow"
                                type="button"
                                disabled
                            >

                                <img
                                    src="/webprogg/images/LookingLeftArrow.png"
                                    alt="Previous"
                                >

                            </button>

                        <?php endif; ?>

                        <!-- PAGE NUMBERS -->

                        <?php for (
                            $p = 1;
                            $p <= $totalPages;
                            $p++
                        ): ?>

                            <?php
                            $showPage =
                                $p === 1 ||
                                $p === $totalPages ||
                                abs($p - $page) <= 1;
                            ?>

                            <?php if (!$showPage): ?>

                                <?php
                                $previousShown =
                                    $p === 2 ||
                                    ($p - 1 === $page + 1);

                                if (!$previousShown) {
                                    continue;
                                }
                                ?>

                                <span class="page-dots">
                                    ...
                                </span>

                                <?php continue; ?>

                            <?php endif; ?>

                            <a
                                class="page-num <?= $p === $page ? 'active' : '' ?>"
                                href="<?= htmlspecialchars(
                                    roomhive_url([
                                        'page' => $p
                                    ]),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>

                        <!-- NEXT -->

                        <?php if ($page < $totalPages): ?>

                            <a
                                class="page-arrow"
                                href="<?= htmlspecialchars(
                                    roomhive_url([
                                        'page' => $page + 1
                                    ]),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <img
                                    src="/webprogg/images/LookingRightArrow.png"
                                    alt="Next"
                                >

                            </a>

                        <?php else: ?>

                            <button
                                class="page-arrow"
                                type="button"
                                disabled
                            >

                                <img
                                    src="/webprogg/images/LookingRightArrow.png"
                                    alt="Next"
                                >

                            </button>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

            <!-- =========================
                AMENITIES SIDEBAR
            ========================== -->

            <aside class="amenities-sidebar">

                <h3>
                    Amenities
                </h3>

                <form
                    method="get"
                    action="/webprogg/Listings/listing.php"
                    id="amenities-form"
                >

                    <!-- KEEP LOCATION -->

                    <?php if ($selectedLocation !== ''): ?>

                        <input
                            type="hidden"
                            name="location"
                            value="<?= htmlspecialchars(
                                $selectedLocation,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    <?php endif; ?>

                    <!-- KEEP CATEGORY -->

                    <?php if ($selectedCategory !== ''): ?>

                        <input
                            type="hidden"
                            name="category"
                            value="<?= htmlspecialchars(
                                $selectedCategory,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    <?php endif; ?>

                    <!-- KEEP SEARCH -->

                    <?php if ($searchQuery !== ''): ?>

                        <input
                            type="hidden"
                            name="q"
                            value="<?= htmlspecialchars(
                                $searchQuery,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    <?php endif; ?>

                    <!-- KEEP PRICE -->

                    <input
                        type="hidden"
                        name="price_max"
                        value="<?= htmlspecialchars(
                            $priceMax,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <!-- MARKER: tells PHP this form was actually
                        submitted, so zero checked boxes means
                        "cleared", not "use the wifi default" -->

                    <input type="hidden" name="amenities_submitted" value="1">

                    <?php foreach (
                        $amenityOptions
                        as $value => $label
                    ): ?>

                        <label class="amenity-option">

                            <input
                                type="checkbox"
                                name="amenities[]"
                                value="<?= htmlspecialchars(
                                    $value,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= in_array(
                                    $value,
                                    $selectedAmenities,
                                    true
                                ) ? 'checked' : '' ?>
                                onchange="document.getElementById('amenities-form').submit()"
                            >

                            <span>
                                <?= htmlspecialchars(
                                    $label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                    <button
                        class="amenities-clear"
                        type="button"
                        onclick="roomhiveClearAmenities()"
                    >
                        Clear
                    </button>

                </form>

                <!-- NEED HELP -->

                <div class="need-help">

                    <h4>
                        Need Help?
                    </h4>

                    <a href="/webprogg/host/howitworks.php">
                        How to rent a room?
                    </a>

                    <a href="/webprogg/Listings/listing.php">
                        How to search listings?
                    </a>

                    <a href="/webprogg/host/becomeahost.php">
                        How to become a host?
                    </a>

                    <a href="/webprogg/host/howitworks.php">
                        Payment &amp; booking
                    </a>

                    <a href="/webprogg/Listings/listing.php">
                        Location help
                    </a>

                    <a href="/webprogg/misc/contacts.php">
                        Contact Support
                    </a>

                </div>

            </aside>

        </section>

    </main>

    <!-- =========================
        FOOTER
        (NOTE: your original paste was cut off inside this
        footer — the LISTINGS links, QUICK LINKS, GET THE APP
        and bottom bar below are a faithful reconstruction.
        Swap in your real footer if it differs.)
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

                <a href="/webprogg/Listings/listing.php?category=privateroom">
                    Private Rooms
                </a>

                <a href="/webprogg/Listings/listing.php?category=entirehouse">
                    Entire Houses
                </a>

                <a href="/webprogg/Listings/listing.php?category=boardinghouse">
                    Boarding Houses
                </a>

                <a href="/webprogg/Listings/listing.php?category=apartment">
                    Apartments
                </a>

            </div>

            <!-- QUICK LINKS -->

            <div class="footer-links">

                <span class="footer-heading">
                    QUICK LINKS
                </span>

                <a href="<?= htmlspecialchars($navigation['HOME'], ENT_QUOTES, 'UTF-8') ?>">
                    Home
                </a>

                <a href="/webprogg/Listings/listing.php">
                    Listings
                </a>

                <a href="/webprogg/host/howitworks.php">
                    How It Works
                </a>

                <a href="/webprogg/hiveclub.php">
                    Hive Club
                </a>

                <a href="/webprogg/misc/contacts.php">
                    Contacts
                </a>

            </div>

            <!-- GET THE APP -->

            <div class="footer-links">

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

                <div class="footer-contact-line">

                    <img
                        src="/webprogg/images/GPSIcon.png"
                        alt=""
                    >

                    <span>
                        Negros Oriental, Philippines
                    </span>

                </div>

            </div>

        </div>

        <!-- FOOTER BOTTOM -->

        <div class="footer-bottom">

            <p>
                &copy; <?= date("Y") ?> RoomHive. All rights reserved.
            </p>

        </div>

    </footer>

    <!-- =========================================================
         NEW — FLOATING LOGIN MODAL (guests)
         Auto-opens once per browser session, and opens from any
         link pointing at loginform.php — the navbar's BECOME A HOST
         link and the guest "Save this search" button are caught
         automatically, no markup changes needed.
    ========================================================== -->
    <div class="lx-modal" id="lxModal" aria-hidden="true">

        <div class="lx-modal-backdrop" data-lx-close></div>

        <div class="lx-modal-card" role="dialog" aria-modal="true" aria-label="Log in to RoomHive">

            <button type="button" class="lx-modal-close" data-lx-close aria-label="Close">
                &times;
            </button>

            <div class="lx-modal-logo">

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive logo"
                >

            </div>

            <h2 class="lx-modal-title">
                Welcome back!
            </h2>

            <!-- Error message (filled in by JS on a failed attempt) -->
            <div class="lx-error" id="lxModalError" hidden></div>

            <form id="lxModalForm" novalidate>

                <input type="hidden" name="redirect" value="">

                <!-- Email -->
                <div class="lx-field">

                    <label for="lx-email">

                        <img
                            src="/webprogg/images/EmailIcon.jpg"
                            alt=""
                        >

                        Email Address

                    </label>

                    <input
                        class="lx-input"
                        type="email"
                        id="lx-email"
                        name="email"
                        autocomplete="email"
                        required
                    >

                </div>

                <!-- Password -->
                <div class="lx-field">

                    <label for="lx-password">

                        <img
                            src="/webprogg/images/LockIcon.png"
                            alt=""
                        >

                        Password

                    </label>

                    <input
                        class="lx-input"
                        type="password"
                        id="lx-password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <!-- Forgot password -->
                <div class="lx-forgot">

                    <a href="/webprogg/auth/forgotpassword.php">
                        Forgot Password?
                    </a>

                </div>

                <button type="submit" class="lx-submit">
                    Log in
                </button>

            </form>

            <div class="lx-divider">
                or
            </div>

            <!-- NOTE: absolute paths — this page lives in /Listings/,
                 so relative hrefs like 'google-login.php' would 404. -->
            <button
                type="button"
                class="lx-social"
                onclick="window.location.href='/webprogg/auth/google-login.php'"
            >

                <img
                    src="/webprogg/images/Googlecons.png"
                    alt=""
                >

                Continue with Google

            </button>

            <button
                type="button"
                class="lx-social"
                onclick="window.location.href='/webprogg/auth/apple-login.php'"
            >

                <img
                    src="/webprogg/images/AppleIcons.png"
                    alt=""
                >

                Continue with Apple

            </button>

            <p class="lx-create">

                Not registered yet?

                <a href="/webprogg/auth/createaccount.php">
                    Create Account Here
                </a>

            </p>

        </div>

    </div>

    <!-- =========================
        SCRIPTS
    ========================== -->

    <script src="/webprogg/assets/javaScript.js"></script>

    <!-- NEW — sidebar "Clear" helper.
         Guarded with typeof so it can't collide if your original
         cut-off footer already defined it inline. -->
    <script>
    if (typeof window.roomhiveClearAmenities !== "function") {
        window.roomhiveClearAmenities = function () {
            var form = document.getElementById("amenities-form");
            if (!form) { return; }
            form.querySelectorAll('input[name="amenities[]"]').forEach(function (cb) {
                cb.checked = false;
            });
            form.submit();
        };
    }
    </script>

    <!-- NEW — FLOATING LOGIN MODAL SCRIPT (self-contained) -->
    <script>
    (function () {
        "use strict";

        var modal = document.getElementById("lxModal");
        var form  = document.getElementById("lxModalForm");

        if (!modal || !form) {
            return;
        }

        var errorBox   = document.getElementById("lxModalError");
        var emailInput = document.getElementById("lx-email");

        function openModal() {
            modal.classList.add("open");
            modal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";

            if (emailInput) {
                window.setTimeout(function () {
                    emailInput.focus();
                }, 350);
            }
        }

        function closeModal() {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";

            if (errorBox) {
                errorBox.hidden = true;
                errorBox.textContent = "";
            }
        }

        /* ---- AUTO-OPEN: guests, once per browser session ----
           Shares the same sessionStorage flag as index.php, so
           the card only nags once no matter which page you land on. */
        var autoOpen = <?php echo $autoOpenLoginPopup ? "true" : "false"; ?>;

        if (autoOpen) {
            var alreadyShown = false;

            try {
                alreadyShown =
                    sessionStorage.getItem("rhLoginModalShown") === "1";
                sessionStorage.setItem("rhLoginModalShown", "1");
            } catch (err) {
                /* storage unavailable — just show it */
            }

            if (!alreadyShown) {
                window.setTimeout(openModal, 700);
            }
        }

        /* ---- Any link to loginform.php opens the modal instead of
                navigating — covers the navbar BECOME A HOST link and
                the guest "Save this search" button with zero markup
                changes. ---- */
        document.addEventListener("click", function (event) {
            if (!event.target || !event.target.closest) {
                return;
            }

            var loginLink = event.target.closest('a[href*="loginform.php"]');

            if (loginLink) {
                event.preventDefault();
                openModal();
                return;
            }

            if (event.target.closest("[data-lx-close]")) {
                closeModal();
            }
        });

        /* ---- Esc closes it ---- */
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && modal.classList.contains("open")) {
                closeModal();
            }
        });

        /* ---- Submit through loginform.php's AJAX path ----
           The X-Requested-With header makes loginform.php answer
           with JSON (already supported), so errors show inside the
           card and success redirects without a full reload. */
        form.addEventListener("submit", function (event) {
            event.preventDefault();

            if (errorBox) {
                errorBox.hidden = true;
            }

            var submitBtn = form.querySelector(".lx-submit");
            var originalLabel = submitBtn ? submitBtn.textContent : "";

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Logging in...";
            }

            fetch("/webprogg/auth/loginform.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: new FormData(form),
                credentials: "same-origin"
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success) {
                        window.location.href = data.redirect;
                        return;
                    }

                    if (errorBox) {
                        errorBox.textContent =
                            (data && data.error) || "Something went wrong.";
                        errorBox.hidden = false;
                    }
                })
                .catch(function () {
                    if (errorBox) {
                        errorBox.textContent =
                            "Couldn't reach the server. Please try again.";
                        errorBox.hidden = false;
                    }
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalLabel || "Log in";
                    }
                });
        });
    })();
    </script>

</body>

</html>