<?php
/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 4)
   SPACE ADDED SUCCESSFULLY
   ========================================================= */

session_start();

/* Only logged-in users can access this page. */
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: loginform.php");
    exit();
}

/* Step 1, Step 2 and Step 3 must be completed first. */
if (
    !isset($_SESSION["host_application"]) ||
    !isset($_SESSION["host_application"]["title"]) ||
    !isset($_SESSION["host_application"]["cover_photo"])
) {
    header("Location: becomeahost.php");
    exit();
}

$isLoggedIn = true;
$currentPage = "becomeahost.php";
$currentStep = 4;
$userName = $_SESSION["user_name"] ?? "User";

/* ---------------------------------------------------------
   HOSTING STEPS
--------------------------------------------------------- */
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

/* ---------------------------------------------------------
   FINISH THE SPACE SETUP ONCE
   This makes the new space available in the host profile.
--------------------------------------------------------- */
if (!isset($_SESSION["host_listings"]) || !is_array($_SESSION["host_listings"])) {
    $_SESSION["host_listings"] = [];
}

if (empty($_SESSION["host_application"]["finalized"])) {
    $app = $_SESSION["host_application"];

    $_SESSION["host_listings"][] = [
        "id" => uniqid("listing_"),
        "title" => $app["title"] ?? "My Space",
        "location" => $app["location"] ?? "",
        "price" => $app["price"] ?? "",
        "image" => $app["cover_photo"] ?? "images/ListingPlaceholder.png",
        "bookings" => 0,
        "occupancy" => 0,
        "earnings" => 0,
        "status" => "Active"
    ];

    $_SESSION["host_application"]["finalized"] = true;
    $_SESSION["is_host"] = true;
}

/* ---------------------------------------------------------
   NAVIGATION
--------------------------------------------------------- */
$navigation = [
    "HOME" => "usershome.php",
    "LISTINGS" => "listing.php",
    "HOW IT WORKS" => "howitworks.php",
    "BECOME A HOST" => "becomeahost.php",
    "HIVE CLUB" => "hiveclub.php",
    "CONTACTS" => "contacts.php"
];

$quickLinks = [
    "About Us" => "index.php",
    "How It Works" => "howitworks.php",
    "Become a Host" => "becomeahost.php",
    "Hive Club" => "hiveclub.php",
    "Contacts" => "contacts.php"
];

$listingCategories = [
    "Shared Bedroom" => "shared-bedroom",
    "Private Room" => "private-room",
    "Entire House" => "entire-house",
    "Boarding House" => "boarding-house",
    "Studio Loft" => "studio-loft"
];

$avatarImage = $_SESSION["host_avatar"] ?? "images/DefaultAvatar.png";
$fullName = $_SESSION["host_application"]["full_name"] ?? $userName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive - Space Added Successfully</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Keep your existing global navigation/footer styles. -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="space-success-page">

<header class="navbar">

    <a href="usershome.php" class="logo">
        <img src="images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

   <nav class="nav-links">
    <?php foreach ($navigation as $name => $link): ?>
        <a href="<?php echo htmlspecialchars($link); ?>"
           class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($name); ?>
        </a>
    <?php endforeach; ?>
</nav>

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
                        <img src="images/MyAccountIcon.png" alt="My Account">
                    </span>
                    <span>MY ACCOUNT</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">
                    <a href="myaccount.php">My Account</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="becomeahost.php" class="list-space">LIST YOUR SPACE</a>
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
</style>
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

        <div class="success-icon-wrap">
            <img
                src="images/SpaceSuccessfullyAddIcon.png"
                alt="Space Added Successfully"
                class="success-icon"
            >
        </div>

        <h2>Space Add Successfully!</h2>

        <p class="success-message">
            Your space has been added and is now live.<br>
            You can manage your listing, update details,<br>
            and start receiving bookings.
        </p>

        <div class="success-actions">

            <a href="hostdashboard.php" class="success-button primary">
                <img src="images/AddYourSpaceIcon-BecomeAHost.png" alt="" class="button-icon">
                <span>GO TO HOST PROFILE</span>
            </a>

            <a href="host-step2.php?add_another=1" class="success-button secondary">
                <img src="images/AddAnotherButtonIcon.png" alt="" class="button-icon add-another-icon">
                <span>ADD ANOTHER SPACE</span>
            </a>

        </div>

        <p class="success-footer-text">
            Continue to your host profile to manage your spaces.
        </p>

    </section>

</main>

<footer class="success-footer">

    <div class="success-footer-top">

        <div class="success-footer-brand">
            <a href="index.php">
                <img
                    src="images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                    class="success-footer-logo"
                >
            </a>

            <div class="success-footer-contact">
                <div>
                    <img src="images/PhoneIcon.jpg" alt="Phone">
                    <span>09275693574</span>
                </div>

                <div>
                    <img src="images/EmailIcon.jpg" alt="Email">
                    <span>kimdivino55@gmail.com</span>
                </div>
            </div>
        </div>

        <div class="success-footer-links">
            <h3>LISTINGS</h3>

            <?php foreach ($listingCategories as $category => $type): ?>
                <a href="listing.php?type=<?php echo urlencode($type); ?>">
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
                <img src="images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>

    </div>

    <div class="success-footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> RoomHive. All rights reserved.</p>
    </div>

</footer>

<script src="javaScript.js"></script>

</body>
</html>