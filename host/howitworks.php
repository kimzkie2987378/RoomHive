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

/* Notification bell badge count — same placeholder used across
   every logged-in page's navbar until real notifications land. */
 $notification_count = 0;

/* NEW — FLOATING LOGIN MODAL
   Guests get the login card popped over the page once per
   browser session. Flip to false to disable auto-open (the
   modal still opens from the BECOME A HOST buttons). */
 $autoOpenLoginPopup = !$isLoggedIn && empty($_SESSION['admin_logged_in']);

/* =========================================================
   ROOMHIVE - HOW IT WORKS
   ========================================================= */
 $navigation = [
    "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => $isLoggedIn ? "/webprogg/host/becomeahost.php" : "/webprogg/auth/loginform.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
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
    "Hive Club" => "/webprogg/hiveclub.php",
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

    <!-- =====================================================
         NEW — FLOATING LOGIN MODAL STYLES
         Self-contained so it can't be broken by a stale
         cached howitworks.css / style.css.
    ====================================================== -->
    <style>

/* =========================================================
   FLOATING LOGIN MODAL — hive-styled, self-contained
========================================================= */

.lx-modal {
  position: fixed;
  inset: 0;
  z-index: 1200;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 24px;

  visibility: hidden;
  pointer-events: none;
}

.lx-modal.open {
  visibility: visible;
  pointer-events: auto;
}

.lx-modal-backdrop {
  position: absolute;
  inset: 0;

  background: rgba(28, 42, 56, 0.38);
  backdrop-filter: blur(9px);
  -webkit-backdrop-filter: blur(9px);

  opacity: 0;
  transition: opacity 0.3s ease;
}

.lx-modal.open .lx-modal-backdrop {
  opacity: 1;
}

.lx-modal-card {
  position: relative;
  z-index: 1;

  width: 362px;
  max-height: calc(100vh - 48px);
  overflow-y: auto;

  background: #ffffff;

  border-radius: 22px;

  padding: 32px 30px 26px;

  box-shadow: 0 30px 70px rgba(28, 42, 56, 0.35);

  opacity: 0;
  transform: translateY(26px) scale(0.96);

  transition:
    opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.lx-modal.open .lx-modal-card {
  opacity: 1;
  transform: translateY(0) scale(1);

  animation: lxFloat 5s ease-in-out 0.4s infinite;
}

/* Honey accent bar across the top */
.lx-modal-card::before {
  content: "";

  position: absolute;
  top: 0;
  left: 0;
  right: 0;

  height: 5px;

  background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);

  border-radius: 22px 22px 0 0;
}

.lx-modal-close {
  position: absolute;
  top: 12px;
  right: 14px;

  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  background: #f4f1e7;

  border: none;
  border-radius: 50%;

  color: #6b7684;

  font-size: 17px;
  line-height: 1;

  cursor: pointer;

  transition:
    background 0.15s ease,
    color 0.15s ease,
    transform 0.15s ease;
}

.lx-modal-close:hover {
  background: #eda423;

  color: #ffffff;

  transform: rotate(90deg);
}

.lx-modal-logo {
  text-align: center;

  margin-bottom: 10px;
}

.lx-modal-logo img {
  width: 96px;

  display: inline-block;
}

.lx-modal-title {
  margin: 0 0 16px;

  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.5px;

  text-align: center;

  color: #1c2a38;
}

.lx-error {
  padding: 11px 14px;

  margin-bottom: 14px;

  background: #fdecec;

  border: 1px solid #f3b9b9;
  border-radius: 11px;

  color: #a4302f;

  font-size: 0.82rem;
  font-weight: 500;

  text-align: center;
}

.lx-field {
  margin-bottom: 12px;
}

.lx-field label {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 6px;

  color: #1c2a38;

  font-size: 0.82rem;
  font-weight: 500;
}

.lx-field label img {
  width: 16px;
  height: 16px;

  object-fit: contain;
}

.lx-input {
  width: 100%;
  height: 44px;

  padding: 0 13px;

  background: #fbfcfd;

  border: 1.5px solid #e3e7ec;
  border-radius: 11px;

  outline: none;

  color: #1c2a38;

  font-family: "Poppins", sans-serif;
  font-size: 0.9rem;

  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background 0.2s ease;
}

.lx-input:focus {
  background: #ffffff;

  border-color: #eda423;

  box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.15);
}

