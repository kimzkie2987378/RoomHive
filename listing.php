<?php
/* =========================
   listing.php
========================== */
session_start();

/* =========================
   NEGROS ORIENTAL LOCATION DATA
========================== */
require_once __DIR__ . '/negros-oriental-locations.php';
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
   LISTINGS DATA
========================== */
$listings = [
    [
        'name'  => 'STUDIO LOFT',
        'image' => 'images/StudioLoft.png',
        'slug'  => 'studioloft'
    ],
    [
        'name'  => 'SHARED ROOM',
        'image' => 'images/SharedBedroom.png',
        'slug'  => 'sharedbedroom'
    ],
    [
        'name'  => 'ENTIRE HOUSE',
        'image' => 'images/EntireHouse.png',
        'slug'  => 'entirehouse'
    ],
    [
        'name'  => 'PRIVATE ROOM',
        'image' => 'images/PrivateRoom.png',
        'slug'  => 'privateroom'
    ],
    [
        'name'  => 'BOARDING HOUSE',
        'image' => 'images/BoardingHouse.png',
        'slug'  => 'boardinghouse'
    ],
    [
        'name'  => 'APARTMENT',
        'image' => 'images/Apartment.png',
        'slug'  => 'apartment'
    ],
];

/* =========================
   ALL LISTINGS
========================== */
$allListings = [
    [
        'id'             => 1,
        'title'          => 'Studio For Rent',
        'image'          => 'images/StudioForRent.png',
        'location'       => 'dumaguete',
        'location_label' => 'Dumaguete City',
        'category'       => 'studioloft',
        'price'          => 5000,
        'amenities'      => ['wifi', 'aircon'],
        'bedrooms'       => 1,
        'rating'         => 4.8,
        'reviews'        => 24,
        'verified'       => true,
        'date_added'     => '2026-08-28',
    ],
    [
        'id'             => 2,
        'title'          => 'Private Room',
        'image'          => 'images/PrivateRoom.png',
        'location'       => 'sibulan',
        'location_label' => 'Sibulan',
        'category'       => 'privateroom',
        'price'          => 3500,
        'amenities'      => ['wifi', 'free-water'],
        'bedrooms'       => 1,
        'rating'         => 4.5,
        'reviews'        => 11,
        'verified'       => true,
        'date_added'     => '2026-07-15',
    ],
    [
        'id'             => 3,
        'title'          => 'Shared Bedroom',
        'image'          => 'images/SharedBedroom.png',
        'location'       => 'bais',
        'location_label' => 'City of Bais',
        'category'       => 'sharedbedroom',
        'price'          => 2800,
        'amenities'      => ['wifi'],
        'bedrooms'       => 1,
        'rating'         => 4.2,
        'reviews'        => 6,
        'verified'       => false,
        'date_added'     => '2026-06-02',
    ],
    [
        'id'             => 4,
        'title'          => 'Cozy Apartment',
        'image'          => 'images/Apartment.png',
        'location'       => 'dumaguete',
        'location_label' => 'Dumaguete City',
        'category'       => 'apartment',
        'price'          => 6200,
        'amenities'      => ['wifi', 'parking', 'aircon'],
        'bedrooms'       => 2,
        'rating'         => 4.9,
        'reviews'        => 38,
        'verified'       => true,
        'date_added'     => '2026-08-30',
    ],
    [
        'id'             => 5,
        'title'          => 'Boarding House Room',
        'image'          => 'images/BoardingHouse.png',
        'location'       => 'valencia',
        'location_label' => 'Valencia',
        'category'       => 'boardinghouse',
        'price'          => 4000,
        'amenities'      => ['wifi', 'free-electricity'],
        'bedrooms'       => 1,
        'rating'         => 4.0,
        'reviews'        => 9,
        'verified'       => false,
        'date_added'     => '2026-05-20',
    ],
    [
        'id'             => 6,
        'title'          => 'Entire House',
        'image'          => 'images/EntireHouse.png',
        'location'       => 'tanjay',
        'location_label' => 'City of Tanjay',
        'category'       => 'entirehouse',
        'price'          => 12000,
        'amenities'      => ['wifi', 'parking', 'pet-friendly'],
        'bedrooms'       => 4,
        'rating'         => 4.7,
        'reviews'        => 15,
        'verified'       => true,
        'date_added'     => '2026-08-20',
    ],
    [
        'id'             => 7,
        'title'          => 'Shaira Dumaguete Apartment For Rent',
        'image'          => 'images/ShairaDumagureApartmentForRent.jpg',
        'detail_url'     => 'ShairaDumagureApartmentForRent.php',
        'location'       => 'dumaguete',
        'location_label' => 'Dumaguete City',
        'category'       => 'apartment',
        'price'          => 6500,
        'amenities'      => ['wifi', 'aircon', 'parking'],
        'bedrooms'       => 2,
        'rating'         => 4.6,
        'reviews'        => 12,
        'verified'       => true,
        'date_added'     => '2026-09-01',
    ],
    [
        'id'             => 8,
        'title'          => 'Casiple Studio For Rent',
        'image'          => 'images/CasipleStudioForRentDauin.jpg',
        'detail_url'     => 'CasipleStudioForRentDauin.php',
        'location'       => 'dauin',
        'location_label' => 'Dauin',
        'category'       => 'studioloft',
        'price'          => 4500,
        'amenities'      => ['wifi'],
        'bedrooms'       => 1,
        'rating'         => 4.4,
        'reviews'        => 8,
        'verified'       => false,
        'date_added'     => '2026-08-25',
    ],
    [
        'id'             => 9,
        'title'          => 'Domingo House For Rent',
        'image'          => 'images/DomingoHouseForRent.jpg',
        'detail_url'     => 'DomingoHouseForRent.php',
        'location'       => 'dumaguete',
        'location_label' => 'Dumaguete City',
        'category'       => 'entirehouse',
        'price'          => 9000,
        'amenities'      => ['wifi', 'parking', 'pet-friendly'],
        'bedrooms'       => 3,
        'rating'         => 4.3,
        'reviews'        => 5,
        'verified'       => false,
        'date_added'     => '2026-08-15',
    ],
];

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
    : 10000;

