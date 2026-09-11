<?php
/* =========================================================
   ROOMHIVE — PENDING TENANTS
   pendingtenants.php

   Same navbar / sidebar / dashboard shell as mylistings.php
   and hostprofile.php. Shows every tenant application (a row
   in `bookings` with status = 'pending') across ALL of this
   host's listings in one place, with Accept / Reject actions
   that call the same accept-booking.php / reject-booking.php
   endpoints used by the 3-dot menus on those two pages.
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
    "SELECT id, name, email, avatar_path, is_host, created_at FROM users WHERE id = :id LIMIT 1"
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
   Same reasoning as mylistings.php / hostprofile.php.
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change back to becomeahost.php once that file exists
    exit;
}

/* Keep the navbar's account icon in sync too — same reasoning
   as hostprofile.php / mylistings.php. */
$_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

/* -----------------------------------------------------
   HOST DATA (sidebar card)
----------------------------------------------------- */
$host = [
    'name'         => $dbUser['name'],
    'avatar'       => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

$notification_count = 0; // TODO: wire up once a notifications table exists

/* -----------------------------------------------------
   PENDING TENANT APPLICATIONS
   Every booking sitting at status = 'pending' on any listing
   this host owns — the same rows the 3-dot menus on
   mylistings.php / hostprofile.php can accept or reject,
   just gathered into one dedicated queue here.

   b.amount_paid + b.paid_at are pulled here too (same columns
   process-payment.php / listingpayment.php / booking-details.php
   already use for the "Amount Paid" / "Balance Due" figures):
     - amount_paid vs total is what actually determines whether
       a booking is fully paid — NOT whether paid_at is set.
       paid_at only marks the moment the reservation fee cleared
       (see process-payment.php's EXPIRY NOTE), so a booking can
       have paid_at set and STILL owe a balance, e.g. the ₱1,000
       reservation fee paid against a ₱3,000 total.
     - paid_at is still useful as "the exact time a payment was
       sent" for display, since a balance payment doesn't update
       it (also per process-payment.php) — it's the timestamp of
       the tenant's first/only payment either way.

   ALIGNMENT FIX: amount_paid is now pulled through
   COALESCE(b.amount_paid, 0) so a NULL in that column (e.g. an
   older row inserted before amount_paid existed, or any write
   path that leaves it unset) reads as 0 here — the same
   "unpaid until proven otherwise" assumption booking-details.php
   makes via `(float) ($booking['amount_paid'] ?? 0)`. Without
   this, a NULL amount_paid could silently produce a different
   payment badge here than on booking-details.php for the exact
   same row.
----------------------------------------------------- */
$pendingStmt = $pdo->prepare(
    "SELECT b.id AS booking_id, b.total, COALESCE(b.amount_paid, 0) AS amount_paid,
            b.booked_at, b.paid_at,
            b.checkin_date, b.checkout_date, b.guests,
            l.id AS listing_id, l.title AS listing_title, l.location AS listing_location, l.price,
            p.photo_path AS cover_photo,
            u.id AS tenant_id, u.name AS tenant_name, u.email AS tenant_email,
            u.avatar_path AS tenant_avatar
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE l.user_id = :id AND b.status = 'pending'
     ORDER BY b.booked_at DESC"
);
$pendingStmt->execute(['id' => $_SESSION['user_id']]);
$pendingApplications = $pendingStmt->fetchAll();

$pending_tenants_count = count($pendingApplications);

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------
   PHOTO PATH FIX
   Uploaded photo_path / avatar_path values for OTHER users'
   photos (listing cover + tenant avatar here) are saved
   relative to /webprogg — e.g. "uploads/listings/abc.jpg".
   Printed as-is, the browser resolves that against the
   CURRENT page's folder instead of the site root, which is
   why these break on a nested page like /booking/, even
   though the logged-in host's OWN avatar (already stored as
   a full "/webprogg/..." path by uploadavatar.php) works
   fine. This forces every photo path back to an absolute,
   site-root path so it loads correctly from any page.

   ALIGNMENT FIX: also strips a leading "webprogg/" if the
   stored value already includes it (matches the same guard
   booking-details.php added), so a path like
   "webprogg/uploads/listings/abc.jpg" doesn't turn into
   "/webprogg/webprogg/uploads/listings/abc.jpg" here while
   rendering correctly there.
