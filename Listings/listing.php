<?php
    /* =========================
    listing.php

    === CHANGES ===
    1-12. (previous fixes: layout, filters synced with host
       form, hearts = wishlist via togglewishlist.php, chip
       row kill switch)
    13. WISHLIST BUTTON FIX v2 — the heart handler now runs
        from a small EARLY script placed BEFORE javaScript.js
        loads, registered on document in CAPTURE phase with
        stopImmediatePropagation(). Whatever handler
        javaScript.js adds (bubble OR capture, any order),
        ours fires first and blocks it — exactly ONE
        togglewishlist.php call per click. Guests get the
        login modal. Late script no longer handles hearts
        (no double-fire from our own code either).
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

    $autoOpenLoginPopup = !$isLoggedIn && empty($_SESSION['admin_logged_in']);

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

    $listingsStmt = $pdo->query(
        "SELECT l.id, l.title, l.category, l.location, l.exact_address, l.price,
                l.bedrooms, l.parking, l.amenities, l.created_at,
                p.photo_path AS cover_photo
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
        ORDER BY l.created_at DESC"
    );
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
            'rating'         => 0,
            'reviews'        => 0,
            'verified'       => false,
            'date_added'     => $row['created_at'],
        ];
    }, $listingsStmt->fetchAll());

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
        'parking'          => '/webprogg/images/parkingicon.png',
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

/* ============ LOGIN MODAL ============ */
.lx-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:24px;visibility:hidden;pointer-events:none}
.lx-modal.open{visibility:visible;pointer-events:auto}
.lx-modal-backdrop{position:absolute;inset:0;background:rgba(22,58,48,.38);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);opacity:0;transition:opacity .3s ease}
.lx-modal.open .lx-modal-backdrop{opacity:1}
.lx-modal-card{position:relative;z-index:1;width:362px;max-height:calc(100vh - 48px);overflow-y:auto;background:#fff;border-radius:22px;padding:32px 30px 26px;box-shadow:0 30px 70px rgba(22,58,48,.35);opacity:0;transform:translateY(26px) scale(.96);transition:opacity .32s cubic-bezier(.22,1,.36,1),transform .32s cubic-bezier(.22,1,.36,1)}
.lx-modal.open .lx-modal-card{opacity:1;transform:translateY(0) scale(1);animation:lxFloat 5s ease-in-out .4s infinite}
.lx-modal-card::before{content:"";position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#dd930f,#fbf1dc,#dd930f);border-radius:22px 22px 0 0}
.lx-modal-close{position:absolute;top:12px;right:14px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:#f4f1e7;border:none;border-radius:50%;color:#62705f;font-size:17px;line-height:1;cursor:pointer;transition:background .15s ease,color .15s ease,transform .15s ease}
.lx-modal-close:hover{background:#dd930f;color:#fff;transform:rotate(90deg)}
.lx-modal-logo{text-align:center;margin-bottom:10px}
.lx-modal-logo img{width:96px;display:inline-block}
.lx-modal-title{margin:0 0 16px;font-family:"Fraunces",serif;font-size:1.45rem;font-weight:600;text-align:center;color:#1c2b24}
.lx-hint{padding:10px 14px;margin-bottom:14px;background:#fbf1dc;border:1px solid #f0dcb4;border-radius:11px;color:#b8760a;font-size:.8rem;font-weight:600;text-align:center}
.lx-field{margin-bottom:12px}
.lx-field label{display:flex;align-items:center;gap:6px;margin-bottom:6px;color:#1c2b24;font-size:.82rem;font-weight:500}
.lx-input{width:100%;height:44px;padding:0 13px;background:#fdfcf8;border:1.5px solid #e8e1cf;border-radius:11px;outline:none;color:#1c2b24;font-family:"Poppins",sans-serif;font-size:.9rem;transition:border-color .2s ease,box-shadow .2s ease,background .2s ease}
.lx-input:focus{background:#fff;border-color:#dd930f;box-shadow:0 0 0 4px rgba(221,147,15,.14)}
.lx-forgot{text-align:center;margin:4px 0 12px}
.lx-forgot a{color:#1c2b24;font-size:.8rem;font-weight:600;text-decoration:none}
.lx-forgot a:hover{color:#b8760a}
.lx-submit{width:100%;height:46px;background:linear-gradient(135deg,#eda423,#dd930f);border:none;border-radius:12px;color:#fff;font-family:"Poppins",sans-serif;font-size:.92rem;font-weight:700;letter-spacing:.02em;cursor:pointer;box-shadow:0 8px 18px rgba(221,147,15,.35);transition:transform .2s ease,box-shadow .2s ease}
.lx-submit:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(221,147,15,.45)}
.lx-create{margin:14px 0 0;text-align:center;color:#62705f;font-size:.83rem}
.lx-create a{color:#b8760a;font-weight:700;text-decoration:none}
.lx-create a:hover{text-decoration:underline}
@keyframes lxFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@media (max-width:480px){.lx-modal{padding:14px}.lx-modal-card{width:100%;padding:26px 20px 22px}}

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
.rh-chip-mini{font-size:.68rem;font-weight:600;color:#b8760a;background:#fbf1dc;border:1px solid #f0dcb4;border-radius:999px;padding:3px 9px;white-space:nowrap}
.rh-chip-more{font-size:.68rem;font-weight:600;color:#62705f;align-self:center}

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
.amenity-clear-btn:hover{border-color:var(--rh-coral);background:#fceaea}

.category-dropdown-panel:not(.open){display:none}

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
                            >
                        <?php endif; ?>

                        <span class="amenity-check"><?= $isChecked ? '&#10003;' : '&#9825;' ?></span>

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

                    <a
                        href="/webprogg/user/userwishlist.php"
                        class="rh-saved-toggle"
                    >
                        <span class="rh-heart-icon">&#9829;</span>
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

                                <?php if (!empty($visibleAmenities)): ?>

                                    <div class="rh-card-amenities">

                                        <?php foreach ($visibleAmenities as $amKey): ?>

                                            <?php
                                            $amLabel = isset($amenityOptions[$amKey])
                                                ? $amenityOptions[$amKey]
                                                : ucwords(str_replace('-', ' ', $amKey));
                                            ?>

                                            <span class="rh-chip-mini">
                                                <?= htmlspecialchars($amLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                        <?php endforeach; ?>

                                        <?php if ($extraAmenities > 0): ?>

                                            <span class="rh-chip-more">
                                                +<?= $extraAmenities ?> more
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

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

                <!-- ========================= PAGINATION ========================= -->

                <?php if ($totalPages > 1): ?>

                    <div class="listings-pagination">

                        <?php if ($page > 1): ?>

                            <a
                                class="page-arrow"
                                href="<?= htmlspecialchars(
                                    roomhive_url(['page' => $page - 1]),
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

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>

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
                                    roomhive_url(['page' => $p]),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>

                            <a
                                class="page-arrow"
                                href="<?= htmlspecialchars(
                                    roomhive_url(['page' => $page + 1]),
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

        </section>

    </main>

    <!-- ========================= FOOTER ========================= -->
    <footer class="site-footer">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="/webprogg/index.php">
                    <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
                </a>
                <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
                <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
                <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>kimdivino55@gmail.com</span></div>
                <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
            </div>
            <div class="footer-links">
                <span class="footer-heading">LISTINGS</span>
                <a href="/webprogg/Listings/listing.php?category=studio-loft">Studios</a>
                <a href="/webprogg/Listings/listing.php?category=shared-bedroom">Shared Rooms</a>
                <a href="/webprogg/Listings/listing.php?category=entire-house">Entire House</a>
                <a href="/webprogg/Listings/listing.php">Featured Stays</a>
            </div>
            <div class="footer-links">
                <span class="footer-heading">QUICK LINKS</span>
                <a href="/webprogg/index.php">About Us</a>
                <a href="/webprogg/misc/contacts.php">Contact</a>
                <a href="/webprogg/host/becomeahost.php">Become a Host</a>
                <a href="/webprogg/hiveclub.php">Hive Club</a>
            </div>
            <div class="footer-contact">
                <span class="footer-heading">GET THE APP</span>
                <div class="footer-app-badges">
                    <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                    <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> RoomHive. All rights reserved.</p>
        </div>
    </footer>

    <!-- ========================= LOGIN MODAL (guests) ========================= -->
    <?php if ($autoOpenLoginPopup): ?>
    <div class="lx-modal" id="lx-login-modal">
        <div class="lx-modal-backdrop" data-lx-close></div>
        <div class="lx-modal-card">
            <button type="button" class="lx-modal-close" data-lx-close aria-label="Close">&times;</button>
            <div class="lx-modal-logo">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive">
            </div>
            <h3 class="lx-modal-title">Welcome back to the Hive</h3>
            <p class="lx-hint" id="lx-login-hint" style="display:none;">
                Log in to save listings to your wishlist
            </p>
            <form method="post" action="/webprogg/auth/login_process.php">
                <div class="lx-field">
                    <label for="lx-email">Email</label>
                    <input class="lx-input" type="email" id="lx-email" name="email" required autocomplete="email">
                </div>
                <div class="lx-field">
                    <label for="lx-password">Password</label>
                    <input class="lx-input" type="password" id="lx-password" name="password" required autocomplete="current-password">
                </div>
                <p class="lx-forgot"><a href="/webprogg/auth/forgotpassword.php">Forgot password?</a></p>
                <button class="lx-submit" type="submit">Log In</button>
            </form>
            <p class="lx-create">Don't have an account? <a href="/webprogg/auth/registerform.php">Create one</a></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- =========================================================
         CHANGE #13 v2 — HEARTS, EARLY SCRIPT.
         Registered BEFORE javaScript.js loads, document
         CAPTURE phase, stopImmediatePropagation(): our handler
         fires first no matter what javaScript.js registers
         (bubble or capture, any order), and blocks every other
         handler. Exactly ONE togglewishlist.php call per click.
         The late script no longer touches hearts at all.
    ========================================================== -->
    <script>
    (function () {
        "use strict";

        var isLoggedIn = document.body.getAttribute('data-logged-in') === '1';
        var saveBusy = false;

        function openLoginForWishlist() {
            var loginModal = document.getElementById('lx-login-modal');
            var loginHint  = document.getElementById('lx-login-hint');

            if (loginModal) {
                if (loginHint) loginHint.style.display = '';
                loginModal.classList.add('open');
            } else {
                window.location.href = '/webprogg/auth/loginform.php';
            }
        }

        function toggleWishlist(btn) {
            if (saveBusy) { return; }

            if (!isLoggedIn) {
                openLoginForWishlist();
                return;
            }

            var listingId = btn.getAttribute('data-listing-id');
            if (!listingId) { return; }

            saveBusy = true;
            btn.disabled = true;

            fetch('/webprogg/user/togglewishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'listing_id=' + encodeURIComponent(listingId),
                credentials: 'same-origin'
            })
            .then(function (res) {
                if (!res.ok) {
                    return res.text().then(function (t) {
                        throw new Error('HTTP ' + res.status + ': ' + t.substring(0, 150));
                    });
                }
                return res.json();
            })
            .then(function (data) {
                saveBusy = false;
                btn.disabled = false;

                if (!data || !data.success) {
                    if (data && data.login) {
                        isLoggedIn = false;
                        openLoginForWishlist();
                    } else {
                        alert((data && data.message) || 'Could not update your wishlist.');
                    }
                    return;
                }

                var saved = !!data.saved;

                btn.classList.toggle('saved', saved);
                btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
                btn.innerHTML = saved ? '&#9829;' : '&#9825;';
            })
            .catch(function (err) {
                saveBusy = false;
                btn.disabled = false;
                console.error('[wishlist toggle]', err);
                alert('Wishlist update failed: ' + (err && err.message ? err.message : 'network error'));
            });
        }

        /* Capture-phase + registered FIRST + stopImmediatePropagation
           = nothing can run before or after us for this click. */
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) { return; }

            var heart = e.target.closest('.rh-save-btn');
            if (heart) {
                e.preventDefault();
                e.stopImmediatePropagation();
                toggleWishlist(heart);
                return;
            }

            /* login modal close (data-lx-close) — also early */
            if (e.target.closest('[data-lx-close]')) {
                var m = document.getElementById('lx-login-modal');
                if (m) { m.classList.remove('open'); }
            }
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var m = document.getElementById('lx-login-modal');
                if (m) { m.classList.remove('open'); }
            }
        });
    })();
    </script>

    <script src="/webprogg/assets/javaScript.js"></script>

    <!-- =========================================================
         PAGE SCRIPTS (late) — everything EXCEPT hearts.
    ========================================================== -->
    <script>
    (function () {
        "use strict";

        var resultsGrid = document.getElementById('rh-listings-results');

        /* ============ PRICE RANGE SLIDER ============ */
        var priceRange = document.getElementById('price-range');
        var priceMaxLabel = document.getElementById('price-max');

        if (priceRange && priceMaxLabel) {
            priceRange.addEventListener('input', function () {
                priceMaxLabel.textContent =
                    Number(this.value).toLocaleString();
            });
            priceRange.addEventListener('change', function () {
                document.getElementById('filter-form').submit();
            });
        }

        /* ============ CATEGORY DROPDOWN ============ */
        var catToggle = document.getElementById('categoryDropdownToggle');
        var catPanel  = document.getElementById('categoryDropdownPanel');

        if (catToggle && catPanel) {
            catToggle.addEventListener('click', function (e) {
                e.stopPropagation();

                var isOpen = catPanel.classList.contains('open');
                catPanel.classList.toggle('open', !isOpen);
                catToggle.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', function (e) {
                if (!catPanel.contains(e.target) && e.target !== catToggle) {
                    catPanel.classList.remove('open');
                    catToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        /* ============ RESULTS COUNT ============ */
        var toolbar      = document.querySelector('.rh-toolbar');
        var resultsCount = document.getElementById('rh-results-count');

        if (toolbar && resultsCount) {
            var shown = parseInt(toolbar.getAttribute('data-shown'), 10) || 0;
            var total = parseInt(toolbar.getAttribute('data-total'), 10) || 0;
            resultsCount.textContent =
                'Showing ' + shown + ' of ' + total +
                ' space' + (total === 1 ? '' : 's');
        }

        /* ============ SORT + VIEW TOGGLE ============ */
        var sortSelect = document.getElementById('rh-sort');

        if (resultsGrid && sortSelect) {
            var originalOrder = Array.prototype.slice.call(
                resultsGrid.querySelectorAll('.listing-box')
            );

            sortSelect.addEventListener('change', function () {
                var list = originalOrder.slice();

                if (sortSelect.value === 'price-asc') {
                    list.sort(function (a, b) {
                        return (parseFloat(a.dataset.price) || 0) -
                               (parseFloat(b.dataset.price) || 0);
                    });
                } else if (sortSelect.value === 'price-desc') {
                    list.sort(function (a, b) {
                        return (parseFloat(b.dataset.price) || 0) -
                               (parseFloat(a.dataset.price) || 0);
                    });
                }

                list.forEach(function (card) {
                    resultsGrid.appendChild(card);
                });
            });
        }

        var viewBtns = document.querySelectorAll('.rh-view-btn');

        viewBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                viewBtns.forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');

                if (resultsGrid) {
                    resultsGrid.classList.toggle(
                        'rh-list-view',
                        btn.getAttribute('data-view') === 'list'
                    );
                }
            });
        });

        /* ============ SAVE THIS SEARCH (localStorage) ============ */
        var saveSearchBtn = document.getElementById('rh-save-search-btn');

        if (saveSearchBtn) {
            saveSearchBtn.addEventListener('click', function () {
                var searches = [];
                try {
                    searches = JSON.parse(
                        localStorage.getItem('roomhive_saved_searches')
                    ) || [];
                } catch (e) {
                    searches = [];
                }

                searches.push({
                    location:  saveSearchBtn.getAttribute('data-location'),
                    category:  saveSearchBtn.getAttribute('data-category'),
                    q:         saveSearchBtn.getAttribute('data-q'),
                    price_min: saveSearchBtn.getAttribute('data-price-min'),
                    price_max: saveSearchBtn.getAttribute('data-price-max'),
                    amenities: saveSearchBtn.getAttribute('data-amenities'),
                    saved_at:  Date.now()
                });

                localStorage.setItem(
                    'roomhive_saved_searches',
                    JSON.stringify(searches)
                );

                var original = saveSearchBtn.innerHTML;
                saveSearchBtn.innerHTML =
                    '<span class="rh-heart-icon">&#9829;</span> Search saved!';
                saveSearchBtn.disabled = true;

                setTimeout(function () {
                    saveSearchBtn.innerHTML = original;
                    saveSearchBtn.disabled = false;
                }, 2000);
            });
        }

        /* ============ LOGIN MODAL AUTO-OPEN (guests) ============ */
        var loginModal = document.getElementById('lx-login-modal');

        if (loginModal && document.body.getAttribute('data-logged-in') !== '1') {
            setTimeout(function () {
                loginModal.classList.add('open');
            }, 600);
        }

    })();
    </script>

</body>

</html>