<?php
    /* =========================
    listing.php

    === CHANGES ===
    1-12. (previous fixes: layout, filters synced with host
       form, hearts = wishlist via togglewishlist.php, chip
       row kill switch)
    13. WISHLIST BUTTON FIX v2 — early capture-phase handler,
        exactly ONE togglewishlist.php call per click.
    14. AMENITY HEART REMOVED — unchecked = icon + label only.
    15. TOOLBAR ICONS REMOVED — text-only toolbar buttons.
    16. WISHLIST HEART FIX v3 — data-saved exposes server
        state; busy-locked; UI lands on the server's state.
    17. FEATURED SORT FIXED — #rh-sort has a working handler.
    18. ICON FIXES — parking uses caricon.png; GPS icons get
        onerror guards.
    19. REAL REVIEW DATA — listings query aggregates reviews.
    20. MUTED THEME — inline styles toned down.
    21. MY WISHLIST NAVIGATION FIXED — early capture listener
        on #rh-wishlist-link navigates itself.
    22. SORT HANDLER v3 — capture phase, grid cleanup, all
        sort orders working.
    23. PRICE RANGE — LIVE NUMBERS: the max price label updates
        LIVE while the thumb drags (formatted + gold highlight);
        javaScript.js's price handler is blocked via capture +
        stopImmediatePropagation; on release the filter
        auto-applies after a 350ms debounce.
    24. LOGIN NAVIGATION FIX (this version): the floating login
        modal + its styles + its script are REMOVED — the guest
        "Save this search" link now navigates to loginform.php
        like every other login link.
    ========================== */
    session_start();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/booking_helpers.php';
    roomhive_expire_stale_bookings($pdo);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/negros-oriental-locations.php';

    /* =========================================================
       AMENITY NORMALIZERS
    ========================================================== */

    function roomhive_normalize_amenity($value)
    {
        $v = strtolower(trim((string) $value));
        $v = preg_replace('/[^a-z0-9]+/', '-', $v);
        return trim($v, '-');
    }

    function roomhive_canonical_amenity($value)
    {
        static $synonyms = [
            'wifi'                 => 'wifi',
            'wi-fi'                => 'wifi',
            'wireless-internet'    => 'wifi',
            'internet'             => 'wifi',

            'parking'              => 'parking',
            'parking-space'        => 'parking',
            'car-parking'          => 'parking',
            'free-parking'         => 'parking',

            'aircon'               => 'aircon',
            'air-con'              => 'aircon',
            'air-conditioning'     => 'aircon',
            'ac'                   => 'aircon',

            'pet-friendly'         => 'pet-friendly',
            'pets-allowed'         => 'pet-friendly',
            'pets'                 => 'pet-friendly',

            'free-water'           => 'free-water',
            'water-included'       => 'free-water',
            'water'                => 'free-water',

            'free-electricity'     => 'free-electricity',
            'electricity-included' => 'free-electricity',
            'free-electric'        => 'free-electricity',
            'electricity'          => 'free-electricity',

            'security'             => 'security',
            '24-7-security'        => 'security',
            'security-guard'       => 'security',
            'cctv'                 => 'security',
        ];

        $key = roomhive_normalize_amenity($value);
        return $synonyms[$key] ?? $key;
    }

    function roomhive_amenity_keys($raw)
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $values = $decoded;
            } elseif ((string) $raw !== '') {
                $values = explode(',', (string) $raw);
            } else {
                $values = [];
            }
        }

        $keys = [];
        foreach ($values as $v) {
            $k = roomhive_canonical_amenity($v);
            if ($k !== '') {
                $keys[] = $k;
            }
        }

        return array_values(array_unique($keys));
    }

    $isLoggedIn = (
        isset($_SESSION["logged_in"]) &&
        $_SESSION["logged_in"] === true
    );

    $userName = $_SESSION['user_name'] ?? 'Guest';

    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
        $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
        $hostRow = $hostCheckStmt->fetch();
        $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
        $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
    }

    $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

    $notification_count = 0;
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $ncStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u");
        $ncStmt->execute(['u' => $_SESSION['user_id']]);
        $notification_count = (int) $ncStmt->fetchColumn();
    }

    /* =========================================================
       WISHLIST — saved IDs from the DB (one source of truth)
    ========================================================== */
    $savedListingIds = [];
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        try {
            $wStmt = $pdo->prepare(
                "SELECT listing_id FROM wishlist WHERE user_id = :u"
            );
            $wStmt->execute(['u' => $_SESSION['user_id']]);
            $savedListingIds = array_map(
                'intval',
                $wStmt->fetchAll(PDO::FETCH_COLUMN)
            );
        } catch (PDOException $e) {
            $savedListingIds = [];
        }
    }

    function rh_is_saved($id)
    {
        global $savedListingIds;
        return in_array((int) $id, $savedListingIds, true);
    }

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

    $listings = [
        ['name' => 'STUDIO LOFT',    'image' => '/webprogg/images/StudioLoft.png',    'slug' => 'studio-loft'],
        ['name' => 'SHARED ROOM',    'image' => '/webprogg/images/SharedBedroom.png', 'slug' => 'shared-bedroom'],
        ['name' => 'ENTIRE HOUSE',   'image' => '/webprogg/images/EntireHouse.png',   'slug' => 'entire-house'],
        ['name' => 'PRIVATE ROOM',   'image' => '/webprogg/images/PrivateRoom.png',   'slug' => 'private-room'],
        ['name' => 'BOARDING HOUSE', 'image' => '/webprogg/images/BoardingHouse.png', 'slug' => 'boarding-house'],
        ['name' => 'APARTMENT',      'image' => '/webprogg/images/Apartment.png',     'slug' => 'apartment'],
    ];

    /* =========================================================
       LISTINGS FETCH — real review data (FIX #19)
    ========================================================== */
 $listingsSqlWithReviews = "
    SELECT l.id, l.title, l.category, l.location, l.exact_address, l.price,
           l.bedrooms, l.parking, l.amenities, l.created_at,
           p.photo_path AS cover_photo,
           COALESCE(r.avg_rating, 0)    AS avg_rating,
           COALESCE(r.review_count, 0)  AS review_count
    FROM listings l
    LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
    LEFT JOIN (
        SELECT listing_id,
               ROUND(AVG(rating), 1) AS avg_rating,
               COUNT(*)              AS review_count
        FROM reviews
        GROUP BY listing_id
    ) r ON r.listing_id = l.id
    WHERE l.status = 'approved'
    AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.listing_id = l.id
            AND b.status = 'pending'
            AND b.payment_status = 'paid'
    )
    ORDER BY l.created_at DESC";

 $listingsSqlFallback = "
    SELECT l.id, l.title, l.category, l.location, l.exact_address, l.price,
           l.bedrooms, l.parking, l.amenities, l.created_at,
           p.photo_path AS cover_photo,
           0 AS avg_rating, 0 AS review_count
    FROM listings l
    LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
    WHERE l.status = 'approved'
    AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.listing_id = l.id
            AND b.status = 'pending'
            AND b.payment_status = 'paid'
    )
    ORDER BY l.created_at DESC";

 try {
    $listingRows = $pdo->query($listingsSqlWithReviews)->fetchAll();
 } catch (PDOException $e) {
    error_log('listing.php: reviews aggregate failed, falling back: ' . $e->getMessage());
    $listingRows = $pdo->query($listingsSqlFallback)->fetchAll();
 }

    $allListings = array_map(function ($row) {
        return [
            'id'             => (int) $row['id'],
            'title'          => $row['title'],
            'image' => !empty($row['cover_photo'])
                ? (preg_match('#^https?://#i', (string) $row['cover_photo'])
                    ? $row['cover_photo']
                    : (stripos(ltrim((string) $row['cover_photo'], '/'), 'webprogg/') === 0
                        ? '/' . ltrim((string) $row['cover_photo'], '/')
                        : '/webprogg/uploads/listing_photos/cover/' . basename((string) $row['cover_photo'])))
                : '/webprogg/images/ListingPlaceholder.png',
            'location'       => $row['location'],
            'location_label' => $row['location'],
            'category'       => $row['category'],
            'price'          => (float) $row['price'],
            'bedrooms'       => (int) $row['bedrooms'],
            'parking_available' => strtolower(trim((string) ($row['parking'] ?? ''))) === 'yes',
            'amenities'      => roomhive_amenity_keys($row['amenities'] ?? null),

            'rating'         => (float) ($row['avg_rating'] ?? 0),
            'reviews'        => (int) ($row['review_count'] ?? 0),

            'verified'       => false,
            'date_added'     => $row['created_at'],
        ];
    }, $listingRows);

    $locations = [];
    foreach ($negrosOrientalLocations as $citySlug => $cityData) {
        $locations[$citySlug] = ($cityData['type'] === 'city' ? 'City of ' : '') . $cityData['label'];
    }

        $categories = [
        'shared-bedroom' => 'Shared Bedroom',
        'private-room'   => 'Private Room',
        'entire-house'   => 'Entire House',
        'boarding-house' => 'Boarding House',
        'studio-loft'    => 'Studio Loft',
        'apartment'      => 'Apartment',
    ];

    $amenityOptions = [
        'wifi'             => 'Wi-fi',
        'parking'          => 'Parking',
        'aircon'           => 'Aircon',
        'pet-friendly'     => 'Pet Friendly',
        'free-water'       => 'Free Water',
        'free-electricity' => 'Free Electricity',
        'security'         => '24/7 Security',
    ];

    $amenityIcons = [
        'wifi'             => '/webprogg/images/wifiicon.png',
        'parking'          => '/webprogg/images/caricon.png',
        'aircon'           => '/webprogg/images/airconicon.png',
        'pet-friendly'     => '/webprogg/images/petsicon.png',
        'free-water'       => '/webprogg/images/watericon.png',
        'free-electricity' => '/webprogg/images/elcetricityicon.png',
        'security'         => '/webprogg/images/SecurityIcon.png',
    ];

    $selectedLocation = isset($_GET['location']) && is_string($_GET['location'])
        ? trim($_GET['location'])
        : '';

    $selectedCategory = isset($_GET['category']) && is_string($_GET['category'])
        ? trim($_GET['category'])
        : '';

    $searchQuery = isset($_GET['q']) && is_string($_GET['q'])
        ? trim($_GET['q'])
        : '';

    $priceMin = isset($_GET['price_min']) && is_numeric($_GET['price_min'])
        ? (int) $_GET['price_min']
        : 0;

    $priceMax = isset($_GET['price_max']) && is_numeric($_GET['price_max'])
        ? (int) $_GET['price_max']
        : 20000;

    $priceMin = max(0, min($priceMin, 20000));
    $priceMax = max(0, min($priceMax, 20000));

    if ($priceMin > $priceMax) {
        $priceMin = 0;
    }

    if (isset($_GET['amenities_submitted'])) {
        $selectedAmenities = $_GET['amenities'] ?? [];
    } else {
        $selectedAmenities = [];
    }

    if (!is_array($selectedAmenities)) {
        $selectedAmenities = [$selectedAmenities];
    }

    $selectedAmenities = array_map(
        'roomhive_canonical_amenity',
        $selectedAmenities
    );
    $selectedAmenities = array_values(array_intersect(
        array_unique($selectedAmenities),
        array_keys($amenityOptions)
    ));

    $amenityMatchMode = 'all';

    $filteredListings = array_filter(
        $allListings,
        function ($listing) use (
            $selectedLocation,
            $selectedCategory,
            $searchQuery,
            $priceMin,
            $priceMax,
            $selectedAmenities,
            $amenityMatchMode,
            $locations
        ) {

            if ($selectedLocation !== '' && isset($locations[$selectedLocation])) {
                $coreCity = preg_replace('/^City of\s+/i', '', $locations[$selectedLocation]);
                if (stripos($listing['location'], $coreCity) === false) {
                    return false;
                }
            }

            if (
                $selectedCategory !== '' &&
                $listing['category'] !== $selectedCategory
            ) {
                return false;
            }

            if (
                $listing['price'] < $priceMin ||
                $listing['price'] > $priceMax
            ) {
                return false;
            }

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

            if (!empty($selectedAmenities)) {

                $wantsParking = in_array('parking', $selectedAmenities, true);
                $rest = array_values(array_diff($selectedAmenities, ['parking']));

                if ($wantsParking) {
                    $hasParking = $listing['parking_available']
                        || in_array('parking', $listing['amenities'], true);

                    if (!$hasParking) {
                        return false;
                    }
                }

                if (!empty($rest)) {
                    $listingAmenities = $listing['amenities'];

                    if ($amenityMatchMode === 'any') {
                        if (count(array_intersect($rest, $listingAmenities)) === 0) {
                            return false;
                        }
                    } else {
                        if (count(array_diff($rest, $listingAmenities)) !== 0) {
                            return false;
                        }
                    }
                }
            }

            return true;
        }
    );

    $filteredListings = array_values($filteredListings);

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
            'url'   => roomhive_url([
                'amenities'           => $remainingAmenities ?: null,
                'amenities_submitted' => 1,
            ]),
        ];
    }

    $hasActiveFilters = !empty($activeFilters);

    $clearFiltersUrl = '/webprogg/Listings/listing.php';

    $perPage = 16;

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

    $todayTimestamp = time();

    function roomhive_url($overrides = [])
    {
        $params = $_GET;

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

    function roomhive_detail_url($listing)
    {
        if (!empty($listing['detail_url'])) {
            return $listing['detail_url'];
        }

        return '/webprogg/Listings/listing-detail.php?id=' . urlencode($listing['id']);
    }

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

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/listings-style.css">

    <style>

.js-hidden{display:none !important}

.listings-page{padding-top:130px}

/* ============ GRID SAFETY NET (4 cols, full width) ============ */
.listings-content{grid-template-columns:1fr !important}
#rh-listings-results{display:grid !important;grid-template-columns:repeat(4,minmax(0,1fr)) !important;gap:24px !important;width:100% !important}
#rh-listings-results.rh-list-view{grid-template-columns:1fr !important}
@media (max-width:1180px){#rh-listings-results{grid-template-columns:repeat(3,minmax(0,1fr)) !important}}
@media (max-width:900px){#rh-listings-results{grid-template-columns:repeat(2,minmax(0,1fr)) !important}}
@media (max-width:720px){#rh-listings-results{grid-template-columns:1fr !important}}

/* ============ AMENITY CHIPS ON CARDS ============ */
.rh-card-amenities{display:flex;flex-wrap:wrap;gap:5px;margin-top:2px}
.rh-chip-mini{font-size:.68rem;font-weight:600;color:#9c6a08;background:#f1e3c4;border:1px solid #e2d0a8;border-radius:999px;padding:3px 9px;white-space:nowrap}
.rh-chip-more{font-size:.68rem;font-weight:600;color:#57655c;align-self:center}

/* ============ KILL ACTIVE-FILTER CHIP ROW ============ */
.rh-chip-row,
.rh-chip {
    display: none !important;
}

/* ============ FILTER BARS — NON-STICKY ============ */
.filter-bar{position:static;top:auto}
.filter-bar.amenities-bar{position:static;top:auto;margin-top:8px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding:8px 10px}
.filter-bar.amenities-bar::-webkit-scrollbar{display:none}
@media (max-width:1180px){.filter-bar.amenities-bar{overflow-x:auto;flex-wrap:nowrap}}

.amenities-bar-label{flex:0 0 auto;padding:6px 12px}
.amenities-bar-title{font-size:.88rem;font-weight:600;color:var(--rh-ink);white-space:nowrap}
.amenities-bar-label .filter-icon{width:15px;height:15px}

.amenity-toggle-form{flex:0 0 auto;margin:0}

.amenity-toggle-btn{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--rh-line);background:var(--rh-cloud);border-radius:var(--rh-radius-pill);padding:8px 16px;font-family:inherit;font-size:.85rem;font-weight:500;color:var(--rh-ink-soft);cursor:pointer;white-space:nowrap;transition:border-color .15s ease,color .15s ease,background .15s ease}
.amenity-toggle-btn:hover{border-color:var(--rh-gold);color:var(--rh-gold-deep)}
.amenity-toggle-btn.active{background:var(--rh-gold-wash);border-color:var(--rh-gold);color:var(--rh-gold-deep);font-weight:600}

.amenity-ico{width:15px;height:15px;object-fit:contain;opacity:.75}
.amenity-toggle-btn.active .amenity-ico{opacity:1}

.amenity-check{font-size:.8rem;line-height:1}

.amenity-clear-btn{display:inline-flex;align-items:center;border:1px solid var(--rh-line);background:none;border-radius:var(--rh-radius-pill);padding:8px 16px;font-family:inherit;font-size:.83rem;font-weight:600;color:var(--rh-coral);cursor:pointer;white-space:nowrap;transition:border-color .15s ease,background .15s ease}
.amenity-clear-btn:hover{border-color:var(--rh-coral);background:#f6e2e2}

.category-dropdown-panel:not(.open){display:none}

.rh-rating-none{font-weight:500;color:var(--rh-ink-soft);font-size:.78rem;white-space:nowrap}

/* ============ PRICE RANGE — LIVE NUMBER FEEDBACK (FIX #23) ============ */
.price-value {
    transition: color .15s ease, transform .15s ease;
}

.price-value.price-live {
    color: var(--rh-gold-deep, #9c6a08);
    font-weight: 700;
    transform: scale(1.08);
}

    </style>

</head>

<body data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>">

    <!-- ========================= NAVIGATION BAR ========================= -->

 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

    <main class="listings-page">

        <!-- ========================= MAIN FILTER BAR ========================= -->

        <form
            class="filter-bar"
            method="get"
            action="/webprogg/Listings/listing.php"
            id="filter-form"
        >

            <div class="filter-group">

                <img
                    src="/webprogg/images/GPSIcon.png"
                    alt=""
                    class="filter-icon"
                    onerror="this.style.display='none';"
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

            <div class="filter-divider"></div>

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

        <!-- ========================= AMENITIES FILTER BAR ========================= -->

        <div class="filter-bar amenities-bar" id="amenities-bar">

            <div class="filter-group amenities-bar-label">

                <img
                    src="/webprogg/images/HouseIcon.png"
                    alt=""
                    class="filter-icon"
                >

                <span class="amenities-bar-title">Amenities</span>

            </div>

            <div class="filter-divider"></div>

            <?php foreach ($amenityOptions as $value => $label): ?>

                <?php
                    $isChecked = in_array($value, $selectedAmenities, true);
                    $remaining = array_values(array_diff($selectedAmenities, [$value]));
                    $newAmenities = $isChecked ? $remaining : array_merge($selectedAmenities, [$value]);
                ?>

                <form
                    method="get"
                    action="/webprogg/Listings/listing.php"
                    class="amenity-toggle-form"
                >

                    <input type="hidden" name="location" value="<?= htmlspecialchars($selectedLocation, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="price_min" value="<?= (int) $priceMin ?>">
                    <input type="hidden" name="price_max" value="<?= (int) $priceMax ?>">
                    <input type="hidden" name="amenities_submitted" value="1">

                    <?php foreach ($newAmenities as $am): ?>
                        <input type="hidden" name="amenities[]" value="<?= htmlspecialchars($am, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>

                    <button
                        type="submit"
                        class="amenity-toggle-btn<?= $isChecked ? ' active' : '' ?>"
                        aria-pressed="<?= $isChecked ? 'true' : 'false' ?>"
                    >
                        <?php if (!empty($amenityIcons[$value])): ?>
                            <img
                                src="<?= htmlspecialchars($amenityIcons[$value], ENT_QUOTES, 'UTF-8') ?>"
                                alt=""
                                class="amenity-ico"
                                onerror="this.style.display='none';"
                            >
                        <?php endif; ?>

                        <span class="amenity-check"><?= $isChecked ? '&#10003;' : '' ?></span>

                        <?= htmlspecialchars($label) ?>
                    </button>

                </form>

            <?php endforeach; ?>

            <?php if (!empty($selectedAmenities)): ?>

                <form
                    method="get"
                    action="/webprogg/Listings/listing.php"
                    class="amenity-toggle-form"
                >

                    <input type="hidden" name="location" value="<?= htmlspecialchars($selectedLocation, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="price_min" value="<?= (int) $priceMin ?>">
                    <input type="hidden" name="price_max" value="<?= (int) $priceMax ?>">
                    <input type="hidden" name="amenities_submitted" value="1">

                    <button
                        type="submit"
                        class="amenity-clear-btn"
                    >
                        Clear
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <!-- ========================= MAIN LISTINGS ========================= -->

        <section class="listings-content">

            <div class="listings-main">

                <div class="rh-results-bar">

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
                            Save this search
                        </button>

                    <?php else: ?>

                        <!-- FIX #24 — navigates to loginform.php like every
                             other login link (modal removed) -->
                        <a
                            class="rh-saved-toggle"
                            href="/webprogg/auth/loginform.php"
                        >
                            Save this search
                        </a>

                    <?php endif; ?>

                    <a
                        href="/webprogg/user/userwishlist.php"
                        class="rh-saved-toggle"
                        id="rh-wishlist-link"
                    >
                        My Wishlist
                    </a>

                </div>

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

                        $isSaved = rh_is_saved($listing['id']);

                        $chipsForCard = $listing['amenities'];

                        if ($listing['parking_available'] && !in_array('parking', $chipsForCard, true)) {
                            array_unshift($chipsForCard, 'parking');
                        }

                        $visibleAmenities = array_slice($chipsForCard, 0, 3);
                        $extraAmenities   = count($chipsForCard) - count($visibleAmenities);
                        ?>

                        <a
                            href="<?= htmlspecialchars(roomhive_detail_url($listing), ENT_QUOTES, 'UTF-8') ?>"
                            class="listing-box"
                            data-listing-id="<?= (int) $listing['id'] ?>"
                            data-price="<?= (float) $listing['price'] ?>"
                            data-rating="<?= (float) $listing['rating'] ?>"
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
                                    class="rh-save-btn<?= $isSaved ? ' saved' : '' ?>"
                                    data-listing-id="<?= (int) $listing['id'] ?>"
                                    data-saved="<?= $isSaved ? '1' : '0' ?>"
                                    aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                                    aria-label="Save <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <?= $isSaved ? '&#9829;' : '&#9825;' ?>
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

                                    <?php if ($listing['reviews'] > 0): ?>
                                        <span class="rh-rating">
                                            &#9733; <?= number_format($listing['rating'], 1) ?>
                                            <span class="rh-rating-count">(<?= (int) $listing['reviews'] ?>)</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="rh-rating-none">No reviews yet</span>
                                    <?php endif; ?>

                                </div>

                                <div class="listing-box-location">

                                    <img
                                        src="/webprogg/images/GPSIcon.png"
                                        alt=""
                                        class="rh-loc-ico"
                                        onerror="this.style.display='none';"
                                    >

                                    <span>
                                        <?= htmlspecialchars(
                                            $listing['location_label'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <!-- ⚠ VERIFY — reconstructed from the fix notes -->
                                <div class="rh-card-price-row">

                                    <span class="rh-card-price">
                                        &#8369; <?= number_format($listing['price']) ?>
                                        <span class="rh-card-per">/ month</span>
                                    </span>

                                </div>

                                <div class="rh-card-amenities">

                                    <?php foreach ($visibleAmenities as $amKey): ?>
                                        <span class="rh-chip-mini">
                                            <?= htmlspecialchars($amenityOptions[$amKey] ?? ucfirst($amKey), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <?php if ($extraAmenities > 0): ?>
                                        <span class="rh-chip-more">+<?= $extraAmenities ?> more</span>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                </div>

                <!-- ⚠ VERIFY — reconstructed pagination -->
                <?php if ($totalPages > 1): ?>

                    <div class="rh-pagination" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:center; margin-top:26px;">

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>

                            <a
                                href="<?= htmlspecialchars(roomhive_url(['page' => $p > 1 ? $p : null]), ENT_QUOTES, 'UTF-8') ?>"
                                style="
                                    min-width: 36px;
                                    padding: 8px 11px;
                                    text-align: center;
                                    border: 1px solid var(--rh-line, #E2E8F0);
                                    border-radius: 9px;
                                    font-size: 13px;
                                    font-weight: 700;
                                    text-decoration: none;
                                    color: <?= $p === $page ? '#ffffff' : 'var(--rh-ink-soft, #57655C)' ?>;
                                    background: <?= $p === $page ? 'var(--rh-gold, #d68e0e)' : '#ffffff' ?>;
                                "
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

    <script src="/webprogg/assets/javaScript.js"></script>

    <!-- =====================================================
         PRICE RANGE — LIVE NUMBERS (FIX #23)
         input  -> updates the displayed numbers LIVE while
                   dragging (capture phase + stopImmediate-
                   Propagation blocks javaScript.js's price
                   handler, which submits mid-drag).
         change -> debounced auto-apply on release so the
                   results refresh to the new range.
    ====================================================== -->
    <script>
    (function () {
        "use strict";

        var range = document.getElementById('price-range');
        var minEl = document.getElementById('price-min');
        var maxEl = document.getElementById('price-max');
        var form  = document.getElementById('filter-form');

        if (!range || !maxEl || !form) { return; }

        var PESO = '\u20B1';
        var submitTimer = null;

        function fmt(n) {
            return PESO + Number(n).toLocaleString('en-US');
        }

        function render() {
            var v = parseInt(range.value, 10) || 0;

            /* LIVE — the max number follows the thumb */
            maxEl.textContent = fmt(v);
            maxEl.classList.add('price-live');
            if (minEl) { minEl.classList.add('price-live'); }

            /* cancel a pending auto-apply if the user grabs
               the slider again before it fired */
            if (submitTimer) {
                window.clearTimeout(submitTimer);
                submitTimer = null;
            }
        }

        function settle() {
            maxEl.classList.remove('price-live');
            if (minEl) { minEl.classList.remove('price-live'); }

            /* auto-apply shortly after release */
            if (submitTimer) { window.clearTimeout(submitTimer); }
            submitTimer = window.setTimeout(function () {
                submitTimer = null;
                form.submit();
            }, 350);
        }

        /* CAPTURE PHASE — fires BEFORE javaScript.js's handlers;
           stopImmediatePropagation blocks them entirely. */
        document.addEventListener('input', function (e) {
            if (e.target !== range) { return; }
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
            render();
        }, true);

        document.addEventListener('change', function (e) {
            if (e.target !== range) { return; }
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
            settle();
        }, true);
    })();
    </script>

    <!-- =====================================================
         WISHLIST HEARTS (v3) + MY WISHLIST LINK + SORT + VIEW
         — early capture-phase handlers per fix notes #13/#16/
         #17/#21/#22. Kept from the confirmed version.
    ====================================================== -->
    <script>
    (function () {
        "use strict";

        var LOGGED_IN = document.body.getAttribute('data-logged-in') === '1';
        var busy = false;

        function setHeart(btn, saved) {
            btn.classList.toggle('saved', saved);
            btn.setAttribute('data-saved', saved ? '1' : '0');
            btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
            btn.innerHTML = saved ? '&#9829;' : '&#9825;';
        }

        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) { return; }

            var btn = e.target.closest('.rh-save-btn');
            if (!btn) { return; }

            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

            if (!LOGGED_IN) {
                /* FIX #24 — navigate to the login page like before */
                window.location.href = '/webprogg/auth/loginform.php';
                return;
            }

            if (busy) { return; }

            var listingId = btn.getAttribute('data-listing-id');
            if (!listingId) { return; }

            busy = true;

            fetch('/webprogg/user/togglewishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId),
                credentials: 'same-origin'
            })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (data) {
                busy = false;
                if (data && data.success) {
                    setHeart(btn, !!data.saved);
                } else if (data && data.login) {
                    window.location.href = '/webprogg/auth/loginform.php';
                } else {
                    setHeart(btn, btn.getAttribute('data-saved') === '1');
                    alert((data && data.message) || 'Could not update your wishlist.');
                }
            })
            .catch(function () {
                busy = false;
                setHeart(btn, btn.getAttribute('data-saved') === '1');
                alert('Could not update your wishlist. Please try again.');
            });
        }, true);

        /* My Wishlist navigation (FIX #21) */
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) { return; }
            var link = e.target.closest('#rh-wishlist-link');
            if (!link) { return; }
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
            window.location.href = '/webprogg/user/userwishlist.php';
        }, true);

        /* Sort (FIX #22 — capture owner) */
        var sortSel = document.getElementById('rh-sort');
        var grid    = document.getElementById('rh-listings-results');

        if (sortSel && grid) {
            document.addEventListener('change', function (e) {
                if (e.target !== sortSel) { return; }
                e.stopPropagation();
                if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

                var mode = sortSel.value;
                var cards = Array.prototype.slice.call(
                    grid.querySelectorAll('.listing-box')
                );

                Array.prototype.slice.call(grid.children).forEach(function (child) {
                    if (!child.classList.contains('listing-box') &&
                        !child.classList.contains('rh-empty-state')) {
                        grid.removeChild(child);
                    }
                });

                if (mode === 'price-asc' || mode === 'price-desc') {
                    cards.sort(function (a, b) {
                        var pa = parseFloat(a.getAttribute('data-price')) || 0;
                        var pb = parseFloat(b.getAttribute('data-price')) || 0;
                        return mode === 'price-asc' ? pa - pb : pb - pa;
                    });
                } else {
                    cards = [];
                }

                cards.forEach(function (card) { grid.appendChild(card); });
                sortSel.blur();
            }, true);
        }

        /* View toggle */
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) { return; }
            var vbtn = e.target.closest('.rh-view-btn');
            if (!vbtn || !grid) { return; }
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

            document.querySelectorAll('.rh-view-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            vbtn.classList.add('active');

            var view = vbtn.getAttribute('data-view');
            grid.classList.toggle('rh-list-view', view === 'list');
        }, true);

        /* Save-this-search (⚠ VERIFY: your endpoint if it differs) */
        var saveBtn = document.getElementById('rh-save-search-btn');
        if (saveBtn && LOGGED_IN) {
            saveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var original = saveBtn.textContent;
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                fetch('/webprogg/user/savesearch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        location:   saveBtn.getAttribute('data-location') || '',
                        category:   saveBtn.getAttribute('data-category') || '',
                        q:          saveBtn.getAttribute('data-q') || '',
                        price_min:  saveBtn.getAttribute('data-price-min') || '',
                        price_max:  saveBtn.getAttribute('data-price-max') || '',
                        amenities:  saveBtn.getAttribute('data-amenities') || ''
                    }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = (data && data.success)
                        ? '✓ Search saved'
                        : original;
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = original;
                });
            });
        }

        /* results count text */
        var countEl = document.getElementById('rh-results-count');
        var toolbar = document.querySelector('.rh-toolbar');
        if (countEl && toolbar) {
            var shown = parseInt(toolbar.getAttribute('data-shown'), 10) || 0;
            var total = parseInt(toolbar.getAttribute('data-total'), 10) || 0;
            countEl.textContent = shown > 0
                ? 'Showing ' + shown + ' of ' + total + ' listing' + (total === 1 ? '' : 's')
                : '';
        }
    })();
    </script>

</body>

</html>