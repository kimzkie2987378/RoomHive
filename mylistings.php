<?php
/* =========================================================
   ROOMHIVE — MY LISTINGS
   mylistings.php

   Same navbar / sidebar / dashboard shell as hostprofile.php.
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
   Same reasoning as hostprofile.php — see that file for the
   full note on why this redirects to hostprofile.php for now.
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: hostprofile.php"); // TODO: change back to becomeahost.php once that file exists
    exit;
}

/* -----------------------------------------------------
   HOST DATA (sidebar card)
----------------------------------------------------- */
$host = [
    'name'         => $dbUser['name'],
    'avatar'       => 'images/default-avatar.png',
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

$notification_count = 0; // TODO: wire up once a notifications table exists

/* -----------------------------------------------------
   MY LISTINGS
   Same query as the mini table on hostprofile.php. Bedrooms,
   bathrooms, sqm, views, bookings and rating aren't columns
   on `listings` yet (and views/bookings need tables that
   don't exist yet either), so those show "—" instead of
   invented numbers until the schema supports them.
----------------------------------------------------- */
$listingsStmt = $pdo->prepare(
    "SELECT id, title, location, exact_address, price, status, created_at
     FROM listings
     WHERE user_id = :id
     ORDER BY created_at DESC"
);
$listingsStmt->execute(['id' => $_SESSION['user_id']]);
$listings = $listingsStmt->fetchAll();

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* Maps a listings.status value to a status-pill class (same map as hostprofile.php) */
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

/* Cache-buster for the stylesheet so browsers don't keep serving a
   stale cached copy after edits (e.g. this fix) are deployed. Bump
   the number any time hostprofile.css changes and you're not seeing
   the update reflected live. */
$hp_css_version = '3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Listings — RoomHive</title>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="hostprofile.css?v=<?php echo h($hp_css_version); ?>">
</head>
<body>

<!-- =========================================================
     NAVBAR (identical markup/classes to hostprofile.php)
========================================================= -->
<header class="navbar">

    <a href="usershome.php" class="logo">
        <img src="images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

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
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <!-- SIDEBAR (identical to hostprofile.php, "My Listings" active) -->
  <aside class="hp-sidebar">

    <div class="hp-sidebar-card">
      <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar">
      <h4><?php echo h($host['name']); ?></h4>
      <span class="hp-host-badge">Host</span>
      <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="hostprofile.php" class="hp-side-link">
      <img src="images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="mylistings.php" class="hp-side-link active">
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

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">My Listings</h1>
        <p class="hp-page-subtitle">Manage your properties and keep track of your listings all in one place.</p>
      </div>
      <a href="becomeahost.php" class="hp-btn-outline">+ Add New Listing</a>
    </div>

    <div class="hp-mylistings-list">
      <?php if (empty($listings)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No listings yet</p>
          <p>Click "Add New Listing" above to publish your first property.</p>
        </div>

      <?php else: foreach ($listings as $l): ?>
      <div class="hp-mylisting-card">
        <div class="hp-mylisting-img-wrap">
          <img class="hp-mylisting-img" src="images/listing-placeholder.jpg" alt="<?php echo h($l['title']); ?>">
          <span class="hp-status <?php echo hp_status_class($l['status']); ?>" style="position:absolute; top:10px; left:10px;">
            <?php echo h(hp_status_label($l['status'])); ?>
          </span>
        </div>

        <div class="hp-mylisting-info">
          <h3><?php echo h($l['title']); ?></h3>
          <div class="hp-mylisting-location">
            <img src="images/locationicon-userprofile.png" alt="">
            <?php echo h($l['location']); ?>
          </div>
          <div class="hp-mylisting-price-row">
            <span class="hp-mylisting-price">&#8369; <?php echo number_format($l['price']); ?></span>
            <span> / month</span>
          </div>
        </div>

        <div class="hp-mylisting-stats">
          <div class="hp-mylisting-stat-line">
            <img src="images/viewsicon-hostprofile.png" alt="">
            Views <b>&mdash;</b>
          </div>
          <div class="hp-mylisting-stat-line">
            <img src="images/bookingsicon-userprofile.png" alt="">
            Bookings <b>&mdash;</b>
          </div>
          <div class="hp-mylisting-stat-line">
            <img src="images/averageratinsicon-userprofile.png" alt="">
            Rating <b>&mdash;</b>
          </div>
        </div>

        <div class="hp-mylisting-actions">
          <button type="button" class="hp-btn-outline">Edit Listing</button>
          <button type="button" class="hp-listing-menu" data-listing-id="<?php echo h($l['id']); ?>" aria-label="More options">&#8942;</button>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</main>

<!-- =========================================================
     FOOTER (identical to hostprofile.php)
========================================================= -->
<footer class="site-footer">

    <div class="footer-top">

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

        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="listing.php?category=studioloft">Studios</a>
            <a href="listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="listing.php?category=entirehouse">Entire House</a>
            <a href="listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="index.php">About Us</a>
            <a href="contacts.php">Contact</a>
            <a href="becomeahost.php">Become a Host</a>
            <a href="hiveclub.php">Hive Club</a>
        </div>

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