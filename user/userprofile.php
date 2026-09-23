<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userprofile.php

   HIVE CLUB INTEGRATION:
   - Real tier from the dual-bucket engine (sweep applied).
   - 5th stat card: lifetime + spendable points (Bronze
     auto-provisioned, so this always shows).
   - Badge logic: active tier / lapsed / join-upsell by points.
   - Real unread bell count (was hardcoded 0).
   - Recent bookings show honest payment state (paid vs total).
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* AUTH GUARD */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* USER DATA */
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

/* HOST REDIRECT */
if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);

 $user = [
    'name'          => $dbUser['name'],
    'avatar'        => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'location'      => $dbUser['location'] ?? '',
    'email'         => $dbUser['email'],
    'phone'         => $dbUser['phone'] ?? '',
    'age'           => $dbUser['age'] ?? '',
    'member_since'  => date('F Y', strtotime($dbUser['created_at'])),
    'about'         => '',
];

/* =========================================================
   HIVE CLUB MEMBERSHIP (engine: sweep + dual buckets)
========================================================= */
hive_expiry_sweep($pdo);
 $hiveMember = hive_member($pdo, $_SESSION['user_id']);

 $hiveTier          = $hiveMember ? (string) $hiveMember['tier'] : 'Bronze';
 $hiveLifetime      = $hiveMember ? (int) $hiveMember['lifetime_points'] : 0;
 $hiveRedeemable    = $hiveMember ? (int) $hiveMember['redeemable_points'] : 0;
 $hiveActive        = hive_active($hiveMember);
 $hiveDaysLeft      = hive_days_until_expiry($hiveMember);
 $hiveNextTier      = hive_next_tier($hiveLifetime);

/* Real unread bell count (was hardcoded 0) */
 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
 );
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* REVIEWS */
 $reviewsStmt = $pdo->prepare("SELECT rating FROM reviews WHERE user_id = :id");
 $reviewsStmt->execute(['id' => $_SESSION['user_id']]);
 $reviews = array_map('floatval', array_column($reviewsStmt->fetchAll(), 'rating'));

 $average_rating = count($reviews) > 0
    ? round(array_sum($reviews) / count($reviews), 1)
    : 0;

/* RECENT BOOKINGS — amount_paid added for honest payment display */
 $bookingsStmt = $pdo->prepare(
    "SELECT b.id, b.total, b.amount_paid, b.status, b.booked_at,
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
    $total = (float) $row['total'];
    $paid  = (float) ($row['amount_paid'] ?? 0);
    $left  = round(max(0, $total - $paid), 2);

    if ($paid > 0.005 && $left <= 0.005) {
        $payState = 'full';
    } elseif ($paid > 0.005) {
        $payState = 'advance';
    } else {
        $payState = 'none';
    }

    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'thumb'    => resolve_photo($row['cover_photo']),
        'dates'    => date('M j, Y', strtotime($row['booked_at'])),
        'status'   => $row['status'],
        'total'    => number_format($total, 2),
        'paid'     => number_format($paid, 2),
        'pay_state'=> $payState,
    ];
}, $bookingsStmt->fetchAll());

/* PAYMENT SUMMARY — BOOKINGS SPENT (actual money in) */
 $allBookingsStmt = $pdo->prepare(
    "SELECT amount_paid, booked_at FROM bookings WHERE user_id = :id AND status != 'cancelled'"
);
 $allBookingsStmt->execute(['id' => $_SESSION['user_id']]);
 $allBookingsForSpend = $allBookingsStmt->fetchAll();

 $oneWeekAgo = strtotime('-7 days');

 $bookings_spent_all_time = 0;
 $bookings_spent_this_week = 0;

foreach ($allBookingsForSpend as $b) {
    /* FIXED: real money paid, not the booking total — a 50%
       reserve no longer counts the unpaid half as "spent". */
    $amount = (float) ($b['amount_paid'] ?? 0);
    $bookings_spent_all_time += $amount;

    if (strtotime($b['booked_at']) >= $oneWeekAgo) {
        $bookings_spent_this_week += $amount;
    }
}

