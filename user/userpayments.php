<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userpayments.php

   Full payment history for the logged-in user: every booking
   charge and every paid/pending Hive Club transaction, merged
   into one chronological list, plus the same totals shown on
   the Overview page and a Payment Methods manager.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA (same shape as userprofile.php, trimmed to
   what the navbar/sidebar/host-redirect actually need)
----------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

$_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
$_SESSION['is_host'] = (bool) $dbUser['is_host'];

$notification_count = 0;

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------
   BOOKING CHARGES
   Every booking that isn't cancelled counts as a real
   payment line, same rule as the Overview totals use.
----------------------------------------------------- */
$bookingsStmt = $pdo->prepare(
    "SELECT b.id, b.total, b.status, b.booked_at, l.title
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE b.user_id = :id AND b.status != 'cancelled'
     ORDER BY b.booked_at DESC"
);
$bookingsStmt->execute(['id' => $_SESSION['user_id']]);

$bookingPayments = array_map(function ($row) {
    return [
        'type'   => 'Booking',
        'label'  => $row['title'],
        'amount' => (float) $row['total'],
        'date'   => $row['booked_at'],
        'status' => $row['status'],
    ];
}, $bookingsStmt->fetchAll());

/* -----------------------------------------------------
   HIVE CLUB TRANSACTIONS
----------------------------------------------------- */
$txnStmt = $pdo->prepare(
    "SELECT amount, purchased_at, payment_status
     FROM hiveclub_transactions
     WHERE user_id = :id
     ORDER BY purchased_at DESC"
);
$txnStmt->execute(['id' => $_SESSION['user_id']]);

$membershipPayments = array_map(function ($row) {
    return [
        'type'   => 'Hive Club',
        'label'  => 'Membership payment',
        'amount' => (float) $row['amount'],
        'date'   => $row['purchased_at'],
        'status' => $row['payment_status'],
    ];
}, $txnStmt->fetchAll());

/* -----------------------------------------------------
   MERGE + SORT
   Both lists are already date-desc individually; merge
   then re-sort the combined set by date desc.
----------------------------------------------------- */
$paymentHistory = array_merge($bookingPayments, $membershipPayments);
usort($paymentHistory, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});

/* -----------------------------------------------------
   TOTALS
   Same math as Overview: this week / all time / pending.
----------------------------------------------------- */
$oneWeekAgo = strtotime('-7 days');

$spent_this_week = 0;
$spent_all_time  = 0;
$pending_to_pay  = 0;

foreach ($paymentHistory as $p) {
    if ($p['status'] === 'pending') {
        $pending_to_pay += $p['amount'];
        continue;
    }
    $spent_all_time += $p['amount'];
    if (strtotime($p['date']) >= $oneWeekAgo) {
        $spent_this_week += $p['amount'];
    }
}

/* -----------------------------------------------------
   PAYMENT METHODS
   New site — nobody has saved a card yet. Once a real
   payments flow exists, replace with:
   SELECT * FROM payment_methods WHERE user_id = ?
----------------------------------------------------- */
$payment_methods = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
</head>
<body>

<header class="navbar">
    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <nav class="nav-links">
        <a href="/webprogg/user/usershome.php">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="/webprogg/host/becomeahost.php">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>

        <a href="notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <div class="account-dropdown js-account-dropdown">
            <button type="button" class="my-account js-account-toggle" id="accountDropdownToggle" aria-haspopup="true" aria-expanded="false">
                <span class="account-circle">
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                </span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <?php if ($dbUser['is_host']): ?>
                    <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                <?php endif; ?>
                <a href="/webprogg/user/userprofile.php">My Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
</header>

