<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 3)
   UPLOAD PHOTOS
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
$currentStep = 3;

// Maximum number of additional photos allowed
$maxAdditionalPhotos = 20;

// Number of empty upload slots to render on first load
$initialPhotoSlots = 10;

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
// LISTING CATEGORIES (footer)
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

        /*
         * Save the uploaded photos to disk and record them in
         * the listing_photos table, attached to the listing
         * created in host-step2.php.
         */

        $listingId = $_SESSION["host_application"]["listing_id"];

        $coverUploadDirectory = "uploads/listing_photos/cover/";

        $additionalUploadDirectory = "uploads/listing_photos/additional/";


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

        $coverPath = $coverUploadDirectory . $coverFileName;


        if (move_uploaded_file($coverFile["tmp_name"], $coverPath)) {

            $pdo->prepare(
                "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                 VALUES (:listing_id, :photo_path, 'cover', 0)"
            )->execute([
                'listing_id' => $listingId,
                'photo_path' => $coverPath,
            ]);

            $_SESSION["host_application"]["cover_photo"] = $coverPath;

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

            $path = $additionalUploadDirectory . $fileName;


            if (move_uploaded_file($file["tmp_name"], $path)) {

                $pdo->prepare(
                    "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                     VALUES (:listing_id, :photo_path, 'additional', :sort_order)"
                )->execute([
                    'listing_id' => $listingId,
                    'photo_path' => $path,
                    'sort_order' => $sortOrder,
                ]);

                $savedAdditionalPaths[] = $path;

                $sortOrder++;

            }

        }

        $_SESSION["host_application"]["additional_photos"] = $savedAdditionalPaths;


        // Go to next host registration step
        header("Location: host-step4.php");

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


    <!-- =====================================================
         PHOTO PREVIEW STYLES
         (You can move these into style.css instead)
    ====================================================== -->

    <style>

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

            Upload Photos

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
            action="host-step3.php"
            method="POST"
            enctype="multipart/form-data"
            id="uploadPhotosForm"
        >


            <!-- =================================================
                 UPLOAD PHOTOS HEADING
            ================================================== -->

            <div class="form-heading">

                <h2>

                    Upload Photos of Your Space

                </h2>


                <p>

                    High-quality photos help your listing stand out and get more bookings.

                </p>

            </div>



            <!-- =================================================
                 COVER PHOTO
            ================================================== -->

            <div class="photo-section-label">

                <label>

                    Cover Photo <span class="required-mark">*</span>

                </label>


                <p>

                    This will be the main photo displayed in your listing.

                </p>

            </div>


            <div class="cover-photo-row">


                <!-- COVER PHOTO DROPZONE -->

                <label
                    for="cover_photo"
                    class="cover-photo-box"
                    id="coverPhotoBox"
                >

                    <div class="cover-photo-placeholder" id="coverPhotoPlaceholder">

                        <div class="upload-icon">

                            ☁

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


                <!-- EXAMPLE REFERENCE PHOTO -->

                <div class="cover-photo-example">

                    <span class="example-badge">

                        Example

                    </span>


                    <img
                        src="images/Living_Room.png"
                        alt="Example of a good cover photo"
                    >

                </div>

            </div>



            <!-- =================================================
                 ADDITIONAL PHOTOS
            ================================================== -->

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

                                ☁

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


            <!-- =================================================
                 FORM BUTTON
            ================================================== -->

            <div class="form-actions">


                <a
                    href="host-step2.php"
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
     PHOTO UPLOAD SCRIPT
========================================================= -->

<script src="host-step3.js"></script>
<script>
    /* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 3)
   PHOTO UPLOAD + LIVE PREVIEWS
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================
       COVER PHOTO PREVIEW
       ===================================================== */

    const coverInput = document.getElementById('cover_photo');
    const coverPlaceholder = document.getElementById('coverPhotoPlaceholder');
    const coverPreview = document.getElementById('coverPhotoPreview');
    const coverRemoveBtn = document.getElementById('coverPhotoRemove');

    if (coverInput) {

        coverInput.addEventListener('change', function () {

            const file = coverInput.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                coverPreview.src = event.target.result;
                coverPreview.hidden = false;
                coverPlaceholder.hidden = true;
                coverRemoveBtn.hidden = false;

            };

            reader.readAsDataURL(file);

        });

    }


    if (coverRemoveBtn) {

        coverRemoveBtn.addEventListener('click', function (event) {

            event.preventDefault();
            event.stopPropagation();

            coverInput.value = '';
            coverPreview.hidden = true;
            coverPreview.src = '';
            coverPlaceholder.hidden = false;
            coverRemoveBtn.hidden = true;

        });

    }


    /* =====================================================
       ADDITIONAL PHOTO SLOTS
       ===================================================== */

    const photoSlots = document.querySelectorAll('.photo-slot');

    photoSlots.forEach(function (slot) {

        const trigger = slot.querySelector('.photo-slot-trigger');
        const input = slot.querySelector('.photo-slot-input');
        const preview = slot.querySelector('.photo-slot-preview');
        const removeBtn = slot.querySelector('.photo-slot-remove');

        if (!trigger || !input || !preview || !removeBtn) {
            return;
        }

        // Clicking the empty slot opens the file picker
        trigger.addEventListener('click', function () {
            input.click();
        });

        // When a file is chosen, show it inside this exact slot
        input.addEventListener('change', function () {

            const file = input.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                preview.src = event.target.result;
                preview.hidden = false;
                trigger.hidden = true;
                removeBtn.hidden = false;

            };

            reader.readAsDataURL(file);

        });

        // Clear this slot and let the user pick again
        removeBtn.addEventListener('click', function (event) {

            event.preventDefault();
            event.stopPropagation();

            input.value = '';
            preview.hidden = true;
            preview.src = '';
            trigger.hidden = false;
            removeBtn.hidden = true;

        });

    });

});
    </script>

</body>

</html>