/* HIVE CLUB MEMBERSHIP PAYMENTS */
 $transactionsStmt = $pdo->prepare(
    "SELECT amount, purchased_at FROM hiveclub_transactions WHERE user_id = :id AND payment_status = 'paid'"
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

/* COMBINED TOTALS */
 $total_spent_this_week = number_format($bookings_spent_this_week + $membership_spent_this_week, 2);
 $total_spent_all_time  = number_format($bookings_spent_all_time + $membership_spent_all_time, 2);

/* PENDING TO PAY */
 $pendingBookingsStmt = $pdo->prepare(
    "SELECT GREATEST(total - amount_paid, 0) AS owed
     FROM bookings
     WHERE user_id = :id AND status = 'pending' AND amount_paid < total"
);
 $pendingBookingsStmt->execute(['id' => $_SESSION['user_id']]);
 $bookings_pending_to_pay = array_sum(array_map(
    'floatval',
    array_column($pendingBookingsStmt->fetchAll(), 'owed')
 ));

 $pendingTxnStmt = $pdo->prepare(
    "SELECT amount FROM hiveclub_transactions WHERE user_id = :id AND payment_status = 'pending'"
);
 $pendingTxnStmt->execute(['id' => $_SESSION['user_id']]);
 $membership_pending_to_pay = array_sum(array_map(
    'floatval',
    array_column($pendingTxnStmt->fetchAll(), 'amount')
 ));

 $total_pending_to_pay = number_format($bookings_pending_to_pay + $membership_pending_to_pay, 2);

 $payment_methods = [];
 $two_factor_enabled = false;

/* WISHLIST */
 $wishlistStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.location, l.price,
            p.photo_path AS cover_photo
     FROM wishlist w
     JOIN listings l ON l.id = w.listing_id
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE w.user_id = :id
     ORDER BY w.created_at DESC
     LIMIT 6"
);
 $wishlistStmt->execute(['id' => $_SESSION['user_id']]);

 $wishlist = array_map(function ($row) {
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'thumb'    => resolve_photo($row['cover_photo']),
        'price'    => number_format((float) $row['price'], 0),
        'rating'   => 0,
        'reviews'  => 0,
        'saved'    => true,
    ];
}, $wishlistStmt->fetchAll());

 $wishlist_total = count($wishlist);

/* STATS ROW — Hive Club points card added */
 $stats = [
    ['icon' => 'bookingsicon-userprofile.png',     'value' => count($bookings),  'label' => 'Bookings Total',            'count' => count($bookings), 'decimals' => 0],
    ['icon' => 'wihlistedicon-userprofile.png',    'value' => $wishlist_total,   'label' => 'Wishlisted Properties',     'count' => $wishlist_total,  'decimals' => 0],
    ['icon' => 'averageratinsicon-userprofile.png','value' => $average_rating,   'label' => 'Average Rating From Reviews','count' => $average_rating, 'decimals' => 1],
    ['icon' => 'totalspenticon-userprofile.png',   'value' => '&#8369; ' . $total_spent_all_time, 'label' => 'Total Spent All Time', 'count' => null, 'decimals' => 0],
    ['icon' => 'GoldIcon-HiveClub.png',            'value' => number_format($hiveLifetime), 'label' => 'Hive Club Points (' . $hiveTier . ')', 'count' => $hiveLifetime, 'decimals' => 0],
];

 $activeSidebar = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>

