<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
/*
 * =========================================================
 * ROOMHIVE USER HOME - AUTHENTICATION CHECK
 * =========================================================
 *
 * Only logged-in users can access usershome.php.
 */

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /webprogg/auth/loginform.php");
    exit();
}

/* NEW — navbar.php contract: this page is auth-gated, so
   anyone reaching this point is logged in. Without this,
   navbar.php defaults $isLoggedIn to false and renders the
   GUEST navbar (LIST YOUR SPACE, no dropdown). */
 $isLoggedIn = true;

/*
 * -----------------------------------------------------
 * KEEP is_host IN SYNC WITH THE DATABASE
 * -----------------------------------------------------
 */
if (isset($_SESSION['user_id'])) {
    $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
    $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
    $hostRow = $hostCheckStmt->fetch();
    $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
    $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
}

 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $notification_count = 0;

 $userName = $_SESSION['user_name'] ?? 'User';

 $currentPage = '/webprogg/user/usershome.php';

 $navigation = [
    "HOME"          => "/webprogg/user/usershome.php",
    "LISTINGS"      => "/webprogg/Listings/listing.php",
    "HOW IT WORKS"  => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB"     => "/webprogg/hiveclub.php",
    "CONTACTS"      => "/webprogg/misc/contacts.php",
];

 $isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

