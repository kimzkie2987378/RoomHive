<?php
/* =========================================================
   ROOMHIVE — PENDING TENANTS
   pendingtenants.php

   SIGN-TO-ACCEPT: clicking Accept opens a signature pad; the
   host must draw their signature, which is saved by
   sign-receipt.php and stamped onto the guest's official
   receipt. Only then is the application accepted
   (accept-booking.php). Reject is unchanged.

   === TENANT VISIBILITY ===
   Each applicant now shows their VERIFICATION STATUS:
   - Verified / Not Verified pill next to their name (card
     + modal), using the same auto-verify rule as the gate
     (ID uploaded + complete profile via verification_gate).
   - Their PERSONAL PHOTO (separate from profile picture,
     uploaded in Edit Profile) appears in the details modal
     for in-person recognition — click to open full size.
   - personal_photo column self-heals if missing.

   === PAY FULL PRICE (compatible) ===
   pt_payment_breakdown() already compares amount_paid vs
   total, so bookings paid in full via process-payment.php
   show "Fully Paid" automatically — no changes required.
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
            u.avatar_path AS tenant_avatar, u.personal_photo
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE l.user_id = :id AND b.status = 'pending'
     ORDER BY b.booked_at DESC"
);

 $pendingApplications = [];
 $pendingStmtRan      = false;

try {
    $pendingStmt->execute(['id' => $_SESSION['user_id']]);
    $pendingApplications = $pendingStmt->fetchAll();
    $pendingStmtRan      = true;
} catch (PDOException $e) {
    /* personal_photo column missing — self-heal, then retry */
    try {
        $ppCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'personal_photo'")->fetch();
        if (!$ppCol) {
            $pdo->exec("ALTER TABLE users ADD COLUMN personal_photo VARCHAR(255) NULL");
        }
        $pendingStmt->execute(['id' => $_SESSION['user_id']]);
        $pendingApplications = $pendingStmt->fetchAll();
        $pendingStmtRan      = true;
    } catch (PDOException $e2) {
        error_log('pendingtenants: query failed: ' . $e2->getMessage());
    }
}

 $pending_tenants_count = count($pendingApplications);

/* -----------------------------------------------------
   VERIFICATION + PERSONAL PHOTO per pending tenant.
   Same auto-verify rule as the gate: ID uploaded +
   complete profile (name, phone, age, location).
----------------------------------------------------- */
if (!function_exists('pt_tenant_verification')) {
    function pt_tenant_verification($pdo, $tenantId) {
        $out = [
            'verified'       => false,
            'has_id'         => false,
            'id_status'      => null,
            'personal_photo' => '',
        ];

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/verification_gate.php';
            $out['verified'] = is_user_verified($pdo, (int) $tenantId);

            $v = $pdo->prepare(
                "SELECT status FROM user_id_documents
                 WHERE user_id = :u AND status != 'rejected'
                 ORDER BY uploaded_at DESC
                 LIMIT 1"
            );
            $v->execute([':u' => (int) $tenantId]);
            $out['id_status'] = $v->fetchColumn();
            $out['has_id']    = (bool) $out['id_status'];
        } catch (PDOException $e) { /* guarded */ }

        try {
            $p = $pdo->prepare(
                "SELECT personal_photo FROM users WHERE id = :u LIMIT 1"
            );
            $p->execute([':u' => (int) $tenantId]);
            $out['personal_photo'] = trim((string) $p->fetchColumn());
        } catch (PDOException $e) { /* guarded */ }

        return $out;
    }
}

