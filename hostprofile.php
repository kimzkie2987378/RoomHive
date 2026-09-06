<?php
/* =========================================================
   ROOMHIVE — HOST PROFILE
   hostprofile.php

 
========================================================= */

session_start();
require_once 'db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, name, email, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: loginform.php");
    exit;
}

/* -----------------------------------------------------
   HOST GUARD
   This page is host-only. Anyone logged in but not yet a
   host gets redirected instead of seeing an empty dashboard
   meant for hosts.

   DEBUG NOTE: if you land here unexpectedly, it's because
   $dbUser['is_host'] came back 0 (or NULL) for your account —
   check with:
       SELECT id, name, is_host FROM users WHERE id = <your id>;
   This has nothing to do with a missing file; it's purely
   what's stored in the `is_host` column for that row.

   Redirecting to becomeahost.php once that page exists on
   your server — for now this points to myaccount.php so the
   guard doesn't 404 while that page is still being built.
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: hostprofile.php"); // TODO: change back to becomeahost.php once that file exists
    exit;
}

/* -----------------------------------------------------
   HOST DATA
   Same "no fake data" rule as myaccount.php: phone / location /
   about have no columns in `users` yet, so they stay blank
   until those columns (or an edit-profile flow) exist.
----------------------------------------------------- */
$host = [
    'name'          => $dbUser['name'],
    'avatar'        => 'images/default-avatar.png',
    'location'      => '', // no column in `users` yet
    'email'         => $dbUser['email'],
    'phone'         => '', // no column in `users` yet — left blank on purpose
    'member_since'  => date('F Y', strtotime($dbUser['created_at'])),
    'about'         => '', // no column in `users` yet
];

$notification_count = 0;

/* -----------------------------------------------------
   MY LISTINGS
   Real query against the `listings` table for this host.
----------------------------------------------------- */
$listingsStmt = $pdo->prepare(
    "SELECT id, title, location, exact_address, price, status, created_at
     FROM listings
     WHERE user_id = :id
     ORDER BY created_at DESC"
);
$listingsStmt->execute(['id' => $_SESSION['user_id']]);
$listings = $listingsStmt->fetchAll();

$listings_total = count($listings);

/* -----------------------------------------------------
   PERFORMANCE OVERVIEW
   TODO: Views need a page-view tracking table; Bookings and
   Occupancy Rate need a real bookings table (neither exists
   yet in the schema); Earnings should sum confirmed bookings
   for this host's listings once that table exists too. Until
   then these correctly show 0 instead of invented demo numbers.
   Per-listing Bookings / Occupancy / Earnings below depend on
   the same missing table, so each listing shows "—" for those
   columns for now.
----------------------------------------------------- */
$total_views    = 0;
$total_bookings = 0;
$occupancy_rate = 0;
$total_earnings = 0;

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* Maps a listings.status value to a small status-pill class */
function hp_status_class($status) {
    switch ($status) {
        case 'approved': return 'hp-status-active';
        case 'pending':  return 'hp-status-pending';
        case 'rejected': return 'hp-status-rejected';
        case 'unlisted': return 'hp-status-unlisted';
        default:         return 'hp-status-pending';
    }
}