.lx-forgot {
  text-align: center;

  margin: 4px 0 12px;
}

.lx-forgot a {
  color: #1c2a38;

  font-size: 0.8rem;
  font-weight: 600;

  text-decoration: none;
}

.lx-forgot a:hover {
  color: #b07708;
}

.lx-submit {
  width: 100%;
  height: 46px;

  background: linear-gradient(135deg, #f6b93b, #eda423);

  border: none;
  border-radius: 12px;

  color: #1c2a38;

  font-family: "Poppins", sans-serif;
  font-size: 0.92rem;
  font-weight: 700;
  letter-spacing: 0.02em;

  cursor: pointer;

  box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);

  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.lx-submit:hover {
  transform: translateY(-2px);

  box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45);
}

.lx-submit:disabled {
  opacity: 0.7;

  cursor: not-allowed;

  transform: none;
}

.lx-divider {
  display: flex;
  align-items: center;
  gap: 10px;

  margin: 16px 0;

  color: #6b7684;

  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.lx-divider::before,
.lx-divider::after {
  content: "";

  flex: 1;
  height: 1px;

  background: #e3e7ec;
}

.lx-social {
  width: 100%;
  height: 44px;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;

  background: #ffffff;

  border: 1.5px solid #e3e7ec;
  border-radius: 11px;

  color: #1c2a38;

  font-family: "Poppins", sans-serif;
  font-size: 0.85rem;
  font-weight: 500;

  cursor: pointer;

  margin-bottom: 10px;

  transition:
    border-color 0.2s ease,
    background 0.2s ease,
    transform 0.2s ease;
}

.lx-social:hover {
  border-color: #eda423;

  background: #fff8ec;

  transform: translateY(-1px);
}

.lx-social img {
  width: 17px;
  height: 17px;

  object-fit: contain;
}

.lx-create {
  margin: 6px 0 0;

  text-align: center;

  color: #6b7684;

  font-size: 0.83rem;
}

.lx-create a {
  color: #b07708;

  font-weight: 700;

  text-decoration: none;
}

.lx-create a:hover {
  text-decoration: underline;
}

@keyframes lxFloat {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-8px);
  }
}

