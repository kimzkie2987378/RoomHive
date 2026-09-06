<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userprofile.php
========================================================= */

session_start();
require_once 'db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
   Redirect to login if nobody is signed in.
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
   Pulled straight from the `users` row created back on
   createaccount.php — Full Name -> name, Email -> email.

   The `users` table has no avatar / about columns, so those
   stay blank/placeholder until those columns (or an
   "editprofile" flow) exist. phone/location ARE real columns
   now (set on becomeahost.php), so we pull them for real.
----------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id, name, email, phone, location, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

/* If the session points at a user that no longer exists
   (e.g. deleted account), don't render with fake data —
   send them back to log in cleanly instead. */
if (!$dbUser) {
    session_destroy();
    header("Location: loginform.php");
    exit;
}

$user = [
    'name'          => $dbUser['name'],
    'avatar'        => 'images/default-avatar.png',
    'location'      => $dbUser['location'] ?? '',
    'email'         => $dbUser['email'],
    'phone'         => $dbUser['phone'] ?? '',
    'member_since'  => date('F Y', strtotime($dbUser['created_at'])),
    'about'         => '', // no column in `users` yet
];

/* -----------------------------------------------------
   HIVE CLUB MEMBERSHIP
   Looks up the user's row in hive_members (if any) so the
   profile card can show their tier instead of a generic
   "Verified" badge. No row = not a Hive Club member yet.
----------------------------------------------------- */
$membershipStmt = $pdo->prepare(
    "SELECT tier, membership_status FROM hive_members WHERE user_id = :id LIMIT 1"
);
$membershipStmt->execute(['id' => $_SESSION['user_id']]);
$membership = $membershipStmt->fetch();

$notification_count = 0;

/* -----------------------------------------------------
   REVIEWS
   SELECT rating FROM reviews WHERE user_id = ?
----------------------------------------------------- */
$reviewsStmt = $pdo->prepare(
    "SELECT rating FROM reviews WHERE user_id = :id"
);
$reviewsStmt->execute(['id' => $_SESSION['user_id']]);
$reviews = array_map('floatval', array_column($reviewsStmt->fetchAll(), 'rating'));

$average_rating = count($reviews) > 0
    ? round(array_sum($reviews) / count($reviews), 1)
    : 0;

/* -----------------------------------------------------
   RECENT BOOKINGS
   Real query against `bookings`, joined to `listings` for
   the title/location and `listing_photos` for the cover
   thumbnail. Most recent 3 shown here; full history lives
   on mybookings.php.
----------------------------------------------------- */
$bookingsStmt = $pdo->prepare(
    "SELECT b.id, b.total, b.status, b.booked_at,
            l.title, l.location,
            p.photo_path AS cover_photo
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE b.user_id = :id
     ORDER BY b.booked_at DESC
     LIMIT 3"
);
$bookingsStmt->execute(['id' => $_SESSION['user_id']]);

$bookings = array_map(function ($row) {
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'thumb'    => $row['cover_photo'] ?? 'images/ListingPlaceholder.png',
        'dates'    => date('M j, Y', strtotime($row['booked_at'])),
        'status'   => $row['status'],
        'total'    => number_format((float) $row['total'], 2),
    ];
}, $bookingsStmt->fetchAll());

/* -----------------------------------------------------
   PAYMENT SUMMARY — BOOKINGS SPENT
   Sums the user's own bookings (all statuses except
   cancelled), split into "this week" and "all time".
----------------------------------------------------- */
$allBookingsStmt = $pdo->prepare(
    "SELECT total, booked_at
     FROM bookings
     WHERE user_id = :id AND status != 'cancelled'"
);
$allBookingsStmt->execute(['id' => $_SESSION['user_id']]);
$allBookingsForSpend = $allBookingsStmt->fetchAll();

$oneWeekAgo = strtotime('-7 days');

$bookings_spent_all_time = 0;
$bookings_spent_this_week = 0;

foreach ($allBookingsForSpend as $b) {
    $amount = (float) $b['total'];
    $bookings_spent_all_time += $amount;

    if (strtotime($b['booked_at']) >= $oneWeekAgo) {
        $bookings_spent_this_week += $amount;
    }
}