/* Keep price values inside allowed range */
$priceMin = max(0, min($priceMin, 10000));
$priceMax = max(0, min($priceMax, 10000));

/* Prevent minimum from being greater than maximum */
if ($priceMin > $priceMax) {
    $priceMin = 0;
}

/* =========================
   AMENITIES INPUT
========================== */

$selectedAmenities = $_GET['amenities'] ?? ['wifi'];

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

        /* LOCATION */
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

if ($priceMax < 10000) {
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
        'url'   => roomhive_url(['amenities' => $remainingAmenities ?: null]),
    ];
}

$hasActiveFilters = !empty($activeFilters);

$clearFiltersUrl = 'listing.php';

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

    return 'listing.php?' . http_build_query($params);
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

    return 'listing-detail.php?id=' . urlencode($listing['id']);
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
    <link rel="stylesheet" href="style.css">

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

    </style>

</head>

<body>

<!-- =========================
     NAVIGATION BAR
========================== -->

<header class="navbar">

    <!-- LOGO -->
    <a
        href="<?= $isLoggedIn ? 'usershome.php' : 'index.php' ?>"
        class="logo"
    >
        <img
            src="images/RoomHiveLogos.png"
            alt="RoomHive Logo"
        >
    </a>

    <!-- NAVIGATION -->
    <nav class="nav-links">

        <!-- HOME -->
        <a
            href="<?= $isLoggedIn ? 'usershome.php' : 'index.php' ?>"
        >
            HOME
        </a>

        <!-- LISTINGS -->
        <a href="listing.php" class="active">
            LISTINGS
        </a>

        <!-- HOW IT WORKS -->
        <a href="howitworks.php">
            HOW IT WORKS
        </a>

        <!-- BECOME A HOST -->
        <a
            href="<?= $isLoggedIn ? 'becomeahost.php' : 'loginform.php' ?>"
        >
            BECOME A HOST
        </a>

        <!-- HIVE CLUB -->
        <a href="hiveclub.php">
            HIVE CLUB
        </a>

        <!-- CONTACTS -->
        <a href="contacts.php">
            CONTACTS
        </a>

        <?php if ($isLoggedIn): ?>

            <!-- MY ACCOUNT -->
            <div class="account-dropdown js-account-dropdown">

        <button
        type="button"
        class="my-account js-account-toggle"
        id="accountDropdownToggle"
        aria-haspopup="true"
        aria-expanded="false"
        >

                    <span class="account-circle">

                        <img
                            src="images/MyAccountIcon.png"
                            alt="My Account"
                        >

                    </span>

                    <span>MY ACCOUNT</span>

                    <span class="dropdown-caret">
                        &#9662;
                    </span>

                </button>

                <div
                    class="account-dropdown-menu"
                    id="accountDropdownMenu"
                >

                    <a href="myaccount.php">
                        My Account
                    </a>

                    <a href="logout.php">
                        Logout
                    </a>

                </div>

            </div>

        <?php else: ?>

            <!-- GUEST -->
            <a
                href="loginform.php"
                class="list-space"
            >
                LIST YOUR SPACE
            </a>

        <?php endif; ?>

    </nav>

