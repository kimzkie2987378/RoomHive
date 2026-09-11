<?php
/* =========================================================
   ROOMHIVE — HOST BOOKINGS
   hostbookings.php

   Same navbar / sidebar / dashboard shell as hostprofile.php.
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

$stmt = $pdo->prepare(
    "SELECT id, name, email, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   HOST GUARD
   Same reasoning as hostprofile.php — see that file for the
   full note on why this redirects to hostprofile.php for now.
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change back to becomeahost.php once that file exists
    exit;
}

/* -----------------------------------------------------
   HOST DATA (sidebar card)
----------------------------------------------------- */
$host = [
    'name'         => $dbUser['name'],
    'avatar'       => '/webprogg/images/default-avatar.png',
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

$notification_count = 0; // TODO: wire up once a notifications table exists

/* -----------------------------------------------------
   CSRF TOKEN
   One token per session, reused by every Accept/Decline form
   on this page and checked by bookingaction.php.
----------------------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* -----------------------------------------------------
   BOOKINGS
   Real query against the actual schema: bookings belonging to
   any listing this host owns, joined to the listing (title,
   location, price, cover photo) and the guest (name).

   Note: `bookings` has no check-in/check-out columns yet —
   only `booked_at` and `total` — so there's no date-range line
   on the card. Add start_date/end_date columns to `bookings`
   later if you want stay length shown here.
----------------------------------------------------- */
$bookingsStmt = $pdo->prepare(
    "SELECT
        b.id,
        b.status,
        b.total,
        b.booked_at,
        l.title    AS listing_title,
        l.location AS listing_location,
        u.name     AS guest_name,
        (SELECT lp.photo_path
         FROM listing_photos lp
         WHERE lp.listing_id = l.id AND lp.photo_type = 'cover'
         ORDER BY lp.sort_order ASC
         LIMIT 1) AS cover_photo
     FROM bookings b
     INNER JOIN listings l ON l.id = b.listing_id
     INNER JOIN users u    ON u.id = b.user_id
     WHERE l.user_id = :host_id
     ORDER BY b.booked_at DESC"
);
$bookingsStmt->execute(['host_id' => $_SESSION['user_id']]);
$bookings = $bookingsStmt->fetchAll();

/* Maps the real bookings.status enum to a badge style/label. */
$statusMeta = [
    'pending'   => ['label' => 'Pending Approval', 'class' => 'hp-badge-yellow'],
    'confirmed' => ['label' => 'Confirmed',        'class' => 'hp-badge-green'],
    'completed' => ['label' => 'Completed',        'class' => 'hp-badge-blue'],
    'cancelled' => ['label' => 'Cancelled',        'class' => 'hp-badge-red'],
];

$total     = count($bookings);
$confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
$pending   = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));

/* Tab filter: ?filter=all|pending|confirmed|cancelled */
$filter = $_GET['filter'] ?? 'all';
$filtered = array_filter($bookings, function ($b) use ($filter) {
    if ($filter === 'pending')   return $b['status'] === 'pending';
    if ($filter === 'confirmed') return $b['status'] === 'confirmed';
    if ($filter === 'cancelled') return $b['status'] === 'cancelled';
    return true;
});

/* Flash message from bookingaction.php's redirect (?msg=accepted|declined|error) */
$flash = $_GET['msg'] ?? null;
$flashText = [
    'accepted'  => ['type' => 'success', 'text' => 'Booking accepted.'],
    'declined'  => ['type' => 'success', 'text' => 'Booking declined.'],
    'error'     => ['type' => 'error',   'text' => 'That booking could not be updated. It may have already been handled.'],
][$flash] ?? null;

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------
   PHOTO PATH RESOLUTION
   `listing_photos.photo_path` is stored RELATIVE (e.g.
   "uploads/listing_photos/cover/cover_123_abc.jpg"), not
   rooted at "/webprogg/...". That's on purpose (see the
   FIX note in host-step3.php) — but it means every page
   that PRINTS a photo_path into an <img src> has to prefix
   it with "/webprogg/" first, or the browser resolves it
   relative to the current page's own folder instead of the
   site root (e.g. "/webprogg/host/uploads/..." instead of
   "/webprogg/uploads/..."), and the image 404s.

   This page was missing that step — it printed cover_photo
   straight from the DB. resolve_photo() fixes that the same
   way mylistings.php / pendingtenants.php / etc. already do.
----------------------------------------------------- */
function resolve_photo($path) {
    if (!$path) {
        return null;
    }
    return '/webprogg/' . ltrim($path, '/');
}

/* Cache-buster for the stylesheet so browsers don't keep serving a
   stale cached copy after edits (e.g. this fix) are deployed. Bump
   the number any time hostprofile.css changes and you're not seeing
   the update reflected live. */
$hp_css_version = '4';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=<?php echo h($hp_css_version); ?>">
</head>
<body>

<!-- =========================================================
     NAVBAR (identical markup/classes to hostprofile.php)