/* -----------------------------------------------------
   HIVE CLUB MEMBERSHIP PAYMENTS
   Every successful membership purchase should already be
   recorded as a row in hiveclub_transactions (payment_status
   = 'paid') at the point of purchase.
----------------------------------------------------- */
$transactionsStmt = $pdo->prepare(
    "SELECT amount, purchased_at
     FROM hiveclub_transactions
     WHERE user_id = :id AND payment_status = 'paid'"
);
$transactionsStmt->execute(['id' => $_SESSION['user_id']]);
$membershipTransactions = $transactionsStmt->fetchAll();

$membership_spent_all_time = 0;
$membership_spent_this_week = 0;

foreach ($membershipTransactions as $txn) {
    $amount = (float) $txn['amount'];
    $membership_spent_all_time += $amount;

    if (strtotime($txn['purchased_at']) >= $oneWeekAgo) {
        $membership_spent_this_week += $amount;
    }
}

/* -----------------------------------------------------
   COMBINED TOTALS
   "This Week" and "All Time" fold together real bookings
   and paid Hive Club membership transactions.
----------------------------------------------------- */
$total_spent_this_week = number_format($bookings_spent_this_week + $membership_spent_this_week, 2);
$total_spent_all_time  = number_format($bookings_spent_all_time + $membership_spent_all_time, 2);

/* -----------------------------------------------------
   PAYMENT METHODS
   TODO: replace with SELECT * FROM payment_methods WHERE user_id = ?
----------------------------------------------------- */
$payment_methods = [
    [
        'icon'    => 'images/visalogo.png',
        'label'   => 'Visa',
        'last4'   => '4242',
        'expires' => '12/26',
    ],
    [
        'icon'    => 'images/mastercardlogo.png',
        'label'   => 'Mastercard',
        'last4'   => '8881',
        'expires' => '08/27',
    ],
];

/* TODO: replace with a real `users.two_factor_enabled` column
   (or a separate `two_factor_auth` table) once the actual 2FA
   setup flow exists. Defaulting to false since nothing has
   enabled it yet — showing "Enabled" here would be inaccurate. */
$two_factor_enabled = false;

/* -----------------------------------------------------
   WISHLIST
   TODO: replace with
   SELECT * FROM listings l
   JOIN wishlist w ON w.listing_id = l.id
   WHERE w.user_id = ?
----------------------------------------------------- */
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

/* Derived counts — always reflect the actual $wishlist array,
   never hardcode these separately or they'll drift out of sync. */
$wishlist_total = count($wishlist);

