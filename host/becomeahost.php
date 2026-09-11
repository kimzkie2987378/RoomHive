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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
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
         IN-BOX ID UPLOAD PREVIEW
         (image, filename, size and a remove button rendered
         directly inside #uploadBox, the same way the cover
         photo box on step 3 shows its own preview)
    ====================================================== -->

    <style>
        .upload-box {
            position: relative;
            overflow: hidden;
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
        }

        .upload-box .upload-remove-btn:hover {
            background: #e0524d;
        }

        @media (max-width: 600px) {
            .upload-box.has-image {
                min-height: 220px;
            }
        }
    </style>

</head>


<body>


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<header class="navbar">


    <!-- LOGO -->

<a    
        href="/webprogg/user/usershome.php"
        class="logo"
    >

        <img
            src="/webprogg/images/RoomHiveLogos.png"
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
            <div class="account-dropdown js-account-dropdown">

                <button
                    type="button"
                    class="my-account js-account-toggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span class="account-circle">
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu">

                    <a href="/webprogg/user/userprofile.php">
                        My Profile
                    </a>

                    <a href="/webprogg/auth/logout.php">
                        Logout
                    </a>

                </div>

            </div>

        <?php else: ?>

            <!-- LIST YOUR SPACE -->

<a            
                href="/webprogg/host/becomeahost.php"
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
<!-- The dropdown's open/close behavior now lives in javaScript.js
     (shared by every page instead of a copy-pasted inline script). -->
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

                <div class="host-step">


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
        >


            <!-- =================================================
                 FIRST ROW
            ================================================== -->

            <div class="form-grid three-columns">


                <!-- FULL NAME -->

                <div class="input-group">

                    <label for="full_name">

                        Full Name

                    </label>


                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($_POST["full_name"] ?? $_SESSION["user_name"] ?? ""); ?>"
                        required
                    >

                </div>



                <!-- EMAIL -->

                <div class="input-group">

                    <label for="email">

                        Email Address

                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($_POST["email"] ?? $_SESSION["user_email"] ?? ""); ?>"
                        required
                    >

                </div>



                <!-- PHONE -->

                <div class="input-group">

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
                required
                    >

                </div>



                <!-- AGE -->

                <div class="input-group">

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
                        required
                    >


                    <small style="display:block; margin-top:6px; color:#777777; font-size:12px;">

                        You must be 18 or older to become a host.

                    </small>

                </div>

            </div>



            <!-- =================================================
                 SECOND ROW
            ================================================== -->

            <div class="form-grid three-columns">


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
                            value="<?php echo htmlspecialchars($_POST["location"] ?? ""); ?>"
                            required
                        >


                        <span>

                            ◉

                        </span>

                    </div>

                </div>



                <!-- ID TYPE -->

                <div class="input-group">

                    <label for="id_type">

                        ID Type

                    </label>


                    <select
                        id="id_type"
                        name="id_type"
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

                </div>



                <!-- ID NUMBER -->

                <div class="input-group">

                    <label for="id_number">

                        ID Number

                    </label>


                    <input
                        type="text"
                        id="id_number"
                        name="id_number"
                        placeholder="Enter ID Number"
                        value="<?php echo htmlspecialchars($_POST["id_number"] ?? ""); ?>"
                        required
                    >

                </div>

            </div>


<!-- =================================================
     UPLOAD ID
     The preview (image + filename + remove button) is
     rendered directly inside #uploadBox by the script
     at the bottom of this file — no separate preview
     element needed anymore.
================================================== -->

<div class="upload-section">

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
                (JPG, PNG – Max 5MB)
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
</div>



            <!-- =================================================
                 FORM BUTTON
            ================================================== -->

            <div class="form-actions">


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
    const fileInput = document.getElementById('upload_id');
    const uploadBox  = document.getElementById('uploadBox');

    if (!fileInput || !uploadBox) return;

    function formatSize(bytes) {
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
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
                <span>(JPG, PNG – Max 5MB)</span>
            </div>
        `;
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;
        showPreview(file);
    });
})();
</script>

<script src="/webprogg/assets/javaScript.js"></script>

</body>

</html>