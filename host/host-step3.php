<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 3)
   UPLOAD PHOTOS
   === HIVE CLUB REMOVED ===
   === SHARED FOOTER (this version) ===
   $listingCategories and $quickLinks were footer-only and
   are deleted — includes/footer.php owns the footer now.
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
 * STEP 1 & STEP 2 CHECK
 * =========================================================
 *
 * The user must have completed step 1 (Tell Us About You)
 * and step 2 (Add Your Space) before reaching this page.
 */

if (
    !isset($_SESSION["host_application"]) ||
    !isset($_SESSION["host_application"]["title"]) ||
    !isset($_SESSION["host_application"]["listing_id"])
) {

    header("Location: /webprogg/host/becomeahost.php");
    exit();

}

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

 $userName = $_SESSION["user_name"] ?? "User";

/*
 * NAVBAR AVATAR
 */
 $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
 $avatarStmt->execute(['id' => $_SESSION['user_id']]);
 $avatarRow = $avatarStmt->fetch();
 $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $notification_count = 0;

 $currentPage = "/webprogg/host/becomeahost.php";
 $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

 $currentStep = 3;

 $maxAdditionalPhotos = 20;

 $initialPhotoSlots = 10;

 $navigation = [
    "HOME" => "/webprogg/user/usershome.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

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

 $errors = [];

 $allowedTypes = [
    "image/jpeg",
    "image/png"
];

 $maxFileSize = 5 * 1024 * 1024;

// Process form when submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // =====================================================
    // COVER PHOTO VALIDATION
    // =====================================================

    if (
        !isset($_FILES["cover_photo"]) ||
        $_FILES["cover_photo"]["error"] !== UPLOAD_ERR_OK
    ) {

        $errors[] = "Please upload a cover photo for your space.";

    } else {

        $coverFile = $_FILES["cover_photo"];

        if ($coverFile["size"] > $maxFileSize) {

            $errors[] = "Cover photo must not exceed 5MB.";

        }

        if (!in_array($coverFile["type"], $allowedTypes, true)) {

            $errors[] = "Cover photo must be a JPG or PNG file.";

        }

    }

    // =====================================================
    // ADDITIONAL PHOTOS VALIDATION
    // =====================================================

    $additionalFiles = [];

    if (isset($_FILES["additional_photos"])) {

        $names = $_FILES["additional_photos"]["name"];

        $count = is_array($names) ? count($names) : 0;

        if ($count > $maxAdditionalPhotos) {

            $errors[] = "You can upload a maximum of {$maxAdditionalPhotos} additional photos.";

        }

        for ($i = 0; $i < $count; $i++) {

            if ($_FILES["additional_photos"]["error"][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $fileSize = $_FILES["additional_photos"]["size"][$i];

            $fileType = $_FILES["additional_photos"]["type"][$i];

            if ($fileSize > $maxFileSize) {

                $errors[] = "Each additional photo must not exceed 5MB.";

                break;

            }

            if (!in_array($fileType, $allowedTypes, true)) {

                $errors[] = "Additional photos must be JPG or PNG files.";

                break;

            }

            $additionalFiles[] = [
                "name" => $_FILES["additional_photos"]["name"][$i],
                "tmp_name" => $_FILES["additional_photos"]["tmp_name"][$i]
            ];

        }

    }

    // =====================================================
    // IF VALID
    // =====================================================

    if (empty($errors)) {

        $listingId = $_SESSION["host_application"]["listing_id"];

        $coverUploadDirectory      = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos/cover/';
        $additionalUploadDirectory = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos/additional/';

        if (!is_dir($coverUploadDirectory)) {

            mkdir($coverUploadDirectory, 0755, true);

        }

        if (!is_dir($additionalUploadDirectory)) {

            mkdir($additionalUploadDirectory, 0755, true);

        }

        // ---- Cover photo ----

        $coverExtension = strtolower(
            pathinfo($coverFile["name"], PATHINFO_EXTENSION)
        );

        $coverFileName =
            "cover_" .
            time() .
            "_" .
            uniqid() .
            "." .
            $coverExtension;

        $coverDiskPath = $coverUploadDirectory . $coverFileName;

        $coverDbPath = "uploads/listing_photos/cover/" . $coverFileName;

        if (move_uploaded_file($coverFile["tmp_name"], $coverDiskPath)) {

            $pdo->prepare(
                "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                 VALUES (:listing_id, :photo_path, 'cover', 0)"
            )->execute([
                'listing_id' => $listingId,
                'photo_path' => $coverDbPath,
            ]);

            $_SESSION["host_application"]["cover_photo"] = $coverDbPath;

        }

        // ---- Additional photos ----

        $savedAdditionalPaths = [];

        $sortOrder = 1;

        foreach ($additionalFiles as $file) {

            $extension = strtolower(
                pathinfo($file["name"], PATHINFO_EXTENSION)
            );

            $fileName =
                "photo_" .
                time() .
                "_" .
                uniqid() .
                "." .
                $extension;

            $diskPath = $additionalUploadDirectory . $fileName;
            $dbPath   = "uploads/listing_photos/additional/" . $fileName;

            if (move_uploaded_file($file["tmp_name"], $diskPath)) {

                $pdo->prepare(
                    "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                     VALUES (:listing_id, :photo_path, 'additional', :sort_order)"
                )->execute([
                    'listing_id' => $listingId,
                    'photo_path' => $dbPath,
                    'sort_order' => $sortOrder,
                ]);

                $savedAdditionalPaths[] = $dbPath;

                $sortOrder++;

            }

        }

        $_SESSION["host_application"]["additional_photos"] = $savedAdditionalPaths;

        // Go to next host registration step
        header("Location: /webprogg/host/host-step4.php");

        exit;

    }

}

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
        RoomHive - Upload Photos
    </title>

    <!-- POPPINS FONT -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- MAIN CSS -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css?v=2"
    >

    <!-- NEW: enables JS-gated entrance animations -->
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
        }

        @media (prefers-reduced-motion: reduce) {
            .hive-blob,
            .hive-pulse-dot::after,
            .hive-shimmer,
            .rh-step3 .next-button::after,
            .rh-step3 .cover-photo-box .upload-icon,
            .rh-step3 .photo-slot-icon {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .js .rh-step3 .host-header,
            .js .rh-step3 .host-form-card,
            .js .rh-step3 .form-errors {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }

        body.rh-step3 {
            background: var(--hive-paper);
            font-family: "Poppins", sans-serif;
            color: var(--hive-ink);
            overflow-y: auto;
            height: auto;
            min-height: 100vh;
        }

        .rh-step3 main.host-page {
            position: relative;

            max-width: 1000px;
            margin: 0 auto;
            padding: 56px 24px 96px;
        }

        .rh-step3 main.host-page::before {
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

        .rh-step3 main.host-page > * {
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

        @keyframes hiveRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .rh-step3 .host-header,
        .js .rh-step3 .host-form-card {
            animation: hiveRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .js .rh-step3 .host-form-card { animation-delay: 0.1s; }

        .rh-step3 .hero-badge {
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

        .rh-step3 .host-header {
            max-width: 860px;
            margin: 0 auto 48px;
            text-align: center;
        }

        .rh-step3 .host-eyebrow {
            display: block;

            margin-top: 18px;

            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--hive-honey-dark);
            text-transform: uppercase;
        }

        .rh-step3 .host-header h1 {
            font-size: clamp(28px, 4vw, 36px);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.6px;
            margin: 10px 0 0;
            color: var(--hive-ink);
        }

        .rh-step3 .host-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            margin-top: 40px;
            position: relative;
        }

        .rh-step3 .host-steps-track {
            position: absolute;
            top: 26px;
            left: 12.5%;
            right: 12.5%;
            height: 2px;
            background: var(--hive-line);
            z-index: 1;
        }

        .rh-step3 .host-steps-progress {
            position: absolute;
            top: 26px;
            left: 12.5%;
            height: 2px;
            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));
            z-index: 1;
            transition: width 0.3s ease;
        }

        .rh-step3 .host-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0 10px;
        }

        .rh-step3 .step-circle {
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

        .rh-step3 .step-circle img {
            width: 22px;
            height: 22px;
            object-fit: contain;
            object-position: center;
            display: block;
            opacity: 0.55;
            filter: grayscale(1);
            transition: opacity 0.2s ease, filter 0.2s ease;
        }

        .rh-step3 .host-step.active .step-circle {
            border-color: var(--hive-honey);
            background: #FDF4E3;
            box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.18);
        }

        .rh-step3 .host-step.active .step-circle img {
            opacity: 1;
            filter: none;
        }

        .rh-step3 .host-step.completed .step-circle {
            border-color: var(--hive-moss);
            background: var(--hive-moss);
        }

        .rh-step3 .host-step.completed .step-circle img {
            opacity: 1;
            filter: brightness(0) invert(1);
        }

        .rh-step3 .step-number {
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

        .rh-step3 .host-step.active .step-number {
            background: var(--hive-honey-dark);
        }

        .rh-step3 .host-step.completed .step-number {
            background: var(--hive-moss);
        }

        .rh-step3 .host-step h3 {
            margin: 14px 0 2px;
            font-size: 14px;
            font-weight: 600;
            color: var(--hive-ink);
        }

        .rh-step3 .host-step p {
            margin: 0 auto;
            max-width: 150px;
            font-size: 12.5px;
            color: var(--hive-ink-soft);
            line-height: 1.4;
        }

        .rh-step3 .host-step:not(.active) h3 {
            color: var(--hive-ink-soft);
        }

        .rh-step3 .form-errors {
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

        .rh-step3 .form-errors::before {
            content: "We need a couple of fixes before continuing";
            font-weight: 600;
            color: #e0524d;
            margin-bottom: 2px;
        }

        .rh-step3 .form-errors p {
            margin: 0;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
            color: #7a2f2c;
        }

        .rh-step3 .form-errors p::before {
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

        .rh-step3 .host-form-card {
            background: var(--hive-surface);
            border: 1px solid var(--hive-line);
            border-radius: 18px;
            box-shadow: 0 18px 40px -22px rgba(28, 42, 56, 0.35);
            padding: 40px;
        }

        .rh-step3 .form-heading h2 {
            position: relative;

            display: inline-block;

            font-size: 19px;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--hive-ink);
        }

        .rh-step3 .form-heading h2::after {
            content: "";

            position: absolute;

            width: 38px;
            height: 3px;

            left: 0;
            bottom: -8px;

            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));

            border-radius: 2px;
        }

        .rh-step3 .form-heading p {
            margin: 18px 0 24px;
            font-size: 14px;
            color: var(--hive-ink-soft);
        }

        .rh-step3 .photo-section-label {
            margin-bottom: 14px;
        }

        .rh-step3 .photo-section-label label {
            display: block;

            margin-bottom: 4px;

            color: var(--hive-ink);

            font-size: 14px;
            font-weight: 700;
        }

        .rh-step3 .photo-section-label p {
            margin: 0;

            color: var(--hive-ink-soft);

            font-size: 12.5px;
        }

        .rh-step3 .required-mark {
            color: #e0524d;
        }

        .cover-photo-box {
            position: relative;
            overflow: hidden;
        }

        .cover-photo-preview {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-slot {
            position: relative;
            overflow: hidden;
        }

        .photo-slot-preview {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-slot-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            z-index: 2;

            transition:
                background 0.15s ease,
                transform 0.15s ease;
        }

        .photo-slot-remove:hover {
            background: #e0524d;
            transform: scale(1.1);
        }

        .cover-photo-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            z-index: 2;

            transition:
                background 0.15s ease,
                transform 0.15s ease;
        }

        .cover-photo-remove:hover {
            background: #e0524d;
            transform: scale(1.1);
        }

        .rh-step3 .cover-photo-row {
            width: 100%;

            display: grid;
            grid-template-columns: 1fr 1fr;

            gap: 20px;

            margin-bottom: 34px;
        }

        @media (max-width: 700px) {
            .rh-step3 .cover-photo-row {
                grid-template-columns: 1fr;
            }
        }

        .rh-step3 .cover-photo-box {
            width: 100%;
            min-height: 190px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 2px dashed #e2d4b6;
            border-radius: 18px;

            background: linear-gradient(160deg, #fffdf6 0%, #fdf3e0 100%);

            cursor: pointer;

            transition:
                border-color 0.2s ease,
                box-shadow 0.25s ease,
                transform 0.2s ease,
                background 0.2s ease;
        }

        .rh-step3 .cover-photo-box:hover {
            border-color: var(--hive-honey);

            background: linear-gradient(160deg, #ffffff 0%, #fff8ec 100%);

            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.16);

            transform: translateY(-2px);
        }

        .rh-step3 .cover-photo-box.has-image {
            background: #111418;
            border-style: solid;
            border-color: var(--hive-line);
            transform: none;
        }

        .rh-step3 .cover-photo-box .upload-icon {
            color: var(--hive-honey);

            font-size: 34px;

            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .rh-step3 .cover-photo-box:hover .upload-icon {
            transform: translateY(-4px) scale(1.12);
        }

        .rh-step3 .cover-photo-box .upload-text strong {
            color: var(--hive-ink);

            font-size: 14px;
        }

        .rh-step3 .cover-photo-box .upload-text span {
            color: var(--hive-ink-soft);

            font-size: 12px;
        }

        .rh-step3 .cover-photo-example {
            position: relative;

            width: 100%;
            min-height: 190px;

            border-radius: 16px;

            overflow: hidden;

            box-shadow: 0 10px 24px rgba(28, 42, 56, 0.1);
        }

        .rh-step3 .cover-photo-example img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            display: block;
        }

        .rh-step3 .cover-photo-example .example-badge {
            position: absolute;

            top: 12px;
            right: 12px;

            padding: 5px 12px;

            background: linear-gradient(135deg, #f6b93b, var(--hive-honey));

            border-radius: 6px;

            color: var(--hive-ink);

            font-size: 11px;
            font-weight: 700;

            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .rh-step3 .photo-grid {
            width: 100%;

            display: grid;
            grid-template-columns: repeat(5, 1fr);

            gap: 16px;

            margin-bottom: 30px;
        }

        @media (max-width: 900px) {
            .rh-step3 .photo-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 700px) {
            .rh-step3 .photo-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 480px) {
            .rh-step3 .photo-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .rh-step3 .photo-slot {
            width: 100%;
            aspect-ratio: 1 / 1;

            border-radius: 14px;
        }

        .rh-step3 .photo-slot-trigger {
            width: 100%;
            height: 100%;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            gap: 6px;

            padding: 10px;

            border: 2px dashed #e2d4b6;
            border-radius: 14px;

            background: linear-gradient(160deg, #fffdf6 0%, #fdf3e0 100%);

            cursor: pointer;

            transition:
                border-color 0.2s ease,
                box-shadow 0.25s ease,
                transform 0.2s ease;
        }

        .rh-step3 .photo-slot:hover .photo-slot-trigger {
            border-color: var(--hive-honey);

            box-shadow: 0 10px 20px rgba(237, 164, 35, 0.14);

            transform: translateY(-3px);
        }

        .rh-step3 .photo-slot-icon {
            display: inline-block;

            color: var(--hive-honey);

            font-size: 22px;
            line-height: 1;

            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .rh-step3 .photo-slot:hover .photo-slot-icon {
            transform: translateY(-3px) scale(1.1);
        }

        .rh-step3 .photo-slot-text {
            display: flex;
            flex-direction: column;

            align-items: center;

            text-align: center;
        }

        .rh-step3 .photo-slot-text strong {
            color: var(--hive-ink);

            font-size: 10.5px;
            font-weight: 600;
        }

        .rh-step3 .photo-slot-text small {
            color: var(--hive-ink-soft);

            font-size: 9px;
        }

        .rh-step3 .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid var(--hive-line);
        }

        .rh-step3 .back-button {
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

        .rh-step3 .back-button:hover {
            border-color: var(--hive-honey);

            color: #b07708;

            transform: translateX(-2px);

            box-shadow: 0 8px 18px rgba(28, 42, 56, 0.08);
        }

        .rh-step3 .next-button {
            position: relative;
            overflow: hidden;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-width: 150px;
            height: 46px;

            padding: 0 30px;

            background: linear-gradient(135deg, var(--hive-honey-light), var(--hive-honey));

            border: none;
            border-radius: 10px;

            color: var(--hive-ink);

            font-family: "Poppins", sans-serif;
            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35);

            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .rh-step3 .next-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45);
        }

        .rh-step3 .next-button:active {
            transform: translateY(0) scale(0.98);
        }

        .rh-step3 .next-button::after {
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

        .rh-step3 .btn-arrow {
            font-size: 16px;
            line-height: 1;
        }

        .rh-step3 .next-button:focus-visible,
        .rh-step3 .back-button:focus-visible {
            outline: 3px solid rgba(237, 164, 35, 0.5);
            outline-offset: 2px;
        }

    </style>

</head>


<body class="rh-step3">


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


    <section class="host-header">

        <span class="hero-badge">

            <span class="hive-pulse-dot"></span>

            Upload Photos &middot; Step 3 of 4

        </span>


        <h1 style="margin-top:20px;">

            Show off

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



    <?php if (!empty($errors)): ?>

        <div class="form-errors" role="alert" aria-live="assertive">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>



    <section class="host-form-card">


        <form
            action="/webprogg/host/host-step3.php"
            method="POST"
            enctype="multipart/form-data"
            id="uploadPhotosForm"
            novalidate
        >


            <div class="form-heading">

                <h2>

                    Upload Photos of Your Space

                </h2>


                <p>

                    High-quality photos help your listing stand out and get more bookings.

                </p>

            </div>



            <div class="photo-section-label">

                <label>

                    Cover Photo <span class="required-mark">*</span>

                </label>


                <p>

                    This will be the main photo displayed in your listing.

                </p>

            </div>


            <div class="cover-photo-row">


                <label
                    for="cover_photo"
                    class="cover-photo-box"
                    id="coverPhotoBox"
                >

                    <div class="cover-photo-placeholder" id="coverPhotoPlaceholder">

                        <div class="upload-icon">

                            &#9729;

                        </div>


                        <div class="upload-text">

                            <strong>

                                Upload cover photo

                            </strong>


                            <span>

                                (JPG, PNG – Max 5MB)

                            </span>

                        </div>

                    </div>


                    <img
                        src=""
                        alt="Cover photo preview"
                        class="cover-photo-preview"
                        id="coverPhotoPreview"
                        hidden
                    >


                    <button
                        type="button"
                        class="cover-photo-remove"
                        id="coverPhotoRemove"
                        hidden
                    >
                        &times;
                    </button>

                </label>


                <input
                    type="file"
                    id="cover_photo"
                    name="cover_photo"
                    accept=".jpg,.jpeg,.png"
                    hidden
                    required
                >


                <div class="cover-photo-example">

                    <span class="example-badge">

                        Example

                    </span>


                    <img
                        src="/webprogg/images/Living_Room.png"
                        alt="Example of a good cover photo"
                    >

                </div>

            </div>



            <div class="photo-section-label">

                <label>

                    Additional Photos

                </label>


                <p>

                    Add more photos to give renters a better idea of your space. You can add up to <?php echo (int) $maxAdditionalPhotos; ?> photos.

                </p>

            </div>


            <div
                class="photo-grid"
                id="photoGrid"
            >

                <?php for ($i = 0; $i < $initialPhotoSlots; $i++): ?>

                    <div class="photo-slot">

                        <button
                            type="button"
                            class="photo-slot-trigger"
                        >

                            <span class="photo-slot-icon">

                                &#9729;

                            </span>


                            <span class="photo-slot-text">

                                <strong>Upload photo</strong>

                                <small>JPG, PNG – Max 5MB</small>

                            </span>

                        </button>


                        <img
                            class="photo-slot-preview"
                            alt="Photo preview"
                            hidden
                        >


                        <button
                            type="button"
                            class="photo-slot-remove"
                            hidden
                        >
                            &times;
                        </button>


                        <input
                            type="file"
                            name="additional_photos[]"
                            accept=".jpg,.jpeg,.png"
                            class="photo-slot-input"
                            hidden
                        >

                    </div>

                <?php endfor; ?>

            </div>


            <div class="form-actions">


                <a
                    href="/webprogg/host/host-step2.php"
                    class="back-button"
                >

                    <span class="btn-arrow">&#8592;</span>

                    BACK

                </a>


                <button
                    type="submit"
                    class="next-button"
                >

                    <span class="btn-arrow">&#8594;</span>

                    NEXT STEP

                </button>

            </div>

        </form>

    </section>

</main>


<!-- =========================================================
     SHARED FOOTER — single source of truth (includes/footer.php)
========================================================= -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>


<!-- =========================================================
     STEP 3 SCRIPT — cover preview, photo slots, submit gate
     ⚠ RECONSTRUCTED (paste cut off here) — hooks the exact
     IDs/classes in your markup & CSS above.
========================================================= -->
<script>
(function () {
    "use strict";

    var form = document.getElementById("uploadPhotosForm");
    if (!form) { return; }

    /* ---------- COVER PHOTO ---------- */

    var coverBox     = document.getElementById("coverPhotoBox");
    var coverInput   = document.getElementById("cover_photo");
    var coverPreview = document.getElementById("coverPhotoPreview");
    var coverRemove  = document.getElementById("coverPhotoRemove");

    function clearCover() {
        coverInput.value = "";
        coverPreview.removeAttribute("src");
        coverPreview.hidden = true;
        coverRemove.hidden = true;
        coverBox.classList.remove("has-image");
    }

    if (coverInput) {

        coverInput.addEventListener("change", function () {
            var file = coverInput.files && coverInput.files[0];

            if (!file) { clearCover(); return; }

            if (["image/jpeg", "image/png"].indexOf(file.type) === -1) {
                clearCover();
                alert("Cover photo must be a JPG or PNG file.");
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                clearCover();
                alert("Cover photo must not exceed 5MB.");
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                coverPreview.src = e.target.result;
                coverPreview.hidden = false;
                coverRemove.hidden = false;
                coverBox.classList.add("has-image");
            };
            reader.readAsDataURL(file);
        });
    }

    if (coverRemove) {
        coverRemove.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearCover();
        });
    }

    /* ---------- ADDITIONAL PHOTO SLOTS ---------- */

    var slots = document.querySelectorAll(".photo-slot");

    slots.forEach(function (slot) {

        var trigger = slot.querySelector(".photo-slot-trigger");
        var input   = slot.querySelector(".photo-slot-input");
        var preview = slot.querySelector(".photo-slot-preview");
        var remove  = slot.querySelector(".photo-slot-remove");

        if (!input) { return; }

        if (trigger) {
            trigger.addEventListener("click", function () {
                input.click();
            });
        }

        input.addEventListener("change", function () {
            var file = input.files && input.files[0];

            if (!file) {
                input.value = "";
                preview.removeAttribute("src");
                preview.hidden = true;
                remove.hidden = true;
                return;
            }

            if (["image/jpeg", "image/png"].indexOf(file.type) === -1) {
                input.value = "";
                preview.removeAttribute("src");
                preview.hidden = true;
                remove.hidden = true;
                alert("Additional photos must be JPG or PNG files.");
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                input.value = "";
                preview.removeAttribute("src");
                preview.hidden = true;
                remove.hidden = true;
                alert("Each photo must not exceed 5MB.");
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.hidden = false;
                remove.hidden = false;
            };
            reader.readAsDataURL(file);
        });

        if (remove) {
            remove.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                input.value = "";
                preview.removeAttribute("src");
                preview.hidden = true;
                remove.hidden = true;
            });
        }
    });

    /* ---------- SUBMIT GATE: cover photo is mandatory ---------- */

    form.addEventListener("submit", function (e) {
        if (!coverInput.files || !coverInput.files.length) {
            e.preventDefault();
            alert("Please upload a cover photo before continuing.");
            if (coverBox) {
                coverBox.scrollIntoView({ behavior: "smooth", block: "center" });
            }
            return;
        }
    });
})();
</script>


</body>

</html>