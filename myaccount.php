<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   myaccount.php
========================================================= */

session_start();

/* -----------------------------------------------------
   AUTH GUARD
   Redirect to login if nobody is signed in.
   Replace with your real session/auth check.
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
   TODO: replace with a real query, e.g.
   $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
   $stmt->execute([$_SESSION['user_id']]);
   $user = $stmt->fetch();
----------------------------------------------------- */
$user = [
    'name'          => 'Maria Santos',
    'avatar'        => 'images/maria-santos-avatar.jpg',
    'verified'      => true,
    'location'      => 'Dumaguete City, Negros Oriental',
    'email'         => 'mariasantos@email.com',
    'phone'         => '0912 345 6789',
    'member_since'  => 'April 2024',
    'about'         => 'Hi! I love traveling and discovering new places. I enjoy peaceful stays and great host experiences.',
];

$notification_count = 3;

/* -----------------------------------------------------
   STATS ROW
   TODO: replace with aggregate queries (COUNT/AVG/SUM)
----------------------------------------------------- */
$stats = [
    ['icon' => 'bookingsicon-userprofile.png',    'value' => '12',        'label' => 'Bookings Total'],
    ['icon' => 'wihlistedicon-userprofile.png',   'value' => '8',         'label' => 'Wishlisted Properties'],
    ['icon' => 'averageratinsicon-userprofile.png','value' => '4.8',      'label' => 'Average Rating From Reviews'],
    ['icon' => 'totalspenticon-userprofile.png',  'value' => '&#8369; 24,560', 'label' => 'Total Spent All Time'],
];

/* -----------------------------------------------------
   RECENT BOOKINGS
   TODO: replace with
   SELECT * FROM bookings WHERE user_id = ? ORDER BY start_date DESC LIMIT 3
----------------------------------------------------- */
$bookings = [
    [
        'id'       => 1,
        'title'    => 'Cozy Studio Apartment',
        'location' => 'Dumaguete City, Negros Oriental',
        'dates'    => 'May 15 – May 18, 2024',
        'thumb'    => 'images/booking-cozy-studio.jpg',
        'status'   => 'completed',
        'total'    => '3,600',
    ],
    [
        'id'       => 2,
        'title'    => 'Beachfront Cottage',
        'location' => 'Amlan, Negros Oriental',
        'dates'    => 'Apr 2 – Apr 5, 2024',
        'thumb'    => 'images/booking-beachfront-cottage.jpg',
        'status'   => 'completed',
        'total'    => '6,750',
    ],
    [
        'id'       => 3,
        'title'    => 'Modern 2BR House',
        'location' => 'Sibulan, Negros Oriental',
        'dates'    => 'Mar 10 – Mar 12, 2024',
        'thumb'    => 'images/booking-modern-2br-house.jpg',
        'status'   => 'cancelled',
        'total'    => '4,200',
    ],
];

$total_spent = '24,560';

/* -----------------------------------------------------
   PAYMENT METHODS
   TODO: replace with SELECT * FROM payment_methods WHERE user_id = ?
----------------------------------------------------- */
$payment_methods = [
    [
        'icon'    => 'images/visaicon-userprofile.png',
        'label'   => 'Visa',
        'last4'   => '4242',
        'expires' => '12/26',
    ],
    [
        'icon'    => 'images/mastercardicon-userprofile.png',
        'label'   => 'Mastercard',
        'last4'   => '8881',
        'expires' => '08/27',
    ],
];

$two_factor_enabled = true;

/* -----------------------------------------------------
   WISHLIST
   TODO: replace with
   SELECT * FROM listings l
   JOIN wishlist w ON w.listing_id = l.id
   WHERE w.user_id = ?
----------------------------------------------------- */
$wishlist_total = 8;

