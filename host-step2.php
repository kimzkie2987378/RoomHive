<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 2)
   ADD YOUR SPACE - BASIC INFORMATION
   ========================================================= */

session_start();
require_once 'db_connect.php';

/*
 * =========================================================
 * AUTHENTICATION CHECK
 * =========================================================
 *
 * Only logged-in users can access this page.
 */

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {

    header("Location: loginform.php");
    exit();

}


/*
 * =========================================================
 * STEP 1 CHECK
 * =========================================================
 *
 * The user must have completed step 1 (Tell Us About You)
 * before reaching this page.
 */

if (!isset($_SESSION["host_application"]) || !isset($_SESSION["host_application_id"])) {

    header("Location: becomeahost.php");
    exit();

}


/*
 * Whether the user is logged in (always true past the
 * check above, but kept as a variable for the nav pattern
 * used across pages).
 */

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);


/*
 * Get the logged-in user's name.
 * This can be used anywhere on the page if needed.
 */

$userName = $_SESSION["user_name"] ?? "User";


// Current page (kept as becomeahost.php so the nav /
// footer "BECOME A HOST" link stays highlighted while the
// user moves through the multi-step host registration flow)
$currentPage = "becomeahost.php";

// The step currently active in the host-steps tracker
$currentStep = 2;

// =========================================================
// NAVIGATION
// =========================================================

$navigation = [
    "HOME" => "usershome.php",
    "LISTINGS" => "listing.php",
    "HOW IT WORKS" => "howitworks.php",
    "BECOME A HOST" => "becomeahost.php",
    "HIVE CLUB" => "hiveclub.php",
    "CONTACTS" => "contacts.php"
];


// =========================================================
// HOSTING STEPS
// =========================================================

$hostSteps = [

    [
        "number" => 1,
        "icon" => "images/CreateIcon-BecomeAHost.png",
        "alt" => "Create Account",
        "title" => "Create Account",
        "description" => "Sign up as a host in minutes"
    ],

    [
        "number" => 2,
        "icon" => "images/AddYourSpaceIcon-BecomeAHost.png",
        "alt" => "Add Your Space",
        "title" => "Add Your Space",
        "description" => "Tell us about your property"
    ],

    [
        "number" => 3,
        "icon" => "images/UploadPhotosIcon-BecomeAHost.png",
        "alt" => "Upload Photos",
        "title" => "Upload Photos",
        "description" => "Show your space to attract renters"
    ],

    [
        "number" => 4,
        "icon" => "images/GetBookingsIcon-BecomeAHost.png",
        "alt" => "Get Bookings",
        "title" => "Get Bookings",
        "description" => "Approve bookings and start earning"
    ]

];


// =========================================================
// LISTING CATEGORIES
// =========================================================

$listingCategories = [

    "Shared Bedroom" => "shared-bedroom",

    "Private Room" => "private-room",

    "Entire House" => "entire-house",

    "Boarding House" => "boarding-house",

    "Studio Loft" => "studio-loft"

];


// =========================================================
// PROPERTY TYPES
// =========================================================

$propertyTypes = [

    "Apartment" => "apartment",

    "Condominium" => "condominium",

    "House" => "house",

    "Townhouse" => "townhouse",

    "Dormitory" => "dormitory",

    "Boarding House" => "boarding-house"

];


// =========================================================
// CAPACITY OPTIONS
// =========================================================

$capacityOptions = [
    "1" => "1 Guest",
    "2" => "2 Guests",
    "3" => "3 Guests",
    "4" => "4 Guests",
    "5" => "5 Guests",
    "6" => "6 Guests",
    "8" => "8 Guests",
    "10" => "10 Guests",
    "10+" => "More than 10 Guests"
];


// =========================================================
// PARKING LOT OPTIONS
// (mirrors the "Parking Lot" row on the listing detail page)
// =========================================================

$parkingOptions = [
    "Yes" => "Yes, parking available",
    "No"  => "No parking available"
];


