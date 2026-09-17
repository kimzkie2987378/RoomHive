<?php
/* =========================================================
   ROOMHIVE — PENDING TENANTS
   pendingtenants.php

   RECEIPT ACCESS (new): each application shows the real
   payment state — "Fully Paid" (green) / "Advance Paid ·
   ₱X left" (amber) / "₱X Pending" — plus a "View Receipt"
   button (card + modal) that opens the guest's official
   receipt (payment-confirmation.php) in a new tab. The
   receipt page allows the listing's host to view it.
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
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

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

 $notification_count = 0;

/* -----------------------------------------------------
   PENDING TENANT APPLICATIONS
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
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/* -----------------------------------------------------
   PHOTO PATH FIX
----------------------------------------------------- */
if (!function_exists('resolve_photo')) {
    function resolve_photo($path, $fallback) {
        if (empty($path)) {
            return $fallback;
        }
        if (preg_match('#^(https?://|/)#i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        if (stripos($normalized, 'webprogg/') === 0) {
            $normalized = substr($normalized, strlen('webprogg/'));
        }
        return '/webprogg/' . $normalized;
    }
}

/* -----------------------------------------------------
   DATE LABELS
----------------------------------------------------- */
function pt_checkin_label($checkin) {
    return !empty($checkin) ? date('F j, Y', strtotime($checkin)) : 'Not specified';
}

function pt_checkout_label($checkin, $checkout) {
    if (!empty($checkout)) {
        return date('F j, Y', strtotime($checkout));
    }
    return !empty($checkin) ? 'Long Term' : 'Not specified';
}

function pt_date_range_label($checkin, $checkout) {
    if (empty($checkin)) {
        return 'Not specified';
    }
    return date('F j, Y', strtotime($checkin)) . ' - ' . pt_checkout_label($checkin, $checkout);
}

/* -----------------------------------------------------
   PAYMENT FIGURES + LABELS
   CHANGED: now distinguishes FULL / ADVANCE / NONE so the
   host sees the advance payment and can open the receipt.
----------------------------------------------------- */
function pt_payment_breakdown($total, $amountPaid) {
    $totalAmount = (float) $total;
    $amountPaid  = (float) ($amountPaid ?? 0);
    $balanceDue  = max(0, round($totalAmount - $amountPaid, 2));
    $hasPayment  = ($amountPaid > 0.005);
    $isFullyPaid = ($hasPayment && $balanceDue <= 0.005);

    if ($isFullyPaid) {
        $state = 'full';
        $status_label = 'Fully Paid';
        $status_class = 'pt-payment-paid';
    } elseif ($hasPayment) {
        $state = 'advance';
        $status_label = 'Advance Paid &#8369;' . number_format($balanceDue, 2) . ' left';
        $status_label = 'Advance Paid · ₱' . number_format($balanceDue, 2) . ' left';
        $status_class = 'pt-payment-pending';
    } else {
        $state = 'none';
        $status_label = '₱' . number_format($balanceDue, 2) . ' Pending';
        $status_class = 'pt-payment-pending';
    }

    return [
        'balance_due'     => $balanceDue,
        'is_fully_paid'   => $isFullyPaid,
        'payment_state'   => $state,
        'amount_paid_fmt' => number_format($amountPaid, 2),
        'status_label'    => $status_label,
        'status_class'    => $status_class,
    ];
}

function pt_payment_time_label($paidAt) {
    return !empty($paidAt) ? date('M j, Y, g:i A', strtotime($paidAt)) : 'Not paid yet';
}

 $hp_css_version = '5';
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
     NAVBAR — canonical host design
========================================================= -->
<header class="navbar">

    <a href="/webprogg/host/hostprofile.php" class="logo">
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

        <!-- MY ACCOUNT DROPDOWN (host-dd) -->
        <div class="host-dd">

            <button
                type="button"
                class="my-account host-dd-toggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <span class="account-circle">
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account">
                </span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="host-dd-menu">
                <a href="/webprogg/host/hostprofile.php">Host Dashboard</a>
                <a href="/webprogg/host/hosteditprofile.php">Profile Settings</a>
                <a href="/webprogg/host/hostmessages.php">Messages</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <!-- SIDEBAR -->
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
    <a href="/webprogg/host/hostearnings.php" class="hp-side-link">
      <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
      Earnings
    </a>
    <a href="/webprogg/host/hostpayouts.php" class="hp-side-link">
      <img src="/webprogg/images/paymentsicon-userprofile.png" alt="">
      Payouts
    </a>
    <a href="/webprogg/host/hostreviews.php" class="hp-side-link">
      <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="/webprogg/host/hostmessages.php" class="hp-side-link">
      <img src="/webprogg/images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="/webprogg/host/hosteditprofile.php" class="hp-side-link">
      <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="/webprogg/host/hostverification.php" class="hp-side-link">
      <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
      Verification
    </a>
    <a href="/webprogg/host/hostpayoutmethods.php" class="hp-side-link">
      <img src="/webprogg/images/payoutmethodsicon-hostprofile.png" alt="">
      Payout Methods
    </a>
    <a href="/webprogg/host/hostnotificationsettings.php" class="hp-side-link">
      <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="/webprogg/host/hostsecurity.php" class="hp-side-link">
      <img src="/webprogg/images/lockicon-userprofile.png" alt="">
      Security
    </a>
    <a href="/webprogg/host/helpcenter.php" class="hp-side-link">
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

        /* Receipt URL — only when the guest has actually paid something */
        $receiptUrl = $payment['payment_state'] !== 'none'
            ? '/webprogg/booking/payment-confirmation.php?id=' . (int) $app['booking_id']
            : '';
      ?>

        <div
            class="pt-card"
            data-booking-id="<?php echo h($app['booking_id']); ?>"
            data-listing-title="<?php echo h($app['listing_title']); ?>"
            data-listing-location="<?php echo h($app['listing_location']); ?>"
            data-listing-photo="<?php echo h(resolve_photo($app['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>"
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
            data-paid="<?php echo h($payment['amount_paid_fmt']); ?>"
            data-balance="<?php echo h(number_format($payment['balance_due'], 2)); ?>"
            data-receipt-url="<?php echo h($receiptUrl); ?>"
            tabindex="0"
            role="button"
            aria-label="View application details from <?php echo h($app['tenant_name']); ?>"
        >

          <img
            class="pt-listing-photo"
            src="<?php echo h(resolve_photo($app['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>"
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

          <!-- PAYMENT — sits to the LEFT of the stay dates -->
          <div class="pt-payment">
            <span class="pt-payment-status <?php echo h($paymentStatusCls); ?>">
              <?php echo h($paymentStatus); ?>
            </span>
            <span class="pt-payment-time"><?php echo h($paymentTime); ?></span>
            <?php if ($receiptUrl !== ''): ?>
            <a
              class="pt-receipt-link"
              href="<?php echo h($receiptUrl); ?>"
              target="_blank"
              rel="noopener"
              title="Open the guest's official receipt"
            >&#128196; View Receipt</a>
            <?php endif; ?>
          </div>

          <!-- STAY DATES — between payment and price/actions -->
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

        <div class="pt-modal-payment">
          <span class="pt-modal-payment-status" id="ptModalPaymentStatus"></span>
          <span class="pt-modal-payment-time" id="ptModalPaymentTime"></span>
        </div>

        <!-- NEW: open the guest's official receipt (new tab) -->
        <a class="pt-modal-receipt-link" id="ptModalReceiptLink" href="#" target="_blank" rel="noopener" style="display:none;">
          &#128196; View Guest Receipt
        </a>

        <div class="pt-modal-paid-row">
          <div>
            <span class="pt-modal-muted">Paid so far</span>
            <strong>&#8369; <span id="ptModalPaid"></span></strong>
          </div>
          <div>
            <span class="pt-modal-muted">Balance</span>
            <strong>&#8369; <span id="ptModalBalance"></span></strong>
          </div>
        </div>

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
     FOOTER
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
     PENDING TENANTS — STYLES (hive-polished)
========================================================= -->
<style>
    /* =====================================================
       HOST DROPDOWN — embedded so it always applies here
    ====================================================== */

    .host-dd {
        position: relative;
    }

    .host-dd .my-account {
        display: flex;
        align-items: center;
        gap: 8px;
        background: none;
        border: none;
        cursor: pointer;
        font: inherit;
        color: inherit;
    }

    .host-dd .account-circle img {
        width: 42px;
        height: 42px;
        padding: 5px;
        background: #1c2a38;
        border-radius: 50%;
        object-fit: contain;
        display: block;
    }

    .host-dd .my-account span:not(.account-circle) {
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        color: #1c2a38;
    }

    .host-dd .dropdown-caret {
        font-size: 0.75em;
        transition: transform 0.15s ease;
    }

    .host-dd.open .dropdown-caret { transform: rotate(180deg); }

    .host-dd-menu {
        display: none !important;

        position: absolute;
        top: calc(100% + 10px);
        right: 0;

        min-width: 190px;
        padding: 6px;

        background: #ffffff;

        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 12px;

        box-shadow: 0 14px 30px rgba(28, 42, 56, 0.14);

        flex-direction: column;

        z-index: 1100;
    }

    .host-dd.open .host-dd-menu {
        display: flex !important;

        animation: hostDDIn 0.2s ease;
    }

    @keyframes hostDDIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .host-dd-menu a {
        display: block;
        padding: 10px 12px;
        border-radius: 8px;
        color: #1c2a38;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.15s ease;
    }

    .host-dd-menu a:hover {
        background: #fdf1dc;
        color: #b07708;
    }

    /* =====================================================
       SIDEBAR BADGE
    ====================================================== */

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

    /* =====================================================
       APPLICATION CARDS
    ====================================================== */

    .pt-list { display: flex; flex-direction: column; gap: 14px; }

    .pt-card {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 16px;
        padding: 16px;
        flex-wrap: wrap;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(28, 42, 56, 0.06);
        transition:
            transform 0.25s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow 0.25s ease,
            border-color 0.25s ease;
    }
    .pt-card:hover {
        transform: translateY(-3px);
        border-color: rgba(237, 164, 35, 0.45);
        box-shadow: 0 18px 34px rgba(237, 164, 35, 0.16);
    }
    .pt-card:focus-visible { outline: 2px solid #eda423; outline-offset: 2px; }

    .pt-listing-photo {
        width: 88px;
        height: 88px;
        object-fit: cover;
        border-radius: 12px;
        flex-shrink: 0;
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .pt-card:hover .pt-listing-photo { transform: scale(1.04); }

    .pt-info { flex: 1; min-width: 200px; }

    .pt-listing-title { margin: 0; font-weight: 700; color: #1c2a38; }

    .pt-listing-location {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 2px 0 8px;
        font-size: 12px;
        color: #6b7684;
    }

    .pt-listing-location img { width: 12px; height: 12px; }

    .pt-tenant-row { display: flex; align-items: center; gap: 8px; }

    .pt-tenant-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px rgba(237, 164, 35, 0.5);
    }

    .pt-tenant-name { margin: 0; font-size: 13px; font-weight: 600; color: #1c2a38; }
    .pt-tenant-email { margin: 0; font-size: 12px; color: #6b7684; }

    .pt-applied-date { margin: 8px 0 0; font-size: 11px; color: #999999; }

    /* PAYMENT column */
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
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .pt-payment-paid {
        background: #E8F8F1;
        color: #1FA971;
        border: 1px solid #B9E3C5;
    }
    .pt-payment-pending {
        background: #FDF1DC;
        color: #B07708;
        border: 1px solid rgba(237, 164, 35, 0.5);
    }

    .pt-payment-time { font-size: 11px; color: #6b7684; }

    /* NEW — View Receipt link on the card */
    .pt-receipt-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 3px;
        padding: 4px 11px;
        border-radius: 999px;
        background: #FDF1DC;
        border: 1px solid rgba(237, 164, 35, 0.5);
        color: #B07708;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .pt-receipt-link:hover {
        background: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(237, 164, 35, 0.25);
    }

    @media (min-width: 720px) {
        .pt-payment { flex-basis: auto; }
    }

    /* STAY DATES column */
    .pt-dates {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-basis: 100%;
        max-width: 220px;
        padding: 8px 12px;
        border-left: 1px solid rgba(28, 42, 56, 0.08);
        border-right: 1px solid rgba(28, 42, 56, 0.08);
        font-size: 12px;
        font-weight: 600;
        color: #1c2a38;
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

    .pt-total { color: #1c2a38; font-size: 15px; }

    .pt-buttons { display: flex; gap: 8px; }

    /* Accept = gradient honey; Reject = red outline */
    .pt-btn-accept {
        background: linear-gradient(135deg, #f6b93b, #eda423) !important;
        border: none !important;
        color: #1c2a38 !important;
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
    }
    .pt-btn-accept:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45);
    }

    .pt-btn-reject { color: #E14B4B; border-color: #E14B4B; }
    .pt-btn-reject:hover { background: #FCEAEA; }

    /* ---- APPLICATION DETAILS MODAL ---- */

    .pt-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(20, 20, 43, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
        padding: 20px;
        z-index: 1000;
    }
    .pt-modal-overlay.open { display: flex; }

    .pt-modal {
        position: relative;
        background: #fff;
        border-radius: 18px;
        padding: 24px;
        width: 100%;
        max-width: 440px;
        max-height: 90vh;
        overflow-y: auto;
        animation: ptModalIn 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }

    @keyframes ptModalIn {
        from { opacity: 0; transform: translateY(16px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .pt-modal::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
    }

    .pt-modal-close {
        position: absolute;
        top: 12px;
        right: 14px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        border-radius: 50%;
        font-size: 22px;
        line-height: 1;
        color: #6b7684;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .pt-modal-close:hover { background: #FDF1DC; color: #1c2a38; }

    .pt-modal-listing {
        display: flex;
        gap: 12px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(28, 42, 56, 0.08);
    }
    .pt-modal-listing-photo { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
    .pt-modal-listing-title { margin: 0; font-weight: 700; color: #1c2a38; }
    .pt-modal-listing-location {
        display: flex; align-items: center; gap: 4px;
        margin: 4px 0 0; font-size: 12px; color: #6b7684;
    }
    .pt-modal-listing-location img { width: 12px; height: 12px; }

    .pt-modal-tenant {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(28, 42, 56, 0.08);
    }
    .pt-modal-tenant-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px rgba(237, 164, 35, 0.5);
    }
    .pt-modal-tenant-name { margin: 0; font-size: 14px; font-weight: 600; color: #1c2a38; }
    .pt-modal-tenant-email { margin: 0; font-size: 12px; color: #6b7684; }

    .pt-modal-heading { margin: 0 0 12px; font-size: 15px; color: #1c2a38; }

    .pt-modal-payment {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        margin-bottom: 10px;
        background: #FAFAFD;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 10px;
    }
    .pt-modal-payment-status {
        display: inline-block;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .pt-modal-payment-time { font-size: 12px; color: #6b7684; }

    /* NEW — receipt button + paid/balance row in the modal */
    .pt-modal-receipt-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        padding: 9px 14px;
        border-radius: 10px;
        background: #FDF1DC;
        border: 1px solid rgba(237, 164, 35, 0.5);
        color: #B07708;
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .pt-modal-receipt-link:hover {
        background: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.25);
    }

    .pt-modal-paid-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        margin-bottom: 10px;
        background: #FAFAFD;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 10px;
    }
    .pt-modal-paid-row > div {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .pt-modal-paid-row strong { color: #1c2a38; font-size: 14px; }

    .pt-modal-daterange {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        margin-bottom: 14px;
        background: #FAFAFD;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 10px;
    }

    .pt-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-bottom: 14px;
    }
    .pt-modal-detail { display: flex; flex-direction: column; gap: 2px; }
    .pt-modal-muted { font-size: 12px; color: #6b7684; }

    .pt-modal-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 14px;
        border-top: 1px solid rgba(28, 42, 56, 0.08);
        margin-bottom: 16px;
    }

    .pt-modal-total-row strong { color: #1c2a38; font-size: 17px; }

    .pt-modal-buttons { justify-content: flex-end; }

    @media (max-width: 720px) {
        .pt-actions {
            width: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }
</style>

<!-- =========================================================
     DROPDOWN SCRIPT — self-contained, per-element listeners
========================================================= -->
<script>
(function () {
    "use strict";

    var dd  = document.querySelector(".host-dd");
    var btn = dd ? dd.querySelector(".host-dd-toggle") : null;

    if (!dd || !btn) { return; }

    dd.classList.remove("open");
    btn.setAttribute("aria-expanded", "false");

    btn.addEventListener("click", function (event) {
        event.stopPropagation();

        var isOpen = dd.classList.toggle("open");
        btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    document.addEventListener("click", function (event) {
        if (!dd.contains(event.target)) {
            dd.classList.remove("open");
            btn.setAttribute("aria-expanded", "false");
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            dd.classList.remove("open");
            btn.setAttribute("aria-expanded", "false");
        }
    });
})();
</script>

<!-- =========================================================
     ACCEPT/REJECT + MODAL SCRIPT
========================================================= -->
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

    /* ACCEPT / REJECT BUTTONS ON EACH CARD */

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

    /* VIEW RECEIPT LINKS — open in a new tab, don't open the modal */
    document.querySelectorAll('.pt-receipt-link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    /* APPLICATION DETAILS MODAL */

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

        /* Receipt link — only when the guest has paid something */
        var receiptLink = document.getElementById('ptModalReceiptLink');
        var receiptUrl = card.getAttribute('data-receipt-url') || '';
        if (receiptLink) {
            if (receiptUrl) {
                receiptLink.href = receiptUrl;
                receiptLink.style.display = 'inline-flex';
            } else {
                receiptLink.style.display = 'none';
            }
        }

        document.getElementById('ptModalPaid').textContent = card.getAttribute('data-paid') || '0.00';
        document.getElementById('ptModalBalance').textContent = card.getAttribute('data-balance') || '0.00';

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