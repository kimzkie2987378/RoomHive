<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userpayments.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

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

/* FIX: was setting $_SESSION by hand — sync_user_session()
   does avatar + is_host consistently with every other page. */
 $navAvatar = sync_user_session($dbUser);

 $notification_count = 0;

/* BOOKING CHARGES */
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

/* HIVE CLUB TRANSACTIONS */
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

/* MERGE + SORT */
 $paymentHistory = array_merge($bookingPayments, $membershipPayments);
usort($paymentHistory, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});

/* TOTALS */
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

/* PAYMENT METHODS */
 $payment_methods = [];

 $activeSidebar = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<script>document.documentElement.classList.add("js");</script>
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

        <!-- FIX: was relative "notifications.php" -->
        <a href="/webprogg/user/notifications.php" class="nav-bell">
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

<!-- HERO -->
<section class="up-hero up-hero-sub">

    <div aria-hidden="true">
        <span class="up-hero-blob up-hero-blob-1"></span>
        <span class="up-hero-blob up-hero-blob-2"></span>
    </div>

    <div class="up-hero-inner">

        <div class="up-hero-text">

            <span class="up-hero-badge up-anim" style="--d: .05s;">
                <span class="up-pulse-dot"></span>
                Payments
            </span>

            <h1 class="up-anim" style="--d: .15s;">
                Your payment <span class="up-shimmer">history</span>
            </h1>

            <span class="up-welcome-underline up-anim" style="--d: .22s;"></span>

            <p class="up-hero-sub up-anim" style="--d: .28s;">
                Every booking charge and Hive Club payment,
                all in one place.
            </p>

        </div>

        <div class="up-hero-art up-anim" style="--d: .3s;">
            <span class="up-art-glow" aria-hidden="true"></span>
            <img src="/webprogg/images/totalspenticon-userprofile.png" alt="" style="object-fit:contain; background:transparent; box-shadow:none;">
        </div>

    </div>

    <svg class="up-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>

<main class="up-dashboard">

  <?php
  /* FIX: the hand-rolled sidebar here pointed at several files
     that don't exist (savedsearches.php, helpcenter.php, etc).
     The shared partial is the single source of truth. */
  require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';
  ?>

  <div class="up-content">

    <!-- TOTALS ROW (with money count-ups) -->
    <section class="up-stats">
      <div class="up-stat-card up-reveal" style="--i: 0;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($spent_this_week); ?>" data-decimals="2"><?php echo h(number_format($spent_this_week, 2)); ?></span></strong>
          <span>Spent This Week</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 1;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($spent_all_time); ?>" data-decimals="2"><?php echo h(number_format($spent_all_time, 2)); ?></span></strong>
          <span>Total Spent All Time</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 2;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($pending_to_pay); ?>" data-decimals="2"><?php echo h(number_format($pending_to_pay, 2)); ?></span></strong>
          <span>Pending to Pay</span>
        </div>
      </div>
    </section>

    <div class="up-two-col">

      <!-- PAYMENT HISTORY -->
      <div class="up-card up-bookings-card up-reveal" style="--i: 1;">
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
            <div class="up-booking-row up-row-static">
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

      <!-- PAYMENT METHODS + HELP -->
      <div class="up-right-col">
        <div class="up-card up-payment-methods up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Payment Methods</h3>
          </div>

          <?php if (empty($payment_methods)): ?>
            <div class="up-payment-methods-empty">
              <p>No payment methods yet</p>
              <p>Add a card to make booking faster.</p>
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

        <div class="up-need-help up-reveal" style="--i: 3;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about a charge? We're here 24/7.</p>
            <a href="/webprogg/misc/contacts.php" class="up-btn-solid">CONTACT SUPPORT</a>
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

<!-- Reveal + money count-up (self-contained) -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* Scroll reveal */
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    io.unobserve(el);
                    el.classList.add("in-view");
                    window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
                });
            },
            { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
        );
        revealEls.forEach(function (el) { io.observe(el); });
    }

    /* Money count-up */
    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var countIo = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    countIo.unobserve(el);

                    var target = parseFloat(el.getAttribute("data-count")) || 0;
                    var decimals = parseInt(el.getAttribute("data-decimals"), 10) || 0;
                    var t0 = null;
                    var DURATION = 1300;

                    var stepFn = function (ts) {
                        if (!t0) t0 = ts;
                        var k = Math.min((ts - t0) / DURATION, 1);
                        var eased = 1 - Math.pow(1 - k, 3);
                        el.textContent = (target * eased).toLocaleString(
                            undefined,
                            { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
                        );
                        if (k < 1) window.requestAnimationFrame(stepFn);
                    };

                    window.requestAnimationFrame(stepFn);
                });
            },
            { threshold: 0.6 }
        );
        Array.prototype.forEach.call(counters, function (el) { countIo.observe(el); });
    }
})();
</script>

</body>
</html>