// =========================================================
// AMENITIES
// (same keys/icons used on listing-detail.php, so whatever
// the host selects here shows up there exactly as-is)
// =========================================================

$amenityOptions = [

    "wifi" => [
        "label" => "Wi-fi",
        "icon" => "images/wifiicon.png"
    ],

    "aircon" => [
        "label" => "Aircon",
        "icon" => "images/airconicon.png"
    ],

    "pet-friendly" => [
        "label" => "Pet Friendly",
        "icon" => "images/petsicon.png"
    ],

    "free-water" => [
        "label" => "Free Water",
        "icon" => "images/watericon.png"
    ],

    "free-electricity" => [
        "label" => "Free Electricity",
        "icon" => "images/elcetricityicon.png"
    ],

    "security" => [
        "label" => "24/7 Security",
        "icon" => "images/SecurityIcon.png"
    ]

];


// =========================================================
// QUICK LINKS
// =========================================================

$quickLinks = [

    "About Us" => "index.php",

    "How It Works" => "howitworks.php",

    "Become a Host" => "becomeahost.php",

    "Hive Club" => "hiveclub.php",

    "Contacts" => "contacts.php"

];


// =========================================================
// FORM PROCESSING
// =========================================================

$errors = [];

$success = false;


// Process form when submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get submitted values
    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $propertyType = trim($_POST["property_type"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $exactAddress = trim($_POST["exact_address"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $capacity = trim($_POST["capacity"] ?? "");
    $bedrooms = trim($_POST["bedrooms"] ?? "");
    $bathrooms = trim($_POST["bathrooms"] ?? "");
    $sizeSqm = trim($_POST["size_sqm"] ?? "");
    $floor = trim($_POST["floor"] ?? "");
    $parking = trim($_POST["parking"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $houseRules = trim($_POST["house_rules"] ?? "");

    // Amenities checklist — keep only keys we actually offer
    $selectedAmenities = $_POST["amenities"] ?? [];

    if (!is_array($selectedAmenities)) {
        $selectedAmenities = [$selectedAmenities];
    }

    $selectedAmenities = array_values(
        array_intersect($selectedAmenities, array_keys($amenityOptions))
    );


    // =====================================================
    // VALIDATION
    // =====================================================

    if ($title === "") {
        $errors[] = "Please enter a title for your space.";
    }


    if ($category === "" || !in_array($category, $listingCategories, true)) {
        $errors[] = "Please select a category.";
    }


    if ($propertyType === "" || !in_array($propertyType, $propertyTypes, true)) {
        $errors[] = "Please select a property type.";
    }


    if ($location === "") {
        $errors[] = "Please enter the location of your space.";
    }


    if ($exactAddress === "") {
        $errors[] = "Please enter the exact address.";
    }


    if ($price === "" || !is_numeric($price) || (float) $price <= 0) {
        $errors[] = "Please enter a valid price per month.";
    }


    if ($capacity === "" || !array_key_exists($capacity, $capacityOptions)) {
        $errors[] = "Please select the maximum number of guests.";
    }


    if ($bedrooms === "" || !is_numeric($bedrooms) || (int) $bedrooms <= 0) {
        $errors[] = "Please enter the number of bedrooms.";
    }


    if ($bathrooms === "" || !is_numeric($bathrooms) || (int) $bathrooms <= 0) {
        $errors[] = "Please enter the number of bathrooms.";
    }


    if ($sizeSqm === "" || !is_numeric($sizeSqm) || (float) $sizeSqm <= 0) {
        $errors[] = "Please enter the size of your space in square meters.";
    }


    if ($floor === "") {
        $errors[] = "Please enter which floor your space is on.";
    }


    if ($parking === "" || !array_key_exists($parking, $parkingOptions)) {
        $errors[] = "Please let renters know if parking is available.";
    }


    if ($description === "") {
        $errors[] = "Please describe your space.";
    }


    // =====================================================
    // IF VALID
    // =====================================================

    if (empty($errors)) {

        /*
         * Insert the new space into the real `listings` table,
         * attached to the host_applications row created in
         * Step 1. Starts as 'draft' — becomes 'pending' once
         * host-step4.php finalizes the application.
         */

        $stmt = $pdo->prepare(
            "INSERT INTO listings
                (host_application_id, user_id, title, category, property_type, location,
                 exact_address, price, capacity, bedrooms, bathrooms, size_sqm, floor,
                 parking, amenities, description, house_rules, status)
             VALUES
                (:host_application_id, :user_id, :title, :category, :property_type, :location,
                 :exact_address, :price, :capacity, :bedrooms, :bathrooms, :size_sqm, :floor,
                 :parking, :amenities, :description, :house_rules, 'draft')"
        );

        $stmt->execute([
            'host_application_id' => $_SESSION['host_application_id'],
            'user_id'             => $_SESSION['user_id'],
            'title'               => $title,
            'category'            => $category,
            'property_type'       => $propertyType,
            'location'            => $location,
            'exact_address'       => $exactAddress,
            'price'               => $price,
            'capacity'            => $capacity,
            'bedrooms'            => $bedrooms,
            'bathrooms'           => $bathrooms,
            'size_sqm'            => $sizeSqm,
            'floor'               => $floor,
            'parking'             => $parking,
            'amenities'           => json_encode($selectedAmenities),
            'description'         => $description,
            'house_rules'         => $houseRules,
        ]);

        $_SESSION['host_application']['listing_id'] = $pdo->lastInsertId();

        // Keep a few fields in session too, in case any later
        // step wants to read them without hitting the DB.
        $_SESSION["host_application"]["title"] = $title;
        $_SESSION["host_application"]["category"] = $category;
        $_SESSION["host_application"]["property_type"] = $propertyType;
        $_SESSION["host_application"]["location"] = $location;
        $_SESSION["host_application"]["exact_address"] = $exactAddress;
        $_SESSION["host_application"]["price"] = $price;
        $_SESSION["host_application"]["capacity"] = $capacity;
        $_SESSION["host_application"]["bedrooms"] = $bedrooms;
        $_SESSION["host_application"]["bathrooms"] = $bathrooms;
        $_SESSION["host_application"]["size_sqm"] = $sizeSqm;
        $_SESSION["host_application"]["floor"] = $floor;
        $_SESSION["host_application"]["parking"] = $parking;
        $_SESSION["host_application"]["amenities"] = $selectedAmenities;
        $_SESSION["host_application"]["description"] = $description;
        $_SESSION["host_application"]["house_rules"] = $houseRules;


        // Go to next host registration step
        header("Location: host-step3.php");

        exit;

    }

}


// Convenience helper for re-populating the form after a
// failed submission without losing what the user typed.
$old = fn(string $key): string => htmlspecialchars($_POST[$key] ?? "");

// Convenience helper for re-checking an amenity checkbox
// after a failed submission.
$amenityChecked = fn(string $key): string =>
    in_array($key, $_POST["amenities"] ?? [], true) ? "checked" : "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        RoomHive - Add Your Space
    </title>


    <!-- =====================================================
         POPPINS FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         MAIN CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body>


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<header class="navbar">


    <!-- LOGO -->

    <a
        href="usershome.php"
        class="logo"
    >

        <img
            src="images/RoomHiveLogos.png"
            alt="RoomHive Logo"
        >

    </a>


    <!-- NAVIGATION -->

    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>

            <a
                href="<?php echo htmlspecialchars($link); ?>"
                class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>"
            >

                <?php echo htmlspecialchars($name); ?>

            </a>

        <?php endforeach; ?>


        <?php if ($isLoggedIn): ?>

            <!-- MY ACCOUNT DROPDOWN -->
            <div class="account-dropdown">

                <button
                    type="button"
                    class="my-account"
                    id="accountDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    onclick="toggleAccountMenu()"
                >
                    <span class="account-circle">
                        <img src="images/MyAccountIcon.png" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">

                    <?php if (isset($_SESSION["is_host"]) && $_SESSION["is_host"] === true): ?>
                        <a href="hostprofile.php">
                            Host Profile
                        </a>
                    <?php endif; ?>

                    <a href="userprofile.php">
                        My Profile
                    </a>

                    <a href="logout.php">
                        Logout
                    </a>

                </div>

            </div>

        <?php else: ?>

            <!-- LIST YOUR SPACE -->

            <a
                href="becomeahost.php"
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
        z-index: 100;
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

<script>
    function toggleAccountMenu() {
        const dropdown = document.getElementById('accountDropdownToggle').closest('.account-dropdown');
        const toggle = document.getElementById('accountDropdownToggle');
        const isOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        const dropdown = document.querySelector('.account-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove('open');
            document.getElementById('accountDropdownToggle').setAttribute('aria-expanded', 'false');
        }
    });
</script>
<?php endif; ?>



<!-- =========================================================
     MAIN HOST PAGE
========================================================= -->

<main class="host-page">


    <!-- =====================================================
         HOST HEADER
    ====================================================== -->

    <section class="host-header">


        <span class="host-eyebrow">

            ADD YOUR SPACE

        </span>


        <h1>

            Tell Us About Your Space

        </h1>



        <!-- =================================================
             HOST STEPS
        ================================================== -->

        <div class="host-steps">


            <?php foreach ($hostSteps as $index => $step): ?>

                <?php $isActive = ($step["number"] === $currentStep); ?>
                <?php $isDone = ($step["number"] < $currentStep); ?>

                <div class="host-step<?php echo $isActive ? ' active' : ''; ?><?php echo $isDone ? ' completed' : ''; ?>">


                    <!-- ICON -->

                    <div class="step-circle">

                        <img
                            src="<?php echo htmlspecialchars($step["icon"]); ?>"
                            alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        >

                    </div>


                    <!-- CONNECTING LINE -->

                    <?php if ($index < count($hostSteps) - 1): ?>

                        <div class="step-line"></div>

                    <?php endif; ?>


                    <!-- NUMBER -->

                    <div class="step-number">

                        <?php echo $step["number"]; ?>

                    </div>


                    <!-- TITLE -->

                    <h3>

                        <?php echo htmlspecialchars($step["title"]); ?>

                    </h3>


                    <!-- DESCRIPTION -->

                    <p>

                        <?php echo htmlspecialchars($step["description"]); ?>

                    </p>


                </div>

            <?php endforeach; ?>


        </div>

    </section>



    <!-- =====================================================
         ERROR MESSAGES
    ====================================================== -->

    <?php if (!empty($errors)): ?>

        <div class="form-errors">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         HOST FORM
    ====================================================== -->

    <section class="host-form-card">


        <!-- FORM -->

        <form
            action="host-step2.php"
            method="POST"
        >


            <!-- =================================================
                 BASIC INFORMATION
            ================================================== -->

            <div class="form-heading">

                <h2>

                    Basic Information

                </h2>


                <p>

                    Let's start with the basics about your space.

                </p>

            </div>



            <!-- TITLE OF YOUR SPACE -->

            <div class="input-group full-width">

                <label for="title">

                    Title of your space

                </label>


                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="e.g. Cozy Studio Suite in Dumaguete City"
                    value="<?php echo $old("title"); ?>"
                    required
                >

            </div>



            <!-- CATEGORY / PROPERTY TYPE -->

            <div class="form-grid two-columns">


                <!-- CATEGORY -->

                <div class="input-group">

                    <label for="category">

                        Category

                    </label>


                    <select
                        id="category"
                        name="category"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?php echo empty($_POST["category"]) ? "selected" : ""; ?>
                        >

                            Select a category

                        </option>


                        <?php foreach ($listingCategories as $label => $value): ?>

                            <option
                                value="<?php echo htmlspecialchars($value); ?>"
                                <?php echo (($_POST["category"] ?? "") === $value) ? "selected" : ""; ?>
                            >

                                <?php echo htmlspecialchars($label); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- PROPERTY TYPE -->

                <div class="input-group">

                    <label for="property_type">

                        Property Type

                    </label>


                    <select
                        id="property_type"
                        name="property_type"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?php echo empty($_POST["property_type"]) ? "selected" : ""; ?>
                        >

                            Select property type

                        </option>


                        <?php foreach ($propertyTypes as $label => $value): ?>

                            <option
                                value="<?php echo htmlspecialchars($value); ?>"
                                <?php echo (($_POST["property_type"] ?? "") === $value) ? "selected" : ""; ?>
                            >

                                <?php echo htmlspecialchars($label); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>



            <!-- LOCATION / EXACT ADDRESS -->

            <div class="form-grid two-columns">


                <!-- LOCATION -->

                <div class="input-group">

                    <label for="location">

                        Location

                    </label>


                    <div class="input-with-icon">

                        <input
                            type="text"
                            id="location"
                            name="location"
                            placeholder="City, Province"
                            value="<?php echo $old("location"); ?>"
                            required
                        >


                        <span>

                            ◉

                        </span>

                    </div>

                </div>



                <!-- EXACT ADDRESS -->

                <div class="input-group">

                    <label for="exact_address">

                        Exact Address

                    </label>


                    <input
                        type="text"
                        id="exact_address"
                        name="exact_address"
                        placeholder="House/Building No., Street, Barangay"
                        value="<?php echo $old("exact_address"); ?>"
                        required
                    >

                </div>

            </div>



            <!-- PRICE PER MONTH / CAPACITY -->

            <div class="form-grid two-columns">


                <!-- PRICE PER MONTH -->

                <div class="input-group">

                    <label for="price">

                        Price per month

                    </label>


                    <div class="price-input-group">

                        <span class="price-icon">

                            &#8369;

                        </span>


                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            step="1"
                            placeholder="e.g. 5000"
                            value="<?php echo $old("price"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- CAPACITY -->

                <div class="input-group">

                    <label for="capacity">

                        Capacity

                    </label>


                    <select
                        id="capacity"
                        name="capacity"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?php echo empty($_POST["capacity"]) ? "selected" : ""; ?>
                        >

                            Maximum number of guests

                        </option>


                        <?php foreach ($capacityOptions as $value => $label): ?>

                            <option
                                value="<?php echo htmlspecialchars($value); ?>"
                                <?php echo (($_POST["capacity"] ?? "") === $value) ? "selected" : ""; ?>
                            >

                                <?php echo htmlspecialchars($label); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>



            <!-- =================================================
                 SPACE DETAILS
                 (feeds the "Property Details" card shown on the
                 listing detail page — same icons: bedicon,
                 showericon, sizeicon, flooricon, caricon)
            ================================================== -->

            <div class="form-section-divider"></div>


            <div class="form-heading">

                <h2>

                    Space Details

                </h2>


                <p>

                    These show up on your listing's property details card.

                </p>

            </div>



            <!-- BEDROOMS / BATHROOMS / SIZE -->

            <div class="form-grid three-columns">


                <!-- BEDROOMS -->

                <div class="input-group">

                    <label for="bedrooms">

                        Bedrooms

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">
                            <img src="images/bedicon.png" alt="">
                        </span>

                        <input
                            type="number"
                            id="bedrooms"
                            name="bedrooms"
                            min="0"
                            step="1"
                            placeholder="e.g. 2"
                            value="<?php echo $old("bedrooms"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- BATHROOMS -->

                <div class="input-group">

                    <label for="bathrooms">

                        Bathrooms

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">
                            <img src="images/showericon.png" alt="">
                        </span>

                        <input
                            type="number"
                            id="bathrooms"
                            name="bathrooms"
                            min="0"
                            step="1"
                            placeholder="e.g. 1"
                            value="<?php echo $old("bathrooms"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- SIZE -->

                <div class="input-group">

                    <label for="size_sqm">

                        Size

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">
                            <img src="images/sizeicon.png" alt="">
                        </span>

                        <input
                            type="number"
                            id="size_sqm"
                            name="size_sqm"
                            min="0"
                            step="1"
                            placeholder="e.g. 28"
                            value="<?php echo $old("size_sqm"); ?>"
                            required
                        >

                        <span class="icon-input-suffix">
                            m&sup2;
                        </span>

                    </div>

                </div>

            </div>



            <!-- FLOOR / PARKING LOT -->

            <div class="form-grid two-columns">


                <!-- FLOOR -->

                <div class="input-group">

                    <label for="floor">

                        Floor

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">
                            <img src="images/flooricon.png" alt="">
                        </span>

                        <input
                            type="text"
                            id="floor"
                            name="floor"
                            inputmode="numeric"
                            placeholder="e.g. 6"
                            value="<?php echo $old("floor"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- PARKING LOT -->

                <div class="input-group">

                    <label for="parking">

                        Parking Lot

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">
                            <img src="images/caricon.png" alt="">
                        </span>

                        <select
                            id="parking"
                            name="parking"
                            required
                        >

                            <option
                                value=""
                                disabled
                                <?php echo empty($_POST["parking"]) ? "selected" : ""; ?>
                            >

                                Is parking available?

                            </option>


                            <?php foreach ($parkingOptions as $value => $label): ?>

                                <option
                                    value="<?php echo htmlspecialchars($value); ?>"
                                    <?php echo (($_POST["parking"] ?? "") === $value) ? "selected" : ""; ?>
                                >

                                    <?php echo htmlspecialchars($label); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

            </div>



            <!-- =================================================
                 AMENITIES
                 (feeds the "Amenities" row on the listing detail
                 page — same icons/keys, so nothing gets
                 re-mapped later)
            ================================================== -->

            <div class="input-group full-width">

                <label>

                    Amenities

                </label>


                <div class="amenities-grid">

                    <?php foreach ($amenityOptions as $key => $data): ?>

                        <label class="amenity-checkbox">

                            <input
                                type="checkbox"
                                name="amenities[]"
                                value="<?php echo htmlspecialchars($key); ?>"
                                <?php echo $amenityChecked($key); ?>
                            >

                            <span class="amenity-checkbox-icon">
                                <img src="<?php echo htmlspecialchars($data["icon"]); ?>" alt="">
                            </span>

                            <span class="amenity-checkbox-label">
                                <?php echo htmlspecialchars($data["label"]); ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>



            <!-- =================================================
                 ABOUT YOUR SPACE
            ================================================== -->

            <div class="form-section-divider"></div>


            <div class="form-heading">

                <h2>

                    About Your Space

                </h2>


                <p>

                    Provide more details to help renters understand your space better.

                </p>

            </div>



            <!-- DESCRIPTION -->

            <div class="input-group full-width">

                <label for="description">

                    Description

                </label>


                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe your space, what makes it special, and important details..."
                    required
                ><?php echo $old("description"); ?></textarea>

            </div>



            <!-- HOUSE RULES -->

            <div class="input-group full-width">

                <label for="house_rules">

                    House Rules (Optional)

                </label>


                <textarea
                    id="house_rules"
                    name="house_rules"
                    placeholder="e.g. No smoking, No pets, Quiet hours, etc."
                ><?php echo $old("house_rules"); ?></textarea>

            </div>



            <!-- =================================================
                 FORM BUTTON
            ================================================== -->

            <div class="form-actions">


                <a
                    href="becomeahost.php"
                    class="back-button"
                >

                    BACK

                </a>


                <button
                    type="submit"
                    class="next-button"
                >

                    NEXT STEP

                </button>


            </div>


        </form>

    </section>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">


    <div class="footer-top">


        <!-- =================================================
             BRAND
        ================================================== -->

        <div class="footer-brand">


            <a href="index.php">

                <img
                    src="images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                    class="footer-logo"
                >

            </a>


            <p class="footer-tagline">

                Your trusted platform for finding and listing
                quality living spaces — made simple, safe,
                and stress-free.

            </p>

        </div>



        <!-- =================================================
             LISTINGS
        ================================================== -->

        <div class="footer-links">


            <span class="footer-heading">

                LISTINGS

            </span>


            <?php foreach ($listingCategories as $category => $type): ?>

                <a
                    href="listing.php?type=<?php echo urlencode($type); ?>"
                >

                    <?php echo htmlspecialchars($category); ?>

                </a>

            <?php endforeach; ?>


        </div>



        <!-- =================================================
             QUICK LINKS
        ================================================== -->

        <div class="footer-links">


            <span class="footer-heading">

                QUICK LINKS

            </span>


            <?php foreach ($quickLinks as $name => $link): ?>

                <a
                    href="<?php echo htmlspecialchars($link); ?>"
                    class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>"
                >

                    <?php echo htmlspecialchars($name); ?>

                </a>

            <?php endforeach; ?>


        </div>



        <!-- =================================================
             GET THE APP
        ================================================== -->

        <div class="footer-contact">


            <span class="footer-heading">

                GET THE APP

            </span>



            <!-- APP STORE -->

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



            <!-- PHONE -->

            <div class="footer-contact-line">


                <img
                    src="images/PhoneIcon.jpg"
                    alt="Phone"
                >


                <span>

                    +63 927 569 3574

                </span>


            </div>



            <!-- EMAIL -->

            <div class="footer-contact-line">


                <img
                    src="images/EmailIcon.jpg"
                    alt="Email"
                >


                <span>

                    hello@roomhive.ph

                </span>


            </div>



            <!-- LOCATION -->

            <div class="footer-contact-line">


                <img
                    src="images/GPSIcon.png"
                    alt="Location"
                >


                <span>

                    Dumaguete City, Negros Oriental

                </span>


            </div>


        </div>

    </div>



    <!-- =====================================================
         FOOTER BOTTOM
    ====================================================== -->

    <div class="footer-bottom">


        <p>

            &copy;

            <?php echo date("Y"); ?>

            RoomHive.

            All rights reserved.

        </p>


    </div>

</footer>


<!-- =========================================================
     FLOOR AUTO-FORMAT
     Lets the host type a plain number (e.g. 6) and turns it
     into an ordinal label (e.g. "6th Floor") once they leave
     the field. Reverts to the raw number on focus so it's
     easy to edit again.
========================================================= -->

<script>
(function () {
    const floorInput = document.getElementById('floor');
    if (!floorInput) return;

    function toOrdinal(num) {
        const n = parseInt(num, 10);
        if (isNaN(n)) return '';

        if (n === 0) return 'Ground Floor';

        const rem100 = n % 100;
        const rem10 = n % 10;

        let suffix = 'th';
        if (rem100 < 11 || rem100 > 13) {
            if (rem10 === 1) suffix = 'st';
            else if (rem10 === 2) suffix = 'nd';
            else if (rem10 === 3) suffix = 'rd';
        }

        return n + suffix + ' Floor';
    }

    // When the user focuses the field, show just the raw number
    // so it's easy to edit.
    floorInput.addEventListener('focus', function () {
        const digits = floorInput.value.match(/\d+/);
        floorInput.value = digits ? digits[0] : '';
    });

    // Only allow digits while typing.
    floorInput.addEventListener('input', function () {
        floorInput.value = floorInput.value.replace(/[^\d]/g, '');
    });

    // When the user leaves the field, format it as "6th Floor".
    floorInput.addEventListener('blur', function () {
        if (floorInput.value.trim() === '') return;
        floorInput.value = toOrdinal(floorInput.value);
    });
})();
</script>

</body>

</html>