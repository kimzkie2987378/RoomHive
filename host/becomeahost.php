<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST
   ========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';   // ← add this, it's missing
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
 * If the user is already a host, don't show them the
 * "Become a Host" form again — send them straight to
 * host-step2.php.
 */

if (isset($_SESSION["is_host"]) && $_SESSION["is_host"] === true) {
    header("Location: /webprogg/host/host-step2.php");
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

// Current page
$currentPage = "/webprogg/host/becomeahost.php";

// =========================================================
// NAVIGATION
// =========================================================

$navigation = [
    "HOME" => "/webprogg/index.php",
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

// The user is on this page to complete Step 1, so the steps
// strip can highlight where they currently stand.
$currentHostStep = 1;


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
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $age = trim($_POST["age"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $idType = trim($_POST["id_type"] ?? "");
    $idNumber = trim($_POST["id_number"] ?? "");


    // =====================================================
    // VALIDATION
    // =====================================================

    if ($fullName === "") {
        $errors[] = "Please enter your full name.";
    }


    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }


    if ($phone === "") {
    $errors[] = "Please enter your phone number.";
} elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
    $errors[] = "Phone number must be exactly 11 digits.";
}


    // =====================================================
    // AGE VALIDATION
    // Must be a whole number, and must be 18+ to host.
    // Anyone under 18 is blocked right here — no record is
    // ever created for a minor's host application.
    // =====================================================

    if ($age === "" || !ctype_digit($age)) {

        $errors[] = "Please enter a valid age.";

    } elseif ((int) $age < 18) {

        $errors[] = "You must be at least 18 years old to become a host.";

    } elseif ((int) $age > 120) {

        $errors[] = "Please enter a valid age.";

    }


    if ($location === "") {
        $errors[] = "Please enter your location.";
    }


    if ($idType === "") {
        $errors[] = "Please select your ID type.";
    }


    if ($idNumber === "") {
        $errors[] = "Please enter your ID number.";
    }


    // =====================================================
    // DUPLICATE EMAIL / PHONE CHECK
    // Warn if this email or phone is already tied to a
    // different user's host application. Checked separately
    // so the user gets a specific warning for each field.
    // Only blocks against applications that are still ACTIVE
    // (pending/approved) — a rejected or otherwise dead
    // application shouldn't permanently squat on an email
    // or phone number.
    // =====================================================

    if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $emailCheckStmt = $pdo->prepare(
            "SELECT id FROM host_applications
             WHERE email = :email AND user_id != :user_id
               AND status IN ('pending', 'approved')
             LIMIT 1"
        );

        $emailCheckStmt->execute([
            'email'   => $email,
            'user_id' => $_SESSION['user_id'],
        ]);

        if ($emailCheckStmt->fetch()) {
            $errors[] = "This email address is already registered to another host application.";
        }

    }


    if ($phone !== "") {

        $phoneCheckStmt = $pdo->prepare(
            "SELECT id FROM host_applications
             WHERE phone = :phone AND user_id != :user_id
               AND status IN ('pending', 'approved')
             LIMIT 1"
        );

        $phoneCheckStmt->execute([
            'phone'   => $phone,
            'user_id' => $_SESSION['user_id'],
        ]);

        if ($phoneCheckStmt->fetch()) {
            $errors[] = "This phone number is already registered to another host application.";
        }

    }


    // =====================================================
    // ID UPLOAD VALIDATION
    // =====================================================

    if (
        !isset($_FILES["upload_id"]) ||
        $_FILES["upload_id"]["error"] !== UPLOAD_ERR_OK
    ) {

        $errors[] = "Please upload your ID.";

    } else {

        $file = $_FILES["upload_id"];

        $maxSize = 5 * 1024 * 1024;

        $allowedTypes = [
            "image/jpeg",
            "image/png"
        ];


        if ($file["size"] > $maxSize) {

            $errors[] = "ID file must not exceed 5MB.";

        }


        if (!in_array($file["type"], $allowedTypes, true)) {

            $errors[] = "Only JPG and PNG files are allowed.";

        }

    }


    // =====================================================
    // IF VALID
    // =====================================================

    if (empty($errors)) {

    $updateStmt = $pdo->prepare(
        "UPDATE users SET phone = :phone, location = :location, age = :age WHERE id = :id"
    );
    $updateStmt->execute([
        'phone'    => $phone,
        'location' => $location,
        'age'      => (int) $age,
        'id'       => $_SESSION['user_id'],
    ]);

    // =================================================
    // SAVE UPLOADED ID
    // -------------------------------------------------
    // Use an ABSOLUTE FILESYSTEM path (via DOCUMENT_ROOT)
    // to actually write the file, so it's not dependent
    // on whatever the script's current working directory
    // happens to be.
    //
    // Store an ABSOLUTE WEB path (starting with /webprogg/...)
    // in the database instead of a relative one. A relative
    // path like "uploads/host_ids/x.jpg" only resolves
    // correctly when viewed from a page in the same folder
    // as becomeahost.php — it breaks the moment it's
    // rendered from a different directory, like
    // /webprogg/admin/hostapplicationeye.php, because the
    // browser resolves relative src attributes against the
    // CURRENT page's URL, not the upload script's location.
    // =================================================

    $uploadDirFilesystem = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/host_ids/';
    $uploadDirWeb        = '/webprogg/uploads/host_ids/';

    if (!is_dir($uploadDirFilesystem)) {
        mkdir($uploadDirFilesystem, 0755, true);
    }

    $fileExtension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $newFileName = "host_" . time() . "_" . uniqid() . "." . $fileExtension;

    $uploadPath = $uploadDirFilesystem . $newFileName; // filesystem write target
    $webPath    = $uploadDirWeb . $newFileName;         // what gets stored in the DB / used in <img src>

    move_uploaded_file($file["tmp_name"], $uploadPath);

    // =====================================================
    // FIND EXISTING APPLICATION FOR THIS USER, IF ANY
    // -----------------------------------------------------
    // Step 2's "BACK" link sends the user right back to this
    // form. If they fill it out and hit "Next step" again,
    // this handler runs a second time — without this check
    // that used to mean a second INSERT and a duplicate row
    // in the admin's Host Applications list for the same
    // person. Reuse the existing row (session first, DB as a
    // fallback for a lost session) instead of inserting again.
    //
    // Fallback also matches 'approved' now, not just
    // 'pending' — once an application is approved, the old
    // pending-only lookup stopped finding it, so a second
    // submission (e.g. via "Add Another Space" -> BACK) would
    // insert a brand new duplicate row instead of updating
    // the existing approved one.
    // =====================================================

    $existingApplicationId = $_SESSION['host_application_id'] ?? null;

    if ($existingApplicationId === null) {

        $existingStmt = $pdo->prepare(
            "SELECT id, id_file FROM host_applications
             WHERE user_id = :user_id AND status IN ('pending', 'approved')
             ORDER BY id DESC
             LIMIT 1"
        );
        $existingStmt->execute(['user_id' => $_SESSION['user_id']]);
        $existingRow = $existingStmt->fetch();

        if ($existingRow) {
            $existingApplicationId = $existingRow['id'];
        }

    } else {

        $existingStmt = $pdo->prepare(
            "SELECT id_file FROM host_applications WHERE id = :id LIMIT 1"
        );
        $existingStmt->execute(['id' => $existingApplicationId]);
        $existingRow = $existingStmt->fetch();

    }

    if ($existingApplicationId !== null && $existingRow) {

        // Update the application already on file instead of
        // creating a duplicate.
        $stmt = $pdo->prepare(
            "UPDATE host_applications
    SET full_name = :full_name,
        email = :email,
        phone = :phone,
        age = :age,
        location = :location,
        id_type = :id_type,
        id_number = :id_number,
        id_file = :id_file,
        status = CASE
                    WHEN status = 'rejected' THEN 'pending'
                    ELSE status
                 END,
        updated_at = NOW()
 WHERE id = :id"
        );

        $stmt->execute([
            'full_name'  => $fullName,
            'email'      => $email,
            'phone'      => $phone,
            'age'        => (int) $age,
            'location'   => $location,
            'id_type'    => $idType,
            'id_number'  => $idNumber,
            'id_file'    => $webPath,
            'id'         => $existingApplicationId,
        ]);

        // Old ID image has been replaced — remove it so
        // uploads/host_ids/ doesn't accumulate orphaned files.
        // id_file in the DB is a WEB path, so translate it back
        // to a filesystem path before checking/deleting it.
        if (!empty($existingRow['id_file']) && $existingRow['id_file'] !== $webPath) {
            $oldFilesystemPath = $_SERVER['DOCUMENT_ROOT'] . $existingRow['id_file'];
            if (file_exists($oldFilesystemPath)) {
                unlink($oldFilesystemPath);
            }
        }

        $_SESSION['host_application_id'] = $existingApplicationId;

    } else {

        // No application on record yet for this user — first
        // time through step 1, so insert a new row. This is
        // what host-step2.php needs as host_application_id
        // when it creates the listing row.
        $stmt = $pdo->prepare(
            "INSERT INTO host_applications
                (user_id, full_name, email, phone, age, location, id_type, id_number, id_file, status)
             VALUES
                (:user_id, :full_name, :email, :phone, :age, :location, :id_type, :id_number, :id_file, 'pending')"
        );

        $stmt->execute([
            'user_id'    => $_SESSION['user_id'],
            'full_name'  => $fullName,
            'email'      => $email,
            'phone'      => $phone,
            'age'        => (int) $age,
            'location'   => $location,
            'id_type'    => $idType,
            'id_number'  => $idNumber,
            'id_file'    => $webPath,
        ]);

        $_SESSION['host_application_id'] = $pdo->lastInsertId();

    }

    $_SESSION["host_application"] = [
        "full_name" => $fullName,
        "email"     => $email,
        "phone"     => $phone,
        "age"       => (int) $age,
        "location"  => $location,
        "id_type"   => $idType,
        "id_number" => $idNumber,
        "id_file"   => $webPath,
    ];

    // Step 1 done — on to "Add Your Space".
    header("Location: /webprogg/host/host-step2.php");
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
        RoomHive - Become a Host
    </title>


    <!-- =====================================================
         POPPINS FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         MAIN CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >


    <!-- =====================================================
         BECOME-A-HOST ENHANCEMENT LAYER
         Everything below is scoped to .host-page-v2 so it
         layers on top of the site-wide stylesheet instead of
         fighting it. It upgrades the step tracker, the form
         card, live validation states and the ID upload box
         without touching any shared component elsewhere.
    ====================================================== -->

    <style>

        .host-page-v2 {
            --hive-amber: #ecac3a;
            --hive-amber-dark: #cf8f1f;
            --hive-ink: #1c1d22;
            --hive-ink-soft: #565a66;
            --hive-cream: #fffaf1;
            --hive-line: #e9e2d3;
            --hive-good: #2f9e5b;
            --hive-bad: #e0524d;
            --hive-shadow: 0 18px 40px -22px rgba(28, 29, 34, 0.35);
        }

        /* ---------- entrance (one orchestrated reveal, not scattered) ---------- */

        @keyframes hiveRise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .host-page-v2 * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }

        .host-page-v2 .host-header,
        .host-page-v2 .host-form-card {
            animation: hiveRise 0.5s ease both;
        }

        .host-page-v2 .host-form-card { animation-delay: 0.08s; }

        /* ---------- step tracker: show exactly where the applicant stands ---------- */

        .host-page-v2 .host-steps { position: relative; }

        .host-page-v2 .host-step {
            transition: transform 0.25s ease;
        }

        .host-page-v2 .host-step[data-state="current"] .step-circle {
            box-shadow: 0 0 0 5px rgba(236, 172, 58, 0.28), var(--hive-shadow);
            transform: translateY(-2px) scale(1.04);
        }

        .host-page-v2 .host-step[data-state="current"] .step-number {
            background: var(--hive-amber);
            color: var(--hive-ink);
        }

        .host-page-v2 .host-step[data-state="upcoming"] {
            opacity: 0.55;
        }

        .host-page-v2 .step-tag {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
            border-radius: 999px;
            background: rgba(236, 172, 58, 0.16);
            color: var(--hive-amber-dark);
        }

        /* ---------- progress bar: how much of the form is filled in ---------- */

        .host-progress {
            max-width: 760px;
            margin: 28px auto 0;
            padding: 0 4px;
        }

        .host-progress-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 13px;
            color: var(--hive-ink-soft);
            margin-bottom: 8px;
        }

        .host-progress-top strong {
            color: var(--hive-ink);
            font-weight: 600;
        }

        .host-progress-track {
            height: 8px;
            border-radius: 999px;
            background: var(--hive-line);
            overflow: hidden;
        }

        .host-progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--hive-amber-dark), var(--hive-amber));
            transition: width 0.35s cubic-bezier(.4,0,.2,1);
        }

        /* ---------- error banner ---------- */

        .host-page-v2 .form-errors {
            max-width: 760px;
            margin: 24px auto 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: 1px solid #f3c9c7;
            background: #fdf1f0;
            border-radius: 14px;
            padding: 16px 18px;
            animation: hiveRise 0.35s ease both;
        }

        .host-page-v2 .form-errors::before {
            content: "We need a couple of fixes before continuing";
            font-weight: 600;
            color: var(--hive-bad);
            margin-bottom: 2px;
        }

        .host-page-v2 .form-errors p {
            margin: 0;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
            color: #7a2f2c;
        }

        .host-page-v2 .form-errors p::before {
            content: "!";
            position: absolute;
            left: 0;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--hive-bad);
            color: #fff;
            font-size: 10px;
            line-height: 14px;
            text-align: center;
            font-weight: 700;
        }

        /* ---------- form card + fields ---------- */

        .host-page-v2 .host-form-card {
            max-width: 760px;
            box-shadow: var(--hive-shadow);
            border: 1px solid var(--hive-line);
        }

        .host-page-v2 .input-group {
            position: relative;
        }

        .host-page-v2 .input-group label {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .host-page-v2 .input-group input,
        .host-page-v2 .input-group select {
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .host-page-v2 .input-group input:focus,
        .host-page-v2 .input-group select:focus {
            outline: none;
            border-color: var(--hive-amber-dark) !important;
            box-shadow: 0 0 0 4px rgba(236, 172, 58, 0.18);
        }

        .field-hint {
            display: block;
            min-height: 16px;
            margin-top: 5px;
            font-size: 11.5px;
            color: var(--hive-ink-soft);
            transition: color 0.15s ease;
        }

        .input-group.is-valid input,
        .input-group.is-valid select {
            border-color: var(--hive-good) !important;
            background-image: none;
        }

        .input-group.is-valid .field-hint {
            color: var(--hive-good);
            font-weight: 500;
        }

        .input-group.is-invalid input,
        .input-group.is-invalid select {
            border-color: var(--hive-bad) !important;
        }

        .input-group.is-invalid .field-hint {
            color: var(--hive-bad);
            font-weight: 500;
        }

        @keyframes hiveShake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .host-page-v2 .host-form-card.shake { animation: hiveShake 0.4s; }

        /* ---------- upload box ---------- */

        .upload-box {
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.15s ease;
        }

        .upload-box.drag-over {
            border-color: var(--hive-amber-dark) !important;
            background-color: rgba(236, 172, 58, 0.08);
            transform: scale(1.005);
        }

        /* Larger preview so the ID is actually legible once uploaded.
           Only grows the box in its "has-image" state — the empty
           click-to-upload state keeps its normal size. */
        .upload-box.has-image {
            min-height: 320px;
            background-color: #111418;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain; /* show the whole ID, not a cropped slice */
        }

        .upload-box.has-image .upload-icon,
        .upload-box.has-image .upload-text {
            display: none;
        }

        .upload-box-overlay {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            padding: 10px 12px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.65), rgba(0, 0, 0, 0));
            animation: hiveRise 0.3s ease both;
        }

        .upload-box-overlay strong {
            max-width: 90%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
        }

        .upload-box-overlay span {
            font-size: 12px;
            opacity: 0.85;
        }

        .upload-box-overlay .upload-check {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: #9ee6b5;
            margin-top: 2px;
        }

        .upload-box .upload-remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
            border: none;
            background: rgba(0, 0, 0, 0.6);
            color: #ffffff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }

        .upload-box .upload-remove-btn:hover {
            background: var(--hive-bad);
            transform: scale(1.08);
        }

        .upload-error {
            display: none;
            margin-top: 8px;
            font-size: 12.5px;
            color: var(--hive-bad);
            font-weight: 500;
        }

        .upload-error.show { display: block; }

        @media (max-width: 600px) {
            .upload-box.has-image {
                min-height: 220px;
            }
        }

        /* ---------- submit button ---------- */

        .host-page-v2 .next-button {
            position: relative;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }

        .host-page-v2 .next-button:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -10px rgba(236, 172, 58, 0.55);
        }

        .host-page-v2 .next-button:disabled {
            opacity: 0.75;
            cursor: progress;
        }

        .btn-spinner {
            display: none;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            margin-right: 8px;
            vertical-align: -2px;
            animation: hiveSpin 0.7s linear infinite;
        }

        @keyframes hiveSpin { to { transform: rotate(360deg); } }

        .next-button.is-loading .btn-spinner { display: inline-block; }

    </style>

