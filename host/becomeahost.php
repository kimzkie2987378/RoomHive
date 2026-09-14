<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 1)
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
         BECOME-A-HOST ENHANCEMENT LAYER (STEP 1)
         Scoped to .host-page-v2 so it layers on top of
         style.css. CHANGED: adds the site-wide honeycomb
         hero texture, shimmer title, balanced form grids
         (2 + 2 + 3 instead of a 3 + 1 orphan), a trust note
         under the ID upload, gradient CTA with shine sweep,
         and a refined reduced-motion block that no longer
         kills the loading spinner.
    ====================================================== -->

    <style>

        .host-page-v2 {
            --hive-amber: #ecac3a;
            --hive-amber-dark: #cf8f1f;
            --hive-honey: #eda423;
            --hive-honey-light: #f6c04e;
            --hive-ink: #1c1d22;
            --hive-ink-soft: #565a66;
            --hive-cream: #fffaf1;
            --hive-line: #e9e2d3;
            --hive-good: #2f9e5b;
            --hive-bad: #e0524d;
            --hive-shadow: 0 18px 40px -22px rgba(28, 29, 34, 0.35);

            position: relative;
        }

        /* ---------- NEW: honeycomb texture + glow blobs ---------- */

        .host-page-v2::before {
            content: "";

            position: absolute;
            inset: 0;

            background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.07' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 49px;

            -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 55%);
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 55%);

            pointer-events: none;

            z-index: 0;
        }

        .host-page-v2 > * {
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

            top: -140px;
            right: -120px;

            background: radial-gradient(circle at 30% 30%, rgba(246, 196, 78, 0.8), rgba(237, 164, 35, 0.22) 60%, transparent 75%);

            animation: hiveDrift 14s ease-in-out infinite alternate;
        }

        .hive-blob-2 {
            width: 260px;
            height: 260px;

            top: 420px;
            left: -140px;

            background: radial-gradient(circle at 60% 40%, rgba(246, 196, 78, 0.6), rgba(237, 164, 35, 0.18) 60%, transparent 75%);

            animation: hiveDrift 18s ease-in-out infinite alternate-reverse;
        }

        /* ---------- entrance (one orchestrated reveal) ---------- */

        @keyframes hiveRise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .host-page-v2 .host-header,
        .js .host-page-v2 .host-form-card {
            animation: hiveRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .js .host-page-v2 .host-form-card { animation-delay: 0.08s; }

        /* ---------- NEW: header polish ---------- */

        .host-page-v2 .host-header {
            text-align: center;
        }

        .host-page-v2 .hero-badge {
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

        .host-page-v2 .host-header h1 {
            position: relative;

            display: inline-block;
        }

        .host-page-v2 .host-header h1::after {
            content: "";

            position: absolute;

            width: 64px;
            height: 4px;

            left: 50%;
            bottom: -14px;
            margin-left: -32px;

            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));

            border-radius: 2px;
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

        /* ---------- step tracker ---------- */

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

        /* ---------- progress bar ---------- */

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
            margin: 36px auto 0;
            box-shadow: var(--hive-shadow);
            border: 1px solid var(--hive-line);
            border-radius: 18px;
        }

        .host-page-v2 .form-heading h2 {
            position: relative;

            display: inline-block;
        }

        .host-page-v2 .form-heading h2::after {
            content: "";

            position: absolute;

            width: 42px;
            height: 3px;

            left: 0;
            bottom: -8px;

            background: linear-gradient(90deg, #f6b93b, var(--hive-honey));

            border-radius: 2px;
        }

        .host-page-v2 .form-heading p {
            margin-top: 18px;
        }

        /* NEW: explicit grid definitions — .three-columns was
           previously only defined scoped to step 2's .rh-step2,
           so step 1's grids could silently collapse. */

        .host-page-v2 .form-grid {
            display: grid;
            gap: 18px 20px;
            margin-bottom: 22px;
        }

        .host-page-v2 .form-grid .input-group {
            margin-bottom: 0;
        }

        .host-page-v2 .form-grid.two-columns {
            grid-template-columns: repeat(2, 1fr);
        }

        .host-page-v2 .form-grid.three-columns {
            grid-template-columns: repeat(3, 1fr);
        }

        @media (max-width: 720px) {
            .host-page-v2 .form-grid.two-columns,
            .host-page-v2 .form-grid.three-columns {
                grid-template-columns: 1fr;
            }
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

        .host-page-v2 .upload-box {
            border: 2px dashed #e2d4b6;

            border-radius: 16px;

            background: linear-gradient(160deg, #fffdf6 0%, #fdf3e0 100%);

            cursor: pointer;

            transition:
                border-color 0.2s ease,
                background-color 0.2s ease,
                box-shadow 0.25s ease,
                transform 0.15s ease;
        }

        .host-page-v2 .upload-box:hover {
            border-color: var(--hive-honey);

            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.16);

            transform: translateY(-2px);
        }

        .host-page-v2 .upload-box .upload-icon {
            color: var(--hive-honey);
            font-size: 32px;

            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .host-page-v2 .upload-box:hover .upload-icon {
            transform: translateY(-4px) scale(1.12);
        }

        .host-page-v2 .upload-box .upload-text strong {
            color: var(--hive-ink);
        }

        .host-page-v2 .upload-box .upload-text span {
            color: var(--hive-ink-soft);
        }

        .upload-box {
            position: relative;
            overflow: hidden;
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

        /* NEW: trust note under the ID upload */

        .upload-secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            margin-top: 10px;

            color: var(--hive-ink-soft);

            font-size: 12px;
        }

        .upload-secure-note svg {
            width: 14px;
            height: 14px;

            color: var(--hive-good);
            flex-shrink: 0;
        }

        @media (max-width: 600px) {
            .upload-box.has-image {
                min-height: 220px;
            }
        }

        /* ---------- submit button ---------- */

        .host-page-v2 .next-button {
            position: relative;
            overflow: hidden;

            background: linear-gradient(135deg, var(--hive-honey-light), var(--hive-honey)) !important;

            border: none !important;

            color: var(--hive-ink) !important;

            font-weight: 700;

            box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35);

            transition:
                transform 0.15s ease,
                box-shadow 0.2s ease,
                opacity 0.15s ease !important;
        }

        .host-page-v2 .next-button:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45);
        }

        .host-page-v2 .next-button:not(:disabled):active {
            transform: translateY(0) scale(0.98);
        }

        .host-page-v2 .next-button:disabled {
            opacity: 0.75;
            cursor: progress;
        }

        /* NEW: infinite shine sweep, matching steps 3 & 4 */

        .host-page-v2 .next-button::after {
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

        .btn-spinner {
            display: none;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 2px solid rgba(28, 29, 34, 0.25);
            border-top-color: var(--hive-ink);
            margin-right: 8px;
            vertical-align: -2px;
            animation: hiveSpin 0.7s linear infinite;
        }

        @keyframes hiveSpin { to { transform: rotate(360deg); } }

        .next-button.is-loading .btn-spinner { display: inline-block; }

        /* ---------- NEW: keyframes for the added decoration ---------- */

        @keyframes hiveDrift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, -24px) scale(1.08); }
        }

        @keyframes hivePing {
            0%   { transform: scale(1); opacity: 0.7; }
            80%, 100% { transform: scale(2.6); opacity: 0; }
        }

        @keyframes hiveShimmer {
            to { background-position: 200% center; }
        }

        /* ---------- reduced motion (FIX: targeted, so the
           loading spinner still spins for users who need it
           to know the form is submitting) ---------- */

        @media (prefers-reduced-motion: reduce) {
            .hive-blob,
            .hive-pulse-dot::after,
            .hive-shimmer,
            .host-page-v2 .next-button::after,
            .host-page-v2 .upload-box .upload-icon {
                animation: none !important;

                opacity: 1 !important;
                transform: none !important;
            }

            .js .host-page-v2 .host-header,
            .js .host-page-v2 .host-form-card {
                animation: none !important;

                opacity: 1 !important;
                transform: none !important;
            }
        }

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

    <!-- NEW: decorative glow blobs (honeycomb lives on ::before) -->
    <span class="hive-blob hive-blob-1" aria-hidden="true"></span>
    <span class="hive-blob hive-blob-2" aria-hidden="true"></span>


    <!-- =====================================================
         HOST HEADER
    ====================================================== -->

    <section class="host-header">

        <!-- NEW: hero badge -->
        <span class="hero-badge">

            <span class="hive-pulse-dot"></span>

            Host Application &middot; Step 1 of 4

        </span>


        <span class="host-eyebrow" style="display:block; margin-top:18px;">

            BECOME A HOST

        </span>


        <h1>

            Start Hosting in

            <span class="hive-shimmer">4 Easy Steps</span>

        </h1>



        <!-- =================================================
             HOST STEPS
        ================================================== -->

        <div class="host-steps" style="margin-top:36px;">


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
                 ROW 1 — CHANGED: rebalanced grids. The old layout
                 put 4 fields in a 3-column grid, stranding "Age"
                 alone on a second line. Now 2 + 2 + 3, no orphans.
            ================================================== -->

            <div class="form-grid two-columns">


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

            </div>



            <!-- =================================================
                 ROW 2
            ================================================== -->

            <div class="form-grid two-columns">


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
                 ROW 3
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

                            &#9678;

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