/* -----------------------------------------------------
   STATS ROW
   Bookings Total is now a real count; the rest follow the
   same "no fake data" rule as before.
----------------------------------------------------- */
$stats = [
    ['icon' => 'bookingsicon-userprofile.png',     'value' => count($bookings),  'label' => 'Bookings Total'],
    ['icon' => 'wihlistedicon-userprofile.png',    'value' => $wishlist_total,   'label' => 'Wishlisted Properties'],
    ['icon' => 'averageratinsicon-userprofile.png','value' => $average_rating,      'label' => 'Average Rating From Reviews'],
    ['icon' => 'totalspenticon-userprofile.png',   'value' => '&#8369; ' . $total_spent_all_time, 'label' => 'Total Spent All Time'],
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
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <?php if (isset($_SESSION['is_host']) && $_SESSION['is_host'] === true): ?>
                    <a href="hostprofile.php">Host Profile</a>
                <?php endif; ?>
                <a href="userprofile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<?php if (isset($_GET['booked'])): ?>
<section style="max-width:900px; margin:16px auto 0; padding:12px 18px; border-radius:10px; background:#eaf7ee; border:1px solid #2f9e5c; color:#1f6b3b; font-size:0.9rem;">
    Your inquiry was sent! Check "My Bookings" below for the details.
</section>
<?php endif; ?>

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
      <img src="images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="notificationsettings.php" class="up-side-link">
      <img src="images/notificationsettings-userprofile.png" alt="">
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
          <?php if ($membership): ?>
            <span class="up-badge-verified">
              <img src="images/verifiedicon-userprofile.png" alt="">
              <?php echo h(ucfirst($membership['tier'])); ?> Member
            </span>
          <?php else: ?>
            <a href="hiveclub.php" class="up-badge-verified" style="text-decoration:none; background:#f0f0f0; color:#777777;">
              Join Hive Club
            </a>
          <?php endif; ?>
        </div>

        <ul class="up-profile-meta">
          <?php if ($user['location'] !== ''): ?>
            <li><img src="images/locationicon-userprofile.png" alt=""><?php echo h($user['location']); ?></li>
          <?php endif; ?>
          <li><img src="images/emailicon-userprofile.png" alt=""><?php echo h($user['email']); ?></li>
          <li><img src="images/phoneicon-userprofile.png" alt=""><?php echo $user['phone'] !== '' ? h($user['phone']) : ''; ?></li>
          <li><img src="images/calendaricon-userprofile.png" alt="">Member since <?php echo h($user['member_since']); ?></li>
        </ul>
      </div>

      <div class="up-profile-about">
        <h3>About Me</h3>
        <p><?php echo $user['about'] !== '' ? h($user['about']) : 'No bio added yet.'; ?></p>
      </div>

      <button type="button" class="up-btn-outline up-edit-profile" id="editProfileButton">Edit Profile</button>
    </section>

    <!-- STATS ROW -->
    <section class="up-stats">
      <?php foreach ($stats as $stat): ?>
        <div class="up-stat-card<?php echo $stat['label'] === 'Wishlisted Properties' ? ' up-stat-wishlist' : ''; ?>">
          <img src="images/<?php echo h($stat['icon']); ?>" alt="">
          <div>
            <strong><?php echo $stat['value']; /* may contain an HTML entity, not user input */ ?></strong>
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

        <?php if (empty($bookings)): ?>

          <div class="up-bookings-empty">
            <p class="up-bookings-empty-title">No bookings yet</p>
            <p class="up-bookings-empty-text">Once you book a stay, it will show up here.</p>
            <a href="listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
          </div>

        <?php else: ?>

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

        <?php endif; ?>
      </div>

      <div class="up-right-col">

        <div class="up-card up-payment-summary">
          <div class="up-card-header">
            <h3>Payment Summary</h3>
            <a href="payments.php" class="up-link-view-all">View All</a>
          </div>

          <!-- WEEK / ALL TIME TOGGLE -->
          <div class="up-summary-toggle" role="tablist" style="display:flex; gap:6px; margin-bottom:12px;">
            <button
              type="button"
              class="up-summary-tab active"
              data-range="week"
              style="flex:1; padding:6px 10px; border:1px solid var(--up-border); border-radius:8px; background:var(--up-orange-light); color:var(--up-orange); font-size:11px; font-weight:700; cursor:pointer;"
            >
              This Week
            </button>
            <button
              type="button"
              class="up-summary-tab"
              data-range="all"
              style="flex:1; padding:6px 10px; border:1px solid var(--up-border); border-radius:8px; background:#ffffff; color:#777777; font-size:11px; font-weight:700; cursor:pointer;"
            >
              All Time
            </button>
          </div>

          <div class="up-payment-summary-body">
            <div>
              <span class="up-muted">Total Spent</span>
              <strong>
                &#8369;
                <span
                  class="up-summary-amount"
                  data-week="<?php echo h($total_spent_this_week); ?>"
                  data-all="<?php echo h($total_spent_all_time); ?>"
                ><?php echo h($total_spent_this_week); ?></span>
              </strong>
              <span class="up-muted up-summary-range-label">Last 7 Days</span>
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
            <span
              class="<?php echo $two_factor_enabled ? 'up-security-enabled' : 'up-security-disabled'; ?>"
              <?php if (!$two_factor_enabled): ?>style="color:#e0524d; font-weight:700;"<?php endif; ?>
            >
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
        <h3>Wishlist (<span class="up-wishlist-count"><?php echo h($wishlist_total); ?></span>)</h3>
        <a href="wishlist.php" class="up-link-view-all">View All</a>
      </div>

      <div class="up-wishlist-grid" id="up-wishlist-grid">
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

      <!-- Shown by JS once every wishlist item has been removed.
           Hidden by default via inline style; toggled in the script below. -->
      <div class="up-wishlist-empty" id="up-wishlist-empty" style="display:none; text-align:center; padding:32px 12px; color:#777777;">
        <p style="margin:0 0 4px; font-weight:700; color:var(--up-navy, #1c2a38);">Your wishlist is empty</p>
        <p style="margin:0; font-size:13px;">Save listings you like and they'll show up here.</p>
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

<!-- =========================================================
     PAYMENT SUMMARY — WEEK / ALL TIME TOGGLE
     Both figures are computed server-side in PHP already
     (bookings + paid hiveclub_transactions); this just swaps
     which one is displayed without a page reload.
========================================================= -->
<script>
(function () {

    const tabs = document.querySelectorAll('.up-summary-tab');
    const amountEl = document.querySelector('.up-summary-amount');
    const rangeLabel = document.querySelector('.up-summary-range-label');

    if (!tabs.length || !amountEl) {
        return;
    }

    tabs.forEach(function (tab) {

        tab.addEventListener('click', function () {

            tabs.forEach(function (t) {
                t.classList.remove('active');
                t.style.background = '#ffffff';
                t.style.color = '#777777';
            });

            tab.classList.add('active');
            tab.style.background = 'var(--up-orange-light)';
            tab.style.color = 'var(--up-orange)';

            const range = tab.getAttribute('data-range');

            if (range === 'all') {
                amountEl.textContent = amountEl.getAttribute('data-all');
                if (rangeLabel) rangeLabel.textContent = 'All Time';
            } else {
                amountEl.textContent = amountEl.getAttribute('data-week');
                if (rangeLabel) rangeLabel.textContent = 'Last 7 Days';
            }
        });

    });

})();
</script>

<!-- =========================================================
     WISHLIST — REMOVE PERMANENTLY + LIVE COUNT SYNC
     TODO: once a real backend exists, replace the localStorage
     calls below with a fetch() to something like
     DELETE /api/wishlist/{id} and drop the localStorage bits.
========================================================= -->
<script>
(function () {

    const STORAGE_KEY = 'roomhive_removed_wishlist_ids';

    function getRemovedIds() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    function saveRemovedIds(ids) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    }

    // Recomputes every count/UI bit from what's actually still in
    // the DOM, rather than subtracting from a stale number — this
    // is what keeps the header, the stat card, and the empty state
    // all correct no matter how many items were removed, including
    // ones removed on a previous visit before this page even loaded.
    function syncWishlistUI() {
        const grid = document.getElementById('up-wishlist-grid');
        const emptyState = document.getElementById('up-wishlist-empty');
        const remaining = grid ? grid.querySelectorAll('.listing-box').length : 0;

        const countEl = document.querySelector('.up-wishlist-count');
        if (countEl) {
            countEl.textContent = remaining;
        }

        const statValue = document.querySelector('.up-stat-wishlist strong');
        if (statValue) {
            statValue.textContent = remaining;
        }

        if (grid && emptyState) {
            grid.style.display = remaining === 0 ? 'none' : '';
            emptyState.style.display = remaining === 0 ? '' : 'none';
        }
    }

    const removedIds = getRemovedIds();

    // Hide anything already removed on a previous visit
    document.querySelectorAll('#up-wishlist-grid .listing-box').forEach(function (box) {
        const id = box.getAttribute('data-listing-id');
        if (removedIds.includes(id)) {
            box.remove();
        }
    });

    // Reflect the real remaining count immediately (covers the case
    // where everything was already removed before this page load)
    syncWishlistUI();

    // Wire up the heart buttons
    document.querySelectorAll('#up-wishlist-grid .rh-save-btn').forEach(function (btn) {

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const box = btn.closest('.listing-box');
            if (!box) return;

            const id = btn.getAttribute('data-listing-id');
            const ids = getRemovedIds();

            if (!ids.includes(id)) {
                ids.push(id);
                saveRemovedIds(ids);
            }

            box.remove();
            syncWishlistUI();
        });

    });

})();
</script>
</body>
</html>