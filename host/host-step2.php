<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 2)
   ADD YOUR SPACE - BASIC INFORMATION
   ========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/*
 * =========================================================
 * AUTHENTICATION CHECK
 * =========================================================
 */

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {

    header("Location: /webprogg/auth/loginform.php");
    exit();

}

/*
 * =========================================================
 * STEP 1 CHECK
 * =========================================================
 */

if (!isset($_SESSION["host_application"]) || !isset($_SESSION["host_application_id"])) {

    $isHost = isset($_SESSION["is_host"]) && $_SESSION["is_host"] === true;

    $application = null;

    if ($isHost) {

        $appStmt = $pdo->prepare(
            "SELECT * FROM host_applications
             WHERE user_id = :user_id AND status = 'approved'
             ORDER BY id DESC
             LIMIT 1"
        );
        $appStmt->execute(['user_id' => $_SESSION['user_id']]);
        $application = $appStmt->fetch();

    }

    if ($application) {

        $_SESSION['host_application_id'] = $application['id'];

        $_SESSION['host_application'] = [
            "full_name" => $application['full_name'],
            "email"     => $application['email'],
            "phone"     => $application['phone'],
            "age"       => $application['age'],
            "location"  => $application['location'],
            "id_type"   => $application['id_type'],
            "id_number" => $application['id_number'],
            "id_file"   => $application['id_file'],
        ];

    } else {

        header("Location: /webprogg/host/becomeahost.php");
        exit();

    }

}

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

 $userName = $_SESSION["user_name"] ?? "User";

/* NAVBAR AVATAR */
 $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
 $avatarStmt->execute(['id' => $_SESSION['user_id']]);
 $avatarRow = $avatarStmt->fetch();
 $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $notification_count = 0;

 $currentPage = "/webprogg/host/becomeahost.php";
 $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

 $currentStep = 2;

