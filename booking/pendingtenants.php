<?php
/* =========================================================
   ROOMHIVE — PENDING TENANTS
   pendingtenants.php

   SIGN-TO-ACCEPT: clicking Accept opens a signature pad; the
   host must draw their signature, which is saved by
   sign-receipt.php and stamped onto the guest's official
   receipt. Only then is the application accepted
   (accept-booking.php). Reject is unchanged.

   SIDEBAR FIX: the Earnings link points to
   /webprogg/host/earning.php — the actual filename. The old
   /webprogg/host/hostearnings.php link 404'd.
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

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

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

 $hp_css_version = '6';
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

<main class="hp-dashboard hp-dashboard--flush-top">

 <?php
/* Shared host sidebar. Set $activePage before including:
   overview | listings | pending | bookings | earnings | payouts |
   reviews | messages | editprofile | verification | payoutmethods |
   notificationsettings | security | quithosting | helpcenter
   Requires host_init.php ($host, $pending_tenants_count). */
 $activePage = $activePage ?? '';

function hp_side_active($activePage, $key) {
    return $activePage === $key ? ' active' : '';
}
?>
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
    .hp-sidebar-card .hp-profile-photo {
        margin: 0 auto 12px;
    }
    .hp-sidebar-card .hp-sidebar-avatar {
        width: 64px;
        height: 64px;
    }

    /* NEW — section label + quit hosting styling */
    .hp-side-heading {
        margin: 20px 10px 6px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #9aa5b1;
    }
    .hp-side-quit {
        color: #b3261e;
    }
    .hp-side-quit:hover {
        background: #fdecea;
        color: #b3261e;
    }
</style>

<aside class="hp-sidebar">

    <div class="hp-sidebar-card">
        <div class="hp-profile-photo">
            <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar" id="hostSidebarAvatarImg">
            <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
                <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
            </button>
            <input type="file" id="hostAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>
        <h4><?php echo h($host['name']); ?></h4>
        <span class="hp-host-badge">Host</span>
        <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="/webprogg/host/hostprofile.php" class="hp-side-link<?php echo hp_side_active($activePage, 'overview'); ?>">Overview</a>
    <a href="/webprogg/user/mylistings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'listings'); ?>">My Listings</a>

    <a href="/webprogg/booking/pendingtenants.php" class="hp-side-link hp-side-link-badged<?php echo hp_side_active($activePage, 'pending'); ?>">
        Pending Tenants
        <?php if ($pending_tenants_count > 0): ?>
            <span class="hp-side-badge"><?php echo h($pending_tenants_count); ?></span>
        <?php endif; ?>
    </a>

    <a href="/webprogg/host/hostbookings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'bookings'); ?>">Bookings</a>
    <a href="/webprogg/host/earnings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'earnings'); ?>">Earnings</a>
    <a href="/webprogg/host/payouts.php" class="hp-side-link<?php echo hp_side_active($activePage, 'payouts'); ?>">Payouts</a>
    <a href="/webprogg/host/hostreviews.php" class="hp-side-link<?php echo hp_side_active($activePage, 'reviews'); ?>">Reviews</a>
    <a href="/webprogg/host/hostmessages.php" class="hp-side-link<?php echo hp_side_active($activePage, 'messages'); ?>">Messages</a>

    <!-- SETTINGS GROUP -->
    <span class="hp-side-heading">Settings</span>
    <a href="/webprogg/host/hosteditprofile.php" class="hp-side-link<?php echo hp_side_active($activePage, 'editprofile'); ?>">Profile &amp; Account</a>
    <a href="/webprogg/host/payoutmethods.php" class="hp-side-link<?php echo hp_side_active($activePage, 'payoutmethods'); ?>">Payout Methods</a>
    <a href="/webprogg/host/hostnotificationsettings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'notificationsettings'); ?>">Notification Settings</a>
    <a href="/webprogg/host/hostsecurity.php" class="hp-side-link<?php echo hp_side_active($activePage, 'security'); ?>">Security</a>
    <a href="/webprogg/host/quithosting.php" class="hp-side-link hp-side-quit<?php echo hp_side_active($activePage, 'quithosting'); ?>">Quit Hosting</a>

    <a href="/webprogg/host/helpcenter.php" class="hp-side-link<?php echo hp_side_active($activePage, 'helpcenter'); ?>">Help Center</a>

    <a href="/webprogg/auth/logout.php" class="hp-side-link hp-side-logout">Log Out</a>

