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
 *
 * Only logged-in users can access this page.
 */

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {

    header("Location: /webprogg/auth/loginform.php");
    exit();

}

/*
 * =========================================================
 * STEP 1 CHECK
 * =========================================================
 *
 * The user must have completed step 1 (Tell Us About You)
 * before reaching this page. Session vars set right after
 * step 1's POST are the fast path, but they don't survive
 * a fresh login/session — so for an already-approved host
 * (is_host === true) who doesn't have them, fall back to
 * looking up their most recent host_applications row in
 * the DB instead of bouncing them back to becomeahost.php.
 * becomeahost.php sends every is_host user straight here,
 * so redirecting them back on a missing session var just
 * creates a redirect loop between the two pages.
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

        // Rehydrate the session so the rest of this page
        // (and the INSERT below) works exactly as if step 1
        // had just been submitted.
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

        // No application on record at all — genuinely needs
        // to complete step 1 first.
        header("Location: /webprogg/host/becomeahost.php");
        exit();

    }

}

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

 $userName = $_SESSION["user_name"] ?? "User";

/*
 * NAVBAR AVATAR
 * $_SESSION['avatar_path'] is only set at login time, so if the
 * user uploaded a new profile photo since then, re-check the DB
 * so the navbar's account icon reflects it immediately instead
 * of only after logging back in.
 */
 $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
 $avatarStmt->execute(['id' => $_SESSION['user_id']]);
 $avatarRow = $avatarStmt->fetch();
 $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

/* Notification bell badge count — same placeholder used across
   every logged-in page's navbar until real notifications land. */
 $notification_count = 0;

// Current page (kept as becomeahost.php so the nav /
// footer "BECOME A HOST" link stays highlighted while the
// user moves through the multi-step host registration flow)
 $currentPage = "/webprogg/host/becomeahost.php";
 $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