function hp_status_label($status) {
    switch ($status) {
        case 'approved': return 'Active';
        case 'pending':  return 'Pending';
        case 'rejected': return 'Rejected';
        case 'unlisted': return 'Unlisted';
        default:         return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Host Profile — RoomHive</title>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="hostprofile.css">
</head>
<body>

<!-- =========================================================
     NAVBAR (uses existing style.css — not redefined here,
     identical markup/classes to myaccount.php)
========================================================= -->
<header class="navbar">

    <!-- LOGO -->
    <a href="usershome.php" class="logo">
        <img src="images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION -->
    <nav class="nav-links">

        <a href="usershome.php">HOME</a>
        <a href="listing.php">LISTINGS</a>
        <a href="howitworks.php">HOW IT WORKS</a>
        <a href="becomeahost.php">BECOME A HOST</a>
        <a href="hiveclub.php">HIVE CLUB</a>
        <a href="contacts.php">CONTACTS</a>

        <a href="notifications.php" class="nav-bell">
            <img src="images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <!-- MY ACCOUNT -->
        <div class="account-dropdown js-account-dropdown">

            <button
                type="button"
                class="my-account js-account-toggle"
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
                <a href="hostprofile.php">Host Profile</a>
                <a href="logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<!-- =========================================================
     HOST PROFILE HERO
========================================================= -->
<section class="hp-hero">
  <div class="hp-hero-text">
    <h1>Host Profile</h1>
    <span class="hp-hero-underline"></span>
    <p>Manage your account, listings, and payouts all in one place.</p>
  </div>

  <div class="hp-hero-image">
    <img src="images/hostprofile-hero.jpg" alt="">
  </div>

  <div class="hp-earnings-float">
    <span class="hp-muted">Total Earnings</span>
    <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
    <span class="hp-earnings-note">No earnings data yet</span>
  </div>
</section>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard">

  <!-- SIDEBAR -->
  <aside class="hp-sidebar">

    <div class="hp-sidebar-card">
      <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar">
      <h4><?php echo h($host['name']); ?></h4>
      <span class="hp-host-badge">Host</span>
      <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="hostprofile.php" class="hp-side-link active">
      <img src="images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="mylistings.php" class="hp-side-link">
      <img src="images/mylistingsicon-hostprofile.png" alt="">
      My Listings
    </a>
    <a href="hostbookings.php" class="hp-side-link">
      <img src="images/bookingsicon-userprofile.png" alt="">
      Bookings
    </a>
    <a href="earnings.php" class="hp-side-link">
      <img src="images/totalspenticon-userprofile.png" alt="">
      Earnings
    </a>
    <a href="payouts.php" class="hp-side-link">
      <img src="images/paymentsicon-userprofile.png" alt="">
      Payouts
    </a>
    <a href="hostreviews.php" class="hp-side-link">
      <img src="images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="hostmessages.php" class="hp-side-link">
      <img src="images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="hosteditprofile.php" class="hp-side-link">
      <img src="images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="verification.php" class="hp-side-link">
      <img src="images/verifiedicon-userprofile.png" alt="">
      Verification
    </a>
    <a href="payoutmethods.php" class="hp-side-link">
      <img src="images/payoutmethodsicon-hostprofile.png" alt="">
      Payout Methods
    </a>
    <a href="hostnotificationsettings.php" class="hp-side-link">
      <img src="images/notificationsettings-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="hostsecurity.php" class="hp-side-link">
      <img src="images/lockicon-userprofile.png" alt="">
      Security
    </a>
    <a href="helpcenter.php" class="hp-side-link">
      <img src="images/needhelpicon-userprofile.png" alt="">
      Help Center
    </a>
    <a href="logout.php" class="hp-side-link hp-side-logout">
      <img src="images/logouticon-userprofile.png" alt="">
      Log Out
    </a>

  </aside>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <!-- PROFILE INFORMATION -->
    <section class="hp-card hp-profile-card">

      <div class="hp-card-header">
        <h3>Profile Information</h3>
        <button type="button" class="hp-btn-outline hp-edit-profile" id="hostEditProfileButton">Edit Profile</button>
      </div>

      <div class="hp-profile-body">

        <div class="hp-profile-photo">
          <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>">
          <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
            <img src="images/cameraicon-userprofile.png" alt="">
          </button>
        </div>

        <div class="hp-profile-col">
          <span class="hp-field-label">Full Name</span>
          <p class="hp-field-value"><?php echo h($host['name']); ?></p>

          <span class="hp-field-label">Email Address</span>
          <p class="hp-field-value"><?php echo h($host['email']); ?></p>

          <span class="hp-field-label">Phone Number</span>
          <p class="hp-field-value"><?php echo $host['phone'] !== '' ? h($host['phone']) : '&mdash;'; ?></p>
        </div>

        <div class="hp-profile-col">
          <span class="hp-field-label">Location</span>
          <p class="hp-field-value hp-field-with-icon">
            <?php if ($host['location'] !== ''): ?>
              <img src="images/locationicon-userprofile.png" alt="">
              <?php echo h($host['location']); ?>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </p>

          <span class="hp-field-label">Bio</span>
          <p class="hp-field-value"><?php echo $host['about'] !== '' ? h($host['about']) : 'No bio added yet.'; ?></p>
        </div>

      </div>
    </section>

    <!-- PERFORMANCE OVERVIEW -->
    <section class="hp-card">
      <div class="hp-card-header">
        <h3>Performance Overview</h3>
      </div>

      <div class="hp-stats">

        <div class="hp-stat-card">
          <img src="images/viewsicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Views</span>
            <strong><?php echo h($total_views); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="images/bookingsicon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Bookings</span>
            <strong><?php echo h($total_bookings); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="images/occupancyicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Occupancy Rate</span>
            <strong><?php echo h($occupancy_rate); ?>%</strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="images/totalspenticon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Earnings</span>
            <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
          </div>
        </div>

      </div>
    </section>

    <!-- MY LISTINGS -->
    <section class="hp-card">
      <div class="hp-card-header">
        <h3>My Listings (<?php echo h($listings_total); ?>)</h3>
        <a href="mylistings.php" class="hp-link-view-all">View All Listings</a>
      </div>

      <?php if (empty($listings)): ?>

        <div class="hp-listings-empty">
          <p class="hp-listings-empty-title">You haven't listed any properties yet</p>
          <p class="hp-listings-empty-text">Once you add a space, it will show up here.</p>
          <a href="becomeahost.php" class="hp-btn-outline">LIST YOUR SPACE</a>
        </div>

      <?php else: ?>

        <div class="hp-listings-table">

          <div class="hp-listings-head">
            <span>Listing</span>
            <span>Bookings</span>
            <span>Occupancy</span>
            <span>Earnings</span>
            <span>Status</span>
          </div>

          <?php foreach ($listings as $listing): ?>
            <div class="hp-listing-row">

              <div class="hp-listing-info">
                <img src="images/listing-placeholder.jpg" alt="<?php echo h($listing['title']); ?>">
                <div>
                  <h4><?php echo h($listing['title']); ?></h4>
                  <p><?php echo h($listing['location']); ?></p>
                </div>
              </div>

              <span class="hp-listing-metric">&mdash;</span>
              <span class="hp-listing-metric">&mdash;</span>
              <span class="hp-listing-metric">&mdash;</span>

              <div class="hp-listing-actions">
                <span class="hp-status <?php echo hp_status_class($listing['status']); ?>">
                  <?php echo h(hp_status_label($listing['status'])); ?>
                </span>
                <button type="button" class="hp-listing-menu" data-listing-id="<?php echo h($listing['id']); ?>" aria-label="More options">
                  &#8942;
                </button>
              </div>

            </div>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>
    </section>

    <!-- GROW YOUR HOSTING BUSINESS -->
    <section class="hp-grow-banner">

      <div class="hp-grow-text">
        <img src="images/grow-hosting-illustration.png" alt="" class="hp-grow-illustration">
        <div>
          <h3>Grow Your Hosting Business</h3>
          <p>Get more bookings and increase your earnings with these host tools.</p>
        </div>
      </div>

      <div class="hp-grow-links">

        <a href="boostlisting.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128640;</span>
          <strong>Boost Your Listing</strong>
          <span>Get more visibility</span>
        </a>

        <a href="hosttips.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128161;</span>
          <strong>Host Tips</strong>
          <span>Learn and improve</span>
        </a>

        <a href="inviteandearn.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128101;</span>
          <strong>Invite &amp; Earn</strong>
          <span>Earn more rewards</span>
        </a>

      </div>

    </section>

  </div>
</main>

<!-- =========================================================
     FOOTER (uses existing style.css — not redefined here,
     identical markup/classes to myaccount.php)
========================================================= -->
<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->
        <div class="footer-brand">

            <a href="usershome.php">
                <img src="images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>

            <p class="footer-tagline">
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>

            <div class="footer-contact-line">
                <img src="images/PhoneIcon.jpg" alt="">
                <span>0927 569 3574</span>
            </div>

            <div class="footer-contact-line">
                <img src="images/EmailIcon.jpg" alt="">
                <span>kimdivino55@gmail.com</span>
            </div>

            <div class="footer-contact-line">
                <img src="images/GPSIcon.png" alt="">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>

        </div>

        <!-- LISTINGS -->
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="listing.php?category=studioloft">Studios</a>
            <a href="listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="listing.php?category=entirehouse">Entire House</a>
            <a href="listing.php">Featured Stays</a>
        </div>

        <!-- QUICK LINKS -->
        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="index.php">About Us</a>
            <a href="contacts.php">Contact</a>
            <a href="becomeahost.php">Become a Host</a>
            <a href="hiveclub.php">Hive Club</a>
        </div>

        <!-- GET THE APP -->
        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>

</footer>

<script src="javaScript.js"></script>

</body>
</html>