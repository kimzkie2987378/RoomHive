<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 4)
   SPACE SUBMITTED — AWAITING ADMIN APPROVAL
   ========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* Only logged-in users can access this page. */
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: /webprogg/auth/loginform.php");
    exit();
}

/* Step 2 and Step 3 must be completed first — listing_id is
   set by host-step2.php's INSERT, and cover_photo is set by
   host-step3.php, so both being present proves the earlier
   steps actually ran. */
if (
    !isset($_SESSION["host_application"]["listing_id"]) ||
    !isset($_SESSION["host_application"]["cover_photo"])
) {
    header("Location: /webprogg/host/becomeahost.php");
    exit();
}

$isLoggedIn = true;
$userName = $_SESSION["user_name"] ?? "User";

/*
 * Whether the ADMIN has already approved this user's host
 * application. This is the real gate now — not "did they
 * finish the upload wizard". It only flips true from
 * hostapplication.php once an admin clicks Approve.
 */
$isHostApproved = isset($_SESSION["is_host"]) && $_SESSION["is_host"] === true;

$currentPage = $isHostApproved ? "/webprogg/host/hostprofile.php" : "/webprogg/user/userprofile.php";
$currentStep = 4;

/* ---------------------------------------------------------
   HOSTING STEPS
--------------------------------------------------------- */
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

/* ---------------------------------------------------------
   FINALIZE THE LISTING SUBMISSION (runs once)
   Flips the real `listings` row from 'draft' to 'pending' so
   it shows up in listingapplication.php's queue for an admin
   to review. This does NOT touch is_host or hosting status —
   that only happens once an admin approves the underlying
   host_applications row (see hostapplication.php). Guarded by
   "finalized" so refreshing this page doesn't re-run the
   update.
--------------------------------------------------------- */
if (empty($_SESSION["host_application"]["finalized"])) {

    $listingId = $_SESSION["host_application"]["listing_id"];

    $pdo->prepare("UPDATE listings SET status = 'pending' WHERE id = :id")
        ->execute(['id' => $listingId]);

    $_SESSION["host_application"]["finalized"] = true;
}

