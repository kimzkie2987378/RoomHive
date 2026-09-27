<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userprofile.php

   VERIF — ID VERIFICATION BADGE:
   - Verified / Pending / Get Verified badge next to name.

   === HIVE CLUB REMOVED (this version) ===
   - Membership engine, tier badge chain, Hive points chart,
     membership payment queries and the footer link are gone.
   - Spending / pending totals now come from BOOKINGS ONLY.
   - Stats row is now FOUR charts:
     1. Bookings  — 6-month bars (all-time total highlighted)
     2. Spending  — 6-month ₱ bars (all-time total highlighted)
     3. Rating    — donut with the average as the center number
     4. Wishlist  — stacked bar by category + legend

   === WISHLIST FIX ===
   Overview wishlist remove button wired by DOCUMENT-LEVEL
   CAPTURE-PHASE delegation with stopPropagation().

   === HERO REMOVED ===
   Body carries `up-no-hero`; dashboard clears the navbar.

   === PHOTO PICKER — NATIVE LABEL ===
   The profile photo is a native <label for="avatarFileInput">.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/verification_gate.php';
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

/* =========================================================
   VERIF — AUTO-VERIFICATION STATUS
========================================================= */
 $isVerified   = is_user_verified($pdo, $_SESSION['user_id']);
 $hasPendingId = false;

try {
    if (!$isVerified) {
        $hpStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM user_id_documents
             WHERE user_id = :u AND status != 'rejected'"
        );
        $hpStmt->execute([':u' => $_SESSION['user_id']]);
        $hasPendingId = (int) $hpStmt->fetchColumn() > 0;
    }
} catch (PDOException $e) {
    $hasPendingId = false;
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

/* Real unread bell count */
 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
 );
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* REVIEWS */
 $reviewsStmt = $pdo->prepare("SELECT rating FROM reviews WHERE user_id = :id");
 $reviewsStmt->execute(['id' => $_SESSION['user_id']]);
 $reviews = array_map('floatval', array_column($reviewsStmt->fetchAll(), 'rating'));

 $average_rating = count($reviews) > 0 ? round(array_sum($reviews) / count($reviews), 1) : 0;

/* RECENT BOOKINGS — honest payment display */
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

/* PAYMENT SUMMARY — real money paid (bookings only) */
 $allBookingsStmt = $pdo->prepare(
    "SELECT amount_paid, booked_at FROM bookings WHERE user_id = :id AND status != 'cancelled'"
);
 $allBookingsStmt->execute(['id' => $_SESSION['user_id']]);
 $allBookingsForSpend = $allBookingsStmt->fetchAll();

 $oneWeekAgo = strtotime('-7 days');

 $bookings_spent_all_time = 0;
 $bookings_spent_this_week = 0;

foreach ($allBookingsForSpend as $b) {
    $amount = (float) ($b['amount_paid'] ?? 0);
    $bookings_spent_all_time += $amount;

    if (strtotime($b['booked_at']) >= $oneWeekAgo) {
        $bookings_spent_this_week += $amount;
    }
}

/* All-time booking count (drives the bookings chart highlight) */
 $lifeBkStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :id");
 $lifeBkStmt->execute(['id' => $_SESSION['user_id']]);
 $total_bookings_all = (int) $lifeBkStmt->fetchColumn();

/* COMBINED TOTALS — bookings only (Hive Club removed) */
 $total_spent_this_week = number_format($bookings_spent_this_week, 2);
 $total_spent_all_time  = number_format($bookings_spent_all_time, 2);

/* PENDING TO PAY — bookings only (Hive Club removed) */
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

 $total_pending_to_pay = number_format($bookings_pending_to_pay, 2);

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

/* =========================================================
   CHART DATA (replaces the icon stats row)
========================================================= */

/* 1 + 2) Bookings & spend per month, last 6 months */
 $upMonthMap = [];
