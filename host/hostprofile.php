<?php
/* =========================================================
   ROOMHIVE — HOST PROFILE
   hostprofile.php

 
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
    "SELECT id, name, email, phone, age, location, avatar_path, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}
/* -----------------------------------------------------
   HOST DATA
   phone/location ARE real columns (set on becomeahost.php),
   so we pull them for real — same as userprofile.php.
   `about` still has no column in `users` yet.
----------------------------------------------------- */
/* Keep the navbar's account icon in sync too — it's a
   separate <img> from the sidebar/profile photo below, so
   both need the same source of truth. */
$_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

$host = [
    'name'          => $dbUser['name'],
    'avatar'        => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'location'      => $dbUser['location'] ?? '',
    'email'         => $dbUser['email'],
    'phone'         => $dbUser['phone'] ?? '',
    'age'           => $dbUser['age'] ?? '',
    'member_since'  => date('F Y', strtotime($dbUser['created_at'])),
    'about'         => '', // no column in `users` yet
];

$notification_count = 0;

/* -----------------------------------------------------
   MY LISTINGS
   Real query against the `listings` table for this host.
----------------------------------------------------- */
/* pending_booking_id / pending_tenant_name come from whichever
   booking on that listing is still awaiting the host's decision
   (status = 'pending') — that's what the Accept/Reject options in
   the 3-dot menu below act on. A listing only ever has one active
   (pending or confirmed) booking at a time, so this LEFT JOIN
   won't duplicate rows. */
$listingsStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.location, l.exact_address, l.price, l.status, l.created_at,
            p.photo_path AS cover_photo,
            pb.id AS pending_booking_id, tu.name AS pending_tenant_name
     FROM listings l
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     LEFT JOIN bookings pb ON pb.listing_id = l.id AND pb.status = 'pending'
     LEFT JOIN users tu ON tu.id = pb.user_id
     WHERE l.user_id = :id
     ORDER BY l.created_at DESC"
);
$listingsStmt->execute(['id' => $_SESSION['user_id']]);
$listings = $listingsStmt->fetchAll();

$listings_total = count($listings);

/* -----------------------------------------------------
   PENDING TENANTS COUNT
   How many tenant applications, across all of this host's
   listings, are still sitting at status = 'pending' — shown
   as a badge next to the "Pending Tenants" sidebar link.
----------------------------------------------------- */
$pendingTenantsCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id AND b.status = 'pending'"
);
$pendingTenantsCountStmt->execute(['id' => $_SESSION['user_id']]);
$pending_tenants_count = (int) $pendingTenantsCountStmt->fetchColumn();

/* -----------------------------------------------------
   PERFORMANCE OVERVIEW
   TODO: Views need a page-view tracking table; Bookings and
   Occupancy Rate need a real bookings table (neither exists
   yet in the schema); Earnings should sum confirmed bookings
   for this host's listings once that table exists too. Until
   then these correctly show 0 instead of invented demo numbers.
   Per-listing Bookings / Occupancy / Earnings below depend on
   the same missing table, so each listing shows "—" for those
   columns for now.
----------------------------------------------------- */
$total_views = 0; // still no page-view tracking table

/* -----------------------------------------------------
   BOOKING-BASED METRICS
   Bookings = confirmed/completed only (not pending — those
   are still just applications). Earnings = paid confirmed/
   completed bookings. Occupancy = nights booked in the last
   30 days as a % of 30, since listings have no fixed total
   availability window in this schema.
----------------------------------------------------- */
$hostBookingsStmt = $pdo->prepare(
    "SELECT b.listing_id, b.total, b.paid_at, b.checkin_date, b.checkout_date
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed', 'completed')"
);

$hostBookingsStmt->execute(['id' => $_SESSION['user_id']]);
$hostBookings = $hostBookingsStmt->fetchAll();

/* Overlap (in nights) between a booking's stay and the
   30-day occupancy window. Null checkout (long-term/
   ongoing) counts as occupying through the end of the
   window. */
function hp_overlap_nights($checkin, $checkout, DateTime $windowStart, DateTime $windowEnd) {
    if (empty($checkin)) {
        return 0;
    }
    $start = new DateTime($checkin);
    $end   = !empty($checkout) ? new DateTime($checkout) : (clone $windowEnd)->modify('+1 day');

    $overlapStart = max($start, $windowStart);
    $overlapEnd   = min($end, $windowEnd);

    if ($overlapEnd <= $overlapStart) {
        return 0;
    }
    return $overlapStart->diff($overlapEnd)->days;
}
/* -----------------------------------------------------
   PER-LISTING BOOKINGS COUNT
   Same rule as hostprofile.php: confirmed/completed only
   (pending is still just an application, not a real booking).
   Views and Rating still have no backing table/column, so
   those stay as "—" for now.
----------------------------------------------------- */
$listingBookingCounts = [];
foreach ($listings as $l) {
    $listingBookingCounts[$l['id']] = 0;
}

