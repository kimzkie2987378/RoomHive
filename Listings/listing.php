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

        <!-- Poppins Font -->
        <link
            href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        >

        <!-- CSS -->
        <link rel="stylesheet" href="/webprogg/assets/style.css">

        <!-- =========================
            LISTINGS PAGE ENHANCEMENTS
            (scoped here so nothing in style.css
            needs to change)
        ========================== -->
        <style>

            /* EXPLORE MORE SPACES — horizontal scroll carousel */
            .rh-carousel-section {
                margin: 22px 0 8px;
            }

            .rh-carousel-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 12px;
            }

            .rh-carousel-header h3 {
                margin: 0;
                font-size: 1.15rem;
                color: #222;
            }

            .rh-carousel-arrows {
                display: flex;
                gap: 8px;
            }

            .rh-carousel-arrow {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border: 1px solid #e0e0e0;
                background: #fff;
                cursor: pointer;
                font-size: 0.85rem;
                color: #444;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: border-color 0.15s ease, color 0.15s ease;
            }

            .rh-carousel-arrow:hover {
                border-color: #f7941d;
                color: #f7941d;
            }

            .rh-carousel-arrow:disabled {
                opacity: 0.35;
                cursor: default;
            }

            .rh-carousel-arrow:disabled:hover {
                border-color: #e0e0e0;
                color: #444;
            }

            .rh-carousel-track {
                display: flex;
                gap: 14px;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                padding-bottom: 6px;
                scrollbar-width: none;
            }

            .rh-carousel-track::-webkit-scrollbar {
                display: none;
            }

            .rh-carousel-card {
                flex: 0 0 auto;
                width: 220px;
                scroll-snap-align: start;
                border-radius: 12px;
                overflow: hidden;
                text-decoration: none;
                color: inherit;
                background: #fff;
                border: 1px solid #eee;
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .rh-carousel-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
            }

            .rh-carousel-card img {
                width: 100%;
                height: 130px;
                object-fit: cover;
                display: block;
            }

            .rh-carousel-card-info {
                padding: 10px 12px 12px;
            }

            .rh-carousel-card-info h4 {
                margin: 0 0 4px;
                font-size: 0.9rem;
                color: #222;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .rh-carousel-card-info p {
                margin: 0;
                font-size: 0.8rem;
                color: #777;
            }

            @media (max-width: 600px) {
                .rh-carousel-card {
                    width: 170px;
                }
            }

            /* RESULTS BAR */
            .rh-results-bar {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 10px;
                margin: 18px 0 10px;
            }

            .rh-saved-toggle {
                display: flex;
                align-items: center;
                gap: 6px;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 999px;
                padding: 6px 14px;
                font-size: 0.85rem;
                font-family: inherit;
                cursor: pointer;
                color: #444;
                transition: border-color 0.15s ease, color 0.15s ease;
            }

            .rh-saved-toggle:hover {
                border-color: #f7941d;
                color: #f7941d;
            }

            .rh-saved-toggle.active {
                background: #fff4e8;
                border-color: #f7941d;
                color: #f7941d;
            }

            .rh-heart-icon {
                font-size: 1rem;
                line-height: 1;
            }

            /* EMPTY STATE */
            .rh-empty-state {
                grid-column: 1 / -1;
                text-align: center;
                padding: 48px 20px;
                border: 1px dashed #e0e0e0;
                border-radius: 12px;
                background: #fafafa;
            }

            .rh-empty-title {
                font-size: 1.1rem;
                font-weight: 600;
                color: #333;
                margin: 0 0 6px;
            }

            .rh-empty-subtitle {
                font-size: 0.9rem;
                color: #777;
                margin: 0 0 18px;
            }

            .rh-empty-clear-btn {
                display: inline-block;
                background: #f7941d;
                color: #fff;
                text-decoration: none;
                padding: 10px 22px;
                border-radius: 999px;
                font-size: 0.9rem;
                font-weight: 600;
            }

            /* CARD ENHANCEMENTS */
            .listing-box {
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .listing-box:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            }

            .rh-card-media {
                position: relative;
            }

            .rh-card-badges {
                position: absolute;
                top: 10px;
                left: 10px;
                display: flex;
                gap: 6px;
            }

            .rh-badge {
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                padding: 4px 9px;
                border-radius: 999px;
                color: #fff;
            }

            .rh-badge-verified {
                background: #2f9e5c;
            }

            .rh-badge-new {
                background: #f7941d;
            }

            .rh-save-btn {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border: none;
                background: rgba(255, 255, 255, 0.9);
                font-size: 1.1rem;
                line-height: 1;
                color: #999;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
                transition: color 0.15s ease, transform 0.1s ease;
            }

            .rh-save-btn:hover {
                transform: scale(1.08);
            }

            .rh-save-btn.saved {
                color: #e0505a;
            }

            .rh-card-title-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
            }

            .rh-rating {
                white-space: nowrap;
                font-size: 0.85rem;
                font-weight: 600;
                color: #333;
            }

            .rh-rating-count {
                font-weight: 400;
                color: #999;
            }

            .rh-bedrooms {
                color: #888;
                margin-left: 4px;
            }

            .listing-box.rh-hidden {
                display: none !important;
            }

            /* SAVE THIS SEARCH BUTTON */
            .rh-save-search-btn:disabled {
                opacity: 0.7;
                cursor: default;
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

                    <a href="#">
                        How to rent a room?
                    </a>

                    <a href="#">
                        How to search listings?
                    </a>

                    <a href="#">
                        How to become a host?
                    </a>

                    <a href="#">
                        Payment &amp; booking
                    </a>

                    <a href="#">
                        Location help
                    </a>

                    <a href="#">
                        Contact Support
                    </a>

                </div>

            </aside>

        </section>

    </main>

    <!-- =========================
        FOOTER
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
        (Category dropdown toggle now lives ONLY in javaScript.js —
        it was previously duplicated here too, which caused two
        click listeners to fire on every tap and cancel each other
        out, so the panel wouldn't reliably open/close.)
    ========================== -->

    <script>

    /* =========================
    PRICE RANGE
    ========================== */

    const priceRange =
        document.getElementById('price-range');

    const priceMaxLabel =
        document.getElementById('price-max');

    if (priceRange && priceMaxLabel) {

        priceRange.addEventListener(
            'input',
            function () {

                priceMaxLabel.textContent =
                    Number(this.value).toLocaleString();

            }
        );

        priceRange.addEventListener(
            'change',
            function () {

                const form =
                    document.getElementById('filter-form');

                if (form) {
                    form.submit();
                }

            }
        );
    }


    /* =========================
    CLEAR AMENITIES
    ========================== */

    function roomhiveClearAmenities() {

        const form =
            document.getElementById('amenities-form');

        if (!form) {
            return;
        }

        const checkboxes =
            form.querySelectorAll(
                'input[name="amenities[]"]'
            );

        checkboxes.forEach(function (checkbox) {
            checkbox.checked = false;
        });

        form.submit();
    }


    /* =========================
    SAVE THIS SEARCH
    (only rendered as a <button> for logged-in users —
    guests get a plain link to the login page instead,
    so this listener has nothing to attach to for them)
    ========================== */

    const saveSearchBtn = document.getElementById('rh-save-search-btn');

    if (saveSearchBtn) {

        saveSearchBtn.addEventListener('click', function () {

            const label = window.prompt('Name this search (optional):', '');

            if (label === null) {
                return; // user cancelled the prompt
            }

            const amenitiesRaw = saveSearchBtn.dataset.amenities;
            const amenities = amenitiesRaw ? amenitiesRaw.split(',') : [];

            const body = new URLSearchParams();
            body.append('label', label);
            body.append('location', saveSearchBtn.dataset.location);
            body.append('category', saveSearchBtn.dataset.category);
            body.append('q', saveSearchBtn.dataset.q);
            body.append('price_min', saveSearchBtn.dataset.priceMin);
            body.append('price_max', saveSearchBtn.dataset.priceMax);
            amenities.forEach(function (amenity) {
                body.append('amenities[]', amenity);
            });

            saveSearchBtn.disabled = true;

            fetch('/webprogg/SavedSearches/save-search.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {

                    saveSearchBtn.disabled = false;

                    if (data.ok) {
                        saveSearchBtn.innerHTML = '<span class="rh-heart-icon">&#128190;</span> Saved!';
                        setTimeout(function () {
                            saveSearchBtn.innerHTML = '<span class="rh-heart-icon">&#128190;</span> Save this search';
                        }, 1500);
                    } else {
                        alert(data.error || 'Could not save search.');
                    }

                })
                .catch(function () {
                    saveSearchBtn.disabled = false;
                    alert('Could not save search.');
                });

        });

    }

    </script>

    <!-- MAIN JAVASCRIPT (handles category dropdown open/close) -->
    <script src="/webprogg/assets/javaScript.js"></script>

    </body>
    </html>