$wishlist = [
    [
        'id'       => 1,
        'title'    => 'Mountain View Cabin',
        'location' => 'Valencia, Negros Oriental',
        'thumb'    => 'images/wishlist-mountain-view-cabin.jpg',
        'price'    => '3,200',
        'rating'   => '4.7',
        'reviews'  => 32,
        'saved'    => true,
    ],
    [
        'id'       => 2,
        'title'    => 'City Center Condo',
        'location' => 'Dumaguete City',
        'thumb'    => 'images/wishlist-city-center-condo.jpg',
        'price'    => '2,800',
        'rating'   => '4.6',
        'reviews'  => 18,
        'saved'    => true,
    ],
    [
        'id'       => 3,
        'title'    => 'Seaside Bungalow',
        'location' => 'Bacong, Negros Oriental',
        'thumb'    => 'images/wishlist-seaside-bungalow.jpg',
        'price'    => '4,500',
        'rating'   => '4.9',
        'reviews'  => 27,
        'saved'    => true,
    ],
    [
        'id'       => 4,
        'title'    => 'Private Resort Villa',
        'location' => 'Zamboanguita, Negros Or.',
        'thumb'    => 'images/wishlist-private-resort-villa.jpg',
        'price'    => '7,800',
        'rating'   => '4.8',
        'reviews'  => 15,
        'saved'    => true,
    ],
];

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account — RoomHive</title>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="myaccount.css">
</head>
<body>

<!-- =========================================================
     NAVBAR (uses existing style.css — not redefined here)
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
                <a href="logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<!-- =========================================================
     WELCOME BANNER
========================================================= -->
<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Welcome back,</p>
    <h1><?php echo h($user['name']); ?>!</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Manage your bookings, favorites, and account settings all in one place.</p>
  </div>
  <div class="up-welcome-image">
    <img src="images/livingroomicon-userprofile.png" alt="">
  </div>