<style>
    /* Hive Club badge states + points sub-line (up-) */
    .up-badge-hc-active {
        background: linear-gradient(135deg, #f6b93b, #eda423);
        color: #1c2a38;
        border-color: transparent;
    }
    .up-badge-hc-lapsed {
        background: #FDF1DC;
        color: #B07708;
        border: 1px dashed rgba(237, 164, 35, 0.5);
    }
    .up-stat-card .up-hc-sub {
        display: block;
        margin-top: 2px;
        font-size: 10.5px;
        font-weight: 700;
        color: #B07708;
    }
</style>
</head>
<body>

<!-- SHARED NAVBAR (includes/usernav.php) -->
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<?php if (isset($_GET['booked'])): ?>
<section class="up-notice">
    &#10003; Your inquiry was sent! Check "My Bookings" below for the details.
</section>
<?php endif; ?>

<!-- WELCOME HERO -->
<section class="up-hero">
    <div aria-hidden="true">
        <span class="up-hero-blob up-hero-blob-1"></span>
        <span class="up-hero-blob up-hero-blob-2"></span>
    </div>

    <div class="up-hero-inner">
        <div class="up-hero-text">
            <span class="up-hero-badge up-anim" style="--d: .05s;">
                <span class="up-pulse-dot"></span>
                Member Dashboard
            </span>

            <p class="up-hero-eyebrow up-anim" style="--d: .12s;">Welcome back,</p>

            <h1 class="up-anim" style="--d: .18s;">
                <span class="up-shimmer"><?php echo h($user['name']); ?>!</span>
            </h1>

            <span class="up-welcome-underline up-anim" style="--d: .24s;"></span>

            <p class="up-hero-sub up-anim" style="--d: .3s;">
                Manage your bookings, favorites, and account
                settings all in one place.
            </p>
        </div>

        <div class="up-hero-art up-anim" style="--d: .3s;">
            <span class="up-art-glow" aria-hidden="true"></span>
            <img src="/webprogg/images/livingroomicon-userprofile.png" alt="">

            <div class="up-chip up-chip-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                    <path d="m3.5 7 8.5 6 8.5-6"/>
                </svg>
                <span>Recent Bookings</span>
            </div>

            <div class="up-chip up-chip-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s-7.5-4.7-9.5-9A5.4 5.4 0 0 1 12 6.5 5.4 5.4 0 0 1 21.5 12c-2 4.3-9.5 9-9.5 9z"/>
                </svg>
                <span>Saved Favorites</span>
            </div>

            <div class="up-chip up-chip-3">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l7 3v6c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                <span>Secure Account</span>
            </div>
        </div>
    </div>

    <svg class="up-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>
</section>

<!-- MAIN DASHBOARD LAYOUT -->
<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <!-- PROFILE CARD -->
    <section class="up-card up-profile-card up-reveal">
      <div class="up-profile-photo">
        <img src="<?php echo h($user['avatar']); ?>" alt="<?php echo h($user['name']); ?>" id="profileAvatarImg">
        <button type="button" class="up-photo-edit" id="photoButton" aria-label="Change profile photo">
          <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
        </button>
        <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
      </div>

      <div class="up-profile-info">
        <div class="up-profile-name-row">
          <h2><?php echo h($user['name']); ?></h2>

          <?php if ($hiveActive && $hiveTier !== 'Bronze'): ?>
            <!-- Paid tier, active -->
            <a href="/webprogg/user/membership.php" class="up-badge-verified up-badge-hc-active" style="text-decoration:none;">
              <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
              <?php echo h($hiveTier); ?> Member
              <?php if ($hiveDaysLeft !== null && $hiveDaysLeft <= 30): ?>
                &middot; <?php echo (int) $hiveDaysLeft; ?>d left
              <?php endif; ?>
            </a>
          <?php elseif ($hiveActive && $hiveLifetime > 0): ?>
            <!-- Bronze with earned points -->
            <a href="/webprogg/user/membership.php" class="up-badge-verified up-badge-hc-active" style="text-decoration:none;">
              <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
              Bronze Member
            </a>
          <?php elseif ($hiveLifetime > 0): ?>
            <!-- Lapsed paid plan — points intact -->
            <a href="/webprogg/user/membership.php" class="up-badge-verified up-badge-hc-lapsed" style="text-decoration:none;">
              Plan lapsed &mdash; renew
            </a>
          <?php else: ?>
            <!-- No points yet — join upsell -->
            <a href="/webprogg/hiveclub.php" class="up-badge-verified" style="text-decoration:none; background:#f0f0f0; color:#777777; border-color:transparent;">
              Join Hive Club
            </a>
          <?php endif; ?>
        </div>

        <ul class="up-profile-meta">
          <?php if ($user['location'] !== ''): ?>
            <li><img src="/webprogg/images/locationicon-userprofile.png" alt=""><?php echo h($user['location']); ?></li>
          <?php endif; ?>
          <li><img src="/webprogg/images/emailicon-userprofile.png" alt=""><?php echo h($user['email']); ?></li>
          <li><img src="/webprogg/images/phoneicon-userprofile.png" alt=""><?php echo $user['phone'] !== '' ? h($user['phone']) : ''; ?></li>
          <?php if ($user['age'] !== ''): ?>
            <li><img src="/webprogg/images/calendaricon-userprofile.png" alt=""><?php echo h($user['age']); ?> years old</li>
          <?php endif; ?>
          <li><img src="/webprogg/images/calendaricon-userprofile.png" alt="">Member since <?php echo h($user['member_since']); ?></li>
        </ul>
      </div>

      <div class="up-profile-about">
        <h3>About Me</h3>
        <p><?php echo $user['about'] !== '' ? h($user['about']) : 'No bio added yet.'; ?></p>
      </div>

      <a href="/webprogg/user/editprofile.php" class="up-btn-outline up-edit-profile" id="editProfileButton">Edit Profile</a>
    </section>

    <!-- STATS ROW -->
    <section class="up-stats">
      <?php foreach ($stats as $i => $stat): ?>
        <div class="up-stat-card up-reveal" style="--i: <?php echo (int) $i; ?>;">
          <img src="/webprogg/images/<?php echo h($stat['icon']); ?>" alt="">
          <div>
            <?php if ($stat['count'] !== null): ?>
                <strong
                    data-count="<?php echo h($stat['count']); ?>"
                    data-decimals="<?php echo (int) $stat['decimals']; ?>"
                ><?php echo $stat['value']; ?></strong>
            <?php else: ?>
                <strong><?php echo $stat['value']; ?></strong>
            <?php endif; ?>
            <span><?php echo h($stat['label']); ?></span>
            <?php if ($stat['label'] === 'Hive Club Points (' . $hiveTier . ')'): ?>
                <span class="up-hc-sub">&#128176; <?php echo number_format($hiveRedeemable); ?> spendable</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </section>

    <!-- RECENT BOOKINGS + PAYMENT SUMMARY -->
    <section class="up-two-col">

      <div class="up-card up-bookings-card up-reveal" style="--i: 1;">
        <div class="up-card-header">
          <h3>Recent Bookings</h3>
          <a href="/webprogg/booking/userbookings.php" class="up-link-view-all">View All</a>
        </div>

        <?php if (empty($bookings)): ?>
          <div class="up-bookings-empty">
            <p class="up-bookings-empty-title">No bookings yet</p>
            <p class="up-bookings-empty-text">Once you book a stay, it will show up here.</p>
            <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
          </div>
        <?php else: ?>
          <?php foreach ($bookings as $booking): ?>
            <a href="/webprogg/booking/booking-details.php?id=<?php echo h($booking['id']); ?>" class="up-booking-row">
              <img src="<?php echo h($booking['thumb']); ?>" alt="<?php echo h($booking['title']); ?>" class="up-booking-thumb">
              <div class="up-booking-info">
                <h4><?php echo h($booking['title']); ?></h4>
                <p class="up-booking-location"><?php echo h($booking['location']); ?></p>
                <p class="up-booking-dates"><img src="/webprogg/images/calendaricon-userprofile.png" alt=""><?php echo h($booking['dates']); ?></p>
              </div>
              <div class="up-booking-side">
                <span class="up-status up-status-<?php echo h($booking['status']); ?>">
                  <?php echo h(ucfirst($booking['status'])); ?>
                </span>

                <?php if ($booking['pay_state'] === 'full'): ?>
                    <strong>&#8369; <?php echo h($booking['total']); ?></strong>
                    <span>Fully Paid</span>
                <?php elseif ($booking['pay_state'] === 'advance'): ?>
                    <strong>&#8369; <?php echo h($booking['paid']); ?></strong>
                    <span>Advance Paid</span>
                <?php else: ?>
                    <strong>&#8369; <?php echo h($booking['total']); ?></strong>
                    <span>Booking Total</span>
                <?php endif; ?>
              </div>
              <span class="up-booking-chevron">&#8250;</span>
            </a>
          <?php endforeach; ?>

          <a href="/webprogg/booking/userbookings.php" class="up-btn-outline up-view-all-bookings">VIEW ALL BOOKINGS</a>
        <?php endif; ?>
      </div>

      <div class="up-right-col">

        <div class="up-card up-payment-summary up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Payment Summary</h3>
            <a href="/webprogg/user/userpayments.php" class="up-link-view-all">View All</a>
          </div>

          <div class="up-summary-toggle" role="tablist">
            <button type="button" class="up-summary-tab active" data-range="week">This Week</button>
            <button type="button" class="up-summary-tab" data-range="pending">Pending to Pay</button>
          </div>

          <div class="up-payment-summary-body">
            <div>
              <span class="up-muted up-summary-label">Total Spent</span>
              <strong>
                &#8369;
                <span
                  class="up-summary-amount"
                  data-week="<?php echo h($total_spent_this_week); ?>"
                  data-pending="<?php echo h($total_pending_to_pay); ?>"
                ><?php echo h($total_spent_this_week); ?></span>
              </strong>
              <span class="up-muted up-summary-range-label">Last 7 Days</span>
            </div>
            <img src="/webprogg/images/totalspenticon-userprofile.png" alt="" class="up-payment-summary-icon">
          </div>
        </div>

        <div class="up-card up-payment-methods up-reveal" style="--i: 3;">
          <div class="up-card-header">
            <h3>Payment Methods</h3>
            <a href="/webprogg/user/userpayments.php" class="up-link-view-all">Manage</a>
          </div>

          <?php if (empty($payment_methods)): ?>
            <div class="up-payment-methods-empty">
              <p>No payment methods yet</p>
              <p>Add a card to make booking faster.</p>
            </div>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <div class="up-card-item">
                <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>">
                <div>
                  <strong>&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; <?php echo h($method['last4']); ?></strong>
                  <span>Expires <?php echo h($method['expires']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <button type="button" class="up-btn-outline up-add-card">+ Add New Card</button>
        </div>

        <div class="up-card up-account-security up-reveal" style="--i: 4;">
          <div class="up-card-header">
            <h3>Account Security</h3>
            <span class="up-badge-secure">
              <img src="/webprogg/images/lockicon-userprofile.png" alt="">
              Secure
            </span>
          </div>
          <div class="up-security-row">
            <span>Two-Factor Authentication</span>
            <span class="<?php echo $two_factor_enabled ? 'up-security-enabled' : 'up-security-disabled'; ?>">
              <?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
          </div>
          <a href="/webprogg/user/security.php" class="up-btn-outline up-manage-security">Manage Security</a>
        </div>

        <div class="up-need-help up-reveal" style="--i: 5;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Our support team is here to assist you 24/7.</p>
            <a href="/webprogg/user/helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

      </div>
    </section>

    <!-- WISHLIST -->
    <section class="up-card up-wishlist up-reveal" style="--i: 1;">
      <div class="up-card-header">
        <h3>Wishlist (<span class="up-wishlist-count"><?php echo h($wishlist_total); ?></span>)</h3>
        <a href="/webprogg/user/userwishlist.php" class="up-link-view-all">View All</a>
      </div>

      <div class="up-wishlist-grid" id="up-wishlist-grid" <?php if (empty($wishlist)): ?>style="display:none;"<?php endif; ?>>
        <?php foreach ($wishlist as $item): ?>
          <a href="/webprogg/Listings/listing.php?id=<?php echo h($item['id']); ?>" class="listing-box" data-listing-id="<?php echo h($item['id']); ?>">
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

      <div class="up-wishlist-empty" id="up-wishlist-empty" style="<?php echo empty($wishlist) ? '' : 'display:none;'; ?>">
        <p style="margin:0 0 4px; font-weight:700; color:var(--up-navy, #1c2a38);">Your wishlist is empty</p>
        <p style="margin:0; font-size:13px;">Save listings you like and they'll show up here.</p>
      </div>
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

<!-- SCROLL REVEAL + STAT COUNT-UP -->
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

<!-- PAYMENT SUMMARY TOGGLE -->
<script>
(function () {
    const tabs = document.querySelectorAll('.up-summary-tab');
    const amountEl = document.querySelector('.up-summary-amount');
    const rangeLabel = document.querySelector('.up-summary-range-label');
    const amountLabel = document.querySelector('.up-summary-label');

    if (!tabs.length || !amountEl) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');

            const range = tab.getAttribute('data-range');
            if (range === 'pending') {
                amountEl.textContent = amountEl.getAttribute('data-pending');
                if (rangeLabel) rangeLabel.textContent = 'Awaiting Host Approval';
                if (amountLabel) amountLabel.textContent = 'Pending to Pay';
            } else {
                amountEl.textContent = amountEl.getAttribute('data-week');
                if (rangeLabel) rangeLabel.textContent = 'Last 7 Days';
                if (amountLabel) amountLabel.textContent = 'Total Spent';
            }
        });
    });
})();
</script>