// =========================================================
// NAVIGATION
// =========================================================

 $navigation = [
    "HOME" => "/webprogg/user/usershome.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

// =========================================================
// HOSTING STEPS
// =========================================================

 $hostSteps = [

    [
        "number" => 1,
        "icon" => "/webprogg/images/CreateIcon-BecomeAHost.png",
        "alt" => "Create Account",
        "title" => "Create Account",
        "description" => "Sign up as a host in minutes"
    ],

    [
        "number" => 2,
        "icon" => "/webprogg/images/AddYourSpaceIcon-BecomeAHost.png",
        "alt" => "Add Your Space",
        "title" => "Add Your Space",
        "description" => "Tell us about your property"
    ],

    [
        "number" => 3,
        "icon" => "/webprogg/images/UploadPhotosIcon-BecomeAHost.png",
        "alt" => "Upload Photos",
        "title" => "Upload Photos",
        "description" => "Show your space to attract renters"
    ],

    [
        "number" => 4,
        "icon" => "/webprogg/images/GetBookingsIcon-BecomeAHost.png",
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
// =========================================================

 $parkingOptions = [
    "Yes" => "Yes, parking available",
    "No"  => "No parking available"
];

// =========================================================
// AMENITIES
// =========================================================

 $amenityOptions = [

    "wifi" => [
        "label" => "Wi-fi",
        "icon" => "/webprogg/images/wifiicon.png"
    ],

    "aircon" => [
        "label" => "Aircon",
        "icon" => "/webprogg/images/airconicon.png"
    ],

    "pet-friendly" => [
        "label" => "Pet Friendly",
        "icon" => "/webprogg/images/petsicon.png"
    ],

    "free-water" => [
        "label" => "Free Water",
        "icon" => "/webprogg/images/watericon.png"
    ],

    "free-electricity" => [
        "label" => "Free Electricity",
        "icon" => "/webprogg/images/elcetricityicon.png"
    ],

    "security" => [
        "label" => "24/7 Security",
        "icon" => "/webprogg/images/SecurityIcon.png"
    ]

];

// =========================================================
// QUICK LINKS
// =========================================================

 $quickLinks = [

    "About Us" => "/webprogg/index.php",
    "How It Works" => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Hive Club" => "/webprogg/hiveclub.php",
    "Contacts" => "/webprogg/misc/contacts.php"

];

// =========================================================
// FORM PROCESSING
// =========================================================

 $errors = [];

 $success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $propertyType = trim($_POST["property_type"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $exactAddress = trim($_POST["exact_address"] ?? "");

    /* ===== NEW: MAP PIN — capture coordinates ===== */
    $latitude  = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");

    $price = trim($_POST["price"] ?? "");
    $capacity = trim($_POST["capacity"] ?? "");
    $bedrooms = trim($_POST["bedrooms"] ?? "");
    $bathrooms = trim($_POST["bathrooms"] ?? "");
    $sizeSqm = trim($_POST["size_sqm"] ?? "");
    $floor = trim($_POST["floor"] ?? "");
    $parking = trim($_POST["parking"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $houseRules = trim($_POST["house_rules"] ?? "");

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

    /* ===== NEW: MAP PIN — validation ===== */
    if ($latitude === "" || $longitude === "") {
        $errors[] = "Please pinpoint your space on the map.";
    }

    if ($latitude !== "" && (!is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90)) {
        $errors[] = "Invalid map pin latitude.";
    }

    if ($longitude !== "" && (!is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180)) {
        $errors[] = "Invalid map pin longitude.";
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

        /* ===== NEW: MAP PIN — latitude/longitude added to INSERT ===== */

        $stmt = $pdo->prepare(
            "INSERT INTO listings
                (host_application_id, user_id, title, category, property_type, location,
                 exact_address, latitude, longitude, price, capacity, bedrooms, bathrooms,
                 size_sqm, floor, parking, amenities, description, house_rules, status)
             VALUES
                (:host_application_id, :user_id, :title, :category, :property_type, :location,
                 :exact_address, :latitude, :longitude, :price, :capacity, :bedrooms, :bathrooms,
                 :size_sqm, :floor, :parking, :amenities, :description, :house_rules, 'draft')"
        );

        $stmt->execute([
            'host_application_id' => $_SESSION['host_application_id'],
            'user_id'             => $_SESSION['user_id'],
            'title'               => $title,
            'category'            => $category,
            'property_type'       => $propertyType,
            'location'            => $location,
            'exact_address'       => $exactAddress,
            'latitude'            => (float) $latitude,
            'longitude'           => (float) $longitude,
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

        unset($_SESSION['host_application']['finalized']);
        unset($_SESSION['host_application']['cover_photo']);

        $_SESSION["host_application"]["title"] = $title;
        $_SESSION["host_application"]["category"] = $category;
        $_SESSION["host_application"]["property_type"] = $propertyType;
        $_SESSION["host_application"]["location"] = $location;
        $_SESSION["host_application"]["exact_address"] = $exactAddress;

        /* ===== NEW: MAP PIN — keep coords in session ===== */
        $_SESSION["host_application"]["latitude"] = $latitude;
        $_SESSION["host_application"]["longitude"] = $longitude;

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

        header("Location: /webprogg/host/host-step3.php");
        exit;

    }

}

// Helper for re-populating the form after a failed submission
 $old = fn(string $key): string => htmlspecialchars($_POST[$key] ?? "");

// Helper for re-checking an amenity checkbox
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

    <!-- POPPINS FONT -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- MAIN CSS -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >

    <!-- ===== NEW: FREE MAP — Leaflet + OpenStreetMap (no API key) ===== -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Enables JS-gated entrance animations -->
    <script>document.documentElement.classList.add("js");</script>

    <style>

        :root {
            --hive-ink: #1c2a38;
            --hive-ink-soft: #5d6875;
            --hive-paper: #faf6ee;
            --hive-surface: #FFFFFF;
            --hive-line: #e8e1cf;
            --hive-honey: #eda423;
            --hive-honey-dark: #d99218;
            --hive-honey-light: #f6c04e;
            --hive-moss: #2f9e5b;
            --hive-danger: #a1332e;
            --hive-radius: 16px;
            --hive-shadow: 0 18px 40px -22px rgba(28, 42, 56, 0.35);
        }

        @media (prefers-reduced-motion: reduce) {
            .hive-blob,
            .hive-pulse-dot::after,
            .hive-shimmer,
            .rh-step2 .next-button::after {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .js .rh-step2 .host-header,
            .js .rh-step2 .host-form-card,
            .js .rh-step2 .form-errors {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }

        body.rh-step2 {
            background: var(--hive-paper);
            font-family: "Poppins", sans-serif;
            color: var(--hive-ink);
            overflow-y: auto;
            height: auto;
            min-height: 100vh;
        }

        .rh-step2 main.host-page,
        main.host-page {
            position: relative;

            max-width: 1160px;
            margin: 0 auto;
            padding: 56px 24px 96px;
        }

        /* ---------- honeycomb texture + glow blobs ---------- */

        .rh-step2 main.host-page::before {
            content: "";

            position: absolute;
            inset: 0;

            background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.07' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 49px;

            -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 50%);
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 50%);

            pointer-events: none;

            z-index: 0;
        }

        .rh-step2 main.host-page > * {
            position: relative;

            z-index: 1;
        }

        .hive-blob {
            position: absolute;

            border-radius: 50%;
            filter: blur(70px);

            pointer-events: none;

            z-index: 0;
        }

        .hive-blob-1 {
            width: 360px;
            height: 360px;

            top: -150px;
            right: -130px;

            background: radial-gradient(circle at 30% 30%, rgba(246, 196, 78, 0.8), rgba(237, 164, 35, 0.22) 60%, transparent 75%);

            animation: hiveDrift 14s ease-in-out infinite alternate;
        }

        .hive-blob-2 {
            width: 260px;
            height: 260px;

            top: 420px;
            left: -150px;

            background: radial-gradient(circle at 60% 40%, rgba(246, 196, 78, 0.6), rgba(237, 164, 35, 0.18) 60%, transparent 75%);

            animation: hiveDrift 18s ease-in-out infinite alternate-reverse;
        }

        @keyframes hiveDrift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, -24px) scale(1.08); }
        }

        /* ---------- entrance reveal ---------- */

        @keyframes hiveRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .rh-step2 .host-header,
        .js .rh-step2 .host-form-card {
            animation: hiveRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .js .rh-step2 .host-form-card { animation-delay: 0.1s; }

        /* ---------- hero badge + shimmer ---------- */

        .rh-step2 .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 8px 16px;

            background: #ffffff;

            border: 1px solid rgba(237, 164, 35, 0.35);
            border-radius: 999px;

            box-shadow: 0 4px 14px rgba(237, 164, 35, 0.12);

            color: #b07708;

            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .hive-pulse-dot {
            position: relative;

            width: 8px;
            height: 8px;

            background: var(--hive-honey);
            border-radius: 50%;
        }

        .hive-pulse-dot::after {
            content: "";

            position: absolute;
            inset: 0;

            background: var(--hive-honey);
            border-radius: 50%;

            animation: hivePing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        @keyframes hivePing {
            0%   { transform: scale(1); opacity: 0.7; }
            80%, 100% { transform: scale(2.6); opacity: 0; }
        }

        .hive-shimmer {
            background: linear-gradient(92deg, #eda423 0%, #f6c04e 45%, #eda423 90%);
            background-size: 200% auto;

            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;

            animation: hiveShimmer 3.5s linear infinite;
        }

        @keyframes hiveShimmer {
            to { background-position: 200% center; }
        }

        /* ---------- HEADER ---------- */

        .rh-step2 .host-header {
            max-width: 860px;
            margin: 0 auto 48px;
            text-align: center;
        }

        .rh-step2 .host-eyebrow {
            display: block;

            margin-top: 18px;

            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--hive-honey-dark);
            text-transform: uppercase;
        }

        .rh-step2 .host-header h1 {
            font-size: clamp(28px, 4vw, 36px);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.6px;
            margin: 10px 0 0;
            color: var(--hive-ink);
        }

        /* ---------- STEPPER ---------- */

        .rh-step2 .host-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            margin-top: 40px;
            position: relative;
        }

        .rh-step2 .host-steps-track {
            position: absolute;
            top: 26px;
            left: 12.5%;
            right: 12.5%;
            height: 2px;
            background: var(--hive-line);
            z-index: 1;
        }

        .rh-step2 .host-steps-progress {
            position: absolute;
            top: 26px;
            left: 12.5%;
            height: 2px;
            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));
            z-index: 1;
            transition: width 0.3s ease;
        }

        .rh-step2 .host-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0 10px;
        }

        .rh-step2 .step-circle {
            position: relative;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--hive-surface);
            border: 2px solid var(--hive-line);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .rh-step2 .step-circle img {
            width: 22px;
            height: 22px;
            object-fit: contain;
            object-position: center;
            display: block;
            opacity: 0.55;
            filter: grayscale(1);
            transition: opacity 0.2s ease, filter 0.2s ease;
        }

        .rh-step2 .host-step.active .step-circle {
            border-color: var(--hive-honey);
            background: #FDF4E3;
            box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.18);
        }

        .rh-step2 .host-step.active .step-circle img {
            opacity: 1;
            filter: none;
        }

        .rh-step2 .host-step.completed .step-circle {
            border-color: var(--hive-moss);
            background: var(--hive-moss);
        }

        .rh-step2 .host-step.completed .step-circle img {
            opacity: 1;
            filter: brightness(0) invert(1);
        }

        .rh-step2 .step-number {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--hive-ink);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            border: 2px solid var(--hive-paper);
            box-sizing: border-box;
        }

        .rh-step2 .host-step.active .step-number {
            background: var(--hive-honey-dark);
        }

        .rh-step2 .host-step.completed .step-number {
            background: var(--hive-moss);
        }

        .rh-step2 .host-step h3 {
            margin: 14px 0 2px;
            font-size: 14px;
            font-weight: 600;
            color: var(--hive-ink);
        }

        .rh-step2 .host-step p {
            margin: 0 auto;
            max-width: 150px;
            font-size: 12.5px;
            color: var(--hive-ink-soft);
            line-height: 1.4;
        }

        .rh-step2 .host-step:not(.active) h3 {
            color: var(--hive-ink-soft);
        }

        /* ---------- ERRORS ---------- */

        .rh-step2 .form-errors {
            max-width: 860px;
            margin: 0 auto 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: #fdf1f0;
            border: 1px solid #f3c9c7;
            border-radius: 14px;
            padding: 16px 18px;
            animation: hiveRise 0.35s ease both;
        }

        .rh-step2 .form-errors::before {
            content: "We need a couple of fixes before continuing";
            font-weight: 600;
            color: #e0524d;
            margin-bottom: 2px;
        }

        .rh-step2 .form-errors p {
            margin: 0;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
            color: #7a2f2c;
        }

        .rh-step2 .form-errors p::before {
            content: "!";
            position: absolute;
            left: 0;
            top: 1px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #e0524d;
            color: #fff;
            font-size: 10px;
            line-height: 14px;
            text-align: center;
            font-weight: 700;
        }

        /* ---------- TWO-COLUMN LAYOUT ---------- */

        .rh-step2 .host-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 32px;
            align-items: flex-start;
        }

        .rh-step2 .listing-preview-rail {
            position: relative;
        }

        @media (max-width: 900px) {
            .rh-step2 .host-layout {
                grid-template-columns: 1fr;
            }
            .rh-step2 .listing-preview-rail {
                order: -1;
            }
        }

        /* ---------- FORM CARD ---------- */

        .rh-step2 .host-form-card {
            background: var(--hive-surface);
            border-radius: var(--hive-radius);
            border: 1px solid var(--hive-line);
            box-shadow: var(--hive-shadow);
            padding: 40px;
        }

        .rh-step2 .form-heading h2 {
            position: relative;

            display: inline-block;

            font-size: 19px;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--hive-ink);
        }

        .rh-step2 .form-heading h2::after {
            content: "";

            position: absolute;

            width: 38px;
            height: 3px;

            left: 0;
            bottom: -8px;

            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));

            border-radius: 2px;
        }

        .rh-step2 .form-heading p {
            margin: 18px 0 24px;
            font-size: 14px;
            color: var(--hive-ink-soft);
        }

        .rh-step2 .form-section-divider {
            height: 1px;
            background: var(--hive-line);
            margin: 36px 0 28px;
        }

        .rh-step2 .input-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 22px;
        }

        .rh-step2 .form-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 22px;
        }

        .rh-step2 .form-grid .input-group {
            margin-bottom: 0;
        }

        .rh-step2 .form-grid.two-columns {
            grid-template-columns: 1fr 1fr;
        }

        .rh-step2 .form-grid.three-columns {
            grid-template-columns: 1fr 1fr 1fr;
        }

        @media (max-width: 640px) {
            .rh-step2 .form-grid.two-columns,
            .rh-step2 .form-grid.three-columns {
                grid-template-columns: 1fr;
            }
        }

        .rh-step2 label {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--hive-ink);
            margin-bottom: 8px;
        }

        .rh-step2 input[type="text"],
        .rh-step2 input[type="number"],
        .rh-step2 select,
        .rh-step2 textarea {
            font-family: "Poppins", sans-serif;
            font-size: 14.5px;
            color: var(--hive-ink);
            background: #fbfcfd;
            border: 1.5px solid #e3e7ec;
            border-radius: 10px;
            padding: 12px 14px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .rh-step2 textarea {
            min-height: 110px;
            resize: vertical;
            line-height: 1.55;
        }

        .rh-step2 input:focus,
        .rh-step2 select:focus,
        .rh-step2 textarea:focus {
            outline: none;
            border-color: var(--hive-honey);
            background: var(--hive-surface);
            box-shadow: 0 0 0 3px rgba(237, 164, 35, 0.22);
        }

        .rh-step2 input::placeholder,
        .rh-step2 textarea::placeholder {
            color: #a5adb8;
        }

        .rh-step2 select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' stroke='%231c2a38' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .rh-step2 .input-with-icon {
            position: relative;
        }

        .rh-step2 .input-with-icon input {
            padding-right: 38px;
        }

        .rh-step2 .input-with-icon span {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--hive-honey-dark);
            font-size: 14px;
        }

        .rh-step2 .price-input-group,
        .rh-step2 .icon-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .rh-step2 .price-icon {
            position: absolute;
            left: 14px;
            font-size: 14.5px;
            color: var(--hive-honey-dark);
            font-weight: 700;
            pointer-events: none;
        }

        .rh-step2 .price-input-group input {
            padding-left: 32px;
        }

        .rh-step2 .icon-input-icon {
            position: absolute;
            left: 12px;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            opacity: 0.55;
        }

        .rh-step2 .icon-input-icon img {
            width: 16px;
            height: 16px;
            object-fit: contain;
        }

        .rh-step2 .icon-input-group input,
        .rh-step2 .icon-input-group select {
            padding-left: 38px;
        }

        .rh-step2 .icon-input-suffix {
            position: absolute;
            right: 14px;
            font-size: 13px;
            color: var(--hive-ink-soft);
            pointer-events: none;
        }

        /* =====================================================
           NEW: MAP PIN — STYLES
        ====================================================== */

        .rh-step2 .map-hint {
            margin: -2px 0 12px;
            font-size: 12.5px;
            color: var(--hive-ink-soft);
            line-height: 1.5;
        }

        .rh-step2 .map-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .rh-step2 .map-locate-btn {
            font-family: "Poppins", sans-serif;
            font-size: 12.5px;
            font-weight: 700;
            color: #b07708;
            background: #FDF4E3;
            border: 1.5px solid rgba(237, 164, 35, 0.5);
            border-radius: 8px;
            padding: 8px 14px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .rh-step2 .map-locate-btn:hover {
            background: #f9e8c8;
            transform: translateY(-1px);
        }

        .rh-step2 .map-locate-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .rh-step2 .pin-status {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--hive-ink-soft);
        }

        .rh-step2 .pin-status.has-pin {
            color: var(--hive-moss);
        }

        .rh-step2 .location-map {
            width: 100%;
            height: 340px;
            border-radius: 12px;
            border: 1.5px solid #e3e7ec;
            background: #eef1f4;
            z-index: 1;
        }

        .rh-step2 .pin-address-preview {
            margin-top: 10px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--hive-moss);
        }

        /* Leaflet emoji pin */
        .rh-pin-icon {
            background: transparent;
            border: none;
        }

        .rh-pin {
            font-size: 30px;
            line-height: 1;
            filter: drop-shadow(0 3px 3px rgba(0, 0, 0, 0.35));
        }

        /* ---------- AMENITY CHIPS ---------- */

        .rh-step2 .amenities-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .rh-step2 .amenity-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1.5px solid var(--hive-line);
            background: #fbfcfd;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--hive-ink-soft);
            transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, transform 0.15s ease;
            margin: 0;
        }

        .rh-step2 .amenity-checkbox:hover {
            transform: translateY(-2px);

            border-color: var(--hive-honey);
        }

        .rh-step2 .amenity-checkbox input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .rh-step2 .amenity-checkbox-icon img {
            width: 16px;
            height: 16px;
            object-fit: contain;
            opacity: 0.7;
        }

        .rh-step2 .amenity-checkbox:has(input:checked) {
            background: #FDF4E3;
            border-color: var(--hive-honey);
            color: var(--hive-ink);
        }

        .rh-step2 .amenity-checkbox:has(input:checked) .amenity-checkbox-icon img {
            opacity: 1;
        }

        .rh-step2 .amenity-checkbox:has(input:focus-visible) {
            box-shadow: 0 0 0 3px rgba(237, 164, 35, 0.3);
        }

        /* ---------- ACTIONS ---------- */

        .rh-step2 .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid var(--hive-line);
        }

        .rh-step2 .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            min-width: 120px;
            height: 46px;

            padding: 0 24px;

            border: 1.5px solid #dfe4ea;
            border-radius: 10px;

            background: #ffffff;

            color: var(--hive-ink);

            font-family: "Poppins", sans-serif;
            font-size: 13px;
            font-weight: 700;

            text-decoration: none;

            transition:
                border-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .rh-step2 .back-button:hover {
            border-color: var(--hive-honey);

            color: #b07708;

            transform: translateX(-2px);

            box-shadow: 0 8px 18px rgba(28, 42, 56, 0.08);
        }

        .rh-step2 .next-button {
            position: relative;
            overflow: hidden;

            font-family: "Poppins", sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--hive-ink);
            background: linear-gradient(135deg, var(--hive-honey-light), var(--hive-honey));
            border: none;
            border-radius: 10px;
            padding: 13px 32px;
            cursor: pointer;

            box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35);

            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .rh-step2 .next-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45);
        }

        .rh-step2 .next-button:active {
            transform: translateY(0) scale(0.98);
        }

        .rh-step2 .next-button::after {
            content: "";

            position: absolute;
            top: 0;
            left: -80%;

            width: 50%;
            height: 100%;

            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.55), transparent);

            transform: skewX(-20deg);

            animation: hiveSweep 3.6s ease-in-out infinite;

            pointer-events: none;
        }

        @keyframes hiveSweep {
            0%        { left: -80%; }
            45%, 100% { left: 130%; }
        }

        .rh-step2 .next-button:focus-visible,
        .rh-step2 .back-button:focus-visible {
            outline: 3px solid rgba(237, 164, 35, 0.5);
            outline-offset: 2px;
        }

        /* ---------- LIVE LISTING PREVIEW ---------- */

        .rh-step2 .listing-preview {
            width: 100%;
            box-sizing: border-box;
            background: var(--hive-surface);
            border: 1px solid var(--hive-line);
            border-radius: var(--hive-radius);
            overflow: hidden;
            box-shadow: var(--hive-shadow);

            transition:
                box-shadow 0.25s ease,
                border-color 0.25s ease;
        }

        .rh-step2 .listing-preview:hover {
            border-color: rgba(237, 164, 35, 0.4);

            box-shadow: 0 14px 30px rgba(237, 164, 35, 0.18);
        }

        .rh-step2 .listing-preview.is-pinned {
            position: fixed;
            top: 90px;
            z-index: 50;
        }

        .rh-step2 .listing-preview-label {
            display: flex;
            align-items: center;
            gap: 6px;

            font-size: 12px;
            font-weight: 600;
            color: var(--hive-honey-dark);
            padding: 16px 18px 0;
        }

        .rh-step2 .listing-preview-label::before {
            content: "";

            width: 6px;
            height: 6px;

            background: var(--hive-honey);
            border-radius: 50%;
        }

        .rh-step2 .listing-preview-photo {
            margin: 12px 18px 0;
            height: 110px;
            border-radius: 10px;
            background:
                linear-gradient(rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0)),
                linear-gradient(135deg, #FDF4E3, #F1E6C8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--hive-honey-dark);
            font-size: 12.5px;
            font-weight: 500;
        }

        .rh-step2 .listing-preview-body {
            padding: 16px 18px 20px;
        }

        .rh-step2 .listing-preview-title {
            font-size: 15.5px;
            font-weight: 700;
            color: var(--hive-ink);
            margin: 0 0 4px;
            line-height: 1.35;
        }

        .rh-step2 .listing-preview-meta {
            font-size: 12.5px;
            color: var(--hive-ink-soft);
            margin: 0 0 12px;
        }

        .rh-step2 .listing-preview-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }

        .rh-step2 .listing-preview-tag {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 9px;
            border-radius: 999px;
            background: var(--hive-paper);
            color: var(--hive-ink-soft);
        }

        .rh-step2 .listing-preview-price {
            font-size: 17px;
            font-weight: 700;
            color: var(--hive-moss);
        }

        .rh-step2 .listing-preview-price span {
            font-size: 12px;
            font-weight: 500;
            color: var(--hive-ink-soft);
        }

    </style>

