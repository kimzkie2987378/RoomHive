<?php
session_start();

/*
 * Check whether the user is logged in.
 */
 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

/*
 * Whether an admin is currently logged in, so the footer's Admin
 * link can go straight to the dashboard instead of the login form.
 */
 $isAdminLoggedIn = (
    isset($_SESSION["admin_logged_in"]) &&
    $_SESSION["admin_logged_in"] === true
);

// =========================
// PAGE DATA - index.php
// =========================

 $navLinks = [
    ['label' => 'HOME', 'href' => '/webprogg/index.php', 'class' => 'active'],
    ['label' => 'LISTINGS', 'href' => '/webprogg/Listings/listing.php', 'class' => ''],
    ['label' => 'HOW IT WORKS', 'href' => '/webprogg/host/howitworks.php', 'class' => ''],

    [
        'label' => 'BECOME A HOST',
        'href' => $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php',
        'class' => $isLoggedIn ? '' : 'js-open-login'
    ],

    ['label' => 'HIVE CLUB', 'href' => '/webprogg/hiveclub.php', 'class' => ''],
    ['label' => 'CONTACTS', 'href' => '/webprogg/misc/contacts.php', 'class' => ''],
];

 $listings = [
    ['name' => 'STUDIO LOFT', 'image' => '/webprogg/images/StudioLoft.png', 'slug' => 'studioloft'],
    ['name' => 'SHARED ROOM', 'image' => '/webprogg/images/SharedBedroom.png', 'slug' => 'sharedbedroom'],
    ['name' => 'ENTIRE HOUSE', 'image' => '/webprogg/images/EntireHouse.png', 'slug' => 'entirehouse'],
    ['name' => 'PRIVATE ROOM', 'image' => '/webprogg/images/PrivateRoom.png', 'slug' => 'privateroom'],
    ['name' => 'BOARDING HOUSE', 'image' => '/webprogg/images/BoardingHouse.png', 'slug' => 'boardinghouse'],
    ['name' => 'APARTMENT', 'image' => '/webprogg/images/Apartment.png', 'slug' => 'apartment'],
];

 $reasons = [
    [
        'icon' => '/webprogg/images/247SupportsIcons.png',
        'title' => '24/7 Support',
        'text' => 'Our team is on call around the clock for hosts and tenants.',
    ],
    [
        'icon' => '/webprogg/images/VerifiedListingsIcons.png',
        'title' => 'Verified Listings',
        'text' => 'Every listing is hand-reviewed and checked by our team before it goes live.',
    ],
    [
        'icon' => '/webprogg/images/SecurePaymentsIcon.png',
        'title' => 'Secure Payments',
        'text' => 'Your booking and deposits are protected end-to-end.',
    ],
    [
        'icon' => '/webprogg/images/NoHiddenFeesIcons.png',
        'title' => 'No Hidden Fees',
        'text' => 'What you see is what you pay — no surprise charges, ever.',
    ],
];

 $testimonials = [
    [
        'quote' => 'Booking my apartment through RoomHive was seamless.',
        'body' => 'The team was responsive from day one and the listing matched exactly what was shown. Moving in was stress-free.',
        'author' => 'Isabelle Cruz',
    ],
    [
        'quote' => 'RoomHive made finding a place stress-free.',
        'body' => 'Verified listings and quick support meant I never had to worry about hidden costs or surprises on move-in day.',
        'author' => 'Miguel Santos',
    ],
    [
        'quote' => 'The verified listings gave me peace of mind.',
        'body' => 'I was nervous about renting sight-unseen, but every photo and detail on RoomHive matched reality. Highly recommend.',
        'author' => 'Andrea Lopez',
    ],
    [
        'quote' => 'Customer support answered me at midnight.',
        'body' => 'I had a payment issue right before move-in and the RoomHive team sorted it out within minutes, even that late.',
        'author' => 'Kevin Tan',
    ],
    [
        'quote' => 'No hidden fees, exactly as advertised.',
        'body' => 'What I saw on the listing page was what I paid — no surprise charges at checkout or on my first invoice.',
        'author' => 'Grace Villanueva',
    ],
    [
        'quote' => 'Found my apartment in under a week.',
        'body' => 'The filters made it easy to narrow down listings by price and amenities, so I didn\'t waste time scrolling.',
        'author' => 'Paolo Reyes',
    ],
];

 $ctaImages = [
    '/webprogg/images/FirstImageLeft.jpg',
    '/webprogg/images/SecondImageLeft.jpg',
    '/webprogg/images/MiddleImage.avif',
    '/webprogg/images/FirstImageRight.jpg',
    '/webprogg/images/SecondImageRight.jpg',
];

 $footerCompanyLinks = [
    ['label' => 'Home', 'href' => '/webprogg/index.php'],
    ['label' => 'Listings', 'href' => '/webprogg/Listings/listing.php'],
    ['label' => 'How It Works', 'href' => '/webprogg/host/howitworks.php'],
    ['label' => 'Contacts', 'href' => '/webprogg/misc/contacts.php'],
];

 $footerInvolvedLinks = [
    ['label' => 'Become A Host', 'href' => '/webprogg/host/becomeahost.php'],
    ['label' => 'Hive Club', 'href' => '/webprogg/hiveclub.php'],
    ['label' => 'List Your Space', 'href' => '/webprogg/host/becomeahost.php'],
    ['label' => 'Terms Of Service', 'href' => '#'],
];

 $phoneNumber = '+639275693574';
 $emailAddress = 'RoomHive@gmail.com';
 $currentYear = date('Y');