if (!empty($listings)) {
    $bookingCountsStmt = $pdo->prepare(
        "SELECT b.listing_id, COUNT(*) AS booking_count
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE l.user_id = :id
           AND b.status IN ('confirmed', 'completed')
         GROUP BY b.listing_id"
    );
    $bookingCountsStmt->execute(['id' => $_SESSION['user_id']]);
    foreach ($bookingCountsStmt->fetchAll() as $row) {
        $listingBookingCounts[(int) $row['listing_id']] = (int) $row['booking_count'];
    }
}

$occupancyWindowDays = 30;
$windowStart = new DateTime("-{$occupancyWindowDays} days");
$windowEnd   = new DateTime('today');

/* Per-listing tallies, seeded so every listing shows real
   zeros instead of missing keys if it has no bookings. */
$listingStats = [];
foreach ($listings as $l) {
    $listingStats[$l['id']] = [
        'bookings'        => 0,
        'earnings'        => 0.0,
        'occupied_nights' => 0,
    ];
}

foreach ($hostBookings as $b) {
    $lid = (int) $b['listing_id'];
    if (!isset($listingStats[$lid])) {
        continue;
    }

    $listingStats[$lid]['bookings']++;

    if (!empty($b['paid_at'])) {
        $listingStats[$lid]['earnings'] += (float) $b['total'];
    }

    $listingStats[$lid]['occupied_nights'] += hp_overlap_nights(
        $b['checkin_date'],
        $b['checkout_date'],
        $windowStart,
        $windowEnd
    );
}

$total_bookings        = 0;
$total_earnings        = 0.0;
$total_occupied_nights = 0;

foreach ($listingStats as $stats) {
    $total_bookings        += $stats['bookings'];
    $total_earnings        += $stats['earnings'];
    $total_occupied_nights += $stats['occupied_nights'];
}

$occupancy_rate = $listings_total > 0
    ? (int) round(min(100, ($total_occupied_nights / ($occupancyWindowDays * $listings_total)) * 100))
    : 0;

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------
   PHOTO PATH FIX
   Listing cover photos (listing_photos.photo_path) are saved
   relative to /webprogg — e.g. "uploads/listing_photos/cover/abc.jpg".
   Printed as-is (as this file previously did, with no fix
   applied), the browser resolves that against the CURRENT
   page's folder instead of the site root, so cover photos in
   the "My Listings" table 404'd and showed as broken images —
   even though this page's OWN avatar (already stored as a
   full "/webprogg/..." path by uploadavatar.php) worked fine.
   Same fix already applied on mylistings.php / pendingtenants.php
   / listingpayment.php / booking-details.php — normalize any
   leading slash / accidental "webprogg/" segment, then rebuild
   as an absolute "/webprogg/..." path every time.
----------------------------------------------------- */
function resolve_photo($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path; // full remote URL — leave it alone
    }
    $normalized = ltrim($path, '/');
    if (stripos($normalized, 'webprogg/') === 0) {
        $normalized = substr($normalized, strlen('webprogg/'));
    }
    return '/webprogg/' . $normalized;
}

/* Maps a listings.status value to a small status-pill class */
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Host Profile — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<!-- =========================================================
     NAVBAR (uses existing style.css — not redefined here,
     identical markup/classes to myaccount.php)
========================================================= -->
<header class="navbar">

    <!-- LOGO -->
    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION -->
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
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                </span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
    <?php if ($dbUser['is_host']): ?>
        <a href="/webprogg/host/hostprofile.php">My Profile</a>
    <?php endif; ?>
    <a href="/webprogg/auth/logout.php">Logout</a>
</div>

        </div>

    </nav>

</header>

<!-- =========================================================
     HOST PROFILE HERO
========================================================= -->
<section class="hp-hero">
  <div class="hp-hero-text">
    <h1>Host Profile</h1>
    <span class="hp-hero-underline"></span>
    <p>Manage your account, listings, and payouts all in one place.</p>
  </div>

  <div class="hp-hero-image">
    <img src="/webprogg/images/hostprofile-hero.jpg" alt="">
  </div>

  <div class="hp-earnings-float">
    <span class="hp-muted">Total Earnings</span>
    <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
    <span class="hp-earnings-note">No earnings data yet</span>
  </div>
