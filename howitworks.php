<?php
session_start();

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

/*
 * NAVBAR AVATAR
 * $_SESSION['avatar_path'] is only set at login time, so if the
 * user uploaded a new profile photo since then, re-check the DB
 * so the navbar's account icon reflects it immediately instead
 * of only after logging back in.
 */
if ($isLoggedIn && isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $avatarStmt->execute(['id' => $_SESSION['user_id']]);
    $avatarRow = $avatarStmt->fetch();
    $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
}
$navAvatar = $_SESSION['avatar_path'] ?? 'images/default-avatar.png';

/* =========================================================
   ROOMHIVE - HOW IT WORKS
   ========================================================= */

// Current page
$currentPage = "howitworks";

// Navigation links
$navigation = [
    "HOME" => $isLoggedIn ? "usershome.php" : "index.php",
    "LISTINGS" => "listing.php",
    "HOW IT WORKS" => "howitworks.php",
    "BECOME A HOST" => $isLoggedIn ? "becomeahost.php" : "loginform.php",
    "HIVE CLUB" => "hiveclub.php",
    "CONTACTS" => "contacts.php"
];

// Renter steps
$renterSteps = [
    [
        "number" => 1,
        "icon" => "images/SearchIcon-HowItWorks.png",
        "alt" => "Search",
        "title" => "Search",
        "description" => "Browse listings by location, category, and price."
    ],
    [
        "number" => 2,
        "icon" => "images/ChooseIcon-HowItWorks.png",
        "alt" => "Choose",
        "title" => "Choose",
        "description" => "View details, photos, amenities, and compare options."
    ],
    [
        "number" => 3,
        "icon" => "images/BookIcon-HowItWorks.png",
        "alt" => "Book",
        "title" => "Book",
        "description" => "Select your dates and send a booking request."
    ],
    [
        "number" => 4,
        "icon" => "images/Confirm&ConfirmBookingIcon-HowItWorks.png",
        "alt" => "Confirm Booking",
        "title" => "Confirm",
        "description" => "Host confirms your request and you're all set!"
    ],
    [
        "number" => 5,
        "icon" => "images/Stay&EnjoyIcon-HowItWorks.png",
        "alt" => "Stay and Enjoy",
        "title" => "Stay & Enjoy",
        "description" => "Check in and enjoy your comfortable stay."
    ]
];

// Host steps
$hostSteps = [
    [
        "number" => 1,
        "icon" => "images/ListYourSpaceIcon-HowItWorks.png",
        "alt" => "List Your Space",
        "title" => "List Your Space",
        "description" => "Add photos, details, amenities, and set your price."
    ],
    [
        "number" => 2,
        "icon" => "images/GetDiscoveredICon-HowItWorks.png",
        "alt" => "Get Discovered",
        "title" => "Get Discovered",
        "description" => "Your listing goes live and matches with renters."
    ],
    [
        "number" => 3,
        "icon" => "images/ReceiveBookingsIcon-HowItWorks.png",
        "alt" => "Receive Bookings",
        "title" => "Receive Bookings",
        "description" => "Guests send booking requests for your review."
    ],
    [
        "number" => 4,
        "icon" => "images/Confirm&ConfirmBookingIcon-HowItWorks.png",
        "alt" => "Confirm Booking",
        "title" => "Confirm Booking",
        "description" => "Review the request and confirm the booking."
    ],
    [
        "number" => 5,
        "icon" => "images/Host&EarnIcon-HowItWorks.png",
        "alt" => "Host and Earn",
        "title" => "Host & Earn",
        "description" => "Welcome guests and earn with RoomHive."
    ]
];

// Listing categories
$listingCategories = [
    "Shared Bedroom" => "shared-bedroom",
    "Private Room" => "private-room",
    "Entire House" => "entire-house",
    "Boarding House" => "boarding-house",
    "Studio Loft" => "studio-loft"
];