----------------------------------------------------- */
function resolve_photo($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    if (preg_match('#^(https?://|/)#i', $path)) {
        return $path; // already absolute — leave it alone
    }
    $normalized = ltrim($path, '/');
    if (stripos($normalized, 'webprogg/') === 0) {
        $normalized = substr($normalized, strlen('webprogg/'));
    }
    return '/webprogg/' . $normalized;
}

/* -----------------------------------------------------
   DATE LABELS
   `checkout_date` is NULL for a Long Term inquiry (book.php
   clears it when the "Long Term" checkbox was checked), so a
   missing checkout here doesn't mean "no data" — it means the
   tenant asked for an open-ended stay starting on check-in.
   That's a real, meaningful state, so it gets its own label
   instead of being lumped in with "Not specified".
----------------------------------------------------- */

/* Single check-in date, e.g. "September 11, 2026". */
function pt_checkin_label($checkin) {
    return !empty($checkin) ? date('F j, Y', strtotime($checkin)) : 'Not specified';
}

/* Single check-out date — "Long Term" when there's a check-in
   but no check-out, "Not specified" when there's neither. */
function pt_checkout_label($checkin, $checkout) {
    if (!empty($checkout)) {
        return date('F j, Y', strtotime($checkout));
    }
    return !empty($checkin) ? 'Long Term' : 'Not specified';
}

/* Combined "Check-in - Check-out" range for the card's date
   line, e.g.:
     "September 11, 2026 - September 13, 2026"   (normal stay)
     "September 11, 2026 - Long Term"             (long term)
     "Not specified"                              (neither set) */
function pt_date_range_label($checkin, $checkout) {
    if (empty($checkin)) {
        return 'Not specified';
    }
    return date('F j, Y', strtotime($checkin)) . ' - ' . pt_checkout_label($checkin, $checkout);
}

/* -----------------------------------------------------
   PAYMENT FIGURES + LABELS
   ALIGNMENT FIX: this used to be three separate small
   functions (pt_payment_remaining / pt_payment_status_label /
   pt_payment_status_class) that each independently recomputed
   `total - amount_paid`. They agreed with booking-details.php
   mathematically, but keeping three separate call sites for
   the same subtraction is exactly how these two pages could
   drift apart the next time only one of them gets edited.

   This is now ONE function, pt_payment_breakdown(), that
   mirrors booking-details.php's own variable names and
   rounding line-for-line:

       $totalAmount = (float) $booking['total'];
       $amountPaid  = (float) ($booking['amount_paid'] ?? 0);
       $balanceDue  = max(0, round($totalAmount - $amountPaid, 2));

   and returns everything the card/modal need (remaining
   amount, status label, badge class, "is it actually fully
   paid" flag) computed from that single balance figure — so
   there's exactly one place doing this math for this page, and
   it's the same math booking-details.php does.
----------------------------------------------------- */
function pt_payment_breakdown($total, $amountPaid) {
    $totalAmount = (float) $total;
    $amountPaid  = (float) ($amountPaid ?? 0);
    $balanceDue  = max(0, round($totalAmount - $amountPaid, 2));
    $isFullyPaid = $balanceDue <= 0.005;

    return [
        'balance_due'   => $balanceDue,
        'is_fully_paid' => $isFullyPaid,
        'status_label'  => $isFullyPaid ? 'Fully Paid' : ('₱' . number_format($balanceDue, 2) . ' Pending'),
        'status_class'  => $isFullyPaid ? 'pt-payment-paid' : 'pt-payment-pending',
    ];
}

/* Exact date + time the tenant's payment was sent (their first
   payment — a later balance payment doesn't move paid_at, see
   process-payment.php), e.g. "Sep 11, 2026, 2:59 PM". */