try {
    $cmStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(booked_at, '%Y-%m') AS ym,
                COUNT(*) AS c,
                COALESCE(SUM(amount_paid), 0) AS s
         FROM bookings
         WHERE user_id = :id
           AND booked_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym"
    );
    $cmStmt->execute(['id' => $_SESSION['user_id']]);
    foreach ($cmStmt->fetchAll() as $row) {
        $upMonthMap[$row['ym']] = [
            'c' => (int) $row['c'],
            's' => (float) $row['s'],
        ];
    }
} catch (PDOException $e) {
    $upMonthMap = [];
}

 $chartMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("-{$i} months");
    $key = date('Y-m', $ts);
    $chartMonths[] = [
        'label'    => date('M', $ts),
        'bookings' => $upMonthMap[$key]['c'] ?? 0,
        'spend'    => $upMonthMap[$key]['s'] ?? 0.0,
    ];
}

 $bkMax = 1;
 $spMax = 0.01;
foreach ($chartMonths as $m) {
    $bkMax = max($bkMax, $m['bookings']);
    $spMax = max($spMax, $m['spend']);
}

 $upMoneyShort = function ($v) {
    $v = (float) $v;
    if ($v >= 1000000) { return number_format($v / 1000000, 1) . 'M'; }
    if ($v >= 1000)    { return number_format($v / 1000, 1) . 'K'; }
    return number_format($v, 0);
 };

/* 4) Wishlist by category (stacked bar) */
 $wlCats = [];