</aside>

<script>
/* Sidebar avatar upload — lives here so every host page gets it. */
(function () {
    const photoButton  = document.getElementById('hostPhotoButton');
    const fileInput    = document.getElementById('hostAvatarFileInput');
    const sidebarImg   = document.getElementById('hostSidebarAvatarImg');
    const navAvatarImg = document.getElementById('navAccountAvatarImg');

    if (!photoButton || !fileInput || !sidebarImg) return;

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

        const previewUrl = URL.createObjectURL(file);
        const previousSrc = sidebarImg.src;
        sidebarImg.src = previewUrl;
        if (navAvatarImg) navAvatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/user/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                sidebarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                sidebarImg.src = previousSrc;
                if (navAvatarImg) navAvatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function () {
            sidebarImg.src = previousSrc;
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

  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">Pending Tenants</h1>
        <p class="hp-page-subtitle">Tenants who applied for your spaces sit here until you accept or reject them. Accepting now requires signing the guest's receipt first.</p>
      </div>
    </div>

    <div class="pt-list">
      <?php if (empty($pendingApplications)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No pending applications</p>
          <p>Once a tenant applies for one of your spaces, they'll show up here for you to accept or reject.</p>
        </div>

      <?php else: foreach ($pendingApplications as $app):
        $dateRangeLabel   = pt_date_range_label($app['checkin_date'], $app['checkout_date']);
        $payment          = pt_payment_breakdown($app['total'], $app['amount_paid']);
        $paymentStatus    = $payment['status_label'];
        $paymentStatusCls = $payment['status_class'];
        $paymentTime      = pt_payment_time_label($app['paid_at']);
        $bookingRef       = str_pad((string) $app['booking_id'], 6, '0', STR_PAD_LEFT);

        $receiptUrl = $payment['payment_state'] !== 'none'
            ? '/webprogg/booking/payment-confirmation.php?id=' . (int) $app['booking_id']
            : '';
      ?>

        <div
            class="pt-card"
            data-booking-id="<?php echo h($app['booking_id']); ?>"
            data-booking-ref="<?php echo h($bookingRef); ?>"
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

    <!-- =========================================================
         SIGN-TO-ACCEPT MODAL (signature pad)
    ========================================================= -->
    <div class="pt-modal-overlay" id="ptSignOverlay">
      <div class="pt-modal pt-sign-modal" role="dialog" aria-modal="true" aria-labelledby="ptSignTitle">

        <button type="button" class="pt-modal-close" id="ptSignClose" aria-label="Close">&times;</button>

        <h3 class="pt-modal-heading" id="ptSignTitle">Sign the receipt to accept</h3>
        <p class="pt-sign-sub">
          Your signature will be stamped on the guest's official receipt for
          booking <b id="ptSignBookingRef">#000000</b>. Signing also accepts this application.
        </p>

        <div class="pt-sign-summary">
          <div><span class="pt-modal-muted">Tenant</span><strong id="ptSignTenant"></strong></div>
          <div><span class="pt-modal-muted">Listing</span><strong id="ptSignListing"></strong></div>
          <div><span class="pt-modal-muted">Paid so far</span><strong>&#8369; <span id="ptSignPaid"></span></strong></div>
          <div><span class="pt-modal-muted">Total</span><strong>&#8369; <span id="ptSignTotal"></span></strong></div>
        </div>

        <a class="pt-modal-receipt-link" id="ptSignViewReceipt" href="#" target="_blank" rel="noopener" style="display:none;">
          &#128196; View full receipt
        </a>

        <div class="pt-sign-canvas-wrap">
          <canvas id="ptSignCanvas"></canvas>
          <span class="pt-sign-hint" id="ptSignHint">✍ Draw your signature here</span>
        </div>

        <div class="pt-sign-tools">
          <button type="button" class="hp-btn-outline" id="ptSignClear">Clear</button>
        </div>

        <div class="pt-buttons pt-modal-buttons">
          <button type="button" class="hp-btn-outline" id="ptSignCancel">Cancel</button>
          <button type="button" class="hp-btn-primary" id="ptSignConfirm" disabled>
            Sign &amp; Accept
          </button>
        </div>

      </div>
    </div>

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

<style>
    /* Dropdown */
    .host-dd { position: relative; }
    .host-dd .my-account {
        display: flex; align-items: center; gap: 8px;
        background: none; border: none; cursor: pointer;
        font: inherit; color: inherit;
    }
    .host-dd .account-circle img {
        width: 42px; height: 42px; padding: 5px;
        background: #1c2a38; border-radius: 50%;
        object-fit: contain; display: block;
    }
    .host-dd .my-account span:not(.account-circle) {
        font-size: 12px; font-weight: 700; white-space: nowrap; color: #1c2a38;
    }
    .host-dd .dropdown-caret { font-size: 0.75em; transition: transform 0.15s ease; }
    .host-dd.open .dropdown-caret { transform: rotate(180deg); }

    .host-dd-menu {
        display: none !important;
        position: absolute; top: calc(100% + 10px); right: 0;
        min-width: 190px; padding: 6px;
        background: #ffffff;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 12px;
        box-shadow: 0 14px 30px rgba(28, 42, 56, 0.14);
        flex-direction: column;
        z-index: 1100;
    }
    .host-dd.open .host-dd-menu { display: flex !important; animation: hostDDIn 0.2s ease; }
    @keyframes hostDDIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .host-dd-menu a {
        display: block; padding: 10px 12px; border-radius: 8px;
        color: #1c2a38; font-size: 13px; font-weight: 600;
        text-decoration: none; white-space: nowrap; transition: 0.15s ease;
    }
    .host-dd-menu a:hover { background: #fdf1dc; color: #b07708; }

    .hp-side-link-badged { position: relative; display: flex; align-items: center; gap: 10px; }
    .hp-side-badge {
        margin-left: auto; background: #E14B4B; color: #fff;
        font-size: 11px; font-weight: 700; line-height: 1;
        padding: 3px 7px; border-radius: 999px;
    }

    /* Cards */
    .pt-list { display: flex; flex-direction: column; gap: 14px; }
    .pt-card {
        display: flex; align-items: center; gap: 16px;
        background: #fff;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 16px; padding: 16px; flex-wrap: wrap;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(28, 42, 56, 0.06);
        transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .pt-card:hover {
        transform: translateY(-3px);
        border-color: rgba(237, 164, 35, 0.45);
        box-shadow: 0 18px 34px rgba(237, 164, 35, 0.16);
    }
    .pt-card:focus-visible { outline: 2px solid #eda423; outline-offset: 2px; }

    .pt-listing-photo {
        width: 88px; height: 88px; object-fit: cover; border-radius: 12px; flex-shrink: 0;
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .pt-card:hover .pt-listing-photo { transform: scale(1.04); }

    .pt-info { flex: 1; min-width: 200px; }
    .pt-listing-title { margin: 0; font-weight: 700; color: #1c2a38; }
    .pt-listing-location {
        display: flex; align-items: center; gap: 4px;
        margin: 2px 0 8px; font-size: 12px; color: #6b7684;
    }
    .pt-listing-location img { width: 12px; height: 12px; }

    .pt-tenant-row { display: flex; align-items: center; gap: 8px; }
    .pt-tenant-avatar {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
        border: 2px solid #fff; box-shadow: 0 0 0 2px rgba(237, 164, 35, 0.5);
    }
    .pt-tenant-name { margin: 0; font-size: 13px; font-weight: 600; color: #1c2a38; }
    .pt-tenant-email { margin: 0; font-size: 12px; color: #6b7684; }
    .pt-applied-date { margin: 8px 0 0; font-size: 11px; color: #999999; }

    .pt-payment {
        display: flex; flex-direction: column; align-items: center; gap: 4px;
        flex-basis: 100%; max-width: 170px; padding: 8px 12px; text-align: center;
    }
    .pt-payment-status {
        display: inline-block; padding: 4px 11px; border-radius: 999px;
        font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .pt-payment-paid { background: #E8F8F1; color: #1FA971; border: 1px solid #B9E3C5; }
    .pt-payment-pending { background: #FDF1DC; color: #B07708; border: 1px solid rgba(237, 164, 35, 0.5); }
    .pt-payment-time { font-size: 11px; color: #6b7684; }

    .pt-receipt-link {
        display: inline-flex; align-items: center; gap: 5px;
        margin-top: 3px; padding: 4px 11px; border-radius: 999px;
        background: #FDF1DC; border: 1px solid rgba(237, 164, 35, 0.5);
        color: #B07708; font-size: 11px; font-weight: 700;
        text-decoration: none; white-space: nowrap;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .pt-receipt-link:hover {
        background: #ffffff; transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(237, 164, 35, 0.25);
    }

    @media (min-width: 720px) { .pt-payment { flex-basis: auto; } }

    .pt-dates {
        display: flex; align-items: center; gap: 6px;
        flex-basis: 100%; max-width: 220px; padding: 8px 12px;
        border-left: 1px solid rgba(28, 42, 56, 0.08);
        border-right: 1px solid rgba(28, 42, 56, 0.08);
        font-size: 12px; font-weight: 600; color: #1c2a38;
        text-align: center; justify-content: center;
    }
    .pt-dates img { width: 14px; height: 14px; flex-shrink: 0; }
    @media (min-width: 720px) { .pt-dates { flex-basis: auto; } }

    .pt-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .pt-total { color: #1c2a38; font-size: 15px; }
    .pt-buttons { display: flex; gap: 8px; }

    .pt-btn-accept {
        background: linear-gradient(135deg, #f6b93b, #eda423) !important;
        border: none !important; color: #1c2a38 !important;
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
    }
    .pt-btn-accept:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45);
    }
    .pt-btn-reject { color: #E14B4B; border-color: #E14B4B; }
    .pt-btn-reject:hover { background: #FCEAEA; }

    /* Modal base */
    .pt-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(20, 20, 43, 0.45);
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        align-items: center; justify-content: center;
        padding: 20px; z-index: 1000;
    }
    .pt-modal-overlay.open { display: flex; }

    .pt-modal {
        position: relative; background: #fff; border-radius: 18px;
        padding: 24px; width: 100%; max-width: 440px; max-height: 90vh;
        overflow-y: auto;
        animation: ptModalIn 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }
    @keyframes ptModalIn {
        from { opacity: 0; transform: translateY(16px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .pt-modal::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0;
        height: 4px; border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
    }
    .pt-modal-close {
        position: absolute; top: 12px; right: 14px; width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        background: none; border: none; border-radius: 50%;
        font-size: 22px; line-height: 1; color: #6b7684; cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .pt-modal-close:hover { background: #FDF1DC; color: #1c2a38; }

    .pt-modal-listing {
        display: flex; gap: 12px; padding-bottom: 14px; margin-bottom: 14px;
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
        display: flex; align-items: center; gap: 10px;
        padding-bottom: 14px; margin-bottom: 14px;
        border-bottom: 1px solid rgba(28, 42, 56, 0.08);
    }
    .pt-modal-tenant-avatar {
        width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        border: 2px solid #fff; box-shadow: 0 0 0 2px rgba(237, 164, 35, 0.5);
    }
    .pt-modal-tenant-name { margin: 0; font-size: 14px; font-weight: 600; color: #1c2a38; }
    .pt-modal-tenant-email { margin: 0; font-size: 12px; color: #6b7684; }

    .pt-modal-heading { margin: 0 0 12px; font-size: 15px; color: #1c2a38; }

    .pt-modal-payment {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 12px; margin-bottom: 10px;
        background: #FAFAFD; border: 1px solid rgba(28, 42, 56, 0.08); border-radius: 10px;
    }
    .pt-modal-payment-status {
        display: inline-block; padding: 4px 11px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
    }
    .pt-modal-payment-time { font-size: 12px; color: #6b7684; }

    .pt-modal-receipt-link {
        display: inline-flex; align-items: center; gap: 6px;
        margin-bottom: 10px; padding: 9px 14px; border-radius: 10px;
        background: #FDF1DC; border: 1px solid rgba(237, 164, 35, 0.5);
        color: #B07708; font-size: 12.5px; font-weight: 700; text-decoration: none;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .pt-modal-receipt-link:hover {
        background: #ffffff; transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.25);
    }

    .pt-modal-paid-row {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 10px 12px; margin-bottom: 10px;
        background: #FAFAFD; border: 1px solid rgba(28, 42, 56, 0.08); border-radius: 10px;
    }
    .pt-modal-paid-row > div { display: flex; flex-direction: column; gap: 2px; }
    .pt-modal-paid-row strong { color: #1c2a38; font-size: 14px; }

    .pt-modal-daterange {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 12px; margin-bottom: 14px;
        background: #FAFAFD; border: 1px solid rgba(28, 42, 56, 0.08); border-radius: 10px;
    }

    .pt-modal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 14px; }
    .pt-modal-detail { display: flex; flex-direction: column; gap: 2px; }
    .pt-modal-muted { font-size: 12px; color: #6b7684; }

    .pt-modal-total-row {
        display: flex; align-items: center; justify-content: space-between;
        padding-top: 14px; border-top: 1px solid rgba(28, 42, 56, 0.08); margin-bottom: 16px;
    }
    .pt-modal-total-row strong { color: #1c2a38; font-size: 17px; }

    .pt-modal-buttons { justify-content: flex-end; }

    /* SIGN-TO-ACCEPT MODAL */
    .pt-sign-sub { margin: -4px 0 14px; font-size: 12.5px; color: #6b7684; line-height: 1.6; }
    .pt-sign-sub b { color: #1c2a38; }

    .pt-sign-summary {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        padding: 12px; margin-bottom: 12px;
        background: #FAFAFD; border: 1px solid rgba(28, 42, 56, 0.08); border-radius: 10px;
    }
    .pt-sign-summary > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .pt-sign-summary strong {
        color: #1c2a38; font-size: 12.5px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .pt-sign-canvas-wrap { position: relative; margin-bottom: 10px; }
    #ptSignCanvas {
        display: block; width: 100%; height: 220px;
        background: #fff;
        border: 1.5px dashed #C9CDD6;
        border-radius: 12px;
        touch-action: none;
        cursor: crosshair;
    }
    .pt-sign-hint {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        pointer-events: none;
        color: #AAB1BE; font-size: 13px; font-weight: 600;
        transition: opacity 0.2s ease;
    }
    .pt-sign-hint.hidden { opacity: 0; }

    .pt-sign-tools { display: flex; justify-content: flex-end; margin-bottom: 14px; }

    #ptSignConfirm[disabled] {
        opacity: 0.5; cursor: not-allowed;
        transform: none !important; box-shadow: none !important;
    }

    @media (max-width: 720px) {
        .pt-actions {
            width: 100%; flex-direction: row;
            align-items: center; justify-content: space-between;
        }
    }
</style>

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

<script>
(function () {

    /* ---------- ACCEPT / REJECT (reject unchanged) ---------- */

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

    /* ---------- APPLICATION DETAILS MODAL ---------- */

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

    if (closeBtn) { closeBtn.addEventListener('click', closeModal); }

    if (overlay) {
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) { closeModal(); }
        });
    }

    /* ---------- SIGN-TO-ACCEPT ---------- */

    var signOverlay   = document.getElementById('ptSignOverlay');
    var signClose     = document.getElementById('ptSignClose');
    var signCancel    = document.getElementById('ptSignCancel');
    var signConfirm   = document.getElementById('ptSignConfirm');
    var signClear     = document.getElementById('ptSignClear');
    var signHint      = document.getElementById('ptSignHint');
    var signCanvas    = document.getElementById('ptSignCanvas');

    var signCard    = null;   /* the .pt-card being accepted */
    var signCtx     = signCanvas ? signCanvas.getContext('2d') : null;
    var hasInk      = false;
    var drawing     = false;
    var lastX       = 0;
    var lastY       = 0;

    function initSignCanvas() {
        if (!signCtx) return;
        var dpr  = window.devicePixelRatio || 1;
        var rect = signCanvas.getBoundingClientRect();
        var w    = Math.max(200, Math.round(rect.width));
        var h    = 220;

        signCanvas.width  = w * dpr;
        signCanvas.height = h * dpr;
        signCtx.setTransform(dpr, 0, 0, dpr, 0, 0);

        /* white background so the saved PNG is not transparent */
        signCtx.fillStyle = '#ffffff';
        signCtx.fillRect(0, 0, w, h);

        /* signature baseline */
        signCtx.strokeStyle = '#C9CDD6';
        signCtx.lineWidth = 1.2;
        signCtx.setLineDash([4, 6]);
        signCtx.beginPath();
        signCtx.moveTo(20, h - 50);
        signCtx.lineTo(w - 20, h - 50);
        signCtx.stroke();
        signCtx.setLineDash([]);

        /* ink settings */
        signCtx.strokeStyle = '#14142B';
        signCtx.lineWidth = 2.4;
        signCtx.lineCap = 'round';
        signCtx.lineJoin = 'round';

        hasInk = false;
        drawing = false;
        signConfirm.disabled = true;
        signHint.classList.remove('hidden');
    }

    function canvasPos(event) {
        var rect = signCanvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    function strokeTo(x, y) {
        signCtx.beginPath();
        signCtx.moveTo(lastX, lastY);
        signCtx.lineTo(x, y);
        signCtx.stroke();
        lastX = x;
        lastY = y;
    }

    if (signCanvas) {
        signCanvas.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            drawing = true;
            try { signCanvas.setPointerCapture(event.pointerId); } catch (e) {}
            var p = canvasPos(event);
            lastX = p.x; lastY = p.y;

            if (!hasInk) {
                hasInk = true;
                signConfirm.disabled = false;
                signHint.classList.add('hidden');
            }
            /* dot */
            signCtx.beginPath();
            signCtx.arc(p.x, p.y, 1.2, 0, Math.PI * 2);
            signCtx.fillStyle = '#14142B';
            signCtx.fill();
        });

        signCanvas.addEventListener('pointermove', function (event) {
            if (!drawing) return;
            var p = canvasPos(event);
            strokeTo(p.x, p.y);
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
            signCanvas.addEventListener(evt, function () { drawing = false; });
        });
    }

    function openSignModal(card) {
        signCard = card;

        document.getElementById('ptSignBookingRef').textContent =
            '#' + (card.getAttribute('data-booking-ref') || card.getAttribute('data-booking-id') || '000000');
        document.getElementById('ptSignTenant').textContent  = card.getAttribute('data-tenant-name') || '';
        document.getElementById('ptSignListing').textContent = card.getAttribute('data-listing-title') || '';
        document.getElementById('ptSignPaid').textContent    = card.getAttribute('data-paid') || '0.00';
        document.getElementById('ptSignTotal').textContent   = card.getAttribute('data-total') || '';

        var rUrl = card.getAttribute('data-receipt-url') || '';
        var link = document.getElementById('ptSignViewReceipt');
        if (rUrl) {
            link.href = rUrl;
            link.style.display = 'inline-flex';
        } else {
            link.style.display = 'none';
        }

        signOverlay.classList.add('open');
        initSignCanvas();
    }

    function closeSignModal() {
        signOverlay.classList.remove('open');
        signCard = null;
        signConfirm.disabled = true;
        signConfirm.textContent = 'Sign & Accept';
    }

    if (signClose)  { signClose.addEventListener('click', closeSignModal); }
    if (signCancel) { signCancel.addEventListener('click', closeSignModal); }

    if (signOverlay) {
        signOverlay.addEventListener('click', function (event) {
            if (event.target === signOverlay) { closeSignModal(); }
        });
    }

    if (signClear) {
        signClear.addEventListener('click', function () { initSignCanvas(); });
    }

    if (signConfirm) {
        signConfirm.addEventListener('click', function () {
            if (!signCard || !hasInk) return;

            var bookingId = signCard.getAttribute('data-booking-id');
            var dataUrl   = signCanvas.toDataURL('image/png');

            signConfirm.disabled = true;
            signConfirm.textContent = 'Saving signature\u2026';

            /* STEP 1 — save the signature */
            fetch('/webprogg/booking/sign-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'booking_id=' + encodeURIComponent(bookingId)
                    + '&signature=' + encodeURIComponent(dataUrl)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        alert(data.message || 'Could not save your signature.');
                        signConfirm.disabled = false;
                        signConfirm.textContent = 'Sign & Accept';
                        return;
                    }

                    /* STEP 2 — accept the application (existing endpoint) */
                    signConfirm.textContent = 'Accepting\u2026';
                    fetch('/webprogg/booking/accept-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'booking_id=' + encodeURIComponent(bookingId)
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (acc) {
                            if (acc.success) {
                                var card = signCard;
                                closeSignModal();
                                closeModal();
                                if (card) card.remove();
                            } else {
                                alert(acc.message || 'Signature saved, but the booking could not be accepted.');
                                signConfirm.disabled = false;
                                signConfirm.textContent = 'Sign & Accept';
                            }
                        })
                        .catch(function () {
                            alert('Signature saved, but the booking could not be accepted. Please try Accept again.');
                            signConfirm.disabled = false;
                            signConfirm.textContent = 'Sign & Accept';
                        });
                })
                .catch(function () {
                    alert('Something went wrong while saving your signature. Please try again.');
                    signConfirm.disabled = false;
                    signConfirm.textContent = 'Sign & Accept';
                });
        });
    }

    /* Esc closes whichever modal is open */
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (signOverlay.classList.contains('open')) { closeSignModal(); }
            else if (overlay.classList.contains('open')) { closeModal(); }
        }
    });

    /* ---------- WIRE THE BUTTONS ---------- */

    /* Card Accept -> open signature pad (NOT direct accept) */
    document.querySelectorAll('.pt-card').forEach(function (card) {
        var acceptBtn = card.querySelector('.pt-btn-accept');
        var rejectBtn = card.querySelector('.pt-btn-reject');
        var buttons = card.querySelectorAll('button');
        var bookingId = card.getAttribute('data-booking-id');
        var tenantName = card.getAttribute('data-tenant-name');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                openSignModal(card);
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

    /* Receipt links open in a new tab — don't trigger the card modal */
    document.querySelectorAll('.pt-receipt-link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    /* Modal Accept -> signature pad; Modal Reject -> unchanged */
    if (modalAcceptBtn) {
        modalAcceptBtn.addEventListener('click', function () {
            if (!activeCard) return;
            openSignModal(activeCard);
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