</head>


<body class="rh-step2">


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<?php
 $guestCtaHref = '/webprogg/host/becomeahost.php';
include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php';
?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- =========================================================
     MAIN HOST PAGE
========================================================= -->

<main class="host-page">

    <span class="hive-blob hive-blob-1" aria-hidden="true"></span>
    <span class="hive-blob hive-blob-2" aria-hidden="true"></span>


    <!-- =====================================================
         HOST HEADER
    ====================================================== -->

    <section class="host-header">

        <span class="hero-badge">

            <span class="hive-pulse-dot"></span>

            Add Your Space &middot; Step 2 of 4

        </span>


        <h1 style="margin-top:20px;">

            Tell us about

            <span class="hive-shimmer">your space</span>

        </h1>


        <?php

            $totalSteps = count($hostSteps);

            $stepProgressPercent = $totalSteps > 1
                ? (($currentStep - 1) / ($totalSteps - 1)) * 100
                : 0;

            $stepProgressPercent = max(0, min(100, $stepProgressPercent));

        ?>

        <div class="host-steps">


            <div class="host-steps-track"></div>

            <div
                class="host-steps-progress"
                style="width: <?php echo $stepProgressPercent; ?>%;"
            ></div>


            <?php foreach ($hostSteps as $index => $step): ?>

                <?php $isActive = ($step["number"] === $currentStep); ?>
                <?php $isDone = ($step["number"] < $currentStep); ?>

                <div class="host-step<?php echo $isActive ? ' active' : ''; ?><?php echo $isDone ? ' completed' : ''; ?>">

                    <div class="step-circle">

                        <img
                            src="<?php echo htmlspecialchars($step["icon"]); ?>"
                            alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        >

                        <span class="step-number">

                            <?php echo $step["number"]; ?>

                        </span>

                    </div>


                    <h3>

                        <?php echo htmlspecialchars($step["title"]); ?>

                    </h3>


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

        <div class="form-errors" role="alert" aria-live="assertive">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         FORM + LIVE PREVIEW
    ====================================================== -->

    <div class="host-layout">


        <!-- FORM CARD -->

        <section class="host-form-card">


            <form
                id="hostStep2Form"
                action="/webprogg/host/host-step2.php"
                method="POST"
            >


                <!-- =================================================
                     BASIC INFORMATION
                ================================================== -->

                <div class="form-heading">

                    <h2>

                        Basic information

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
                                <?php echo $old("category") === "" ? "selected" : ""; ?>
                            >

                                Select a category

                            </option>


                            <?php foreach ($listingCategories as $catLabel => $catValue): ?>

                                <option
                                    value="<?php echo htmlspecialchars($catValue); ?>"
                                    <?php echo $old("category") === $catValue ? "selected" : ""; ?>
                                >

                                    <?php echo htmlspecialchars($catLabel); ?>

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
                                <?php echo $old("property_type") === "" ? "selected" : ""; ?>
                            >

                                Select a property type

                            </option>


                            <?php foreach ($propertyTypes as $ptLabel => $ptValue): ?>

                                <option
                                    value="<?php echo htmlspecialchars($ptValue); ?>"
                                    <?php echo $old("property_type") === $ptValue ? "selected" : ""; ?>
                                >

                                    <?php echo htmlspecialchars($ptLabel); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>



                <!-- LOCATION (GENERAL AREA) -->

                <div class="input-group">

                    <label for="location">

                        Location <span style="font-weight:400; color: var(--hive-ink-soft);">(city / barangay / general area in Negros Oriental)</span>

                    </label>


                    <div class="icon-input-group">

                        <span class="icon-input-icon">

                            <img
                                src="/webprogg/images/GPSIcon.png"
                                alt=""
                            >

                        </span>


                        <input
                            type="text"
                            id="location"
                            name="location"
                            placeholder="e.g. Dumaguete City, Negros Oriental"
                            value="<?php echo $old("location"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- =============================================
                     NEW: MAP PIN — PINPOINT EXACT LOCATION
                     Free Leaflet + OpenStreetMap map, locked to
                     Negros Oriental. The host types the general
                     area above, the map geocodes + centers there,
                     then the host clicks the exact spot.
                ============================================== -->

                <div class="input-group map-input-group">

                    <label>

                        Pinpoint exact location on map

                    </label>


                    <p class="map-hint">

                        Type the general area above, then click the exact spot of your
                        space on the map. You can drag the pin to adjust. Renters will
                        see this exact pin on your listing.

                    </p>


                    <input type="hidden" id="mapLatitude"  name="latitude"  value="<?php echo $old("latitude"); ?>">

                    <input type="hidden" id="mapLongitude" name="longitude" value="<?php echo $old("longitude"); ?>">


                    <div class="map-toolbar">

                        <button type="button" id="locateOnMapBtn" class="map-locate-btn">

                            &#128269; Search area on map

                        </button>


                        <button type="button" id="useMyLocationBtn" class="map-locate-btn">

                            &#128205; Use my current location

                        </button>


                        <span id="pinStatus" class="pin-status">

                            No pin dropped yet

                        </span>

                    </div>


                    <div id="locationMap" class="location-map"></div>


                    <p class="pin-address-preview" id="pinAddressPreview">

                        <?php echo $old("latitude") !== ""
                            ? "Pin saved — your previous pin has been restored."
                            : "The address of your pin will appear here."; ?>

                    </p>

                </div>

                <!-- ============ END NEW: MAP PIN ============ -->



                <!-- EXACT ADDRESS -->

                <div class="input-group">

                    <label for="exact_address">

                        Exact Address

                    </label>


                    <input
                        type="text"
                        id="exact_address"
                        name="exact_address"
                        placeholder="e.g. 123 Rizal Blvd, Poblacion 4 (street, building, unit no.)"
                        value="<?php echo $old("exact_address"); ?>"
                        required
                    >

                </div>



                <div class="form-section-divider"></div>



                <!-- =================================================
                     SPACE DETAILS
                ================================================== -->

                <div class="form-heading">

                    <h2>

                        Space details

                    </h2>


                    <p>

                        Tell renters about the size, capacity, and pricing.

                    </p>

                </div>



                <!-- PRICE -->

                <div class="input-group">

                    <label for="price">

                        Price per month

                    </label>


                    <div class="price-input-group">

                        <span class="price-icon">&#8369;</span>


                        <input
                            type="number"
                            id="price"
                            name="price"
                            placeholder="0.00"
                            step="0.01"
                            min="1"
                            value="<?php echo $old("price"); ?>"
                            required
                        >

                    </div>

                </div>



                <!-- CAPACITY -->

                <div class="input-group">

                    <label for="capacity">

                        Maximum Guests

                    </label>


                    <select
                        id="capacity"
                        name="capacity"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?php echo $old("capacity") === "" ? "selected" : ""; ?>
                        >

                            Select maximum guests

                        </option>


                        <?php foreach ($capacityOptions as $capValue => $capLabel): ?>

                            <option
                                value="<?php echo htmlspecialchars($capValue); ?>"
                                <?php echo $old("capacity") === $capValue ? "selected" : ""; ?>
                            >

                                <?php echo htmlspecialchars($capLabel); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- BEDROOMS / BATHROOMS / SIZE -->

                <div class="form-grid three-columns">


                    <div class="input-group">

                        <label for="bedrooms">

                            Bedrooms

                        </label>


                        <input
                            type="number"
                            id="bedrooms"
                            name="bedrooms"
                            placeholder="e.g. 1"
                            min="1"
                            value="<?php echo $old("bedrooms"); ?>"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="bathrooms">

                            Bathrooms

                        </label>


                        <input
                            type="number"
                            id="bathrooms"
                            name="bathrooms"
                            placeholder="e.g. 1"
                            min="1"
                            value="<?php echo $old("bathrooms"); ?>"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="size_sqm">

                            Size (m&sup2;)

                        </label>


                        <input
                            type="number"
                            id="size_sqm"
                            name="size_sqm"
                            placeholder="e.g. 25"
                            step="0.01"
                            min="1"
                            value="<?php echo $old("size_sqm"); ?>"
                            required
                        >

                    </div>


                </div>



                <!-- FLOOR / PARKING -->

                <div class="form-grid two-columns">


                    <div class="input-group">

                        <label for="floor">

                            Floor

                        </label>


                        <input
                            type="text"
                            id="floor"
                            name="floor"
                            placeholder="e.g. 2nd floor / Ground floor"
                            value="<?php echo $old("floor"); ?>"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="parking">

                            Parking Lot

                        </label>


                        <select
                            id="parking"
                            name="parking"
                            required
                        >

                            <option
                                value=""
                                disabled
                                <?php echo $old("parking") === "" ? "selected" : ""; ?>
                            >

                                Is parking available?

                            </option>


                            <?php foreach ($parkingOptions as $parkValue => $parkLabel): ?>

                                <option
                                    value="<?php echo htmlspecialchars($parkValue); ?>"
                                    <?php echo $old("parking") === $parkValue ? "selected" : ""; ?>
                                >

                                    <?php echo htmlspecialchars($parkLabel); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>



                <div class="form-section-divider"></div>



                <!-- =================================================
                     AMENITIES
                ================================================== -->

                <div class="form-heading">

                    <h2>

                        Amenities

                    </h2>


                    <p>

                        Select everything that's included in your space.

                    </p>

                </div>



                <div class="amenities-grid">


                    <?php foreach ($amenityOptions as $amenityKey => $amenity): ?>


                        <label class="amenity-checkbox">

                            <input
                                type="checkbox"
                                name="amenities[]"
                                value="<?php echo htmlspecialchars($amenityKey); ?>"
                                <?php echo $amenityChecked($amenityKey); ?>
                            >


                            <span class="amenity-checkbox-icon">

                                <img
                                    src="<?php echo htmlspecialchars($amenity["icon"]); ?>"
                                    alt=""
                                >

                            </span>


                            <?php echo htmlspecialchars($amenity["label"]); ?>


                        </label>


                    <?php endforeach; ?>


                </div>



                <div class="form-section-divider"></div>



                <!-- =================================================
                     DESCRIPTION
                ================================================== -->

                <div class="form-heading">

                    <h2>

                        Description

                    </h2>


                    <p>

                        Describe your space — what makes it special?

                    </p>

                </div>



                <!-- DESCRIPTION -->

                <div class="input-group">

                    <label for="description">

                        About your space

                    </label>


                    <textarea
                        id="description"
                        name="description"
                        placeholder="e.g. Bright and airy studio in the heart of the city, walking distance to schools, cafes, and the boulevard..."
                        required
                    ><?php echo $old("description"); ?></textarea>

                </div>



                <!-- HOUSE RULES (OPTIONAL) -->

                <div class="input-group">

                    <label for="house_rules">

                        House Rules <span style="font-weight:400; color: var(--hive-ink-soft);">(optional)</span>

                    </label>


                    <textarea
                        id="house_rules"
                        name="house_rules"
                        placeholder="e.g. No smoking. Quiet hours after 10 PM. Visitors allowed until 8 PM."
                    ><?php echo $old("house_rules"); ?></textarea>

                </div>



                <!-- ACTIONS -->

                <div class="form-actions">


                    <a
                        href="/webprogg/host/becomeahost.php"
                        class="back-button"
                    >

                        &#8592; Back

                    </a>


                    <button
                        type="submit"
                        class="next-button"
                    >

                        Continue &#8594;

                    </button>


                </div>


            </form>

        </section>



        <!-- =====================================================
             LIVE PREVIEW SIDEBAR
        ====================================================== -->

        <aside class="listing-preview-rail">


            <div class="listing-preview" id="listingPreview">

                <span class="listing-preview-label">

                    Live Preview

                </span>


                <div class="listing-preview-photo">

                    Photo added in Step 3

                </div>


                <div class="listing-preview-body">

                    <h3 class="listing-preview-title" id="previewTitle">

                        Your listing title appears here

                    </h3>


                    <p class="listing-preview-meta" id="previewMeta">

                        Category &middot; Location

                    </p>


                    <div class="listing-preview-tags" id="previewTags"></div>


                    <div class="listing-preview-price" id="previewPrice">

                        &#8369;0

                        <span>/ month</span>

                    </div>

                </div>

            </div>


        </aside>


    </div>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>