</head>


<body>


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

<main class="host-page host-page-v2">


    <!-- =====================================================
         HOST HEADER
    ====================================================== -->

    <section class="host-header">


        <span class="host-eyebrow">

            BECOME A HOST

        </span>


        <h1>

            Start Hosting in 4 Easy Steps

        </h1>



        <!-- =================================================
             HOST STEPS
        ================================================== -->

        <div class="host-steps">


            <?php foreach ($hostSteps as $index => $step): ?>

                <?php
                    $stepState = $step["number"] === $currentHostStep
                        ? "current"
                        : ($step["number"] < $currentHostStep ? "done" : "upcoming");
                ?>

                <div class="host-step" data-state="<?php echo $stepState; ?>">


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

                    <?php if ($stepState === "current"): ?>
                        <span class="step-tag">You're here</span>
                    <?php endif; ?>


                </div>

            <?php endforeach; ?>


        </div>


        <!-- =================================================
             FORM COMPLETION PROGRESS
             Updated live by JS as required fields are filled.
        ================================================== -->

        <div class="host-progress">
            <div class="host-progress-top">
                <span>Application details</span>
                <span><strong id="progressCount">0</strong> of <span id="progressTotal">8</span> fields complete</span>
            </div>
            <div class="host-progress-track">
                <div class="host-progress-fill" id="progressFill"></div>
            </div>
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
         HOST FORM
    ====================================================== -->

    <section class="host-form-card">


        <!-- FORM HEADING -->

        <div class="form-heading">

            <h2>

                Tell Us About You

            </h2>


            <p>

                Let's get to know you first.

            </p>

        </div>



        <!-- FORM -->

        <form
            action="/webprogg/host/becomeahost.php"
            method="POST"
            enctype="multipart/form-data"
            id="hostForm"
            novalidate
        >


            <!-- =================================================
                 FIRST ROW
            ================================================== -->

            <div class="form-grid three-columns">


                <!-- FULL NAME -->

                <div class="input-group" data-field="full_name">

                    <label for="full_name">

                        Full Name

                    </label>


                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($_POST["full_name"] ?? $_SESSION["user_name"] ?? ""); ?>"
                        aria-describedby="hint_full_name"
                        required
                    >
                    <small class="field-hint" id="hint_full_name"></small>

                </div>



                <!-- EMAIL -->

                <div class="input-group" data-field="email">

                    <label for="email">

                        Email Address

                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($_POST["email"] ?? $_SESSION["user_email"] ?? ""); ?>"
                        aria-describedby="hint_email"
                        required
                    >
                    <small class="field-hint" id="hint_email"></small>

                </div>



                <!-- PHONE -->

                <div class="input-group" data-field="phone">

                    <label for="phone">

                        Phone Number

                    </label>


                   <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Enter 11-digit phone number"
                 value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>"
                 pattern="[0-9]{11}"
                 maxlength="11"
                minlength="11"
                 inputmode="numeric"
                 aria-describedby="hint_phone"
                required
                    >
                    <small class="field-hint" id="hint_phone">e.g. 09171234567</small>

                </div>



                <!-- AGE -->

                <div class="input-group" data-field="age">

                    <label for="age">

                        Age

                    </label>


                    <input
                        type="number"
                        id="age"
                        name="age"
                        placeholder="Enter your age"
                        min="18"
                        max="120"
                        value="<?php echo htmlspecialchars($_POST["age"] ?? ""); ?>"
                        aria-describedby="hint_age"
                        required
                    >

                    <small class="field-hint" id="hint_age">You must be 18 or older to become a host.</small>

                </div>

            </div>



            <!-- =================================================
                 SECOND ROW
            ================================================== -->

            <div class="form-grid three-columns">


                <!-- LOCATION -->

                <div class="input-group" data-field="location">

                    <label for="location">

                        Location

                    </label>


                    <div class="input-with-icon">

                        <input
                            type="text"
                            id="location"
                            name="location"
                            placeholder="City, Province"
                            value="<?php echo htmlspecialchars($_POST["location"] ?? ""); ?>"
                            aria-describedby="hint_location"
                            required
                        >


                        <span>

                            ◉

                        </span>

                    </div>
                    <small class="field-hint" id="hint_location"></small>

                </div>



                <!-- ID TYPE -->

                <div class="input-group" data-field="id_type">

                    <label for="id_type">

                        ID Type

                    </label>


                    <select
                        id="id_type"
                        name="id_type"
                        aria-describedby="hint_id_type"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?php echo empty($_POST["id_type"]) ? "selected" : ""; ?>
                        >

                            Select ID Type

                        </option>


                        <option
                            value="passport"
                            <?php echo (($_POST["id_type"] ?? "") === "passport") ? "selected" : ""; ?>
                        >

                            Passport

                        </option>


                        <option
                            value="drivers-license"
                            <?php echo (($_POST["id_type"] ?? "") === "drivers-license") ? "selected" : ""; ?>
                        >

                            Driver's License

                        </option>


                        <option
                            value="national-id"
                            <?php echo (($_POST["id_type"] ?? "") === "national-id") ? "selected" : ""; ?>
                        >

                            National ID

                        </option>


                        <option
                            value="sss"
                            <?php echo (($_POST["id_type"] ?? "") === "sss") ? "selected" : ""; ?>
                        >

                            SSS ID

                        </option>


                        <option
                            value="philhealth"
                            <?php echo (($_POST["id_type"] ?? "") === "philhealth") ? "selected" : ""; ?>
                        >

                            PhilHealth ID

                        </option>

                    </select>
                    <small class="field-hint" id="hint_id_type"></small>

                </div>



                <!-- ID NUMBER -->

                <div class="input-group" data-field="id_number">

                    <label for="id_number">

                        ID Number

                    </label>


                    <input
                        type="text"
                        id="id_number"
                        name="id_number"
                        placeholder="Enter ID Number"
                        value="<?php echo htmlspecialchars($_POST["id_number"] ?? ""); ?>"
                        aria-describedby="hint_id_number"
                        required
                    >
                    <small class="field-hint" id="hint_id_number"></small>

                </div>

            </div>


