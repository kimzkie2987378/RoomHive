<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   savedsearches.php
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

 $navAvatar = sync_user_session($dbUser);

 $notification_count = 0;

/* DELETE (POST + CSRF, as before) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (csrf_verify()) {
        $deleteStmt = $pdo->prepare("DELETE FROM saved_searches WHERE id = :id AND user_id = :uid");
        $deleteStmt->execute(['id' => (int) $_POST['delete_id'], 'uid' => $_SESSION['user_id']]);
    }
    header("Location: /webprogg/user/savedsearches.php");
    exit;
}

/* SAVED SEARCHES */
 $searchesStmt = $pdo->prepare(
    "SELECT id, label, location, category, min_price, max_price, created_at
     FROM saved_searches
     WHERE user_id = :id
     ORDER BY created_at DESC"
);
 $searchesStmt->execute(['id' => $_SESSION['user_id']]);

 $savedSearches = array_map(function ($row) {
    $params = [];
    if (!empty($row['location']))  $params['location'] = $row['location'];
    if (!empty($row['category']))  $params['category'] = $row['category'];
    if (!empty($row['min_price'])) $params['min_price'] = $row['min_price'];
    if (!empty($row['max_price'])) $params['max_price'] = $row['max_price'];

    return [
        'id'       => (int) $row['id'],
        'label'    => $row['label'] ?: 'Saved search',
        'location' => $row['location'] ?? '',
        'category' => $row['category'] ?? '',
        'min_price'=> $row['min_price'] ?? '',
        'max_price'=> $row['max_price'] ?? '',
        'date'     => date('M j, Y', strtotime($row['created_at'])),
        'url'      => '/webprogg/Listings/listing.php' . (empty($params) ? '' : '?' . http_build_query($params)),
    ];
}, $searchesStmt->fetchAll());

 $activeSidebar = 'savedsearches';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Saved Searches — RoomHive</title>

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
                Saved Searches
            </span>

            <h1 class="up-anim" style="--d: .15s;">
                Your saved <span class="up-shimmer">filters</span>
            </h1>

            <span class="up-welcome-underline up-anim" style="--d: .22s;"></span>

            <p class="up-hero-sub up-anim" style="--d: .28s;">
                Jump back into a search without re-entering
                your filters.
            </p>

        </div>

        <div class="up-hero-art up-hero-art-contain up-anim" style="--d: .3s;">
            <span class="up-art-glow" aria-hidden="true"></span>
            <img src="/webprogg/images/savedsearchesicon-userprofile.png" alt="">
        </div>

    </div>

    <svg class="up-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <section class="up-card up-reveal">
      <div class="up-card-header">
        <h3>Saved Searches (<span class="up-wishlist-count"><?php echo h(count($savedSearches)); ?></span>)</h3>
        <a href="/webprogg/Listings/listing.php" class="up-link-view-all">New Search</a>
      </div>

      <?php if (empty($savedSearches)): ?>

        <div class="up-bookings-empty">
          <p class="up-bookings-empty-title">No saved searches yet</p>
          <p class="up-bookings-empty-text">Save a search from the Listings page to quickly come back to it.</p>
          <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
        </div>

      <?php else: ?>

        <?php foreach ($savedSearches as $i => $search): ?>
          <div class="up-booking-row up-row-static up-reveal" style="--i: <?php echo (int) min($i, 6); ?>; border-radius:12px;">
            <div class="up-booking-info">
              <h4><?php echo h($search['label']); ?></h4>
              <p class="up-booking-location">
                <?php
                  $bits = [];
                  if ($search['location'] !== '') $bits[] = $search['location'];
                  if ($search['category'] !== '') $bits[] = ucfirst(str_replace('_', ' ', $search['category']));
                  if ($search['min_price'] !== '' || $search['max_price'] !== '') {
                      $bits[] = '&#8369; ' . h($search['min_price'] ?: '0') . ' &ndash; ' . h($search['max_price'] ?: 'any');
                  }
                  echo empty($bits) ? 'All listings' : implode(' &middot; ', array_map('h', $bits));
                ?>
              </p>
              <p class="up-booking-dates">
                <img src="/webprogg/images/calendaricon-userprofile.png" alt="">
                Saved <?php echo h($search['date']); ?>
              </p>
            </div>
            <div class="up-booking-side us-actions" style="flex-direction:row;">
              <a href="<?php echo h($search['url']); ?>" class="up-btn-outline" style="padding:9px 16px; font-size:12px;">RUN SEARCH</a>
              <form method="POST" action="/webprogg/user/savedsearches.php" onsubmit="return confirm('Remove this saved search?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_id" value="<?php echo h($search['id']); ?>">
                <button type="submit" class="us-delete-btn" aria-label="Delete saved search">&#10005;</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

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
            <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>kimdivino55@gmail.com</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
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