/* ---------------------------------------------------------
   NAVIGATION
--------------------------------------------------------- */
$navigation = [
    "HOME" => "/webprogg/user/usershome.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

$quickLinks = [
    "About Us" => "/webprogg/index.php",
    "How It Works" => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Hive Club" => "/webprogg/hiveclub.php",
    "Contacts" => "/webprogg/misc/contacts.php"
];

$listingCategories = [
    "Shared Bedroom" => "shared-bedroom",
    "Private Room" => "private-room",
    "Entire House" => "entire-house",
    "Boarding House" => "boarding-house",
    "Studio Loft" => "studio-loft"
];

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
$fullName = $_SESSION["host_application"]["full_name"] ?? $userName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive - <?php echo $isHostApproved ? 'Space Added Successfully' : 'Awaiting Approval'; ?></title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Keep your existing global navigation/footer styles. -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
</head>

<body class="space-success-page">

<header class="navbar">

    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>
            <a href="<?php echo htmlspecialchars($link); ?>"
               class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($name); ?>
            </a>
        <?php endforeach; ?>

        <?php if ($isLoggedIn): ?>
            <div class="account-dropdown">
                <button
                    type="button"
                    class="my-account"
                    id="accountDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span class="account-circle">
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">
                    <?php if ($isHostApproved): ?>
                        <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                    <?php endif; ?>
                    <a href="/webprogg/user/userprofile.php">My Profile</a>
                    <a href="/webprogg/auth/logout.php">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="/webprogg/host/becomeahost.php" class="list-space">LIST YOUR SPACE</a>
        <?php endif; ?>

    </nav>

</header>

<?php if ($isLoggedIn): ?>
<style>
    .account-dropdown {
        position: relative;
        margin-left: 32px;
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

    .pending-approval-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff4e8;
        border: 1px solid #f7941d;
        color: #8a5a10;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0 auto 20px;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('accountDropdownToggle');
        const dropdown = toggle ? toggle.closest('.account-dropdown') : null;

        if (!toggle || !dropdown) {
            return;
        }

        toggle.addEventListener('click', function () {
            const isOpen = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
</script>
<?php endif; ?>

<main class="success-main">

    <section class="success-header">
        <span class="success-eyebrow">ADD YOUR SPACE</span>
        <h1>Get Bookings</h1>

        <div class="host-steps">

            <?php foreach ($hostSteps as $index => $step): ?>
                <?php
                    $isActive = ($step["number"] === $currentStep);
                    $isDone = ($step["number"] < $currentStep);
                ?>

                <div class="host-step<?php echo $isActive ? ' active' : ''; ?><?php echo $isDone ? ' completed' : ''; ?>">

                    <div class="step-circle">
                        <img
                            src="<?php echo htmlspecialchars($step["icon"]); ?>"
                            alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        >
                    </div>

                    <?php if ($index < count($hostSteps) - 1): ?>
                        <div class="step-line"></div>
                    <?php endif; ?>

                    <div class="step-number">
                        <?php echo $step["number"]; ?>
                    </div>

                    <h3><?php echo htmlspecialchars($step["title"]); ?></h3>

                    <p><?php echo htmlspecialchars($step["description"]); ?></p>

                </div>
            <?php endforeach; ?>

        </div>
    </section>

    <section class="success-card">

        <?php if (!$isHostApproved): ?>
            <span class="pending-approval-badge">&#9203; Awaiting Admin Approval</span>
        <?php endif; ?>

        <div class="success-icon-wrap">
            <img
                src="/webprogg/images/SpaceSuccessfullyAddIcon.png"
                alt="Space Submitted"
                class="success-icon"
            >
        </div>

        <?php if ($isHostApproved): ?>

            <h2>Space Added Successfully!</h2>

            <p class="success-message">
                Your space has been added and is now awaiting approval.<br>
                You can manage your listing, update details,<br>
                and start receiving bookings once it's live.
            </p>

            <div class="success-actions">

                <a href="/webprogg/host/hostprofile.php" class="success-button primary">
                    <img src="/webprogg/images/AddYourSpaceIcon-BecomeAHost.png" alt="" class="button-icon">
                    <span>GO TO HOST PROFILE</span>
                </a>

                <a href="/webprogg/host/becomeahost.php" class="success-button secondary">
                    <img src="/webprogg/images/AddAnotherButtonIcon.png" alt="" class="button-icon add-another-icon">
                    <span>ADD ANOTHER SPACE</span>
                </a>

            </div>

            <p class="success-footer-text">
                Continue to your host profile to manage your spaces.
            </p>

        <?php else: ?>

            <h2>Submitted for Review!</h2>

            <p class="success-message">
                Your space and host application have been submitted.<br>
                An admin needs to review and approve your application<br>
                before you can access your Host Profile and manage listings.
            </p>

            <div class="success-actions">

                <a href="/webprogg/user/userprofile.php" class="success-button primary">
                    <img src="/webprogg/images/AddYourSpaceIcon-BecomeAHost.png" alt="" class="button-icon">
                    <span>GO TO MY PROFILE</span>
                </a>

            </div>

            <p class="success-footer-text">
                We'll let you know as soon as your host application is approved.
            </p>

        <?php endif; ?>

    </section>

</main>

<footer class="success-footer">

    <div class="success-footer-top">

        <div class="success-footer-brand">
            <a href="/webprogg/index.php">
                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                    class="success-footer-logo"
                >
            </a>

            <div class="success-footer-contact">
                <div>
                    <img src="/webprogg/images/PhoneIcon.jpg" alt="Phone">
                    <span>09275693574</span>
                </div>

                <div>
                    <img src="/webprogg/images/EmailIcon.jpg" alt="Email">
                    <span>kimdivino55@gmail.com</span>
                </div>
            </div>
        </div>

        <div class="success-footer-links">
            <h3>LISTINGS</h3>

            <?php foreach ($listingCategories as $category => $type): ?>
                <a href="/webprogg/Listings/listing.php?type=<?php echo urlencode($type); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="success-footer-links">
            <h3>QUICK LINKS</h3>

            <?php foreach ($quickLinks as $name => $link): ?>
                <a href="<?php echo htmlspecialchars($link); ?>">
                    <?php echo htmlspecialchars($name); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="success-footer-app">
            <h3>GET THE APP</h3>

            <div class="app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>

    </div>

    <div class="success-footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> RoomHive. All rights reserved.</p>
    </div>

</footer>

<script src="/webprogg/assets/javaScript.js"></script>

</body>
</html>