</section>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="up-dashboard">

  <!-- SIDEBAR -->
  <aside class="up-sidebar">
    <a href="userprofile.php" class="up-side-link active">
      <img src="images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="mybookings.php" class="up-side-link">
      <img src="images/bookingsicon-userprofile.png" alt="">
      My Bookings
    </a>
    <a href="wishlist.php" class="up-side-link">
      <img src="images/wihlistedicon-userprofile.png" alt="">
      Wishlist
    </a>
    <a href="reviews.php" class="up-side-link">
      <img src="images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="payments.php" class="up-side-link">
      <img src="images/paymentsicon-userprofile.png" alt="">
      Payments
    </a>
    <a href="messages.php" class="up-side-link">
      <img src="images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="editprofile.php" class="up-side-link">
      <img src="images/profileaccounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="notificationsettings.php" class="up-side-link">
      <img src="images/notificationicon-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="savedsearches.php" class="up-side-link">
      <img src="images/savedsearchesicon-userprofile.png" alt="">
      Saved Searches
    </a>
    <a href="helpcenter.php" class="up-side-link">
      <img src="images/needhelpicon-userprofile.png" alt="">
      Help Center
    </a>
    <a href="logout.php" class="up-side-link up-side-logout">
      <img src="images/logouticon-userprofile.png" alt="">
      Log Out
    </a>
  </aside>

  <!-- CENTER + RIGHT COLUMNS -->
  <div class="up-content">

    <!-- PROFILE CARD -->
    <section class="up-card up-profile-card">
      <div class="up-profile-photo">
        <img src="<?php echo h($user['avatar']); ?>" alt="<?php echo h($user['name']); ?>">
        <button type="button" class="up-photo-edit" id="photoButton" aria-label="Change profile photo">
          <img src="images/cameraicon-userprofile.png" alt="">
        </button>
      </div>

      <div class="up-profile-info">
        <div class="up-profile-name-row">
          <h2><?php echo h($user['name']); ?></h2>
          <?php if ($user['verified']): ?>
            <span class="up-badge-verified">
              <img src="images/verifiedicon-userprofile.png" alt="">
              Verified
            </span>
          <?php endif; ?>
        </div>

        <ul class="up-profile-meta">
          <li><img src="images/locationicon-userprofile.png" alt=""><?php echo h($user['location']); ?></li>
          <li><img src="images/emailicon-userprofile.png" alt=""><?php echo h($user['email']); ?></li>
          <li><img src="images/phoneicon-userprofile.png" alt=""><?php echo h($user['phone']); ?></li>
          <li><img src="images/calendaricon-userprofile.png" alt="">Member since <?php echo h($user['member_since']); ?></li>
        </ul>
      </div>

      <div class="up-profile-about">
        <h3>About Me</h3>
        <p><?php echo h($user['about']); ?></p>
      </div>

      <button type="button" class="up-btn-outline up-edit-profile" id="editProfileButton">Edit Profile</button>
    </section>

    <!-- STATS ROW -->
    <section class="up-stats">
      <?php foreach ($stats as $stat): ?>
        <div class="up-stat-card">
          <img src="images/<?php echo h($stat['icon']); ?>" alt="">
          <div>
            <strong><?php echo $stat['value']; /* contains an HTML entity, not user input */ ?></strong>
            <span><?php echo h($stat['label']); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- RECENT BOOKINGS + PAYMENT SUMMARY -->
    <section class="up-two-col">

      <div class="up-card up-bookings-card">
        <div class="up-card-header">
          <h3>Recent Bookings</h3>
          <a href="mybookings.php" class="up-link-view-all">View All</a>
        </div>

        <?php foreach ($bookings as $booking): ?>
          <a href="booking-details.php?id=<?php echo h($booking['id']); ?>" class="up-booking-row">
            <img src="<?php echo h($booking['thumb']); ?>" alt="<?php echo h($booking['title']); ?>" class="up-booking-thumb">
            <div class="up-booking-info">
              <h4><?php echo h($booking['title']); ?></h4>
              <p class="up-booking-location"><?php echo h($booking['location']); ?></p>
              <p class="up-booking-dates"><img src="images/calendaricon-userprofile.png" alt=""><?php echo h($booking['dates']); ?></p>
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

        <a href="mybookings.php" class="up-btn-outline up-view-all-bookings">VIEW ALL BOOKINGS</a>
      </div>

      <div class="up-right-col">

        <div class="up-card up-payment-summary">
          <div class="up-card-header">
            <h3>Payment Summary</h3>
            <a href="payments.php" class="up-link-view-all">View All</a>
          </div>
          <div class="up-payment-summary-body">
            <div>
              <span class="up-muted">Total Spent</span>
              <strong>&#8369; <?php echo h($total_spent); ?></strong>
              <span class="up-muted">All Time</span>
            </div>
            <img src="images/totalspenticon-userprofile.png" alt="" class="up-payment-summary-icon">
          </div>
        </div>

        <div class="up-card up-payment-methods">
          <div class="up-card-header">
            <h3>Payment Methods</h3>
            <a href="payments.php" class="up-link-view-all">Manage</a>
          </div>

          <?php foreach ($payment_methods as $method): ?>
            <div class="up-card-item">
              <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>">
              <div>
                <strong>&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; <?php echo h($method['last4']); ?></strong>
                <span>Expires <?php echo h($method['expires']); ?></span>
              </div>
            </div>
          <?php endforeach; ?>

          <button type="button" class="up-btn-outline up-add-card">+ Add New Card</button>
        </div>

        <div class="up-card up-account-security">
          <div class="up-card-header">
            <h3>Account Security</h3>
            <span class="up-badge-secure">
              <img src="images/lockicon-userprofile.png" alt="">
              Secure
            </span>
          </div>
          <div class="up-security-row">
            <span>Two-Factor Authentication</span>
            <span class="<?php echo $two_factor_enabled ? 'up-security-enabled' : 'up-security-disabled'; ?>">
              <?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
          </div>
          <a href="security.php" class="up-btn-outline up-manage-security">Manage Security</a>
        </div>

        <div class="up-need-help">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Our support team is here to assist you 24/7.</p>
            <a href="helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

      </div>
    </section>

    <!-- WISHLIST -->
    <section class="up-card up-wishlist">
      <div class="up-card-header">
        <h3>Wishlist (<?php echo h($wishlist_total); ?>)</h3>
        <a href="wishlist.php" class="up-link-view-all">View All</a>
      </div>

      <div class="up-wishlist-grid">
        <?php foreach ($wishlist as $item): ?>
          <a href="listing.php?id=<?php echo h($item['id']); ?>" class="listing-box" data-listing-id="<?php echo h($item['id']); ?>">
            <div class="up-wishlist-thumb">
              <img src="<?php echo h($item['thumb']); ?>" alt="<?php echo h($item['title']); ?>">
              <button type="button"
                      class="rh-save-btn<?php echo $item['saved'] ? ' saved' : ''; ?>"
                      data-listing-id="<?php echo h($item['id']); ?>"
                      aria-label="<?php echo $item['saved'] ? 'Remove from saved' : 'Save listing'; ?>">
                &#9829;
              </button>
            </div>
            <h4><?php echo h($item['title']); ?></h4>
            <p class="up-wishlist-location"><?php echo h($item['location']); ?></p>
            <div class="up-wishlist-meta">
              <span class="up-wishlist-price">&#8369; <?php echo h($item['price']); ?> / night</span>
              <span class="up-wishlist-rating">&#9733; <?php echo h($item['rating']); ?> (<?php echo h($item['reviews']); ?>)</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

  </div>
</main>

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