</section>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard">

  <!-- SIDEBAR -->
  <aside class="hp-sidebar">

    <div class="hp-sidebar-card">
      <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar" id="hostSidebarAvatarImg">
      <h4><?php echo h($host['name']); ?></h4>
      <span class="hp-host-badge">Host</span>
      <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="/webprogg/host/hostprofile.php" class="hp-side-link active">
      <img src="/webprogg/images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="/webprogg/user/mylistings.php" class="hp-side-link">
      <img src="/webprogg/images/mylistingsicon-hostprofile.png" alt="">
      My Listings
    </a>
    <a href="/webprogg/booking/pendingtenants.php" class="hp-side-link hp-side-link-badged">
      <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
      Pending Tenants
      <?php if ($pending_tenants_count > 0): ?>
        <span class="hp-side-badge"><?php echo h($pending_tenants_count); ?></span>
      <?php endif; ?>
    </a>
    <a href="/webprogg/host/hostbookings.php" class="hp-side-link">
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

    <!-- PROFILE INFORMATION -->
    <section class="hp-card hp-profile-card">

      <div class="hp-card-header">
        <h3>Profile Information</h3>
        <button type="button" class="hp-btn-outline hp-edit-profile" id="hostEditProfileButton">Edit Profile</button>
      </div>

      <div class="hp-profile-body">

        <div class="hp-profile-photo">
          <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" id="hostProfileAvatarImg">
          <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
            <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
          </button>
          <input type="file" id="hostAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>

        <div class="hp-profile-col">
          <span class="hp-field-label">Full Name</span>
          <p class="hp-field-value"><?php echo h($host['name']); ?></p>

          <span class="hp-field-label">Email Address</span>
          <p class="hp-field-value"><?php echo h($host['email']); ?></p>

          <span class="hp-field-label">Phone Number</span>
          <p class="hp-field-value"><?php echo $host['phone'] !== '' ? h($host['phone']) : '&mdash;'; ?></p>

          <span class="hp-field-label">Age</span>
          <p class="hp-field-value"><?php echo $host['age'] !== '' ? h($host['age']) : '&mdash;'; ?></p>
        </div>

        <div class="hp-profile-col">
          <span class="hp-field-label">Location</span>
          <p class="hp-field-value hp-field-with-icon">
            <?php if ($host['location'] !== ''): ?>
              <img src="/webprogg/images/locationicon-userprofile.png" alt="">
              <?php echo h($host['location']); ?>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </p>

          <span class="hp-field-label">Bio</span>
          <p class="hp-field-value"><?php echo $host['about'] !== '' ? h($host['about']) : 'No bio added yet.'; ?></p>
        </div>

      </div>
    </section>

    <!-- PERFORMANCE OVERVIEW -->
    <section class="hp-card">
      <div class="hp-card-header">
        <h3>Performance Overview</h3>
      </div>

      <div class="hp-stats">

        <div class="hp-stat-card">
          <img src="/webprogg/images/viewsicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Views</span>
            <strong><?php echo h($total_views); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Bookings</span>
            <strong><?php echo h($total_bookings); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/occupancyicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Occupancy Rate</span>
            <strong><?php echo h($occupancy_rate); ?>%</strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Earnings</span>
            <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
          </div>
        </div>

      </div>
    </section>

    <!-- MY LISTINGS -->
    <section class="hp-card">
      <div class="hp-card-header">
        <h3>My Listings (<?php echo h($listings_total); ?>)</h3>
        <a href="/webprogg/user/mylistings.php" class="hp-link-view-all">View All Listings</a>
      </div>

      <?php if (empty($listings)): ?>

        <div class="hp-listings-empty">
          <p class="hp-listings-empty-title">You haven't listed any properties yet</p>
          <p class="hp-listings-empty-text">Once you add a space, it will show up here.</p>
          <a href="/webprogg/host/becomeahost.php" class="hp-btn-outline">LIST YOUR SPACE</a>
        </div>

      <?php else: ?>

        <div class="hp-listings-table">

          <div class="hp-listings-head">
            <span>Listing</span>
            <span>Bookings</span>
            <span>Occupancy</span>
            <span>Earnings</span>
            <span>Status</span>
          </div>

          <?php foreach ($listings as $listing): ?>
            <div class="hp-listing-row">

              <div class="hp-listing-info">
                <img src="<?php echo h(resolve_photo($listing['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="<?php echo h($listing['title']); ?>">
                <div>
                  <h4><?php echo h($listing['title']); ?></h4>
                  <p><?php echo h($listing['location']); ?></p>
                </div>
              </div>

              <?php