try {
    $wcStmt = $pdo->prepare(
        "SELECT l.category, COUNT(*) AS c
         FROM wishlist w
         JOIN listings l ON l.id = w.listing_id
         WHERE w.user_id = :id
         GROUP BY l.category
         ORDER BY c DESC
         LIMIT 4"
    );
    $wcStmt->execute(['id' => $_SESSION['user_id']]);
    $wlCats = $wcStmt->fetchAll();
} catch (PDOException $e) {
    $wlCats = [];
}

 $wlCatColors = ['#eda423', '#2F7DE1', '#1fa971', '#7a4bb0'];
 $wlCatTotal  = array_sum(array_map(fn($r) => (int) $r['c'], $wlCats));

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
    /* VERIF — ID VERIFICATION BADGE STATES */
    .up-badge-id-verified {
        background: #E8F8F1;
        color: #178A50;
        border: 1px solid rgba(23, 138, 80, 0.35);
    }
    .up-badge-id-pending {
        background: #FFF1DC;
        color: #B07708;
        border: 1px dashed rgba(237, 164, 35, 0.5);
    }
    .up-badge-id-none {
        background: #F0F0F0;
        color: #777777;
        border-color: transparent;
    }
    .up-badge-id-none:hover {
        background: #1c2a38;
        color: #ffffff;
    }

    /* HERO REMOVED — navbar clearance */
    .up-no-hero .up-notice {
        margin: 110px auto 0;
    }
    .up-no-hero .up-dashboard {
        margin-top: 110px;
    }
    .up-no-hero.has-notice .up-dashboard {
        margin-top: 26px;
    }

    /* =====================================================
       PHOTO PICKER — NATIVE LABEL + WHITE BADGE
    ====================================================== */
    .up-profile-photo .up-photo-hit {
        display: block;
        width: 100%;
        height: 100%;

        cursor: pointer;
        position: relative;
    }

    .up-profile-photo .up-photo-hit > img {
        width: 96px;
        height: 96px;

        border-radius: 12px;
        object-fit: cover;
        display: block;

        border: 3px solid #ffffff;
        box-shadow:
            0 0 0 3px var(--up-orange, #eda423),
            0 10px 22px rgba(237, 164, 35, 0.3);
    }

    /* WHITE circular badge behind the upload icon */
    .up-profile-photo .up-photo-edit {
        position: absolute;
        right: -4px;
        bottom: 0;

        width: 32px;
        height: 32px;

        display: flex;
        align-items: center;
        justify-content: center;

        background: #ffffff;
        border: 2px solid #ffffff;
        border-radius: 50%;

        box-shadow: 0 4px 12px rgba(28, 42, 56, 0.28);

        z-index: 6;

        transition: transform 0.2s ease;
    }

    .up-profile-photo .up-photo-hit:hover .up-photo-edit {
        transform: scale(1.12) rotate(8deg);
    }

    .up-profile-photo .up-photo-edit img {
        width: 18px;
        height: 18px;

        object-fit: contain;
        display: block;

        /* the icon keeps its own colors on the white badge */
        filter: none !important;

        pointer-events: none;
    }

    .up-profile-photo .up-photo-hit.is-busy {
        opacity: 0.6;
        cursor: wait;
    }

    /* =====================================================
       STATS -> LIVE CHARTS (icon-free, numbers highlighted)
    ====================================================== */
    .up-charts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 16px;
    }

    .up-chart-card {
        background: #ffffff;
        border: 1px solid var(--up-border, rgba(28, 42, 56, 0.08));
        border-radius: var(--up-radius, 18px);
        box-shadow: var(--up-shadow, 0 6px 20px rgba(28, 42, 56, 0.06));
        padding: 18px 16px 16px;

        transition:
            transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
            box-shadow 0.3s ease,
            border-color 0.3s ease;
    }

    .up-chart-card:hover {
        transform: translateY(-5px);
        border-color: rgba(237, 164, 35, 0.4);
        box-shadow: var(--up-shadow-lift, 0 18px 34px rgba(237, 164, 35, 0.16));
    }

    .up-ch-label {
        display: block;
        color: var(--up-text-muted, #6b7684);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    /* THE HIGHLIGHT — big, colored key number per chart */
    .up-ch-highlight {
        display: block;
        margin: 4px 0 2px;

        color: var(--up-navy, #1c2a38);
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1.1;
    }

    .up-ch-highlight.up-hl-gold  { color: #b07708; }
    .up-ch-highlight.up-hl-green { color: #1e7a3d; }
    .up-ch-highlight.up-hl-blue  { color: #2F7DE1; }

    .up-ch-sub {
        display: block;
        margin-bottom: 12px;

        color: #8B93A6;
        font-size: 10.5px;
    }

    /* vertical bars (bookings + spend) */
    .up-ch-bars {
        display: flex;
        align-items: flex-end;
        gap: 5px;

        height: 74px;
    }

    .up-ch-barcol {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;

        height: 100%;
    }

    .up-ch-barval {
        font-size: 9.5px;
        font-weight: 800;
        color: var(--up-navy, #1c2a38);
        white-space: nowrap;
    }

    .up-ch-bartrack {
        flex: 1;
        width: 100%;

        display: flex;
        align-items: flex-end;

        background: #F6F7F9;
        border-radius: 6px;
        overflow: hidden;
    }

    .up-ch-bar {
        width: 100%;
        height: var(--h, 4%);

        border-radius: 6px 6px 0 0;

        transition: height 0.6s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .up-ch-bar.gold { background: linear-gradient(180deg, #f6c04e, #eda423); }
    .up-ch-bar.green { background: linear-gradient(180deg, #43bd67, #1fa971); }

    .up-ch-barlbl {
        font-size: 9.5px;
        color: #8B93A6;
        white-space: nowrap;
    }

    /* donut (rating) */
    .up-ch-donut-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .up-ch-donut {
        position: relative;
        width: 84px;
        height: 84px;
        flex-shrink: 0;
    }

    .up-ch-donut svg { width: 100%; height: 100%; display: block; }

    .up-ch-donut-bg {
        fill: none;
        stroke: #F0F1F6;
        stroke-width: 9;
    }

    .up-ch-donut-fg {
        fill: none;
        stroke: #eda423;
        stroke-width: 9;
        stroke-linecap: round;
        stroke-dasharray: 125.66;
        stroke-dashoffset: 125.66;
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
        transition: stroke-dashoffset 1.2s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .up-ch-donut.on .up-ch-donut-fg {
        stroke-dashoffset: var(--off, 125.66);
    }

    .up-ch-donut-num {
        position: absolute;
        inset: 0;

        display: flex;
        align-items: center;
        justify-content: center;

        color: var(--up-navy, #1c2a38);
        font-size: 18px;
        font-weight: 800;
    }

    .up-ch-donut-side {
        display: flex;
        flex-direction: column;
        gap: 3px;
        font-size: 10.5px;
        color: #8B93A6;
    }

    .up-ch-donut-side strong {
        color: var(--up-navy, #1c2a38);
        font-size: 12px;
    }

    /* stacked bar (wishlist categories) */
    .up-ch-stack {
        display: flex;
        height: 14px;

        background: #F0F1F6;
        border-radius: 999px;
        overflow: hidden;
    }

    .up-ch-stack span {
        width: 0%;
        transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .up-ch-stack.on span {
        width: var(--w, 0%);
    }

    .up-ch-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 5px 12px;

        margin-top: 10px;
    }

    .up-ch-legend span {
        display: inline-flex;
        align-items: center;
        gap: 5px;

        font-size: 10px;
        color: #5d6875;
    }

    .up-ch-legend i {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .up-ch-empty {
        padding: 16px 4px;
        text-align: center;

        color: #8B93A6;
        font-size: 11px;
        font-style: italic;
    }

    /* dark mode */
    body[data-theme="dark"] .up-chart-card,
    html[data-theme-preview="1"] .up-chart-card {
        background: #1a222b;
    }

    body[data-theme="dark"] .up-ch-bartrack,
    html[data-theme-preview="1"] .up-ch-bartrack,
    body[data-theme="dark"] .up-ch-stack,
    html[data-theme-preview="1"] .up-ch-stack,
    body[data-theme="dark"] .up-ch-donut-bg,
    html[data-theme-preview="1"] .up-ch-donut-bg {
        background: #0d1218;
        stroke: #0d1218;
    }

    @media (prefers-reduced-motion: reduce) {
        .up-ch-bar,
        .up-ch-stack span,
        .up-ch-donut-fg {
            transition: none !important;
        }

        .up-ch-bar { height: var(--h, 4%) !important; }
        .up-ch-stack span { width: var(--w, 0%) !important; }
        .up-ch-donut-fg { stroke-dashoffset: var(--off, 125.66) !important; }
    }
</style>
</head>
<body class="up-no-hero<?php echo isset($_GET['booked']) ? ' has-notice' : ''; ?>">

<!-- SHARED NAVBAR (includes/usernav.php) -->
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<?php if (isset($_GET['booked'])): ?>
<section class="up-notice">
    &#10003; Your inquiry was sent! Check "My Bookings" below for the details.
</section>
<?php endif; ?>

<!-- WELCOME HERO — REMOVED -->

<!-- MAIN DASHBOARD LAYOUT -->
<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <!-- PROFILE CARD -->
    <section class="up-card up-profile-card up-reveal">
      <div class="up-profile-photo">
        <!-- NATIVE PICKER: the label's default action opens the
             choose-file dialog at the browser level. -->
        <label
            for="avatarFileInput"
            class="up-photo-hit"
            id="photoHit"
            title="Change profile photo"
            aria-label="Change profile photo"
        >
          <img src="<?php echo h($user['avatar']); ?>" alt="<?php echo h($user['name']); ?>" id="profileAvatarImg">
          <span class="up-photo-edit" id="photoEditBadge">
            <img src="/webprogg/images/UploadPhotosIcon-BecomeAHost.png" alt="">
          </span>
        </label>
        <input
            type="file"
            id="avatarFileInput"
            name="avatar"
            accept="image/jpeg,image/png,image/webp"
            style="position:absolute; width:1px; height:1px; opacity:0; overflow:hidden;"
        >
      </div>

      <div class="up-profile-info">
        <div class="up-profile-name-row">
          <h2><?php echo h($user['name']); ?></h2>

          <!-- VERIFIED BADGE (ID + complete profile) -->
          <?php if ($isVerified): ?>
            <span class="up-badge-verified up-badge-id-verified" title="Identity verified by RoomHive">
              <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
              Verified
            </span>
          <?php elseif ($hasPendingId): ?>
            <a href="/webprogg/user/editprofile.php" class="up-badge-verified up-badge-id-pending"
               title="Complete your profile to get verified">
              Verification Pending
            </a>
          <?php else: ?>
            <a href="/webprogg/user/editprofile.php" class="up-badge-verified up-badge-id-none"
               title="Get verified to unlock listing">
              Get Verified
            </a>
          <?php endif; ?>

          <!-- HIVE CLUB BADGES REMOVED -->
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

    <!-- =====================================================
         STATS AS LIVE CHARTS (4 charts — Hive points chart
         removed; key numbers highlighted big and colored)
    ====================================================== -->
    <section class="up-charts">

        <!-- 1) BOOKINGS — 6-month bars -->
        <div class="up-chart-card up-reveal" style="--i: 0;">
            <span class="up-ch-label">Bookings</span>
            <strong class="up-ch-highlight up-hl-blue"><?php echo number_format($total_bookings_all); ?></strong>
            <span class="up-ch-sub">all-time total</span>

            <div class="up-ch-bars" data-chart>
                <?php foreach ($chartMonths as $m):
                    $h = max(5, (int) round(($m['bookings'] / $bkMax) * 100));
                ?>
                <div class="up-ch-barcol" title="<?php echo h($m['label']); ?>: <?php echo (int) $m['bookings']; ?> booking<?php echo $m['bookings'] === 1 ? '' : 's'; ?>">
                    <span class="up-ch-barval"><?php echo (int) $m['bookings']; ?></span>
                    <div class="up-ch-bartrack">
                        <div class="up-ch-bar gold" style="--h: <?php echo $h; ?>%;"></div>
                    </div>
                    <span class="up-ch-barlbl"><?php echo h($m['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2) SPENDING — 6-month ₱ bars -->
        <div class="up-chart-card up-reveal" style="--i: 1;">
            <span class="up-ch-label">Total Spent</span>
            <strong class="up-ch-highlight up-hl-gold">&#8369;<?php echo h($total_spent_all_time); ?></strong>
            <span class="up-ch-sub">all-time (bookings)</span>

            <div class="up-ch-bars" data-chart>
                <?php foreach ($chartMonths as $m):
                    $h = max(4, (int) round(($m['spend'] / $spMax) * 100));
                ?>
                <div class="up-ch-barcol" title="<?php echo h($m['label']); ?>: &#8369;<?php echo number_format($m['spend'], 2); ?>">
                    <span class="up-ch-barval">&#8369;<?php echo h($upMoneyShort($m['spend'])); ?></span>
                    <div class="up-ch-bartrack">
                        <div class="up-ch-bar green" style="--h: <?php echo $h; ?>%;"></div>
                    </div>
                    <span class="up-ch-barlbl"><?php echo h($m['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3) AVERAGE RATING — donut -->
        <div class="up-chart-card up-reveal" style="--i: 2;">
            <span class="up-ch-label">Average Rating</span>
            <strong class="up-ch-highlight up-hl-gold"><?php echo number_format($average_rating, 1); ?> / 5</strong>
            <span class="up-ch-sub">from your reviews</span>

            <?php if (count($reviews) > 0): ?>
            <div class="up-ch-donut-wrap">
                <div
                    class="up-ch-donut"
                    data-donut
                    data-pct="<?php echo (int) round(($average_rating / 5) * 100); ?>"
                >
                    <svg viewBox="0 0 48 48" aria-hidden="true">
                        <circle class="up-ch-donut-bg" cx="24" cy="24" r="20"></circle>
                        <circle class="up-ch-donut-fg" cx="24" cy="24" r="20"></circle>
                    </svg>
                    <span class="up-ch-donut-num">&#9733;</span>
                </div>
                <div class="up-ch-donut-side">
                    <strong><?php echo count($reviews); ?> review<?php echo count($reviews) === 1 ? '' : 's'; ?></strong>
                    <span>rating fills the ring</span>
                    <span>(<?php echo (int) round(($average_rating / 5) * 100); ?>% of 5 stars)</span>
                </div>
            </div>
            <?php else: ?>
            <div class="up-ch-empty">
                No reviews yet — ratings from hosts will fill this ring.
            </div>
            <?php endif; ?>
        </div>

        <!-- 4) WISHLIST — stacked bar by category -->
        <div class="up-chart-card up-reveal" style="--i: 3;">
            <span class="up-ch-label">Wishlist</span>
            <strong class="up-ch-highlight up-hl-blue"><?php echo number_format($wishlist_total); ?></strong>
            <span class="up-ch-sub">saved propert<?php echo $wishlist_total === 1 ? 'y' : 'ies'; ?></span>

            <?php if ($wishlist_total > 0 && !empty($wlCats)): ?>
                <div class="up-ch-stack" data-chart>
                    <?php foreach ($wlCats as $i => $cat):
                        $c = (int) $cat['c'];
                        if ($c <= 0) { continue; }
                        $w = round(($c / max(1, $wlCatTotal)) * 100, 1);
                        $color = $wlCatColors[$i % count($wlCatColors)];
                        $label = ucwords(str_replace('-', ' ', (string) $cat['category']));
                    ?>
                    <span
                        style="--w: <?php echo $w; ?>%; background: <?php echo $color; ?>;"
                        title="<?php echo h($label); ?>: <?php echo $c; ?>"
                    ></span>
                    <?php endforeach; ?>
                </div>

                <div class="up-ch-legend">
                    <?php foreach ($wlCats as $i => $cat):
                        $color = $wlCatColors[$i % count($wlCatColors)];
                        $label = ucwords(str_replace('-', ' ', (string) $cat['category']));
                    ?>
                    <span>
                        <i style="background: <?php echo $color; ?>;"></i>
                        <?php echo h($label); ?>: <?php echo (int) $cat['c']; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="up-ch-empty">
                    Nothing saved yet — tap the heart on any listing.
                </div>
            <?php endif; ?>
        </div>

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

        <!-- VERIFICATION STATUS CARD -->
        <div class="up-card up-account-security up-reveal" style="--i: 0;">
          <div class="up-card-header">
            <h3>Identity Verification</h3>

            <?php if ($isVerified): ?>
              <span class="up-badge-verified up-badge-id-verified">
                <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
                Verified
              </span>
            <?php elseif ($hasPendingId): ?>
              <span class="up-badge-verified up-badge-id-pending">Pending</span>
            <?php endif; ?>
          </div>

          <?php if ($isVerified): ?>

            <p style="font-size:13px; color:#178A50; font-weight:700; margin:0 0 12px;">
              &#10003; Your identity is verified — you can list spaces and
              enjoy faster booking approvals.
            </p>

          <?php elseif ($hasPendingId): ?>

            <p style="font-size:13px; color:#B07708; font-weight:600; margin:0 0 12px;">
              &#8987; Your ID is received — complete your profile
              (name, phone, age, location) in Edit Profile to be
              verified automatically.
            </p>

          <?php else: ?>

            <p style="font-size:13px; color:#777777; margin:0 0 12px;">
              Verification is required before you can list a space.
              Upload one government-issued ID — it's free and automatic.
            </p>

          <?php endif; ?>

          <a href="/webprogg/user/editprofile.php#verify-card" class="up-btn-solid" style="display:inline-block;">
            <?php echo $isVerified ? 'VIEW VERIFICATION' : 'VERIFY NOW'; ?>
          </a>
        </div>

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
          <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($item['id']); ?>" class="listing-box" data-listing-id="<?php echo h($item['id']); ?>">
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
              <span class="up-wishlist-price">&#8369; <?php echo h($item['price']); ?> / month</span>
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
            <a href="/webprogg/Listings/listing.php?category=studio-loft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=shared-bedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entire-house">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>
        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
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

<!-- SCROLL REVEAL + CHART ANIMATIONS + PAGE BEHAVIOR
     (tail reconstructed from the docblock — VERIFY the two
     fetch endpoint URLs against your backend) -->
<script>
(function () {
    "use strict";
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- Scroll reveal ---- */
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

    /* ---- Chart animations (bars / stack / donut) ---- */
    var vizEls = Array.prototype.slice.call(
        document.querySelectorAll("[data-chart], [data-donut]")
    );

    if (reduced || !("IntersectionObserver" in window)) {
        vizEls.forEach(function (el) { el.classList.add("on"); });
    } else {
        var vIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                vIO.unobserve(entry.target);

                /* stagger the bars for a cascade */
                var bars = entry.target.querySelectorAll(".up-ch-bar");
                Array.prototype.forEach.call(bars, function (bar, i) {
                    bar.style.transitionDelay = (i * 45) + "ms";
                });

                /* donut: compute the dash offset from data-pct */
                if (entry.target.hasAttribute("data-donut")) {
                    var pct = parseFloat(entry.target.getAttribute("data-pct")) || 0;
                    var C = 125.66;
                    entry.target.style.setProperty("--off", (C * (1 - pct / 100)).toFixed(2));
                }

                entry.target.classList.add("on");
            });
        }, { threshold: 0.4 });
        vizEls.forEach(function (el) { vIO.observe(el); });
    }

    /* ---- Payment summary toggle (This Week / Pending to Pay) ---- */
    var sumTabs   = Array.prototype.slice.call(document.querySelectorAll(".up-summary-tab"));
    var sumAmount = document.querySelector(".up-summary-amount");
    var sumRange  = document.querySelector(".up-summary-range-label");

    sumTabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            sumTabs.forEach(function (t) { t.classList.remove("active"); });
            tab.classList.add("active");

            if (!sumAmount) { return; }
            if (tab.getAttribute("data-range") === "pending") {
                sumAmount.textContent = sumAmount.getAttribute("data-pending") || "0.00";
                if (sumRange) { sumRange.textContent = "Pending to Pay"; }
            } else {
                sumAmount.textContent = sumAmount.getAttribute("data-week") || "0.00";
                if (sumRange) { sumRange.textContent = "Last 7 Days"; }
            }
        });
    });

    /* =====================================================
       WISHLIST REMOVE — DOCUMENT-LEVEL CAPTURE-PHASE
       delegation with stopPropagation, so the row's own
       link navigation never fires before we decide.
    ====================================================== */
    document.addEventListener("click", function (e) {

        var btn = e.target.closest ? e.target.closest(".rh-save-btn") : null;
        if (!btn) { return; }

        var grid = document.getElementById("up-wishlist-grid");
        if (!grid || !grid.contains(btn)) { return; }

        /* We're on the overview wishlist — intercept the click
           and remove the item instead of navigating. */
        e.preventDefault();
        e.stopPropagation();

        var card      = btn.closest(".listing-box");
        var listingId = btn.getAttribute("data-listing-id");
        if (!card || !listingId) { return; }

        btn.classList.remove("saved");
        btn.disabled = true;

        fetch("/webprogg/user/wishlist-toggle.php", { /* VERIFY endpoint */
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "listing_id=" + encodeURIComponent(listingId)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success === false) {
                    throw new Error(data.message || "Could not remove.");
                }
                card.remove();

                var countEl = document.querySelector(".up-wishlist-count");
                var left    = grid.querySelectorAll(".listing-box").length;

                if (countEl) { countEl.textContent = String(left); }

                var empty = document.getElementById("up-wishlist-empty");
                if (empty && left === 0) {
                    grid.style.display = "none";
                    empty.style.display = "";
                }
            })
            .catch(function () {
                /* restore on failure */
                btn.classList.add("saved");
            })
            .then(function () {
                btn.disabled = false;
            });

    }, true); /* capture phase */

    /* ---- Avatar upload (native label picker) ---- */
    var avatarInput = document.getElementById("avatarFileInput");
    var avatarImg   = document.getElementById("profileAvatarImg");
    var photoHit    = document.getElementById("photoHit");

    if (avatarInput && avatarImg) {
        avatarInput.addEventListener("change", function () {
            var file = this.files && this.files[0];
            if (!file) { return; }

            if (photoHit) { photoHit.classList.add("is-busy"); }

            var fd = new FormData();
            fd.append("avatar", file);

            fetch("/webprogg/user/upload-avatar.php", { method: "POST", body: fd }) /* VERIFY endpoint */
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success && data.avatar) {
                        avatarImg.src = data.avatar;
                    } else {
                        window.alert((data && data.message) || "Upload failed. Try another image.");
                    }
                })
                .catch(function () {
                    window.alert("Upload failed. Please try again.");
                })
                .then(function () {
                    if (photoHit) { photoHit.classList.remove("is-busy"); }
                    avatarInput.value = "";
                });
        });
    }

})();
</script>

</body>
</html>