// The step currently active in the host-steps tracker
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

        // This is a NEW listing — clear any leftover flags/fields from a
        // previous "Add Another Space" run. Without this, `finalized`
        // stays true from the last listing and host-step4.php silently
        // skips flipping THIS listing's status from 'draft' to 'pending'.
        unset($_SESSION['host_application']['finalized']);
        unset($_SESSION['host_application']['cover_photo']);

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
        header("Location: /webprogg/host/host-step3.php");
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

    <!-- NEW: enables JS-gated entrance animations -->
    <script>document.documentElement.classList.add("js");</script>

    <!-- =====================================================
         STEP 2 — MODERNIZED PAGE STYLES
         CHANGED: palette aligned to the site-wide hive
         tokens (honey #eda423 / moss #2f9e5b / ink #1c2a38),
         honeycomb texture + glow blobs, hero badge + shimmer,
         centered header, upgraded error banner, gradient CTA
         with shine sweep, refined reduced-motion rules. All
         functional rules (stepper, inputs, chips, preview)
         are preserved.
    ====================================================== -->

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

        /* ---------- NEW: honeycomb texture + glow blobs ---------- */

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

        /* ---------- entrance (one orchestrated reveal) ---------- */

        @keyframes hiveRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .rh-step2 .host-header,
        .js .rh-step2 .host-form-card {
            animation: hiveRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .js .rh-step2 .host-form-card { animation-delay: 0.1s; }

        /* ---------- NEW: hero badge + shimmer ---------- */

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

        /* ---------- HEADER (CHANGED: centered to match step 1) ---------- */

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

        /* ---------- STEPPER (kept — now centered) ---------- */

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

        /* ---------- ERRORS (CHANGED: heading + "!" bullets like step 1) ---------- */

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

        /* ---------- TWO-COLUMN LAYOUT (kept) ---------- */

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

        /* NEW: gold accent bar under each section heading */
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

        /* ---------- AMENITY CHIPS (kept) ---------- */

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

        /* ---------- ACTIONS (CHANGED: gradient CTA + styled BACK) ---------- */

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

        /* NEW: infinite shine sweep, matching steps 1, 3 and 4 */
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

        /* ---------- LIVE LISTING PREVIEW (kept + hover polish) ---------- */

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


<!-- =========================================================
     MAIN HOST PAGE
========================================================= -->

<main class="host-page">

    <!-- NEW: decorative glow blobs (honeycomb lives on ::before) -->
    <span class="hive-blob hive-blob-1" aria-hidden="true"></span>
    <span class="hive-blob hive-blob-2" aria-hidden="true"></span>


    <!-- =====================================================
         HOST HEADER
    ====================================================== -->

    <section class="host-header">

        <!-- NEW: hero badge, matching step 1 -->
        <span class="hero-badge">

            <span class="hive-pulse-dot"></span>

            Add Your Space &middot; Step 2 of 4

        </span>


        <h1 style="margin-top:20px;">

            Tell us about

            <span class="hive-shimmer">your space</span>

        </h1>



        <!-- =================================================
             HOST STEPS
        ================================================== -->

        <?php

            // One continuous connector behind all four circles, plus a
            // filled portion showing progress up to the current step.
            // Both are sized purely from $hostSteps / $currentStep, so
            // they always line up with however many steps exist.
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


                    <!-- ICON + NUMBER -->
                    <!-- Number badge lives inside the circle now, so its
                         position is always relative to that circle's own
                         box instead of a hardcoded offset — keeps every
                         step's badge sitting in exactly the same spot. -->

                    <div class="step-circle">

                        <img
                            src="<?php echo htmlspecialchars($step["icon"]); ?>"
                            alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        >

                        <span class="step-number">

                            <?php echo $step["number"]; ?>

                        </span>

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

                                &#9678;

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

                        Space details

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



                    <!-- BATHROOMS -->

                    <div class="input-group">

                        <label for="bathrooms">

                            Bathrooms

                        </label>


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



                    <!-- SIZE -->

                    <div class="input-group">

                        <label for="size_sqm">

                            Size

                        </label>


                        <div class="icon-input-group">

                            <input
                                type="number"
                                id="size_sqm"
                                name="size_sqm"
                                min="0"
                                step="0.1"
                                placeholder="e.g. 25"
                                value="<?php echo $old("size_sqm"); ?>"
                                required
                            >


                            <span class="icon-input-suffix">

                                sqm

                            </span>

                        </div>

                    </div>

                </div>



                <!-- FLOOR / PARKING -->

                <div class="form-grid two-columns">


                    <!-- FLOOR -->

                    <div class="input-group">

                        <label for="floor">

                            Floor

                        </label>


                        <input
                            type="text"
                            id="floor"
                            name="floor"
                            placeholder="e.g. 2nd floor, Ground"
                            value="<?php echo $old("floor"); ?>"
                            required
                        >

                    </div>



                    <!-- PARKING -->

                    <div class="input-group">

                        <label for="parking">

                            Parking

                        </label>


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



                <!-- =================================================
                     ABOUT YOUR SPACE
                ================================================== -->

                <div class="form-section-divider"></div>


                <div class="form-heading">

                    <h2>

                        About your space

                    </h2>


                    <p>

                        Tell renters what makes your space special.

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
                        placeholder="Describe the space, the vibe, what's nearby, and why renters will love it..."
                        required
                    ><?php echo $old("description"); ?></textarea>

                </div>



                <!-- HOUSE RULES -->

                <div class="input-group full-width">

                    <label for="house_rules">

                        House Rules

                    </label>


                    <textarea
                        id="house_rules"
                        name="house_rules"
                        placeholder="e.g. No smoking. No pets. Quiet hours after 10 PM..."
                    ><?php echo $old("house_rules"); ?></textarea>

                </div>



                <!-- =================================================
                     AMENITIES
                ================================================== -->

                <div class="form-section-divider"></div>


                <div class="form-heading">

                    <h2>

                        Amenities

                    </h2>


                    <p>

                        Select everything included with your space.

                    </p>

                </div>


                <div class="amenities-grid">

                    <?php foreach ($amenityOptions as $key => $amenity): ?>

                        <label class="amenity-checkbox">

                            <input
                                type="checkbox"
                                name="amenities[]"
                                value="<?php echo htmlspecialchars($key); ?>"
                                data-label="<?php echo htmlspecialchars($amenity["label"]); ?>"
                                <?php echo $amenityChecked($key); ?>
                            >


                            <span class="amenity-checkbox-icon">

                                <img
                                    src="<?php echo htmlspecialchars($amenity["icon"]); ?>"
                                    alt=""
                                >

                            </span>


                            <span>

                                <?php echo htmlspecialchars($amenity["label"]); ?>

                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>



                <!-- =================================================
                     FORM ACTIONS
                ================================================== -->

                <div class="form-actions">


                    <a
                        href="/webprogg/host/becomeahost.php"
                        class="back-button"
                    >

                        <span class="btn-arrow">&#8249;</span>

                        BACK

                    </a>


                    <button
                        type="submit"
                        class="next-button"
                    >

                        NEXT STEP

                        <span class="btn-arrow">&#8250;</span>

                    </button>


                </div>


            </form>

        </section>


        <!-- ================================================
             LIVE LISTING PREVIEW RAIL
        ================================================== -->

        <div class="listing-preview-rail" id="previewRail">

            <aside class="listing-preview" id="listingPreview">

                <span class="listing-preview-label">

                    Live preview

                </span>


                <div class="listing-preview-photo">

                    Photo appears in Step 3

                </div>


                <div class="listing-preview-body">

                    <h4 class="listing-preview-title" id="previewTitle">

                        Your listing title

                    </h4>


                    <p class="listing-preview-meta" id="previewMeta">

                        Category &middot; Location

                    </p>


                    <div class="listing-preview-tags" id="previewTags">

                        <span class="listing-preview-tag">

                            No amenities selected

                        </span>

                    </div>


                    <p class="listing-preview-price">

                        &#8369; <span id="previewPriceNum">0</span>

                        <span>/month</span>

                    </p>

                </div>

            </aside>

        </div>

    </div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">


    <div class="footer-top">


        <!-- BRAND -->

        <div class="footer-brand">


            <a href="/webprogg/index.php">

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
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



        <!-- LISTINGS -->

        <div class="footer-links">


            <span class="footer-heading">

                LISTINGS

            </span>


            <?php foreach ($listingCategories as $category => $type): ?>

                <a href="/webprogg/Listings/listing.php?type=<?php echo urlencode($type); ?>">

                    <?php echo htmlspecialchars($category); ?>

                </a>

            <?php endforeach; ?>


        </div>



        <!-- QUICK LINKS -->

        <div class="footer-links">


            <span class="footer-heading">

                QUICK LINKS

            </span>


            <?php foreach ($quickLinks as $name => $link): ?>

                <a href="<?php echo htmlspecialchars($link); ?>">

                    <?php echo htmlspecialchars($name); ?>

                </a>

            <?php endforeach; ?>


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



            <div class="footer-contact-line">


                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt="Phone"
                >


                <span>

                    +63 927 569 3574

                </span>


            </div>



            <div class="footer-contact-line">


                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt="Email"
                >


                <span>

                    hello@roomhive.ph

                </span>


            </div>



            <div class="footer-contact-line">


                <img
                    src="/webprogg/images/GPSIcon.png"
                    alt="Location"
                >


                <span>

                    Dumaguete City, Negros Oriental

                </span>


            </div>


        </div>

    </div>



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
     LIVE PREVIEW + STICKY RAIL SCRIPT
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* =====================================================
       LIVE LISTING PREVIEW
       Mirrors title / category · location / amenity tags /
       price into the sidebar card as the host types.
    ====================================================== */

    var titleInput   = document.getElementById("title");
    var categorySel  = document.getElementById("category");
    var locationIn   = document.getElementById("location");
    var priceIn      = document.getElementById("price");

    var previewTitle = document.getElementById("previewTitle");
    var previewMeta  = document.getElementById("previewMeta");
    var previewTags  = document.getElementById("previewTags");
    var previewPrice = document.getElementById("previewPriceNum");

    var amenityChecks = Array.prototype.slice.call(
        document.querySelectorAll('.amenities-grid input[type="checkbox"]')
    );

    function updatePreview() {

        if (previewTitle && titleInput) {
            previewTitle.textContent =
                titleInput.value.trim() || "Your listing title";
        }

        if (previewMeta) {
            var bits = [];

            if (categorySel && categorySel.value) {
                bits.push(categorySel.options[categorySel.selectedIndex].text);
            }

            if (locationIn && locationIn.value.trim()) {
                bits.push(locationIn.value.trim());
            }

            previewMeta.textContent =
                bits.length ? bits.join(" \u00B7 ") : "Category \u00B7 Location";
        }

        if (previewTags) {
            previewTags.innerHTML = "";

            var any = false;

            amenityChecks.forEach(function (cb) {
                if (cb.checked) {
                    any = true;

                    var tag = document.createElement("span");
                    tag.className = "listing-preview-tag";
                    tag.textContent = cb.getAttribute("data-label") || "";

                    previewTags.appendChild(tag);
                }
            });

            if (!any) {
                var hint = document.createElement("span");
                hint.className = "listing-preview-tag";
                hint.textContent = "No amenities selected";

                previewTags.appendChild(hint);
            }
        }

        if (previewPrice && priceIn) {
            var value = parseFloat(priceIn.value);
            previewPrice.textContent = isNaN(value)
                ? "0"
                : value.toLocaleString();
        }
    }

    [
        [titleInput, "input"],
        [categorySel, "change"],
        [locationIn, "input"],
        [priceIn, "input"]
    ].forEach(function (pair) {
        if (pair[0]) {
            pair[0].addEventListener(pair[1], updatePreview);
        }
    });

    amenityChecks.forEach(function (cb) {
        cb.addEventListener("change", updatePreview);
    });

    /* Initial paint — covers the server-repopulate case after
       a failed POST so the preview matches the form. */
    updatePreview();


    /* =====================================================
       STICKY PREVIEW RAIL
       Pins the preview under the navbar while the form
       scrolls, and releases it before the form card ends
       so it never overlaps the footer. Desktop only.
    ====================================================== */

    var rail    = document.getElementById("previewRail");
    var preview = document.getElementById("listingPreview");
    var layout  = document.querySelector(".host-layout");

    if (!rail || !preview || !layout) {
        return;
    }

    var NAV_OFFSET = 90;
    var pinActive = false;

    function pinUpdate() {
        if (window.innerWidth <= 900 || reduced) {
            preview.classList.remove("is-pinned");
            preview.style.left = "";
            preview.style.width = "";
            pinActive = false;
            return;
        }

        var railRect = rail.getBoundingClientRect();
        var layoutRect = layout.getBoundingClientRect();
        var previewHeight = preview.offsetHeight;

        var shouldPin =
            railRect.top <= NAV_OFFSET &&
            (layoutRect.bottom - NAV_OFFSET) > previewHeight + 24;

        if (shouldPin) {
            if (!pinActive) {
                preview.classList.add("is-pinned");
                preview.style.width = railRect.width + "px";
                preview.style.left = railRect.left + "px";
                pinActive = true;
            }

            /* Stop the preview from sliding past the layout's
               bottom edge on very long pins. */
            var overflow =
                layoutRect.bottom - NAV_OFFSET - previewHeight - 24;

            preview.style.top =
                overflow < 0 ? (NAV_OFFSET + overflow) + "px" : NAV_OFFSET + "px";

        } else if (pinActive) {
            preview.classList.remove("is-pinned");
            preview.style.left = "";
            preview.style.width = "";
            preview.style.top = "";
            pinActive = false;
        }
    }

    window.addEventListener("scroll", pinUpdate, { passive: true });
    window.addEventListener("resize", pinUpdate);

    pinUpdate();
})();
</script>


</body>

</html>