<!-- =================================================
     UPLOAD ID
     The preview (image + filename + remove button) is
     rendered directly inside #uploadBox by the script
     at the bottom of this file — no separate preview
     element needed anymore. Drag-and-drop is supported
     in addition to the click-to-browse label.
================================================== -->

<div class="upload-section" data-field="upload_id">

    <label>
        Upload ID
    </label>

    <label
        for="upload_id"
        class="upload-box"
        id="uploadBox"
    >
        <div class="upload-icon">
            ☁
        </div>

        <div class="upload-text">
            <strong>
                Click to upload your ID
            </strong>

            <span>
                or drag and drop &middot; JPG, PNG &middot; Max 5MB
            </span>
        </div>
    </label>

    <input
        type="file"
        id="upload_id"
        name="upload_id"
        accept=".jpg,.jpeg,.png"
        hidden
        required
    >

    <small class="upload-error" id="uploadError"></small>

</div>



            <!-- =================================================
                 FORM BUTTON
            ================================================== -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="next-button"
                    id="submitBtn"
                >
                    <span class="btn-spinner"></span>
                    <span class="btn-label">NEXT STEP</span>

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



        <!-- =================================================
             LISTINGS
        ================================================== -->

        <div class="footer-links">


            <span class="footer-heading">

                LISTINGS

            </span>


            <?php foreach ($listingCategories as $category => $type): ?>

           <a                
                    href="/webprogg/Listings/listing.php?type=<?php echo urlencode($type); ?>"
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

                <a                    href="<?php echo htmlspecialchars($link); ?>"
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
                    src="/webprogg/images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >


                <img
                    src="/webprogg/images/AppStore.jpg"
                    alt="Download on the App Store"
                >


            </div>



            <!-- PHONE -->

            <div class="footer-contact-line">


                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt="Phone"
                >


                <span>

                    +63 927 569 3574

                </span>


            </div>



            <!-- EMAIL -->

            <div class="footer-contact-line">


                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt="Email"
                >


                <span>

                    hello@roomhive.ph

                </span>


            </div>



            <!-- LOCATION -->

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