/*
 * The login card pops up on load for guests only.
 * Logged-in users and admins never see it.
 */
 $showLoginPopup = !$isLoggedIn && !$isAdminLoggedIn;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive</title>

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/motion.css">
    <link rel="stylesheet" href="/webprogg/assets/loginform.css">

    <script>document.documentElement.classList.add("js-animations");</script>

    <style>
        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .footer-admin-link {
            font-size: 12px;
            color: #8B93A6;
            text-decoration: none;
            opacity: 0.8;
        }
        .footer-admin-link:hover { opacity: 1; text-decoration: underline; }

        /* =========================================================
           LOGIN MODAL — SELF-CONTAINED STYLES
           Copied from loginform.css so the popup works even if the
           browser is serving a stale cached copy of that file.
        ========================================================= */

        @keyframes login-card-float {
            0%, 100% {
                transform: translateY(0px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }
            50% {
                transform: translateY(-8px);
                box-shadow: 0 18px 35px rgba(0, 0, 0, 0.12);
            }
        }

        .login-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;

            display: flex;
            justify-content: center;
            align-items: center;

            padding: 30px;

            visibility: hidden;
            pointer-events: none;
        }

        .login-modal-overlay.open {
            visibility: visible;
            pointer-events: auto;
        }

        .login-modal-backdrop {
            position: absolute;
            inset: 0;

            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);

            opacity: 0;
            transition: opacity 0.35s ease;
        }

        .login-modal-overlay.open .login-modal-backdrop {
            opacity: 1;
        }

        .login-modal-overlay .login-card {
            position: relative;
            z-index: 1;

            opacity: 0;
            transform: translateY(24px) scale(0.96);

            transition:
                opacity 0.35s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .login-modal-overlay.open .login-card {
            opacity: 1;
            transform: translateY(0) scale(1);

            animation: login-card-float 4.5s ease-in-out 0.35s infinite;
        }

        .login-modal-close {
            position: absolute;
            top: 10px;
            right: 14px;

            background: none;
            border: none;

            font-size: 22px;
            line-height: 1;
            color: #8b93a6;

            cursor: pointer;
        }

        .login-modal-close:hover {
            color: #1c2a38;
        }

        @media (prefers-reduced-motion: reduce) {
            .login-modal-overlay .login-card,
            .login-modal-backdrop {
                transition: none;
            }
            .login-modal-overlay.open .login-card {
                animation: none;
            }
        }
    </style>
</head>

<body>

<!-- =========================
     NAVIGATION BAR
========================== -->

<nav class="navbar">

    <a href="/webprogg/index.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <div class="nav-links">

        <?php foreach ($navLinks as $link): ?>

            <a
                href="<?php echo htmlspecialchars($link['href']); ?>"
                class="<?php echo htmlspecialchars($link['class']); ?>"
            >
                <?php echo htmlspecialchars($link['label']); ?>
            </a>

        <?php endforeach; ?>

        <a
            href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
            class="list-space<?php echo $isLoggedIn ? '' : ' js-open-login'; ?>"
        >
            LIST YOUR SPACE
        </a>

    </div>

</nav>


<!-- =========================
     HERO SECTION
========================== -->

<section class="hero">

    <img
        class="hero-image"
        src="/webprogg/images/Living_Room.png"
        alt="Living Room"
    >

    <div class="hero-overlay"></div>

    <div class="hero-content">

        <h1>
            New Downtown<br>
            Rooms Available<br>
            Now!
        </h1>

        <p>
            Discover our latest downtown room listings <br>
            modern spaces in prime locations, ready<br>
            for you to move in today.
        </p>

        <div class="hero-buttons">

            <a
                href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
                class="btn-primary<?php echo $isLoggedIn ? '' : ' js-open-login'; ?>"
            >
                LIST YOUR SPACE
            </a>

            <a
                href="/webprogg/Listings/listing.php"
                class="btn-secondary"
            >
                VIEW LISTING
            </a>

        </div>

    </div>

</section>


<!-- =========================
     HIVE CLUB SECTION
========================== -->

<section class="hive-club">

    <div class="club-content">

        <img
            src="/webprogg/images/Crown_Logo.png"
            alt="Hive Club Crown"
            class="crown-logo"
        >

        <div class="club-text">

            <span class="membership">
                MEMBERSHIP
            </span>

            <h3>
                Join The VIP Hive Club
            </h3>

            <p>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            </p>

        </div>

    </div>

    <a href="/webprogg/hiveclub.php" class="join-button">
        JOIN NOW
    </a>

</section>


<!-- =========================
     TOP LISTINGS SECTION
========================== -->

<section class="listings">

    <span class="listings-eyebrow">
        EXPLORE ROOMHIVE
    </span>

    <h2 class="listings-title">
        Check Out Our Top Listing!
    </h2>

    <div class="listings-grid">

        <?php foreach ($listings as $listing): ?>

            <a
                href="/webprogg/Listings/listing.php?category=<?php echo urlencode($listing['slug']); ?>"
                class="listing-card"
            >

                <img
                    src="<?php echo htmlspecialchars($listing['image']); ?>"
                    alt="<?php echo htmlspecialchars($listing['name']); ?>"
                >

                <div class="listing-info">

                    <span class="listing-name">
                        <?php echo htmlspecialchars($listing['name']); ?>
                    </span>

                    <span class="listing-link">
                        VIEW LISTING
                    </span>

                </div>

            </a>

        <?php endforeach; ?>

    </div>

</section>


<!-- =========================
     ABOUT US SECTION
========================== -->

<section class="about">

    <div class="about-text">

        <span class="about-eyebrow">
            WHO WE ARE
        </span>

        <h2>
            About RoomHive
        </h2>

        <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            Accumsan in nisi nisi scelerisque eu. Varius vel pharetra vel turpis nunc eget.
        </p>

        <p>
            Adipiscing elit pellentesque habitant morbi tristique senectus.
            Ut faucibus pulvinar elementum integer enim neque volutpat.
            Nibh tellus molestie nunc non blandit massa.
        </p>

        <a href="#" class="about-button">
            MORE ABOUT US
        </a>

    </div>

    <div class="about-image">

        <img
            src="/webprogg/images/3rdPageImage.png"
            alt="RoomHive team handing over keys"
        >

    </div>

</section>


<!-- =========================
     REASON WHY SECTION
========================== -->

<section class="reasons">

    <div class="reasons-header">

        <div class="reasons-heading">

            <span class="reasons-eyebrow">
                WHY CHOOSE US
            </span>

            <h2>
                Reason Why RoomHive Is The Best
            </h2>

        </div>

        <p class="reasons-desc">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua
            ut enim ad minim veniam.
        </p>

    </div>


    <div class="reasons-grid">

        <?php foreach ($reasons as $reason): ?>

            <div class="reason-card">

                <img
                    src="<?php echo htmlspecialchars($reason['icon']); ?>"
                    alt="<?php echo htmlspecialchars($reason['title']); ?>"
                >

                <div class="reason-text">

                    <h4>
                        <?php echo htmlspecialchars($reason['title']); ?>
                    </h4>

                    <p>
                        <?php echo htmlspecialchars($reason['text']); ?>
                    </p>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- =========================
     TESTIMONIALS SECTION
========================== -->

<section class="testimonials">

    <div class="testimonials-text">

        <span class="testimonials-eyebrow">
            TESTIMONIALS
        </span>

        <h2>
            Our Happy Tenants
        </h2>

        <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>

        <div class="testimonials-nav">

            <button
                class="nav-arrow"
                aria-label="Previous testimonial"
            >
                <img
                    src="/webprogg/images/TenantsLeftArrow.png"
                    alt="Previous"
                >
            </button>

            <div class="testimonials-dots">

                <?php for ($i = 0; $i < count($testimonials); $i++): ?>

                    <span
                        class="dot<?php echo ($i === count($testimonials) - 1) ? ' active' : ''; ?>"
                    ></span>

                <?php endfor; ?>

            </div>

            <button
                class="nav-arrow"
                aria-label="Next testimonial"
            >
                <img
                    src="/webprogg/images/TenantsRightArrows.png"
                    alt="Next"
                >
            </button>

        </div>

    </div>


    <div class="testimonials-slider">
        <div class="testimonials-track">

            <?php foreach ($testimonials as $testimonial): ?>

                <div class="testimonial-card">

                    <p class="testimonial-quote">
                        "<?php echo htmlspecialchars($testimonial['quote']); ?>"
                    </p>

                    <p class="testimonial-body">
                        <?php echo htmlspecialchars($testimonial['body']); ?>
                    </p>

                    <div class="testimonial-author">

                        <img
                            src="/webprogg/images/HappyTenantsHumanIcon.png"
                            alt="Tenant"
                        >

                        <span>
                            <?php echo htmlspecialchars($testimonial['author']); ?>
                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>
    </div>

</section>


<!-- =========================
     FINAL CTA
========================== -->

<section class="final-cta">

    <div class="cta-image-strip">

        <?php foreach ($ctaImages as $image): ?>

            <img
                src="<?php echo htmlspecialchars($image); ?>"
                alt="RoomHive interior"
            >

        <?php endforeach; ?>

    </div>

    <div class="cta-box">

        <span class="cta-eyebrow">
            READY TO MOVE?
        </span>

        <h2 class="cta-title">
            Find A Place You'll Love To Call Home
        </h2>

        <a
            href="/webprogg/Listings/listing.php"
            class="cta-button"
        >
            VIEW LISTINGS
        </a>

    </div>

</section>


<!-- =========================
     DUAL CTA
========================== -->

<section class="dual-cta">

    <div class="dual-cta-panel hive-panel">

        <img
            src="/webprogg/images/Crown_Logo.png"
            alt="Hive Club Crown"
            class="dual-cta-icon"
        >

        <h3>
            Join The Hive Club
        </h3>

        <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>

        <a
            href="/webprogg/hiveclub.php"
            class="dual-cta-button hive-button"
        >
            JOIN NOW
        </a>

    </div>


    <div class="dual-cta-panel host-panel">

        <img
            src="/webprogg/images/HouseIcon.png"
            alt="Become a Host"
            class="dual-cta-icon"
        >

        <h3>
            Become A Host
        </h3>

        <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>

        <a
            href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
            class="dual-cta-button host-button<?php echo $isLoggedIn ? '' : ' js-open-login'; ?>"
        >
            LIST YOUR SPACE
        </a>

    </div>

</section>


<!-- =========================
     FOOTER
========================== -->

<footer class="site-footer">

    <div class="footer-top">

        <div class="footer-brand">

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive Logo"
                class="footer-logo"
            >

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt="Phone"
                >

                <span>
                    <?php echo htmlspecialchars($phoneNumber); ?>
                </span>

            </div>

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt="Email"
                >

                <span>
                    <?php echo htmlspecialchars($emailAddress); ?>
                </span>

            </div>

        </div>


        <div class="footer-links">

            <span class="footer-heading">
                COMPANY
            </span>

            <?php foreach ($footerCompanyLinks as $link): ?>

                <a
                    href="<?php echo htmlspecialchars($link['href']); ?>"
                >
                    <?php echo htmlspecialchars($link['label']); ?>
                </a>

            <?php endforeach; ?>

        </div>


        <div class="footer-links">

            <span class="footer-heading">
                GET INVOLVED
            </span>

            <?php foreach ($footerInvolvedLinks as $link): ?>

                <a
                    href="<?php echo htmlspecialchars($link['href']); ?>"
                >
                    <?php echo htmlspecialchars($link['label']); ?>
                </a>

            <?php endforeach; ?>

        </div>


        <div class="footer-contact">

            <span class="footer-heading">
                GET THE APP
            </span>

            <div class="footer-app-badges">

                <img
                    src="/webprogg/images/AppStore.jpg"
                    alt="Download on the App Store"
                >

                <img
                    src="/webprogg/images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >

            </div>

        </div>

    </div>


    <div class="footer-bottom">

        <p>
            &copy;
            <?php echo htmlspecialchars($currentYear); ?>
            RoomHive. All rights reserved.
        </p>

        <a
            href="<?php echo $isAdminLoggedIn ? '/webprogg/admin/admin.php' : '/webprogg/auth/adminlogin.php'; ?>"
            class="footer-admin-link"
        >
            Admin
        </a>

    </div>

</footer>


<!-- =========================================================
     LOGIN MODAL — pops up over index.php for logged-out visitors
========================================================= -->
<div
    class="login-modal-overlay"
    id="loginModalOverlay"
    aria-hidden="true"
>

    <div class="login-modal-backdrop" data-close-login></div>

    <div class="login-card">

        <button type="button" class="login-modal-close" data-close-login aria-label="Close">
            &times;
        </button>

        <!-- RoomHive Logo -->
        <div class="login-logo">

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive Logo"
            >

        </div>

        <!-- Login Title -->
        <div class="login-header">

            <h1>Welcome Back!</h1>

        </div>

        <!-- Error Message (filled in by JS on a failed attempt) -->
        <div class="login-error" id="loginModalError" hidden></div>

        <!-- Login Form -->
        <form id="loginModalForm" novalidate>

            <input type="hidden" name="redirect" value="">

            <!-- Email -->
            <div class="login-input-group">

                <label for="modal-email">

                    <img
                        src="/webprogg/images/EmailIcon.jpg"
                        alt="Email"
                    >

                    <span>Email Address</span>

                </label>

                <input
                    type="email"
                    id="modal-email"
                    name="email"
                    autocomplete="email"
                    required
                >

            </div>

            <!-- Password -->
            <div class="login-input-group">

                <label for="modal-password">

                    <img
                        src="/webprogg/images/LockIcon.png"
                        alt="Password"
                    >

                    <span>Password</span>

                </label>

                <input
                    type="password"
                    id="modal-password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- Forgot Password -->
            <div class="forgot-password">

                <a href="/webprogg/auth/forgotpassword.php">
                    Forgot Password?
                </a>

            </div>

            <!-- Login Button -->
            <button
                type="submit"
                class="login-button"
            >
                Log in
            </button>

        </form>

        <!-- Google -->
        <button
            type="button"
            class="social-login google-login"
            onclick="window.location.href='google-login.php'"
        >

            <img
                src="/webprogg/images/Googlecons.png"
                alt="Google"
            >

            <span>Continue with Google</span>

        </button>

        <!-- Apple -->
        <button
            type="button"
            class="social-login apple-login"
            onclick="window.location.href='apple-login.php'"
        >

            <img
                src="/webprogg/images/AppleIcons.png"
                alt="Apple"
            >

            <span>Continue with Apple</span>

        </button>

        <!-- Create Account -->
        <div class="create-account">

            <span>Not registered yet?</span>

            <a href="/webprogg/auth/createaccount.php">
                Create Account Here
            </a>

        </div>

    </div>

</div>


<script src="/webprogg/assets/javaScript.js"></script>

<!-- =========================================================
     LOGIN POPUP SCRIPT — SELF-CONTAINED
     Lives directly in index.php on purpose: it cannot be
     broken by a cached or erroring javaScript.js.
========================================================= -->
<script>
(function () {
    "use strict";

    /* PHP decides: guests get true, logged-in users/admins get false */
    var AUTO_OPEN = <?php echo $showLoginPopup ? 'true' : 'false'; ?>;

    var overlay  = document.getElementById("loginModalOverlay");
    var form     = document.getElementById("loginModalForm");
    var errorBox = document.getElementById("loginModalError");

    if (!overlay || !form) {
        return;
    }

    function openModal(redirectTarget) {
        var redirectInput = overlay.querySelector('input[name="redirect"]');

        if (redirectInput) {
            redirectInput.value = redirectTarget || "";
        }

        overlay.classList.add("open");
        overlay.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";

        var emailField = document.getElementById("modal-email");

        if (emailField) {
            window.setTimeout(function () {
                emailField.focus();
            }, 400);
        }
    }

    function closeModal() {
        overlay.classList.remove("open");
        overlay.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";

        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = "";
        }
    }

    /* ---- POP UP AUTOMATICALLY ~0.5s after the page loads ---- */
    if (AUTO_OPEN) {
        window.setTimeout(function () {
            openModal("");
        }, 500);
    }

    /* ---- Open via BECOME A HOST / LIST YOUR SPACE links,
            close via backdrop or the × button ---- */
    document.addEventListener("click", function (event) {
        if (!event.target || !event.target.closest) {
            return;
        }

        var trigger = event.target.closest(".js-open-login");

        if (trigger) {
            event.preventDefault();

            var redirect = "";

            try {
                var url = new URL(trigger.href, window.location.origin);
                redirect = url.searchParams.get("redirect") || "";
            } catch (err) {
                redirect = "";
            }

            openModal(redirect);

            return;
        }

        if (event.target.closest("[data-close-login]")) {
            closeModal();
        }
    });

    /* ---- Esc closes it ---- */
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && overlay.classList.contains("open")) {
            closeModal();
        }
    });

    /* ---- Submit via fetch so errors show inside the popup ---- */
    form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (errorBox) {
            errorBox.hidden = true;
        }

        var submitBtn = form.querySelector(".login-button");
        var originalLabel = submitBtn ? submitBtn.textContent : "";

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Logging in...";
        }

        fetch("/webprogg/auth/loginform.php", {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: new FormData(form),
            credentials: "same-origin",
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    window.location.href = data.redirect;
                    return;
                }

                if (errorBox) {
                    errorBox.textContent =
                        (data && data.error) || "Something went wrong.";
                    errorBox.hidden = false;
                }
            })
            .catch(function () {
                if (errorBox) {
                    errorBox.textContent =
                        "Couldn't reach the server. Please try again.";
                    errorBox.hidden = false;
                }
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalLabel || "Log in";
                }
            });
    });
})();
</script>

</body>

</html>