function pt_payment_time_label($paidAt) {
    return !empty($paidAt) ? date('M j, Y, g:i A', strtotime($paidAt)) : 'Not paid yet';
}

/* Cache-buster for the stylesheet, same pattern as mylistings.php. */
$hp_css_version = '3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Tenants — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=<?php echo h($hp_css_version); ?>">
</head>
<body>

<!-- =========================================================
     NAVBAR (identical markup/classes to mylistings.php / hostprofile.php)
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
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account">
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

  <!-- SIDEBAR (identical to mylistings.php / hostprofile.php, "Pending Tenants" active) -->
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
    <a href="/webprogg/booking/pendingtenants.php" class="hp-side-link hp-side-link-badged active">
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

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">Pending Tenants</h1>
        <p class="hp-page-subtitle">Tenants who applied for your spaces sit here until you accept or reject them. A listing stays hidden from search while an application on it is pending.</p>
      </div>
    </div>

    <div class="pt-list">
      <?php if (empty($pendingApplications)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No pending applications</p>
          <p>Once a tenant applies for one of your spaces, they'll show up here for you to accept or reject.</p>
        </div>

      <?php else: foreach ($pendingApplications as $app):
        $dateRangeLabel = pt_date_range_label($app['checkin_date'], $app['checkout_date']);
        $payment        = pt_payment_breakdown($app['total'], $app['amount_paid']);
        $paymentStatus    = $payment['status_label'];
        $paymentStatusCls = $payment['status_class'];
        $paymentTime      = pt_payment_time_label($app['paid_at']);
      ?>

        <div
            class="pt-card"
            data-booking-id="<?php echo h($app['booking_id']); ?>"
            data-listing-title="<?php echo h($app['listing_title']); ?>"
            data-listing-location="<?php echo h($app['listing_location']); ?>"
            data-listing-photo="<?php echo h(resolve_photo($app['cover_photo'], '/webprogg/images/listing-placeholder.jpg')); ?>"
            data-tenant-name="<?php echo h($app['tenant_name']); ?>"
            data-tenant-email="<?php echo h($app['tenant_email']); ?>"
            data-tenant-avatar="<?php echo h(resolve_photo($app['tenant_avatar'], '/webprogg/images/default-avatar.png')); ?>"
            data-checkin="<?php echo h(pt_checkin_label($app['checkin_date'])); ?>"
            data-checkout="<?php echo h(pt_checkout_label($app['checkin_date'], $app['checkout_date'])); ?>"
            data-daterange="<?php echo h($dateRangeLabel); ?>"
            data-payment-status="<?php echo h($paymentStatus); ?>"
            data-payment-class="<?php echo h($paymentStatusCls); ?>"
            data-payment-time="<?php echo h($paymentTime); ?>"
            data-guests="<?php echo h($app['guests'] !== null && $app['guests'] !== '' ? $app['guests'] : 'Not specified'); ?>"
            data-applied="<?php echo h(date('M j, Y', strtotime($app['booked_at']))); ?>"
            data-total="<?php echo h(number_format((float) $app['total'], 2)); ?>"
            tabindex="0"
            role="button"
            aria-label="View application details from <?php echo h($app['tenant_name']); ?>"
        >

          <img
            class="pt-listing-photo"
            src="<?php echo h(resolve_photo($app['cover_photo'], '/webprogg/images/listing-placeholder.jpg')); ?>"
            alt="<?php echo h($app['listing_title']); ?>"
          >

          <div class="pt-info">
            <p class="pt-listing-title"><?php echo h($app['listing_title']); ?></p>
            <p class="pt-listing-location">
              <img src="/webprogg/images/locationicon-userprofile.png" alt="">
              <?php echo h($app['listing_location']); ?>
            </p>

            <div class="pt-tenant-row">
              <img
                class="pt-tenant-avatar"
                src="<?php echo h(resolve_photo($app['tenant_avatar'], '/webprogg/images/default-avatar.png')); ?>"
                alt="<?php echo h($app['tenant_name']); ?>"
              >
              <div>
                <p class="pt-tenant-name"><?php echo h($app['tenant_name']); ?></p>
                <p class="pt-tenant-email"><?php echo h($app['tenant_email']); ?></p>
              </div>
            </div>

            <p class="pt-applied-date">
              Applied <?php echo h(date('M j, Y', strtotime($app['booked_at']))); ?>
            </p>
          </div>

          <!-- =========================================
               PAYMENT — sits to the LEFT of the stay dates.
               Top: status badge — "Fully Paid" once
               amount_paid covers total, otherwise the exact
               amount still owed (e.g. "\u{20B1}2,000.00 Pending"),
               same figures booking-details.php's Balance Due
               already uses (same pt_payment_breakdown() /
               $balanceDue math on both pages now).
               Bottom: the exact date + time the tenant's
               payment was sent (or "Not paid yet" if nothing's
               been paid at all).
          ========================================== -->
          <div class="pt-payment">
            <span class="pt-payment-status <?php echo h($paymentStatusCls); ?>">
              <?php echo h($paymentStatus); ?>
            </span>
            <span class="pt-payment-time"><?php echo h($paymentTime); ?></span>
          </div>

          <!-- =========================================
               STAY DATES — sits between the payment column
               and the price/actions. Shows
               "Check-in - Check-out", or "Check-in - Long Term"
               when the tenant applied without a checkout date
               (book.php's Long Term option).
          ========================================== -->
          <div class="pt-dates">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            <span><?php echo h($dateRangeLabel); ?></span>
          </div>

          <div class="pt-actions">
            <strong class="pt-total">&#8369; <?php echo h(number_format((float) $app['total'], 2)); ?></strong>
            <div class="pt-buttons">
              <button
                type="button"
                class="hp-btn-outline pt-btn-reject"
                data-booking-id="<?php echo h($app['booking_id']); ?>"
                data-tenant-name="<?php echo h($app['tenant_name']); ?>"
              >
                Reject
              </button>
              <button
                type="button"
                class="hp-btn-primary pt-btn-accept"
                data-booking-id="<?php echo h($app['booking_id']); ?>"
                data-tenant-name="<?php echo h($app['tenant_name']); ?>"
              >
                Accept
              </button>
            </div>
          </div>

        </div>

      <?php endforeach; endif; ?>
    </div>

    <!-- =========================================================
         APPLICATION DETAILS MODAL
         Filled in by JS from the clicked .pt-card's data-* attrs.
    ========================================================= -->
    <div class="pt-modal-overlay" id="ptModalOverlay">
      <div class="pt-modal" role="dialog" aria-modal="true" aria-labelledby="ptModalTitle">

        <button type="button" class="pt-modal-close" id="ptModalClose" aria-label="Close">&times;</button>

        <div class="pt-modal-listing">
          <img class="pt-modal-listing-photo" id="ptModalListingPhoto" src="" alt="">
          <div>
            <p class="pt-modal-listing-title" id="ptModalListingTitle"></p>
            <p class="pt-modal-listing-location">
              <img src="/webprogg/images/locationicon-userprofile.png" alt="">
              <span id="ptModalListingLocation"></span>
            </p>
          </div>
        </div>

        <div class="pt-modal-tenant">
          <img class="pt-modal-tenant-avatar" id="ptModalTenantAvatar" src="" alt="">
          <div>
            <p class="pt-modal-tenant-name" id="ptModalTenantName"></p>
            <p class="pt-modal-tenant-email" id="ptModalTenantEmail"></p>
          </div>
        </div>

        <h3 class="pt-modal-heading" id="ptModalTitle">Application Details</h3>

        <!-- Payment status + exact time, same info as the card. -->
        <div class="pt-modal-payment">
          <span class="pt-modal-payment-status" id="ptModalPaymentStatus"></span>
          <span class="pt-modal-payment-time" id="ptModalPaymentTime"></span>
        </div>

        <!-- Combined stay-dates line, same format as the card. -->
        <div class="pt-modal-daterange">
          <span class="pt-modal-muted">Dates</span>
          <strong id="ptModalDateRange"></strong>
        </div>

        <div class="pt-modal-grid">
          <div class="pt-modal-detail">
            <span class="pt-modal-muted">Check-in</span>
            <strong id="ptModalCheckin"></strong>
          </div>
          <div class="pt-modal-detail">
            <span class="pt-modal-muted">Check-out</span>
            <strong id="ptModalCheckout"></strong>
          </div>
          <div class="pt-modal-detail">
            <span class="pt-modal-muted">Guests</span>
            <strong id="ptModalGuests"></strong>
          </div>
          <div class="pt-modal-detail">
            <span class="pt-modal-muted">Applied On</span>
            <strong id="ptModalApplied"></strong>
          </div>
        </div>

        <div class="pt-modal-total-row">
          <span class="pt-modal-muted">Total</span>
          <strong>&#8369; <span id="ptModalTotal"></span></strong>
        </div>

        <div class="pt-buttons pt-modal-buttons">
          <button type="button" class="hp-btn-outline pt-btn-reject" id="ptModalReject">
            Reject
          </button>
          <button type="button" class="hp-btn-primary pt-btn-accept" id="ptModalAccept">
            Accept
          </button>
        </div>

      </div>
    </div>

  </div>
</main>

<!-- =========================================================
     FOOTER (identical to mylistings.php / hostprofile.php)
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

<!-- =========================================================
     PENDING TENANTS — STYLES + ACCEPT/REJECT
========================================================= -->
<style>
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

    .pt-list { display: flex; flex-direction: column; gap: 14px; }

    .pt-card {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 14px;
        padding: 14px;
        flex-wrap: wrap;
        cursor: pointer;
    }
    .pt-card:hover { border-color: #E0A020; }
    .pt-card:focus-visible { outline: 2px solid #E0A020; outline-offset: 2px; }

    .pt-listing-photo {
        width: 88px;
        height: 88px;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .pt-info { flex: 1; min-width: 200px; }

    .pt-listing-title { margin: 0; font-weight: 700; color: #14142B; }

    .pt-listing-location {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 2px 0 8px;
        font-size: 12px;
        color: #777777;
    }

    .pt-listing-location img { width: 12px; height: 12px; }

    .pt-tenant-row { display: flex; align-items: center; gap: 8px; }

    .pt-tenant-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .pt-tenant-name { margin: 0; font-size: 13px; font-weight: 600; color: #14142B; }
    .pt-tenant-email { margin: 0; font-size: 12px; color: #777777; }

    .pt-applied-date { margin: 8px 0 0; font-size: 11px; color: #999999; }

    /* PAYMENT — sits to the left of the stay dates on the card */
    .pt-payment {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        flex-basis: 100%;
        max-width: 170px;
        padding: 8px 12px;
        text-align: center;
    }

    .pt-payment-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .pt-payment-paid {
        background: #E6F6EC;
        color: #1E7A3D;
        border: 1px solid #2ECC71;
    }
    .pt-payment-pending {
        background: #FFF4E0;
        color: #8A5A10;
        border: 1px solid #F7941D;
    }

    .pt-payment-time {
        font-size: 11px;
        color: #777777;
    }

    @media (min-width: 720px) {
        .pt-payment { flex-basis: auto; }
    }

    /* STAY DATES — sits between pt-payment and pt-actions */
    .pt-dates {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-basis: 100%;
        max-width: 220px;
        padding: 8px 12px;
        border-left: 1px solid #EEF1F6;
        border-right: 1px solid #EEF1F6;
        font-size: 12px;
        font-weight: 600;
        color: #14142B;
        text-align: center;
        justify-content: center;
    }
    .pt-dates img { width: 14px; height: 14px; flex-shrink: 0; }

    @media (min-width: 720px) {
        .pt-dates { flex-basis: auto; }
    }

    .pt-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .pt-total { color: #14142B; }

    .pt-buttons { display: flex; gap: 8px; }

    .pt-btn-accept { background: #1E7A3D; border-color: #1E7A3D; color: #fff; }
    .pt-btn-accept:hover { background: #17612F; }

    .pt-btn-reject { color: #E14B4B; border-color: #E14B4B; }
    .pt-btn-reject:hover { background: #FCEAEA; }

    /* APPLICATION DETAILS MODAL */

    .pt-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(20, 20, 43, 0.45);
        align-items: center;
        justify-content: center;
        padding: 20px;
        z-index: 1000;
    }
    .pt-modal-overlay.open { display: flex; }

    .pt-modal {
        position: relative;
        background: #fff;
        border-radius: 14px;
        padding: 24px;
        width: 100%;
        max-width: 420px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .pt-modal-close {
        position: absolute;
        top: 12px;
        right: 14px;
        background: none;
        border: none;
        font-size: 22px;
        line-height: 1;
        color: #777777;
        cursor: pointer;
    }
    .pt-modal-close:hover { color: #14142B; }

    .pt-modal-listing {
        display: flex;
        gap: 12px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid #EEF1F6;
    }
    .pt-modal-listing-photo { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
    .pt-modal-listing-title { margin: 0; font-weight: 700; color: #14142B; }
    .pt-modal-listing-location {
        display: flex; align-items: center; gap: 4px;
        margin: 4px 0 0; font-size: 12px; color: #777777;
    }
    .pt-modal-listing-location img { width: 12px; height: 12px; }

    .pt-modal-tenant {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid #EEF1F6;
    }
    .pt-modal-tenant-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
    .pt-modal-tenant-name { margin: 0; font-size: 14px; font-weight: 600; color: #14142B; }
    .pt-modal-tenant-email { margin: 0; font-size: 12px; color: #777777; }

    .pt-modal-heading { margin: 0 0 12px; font-size: 15px; color: #14142B; }

    .pt-modal-payment {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        margin-bottom: 10px;
        background: #FAFAFD;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
    }
    .pt-modal-payment-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .pt-modal-payment-time {
        font-size: 12px;
        color: #777777;
    }

    .pt-modal-daterange {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        margin-bottom: 14px;
        background: #FAFAFD;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
    }

    .pt-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-bottom: 14px;
    }
    .pt-modal-detail { display: flex; flex-direction: column; gap: 2px; }
    .pt-modal-muted { font-size: 12px; color: #777777; }

    .pt-modal-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 14px;
        border-top: 1px solid #EEF1F6;
        margin-bottom: 16px;
    }

    .pt-modal-buttons { justify-content: flex-end; }
</style>
<script>
(function () {

    function handleDecision(bookingId, tenantName, buttons, card, endpoint, confirmMessage, busyText, onSuccess) {
        if (!confirm(confirmMessage.replace('%s', tenantName || 'this tenant'))) {
            return;
        }

        buttons.forEach(function (b) { b.disabled = true; });
        buttons.forEach(function (b) {
            if (b.classList.contains('pt-btn-accept') || b.classList.contains('pt-btn-reject')) {
                b.textContent = busyText;
            }
        });

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + encodeURIComponent(bookingId)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    if (card) card.remove();
                    if (onSuccess) onSuccess();
                } else {
                    alert(data.message || 'Could not update this application.');
                    buttons.forEach(function (b) { b.disabled = false; });
                }
            })
            .catch(function () {
                alert('Something went wrong. Please try again.');
                buttons.forEach(function (b) { b.disabled = false; });
            });
    }

    /* ACCEPT / REJECT BUTTONS ON EACH CARD
       stopPropagation so clicking these doesn't also trigger the
       card's own click handler and pop the modal open underneath. */

    document.querySelectorAll('.pt-card').forEach(function (card) {
        var acceptBtn = card.querySelector('.pt-btn-accept');
        var rejectBtn = card.querySelector('.pt-btn-reject');
        var buttons = card.querySelectorAll('button');
        var bookingId = card.getAttribute('data-booking-id');
        var tenantName = card.getAttribute('data-tenant-name');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                handleDecision(bookingId, tenantName, buttons, card, '/webprogg/booking/accept-booking.php',
                    'Accept %s\'s application for this listing?', 'Accepting...');
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                handleDecision(bookingId, tenantName, buttons, card, '/webprogg/booking/reject-booking.php',
                    'Reject %s\'s application for this listing?', 'Rejecting...');
            });
        }
    });

    /* APPLICATION DETAILS MODAL
       Click anywhere on a card (but not its buttons) to see what
       the tenant entered on listing-detail.php — check-in,
       check-out, and guests. */

    var overlay = document.getElementById('ptModalOverlay');
    var closeBtn = document.getElementById('ptModalClose');
    var modalAcceptBtn = document.getElementById('ptModalAccept');
    var modalRejectBtn = document.getElementById('ptModalReject');
    var activeCard = null;

    function openModalForCard(card) {
        activeCard = card;

        document.getElementById('ptModalListingPhoto').src = card.getAttribute('data-listing-photo');
        document.getElementById('ptModalListingTitle').textContent = card.getAttribute('data-listing-title');
        document.getElementById('ptModalListingLocation').textContent = card.getAttribute('data-listing-location');
        document.getElementById('ptModalTenantAvatar').src = card.getAttribute('data-tenant-avatar');
        document.getElementById('ptModalTenantName').textContent = card.getAttribute('data-tenant-name');
        document.getElementById('ptModalTenantEmail').textContent = card.getAttribute('data-tenant-email');

        var paymentStatusEl = document.getElementById('ptModalPaymentStatus');
        paymentStatusEl.textContent = card.getAttribute('data-payment-status');
        paymentStatusEl.className = 'pt-modal-payment-status ' + card.getAttribute('data-payment-class');
        document.getElementById('ptModalPaymentTime').textContent = card.getAttribute('data-payment-time');

        document.getElementById('ptModalDateRange').textContent = card.getAttribute('data-daterange');
        document.getElementById('ptModalCheckin').textContent = card.getAttribute('data-checkin');
        document.getElementById('ptModalCheckout').textContent = card.getAttribute('data-checkout');
        document.getElementById('ptModalGuests').textContent = card.getAttribute('data-guests');
        document.getElementById('ptModalApplied').textContent = card.getAttribute('data-applied');
        document.getElementById('ptModalTotal').textContent = card.getAttribute('data-total');

        overlay.classList.add('open');
    }

    function closeModal() {
        overlay.classList.remove('open');
        activeCard = null;
    }

    document.querySelectorAll('.pt-card').forEach(function (card) {
        card.addEventListener('click', function () {
            openModalForCard(card);
        });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openModalForCard(card);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (overlay) {
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.classList.contains('open')) {
            closeModal();
        }
    });

    if (modalAcceptBtn) {
        modalAcceptBtn.addEventListener('click', function () {
            if (!activeCard) return;
            var bookingId = activeCard.getAttribute('data-booking-id');
            var tenantName = activeCard.getAttribute('data-tenant-name');
            var card = activeCard;
            handleDecision(bookingId, tenantName, [modalAcceptBtn, modalRejectBtn], card, '/webprogg/booking/accept-booking.php',
                'Accept %s\'s application for this listing?', 'Accepting...', closeModal);
        });
    }

    if (modalRejectBtn) {
        modalRejectBtn.addEventListener('click', function () {
            if (!activeCard) return;
            var bookingId = activeCard.getAttribute('data-booking-id');
            var tenantName = activeCard.getAttribute('data-tenant-name');
            var card = activeCard;
            handleDecision(bookingId, tenantName, [modalAcceptBtn, modalRejectBtn], card, '/webprogg/booking/reject-booking.php',
                'Reject %s\'s application for this listing?', 'Rejecting...', closeModal);
        });
    }

})();
</script>

</body>
</html>