<script>
(function () {
    "use strict";

    /* =====================================================
       SHARED STATE
    ====================================================== */

    const form        = document.getElementById('hostForm');
    const submitBtn    = document.getElementById('submitBtn');
    const fileInput    = document.getElementById('upload_id');
    const uploadBox     = document.getElementById('uploadBox');
    const uploadError   = document.getElementById('uploadError');
    const progressFill  = document.getElementById('progressFill');
    const progressCount = document.getElementById('progressCount');

    const REQUIRED_FIELDS = ['full_name', 'email', 'phone', 'age', 'location', 'id_type', 'id_number'];
    const TOTAL_TRACKED    = REQUIRED_FIELDS.length + 1; // +1 for the ID upload

    /* =====================================================
       PER-FIELD VALIDATORS
       Each returns "" when valid, or a short message to
       show under the field when it isn't.
    ====================================================== */

    const validators = {

        full_name(value) {
            if (!value.trim()) return "Full name is required.";
            if (value.trim().length < 2) return "That name looks too short.";
            return "";
        },

        email(value) {
            if (!value.trim()) return "Email is required.";
            const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
            return ok ? "" : "Enter a valid email address.";
        },

        phone(value) {
            const digits = value.replace(/\D/g, '');
            if (!digits) return "Phone number is required.";
            if (digits.length !== 11) return `${digits.length}/11 digits entered.`;
            return "";
        },

        age(value) {
            if (value === "") return "Age is required.";
            const n = Number(value);
            if (!Number.isInteger(n)) return "Enter a whole number.";
            if (n < 18) return "You must be at least 18 to host.";
            if (n > 120) return "Enter a valid age.";
            return "";
        },

        location(value) {
            if (!value.trim()) return "Location is required.";
            return "";
        },

        id_type(value) {
            return value ? "" : "Select an ID type.";
        },

        id_number(value) {
            if (!value.trim()) return "ID number is required.";
            if (value.trim().length < 4) return "That ID number looks too short.";
            return "";
        }

    };

    const successHints = {
        full_name: "Looks good.",
        email: "Valid email.",
        phone: "Valid Philippine mobile number.",
        age: "You're eligible to host.",
        location: "Got it.",
        id_type: "ID type selected.",
        id_number: "Got it."
    };

    /* =====================================================
       VALIDATE ONE FIELD + UPDATE ITS UI
    ====================================================== */

    function validateField(name, { silent = false } = {}) {

        const input = form.elements[name];
        const group = form.querySelector(`.input-group[data-field="${name}"]`);
        if (!input || !group) return true;

        const hint  = group.querySelector('.field-hint');
        const value = input.value;
        const error = validators[name] ? validators[name](value) : "";

        // Don't shout "required" at someone who hasn't touched the field yet.
        if (silent && value.trim() === "" && !group.dataset.touched) {
            group.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        if (error) {
            group.classList.add('is-invalid');
            group.classList.remove('is-valid');
            if (hint) hint.textContent = error;
        } else {
            group.classList.remove('is-invalid');
            group.classList.add('is-valid');
            if (hint) hint.textContent = successHints[name] || "";
        }

        return !error;
    }

    /* =====================================================
       PHONE FORMATTING — digits only, capped at 11
    ====================================================== */

    const phoneInput = form.elements['phone'];
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const digits = phoneInput.value.replace(/\D/g, '').slice(0, 11);
            phoneInput.value = digits;
        });
    }

    /* =====================================================
       AGE — block non-numeric keystrokes beyond the browser default
    ====================================================== */

    const ageInput = form.elements['age'];
    if (ageInput) {
        ageInput.addEventListener('input', function () {
            ageInput.value = ageInput.value.replace(/[^\d]/g, '').slice(0, 3);
        });
    }

    /* =====================================================
       WIRE UP LIVE VALIDATION + PROGRESS METER
    ====================================================== */

    REQUIRED_FIELDS.forEach((name) => {
        const input = form.elements[name];
        if (!input) return;

        const group = form.querySelector(`.input-group[data-field="${name}"]`);

        const onInteract = () => {
            if (group) group.dataset.touched = "1";
            validateField(name);
            updateProgress();
        };

        input.addEventListener('blur', onInteract);
        input.addEventListener('input', () => { validateField(name, { silent: true }); updateProgress(); });
        input.addEventListener('change', onInteract);
    });

    function updateProgress() {
        let complete = 0;

        REQUIRED_FIELDS.forEach((name) => {
            const input = form.elements[name];
            if (!input) return;
            const error = validators[name] ? validators[name](input.value) : (input.value ? "" : "missing");
            if (!error) complete += 1;
        });

        if (fileInput && fileInput.files && fileInput.files.length > 0) complete += 1;

        const pct = Math.round((complete / TOTAL_TRACKED) * 100);
        progressFill.style.width = pct + '%';
        progressCount.textContent = complete;
    }

    /* =====================================================
       ID UPLOAD — drag & drop, preview, remove, validation
    ====================================================== */

    function formatSize(bytes) {
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function showUploadError(message) {
        uploadError.textContent = message;
        uploadError.classList.add('show');
    }

    function clearUploadError() {
        uploadError.textContent = '';
        uploadError.classList.remove('show');
    }

    function isAcceptableFile(file) {
        const allowedTypes = ['image/jpeg', 'image/png'];
        const maxSize = 5 * 1024 * 1024;

        if (!allowedTypes.includes(file.type)) {
            showUploadError('Only JPG and PNG files are allowed.');
            return false;
        }
        if (file.size > maxSize) {
            showUploadError('ID file must not exceed 5MB.');
            return false;
        }
        clearUploadError();
        return true;
    }

    function showPreview(file) {
        const imageUrl = URL.createObjectURL(file);

        uploadBox.style.backgroundImage = `url(${imageUrl})`;
        uploadBox.classList.add('has-image');

        uploadBox.innerHTML = `
            <button type="button" class="upload-remove-btn" id="uploadRemoveBtn" aria-label="Remove file">&times;</button>
            <div class="upload-box-overlay">
                <strong>${file.name}</strong>
                <span>${formatSize(file.size)}</span>
                <span class="upload-check">✓ Ready to submit</span>
            </div>
        `;

        document.getElementById('uploadRemoveBtn').addEventListener('click', function (event) {
            /* uploadBox is a <label for="upload_id">, so any click inside
               it — including this button — would otherwise re-open the
               file picker. Stop that before resetting. */
            event.preventDefault();
            event.stopPropagation();
            resetBox();
        });
    }

    function resetBox() {
        fileInput.value = '';
        uploadBox.style.backgroundImage = '';
        uploadBox.classList.remove('has-image');
        uploadBox.innerHTML = `
            <div class="upload-icon">☁</div>
            <div class="upload-text">
                <strong>Click to upload your ID</strong>
                <span>or drag and drop &middot; JPG, PNG &middot; Max 5MB</span>
            </div>
        `;
        updateProgress();
    }

    function handleIncomingFile(file) {
        if (!file) return;
        if (!isAcceptableFile(file)) {
            resetBox();
            return;
        }
        showPreview(file);
        updateProgress();
    }

    fileInput.addEventListener('change', function () {
        handleIncomingFile(fileInput.files[0]);
    });

    ['dragenter', 'dragover'].forEach((evt) => {
        uploadBox.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            uploadBox.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach((evt) => {
        uploadBox.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            uploadBox.classList.remove('drag-over');
        });
    });

    uploadBox.addEventListener('drop', function (e) {
        const dropped = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (!dropped) return;

        // Reflect the dropped file back onto the real <input type="file">
        // so the existing form submission still works unchanged.
        const dt = new DataTransfer();
        dt.items.add(dropped);
        fileInput.files = dt.files;

        handleIncomingFile(dropped);
    });

    /* =====================================================
       SUBMIT — validate everything, focus the first problem,
       shake the card if something's wrong, otherwise show a
       loading state while the normal POST happens.
    ====================================================== */

    const hostFormCard = document.querySelector('.host-form-card');

    form.addEventListener('submit', function (e) {

        let firstInvalid = null;

        REQUIRED_FIELDS.forEach((name) => {
            const group = form.querySelector(`.input-group[data-field="${name}"]`);
            if (group) group.dataset.touched = "1";
            const ok = validateField(name);
            if (!ok && !firstInvalid) firstInvalid = form.elements[name];
        });

        if (!fileInput.files || fileInput.files.length === 0) {
            showUploadError('Please upload your ID.');
            if (!firstInvalid) firstInvalid = fileInput;
        } else if (!isAcceptableFile(fileInput.files[0])) {
            if (!firstInvalid) firstInvalid = fileInput;
        }

        updateProgress();

        if (firstInvalid) {
            e.preventDefault();
            hostFormCard.classList.remove('shake');
            // restart the animation
            void hostFormCard.offsetWidth;
            hostFormCard.classList.add('shake');
            firstInvalid.focus({ preventScroll: false });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-label').textContent = 'SAVING…';
        // form submits normally — PHP does the real work server-side.
    });

    /* Initial progress read, e.g. after a validation-error reload that
       re-populates values from $_POST. */
    updateProgress();

})();
</script>

<script src="/webprogg/assets/javaScript.js"></script>

</body>

</html>