@media (max-width: 480px) {
  .lx-modal {
    padding: 14px;
  }

  .lx-modal-card {
    width: 100%;

    padding: 26px 20px 22px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .lx-modal-card,
  .lx-modal-backdrop {
    transition: none;
  }

  .lx-modal.open .lx-modal-card {
    animation: none;
  }
}

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


                <!-- For guests this links to loginform.php — the
                     floating modal's JS catches it and opens the
                     card in place instead of navigating. -->
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


            <!-- For guests this links to loginform.php — the
                 floating modal's JS catches it and opens the
                 card in place instead of navigating. -->
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


<!-- =========================================================
     NEW — FLOATING LOGIN MODAL (guests)
     Auto-opens once per browser session, and opens from any
     link pointing at loginform.php — the hero and CTA
     BECOME A HOST buttons are caught automatically.
========================================================= -->
<div class="lx-modal" id="lxModal" aria-hidden="true">

    <div class="lx-modal-backdrop" data-lx-close></div>

    <div class="lx-modal-card" role="dialog" aria-modal="true" aria-label="Log in to RoomHive">

        <button type="button" class="lx-modal-close" data-lx-close aria-label="Close">
            &times;
        </button>

        <div class="lx-modal-logo">

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive logo"
            >

        </div>

        <h2 class="lx-modal-title">
            Welcome back!
        </h2>

        <!-- Error message (filled in by JS on a failed attempt) -->
        <div class="lx-error" id="lxModalError" hidden></div>

        <form id="lxModalForm" novalidate>

            <input type="hidden" name="redirect" value="">

            <!-- Email -->
            <div class="lx-field">

                <label for="lx-email">

                    <img
                        src="/webprogg/images/EmailIcon.jpg"
                        alt=""
                    >

                    Email Address

                </label>

                <input
                    class="lx-input"
                    type="email"
                    id="lx-email"
                    name="email"
                    autocomplete="email"
                    required
                >

            </div>

            <!-- Password -->
            <div class="lx-field">

                <label for="lx-password">

                    <img
                        src="/webprogg/images/LockIcon.png"
                        alt=""
                    >

                    Password

                </label>

                <input
                    class="lx-input"
                    type="password"
                    id="lx-password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- Forgot password -->
            <div class="lx-forgot">

                <a href="/webprogg/auth/forgotpassword.php">
                    Forgot Password?
                </a>

            </div>

            <button type="submit" class="lx-submit">
                Log in
            </button>

        </form>

        <div class="lx-divider">
            or
        </div>

        <!-- NOTE: absolute paths — this page lives in /host/,
             so relative hrefs like 'google-login.php' would 404. -->
        <button
            type="button"
            class="lx-social"
            onclick="window.location.href='/webprogg/auth/google-login.php'"
        >

            <img
                src="/webprogg/images/Googlecons.png"
                alt=""
            >

            Continue with Google

        </button>

        <button
            type="button"
            class="lx-social"
            onclick="window.location.href='/webprogg/auth/apple-login.php'"
        >

            <img
                src="/webprogg/images/AppleIcons.png"
                alt=""
            >

            Continue with Apple

        </button>

        <p class="lx-create">

            Not registered yet?

            <a href="/webprogg/auth/createaccount.php">
                Create Account Here
            </a>

        </p>

    </div>

</div>


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


<!-- =========================================================
     NEW — FLOATING LOGIN MODAL SCRIPT (self-contained)
========================================================= -->
<script>
(function () {
    "use strict";

    var modal = document.getElementById("lxModal");
    var form  = document.getElementById("lxModalForm");

    if (!modal || !form) {
        return;
    }

    var errorBox   = document.getElementById("lxModalError");
    var emailInput = document.getElementById("lx-email");

    function openModal() {
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";

        if (emailInput) {
            window.setTimeout(function () {
                emailInput.focus();
            }, 350);
        }
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";

        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = "";
        }
    }

    /* ---- AUTO-OPEN: guests, once per browser session ----
       Shares the same sessionStorage flag as index.php and
       listing.php, so the card only nags once no matter which
       page you land on. */
    var autoOpen = <?php echo $autoOpenLoginPopup ? "true" : "false"; ?>;

    if (autoOpen) {
        var alreadyShown = false;

        try {
            alreadyShown =
                sessionStorage.getItem("rhLoginModalShown") === "1";
            sessionStorage.setItem("rhLoginModalShown", "1");
        } catch (err) {
            /* storage unavailable — just show it */
        }

        if (!alreadyShown) {
            window.setTimeout(openModal, 700);
        }
    }

    /* ---- Any link to loginform.php opens the modal instead of
            navigating — covers the hero BECOME A HOST button and
            the CTA LIST YOUR SPACE button with zero markup
            changes. ---- */
    document.addEventListener("click", function (event) {
        if (!event.target || !event.target.closest) {
            return;
        }

        var loginLink = event.target.closest('a[href*="loginform.php"]');

        if (loginLink) {
            event.preventDefault();
            openModal();
            return;
        }

        if (event.target.closest("[data-lx-close]")) {
            closeModal();
        }
    });

    /* ---- Esc closes it ---- */
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("open")) {
            closeModal();
        }
    });

    /* ---- Submit through loginform.php's AJAX path ----
       The X-Requested-With header makes loginform.php answer
       with JSON (already supported), so errors show inside the
       card and success redirects without a full reload. */
    form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (errorBox) {
            errorBox.hidden = true;
        }

        var submitBtn = form.querySelector(".lx-submit");
        var originalLabel = submitBtn ? submitBtn.textContent : "";

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Logging in...";
        }

        fetch("/webprogg/auth/loginform.php", {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: new FormData(form),
            credentials: "same-origin"
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