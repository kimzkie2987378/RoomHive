<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userreviews.php

   Reviews the logged-in user has left on stays they've
   booked. Assumes `reviews` carries listing_id, comment and
   created_at alongside the rating column userprofile.php
   already reads — adjust the SELECT below if those column
   names differ in the real schema.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
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

$navAvatar = sync_user_session($dbUser);

$notification_count = 0;

/* -----------------------------------------------------
   REVIEWS LEFT BY THIS USER
   Joined to listings/listing_photos the same way Recent
   Bookings does on userprofile.php.
----------------------------------------------------- */
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

<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Reviews</p>
    <h1>Reviews you've left</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Everything you've shared about the places you've stayed.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <section class="up-stats">
      <div class="up-stat-card">
        <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
        <div>
          <strong><?php echo h($review_count); ?></strong>
          <span>Reviews Written</span>
        </div>
      </div>
      <div class="up-stat-card">
        <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
        <div>
          <strong><?php echo h($average_rating); ?> &#9733;</strong>
          <span>Average Rating Given</span>
        </div>
      </div>
    </section>

    <section class="up-card up-bookings-card">
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

        <?php foreach ($reviews as $review): ?>
          <a href="/webprogg/Listings/listing.php?id=<?php echo h($review['listing_id']); ?>" class="up-booking-row">
            <img src="<?php echo h($review['thumb']); ?>" alt="<?php echo h($review['title']); ?>" class="up-booking-thumb">
            <div class="up-booking-info">
              <h4><?php echo h($review['title']); ?></h4>
              <p class="up-booking-location"><?php echo h($review['location']); ?></p>
              <?php if ($review['comment'] !== ''): ?>
                <p class="up-booking-dates"><?php echo h($review['comment']); ?></p>
              <?php endif; ?>
            </div>
            <div class="up-booking-side">
              <strong>&#9733; <?php echo h($review['rating']); ?></strong>
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