<?php
session_start();
/*
 * =========================================================
 * ROOMHIVE USER HOME - AUTHENTICATION CHECK
 * =========================================================
 *
 * Only logged-in users can access userhome.php.
 */

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: loginform.php");
    exit();
}

/*
 * Get the logged-in user's name.
 */
$userName = $_SESSION['user_name'] ?? 'User';


  // =========================
  // PAGE DATA Users Home
  // =========================

$navLinks = [
    ['label' => 'HOME', 'href' => 'usershome.php', 'class' => 'active'],
    ['label' => 'LISTINGS', 'href' => 'listing.php', 'class' => ''],
    ['label' => 'HOW IT WORKS', 'href' => 'howitworks.php', 'class' => ''],
    ['label' => 'BECOME A HOST', 'href' => 'becomeahost.php', 'class' => ''],
    ['label' => 'HIVE CLUB', 'href' => 'hiveclub.php', 'class' => ''],
    ['label' => 'CONTACTS', 'href' => 'contacts.php', 'class' => ''],
];

  $listings = [
    ['name' => 'STUDIO LOFT',     'image' => 'StudioLoft.png'],
    ['name' => 'SHARED ROOM',     'image' => 'SharedBedroom.png'],
    ['name' => 'ENTIRE HOUSE',    'image' => 'EntireHouse.png'],
    ['name' => 'PRIVATE ROOM',    'image' => 'PrivateRoom.png'],
    ['name' => 'BOARDING HOUSE',  'image' => 'BoardingHouse.png'],
    ['name' => 'APARTMENT',       'image' => 'Apartment.png'],
  ];

  $reasons = [
    [
      'icon'  => '247SupportsIcons.png',
      'title' => '24/7 Support',
      'text'  => 'Our team is on call around the clock for hosts and tenants.',
    ],
    [
      'icon'  => 'VerifiedListingsIcons.png',
      'title' => 'Verified Listings',
      'text'  => 'Every listing is hand-reviewed and checked by our team before it goes live.',
    ],
    [
      'icon'  => 'SecurePaymentsIcon.png',
      'title' => 'Secure Payments',
      'text'  => 'Your booking and deposits are protected end-to-end.',
    ],
    [
      'icon'  => 'NoHiddenFeesIcons.png',
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
  ];

  $ctaImages = [
    'FirstImageLeft.jpg',
    'SecondImageLeft.jpg',
    'MiddleImage.avif',
    'FirstImageRight.jpg',
    'SecondImageRight.jpg',
  ];

  $footerCompanyLinks = [
    ['label' => 'Home', 'href' => 'index.php'],
    ['label' => 'Listings', 'href' => 'listings.php'],
    ['label' => 'How It Works', 'href' => 'howitworks.php'],
    ['label' => 'Contacts', 'href' => 'contacts.php'],
  ];

  $footerInvolvedLinks = [
    ['label' => 'Become A Host', 'href' => 'becomeahost.php'],
    ['label' => 'Hive Club', 'href' => 'hiveclub.php'],
    ['label' => 'List Your Space', 'href' => 'becomeahost.php'],
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
    <link rel="stylesheet" href="style.css" />
  </head>
 
  <body>
   <!-- =========================
     NAVIGATION BAR
========================== -->
<nav class="navbar">

    <!-- LOGO -->
    <a href="usershome.php" class="logo">
        <img src="images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION LINKS -->
    <div class="nav-links">

        <?php foreach ($navLinks as $link): ?>
            <a
                href="<?php echo htmlspecialchars($link['href']); ?>"
                class="<?php echo htmlspecialchars($link['class']); ?>"
            >
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        <?php endforeach; ?>

        <!-- MY ACCOUNT DROPDOWN -->
        <div class="account-dropdown js-account-dropdown">

            <button
                type="button"
                class="my-account js-account-toggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <img src="images/MyAccountIcon.png" alt="My Account">
                <span>MY ACCOUNT</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu">

                <?php if (isset($_SESSION['is_host']) && $_SESSION['is_host'] === true): ?>
                    <a href="hostdashboard.php">
                        Host Dashboard
                    </a>
                <?php endif; ?>

                <a href="myaccount.php">
                    My Account
                </a>

                <a href="logout.php">
                    Logout
                </a>

            </div>

        </div>

    </div>

</nav>

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
<!-- The dropdown's open/close behavior now lives in javaScript.js
     (shared by every page instead of a copy-pasted inline script). -->

    <!-- =========================
         HERO SECTION
    ========================== -->
    <section class="hero">
      <img class="hero-image" src="images/Living_Room.png" alt="Living Room" />
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
          <button class="btn-primary">LIST YOUR SPACE</button>
          <button class="btn-secondary">VIEW LISTING</button>
        </div>
      </div>
    </section>

    <!-- =========================
         HIVE CLUB SECTION
    ========================== -->
    <section class="hive-club">
      <div class="club-content">
        <!-- CROWN LOGO -->
        <img src="images/Crown_Logo.png" alt="Hive Club Crown" class="crown-logo" />
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
      <a href="#" class="join-button">JOIN NOW</a>
    </section>

    <!-- =========================
         TOP LISTINGS SECTION
    ========================== -->
    <section class="listings">
      <span class="listings-eyebrow">EXPLORE ROOMHIVE</span>
      <h2 class="listings-title">Check Out Our Top Listing!</h2>
      <div class="listings-grid">
        <?php foreach ($listings as $listing): ?>
          <a href="#" class="listing-card">
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
        <a href="#" class="about-button">MORE ABOUT US</a>
      </div>
      <div class="about-image">
        <img src="images/3rdPageImage.png" alt="RoomHive team handing over keys" />
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
            <img src="images/TenantsLeftArrow.png" alt="Previous" />
          </button>
          <div class="testimonials-dots">
            <?php for ($i = 0; $i < count($testimonials); $i++): ?>
              <span class="dot<?php echo ($i === count($testimonials) - 1) ? ' active' : ''; ?>"></span>
            <?php endfor; ?>
          </div>
          <button class="nav-arrow" aria-label="Next testimonial">
            <img src="images/TenantsRightArrows.png" alt="Next" />
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
                <img src="images/HappyTenantsHumanIcon.png" alt="Tenant" />
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
        <a href="#" class="cta-button">VIEW LISTINGS</a>
      </div>
    </section>

    <!-- =========================
         JOIN HIVE CLUB / BECOME A HOST SECTION
    ========================== -->
    <section class="dual-cta">
      <div class="dual-cta-panel hive-panel">
        <img src="images/Crown_Logo.png" alt="Hive Club Crown" class="dual-cta-icon" />
        <h3>Join The Hive Club</h3>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>
        <a href="#" class="dual-cta-button hive-button">JOIN NOW</a>
      </div>
      <div class="dual-cta-panel host-panel">
        <img src="images/HouseIcon.png" alt="Become a Host" class="dual-cta-icon" />
        <h3>Become A Host</h3>
        <p>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
          eiusmod tempor incididunt ut labore et dolore magna aliqua.
        </p>
        <a href="becomeahost.php" class="dual-cta-button host-button">LIST YOUR SPACE</a>
      </div>
    </section>

    <!-- =========================
         FOOTER
    ========================== -->
    <footer class="site-footer">
      <div class="footer-top">
        <div class="footer-brand">
          <img
            src="images/RoomHiveLogos.png"
            alt="RoomHive Logo"
            class="footer-logo"
          />
          <div class="footer-contact-line">
            <img src="images/PhoneIcon.jpg" alt="Phone" />
            <span><?php echo htmlspecialchars($phoneNumber); ?></span>
          </div>
          <div class="footer-contact-line">
            <img src="images/EmailIcon.jpg" alt="Email" />
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
            <img src="images/AppStore.jpg" alt="Download on the App Store" />
            <img src="images/GooglePlay.jpg" alt="Get it on Google Play" />
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; <?php echo htmlspecialchars($currentYear); ?> RoomHive. All rights reserved.</p>
      </div>
    </footer>

    <script src="javaScript.js"></script>
  </body>
  
 </html>