// Quick links
$quickLinks = [
    "About Us" => "index.php",
    "How It Works" => "howitworks.php",
    "Become a Host" => "becomeahost.php",
    "Hive Club" => "hiveclub.php",
    "Contacts" => "contacts.php"
];

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
        RoomHive - How It Works
    </title>

    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- =====================================================
         CSS
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

    <div class="logo">

        <a href="<?php echo $isLoggedIn ? 'usershome.php' : 'index.php'; ?>">

            <img
                src="images/RoomHiveLogos.png"
                alt="RoomHive Logo"
            >

        </a>

    </div>


    <!-- NAVIGATION -->

    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>

            <a
                href="<?php echo htmlspecialchars($link); ?>"
                class="<?php echo ($name === 'HOW IT WORKS') ? 'active' : ''; ?>"
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
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">

                    <?php if (isset($_SESSION['is_host']) && $_SESSION['is_host'] === true): ?>
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
                href="loginform.php"
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
     HOW IT WORKS HEADER
========================================================= -->

<section class="how-header">

    <div class="how-header-text">

        <h1>
            How It Works
        </h1>

        <span class="eyebrow-line"></span>

        <p>
            Finding or listing a space on RoomHive is quick and easy!
        </p>

    </div>


    <div class="how-header-illustration">

        <img
            src="images/HumanThinkingWithHouseAndKey.png"
            alt="Person thinking about renting or listing a space"
        >

    </div>

</section>



<!-- =========================================================
     FOR RENTERS
========================================================= -->

<section class="steps-section renters-section">

    <span class="steps-eyebrow">
        For Renters
    </span>

    <p class="steps-subtitle">
        Find your perfect space in just a few steps.
    </p>


    <div class="steps-container">

        <?php foreach ($renterSteps as $step): ?>

            <div class="step-card">

                <div class="step-number">

                    <?php echo $step["number"]; ?>

                </div>


                <div class="step-icon-box">

                    <img
                        src="<?php echo htmlspecialchars($step["icon"]); ?>"
                        alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        class="step-icon"
                    >

                </div>


                <h4>

                    <?php echo htmlspecialchars($step["title"]); ?>

                </h4>


                <p>

                    <?php echo htmlspecialchars($step["description"]); ?>

                </p>

            </div>

        <?php endforeach; ?>

    </div>

</section>



<!-- =========================================================
     FOR HOSTS
========================================================= -->

<section class="steps-section hosts-section">

    <span class="steps-eyebrow">
        For Hosts
    </span>

    <p class="steps-subtitle">
        List your space and start earning.
    </p>


    <div class="steps-container">

        <?php foreach ($hostSteps as $step): ?>

            <div class="step-card">

                <div class="step-number">

                    <?php echo $step["number"]; ?>

                </div>


                <div class="step-icon-box">

                    <img
                        src="<?php echo htmlspecialchars($step["icon"]); ?>"
                        alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                        class="step-icon"
                    >

                </div>


                <h4>

                    <?php echo htmlspecialchars($step["title"]); ?>

                </h4>


                <p>

                    <?php echo htmlspecialchars($step["description"]); ?>

                </p>

            </div>

        <?php endforeach; ?>

    </div>

</section>



<!-- =========================================================
     READY TO GET STARTED
========================================================= -->

<section class="ready-cta">

    <div class="ready-cta-left">


        <!-- CROWN -->

        <img
            src="images/Crown_Logo.png"
            alt="RoomHive Crown"
            class="ready-cta-crown"
        >


        <!-- TEXT -->

        <div class="ready-cta-text">

            <h2>
                Ready to get started?
            </h2>

            <p>
                Join RoomHive today and be part of a trusted community.
            </p>

        </div>

    </div>


    <!-- BUTTONS -->

    <div class="ready-cta-buttons">

        <a
            href="listing.php"
            class="btn-primary"
        >

            FIND A SPACE

        </a>


        <a
            href="<?php echo $isLoggedIn ? 'becomeahost.php' : 'loginform.php'; ?>"
            class="btn-secondary"
        >

            LIST YOUR SPACE

        </a>

    </div>

</section>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

    <div class="footer-top">


        <!-- =================================================
             FOOTER BRAND
        ================================================== -->

        <div class="footer-brand">

            <a href="<?php echo $isLoggedIn ? 'usershome.php' : 'index.php'; ?>">

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
                    class="<?php echo ($link === 'howitworks.php') ? 'active' : ''; ?>"
                >

                    <?php echo htmlspecialchars($name); ?>

                </a>

            <?php endforeach; ?>

        </div>



        <!-- =================================================
             CONTACT / APP
        ================================================== -->

        <div class="footer-contact">

            <span class="footer-heading">
                GET THE APP
            </span>


            <!-- APP BADGES -->

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
                    Bacolod City, Negros Occidental
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


</body>

</html>