<!-- =========================================================
     LIVE PREVIEW UPDATER
========================================================= -->

<script>
(function () {

    var titleInput = document.getElementById("title");
    var categorySelect = document.getElementById("category");
    var locationInput = document.getElementById("location");
    var priceInput = document.getElementById("price");
    var amenityBoxes = document.querySelectorAll('input[name="amenities[]"]');

    var previewTitle = document.getElementById("previewTitle");
    var previewMeta = document.getElementById("previewMeta");
    var previewTags = document.getElementById("previewTags");
    var previewPrice = document.getElementById("previewPrice");

    function categoryLabel(value) {
        var map = {
            "shared-bedroom": "Shared Bedroom",
            "private-room": "Private Room",
            "entire-house": "Entire House",
            "boarding-house": "Boarding House",
            "studio-loft": "Studio Loft"
        };
        return map[value] || value;
    }

    function updatePreview() {

        previewTitle.textContent = titleInput.value.trim()
            || "Your listing title appears here";

        var metaParts = [];
        if (categorySelect.value) {
            metaParts.push(categoryLabel(categorySelect.value));
        }
        if (locationInput.value.trim()) {
            metaParts.push(locationInput.value.trim());
        }
        previewMeta.textContent = metaParts.length
            ? metaParts.join(" · ")
            : "Category · Location";

        previewTags.innerHTML = "";
        amenityBoxes.forEach(function (box) {
            if (box.checked) {
                var tag = document.createElement("span");
                tag.className = "listing-preview-tag";
                tag.textContent = box.parentNode.textContent.trim();
                previewTags.appendChild(tag);
            }
        });

        var priceVal = parseFloat(priceInput.value);
        previewPrice.innerHTML = "&#8369;" +
            (isNaN(priceVal) ? "0" : priceVal.toLocaleString()) +
            " <span>/ month</span>";

    }

    [titleInput, categorySelect, locationInput, priceInput].forEach(function (el) {
        if (el) {
            el.addEventListener("input", updatePreview);
            el.addEventListener("change", updatePreview);
        }
    });

    amenityBoxes.forEach(function (box) {
        box.addEventListener("change", updatePreview);
    });

    updatePreview();

})();
</script>


