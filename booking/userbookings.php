<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userbookings.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* AUTH GUARD */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* USER DATA */
 $stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ($dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);

 $notification_count = 0;

/* STATUS FILTER */
 $validStatuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];

 $statusFilter = isset($_GET['status']) && in_array($_GET['status'], $validStatuses, true)
    ? $_GET['status']
    : 'all';

/* ALL BOOKINGS */
 $sql = "SELECT b.id, b.total, b.status, b.booked_at,
               l.title, l.location,
               p.photo_path AS cover_photo
        FROM bookings b
        JOIN listings l ON l.id = b.listing_id
        LEFT JOIN listing_photos p
               ON p.listing_id = l.id AND p.photo_type = 'cover'
        WHERE b.user_id = :id";

if ($statusFilter !== 'all') {
    $sql .= " AND b.status = :status";
}

 $sql .= " ORDER BY b.booked_at DESC";

 $bookingsStmt = $pdo->prepare($sql);
 $bookingsStmt->bindValue(':id', $_SESSION['user_id']);
if ($statusFilter !== 'all') {
    $bookingsStmt->bindValue(':status', $statusFilter);
}
 $bookingsStmt->execute();

 $bookings = array_map(function ($row) {
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'thumb'    => resolve_photo($row['cover_photo']),
        'dates'    => date('M j, Y', strtotime($row['booked_at'])),
        'status'   => $row['status'],
        'total'    => number_format((float) $row['total'], 2),
    ];
}, $bookingsStmt->fetchAll());

 $bookings_total = count($bookings);

/* TAB COUNTS */
 $countsStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total
     FROM bookings
     WHERE user_id = :id
     GROUP BY status"
);
 $countsStmt->execute(['id' => $_SESSION['user_id']]);

 $statusCounts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($countsStmt->fetchAll() as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['total'];
    }
}
 $statusCounts['all'] = array_sum($statusCounts);

 $tabs = [
    'all'       => 'All',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

 $activeSidebar = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<!-- =====================================================
     PLAIN PAGE HEADER (this page only)
     Replaces the decorative hero entirely: no photo, no
     honeycomb, no blobs, no wave, no chips. Just the
     heading block on white, aligned with the dashboard
     below it.
===================================================== -->
<style>
    .ub-page-head {
        /* Clear the 90px fixed navbar + breathing room */
        margin: 130px auto 0;

        max-width: 1400px;

        /* Matches .up-dashboard's side gutters so the
           heading lines up with the cards below */
        width: calc(100% - 120px);

        padding: 0 24px 6px;
    }

    .ub-page-head .ub-eyebrow {
        display: block;

        margin-bottom: 8px;

        color: #b07708;

        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .ub-page-head h1 {
        margin: 0 0 10px;

        color: #1c2a38;

        font-size: 32px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1.15;
    }

    .ub-page-head .ub-lead {
        margin: 0;

        max-width: 520px;

        color: #5d6875;

        font-size: 14.5px;
        line-height: 1.65;
    }

    /* Dashboard sits closer now that the tall hero is gone */
    .up-dashboard {
        margin-top: 26px;
    }

    @media (max-width: 1200px) {
        .ub-page-head {
            width: calc(100% - 80px);
        }
    }

    @media (max-width: 900px) {
        .ub-page-head {
            width: calc(100% - 50px);

            margin-top: 115px;
        }
    }

    @media (max-width: 480px) {
        .ub-page-head {
            width: calc(100% - 30px);

            margin-top: 110px;
        }

        .ub-page-head h1 {
            font-size: 26px;
        }
    }
</style>

<script>document.documentElement.classList.add("js");</script>
</head>
<body>

<!-- NAVBAR -->
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
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<!-- =====================================================
     PAGE HEADER — PLAIN
     CHANGED: the entire decorative hero is gone (photo,
     scrim, chips, wave, honeycomb, blobs, shimmer). Just
     the heading block on white, aligned with the
     dashboard's gutters.
===================================================== -->
<header class="ub-page-head">

    <span class="ub-eyebrow">
        Every Stay
    </span>

    <h1>
        My Bookings
    </h1>

    <p class="ub-lead">
        Track every inquiry and stay you've booked through RoomHive.
    </p>

</header>

<!-- DASHBOARD -->
<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <section class="up-card up-bookings-card up-reveal">

      <div class="up-card-header">
        <h3>My Bookings (<span class="up-wishlist-count"><?php echo h($bookings_total); ?></span>)</h3>
        <a href="/webprogg/Listings/listing.php" class="up-link-view-all">Browse Listings</a>
      </div>

      <!-- STATUS TABS (segmented pills) -->
      <div class="ub-tabs">
        <?php foreach ($tabs as $key => $label): ?>
          <a
            href="/webprogg/booking/userbookings.php?status=<?php echo h($key); ?>"
            class="ub-tab<?php echo $statusFilter === $key ? ' active' : ''; ?>"
          >
            <?php echo h($label); ?>
            <span class="ub-tab-count">(<?php echo h($statusCounts[$key]); ?>)</span>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($bookings)): ?>

        <div class="up-bookings-empty">
          <p class="up-bookings-empty-title">
            <?php echo $statusFilter === 'all' ? 'No bookings yet' : 'No ' . h($tabs[$statusFilter]) . ' bookings'; ?>
          </p>
          <p class="up-bookings-empty-text">
            <?php echo $statusFilter === 'all' ? 'Once you book a stay, it will show up here.' : 'Try a different tab, or browse listings to book a new stay.'; ?>
          </p>
          <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
        </div>

      <?php else: ?>

        <div class="ub-list" style="display:flex; flex-direction:column;">
          <?php foreach ($bookings as $i => $booking): ?>
            <a
                href="/webprogg/booking/booking-details.php?id=<?php echo h($booking['id']); ?>"
                class="up-booking-row up-reveal"
                style="--i: <?php echo (int) min($i, 6); ?>; border-radius:12px;"
            >
              <img src="<?php echo h($booking['thumb']); ?>" alt="<?php echo h($booking['title']); ?>" class="up-booking-thumb">
              <div class="up-booking-info">
                <h4><?php echo h($booking['title']); ?></h4>
                <p class="up-booking-location"><?php echo h($booking['location']); ?></p>
                <p class="up-booking-dates"><img src="/webprogg/images/calendaricon-userprofile.png" alt=""><?php echo h($booking['dates']); ?></p>
              </div>
              <div class="up-booking-side">
                <span class="up-status up-status-<?php echo h($booking['status']); ?>">
                  <?php echo h(ucfirst($booking['status'])); ?>
                </span>
                <strong>&#8369; <?php echo h($booking['total']); ?></strong>
                <span>Total Paid</span>
              </div>
              <span class="up-booking-chevron">&#8250;</span>
            </a>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </section>

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

<!-- Reveal (self-contained) -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
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
})();
</script>

</body>
</html>