$stats = $listingStats[$listing['id']];
$listingOccupancy = $occupancyWindowDays > 0
    ? (int) round(min(100, ($stats['occupied_nights'] / $occupancyWindowDays) * 100))
    : 0;
?>
<span class="hp-listing-metric"><?php echo h($stats['bookings']); ?></span>
<span class="hp-listing-metric"><?php echo h($listingOccupancy); ?>%</span>
<span class="hp-listing-metric">&#8369; <?php echo h(number_format($stats['earnings'], 2)); ?></span>
              <div class="hp-listing-actions">
                <span class="hp-status <?php echo hp_status_class($listing['status']); ?>">
                  <?php echo h(hp_status_label($listing['status'])); ?>
                </span>
                <?php if (!empty($listing['pending_booking_id'])): ?>
                  <span class="hp-status hp-status-pending" title="<?php echo h($listing['pending_tenant_name']); ?> is awaiting your decision">
                    Applicant Pending
                  </span>
                <?php endif; ?>
                <div class="hp-menu-wrap">
                  <button type="button" class="hp-listing-menu" data-listing-id="<?php echo h($listing['id']); ?>" aria-haspopup="true" aria-expanded="false" aria-label="More options">
                    &#8942;
                  </button>
                  <div class="hp-menu-dropdown">
                    <?php if (!empty($listing['pending_booking_id'])): ?>
                      <button type="button" class="hp-menu-item hp-menu-accept" data-booking-id="<?php echo h($listing['pending_booking_id']); ?>" data-tenant-name="<?php echo h($listing['pending_tenant_name']); ?>">
                        Accept Tenant
                      </button>
                      <button type="button" class="hp-menu-item hp-menu-reject" data-booking-id="<?php echo h($listing['pending_booking_id']); ?>" data-tenant-name="<?php echo h($listing['pending_tenant_name']); ?>">
                        Reject Tenant
                      </button>
                    <?php endif; ?>
                    <button type="button" class="hp-menu-item hp-menu-delete" data-listing-id="<?php echo h($listing['id']); ?>" data-listing-title="<?php echo h($listing['title']); ?>">
                      Delete Listing
                    </button>
                  </div>
                </div>
              </div>

            </div>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>
    </section>

    <!-- GROW YOUR HOSTING BUSINESS -->
    <section class="hp-grow-banner">

      <div class="hp-grow-text">
        <img src="/webprogg/images/grow-hosting-illustration.png" alt="" class="hp-grow-illustration">
        <div>
          <h3>Grow Your Hosting Business</h3>
          <p>Get more bookings and increase your earnings with these host tools.</p>
        </div>
      </div>

      <div class="hp-grow-links">

        <a href="boostlisting.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128640;</span>
          <strong>Boost Your Listing</strong>
          <span>Get more visibility</span>
        </a>

        <a href="hosttips.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128161;</span>
          <strong>Host Tips</strong>
          <span>Learn and improve</span>
        </a>

        <a href="inviteandearn.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128101;</span>
          <strong>Invite &amp; Earn</strong>
          <span>Earn more rewards</span>
        </a>

      </div>

    </section>

  </div>
</main>

<!-- =========================================================
     FOOTER (uses existing style.css — not redefined here,
     identical markup/classes to myaccount.php)
========================================================= -->
<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->
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

        <!-- LISTINGS -->
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <!-- GET THE APP -->
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

<!-- =========================================================
     LISTING 3-DOT MENU + DELETE