<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Payments</p>
    <h1>Your payment history</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Every booking charge and Hive Club payment, all in one place.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <aside class="up-sidebar">
    <a href="/webprogg/user/userprofile.php" class="up-side-link">
      <img src="/webprogg/images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="/webprogg/booking/userbookings.php" class="up-side-link">
      <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
      My Bookings
    </a>
    <a href="/webprogg/user/userwishlist.php" class="up-side-link">
      <img src="/webprogg/images/wihlistedicon-userprofile.png" alt="">
      Wishlist
    </a>
    <a href="userpayments.php" class="up-side-link active">
      <img src="/webprogg/images/paymentsicon-userprofile.png" alt="">
      Payments
    </a>
    <a href="userreviews.php" class="up-side-link">
      <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="usermessages.php" class="up-side-link">
      <img src="/webprogg/images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="editprofile.php" class="up-side-link">
      <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="security.php" class="up-side-link">
      <img src="/webprogg/images/lockicon-userprofile.png" alt="">
      Settings
    </a>
    <a href="usernotificationsettings.php" class="up-side-link">
      <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="savedsearches.php" class="up-side-link">
      <img src="/webprogg/images/savedsearchesicon-userprofile.png" alt="">
      Saved Searches
    </a>
    <a href="helpcenter.php" class="up-side-link">
      <img src="/webprogg/images/needhelpicon-userprofile.png" alt="">
      Help Center
    </a>
    <a href="/webprogg/auth/logout.php" class="up-side-link up-side-logout">
      <img src="/webprogg/images/logouticon-userprofile.png" alt="">
      Log Out
    </a>
  </aside>

  <div class="up-content">

    <!-- TOTALS ROW -->
    <section class="up-stats">
      <div class="up-stat-card">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <?php echo h(number_format($spent_this_week, 2)); ?></strong>
          <span>Spent This Week</span>
        </div>
      </div>
      <div class="up-stat-card">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <?php echo h(number_format($spent_all_time, 2)); ?></strong>
          <span>Total Spent All Time</span>
        </div>
      </div>
      <div class="up-stat-card">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <?php echo h(number_format($pending_to_pay, 2)); ?></strong>
          <span>Pending to Pay</span>
        </div>
      </div>
    </section>

    <div class="up-two-col">

      <!-- PAYMENT HISTORY -->
      <div class="up-card up-bookings-card">
        <div class="up-card-header">
          <h3>Payment History</h3>
        </div>

        <?php if (empty($paymentHistory)): ?>

          <div class="up-bookings-empty">
            <p class="up-bookings-empty-title">No payments yet</p>
            <p class="up-bookings-empty-text">Charges from bookings and Hive Club will show up here.</p>
            <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
          </div>

        <?php else: ?>

          <?php foreach ($paymentHistory as $payment): ?>
            <div class="up-booking-row" style="cursor:default;">
              <div class="up-booking-info">
                <h4><?php echo h($payment['label']); ?></h4>
                <p class="up-booking-location"><?php echo h($payment['type']); ?></p>
                <p class="up-booking-dates">
                  <img src="/webprogg/images/calendaricon-userprofile.png" alt="">
                  <?php echo h(date('M j, Y', strtotime($payment['date']))); ?>
                </p>
              </div>
              <div class="up-booking-side">
                <span class="up-status up-status-<?php echo h($payment['status']); ?>">
                  <?php echo h(ucfirst($payment['status'])); ?>
                </span>
                <strong>&#8369; <?php echo h(number_format($payment['amount'], 2)); ?></strong>
              </div>
            </div>
          <?php endforeach; ?>

        <?php endif; ?>
      </div>

      <!-- PAYMENT METHODS -->
      <div class="up-right-col">
        <div class="up-card up-payment-methods">
          <div class="up-card-header">
            <h3>Payment Methods</h3>
          </div>

          <?php if (empty($payment_methods)): ?>
            <div class="up-payment-methods-empty" style="text-align:center; padding:20px 8px; color:#777777;">
              <p style="margin:0 0 4px; font-weight:700; color:var(--up-navy, #1c2a38);">No payment methods yet</p>
              <p style="margin:0; font-size:13px;">Add a card to make booking faster.</p>
            </div>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <div class="up-card-item">
                <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>">
                <div>
                  <strong>&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; <?php echo h($method['last4']); ?></strong>
                  <span>Expires <?php echo h($method['expires']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <button type="button" class="up-btn-outline up-add-card">+ Add New Card</button>
        </div>

        <div class="up-need-help">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about a charge? We're here 24/7.</p>
            <a href="helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>
            <p class="footer-tagline">
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>
            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="">
                <span>0927 569 3574</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="">
                <span>kimdivino55@gmail.com</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>
        </div>

        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>

        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>
</body>
</html>