// =========================
// PAGE DATA Users Home
// =========================

 $listings = [
    ['name' => 'STUDIO LOFT',     'image' => '/webprogg/images/StudioLoft.png',    'type' => 'studio-loft'],
    ['name' => 'SHARED ROOM',     'image' => '/webprogg/images/SharedBedroom.png', 'type' => 'shared-bedroom'],
    ['name' => 'ENTIRE HOUSE',    'image' => '/webprogg/images/EntireHouse.png',   'type' => 'entire-house'],
    ['name' => 'PRIVATE ROOM',    'image' => '/webprogg/images/PrivateRoom.png',   'type' => 'private-room'],
    ['name' => 'BOARDING HOUSE',  'image' => '/webprogg/images/BoardingHouse.png', 'type' => 'boarding-house'],
    ['name' => 'APARTMENT',       'image' => '/webprogg/images/Apartment.png',     'type' => 'apartment'],
];

 $reasons = [
    [
      'icon'  => '/webprogg/images/247SupportsIcons.png',
      'title' => '24/7 Support',
      'text'  => 'Our team is on call around the clock for hosts and tenants.',
    ],
    [
      'icon'  => '/webprogg/images/VerifiedListingsIcons.png',
      'title' => 'Verified Listings',
      'text'  => 'Every listing is hand-reviewed and checked by our team before it goes live.',
    ],
    [
      'icon'  => '/webprogg/images/SecurePaymentsIcon.png',
      'title' => 'Secure Payments',
      'text'  => 'Your booking and deposits are protected end-to-end.',
    ],
    [
      'icon'  => '/webprogg/images/NoHiddenFeesIcons.png',
      'title' => 'No Hidden Fees',
      'text'  => 'What you see is what you pay --- no surprise charges, ever.',
    ],
];

 $testimonials = [
    [
      'quote'  => 'Booking my apartment through RoomHive was seamless.',
      'body'   => 'The team was responsive from day one and the listing matched exactly what was shown. Moving in was stress-free.',
      'author' => 'Isabelle Cruz',
    ],
    [
      'quote'  => 'RoomHive made finding a place stress-free.',
      'body'   => 'Verified listings and quick support meant I never had to worry about hidden costs or surprises on move-in day.',
      'author' => 'Miguel Santos',
    ],
    [
      'quote'  => 'The verified listings gave me peace of mind.',
      'body'   => 'I was nervous about renting sight-unseen, but every photo and detail on RoomHive matched reality. Highly recommend.',
      'author' => 'Andrea Lopez',
    ],
    [
      'quote'  => 'Customer support answered me at midnight.',
      'body'   => 'I had a payment issue right before move-in and the RoomHive team sorted it out within minutes, even that late.',
      'author' => 'Kevin Tan',
    ],
    [
      'quote'  => 'No hidden fees, exactly as advertised.',
      'body'   => 'What I saw on the listing page was what I paid — no surprise charges at checkout or on my first invoice.',
      'author' => 'Grace Villanueva',
    ],
    [
      'quote'  => 'Found my apartment in under a week.',
      'body'   => 'The filters made it easy to narrow down listings by price and amenities, so I didn\'t waste time scrolling.',
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
    ['label' => 'Home', 'href' => '/webprogg/user/usershome.php'],
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

/* =========================================================
   NEW — SPONSORED AD LISTINGS (floating ad widget)
   Same source as listing.php: approved listings with no
   active 'pending' hold, newest first. Up to 8 are pulled
   so the 2 visible ad cards can rotate to fresh listings
   every cycle. Image path handling matches listing.php.
========================================================= */
 $adStmt = $pdo->query(
    "SELECT l.id, l.title, l.location, l.price,
            p.photo_path AS cover_photo
     FROM listings l
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE l.status = 'approved'
     AND NOT EXISTS (
         SELECT 1 FROM bookings b
         WHERE b.listing_id = l.id
             AND b.status = 'pending'
     )
     ORDER BY l.created_at DESC
     LIMIT 8"
 );
 $adListings = array_map(function ($row) {
    $cover = (string) ($row['cover_photo'] ?? '');

    if ($cover === '') {
        $adImage = '/webprogg/images/ListingPlaceholder.png';
    } elseif (preg_match('#^(https?://|/)#i', $cover)) {
        $adImage = $cover; /* already absolute */
    } else {
        /* same convention listing.php uses for cover photos */
        $adImage = '/webprogg/uploads/listing_photos/cover/' . basename($cover);
    }

    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'price'    => number_format((float) $row['price']),
        'image'    => $adImage,
        'url'      => '/webprogg/Listings/listing-detail.php?id=' . (int) $row['id'],
    ];
 }, $adStmt->fetchAll());
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RoomHive</title>
    <!-- Google Font -->
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <!-- CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css" />
    <link rel="stylesheet" href="/webprogg/assets/motion.css" />

    <script>document.documentElement.classList.add("js-animations");</script>

    <!-- =====================================================
         SPONSORED AD WIDGET STYLES
         2 floating listing cards, bottom-right. Each cycle:
         animate in -> hold 10s -> animate out -> next pair.
    ====================================================== -->
    <style>
        .rh-ad-wrap {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 900;

            display: flex;
            flex-direction: column;
            gap: 10px;

            width: 292px;

            pointer-events: none;
        }

        .rh-ad-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            pointer-events: auto;

            background: #1c2a38;
            border-radius: 10px;
            padding: 5px 10px;

            opacity: 0;
            transform: translateY(12px);
            transition: opacity .45s ease, transform .45s ease;
        }
        .rh-ad-wrap.show .rh-ad-header {
            opacity: 1;
            transform: none;
        }

        .rh-ad-header span {
            color: #f6c04e;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .rh-ad-close {
            background: none;
            border: none;
            color: #aab6c2;
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 4px;
        }
        .rh-ad-close:hover { color: #fff; }

        .rh-ad-card {
            pointer-events: auto;

            display: flex;
            gap: 10px;
            align-items: center;

            background: #fff;
            border: 1px solid rgba(28, 42, 56, 0.08);
            border-radius: 14px;
            padding: 8px;

            box-shadow: 0 14px 34px rgba(28, 42, 56, 0.2);

            text-decoration: none;

            opacity: 0;
            transform: translateY(18px) scale(.97);

            transition:
                opacity .5s ease,
                transform .5s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .rh-ad-card.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .rh-ad-card.hide {
            opacity: 0;
            transform: translateY(10px) scale(.98);
        }

        .rh-ad-card:nth-child(3) { transition-delay: .12s; } /* second card follows */

        .rh-ad-thumb {
            width: 74px;
            height: 74px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            background: #F0EEE6;
        }

        .rh-ad-body { min-width: 0; flex: 1; }

        .rh-ad-title {
            margin: 0;
            font-size: 12.5px;
            font-weight: 700;
            color: #1c2a38;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rh-ad-loc {
            margin: 2px 0 0;
            font-size: 11px;
            color: #6b7684;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rh-ad-price {
            margin: 4px 0 0;
            font-size: 12.5px;
            font-weight: 800;
            color: #b07708;
        }

        .rh-ad-cta {
            display: inline-block;
            margin-top: 3px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            color: #dd930f;
        }

        .rh-ad-card:hover { border-color: rgba(237, 164, 35, .55); }

        @media (max-width: 640px) {
            .rh-ad-wrap { width: 236px; right: 12px; bottom: 12px; }
            .rh-ad-thumb { width: 60px; height: 60px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .rh-ad-card,
            .rh-ad-header {
                transition: none !important;
            }
        }
    </style>
  </head>

  <body>

    <!-- =========================
         NAVIGATION BAR
    ========================== -->

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
    <!-- =========================
         HERO SECTION
    ========================== -->
    <section class="hero">
      <img class="hero-image" src="/webprogg/images/Living_Room.png" alt="Living Room" />
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <p class="user-welcome">
    Welcome, <?php echo htmlspecialchars($userName); ?>!
</p>
        <h1>
          New Downtown<br />
          Rooms Available<br />
          Now!
        </h1>
        <p>
          Discover our latest downtown room listings <br />
          modern spaces in prime locations, ready<br />
          for you to move in today.
        </p>
        <div class="hero-buttons">
          <a href="/webprogg/host/becomeahost.php" class="btn-primary">LIST YOUR SPACE</a>
          <a href="/webprogg/Listings/listing.php" class="btn-secondary">VIEW LISTING</a>
        </div>
      </div>
    </section>

    <!-- =========================
         HIVE CLUB SECTION
    ========================== -->
    <section class="hive-club">
      <div class="club-content">
        <!-- CROWN LOGO -->
        <img src="/webprogg/images/Crown_Logo.png" alt="Hive Club Crown" class="crown-logo" />
        <!-- CLUB INFORMATION -->
        <div class="club-text">
          <span class="membership">MEMBERSHIP</span>
          <h3>Join The VIP Hive Club</h3>
          <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
            eiusmod tempor incididunt ut labore et dolore magna aliqua.
          </p>
        </div>
      </div>
      <!-- JOIN BUTTON -->
      <a href="/webprogg/hiveclub.php" class="join-button">JOIN NOW</a>
    </section>

    <!-- =========================
         TOP LISTINGS SECTION
    ========================== -->
    <section class="listings">
      <span class="listings-eyebrow">EXPLORE ROOMHIVE</span>
      <h2 class="listings-title">Check Out Our Top Listing!</h2>
      <div class="listings-grid">
        <?php foreach ($listings as $listing): ?>
          <a href="/webprogg/Listings/listing.php?type=<?php echo urlencode($listing['type']); ?>" class="listing-card">
            <img src="<?php echo htmlspecialchars($listing['image']); ?>" alt="<?php echo htmlspecialchars($listing['name']); ?>" />
            <div class="listing-info">
              <span class="listing-name"><?php echo htmlspecialchars($listing['name']); ?></span>
              <span class="listing-link">VIEW LISTING</span>
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
        <span class="about-eyebrow">WHO WE ARE</span>
        <h2>About RoomHive</h2>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua. Accumsan
          in nisi nisi scelerisque eu. Varius vel pharetra vel turpis nunc eget.
        </p>
        <p>
          Adipiscing elit pellentesque habitant morbi tristique senectus. Ut
          faucibus pulvinar elementum integer enim neque volutpat. Nibh tellus
          molestie nunc non blandit massa.
        </p>
        <a href="/webprogg/host/howitworks.php" class="about-button">MORE ABOUT US</a>
      </div>
      <div class="about-image">
        <img src="/webprogg/images/3rdPageImage.png" alt="RoomHive team handing over keys" />
      </div>
    </section>

    <!-- =========================
         REASON WHY SECTION
    ========================== -->
    <section class="reasons">
      <div class="reasons-header">
        <div class="reasons-heading">
          <span class="reasons-eyebrow">WHY CHOOSE US</span>
          <h2>Reason Why RoomHive Is The Best</h2>
        </div>
        <p class="reasons-desc">
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad
          minim veniam.
        </p>
      </div>
      <div class="reasons-grid">
        <?php foreach ($reasons as $reason): ?>
          <div class="reason-card">
            <img src="<?php echo htmlspecialchars($reason['icon']); ?>" alt="<?php echo htmlspecialchars($reason['title']); ?>" />
            <div class="reason-text">
              <h4><?php echo htmlspecialchars($reason['title']); ?></h4>
              <p><?php echo htmlspecialchars($reason['text']); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- =========================
         HAPPY TENANTS / TESTIMONIALS SECTION
    ========================== -->
    <section class="testimonials">
      <div class="testimonials-text">
        <span class="testimonials-eyebrow">TESTIMONIALS</span>
        <h2>Our Happy Tenants</h2>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>
        <div class="testimonials-nav">
          <button class="nav-arrow" aria-label="Previous testimonial">
            <img src="/webprogg/images/TenantsLeftArrow.png" alt="Previous" />
          </button>
          <div class="testimonials-dots">
            <?php for ($i = 0; $i < count($testimonials); $i++): ?>
              <span class="dot<?php echo ($i === 0) ? ' active' : ''; ?>"></span>
            <?php endfor; ?>
          </div>
          <button class="nav-arrow" aria-label="Next testimonial">
            <img src="/webprogg/images/TenantsRightArrows.png" alt="Next" />
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
                <img src="/webprogg/images/HappyTenantsHumanIcon.png" alt="Tenant" />
                <span><?php echo htmlspecialchars($testimonial['author']); ?></span>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>
    </section>

    <!-- =========================
         READY TO MOVE / FINAL CTA SECTION
    ========================== -->
    <section class="final-cta">
      <div class="cta-image-strip">
        <?php foreach ($ctaImages as $image): ?>
          <img src="<?php echo htmlspecialchars($image); ?>" alt="RoomHive interior" />
        <?php endforeach; ?>
      </div>
      <div class="cta-box">
        <span class="cta-eyebrow">READY TO MOVE?</span>
        <h2 class="cta-title">Find A Place You'll Love To Call Home</h2>
        <a href="/webprogg/Listings/listing.php" class="cta-button">VIEW LISTINGS</a>
      </div>
    </section>

    <!-- =========================
         JOIN HIVE CLUB / BECOME A HOST SECTION
    ========================== -->
    <section class="dual-cta">
      <div class="dual-cta-panel hive-panel">
        <img src="/webprogg/images/Crown_Logo.png" alt="Hive Club Crown" class="dual-cta-icon" />
        <h3>Join The Hive Club</h3>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>
        <a href="/webprogg/hiveclub.php" class="dual-cta-button hive-button">JOIN NOW</a>
      </div>
      <div class="dual-cta-panel host-panel">
        <img src="/webprogg/images/HouseIcon.png" alt="Become a Host" class="dual-cta-icon" />
        <h3>Become A Host</h3>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>
        <a href="/webprogg/host/becomeahost.php" class="dual-cta-button host-button">LIST YOUR SPACE</a>
      </div>
    </section>

    <!-- =========================
         SPONSORED AD WIDGET (floating)
         2 real listing cards; every 10 seconds they animate
         out and the next pair animates in. Rendered only when
         there is at least one approved listing to show.
    ========================== -->
    <?php if (!empty($adListings)): ?>
    <div class="rh-ad-wrap" id="rhAdWrap" aria-label="Sponsored listings">

        <div class="rh-ad-header">
            <span>Sponsored &middot; For You</span>
            <button type="button" class="rh-ad-close" id="rhAdClose" aria-label="Close ads">&times;</button>
        </div>

        <a href="#" class="rh-ad-card" id="rhAdCard0" data-slot="0">
            <img class="rh-ad-thumb" src="" alt="" id="rhAdImg0">
            <div class="rh-ad-body">
                <p class="rh-ad-title" id="rhAdTitle0"></p>
                <p class="rh-ad-loc" id="rhAdLoc0"></p>
                <p class="rh-ad-price" id="rhAdPrice0"></p>
                <span class="rh-ad-cta">VIEW LISTING &rarr;</span>
            </div>
        </a>

        <a href="#" class="rh-ad-card" id="rhAdCard1" data-slot="1">
            <img class="rh-ad-thumb" src="" alt="" id="rhAdImg1">
            <div class="rh-ad-body">
                <p class="rh-ad-title" id="rhAdTitle1"></p>
                <p class="rh-ad-loc" id="rhAdLoc1"></p>
                <p class="rh-ad-price" id="rhAdPrice1"></p>
                <span class="rh-ad-cta">VIEW LISTING &rarr;</span>
            </div>
        </a>

    </div>
    <?php endif; ?>

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
          />
          <div class="footer-contact-line">
            <img src="/webprogg/images/PhoneIcon.jpg" alt="Phone" />
            <span><?php echo htmlspecialchars($phoneNumber); ?></span>
          </div>
          <div class="footer-contact-line">
            <img src="/webprogg/images/EmailIcon.jpg" alt="Email" />
            <span><?php echo htmlspecialchars($emailAddress); ?></span>
          </div>
        </div>

        <div class="footer-links">
          <span class="footer-heading">COMPANY</span>
          <?php foreach ($footerCompanyLinks as $link): ?>
            <a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
          <?php endforeach; ?>
        </div>

        <div class="footer-links">
          <span class="footer-heading">GET INVOLVED</span>
          <?php foreach ($footerInvolvedLinks as $link): ?>
            <a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
          <?php endforeach; ?>
        </div>

        <div class="footer-contact">
          <span class="footer-heading">GET THE APP</span>
          <div class="footer-app-badges">
            <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store" />
            <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play" />
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; <?php echo htmlspecialchars($currentYear); ?> RoomHive. All rights reserved.</p>
      </div>
    </footer>

    <script src="/webprogg/assets/javaScript.js"></script>

    <!-- =========================================================
         SPONSORED AD ROTATION
         - 2 visible slots, pool of up to 8 real listings
         - Every 10 seconds: fade out -> next pair -> fade in
         - Close button removes the widget for the page
    ========================================================= -->
    <script>
    (function () {
        "use strict";

        var wrap = document.getElementById('rhAdWrap');
        if (!wrap) return;

        var POOL = <?php
            echo json_encode(
                $adListings,
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
        ?>;

        if (!Array.isArray(POOL) || POOL.length === 0) {
            wrap.remove();
            return;
        }

        var HOLD_MS   = 10000; /* each pair stays 10 seconds */
        var FADE_MS   = 550;   /* must match the CSS transition */

        var slots = [
            {
                card:  document.getElementById('rhAdCard0'),
                img:   document.getElementById('rhAdImg0'),
                title: document.getElementById('rhAdTitle0'),
                loc:   document.getElementById('rhAdLoc0'),
                price: document.getElementById('rhAdPrice0')
            },
            {
                card:  document.getElementById('rhAdCard1'),
                img:   document.getElementById('rhAdImg1'),
                title: document.getElementById('rhAdTitle1'),
                loc:   document.getElementById('rhAdLoc1'),
                price: document.getElementById('rhAdPrice1')
            }
        ].filter(function (s) { return s.card; });

        if (!slots.length) { wrap.remove(); return; }

        var cursor = 0;
        var timer  = null;

        function itemAt(i) {
            return POOL[((i % POOL.length) + POOL.length) % POOL.length];
        }

        function fill(slot, listing) {
            slot.card.href = listing.url;
            slot.img.src   = listing.image;
            slot.img.alt   = listing.title;
            slot.title.textContent = listing.title;
            slot.loc.textContent   = listing.location || '';
            slot.price.innerHTML   = '\u20B1' + listing.price + '<span style="font-weight:600;color:#8B93A6;">/month</span>';
        }

        function showPair() {
            for (var i = 0; i < slots.length; i++) {
                fill(slots[i], itemAt(cursor + i));
                slots[i].card.classList.remove('hide');
                slots[i].card.classList.add('show');
            }
            wrap.classList.add('show');
        }

        function hidePair(then) {
            wrap.classList.remove('show');
            for (var i = 0; i < slots.length; i++) {
                slots[i].card.classList.remove('show');
                slots[i].card.classList.add('hide');
            }
            window.setTimeout(then, FADE_MS);
        }

        function cycle() {
            hidePair(function () {
                cursor += slots.length;          /* advance the pool */
                if (cursor >= POOL.length) { cursor = 0; }
                showPair();
            });
        }

        /* First appearance shortly after load */
        window.setTimeout(showPair, 1200);

        /* Then every 10 seconds */
        timer = window.setInterval(cycle, HOLD_MS);

        /* Pause rotating while the tab is hidden so the user
           always gets a full 10s view when they come back */
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                window.clearInterval(timer);
            } else {
                timer = window.setInterval(cycle, HOLD_MS);
            }
        });

        /* Close button — removes the ads for this visit */
        var closeBtn = document.getElementById('rhAdClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                window.clearInterval(timer);
                wrap.remove();
            });
        }
    })();
    </script>

  </body>

</html>