<!-- PROFILE PHOTO UPLOAD -->
<script>
(function () {
    const photoButton = document.getElementById('photoButton');
    const fileInput    = document.getElementById('avatarFileInput');
    const avatarImg    = document.getElementById('profileAvatarImg');
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

        const previewUrl = URL.createObjectURL(file);
        const previousSrc = avatarImg.src;
        avatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/user/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) {
            if (!res.ok) {
                return res.json().catch(function () {
                    throw new Error('Upload endpoint returned ' + res.status);
                });
            }
            return res.json();
        })
        .then(function (data) {
            if (data.success) {
                avatarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                avatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function (err) {
            avatarImg.src = previousSrc;
            console.error('[avatar upload]', err);
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

<!-- WISHLIST REMOVE (Overview mini-grid) -->
<script>
(function () {
    const grid = document.getElementById('up-wishlist-grid');
    const emptyState = document.getElementById('up-wishlist-empty');
    const countEl = document.querySelector('.up-wishlist-count');
    const statValue = document.querySelector('.up-stat-wishlist strong');

    function syncWishlistUI() {
        const remaining = grid ? grid.querySelectorAll('.listing-box').length : 0;
        if (countEl) countEl.textContent = remaining;
        if (statValue) statValue.textContent = remaining;
        if (grid && emptyState) {
            grid.style.display = remaining === 0 ? 'none' : '';
            emptyState.style.display = remaining === 0 ? '' : 'none';
        }
    }

    if (!grid) return;

    grid.querySelectorAll('.rh-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const box = btn.closest('.listing-box');
            const listingId = btn.getAttribute('data-listing-id');
            if (!box || !listingId) return;

            btn.disabled = true;

            fetch('/webprogg/user/togglewishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    box.remove();
                    syncWishlistUI();
                } else {
                    btn.disabled = false;
                    alert(data.message || 'Could not update your wishlist.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Something went wrong. Please try again.');
            });
        });
    });
})();
</script>
</body>
</html>