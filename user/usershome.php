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

/*
 * -----------------------------------------------------
 * KEEP is_host IN SYNC WITH THE DATABASE
 * -----------------------------------------------------
 * $_SESSION['is_host'] is only set at login time, so if an
 * admin approves this user's host application (or their
 * host status otherwise changes) during the same session,
 * the session flag goes stale and the nav keeps showing
 * them as a regular user. Re-check the real column on every
 * load so the nav is always accurate.
 */
if (isset($_SESSION['user_id'])) {
    $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
    $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
    $hostRow = $hostCheckStmt->fetch();
    $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
    $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
}

/* Same staleness reasoning as is_host above: the navbar's
   account icon should reflect a freshly-uploaded profile photo
   without requiring the user to log out and back in. */
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

/* Notification bell badge count — same placeholder used across
   every logged-in page's navbar until real notifications land. */
$notification_count = 0;

/*
 * Get the logged-in user's name.
 */
$userName = $_SESSION['user_name'] ?? 'User';

// Current page (used to compute the "active" nav class dynamically,
// the same pattern becomeahost.php uses)
$currentPage = '/webprogg/user/usershome.php';

/* This page is guarded above, so we always reach here logged in. */
$isLoggedIn = true;
$isHost = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

$navigation = [
    'HOME' => '/webprogg/user/usershome.php',
    'LISTINGS' => '/webprogg/Listings/listing.php',
    'HOW IT WORKS' => '/webprogg/host/howitworks.php',
    'BECOME A HOST' => '/webprogg/host/becomeahost.php',
    'HIVE CLUB' => '/webprogg/hiveclub.php',
    'CONTACTS' => '/webprogg/misc/contacts.php',
];


  // =========================
  // PAGE DATA Users Home
  // =========================

$navLinks = [
    ['label' => 'HOME', 'href' => '/webprogg/user/usershome.php'],
    ['label' => 'LISTINGS', 'href' => '/webprogg/Listings/listing.php'],
    ['label' => 'HOW IT WORKS', 'href' => '/webprogg/host/howitworks.php'],
    ['label' => 'BECOME A HOST', 'href' => '/webprogg/host/becomeahost.php'],
    ['label' => 'HIVE CLUB', 'href' => '/webprogg/hiveclub.php'],
    ['label' => 'CONTACTS', 'href' => '/webprogg/misc/contacts.php'],
];

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
  </head>
 
  <body>
   <!-- =========================
     NAVIGATION BAR
========================== -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>

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
  </body>
  
 </html>