</header>

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
            src="images/living_room_illustration.png"
            alt=""
            class="greeting-illustration"
        >

    </div>

    <!-- =========================
         EXPLORE MORE SPACES
         (horizontally scrollable strip of every listing,
         independent of the filters/pagination below —
         scroll or use the arrows to browse them all)
    ========================== -->

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

    <!-- =========================
         FILTER BAR
    ========================== -->

    <form
        class="filter-bar"
        method="get"
        action="listing.php"
        id="filter-form"
    >

        <!-- LOCATION -->

        <div class="filter-group">

            <img
                src="images/GPSIcon.png"
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
                src="images/DownwardArrow.png"
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
                src="images/HouseIcon.png"
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
                src="images/DownwardArrow.png"
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
                max="10000"
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
                    src="images/SearchIcon_orange.png"
                    alt="Search"
                >

            </button>

        </div>

        <!-- PRESERVE AMENITIES -->

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
                 SAVED-ONLY TOGGLE
            ========================== -->

            <div class="rh-results-bar">

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
                                    src="images/GPSIcon.png"
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
                                src="images/LookingLeftArrow.png"
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
                                src="images/LookingLeftArrow.png"
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
                                src="images/LookingRightArrow.png"
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
                                src="images/LookingRightArrow.png"
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
                action="listing.php"
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
                src="images/RoomHiveLogos.png"
                alt="RoomHive Logo"
                class="footer-logo"
            >

            <p class="footer-tagline">
                Find your next room, studio, or shared space —
                verified listings, no hidden fees.
            </p>

            <div class="footer-contact-line">

                <img
                    src="images/PhoneIcon.jpg"
                    alt=""
                >

                <span>
                    0917 156 3974
                </span>

            </div>

            <div class="footer-contact-line">

                <img
                    src="images/EmailIcon.jpg"
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

            <a href="listing.php?category=studioloft">
                Studios
            </a>

            <a href="listing.php?category=sharedbedroom">
                Shared Rooms
            </a>

            <a href="listing.php?category=entirehouse">
                Entire House
            </a>

            <a href="listing.php">
                Featured Stays
            </a>

        </div>

        <!-- QUICK LINKS -->

        <div class="footer-links">

            <span class="footer-heading">
                QUICK LINKS
            </span>

            <a href="index.php">
                About Us
            </a>

            <a href="contacts.php">
                Contact
            </a>

            <a href="becomeahost.php">
                Become a Host
            </a>

            <a href="hiveclub.php">
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
                    src="images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >

                <img
                    src="images/AppStore.jpg"
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

</script>

<!-- MAIN JAVASCRIPT (handles category dropdown open/close) -->
<script src="javaScript.js"></script>

</body>
</html>