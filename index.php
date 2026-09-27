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
 * Whether an admin is currently logged in — the shared footer's
 * Admin link goes straight to the dashboard instead of the
 * login form.
 */
 $isAdminLoggedIn = (
    isset($_SESSION["admin_logged_in"]) &&
    $_SESSION["admin_logged_in"] === true
);

/*
 * SHARED FOOTER SETTINGS (used by includes/footer.php)
 * The homepage is the only page that shows the small Admin link.
 */
 $footerShowAdminLink = true;
 $currentPage = "/webprogg/index.php";

// =========================
// PAGE DATA - index.php
//
// === DUAL CTA ===
// The old Hive Club panel is now a "List Now" panel
// (navy background, gold button) — the band keeps its
// original two-panel navy/gold layout.
//
// === SHARED FOOTER (this version) ===
// The hard-coded footer, $footerCompanyLinks and
// $footerInvolvedLinks are gone — replaced by
// includes/footer.php, the single source of truth
// for footer links. HIVE CLUB REMOVED site-wide.
// =========================

 $navLinks = [
    ['label' => 'HOME', 'href' => '/webprogg/index.php', 'class' => 'active'],
    ['label' => 'LISTINGS', 'href' => '/webprogg/Listings/listing.php', 'class' => ''],
    ['label' => 'HOW IT WORKS', 'href' => '/webprogg/host/howitworks.php', 'class' => ''],

    [
        'label' => 'BECOME A HOST',
        'href' => $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php',
        'class' => ''
    ],

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

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- ?v=2: cache-buster so browsers pick up the hero-gap fixes -->
    <link rel="stylesheet" href="/webprogg/assets/style.css?v=2">
    <link rel="stylesheet" href="/webprogg/assets/motion.css?v=2">

    <script>document.documentElement.classList.add("js-animations");</script>

    <!-- INLINE FOOTER STYLES REMOVED — they now live inside
         includes/footer.php and travel with the include. -->

</head>

<body>

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
            class="list-space"
        >
            LIST YOUR SPACE
        </a>

    </div>

</nav>


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
                class="btn-primary"
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


<section class="dual-cta">

    <div class="dual-cta-panel list-panel">

        <img
            src="/webprogg/images/UploadPhotosIcon-BecomeAHost.png"
            alt="List Your Space"
            class="dual-cta-icon"
        >

        <h3>
            List Now
        </h3>

        <p>
            Got a room, apartment or house sitting empty?
            Put it up in minutes and start receiving booking
            enquiries from verified renters today.
        </p>

        <a
            href="<?php echo $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php'; ?>"
            class="dual-cta-button list-button"
        >
            LIST NOW
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
            class="dual-cta-button host-button"
        >
            LIST YOUR SPACE
        </a>

    </div>

</section>


<!-- =========================================================
     SHARED FOOTER — single source of truth (includes/footer.php)
========================================================= -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>


<script src="/webprogg/assets/javaScript.js"></script>

</body>

</html>