<!-- =========================================================
     NEW: MAP PIN — LEAFLET + OPENSTREETMAP (100% FREE)
     No API key. Map is locked to Negros Oriental, PH.
========================================================== -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function () {

    var mapEl = document.getElementById("locationMap");
    if (!mapEl || typeof L === "undefined") return;

    var locationInput = document.getElementById("location");
    var latInput      = document.getElementById("mapLatitude");
    var lngInput      = document.getElementById("mapLongitude");
    var pinStatus     = document.getElementById("pinStatus");
    var pinAddress    = document.getElementById("pinAddressPreview");
    var form          = document.getElementById("hostStep2Form");

    /* =============================================
       NEGROS ORIENTAL, PHILIPPINES
       The map cannot be panned outside this box.
    ============================================== */
    var NEGROS_CENTER = [9.55, 122.95];
    var NEGROS_BOUNDS = L.latLngBounds([8.40, 122.20], [10.55, 123.70]);

    var pinIcon = L.divIcon({
        className: "rh-pin-icon",
        html: '<div class="rh-pin">📍</div>',
        iconSize: [36, 36],
        iconAnchor: [18, 34]
    });

    var map = L.map("locationMap", {
        center: NEGROS_CENTER,
        zoom: 10,
        minZoom: 9,
        maxZoom: 19,
        maxBounds: NEGROS_BOUNDS,
        maxBoundsViscosity: 0.9
    });

    /* Two free tile layers — hosts can switch to satellite
       to pinpoint the exact building/roof. */
    var standard = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var satellite = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
        maxZoom: 19,
        attribution: "Tiles &copy; Esri"
    });

    standard.addTo(map);

    L.control.layers({
        "Map": standard,
        "Satellite": satellite
    }).addTo(map);

    var marker = null;

    function placePin(lat, lng) {
        if (!marker) {
            marker = L.marker([lat, lng], {
                draggable: true,
                icon: pinIcon
            }).addTo(map);

            marker.on("dragend", function () {
                var p = marker.getLatLng();
                savePin(p.lat, p.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        savePin(lat, lng);
    }

    function savePin(lat, lng) {
        latInput.value = lat;
        lngInput.value = lng;
        pinStatus.textContent = "✔ Pin dropped — drag it or click the map to adjust.";
        pinStatus.classList.add("has-pin");
        reverseGeocode(lat, lng);
    }

    /* Free reverse geocoding via Nominatim (no key) */
    function reverseGeocode(lat, lng) {
        fetch("https://nominatim.openstreetmap.org/reverse?format=json&lat=" + lat + "&lon=" + lng + "&zoom=18&addressdetails=1")
            .then(function (res) { return res.json(); })
            .then(function (data) {
                pinAddress.textContent = (data && data.display_name)
                    ? data.display_name
                    : "Pin saved at " + Number(lat).toFixed(6) + ", " + Number(lng).toFixed(6);
            })
            .catch(function () {
                pinAddress.textContent = "Pin saved at " + Number(lat).toFixed(6) + ", " + Number(lng).toFixed(6);
            });
    }

    /* Search typed location — results LIMITED to Negros Oriental
       via viewbox + bounded=1 (Nominatim, free, no key) */
    function searchLocation() {
        var q = locationInput.value.trim();
        if (!q) return;

        pinStatus.textContent = "Searching…";
        pinStatus.classList.remove("has-pin");

        var url = "https://nominatim.openstreetmap.org/search?format=json&limit=1" +
                  "&countrycodes=ph" +
                  "&viewbox=122.20,10.55,123.70,8.40&bounded=1" +
                  "&q=" + encodeURIComponent(q);

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (results) {
                if (results && results.length > 0) {
                    map.setView([parseFloat(results[0].lat), parseFloat(results[0].lon)], 16);
                    pinStatus.textContent = "Now click the exact spot of your space.";
                } else {
                    pinStatus.textContent = "Couldn't find that area — try the town name (e.g. 'Dumaguete City').";
                }
            })
            .catch(function () {
                pinStatus.textContent = "Search failed — pan the map manually instead.";
            });
    }

    /* Click map = drop / move pin */
    map.on("click", function (e) {
        placePin(e.latlng.lat, e.latlng.lng);
    });

    if (locationInput) {
        locationInput.addEventListener("change", searchLocation);
    }

    var locateBtn = document.getElementById("locateOnMapBtn");
    if (locateBtn) {
        locateBtn.addEventListener("click", searchLocation);
    }

    /* Browser GPS — also 100% free, no key */
    var geoBtn = document.getElementById("useMyLocationBtn");
    if (geoBtn) {
        geoBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                alert("Geolocation is not supported by your browser.");
                return;
            }

            geoBtn.disabled = true;

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    geoBtn.disabled = false;
                    var lat = pos.coords.latitude;
                    var lng = pos.coords.longitude;

                    if (!NEGROS_BOUNDS.contains([lat, lng])) {
                        alert("You appear to be outside Negros Oriental. Please click the map instead.");
                        return;
                    }

                    map.setView([lat, lng], 17);
                    placePin(lat, lng);
                },
                function () {
                    geoBtn.disabled = false;
                    alert("Couldn't get your location. Allow location access, or click the map instead.");
                }
            );
        });
    }

    /* Restore pin after a failed validation resubmit */
    if (latInput.value && lngInput.value) {
        var sLat = parseFloat(latInput.value);
        var sLng = parseFloat(lngInput.value);
        map.setView([sLat, sLng], 17);
        placePin(sLat, sLng);
    }

    /* Require a pin before submitting */
    form.addEventListener("submit", function (e) {
        if (!latInput.value || !lngInput.value) {
            e.preventDefault();
            alert("Please pinpoint your space on the map before continuing.");
            mapEl.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    });

})();
</script>


</body>

</html>