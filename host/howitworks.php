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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
    $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $avatarStmt->execute(['id' => $_SESSION['user_id']]);
    $avatarRow = $avatarStmt->fetch();
    $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
}
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

/* Notification bell badge count */
 $notification_count = 0;

/* FLOATING LOGIN MODAL — DELETED. Guests who click any
   login link now navigate to loginform.php directly. */

/* =========================================================
   ROOMHIVE - HOW IT WORKS
   ========================================================= */
 $navigation = [
    "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => $isLoggedIn ? "/webprogg/host/becomeahost.php" : "/webprogg/auth/loginform.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

 $currentPage = $navigation['HOW IT WORKS'];
 $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

// Renter steps
 $renterSteps = [
    [
        "number" => 1,
        "icon" => "/webprogg/images/SearchIcon-HowItWorks.png",
        "alt" => "Search",
        "title" => "Search",
        "description" => "Browse listings by location, category, and price."
    ],
    [
        "number" => 2,
        "icon" => "/webprogg/images/ChooseIcon-HowItWorks.png",
        "alt" => "Choose",
        "title" => "Choose",
        "description" => "View details, photos, amenities, and compare options."
    ],
    [
        "number" => 3,
        "icon" => "/webprogg/images/BookIcon-HowItWorks.png",
        "alt" => "Book",
        "title" => "Book",
        "description" => "Select your dates and send a booking request."
    ],
    [
        "number" => 4,
        "icon" => "/webprogg/images/Confirm&ConfirmBookingIcon-HowItWorks.png",
        "alt" => "Confirm Booking",
        "title" => "Confirm",
        "description" => "Host confirms your request and you're all set!"
    ],
    [
        "number" => 5,
        "icon" => "/webprogg/images/Stay&EnjoyIcon-HowItWorks.png",
        "alt" => "Stay and Enjoy",
        "title" => "Stay & Enjoy",
        "description" => "Check in and enjoy your comfortable stay."
    ]
];

// Host steps
 $hostSteps = [
    [
        "number" => 1,
        "icon" => "/webprogg/images/ListYourSpaceIcon-HowItWorks.png",
        "alt" => "List Your Space",
        "title" => "List Your Space",
        "description" => "Add photos, details, amenities, and set your price."
    ],
    [
        "number" => 2,
        "icon" => "/webprogg/images/GetDiscoveredICon-HowItWorks.png",
        "alt" => "Get Discovered",
        "title" => "Get Discovered",
        "description" => "Your listing goes live and matches with renters."
    ],
    [
        "number" => 3,
        "icon" => "/webprogg/images/ReceiveBookingsIcon-HowItWorks.png",
        "alt" => "Receive Bookings",
        "title" => "Receive Bookings",
        "description" => "Guests send booking requests for your review."
    ],
    [
        "number" => 4,
        "icon" => "/webprogg/images/Confirm&ConfirmBookingIcon-HowItWorks.png",
        "alt" => "Confirm Booking",
        "title" => "Confirm Booking",
        "description" => "Review the request and confirm the booking."
    ],
    [
        "number" => 5,
        "icon" => "/webprogg/images/Host&EarnIcon-HowItWorks.png",
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
    "About Us" => "/webprogg/index.php",
    "How It Works" => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Contacts" => "/webprogg/misc/contacts.php"
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >
 
    <!-- =====================================================
         CSS
    ====================================================== -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >
    <link
        rel="stylesheet"
        href="/webprogg/assets/howitworks.css"
    >

    <!-- Flags that JS is available so scroll-reveal elements
         only start hidden when they can actually be revealed. -->
    <script>document.documentElement.classList.add("js");</script>

    <!-- FLOATING LOGIN MODAL STYLES — DELETED -->

    <!-- =====================================================
         BRIGHT THEME PATCH (kept — page-level styles only)
    ====================================================== -->
    <style>

:root {
  color-scheme: light;
}

/* ---- Brighten the page itself ---- */
body {
  background: #fffdf8;
}

.hiw-hero {
  background: linear-gradient(180deg, #fff4d6 0%, #fffdf8 78%, #ffffff 100%);
}

.hiw-renters { background: #fffdf8; }
.hiw-hosts   { background: #fff8e9; }

    </style>

</head>


<body>


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->
 
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- =========================================================
     HERO
========================================================= -->

<section class="hiw-hero">

    <!-- Decorative background: glow blobs + honeycomb texture -->
    <div class="hiw-hero-decor" aria-hidden="true">

        <span class="hiw-blob hiw-blob-1"></span>
        <span class="hiw-blob hiw-blob-2"></span>
        <span class="hiw-blob hiw-blob-3"></span>

    </div>


    <div class="hiw-hero-inner">

        <!-- HERO TEXT -->
        <div class="hiw-hero-text">

            <span class="hiw-hero-badge hiw-anim" style="--d: .05s;">

                <span class="hiw-pulse-dot"></span>

                Simple • Safe • Stress-Free

            </span>


            <h1 class="hiw-anim" style="--d: .15s;">

                How It

                <span class="hiw-shimmer-text">Works</span>

            </h1>


            <p class="hiw-anim" style="--d: .25s;">

                Finding or listing a space on RoomHive is quick and
                easy — follow the path and you're home.

            </p>


            <div class="hiw-hero-actions hiw-anim" style="--d: .35s;">

                <a
                    href="/webprogg/Listings/listing.php"
                    class="hiw-btn hiw-btn-honey"
                >

                    FIND A SPACE

                    <span class="hiw-btn-arrow">&rarr;</span>

                </a>


                <!-- Navigates to loginform.php for guests — no popup -->
                <a
                    href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
                    class="hiw-btn hiw-btn-outline"
                >

                    BECOME A HOST

                </a>

            </div>


            <!-- QUICK STATS -->
            <div class="hiw-hero-stats hiw-anim" style="--d: .45s;">

                <div class="hiw-stat">

                    <strong data-count="5">5</strong>

                    <span>Easy Steps</span>

                </div>

                <span class="hiw-stat-sep"></span>


                <div class="hiw-stat">

                    <strong data-count="100" data-suffix="%">100%</strong>

                    <span>Verified Listings</span>

                </div>

                <span class="hiw-stat-sep"></span>


                <div class="hiw-stat">

                    <strong>24/7</strong>

                    <span>Support</span>

                </div>

            </div>

        </div>


        <!-- HERO ART -->
        <div class="hiw-hero-art hiw-anim" style="--d: .3s;">

            <span class="hiw-art-glow" aria-hidden="true"></span>

            <img
                class="hiw-art-img"
                src="/webprogg/images/HumanThinkingWithHouseAndKey.png"
                alt="Person thinking about renting or listing a space"
            >


            <!-- Floating glass chips -->
            <div class="hiw-chip hiw-chip-1">

                <img
                    src="/webprogg/images/SearchIcon-HowItWorks.png"
                    alt=""
                >

                <span>Smart Search</span>

            </div>


            <div class="hiw-chip hiw-chip-2">

                <img
                    src="/webprogg/images/BookIcon-HowItWorks.png"
                    alt=""
                >

                <span>Easy Booking</span>

            </div>


            <div class="hiw-chip hiw-chip-3">

                <img
                    src="/webprogg/images/Stay&EnjoyIcon-HowItWorks.png"
                    alt=""
                >

                <span>Enjoy Your Stay</span>

            </div>

        </div>

    </div>


    <!-- Wave divider into the next section -->
    <svg
        class="hiw-hero-wave"
        viewBox="0 0 1440 90"
        preserveAspectRatio="none"
        aria-hidden="true"
    >

        <path
            d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z"
            fill="#ffffff"
        >

        </path>

    </svg>

</section>


<!-- =========================================================
     FOR RENTERS
========================================================= -->

<section class="hiw-section hiw-renters" id="for-renters">

    <div class="hiw-section-head hiw-reveal">

        <span class="hiw-eyebrow">
            For Renters
        </span>

        <h2>
            Find your perfect space<br>
            in just a few steps
        </h2>

        <p>
            From first search to move-in day, RoomHive keeps
            everything simple.
        </p>

    </div>


    <div class="hiw-steps">

        <?php foreach ($renterSteps as $i => $step): ?>

            <article
                class="hiw-step hiw-reveal"
                style="--i: <?php echo (int) $i; ?>;"
            >

                <div class="hiw-step-number">

                    <?php echo $step["number"]; ?>

                </div>


                <div class="hiw-step-icon">

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

            </article>

        <?php endforeach; ?>

    </div>

</section>


<!-- =========================================================
     FOR HOSTS
========================================================= -->

<section class="hiw-section hiw-hosts" id="for-hosts">

    <div class="hiw-section-head hiw-reveal">

        <span class="hiw-eyebrow">
            For Hosts
        </span>

        <h2>
            List your space<br>
            and start earning
        </h2>

        <p>
            Turn your extra space into income — five steps
            from listing to payout.
        </p>

    </div>


    <div class="hiw-steps">

        <?php foreach ($hostSteps as $i => $step): ?>

            <article
                class="hiw-step hiw-reveal"
                style="--i: <?php echo (int) $i; ?>;"
            >

                <div class="hiw-step-number">

                    <?php echo $step["number"]; ?>

                </div>


                <div class="hiw-step-icon">

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

            </article>

        <?php endforeach; ?>

    </div>

</section>


<!-- =========================================================
     READY TO GET STARTED
========================================================= -->

<section class="hiw-cta-wrap">

    <div class="hiw-cta hiw-reveal">

        <div class="hiw-cta-left">

            <img
                src="/webprogg/images/Crown_Logo.png"
                alt="RoomHive Crown"
                class="hiw-cta-crown"
            >


            <div class="hiw-cta-text">

                <span class="hiw-cta-kicker">
                    Ready When You Are
                </span>

                <h2>
                    Ready to get started?
                </h2>

                <p>
                    Join RoomHive today and be part of a
                    trusted community.
                </p>

            </div>

        </div>


        <div class="hiw-cta-buttons">

            <a
                href="/webprogg/Listings/listing.php"
                class="hiw-btn hiw-btn-honey hiw-btn-shine"
            >

                FIND A SPACE

            </a>


            <!-- Navigates to loginform.php for guests — no popup -->
            <a
                href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
                class="hiw-btn hiw-btn-ghost-light"
            >

                LIST YOUR SPACE

            </a>

        </div>

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

            <a href="<?php echo $isLoggedIn ? '/webprogg/user/usershome.php' : '/webprogg/index.php'; ?>">

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

                <a
                    href="<?php echo htmlspecialchars($link); ?>"
                    class="<?php echo ($link === '/webprogg/host/howitworks.php') ? 'active' : ''; ?>"
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


<!-- FLOATING LOGIN MODAL MARKUP — DELETED -->


<!-- =========================================================
     SCROLL REVEAL + STAT COUNT-UP
     Self-contained on purpose — can't be broken by a cached
     or erroring javaScript.js.
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(
        document.querySelectorAll(".hiw-reveal")
    );

    /* ---- Scroll reveal (staggered via the --i custom property) ---- */
    if (reduced || !("IntersectionObserver" in window)) {

        revealEls.forEach(function (el) {
            el.classList.add("in-view");
        });

    } else {

        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;

                    var el = entry.target;
                    io.unobserve(el);
                    el.classList.add("in-view");

                    /* After the reveal finishes, zero the stagger
                       delay so hover transitions respond instantly. */
                    window.setTimeout(function () {
                        el.style.setProperty("--i", "0");
                    }, 1200);
                });
            },
            { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
        );

        revealEls.forEach(function (el) {
            io.observe(el);
        });
    }


    /* ---- Stat count-up (hero stats) ---- */
    var stats = document.querySelectorAll("[data-count]");

    if (stats.length && !reduced && "IntersectionObserver" in window) {

        var statIo = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;

                    var el = entry.target;
                    statIo.unobserve(el);

                    var target = parseInt(el.getAttribute("data-count"), 10) || 0;
                    var suffix = el.getAttribute("data-suffix") || "";
                    var t0 = null;
                    var DURATION = 1400;

                    var stepFn = function (ts) {
                        if (!t0) t0 = ts;
                        var k = Math.min((ts - t0) / DURATION, 1);
                        var eased = 1 - Math.pow(1 - k, 3);
                        el.textContent = Math.round(target * eased) + suffix;
                        if (k < 1) window.requestAnimationFrame(stepFn);
                    };

                    window.requestAnimationFrame(stepFn);
                });
            },
            { threshold: 0.6 }
        );

        Array.prototype.forEach.call(stats, function (el) {
            statIo.observe(el);
        });
    }
    /* No JS / reduced motion: the final numbers are already in
       the markup, so nothing needs to happen. */
})();
</script>


<!-- FLOATING LOGIN MODAL SCRIPT — DELETED -->


</body>

</html>