/* Attach verification + personal photo to each application */
foreach ($pendingApplications as $key => $app) {
    $pendingApplications[$key]['verification'] = pt_tenant_verification($pdo, (int) $app['tenant_id']);
}

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
/* Shared host sidebar. Set $activePage before including. */
 $activePage = $activePage ?? 'pending';

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

    /* Section label + quit hosting styling */
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
        <p class="hp-page-subtitle">Tenants who applied for your spaces sit here until you accept or reject them. Accepting now requires signing the guest's receipt first. Verification badges show who completed ID + profile.</p>
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

        $tenantVer  = $app['verification'];
        $tenantPers = trim((string) ($tenantVer['personal_photo'] ?? ''));

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
            data-tenant-personal="<?php echo h($tenantPers); ?>"
            data-tenant-verified="<?php echo $tenantVer['verified'] ? '1' : '0'; ?>"
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
                <p class="pt-tenant-name">
                  <?php echo h($app['tenant_name']); ?>
                  <?php if ($tenantVer['verified']): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;background:#E8F8F1;color:#178A50;border:1px solid rgba(23,138,80,.35);border-radius:999px;font-size:10px;font-weight:800;vertical-align:middle;">&#10003; Verified</span>
                  <?php else: ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;background:#FFF4E0;color:#C77A00;border:1px dashed rgba(237,164,35,.5);border-radius:999px;font-size:10px;font-weight:800;vertical-align:middle;">Not Verified</span>
                  <?php endif; ?>
                </p>
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
            <p class="pt-modal-tenant-name">
              <span id="ptModalTenantName"></span>
              <span id="ptModalTenantVerified" class="pt-verified-pill"></span>
            </p>
            <p class="pt-modal-tenant-email" id="ptModalTenantEmail"></p>
          </div>
        </div>

        <div id="ptModalTenantPersonalWrap" style="display:none; margin:-4px 0 14px;">
          <span style="display:block; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#8B93A6; margin-bottom:6px;">Guest Personal Photo</span>
          <a id="ptModalTenantPersonalLink" href="#" target="_blank" rel="noopener" title="Open full size">
            <img id="ptModalTenantPersonal" src="" alt="Personal photo"
                 style="width:100%; max-width:380px; height:170px; object-fit:cover; object-position:center; border-radius:10px; border:1px solid #EEF1F6; display:block;">
          </a>
          <span style="display:block; margin-top:5px; font-size:11px; color:#8B93A6;">
            Used to recognize the guest in person. Click to open full size.
          </span>
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
            <a href="/webprogg/Listings/listing.php?category=studio-loft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=shared-bedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entire-house">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
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

    /* Verified pill (list + modal) */
    .pt-verified-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 9px; border-radius: 999px;
        font-size: 10px; font-weight: 800; vertical-align: middle;
    }
    .pt-verified-pill.yes { background: #E8F8F1; color: #178A50; border: 1px solid rgba(23,138,80,.35); }
    .pt-verified-pill.no  { background: #FFF4E0; color: #C77A00; border: 1px dashed rgba(237,164,35,.5); }

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
    "use strict";

    /* =========================================================
       SHARED HELPERS
    ========================================================= */
    function openOverlay(el)  { if (el) el.classList.add('open'); }
    function closeOverlay(el) { if (el) el.classList.remove('open'); }

    /* =========================================================
       ACCEPT / REJECT (reject unchanged — accept goes through
       the signature pad; handleDecision is used for REJECT only)
    ========================================================= */
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

    /* =========================================================
       APPLICATION DETAILS MODAL
    ========================================================= */
    var overlay         = document.getElementById('ptModalOverlay');
    var closeBtn        = document.getElementById('ptModalClose');
    var modalAcceptBtn  = document.getElementById('ptModalAccept');
    var modalRejectBtn  = document.getElementById('ptModalReject');
    var activeCard      = null;

    var mListingPhoto     = document.getElementById('ptModalListingPhoto');
    var mListingTitle     = document.getElementById('ptModalListingTitle');
    var mListingLocation  = document.getElementById('ptModalListingLocation');
    var mTenantAvatar     = document.getElementById('ptModalTenantAvatar');
    var mTenantName       = document.getElementById('ptModalTenantName');
    var mTenantVerified   = document.getElementById('ptModalTenantVerified');
    var mTenantEmail      = document.getElementById('ptModalTenantEmail');
    var mPersonalWrap     = document.getElementById('ptModalTenantPersonalWrap');
    var mPersonalLink     = document.getElementById('ptModalTenantPersonalLink');
    var mPersonal         = document.getElementById('ptModalTenantPersonal');
    var mPaymentStatus    = document.getElementById('ptModalPaymentStatus');
    var mPaymentTime      = document.getElementById('ptModalPaymentTime');
    var mReceiptLink      = document.getElementById('ptModalReceiptLink');
    var mPaid             = document.getElementById('ptModalPaid');
    var mBalance          = document.getElementById('ptModalBalance');
    var mDateRange        = document.getElementById('ptModalDateRange');
    var mCheckin          = document.getElementById('ptModalCheckin');
    var mCheckout         = document.getElementById('ptModalCheckout');
    var mGuests           = document.getElementById('ptModalGuests');
    var mApplied          = document.getElementById('ptModalApplied');
    var mTotal            = document.getElementById('ptModalTotal');

    function openDetailsModal(card) {
        if (!card) return;
        activeCard = card;
        var d = card.dataset;

        mListingPhoto.src    = d.listingPhoto || '';
        mListingTitle.textContent  = d.listingTitle || '';
        mListingLocation.textContent = d.listingLocation || '';

        mTenantAvatar.src = d.tenantAvatar || '';
        mTenantName.textContent = d.tenantName || '';
        if (d.tenantVerified === '1') {
            mTenantVerified.textContent = '\u2713 Verified';
            mTenantVerified.className = 'pt-verified-pill yes';
        } else {
            mTenantVerified.textContent = 'Not Verified';
            mTenantVerified.className = 'pt-verified-pill no';
        }
        mTenantEmail.textContent = d.tenantEmail || '';

        if (d.tenantPersonal) {
            mPersonalWrap.style.display = 'block';
            mPersonalLink.href = d.tenantPersonal;
            mPersonal.src = d.tenantPersonal;
        } else {
            mPersonalWrap.style.display = 'none';
            mPersonalLink.removeAttribute('href');
            mPersonal.removeAttribute('src');
        }

        mPaymentStatus.textContent = d.paymentStatus || '';
        mPaymentStatus.className = 'pt-modal-payment-status ' + (d.paymentClass || 'pt-payment-pending');
        mPaymentTime.textContent = d.paymentTime || '';

        if (d.receiptUrl) {
            mReceiptLink.href = d.receiptUrl;
            mReceiptLink.style.display = 'inline-flex';
        } else {
            mReceiptLink.style.display = 'none';
        }

        mPaid.textContent     = d.paid || '0.00';
        mBalance.textContent  = d.balance || '0.00';
        mDateRange.textContent = d.daterange || '';
        mCheckin.textContent  = d.checkin || '';
        mCheckout.textContent = d.checkout || '';
        mGuests.textContent   = d.guests || '';
        mApplied.textContent  = d.applied || '';
        mTotal.textContent    = d.total || '0.00';

        openOverlay(overlay);
    }

    /* Card click / keyboard opens the details modal */
    document.querySelectorAll('.pt-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('.pt-btn-accept') ||
                e.target.closest('.pt-btn-reject') ||
                e.target.closest('a')) {
                return;
            }
            openDetailsModal(card);
        });

        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDetailsModal(card);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () { closeOverlay(overlay); });
    }
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeOverlay(overlay);
        });
    }

    /* Reject from inside the modal */
    if (modalRejectBtn) {
        modalRejectBtn.addEventListener('click', function () {
            if (!activeCard) return;
            var card = activeCard;
            handleDecision(
                card.dataset.bookingId,
                card.dataset.tenantName,
                [modalRejectBtn],
                card,
                '/webprogg/booking/reject-booking.php',
                'Reject %s\'s application? Their payment will be refunded.',
                'Rejecting\u2026',
                function () { closeOverlay(overlay); activeCard = null; }
            );
        });
    }

    /* Accept from inside the modal -> SIGN-TO-ACCEPT (no direct accept) */
    if (modalAcceptBtn) {
        modalAcceptBtn.addEventListener('click', function () {
            var card = activeCard;
            closeOverlay(overlay);
            openSignModal(card);
        });
    }

    /* =========================================================
       SIGN-TO-ACCEPT MODAL (signature pad)
    ========================================================= */
    var signOverlay     = document.getElementById('ptSignOverlay');
    var signClose       = document.getElementById('ptSignClose');
    var signCancel      = document.getElementById('ptSignCancel');
    var signClear       = document.getElementById('ptSignClear');
    var signConfirm     = document.getElementById('ptSignConfirm');
    var signViewReceipt = document.getElementById('ptSignViewReceipt');

    var signBookingRef = document.getElementById('ptSignBookingRef');
    var signTenant     = document.getElementById('ptSignTenant');
    var signListing    = document.getElementById('ptSignListing');
    var signPaid       = document.getElementById('ptSignPaid');
    var signTotal      = document.getElementById('ptSignTotal');

    var canvas = document.getElementById('ptSignCanvas');
    var hint   = document.getElementById('ptSignHint');
    var ctx    = canvas ? canvas.getContext('2d') : null;

    var signCard = null;
    var drawing  = false;
    var hasInk   = false;
    var lastX = 0, lastY = 0;

    function sizeCanvas() {
        if (!canvas || !ctx) return;
        var ratio = window.devicePixelRatio || 1;
        var rect  = canvas.getBoundingClientRect();

        /* Preserve any existing strokes across resizes */
        var snapshot = hasInk ? canvas.toDataURL() : null;

        canvas.width  = Math.max(1, Math.round(rect.width * ratio));
        canvas.height = Math.max(1, Math.round(rect.height * ratio));
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth   = 2.2;
        ctx.lineCap     = 'round';
        ctx.lineJoin    = 'round';
        ctx.strokeStyle = '#14142B';

        if (snapshot) {
            var img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, rect.width, rect.height);
            };
            img.src = snapshot;
        }
    }

    function resetSignature() {
        if (!canvas || !ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk  = false;
        drawing = false;
        if (hint) hint.classList.remove('hidden');
        if (signConfirm) {
            signConfirm.disabled = true;
            signConfirm.textContent = 'Sign & Accept';
        }
    }

    function getPoint(e) {
        var rect = canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    if (canvas) {
        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            if (canvas.setPointerCapture) {
                try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* noop */ }
            }
            drawing = true;
            var p = getPoint(e);
            lastX = p.x;
            lastY = p.y;
            hasInk = true;
            if (hint) hint.classList.add('hidden');
            /* Draw a dot for a single tap */
            ctx.beginPath();
            ctx.arc(lastX, lastY, 1.4, 0, Math.PI * 2);
            ctx.fillStyle = '#14142B';
            ctx.fill();
            if (signConfirm) signConfirm.disabled = false;
        });

        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            e.preventDefault();
            var p = getPoint(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            lastX = p.x;
            lastY = p.y;
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
            canvas.addEventListener(evt, function () { drawing = false; });
        });
    }

    function openSignModal(card) {
        if (!card || !signOverlay) return;
        signCard = card;
        var d = card.dataset;

        if (signBookingRef) signBookingRef.textContent = '#' + (d.bookingRef || '000000');
        if (signTenant)     signTenant.textContent     = d.tenantName || '';
        if (signListing)    signListing.textContent    = d.listingTitle || '';
        if (signPaid)       signPaid.textContent       = d.paid || '0.00';
        if (signTotal)      signTotal.textContent      = d.total || '0.00';

        if (signViewReceipt) {
            if (d.receiptUrl) {
                signViewReceipt.href = d.receiptUrl;
                signViewReceipt.style.display = 'inline-flex';
            } else {
                signViewReceipt.style.display = 'none';
            }
        }

        resetSignature();
        openOverlay(signOverlay);
        /* Canvas must be sized while visible to get correct dimensions */
        requestAnimationFrame(sizeCanvas);
    }

    function closeSign() {
        closeOverlay(signOverlay);
        signCard = null;
        resetSignature();
    }

    /* Accept buttons on the cards open the sign modal */
    document.querySelectorAll('.pt-card .pt-btn-accept').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openSignModal(btn.closest('.pt-card'));
        });
    });

    /* Reject buttons on the cards (unchanged flow) */
    document.querySelectorAll('.pt-card .pt-btn-reject').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var card = btn.closest('.pt-card');
            handleDecision(
                btn.dataset.bookingId || (card ? card.dataset.bookingId : ''),
                btn.dataset.tenantName || (card ? card.dataset.tenantName : ''),
                [btn],
                card,
                '/webprogg/booking/reject-booking.php',
                'Reject %s\'s application? Their payment will be refunded.',
                'Rejecting\u2026',
                null
            );
        });
    });

    if (signClose)  signClose.addEventListener('click', closeSign);
    if (signCancel) signCancel.addEventListener('click', closeSign);
    if (signOverlay) {
        signOverlay.addEventListener('click', function (e) {
            if (e.target === signOverlay) closeSign();
        });
    }
    if (signClear) signClear.addEventListener('click', resetSignature);

    window.addEventListener('resize', function () {
        if (signOverlay && signOverlay.classList.contains('open')) {
            sizeCanvas();
        }
    });

    /* =========================================================
       SIGN & ACCEPT — save signature, then accept the booking
    ========================================================= */
    if (signConfirm) {
        signConfirm.addEventListener('click', function () {
            if (!signCard || !hasInk || !canvas) return;

            var bookingId     = signCard.dataset.bookingId;
            var signatureData = canvas.toDataURL('image/png');

            signConfirm.disabled = true;
            signConfirm.textContent = 'Saving signature\u2026';

            fetch('/webprogg/booking/sign-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'booking_id=' + encodeURIComponent(bookingId) +
                      '&signature=' + encodeURIComponent(signatureData)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Could not save the signature.');
                    }
                    /* Signature saved — now accept the application */
                    signConfirm.textContent = 'Accepting\u2026';
                    return fetch('/webprogg/booking/accept-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'booking_id=' + encodeURIComponent(bookingId)
                    }).then(function (res) { return res.json(); });
                })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Could not accept this application.');
                    }
                    if (signCard) signCard.remove();
                    closeSign();
                })
                .catch(function (err) {
                    alert(err && err.message ? err.message : 'Something went wrong. Please try again.');
                    signConfirm.disabled = false;
                    signConfirm.textContent = 'Sign & Accept';
                });
        });
    }

    /* =========================================================
       GLOBAL ESC — close whichever modal is open
    ========================================================= */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (overlay && overlay.classList.contains('open')) {
            closeOverlay(overlay);
        }
        if (signOverlay && signOverlay.classList.contains('open')) {
            closeSign();
        }
    });

})();
</script>

</body>
</html>