<div class="upload-section" data-field="upload_id" style="margin-bottom:24px;">

    <label>
        Upload ID
    </label>

    <label
        for="upload_id"
        class="upload-box"
        id="uploadBox"
    >
        <div class="upload-icon">
            &#9729;
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

    <!-- NEW: trust reassurance -->
    <p class="upload-secure-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l7 3v6c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6z"/>
            <path d="m9 12 2 2 4-4"/>
        </svg>
        Your ID is only visible to the RoomHive admin team.
    </p>

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

                <a href="<?php echo htmlspecialchars($link); ?>"
                    class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>"
                >

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

<script>
(function () {
    "use strict";

    /* =====================================================
       SHARED STATE
    ====================================================== */

    const form         = document.getElementById('hostForm');
    const submitBtn    = document.getElementById('submitBtn');
    const fileInput    = document.getElementById('upload_id');
    const uploadBox    = document.getElementById('uploadBox');
    const uploadError  = document.getElementById('uploadError');
    const progressFill = document.getElementById('progressFill');
    const progressCount = document.getElementById('progressCount');
    const formCard     = form ? form.closest('.host-form-card') : null;

    const REQUIRED_FIELDS = ['full_name', 'email', 'phone', 'age', 'location', 'id_type', 'id_number'];
    const TOTAL_TRACKED   = REQUIRED_FIELDS.length + 1; // +1 for the ID upload

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
        phone: "Valid phone number.",
        age: "Looks good.",
        location: "Looks good.",
        id_type: "Selected.",
        id_number: "Looks good."
    };

    /* =====================================================
       LIVE FIELD VALIDATION
       Marks .is-valid / .is-invalid on the group and fills
       the hint. silent=true updates the progress bar only —
       used on load so PHP-repopulated fields aren't painted
       red before the user touches anything.
    ====================================================== */

    function validateField(name, silent) {
        const input = document.getElementById(name);
        const group = document.querySelector(`.input-group[data-field="${name}"]`);

        if (!input || !group) return false;

        const message = validators[name] ? validators[name](input.value) : "";
        const valid = message === "";

        if (!silent) {
            group.classList.toggle('is-valid', valid);
            group.classList.toggle('is-invalid', !valid);

            const hint = group.querySelector('.field-hint');
            if (hint) {
                hint.textContent = valid ? (successHints[name] || "") : message;
            }
        }

        return valid;
    }

    /* =====================================================
       PROGRESS BAR
    ====================================================== */

    function updateProgress() {
        let done = REQUIRED_FIELDS.filter((name) => validateField(name, true)).length;

        if (fileInput && fileInput.files.length > 0) {
            done += 1;
        }

        if (progressFill) {
            progressFill.style.width = `${Math.round((done / TOTAL_TRACKED) * 100)}%`;
        }

        if (progressCount) {
            progressCount.textContent = String(done);
        }
    }

    /* =====================================================
       ID UPLOAD — preview, remove, drag & drop
       Renders a bottom overlay (filename + size + check)
       and a floating remove button inside #uploadBox, and
       paints the preview as the box's background so the
       whole ID stays legible (.has-image uses contain).
    ====================================================== */

    const MAX_SIZE = 5 * 1024 * 1024;
    const ALLOWED_TYPES = ['image/jpeg', 'image/png'];

    let overlay = null;
    let removeBtn = null;

    function formatSize(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function clearUploadUI() {
        uploadBox.classList.remove('has-image');
        uploadBox.style.backgroundImage = '';

        if (overlay) { overlay.remove(); overlay = null; }
        if (removeBtn) { removeBtn.remove(); removeBtn = null; }

        if (uploadError) {
            uploadError.textContent = '';
            uploadError.classList.remove('show');
        }

        updateProgress();
    }

    function showUploadError(message) {
        if (!uploadError) return;

        uploadError.textContent = message;
        uploadError.classList.add('show');
    }

    function handleFile(file) {
        if (!file) {
            clearUploadUI();
            return;
        }

        if (!ALLOWED_TYPES.includes(file.type)) {
            clearUploadUI();
            fileInput.value = '';
            showUploadError('Only JPG and PNG files are allowed.');
            return;
        }

        if (file.size > MAX_SIZE) {
            clearUploadUI();
            fileInput.value = '';
            showUploadError('That file is too large. Maximum is 5MB.');
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            uploadBox.classList.add('has-image');
            uploadBox.style.backgroundImage = `url(${event.target.result})`;

            /* Rebuild the overlay + remove button each time */
            if (overlay) overlay.remove();
            if (removeBtn) removeBtn.remove();

            overlay = document.createElement('div');
            overlay.className = 'upload-box-overlay';

            const name = document.createElement('strong');
            name.textContent = file.name;

            const size = document.createElement('span');
            size.textContent = formatSize(file.size);

            const check = document.createElement('span');
            check.className = 'upload-check';
            check.textContent = 'Uploaded ✓';

            overlay.appendChild(name);
            overlay.appendChild(size);
            overlay.appendChild(check);

            removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'upload-remove-btn';
            removeBtn.setAttribute('aria-label', 'Remove uploaded ID');
            removeBtn.innerHTML = '&times;';

            removeBtn.addEventListener('click', function (e) {
                /* The box is a <label for="upload_id"> — without
                   preventDefault the click would reopen the picker. */
                e.preventDefault();
                e.stopPropagation();

                fileInput.value = '';
                clearUploadUI();
            });

            uploadBox.appendChild(overlay);
            uploadBox.appendChild(removeBtn);

            if (uploadError) {
                uploadError.textContent = '';
                uploadError.classList.remove('show');
            }

            updateProgress();
        };

        reader.readAsDataURL(file);
    }

    if (fileInput && uploadBox) {

        fileInput.addEventListener('change', function () {
            handleFile(fileInput.files && fileInput.files[0]);
        });

        /* ---- drag & drop ---- */

        ['dragenter', 'dragover'].forEach(function (eventName) {
            uploadBox.addEventListener(eventName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                uploadBox.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            uploadBox.addEventListener(eventName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                uploadBox.classList.remove('drag-over');
            });
        });

        uploadBox.addEventListener('drop', function (e) {
            const files = e.dataTransfer && e.dataTransfer.files;

            if (files && files.length > 0) {
                /* Route the dropped file through the same
                   input so it actually submits with the form. */
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    fileInput.files = transfer.files;
                } catch (err) {
                    /* Older browsers: fall back silently —
                       the change event below just won't fire. */
                }

                handleFile(fileInput.files && fileInput.files[0]);
            }
        });
    }

    /* =====================================================
       WIRE UP LIVE VALIDATION
    ====================================================== */

    REQUIRED_FIELDS.forEach(function (name) {
        const input = document.getElementById(name);

        if (!input) return;

        /* Phone: digits only, max 11 — matches the server's
           /^[0-9]{11}$/ rule and stops paste-mess early. */
        if (name === 'phone') {
            input.addEventListener('input', function () {
                const cleaned = input.value.replace(/\D/g, '').slice(0, 11);
                if (cleaned !== input.value) {
                    input.value = cleaned;
                }
            });
        }

        input.addEventListener('input', function () {
            validateField(name, false);
            updateProgress();
        });

        input.addEventListener('change', function () {
            validateField(name, false);
            updateProgress();
        });

        /* Selects clear :invalid styling as soon as a real
           option is picked, even before blur. */
        input.addEventListener('blur', function () {
            validateField(name, false);
        });
    });

    /* =====================================================
       SUBMIT — validate everything first; shake + focus the
       first bad field if anything is off. On success, flip
       the button into its loading state while the POST
       navigates to step 2.
    ====================================================== */

    if (form) {
        form.addEventListener('submit', function (e) {

            let firstInvalid = null;

            REQUIRED_FIELDS.forEach(function (name) {
                const valid = validateField(name, false);
                if (!valid && !firstInvalid) {
                    firstInvalid = document.getElementById(name);
                }
            });

            let fileOk = fileInput && fileInput.files.length > 0;

            if (!fileOk) {
                showUploadError('Please upload your ID.');
                if (!firstInvalid) firstInvalid = fileInput;
            } else if (uploadError) {
                uploadError.textContent = '';
                uploadError.classList.remove('show');
            }

            if (firstInvalid) {
                e.preventDefault();

                if (formCard) {
                    formCard.classList.remove('shake');
                    /* restart the animation if it's already run */
                    void formCard.offsetWidth;
                    formCard.classList.add('shake');
                }

                if (firstInvalid.focus) {
                    firstInvalid.focus();
                }

                if (firstInvalid.scrollIntoView) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');

                const label = submitBtn.querySelector('.btn-label');
                if (label) label.textContent = 'SUBMITTING...';
            }
        });
    }

    /* =====================================================
       INITIAL STATE
       Server-side errors re-render the page with values
       repopulated — compute progress silently so nothing
       is painted red before the user touches it.
    ====================================================== */

    updateProgress();

})();
</script>

</body>

</html>