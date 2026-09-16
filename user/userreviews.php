<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userreviews.php
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

/* REVIEWS LEFT BY THIS USER */
 $reviewsStmt = $pdo->prepare(
    "SELECT r.id, r.rating, r.comment, r.created_at,
            l.id AS listing_id, l.title, l.location,
            p.photo_path AS cover_photo
     FROM reviews r
     JOIN listings l ON l.id = r.listing_id
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE r.user_id = :id
     ORDER BY r.created_at DESC"
);
 $reviewsStmt->execute(['id' => $_SESSION['user_id']]);

 $reviews = array_map(function ($row) {
    return [
        'id'         => (int) $row['id'],
        'listing_id' => (int) $row['listing_id'],
        'title'      => $row['title'],
        'location'   => $row['location'],
        'thumb'      => resolve_photo($row['cover_photo']),
        'rating'     => (float) $row['rating'],
        'comment'    => $row['comment'] ?? '',
        'date'       => date('M j, Y', strtotime($row['created_at'])),
    ];
}, $reviewsStmt->fetchAll());

 $review_count = count($reviews);
 $average_rating = $review_count > 0
    ? round(array_sum(array_column($reviews, 'rating')) / $review_count, 1)
    : 0;

 $activeSidebar = 'reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviews — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<!-- PAGE HEADER — PLAIN -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Your Voice</span>
    <h1>Reviews you've left</h1>
    <p class="ub-lead">Everything you've shared about the places you've stayed.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
  <div class="up-content">

    <!-- STATS (with count-ups) -->
    <section class="up-stats">
      <div class="up-stat-card up-reveal" style="--i: 0;">
        <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
        <div>
          <strong data-count="<?php echo h($review_count); ?>" data-decimals="0"><?php echo h($review_count); ?></strong>
          <span>Reviews Written</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 1;">
        <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
        <div>
          <strong><span data-count="<?php echo h($average_rating); ?>" data-decimals="1"><?php echo h($average_rating); ?></span> &#9733;</strong>
          <span>Average Rating Given</span>
        </div>
      </div>
    </section>

    <!-- REVIEW LIST -->
    <section class="up-card up-bookings-card up-reveal" style="--i: 1;">
      <div class="up-card-header">
        <h3>Your Reviews</h3>
      </div>

      <?php if (empty($reviews)): ?>

        <div class="up-bookings-empty">
          <p class="up-bookings-empty-title">No reviews yet</p>
          <p class="up-bookings-empty-text">After a completed stay, you can leave a review from My Bookings.</p>
          <a href="/webprogg/booking/userbookings.php" class="up-btn-outline">VIEW MY BOOKINGS</a>
        </div>

      <?php else: ?>

        <?php foreach ($reviews as $i => $review): ?>
          <a
              href="/webprogg/Listings/listing.php?id=<?php echo h($review['listing_id']); ?>"
              class="up-booking-row up-reveal"
              style="--i: <?php echo (int) min($i, 6); ?>; border-radius:12px;"
          >
            <img src="<?php echo h($review['thumb']); ?>" alt="<?php echo h($review['title']); ?>" class="up-booking-thumb">
            <div class="up-booking-info">
              <h4><?php echo h($review['title']); ?></h4>
              <p class="up-booking-location"><?php echo h($review['location']); ?></p>
              <?php if ($review['comment'] !== ''): ?>
                <p class="upr-comment"><?php echo h($review['comment']); ?></p>
              <?php endif; ?>
            </div>
            <div class="up-booking-side">
              <span class="upr-stars">
                <?php
                $full = (int) floor($review['rating']);
                for ($s = 1; $s <= 5; $s++) {
                    echo $s <= $full ? '&#9733;' : '&#9734;';
                }
                ?>
              </span>
              <strong><?php echo h($review['rating']); ?> / 5</strong>
              <span><?php echo h($review['date']); ?></span>
            </div>
            <span class="up-booking-chevron">&#8250;</span>
          </a>
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
            <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
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

<!-- Reveal + count-up (self-contained) -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.classList.add("in-view");
                window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var countIo = new IntersectionObserver(function (entries) {
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
        }, { threshold: 0.6 });
        Array.prototype.forEach.call(counters, function (el) { countIo.observe(el); });
    }
})();
</script>

</body>
</html>