========================================================= -->
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

            <button
                type="button"
                class="my-account js-account-toggle"
                id="accountDropdownToggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <span class="account-circle">
                    <img src="/webprogg/images/MyAccountIcon.png" alt="My Account">
                </span>
                <span>MY ACCOUNT</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <a href="/webprogg/user/myaccount.php">My Account</a>
                <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <!-- SIDEBAR (identical to hostprofile.php, "Bookings" active) -->
  <aside class="hp-sidebar">

    <div class="hp-sidebar-card">
      <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar">
      <h4><?php echo h($host['name']); ?></h4>
      <span class="hp-host-badge">Host</span>
      <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="/webprogg/host/hostprofile.php" class="hp-side-link">
      <img src="/webprogg/images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="/webprogg/user/mylistings.php" class="hp-side-link">
      <img src="/webprogg/images/mylistingsicon-hostprofile.png" alt="">
      My Listings
    </a>
    <a href="/webprogg/host/hostbookings.php" class="hp-side-link active">
      <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
      Bookings
    </a>
    <a href="earnings.php" class="hp-side-link">
      <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
      Earnings
    </a>
    <a href="payouts.php" class="hp-side-link">
      <img src="/webprogg/images/paymentsicon-userprofile.png" alt="">
      Payouts
    </a>
    <a href="hostreviews.php" class="hp-side-link">
      <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="hostmessages.php" class="hp-side-link">
      <img src="/webprogg/images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="hosteditprofile.php" class="hp-side-link">
      <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="verification.php" class="hp-side-link">
      <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
      Verification
    </a>
    <a href="payoutmethods.php" class="hp-side-link">
      <img src="/webprogg/images/payoutmethodsicon-hostprofile.png" alt="">
      Payout Methods
    </a>
    <a href="hostnotificationsettings.php" class="hp-side-link">
      <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="hostsecurity.php" class="hp-side-link">
      <img src="/webprogg/images/lockicon-userprofile.png" alt="">
      Security
    </a>
    <a href="helpcenter.php" class="hp-side-link">
      <img src="/webprogg/images/needhelpicon-userprofile.png" alt="">
      Help Center
    </a>
    <a href="/webprogg/auth/logout.php" class="hp-side-link hp-side-logout">
      <img src="/webprogg/images/logouticon-userprofile.png" alt="">
      Log Out
    </a>

  </aside>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">My Bookings</h1>
        <p class="hp-page-subtitle">View and manage all your booking requests and confirmed stays.</p>
      </div>

      <div class="hp-stat-pillbox">
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Total Bookings</p>
            <p class="hp-stat-pill-value"><?php echo h($total); ?></p>
          </div>
        </div>
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon hp-green">
            <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Confirmed Stays</p>
            <p class="hp-stat-pill-value"><?php echo h($confirmed); ?></p>
          </div>
        </div>
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon hp-yellow">
            <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Pending Requests</p>
            <p class="hp-stat-pill-value"><?php echo h($pending); ?></p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flashText): ?>
      <div class="hp-flash hp-flash-<?php echo h($flashText['type']); ?>">
        <?php echo h($flashText['text']); ?>
      </div>
    <?php endif; ?>

    <div class="hp-controls">
      <div class="hp-tabs">
        <a class="hp-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All (<?php echo h($total); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="?filter=pending">Pending (<?php echo h($pending); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>" href="?filter=confirmed">Confirmed (<?php echo h($confirmed); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" href="?filter=cancelled">Cancelled (<?php echo h($cancelled); ?>)</a>
      </div>
      <div class="hp-search-wrap">
        <img src="/webprogg/images/searchicon-userprofile.png" alt="">
        <input type="text" placeholder="Search by guest name, property or date...">
      </div>
      <button type="button" class="hp-filter-btn">
        <img src="/webprogg/images/filtericon-userprofile.png" alt="">
        Filter
      </button>
    </div>

    <div class="hp-booking-list">
      <?php if (empty($bookings)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No bookings yet</p>
          <p>Once guests start booking your listings, their requests and stays will show up here.</p>
        </div>

      <?php elseif (empty($filtered)): ?>

        <div class="hp-empty-state">No bookings match this filter yet.</div>

      <?php else: foreach ($filtered as $b):
        $meta = $statusMeta[$b['status']] ?? ['label' => ucfirst($b['status']), 'class' => 'hp-badge-yellow'];
        $imageSrc = resolve_photo($b['cover_photo']) ?: '/webprogg/images/listing-placeholder.jpg';
      ?>
      <div class="hp-booking-card">
        <img class="hp-booking-img" src="<?php echo h($imageSrc); ?>" alt="<?php echo h($b['listing_title']); ?>">
        <div class="hp-booking-info">
          <h4><?php echo h($b['listing_title']); ?></h4>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/locationicon-userprofile.png" alt="">
            <?php echo h($b['listing_location']); ?>
          </div>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
            <?php echo h($b['guest_name']); ?>
          </div>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            Booked <?php echo h(date('M j, Y', strtotime($b['booked_at']))); ?>
          </div>
        </div>

        <div class="hp-booking-right">
          <div class="hp-booking-status-col">
            <span class="hp-badge <?php echo $meta['class']; ?>"><?php echo h($meta['label']); ?></span>
            <p class="hp-booking-rent-label">Total</p>
            <p class="hp-booking-rent-value">&#8369; <?php echo number_format($b['total'], 2); ?></p>
          </div>

          <?php if ($b['status'] === 'pending'): ?>
            <div class="hp-booking-actions">
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-accept">Accept</button>
              </form>
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;"
                    onsubmit="return confirm('Decline this booking request?');">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-decline">Decline</button>
              </form>
            </div>
          <?php else: ?>
            <div class="hp-booking-actions">
              <button type="button" class="hp-btn-outline">View Details</button>
              <button type="button" class="hp-listing-menu" aria-label="More options">&#8942;</button>
            </div>
          <?php endif; ?>
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