========================================================= -->
<style>
    .hp-menu-wrap { position: relative; display: inline-block; }
    .hp-menu-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 160px;
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(20, 20, 43, 0.12);
        padding: 6px;
        z-index: 50;
    }
    .hp-menu-wrap.open .hp-menu-dropdown { display: block; }
    .hp-menu-item {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        cursor: pointer;
        color: #14142B;
    }
    .hp-menu-item:hover { background: #F6F7FB; }
    .hp-menu-delete { color: #E14B4B; }
    .hp-menu-delete:hover { background: #FCEAEA; }
    .hp-menu-accept { color: #1E7A3D; }
    .hp-menu-accept:hover { background: #E6F6EC; }
    .hp-menu-reject { color: #E14B4B; }
    .hp-menu-reject:hover { background: #FCEAEA; }
    .hp-side-link-badged { position: relative; display: flex; align-items: center; gap: 10px; }
    .hp-side-badge {
        margin-left: auto;
        background: #E14B4B;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        padding: 3px 7px;
        border-radius: 999px;
    }
</style>
<script>
(function () {
    // Open/close the 3-dot dropdown for whichever listing was clicked.
    document.querySelectorAll('.hp-listing-menu').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const wrap = btn.closest('.hp-menu-wrap');
            const wasOpen = wrap.classList.contains('open');

            // Close any other open menus first.
            document.querySelectorAll('.hp-menu-wrap.open').forEach(function (w) {
                w.classList.remove('open');
            });

            if (!wasOpen) {
                wrap.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            } else {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Clicking anywhere else closes any open menu.
    document.addEventListener('click', function () {
        document.querySelectorAll('.hp-menu-wrap.open').forEach(function (w) {
            w.classList.remove('open');
        });
    });

    // Delete a listing.
    document.querySelectorAll('.hp-menu-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const listingId = btn.getAttribute('data-listing-id');
            const listingTitle = btn.getAttribute('data-listing-title') || 'this listing';

            if (!confirm('Delete "' + listingTitle + '"? This cannot be undone.')) {
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Deleting...';

            fetch('/webprogg/Listings/delete-listing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        const row = btn.closest('.hp-listing-row');
                        if (row) row.remove();
                    } else {
                        alert(data.message || 'Could not delete this listing.');
                        btn.disabled = false;
                        btn.textContent = 'Delete Listing';
                    }
                })
                .catch(function () {
                    alert('Something went wrong deleting this listing. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Delete Listing';
                });
        });
    });

    // Accept or reject a tenant's application (booking) for a listing.
    function handleDecision(btn, endpoint, confirmMessage, busyText) {
        const bookingId = btn.getAttribute('data-booking-id');
        const tenantName = btn.getAttribute('data-tenant-name') || 'this tenant';

        if (!confirm(confirmMessage.replace('%s', tenantName))) {
            return;
        }

        btn.disabled = true;
        btn.textContent = busyText;

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + encodeURIComponent(bookingId)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not update this application.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                alert('Something went wrong. Please try again.');
                btn.disabled = false;
            });
    }

    document.querySelectorAll('.hp-menu-accept').forEach(function (btn) {
        btn.addEventListener('click', function () {
            handleDecision(
                btn,
                '/webprogg/booking/accept-booking.php',
                'Accept %s\'s application for this listing?',
                'Accepting...'
            );
        });
    });

    document.querySelectorAll('.hp-menu-reject').forEach(function (btn) {
        btn.addEventListener('click', function () {
            handleDecision(
                btn,
                '/webprogg/booking/reject-booking.php',
                'Reject %s\'s application for this listing?',
                'Rejecting...'
            );
        });
    });
})();
</script>

<!-- =========================================================
     PROFILE PHOTO — UPLOAD ON CAMERA ICON CLICK
     Opens the file picker, shows an instant local preview,
     uploads to uploadavatar.php, then swaps in the real saved
     image everywhere it appears on this page (profile card,
     sidebar card, and the navbar icon), or reverts + alerts
     on failure.
========================================================= -->
<script>
(function () {
    const photoButton  = document.getElementById('hostPhotoButton');
    const fileInput    = document.getElementById('hostAvatarFileInput');
    const avatarImg    = document.getElementById('hostProfileAvatarImg');
    const sidebarImg   = document.getElementById('hostSidebarAvatarImg');
    const navAvatarImg = document.getElementById('navAccountAvatarImg');

    if (!photoButton || !fileInput || !avatarImg) return;

    photoButton.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please choose a JPG, PNG, or WEBP image.');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('That image is too large. Please choose one under 5MB.');
            fileInput.value = '';
            return;
        }

        // Instant local preview while it uploads
        const previewUrl = URL.createObjectURL(file);
        const previousSrc = avatarImg.src;
        avatarImg.src = previewUrl;
        if (sidebarImg) sidebarImg.src = previewUrl;
        if (navAvatarImg) navAvatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/upload/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                avatarImg.src = data.avatar_url;
                if (sidebarImg) sidebarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                avatarImg.src = previousSrc;
                if (sidebarImg) sidebarImg.src = previousSrc;
                if (navAvatarImg) navAvatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function () {
            avatarImg.src = previousSrc;
            if (sidebarImg) sidebarImg.src = previousSrc;
            if (navAvatarImg) navAvatarImg.src = previousSrc;
            alert('Something went wrong uploading your photo. Please try again.');
        })
        .finally(function () {
            URL.revokeObjectURL(previewUrl);
            photoButton.disabled = false;
            fileInput.value = '';
        });
    });
})();
</script>

</body>
</html>