<?php
/* =========================================================
   ROOMHIVE — HOST PROFILE (Overview)
   hostprofile.php

   Uses the shared host shell (host_init + host_navbar +
   host_sidebar + host_footer).

   SCHEMA NOTE: bookings has NO `total` and NO `paid_at`.
   Money = host_payout_amount (falls back to amount_paid);
   confirmed/completed status is treated as paid.

   === HERO REMOVED ===
   The HERO banner (greeting, shimmer name, floating hexes,
   parallax image, floating earnings card) is deleted. The
   .hp-dashboard clears the fixed navbar itself.

   === GROW BANNER REMOVED ===
   The "Grow Your Hosting Business" section is deleted.

   === OCCUPANCY LOCK + TENANT DETAILS ===
   1. A listing whose stay window covers TODAY (confirmed or
      completed booking; checkout NULL = ongoing monthly stay)
      shows an "Occupied" status badge.
   2. Occupied rows show the CURRENT TENANT's details (avatar,
      name, email, phone, check-in -> check-out).
   3. Occupied listings cannot be deleted: the Delete Listing
      option is replaced by a locked menu entry, AND
      delete-listing.php must have the server-side guard —
      the client-side hiding alone is not security.

   === LIVE PERFORMANCE OVERVIEW — NO ICONS (this version) ===
   Stat-card IMG ICONS are deleted. Each card now carries a
   DIFFERENT native chart type (no libraries, maximum
   compatibility — pure CSS + SVG only):
     - Bookings          -> vertical CSS bar sparkline (30d)
     - Occupancy Rate    -> SVG donut ring (kept)
     - Earnings          -> CSS heatmap strip (30d intensity)
     - Booking Status Mix-> horizontal stacked bar + legend
                           (new; replaces the plain number)
   The Daily Earnings SVG area/line chart and the 6-month
   Earnings Trend bar chart remain as-is. All animated via
   IntersectionObserver, with reduced-motion + no-JS fallbacks
   and dark-mode variants.
========================================================= */

require_once __DIR__ . '/host_init.php';

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change to becomeahost.php once appropriate
    exit;
}

/* Extend the shared $host array (host_init provides name/
   avatar/member_since) with the extra profile fields. */
 $host['location'] = $dbUser['location'] ?? '';
 $host['email']    = $dbUser['email'];
 $host['phone']    = $dbUser['phone'] ?? '';
 $host['age']      = $dbUser['age'] ?? '';
 $host['about']    = ''; // `users` has no `about` column yet

/* -----------------------------------------------------
   MY LISTINGS
----------------------------------------------------- */
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
   CURRENT OCCUPANTS
   Who is staying in each listing RIGHT NOW: a confirmed/
   completed booking whose stay window covers today. A
   booking with no checkout date (monthly, open-ended)
   counts as ongoing once checked in. Powers:
     - the "Occupied" status badge
     - the current-tenant details strip
     - the delete lock for occupied listings
----------------------------------------------------- */
 $occupantsStmt = $pdo->prepare(
    "SELECT b.listing_id, b.id AS booking_id,
            b.checkin_date, b.checkout_date,
            u.id AS tenant_id, u.name AS tenant_name,
            u.email AS tenant_email, u.phone AS tenant_phone,
            u.avatar_path AS tenant_avatar
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = b.user_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed', 'completed')
       AND b.checkin_date IS NOT NULL
       AND b.checkin_date <= CURDATE()
       AND (b.checkout_date IS NULL OR b.checkout_date = '' OR b.checkout_date >= CURDATE())
     ORDER BY b.checkin_date DESC"
);
 $occupantsStmt->execute(['id' => $_SESSION['user_id']]);

 $currentOccupants = [];
foreach ($occupantsStmt->fetchAll() as $row) {
    $lid = (int) $row['listing_id'];

    if (isset($currentOccupants[$lid])) {
        continue; /* keep the most recent stay per listing */
    }

    $currentOccupants[$lid] = [
        'booking_id' => (int) $row['booking_id'],
        'name'       => $row['tenant_name'],
        'email'      => $row['tenant_email'],
        'phone'      => (string) ($row['tenant_phone'] ?? ''),
        'avatar'     => !empty($row['tenant_avatar'])
                            ? $row['tenant_avatar']
                            : '/webprogg/images/default-avatar.png',
        'checkin'    => !empty($row['checkin_date'])
                            ? date('M j, Y', strtotime($row['checkin_date']))
                            : '&mdash;',
        'checkout'   => !empty($row['checkout_date'])
                            ? date('M j, Y', strtotime($row['checkout_date']))
                            : 'Ongoing',
    ];
}

/* -----------------------------------------------------
   BOOKING-BASED METRICS (all-time — feeds listing rows)
----------------------------------------------------- */
 $hostBookingsStmt = $pdo->prepare(
    "SELECT b.listing_id, b.amount_paid, b.host_payout_amount,
            b.checkin_date, b.checkout_date
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed', 'completed')"
);

 $hostBookingsStmt->execute(['id' => $_SESSION['user_id']]);
 $hostBookings = $hostBookingsStmt->fetchAll();

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

 $occupancyWindowDays = 30;
 $windowStart = new DateTime("-{$occupancyWindowDays} days");
 $windowEnd   = new DateTime('today');

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

    $listingStats[$lid]['earnings'] += (float) (
        $b['host_payout_amount'] !== null ? $b['host_payout_amount'] : $b['amount_paid']
    );

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

/* =========================================================
   GREETING + TENURE (kept — informational)
========================================================= */
 $hour = (int) date('G');

if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

 $firstName = explode(' ', trim($host['name']))[0];

 $daysAsHost = max(1, (int) floor(
    (time() - strtotime($dbUser['created_at'])) / 86400
));

/* =========================================================
   MONTHLY EARNINGS, LAST 6 MONTHS (real query)
   Powers the Earnings Trend chart.
========================================================= */
 $monthlyStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(b.booked_at, '%Y-%m') AS ym,
            SUM(CASE
                    WHEN b.host_payout_amount IS NULL THEN b.amount_paid
                    ELSE b.host_payout_amount
                END) AS month_total
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed', 'completed')
       AND b.booked_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(b.booked_at, '%Y-%m')"
);
 $monthlyStmt->execute(['id' => $_SESSION['user_id']]);

 $monthTotals = [];
foreach ($monthlyStmt->fetchAll() as $row) {
    $monthTotals[$row['ym']] = (float) $row['month_total'];
}

 $earnMonths    = [];
 $sixMonthTotal = 0.0;

for ($i = 5; $i >= 0; $i--) {
    $key   = date('Y-m', strtotime("-{$i} months"));
    $total = $monthTotals[$key] ?? 0.0;

    $sixMonthTotal += $total;

    $earnMonths[] = [
        'label' => date('M', strtotime("-{$i} months")),
        'total' => $total,
    ];
}

 $maxMonthTotal = 0.0;
foreach ($earnMonths as $m) {
    $maxMonthTotal = max($maxMonthTotal, $m['total']);
}

/* =========================================================
   DAILY ACTIVITY, LAST 30 DAYS (real query)
   Powers the Bookings sparkline + Earnings heatmap + the
   Daily Earnings area/line chart.
========================================================= */
 $dailyMap = [];
try {
    $dailyStmt = $pdo->prepare(
        "SELECT DATE(b.booked_at) AS d,
                COUNT(*) AS bookings,
                SUM(CASE
                        WHEN b.status IN ('confirmed', 'completed')
                        THEN COALESCE(b.host_payout_amount, b.amount_paid, 0)
                        ELSE 0
                    END) AS earnings
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE l.user_id = :id
           AND b.booked_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
         GROUP BY DATE(b.booked_at)"
    );
    $dailyStmt->execute(['id' => $_SESSION['user_id']]);

    foreach ($dailyStmt->fetchAll() as $row) {
        $dailyMap[$row['d']] = [
            'bookings' => (int) $row['bookings'],
            'earnings' => (float) $row['earnings'],
        ];
    }
} catch (Exception $e) {
    error_log('hostprofile: daily activity query failed: ' . $e->getMessage());
    /* $dailyMap stays empty — the charts render as flat/zero */
}

 $dailySeries   = [];
 $bookings_30d  = 0;
 $earnings_30d  = 0.0;

for ($i = 29; $i >= 0; $i--) {
    $ts  = strtotime("-{$i} days");
    $key = date('Y-m-d', $ts);

    $bk = $dailyMap[$key]['bookings'] ?? 0;
    $er = $dailyMap[$key]['earnings'] ?? 0.0;

    $bookings_30d += $bk;
    $earnings_30d += $er;

    $dailySeries[] = [
        'label'    => date('M j', $ts),
        'bookings' => $bk,
        'earnings' => $er,
    ];
}

 $n30 = count($dailySeries); /* always 30 */

 $dailyBookMax    = 0;
 $dailyEarnMaxRaw = 0.0;
foreach ($dailySeries as $p) {
    $dailyBookMax    = max($dailyBookMax, $p['bookings']);
    $dailyEarnMaxRaw = max($dailyEarnMaxRaw, $p['earnings']);
}

 $chartEarnMax = max($dailyEarnMaxRaw, 0.01);

/* Pre-build the SVG polyline points + area path */
 $pvPoints = [];
foreach ($dailySeries as $i => $p) {
    $x = 8 + ($i / max(1, $n30 - 1)) * 584;
    $y = 18 + (1 - ($p['earnings'] / $chartEarnMax)) * 132;
    $pvPoints[] = round($x, 1) . ',' . round($y, 1);
}
 $pvPointsStr = implode(' ', $pvPoints);
 $pvAreaPath  = 'M8,150 L' . implode(' L', $pvPoints) . ' L592,150 Z';

/* =========================================================
   NEW — BOOKING STATUS MIX (for the stacked bar card)
   One grouped count over ALL bookings on the host's
   listings, bucketed into 4 segments.
========================================================= */
 $statusMix = [];
try {
    $mixStmt = $pdo->prepare(
        "SELECT b.status, COUNT(*) AS c
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE l.user_id = :id
         GROUP BY b.status"
    );
    $mixStmt->execute(['id' => $_SESSION['user_id']]);
    foreach ($mixStmt->fetchAll() as $row) {
        $statusMix[$row['status']] = (int) $row['c'];
    }
} catch (Exception $e) {
    $statusMix = [];
}

 $mixTotal = array_sum($statusMix);

 $mixSegments = [
    ['label' => 'Pending',    'count' => $statusMix['pending'] ?? 0,                       'color' => '#eda423'],
    ['label' => 'Confirmed',  'count' => $statusMix['confirmed'] ?? 0,                     'color' => '#1fa971'],
    ['label' => 'Completed',  'count' => $statusMix['completed'] ?? 0,                     'color' => '#33517E'],
    ['label' => 'Cancelled / Rejected',
        'count' => ($statusMix['cancelled'] ?? 0) + ($statusMix['rejected'] ?? 0),          'color' => '#e0524d'],
];

 $activePage = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Host Profile — RoomHive</title>

<!-- Anti-flash dark-mode bootstrap -->
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=7">

<script>document.documentElement.classList.add("js");</script>

<style>
    /* =====================================================
       HERO REMOVED — navbar clearance for this page only.
    ====================================================== */
    .hp-dashboard {
        margin-top: 110px;
    }

    /* =====================================================
       OCCUPIED STATUS + TENANT STRIP
    ====================================================== */
    .hp-status-occupied {
        background: #E9F0FA;
        color: #33517E;
        border: 1px solid rgba(51, 81, 126, 0.28);
    }

    .hp-tenant-strip {
        display: flex;
        align-items: center;
        gap: 10px;

        margin-top: 8px;
        padding: 8px 10px;

        background: #F7F9FC;
        border: 1px dashed rgba(28, 42, 56, 0.18);
        border-radius: 10px;
    }

    .hp-tenant-strip img {
        width: 34px;
        height: 34px;
        flex-shrink: 0;

        border-radius: 8px;
        object-fit: cover;
    }

    .hp-tenant-strip > div {
        display: flex;
        flex-direction: column;
        gap: 1px;

        min-width: 0;
    }

    .hp-tenant-name {
        font-size: 12.5px;
        font-weight: 800;
        color: #1c2a38;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hp-tenant-meta {
        font-size: 11px;
        color: #5d6875;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hp-menu-locked {
        cursor: default !important;
        opacity: 0.65;
    }

    /* =====================================================
       LIVE PERFORMANCE CHARTS — icon-free, native only
    ====================================================== */

    .hp-stat-sub {
        display: block;
        margin-top: 2px;
        font-size: 10.5px;
        color: #8B93A6;
    }

    /* 1) BOOKINGS — vertical bar sparkline (CSS) */
    .hp-mini-spark {
        display: flex;
        align-items: flex-end;
        gap: 2px;

        height: 38px;
        margin-top: 12px;
    }

    .hp-mini-spark span {
        flex: 1;
        min-width: 3px;

        background: linear-gradient(180deg, #f6c04e, #eda423);
        border-radius: 2px 2px 0 0;

        transition: height 0.55s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .js .hp-mini-spark:not(.on) span {
        height: 5% !important;
    }

    .hp-mini-spark.on span {
        height: var(--h, 5%);
    }

    /* 2) EARNINGS — heatmap strip (CSS intensity cells) */
    .hp-heat {
        display: flex;
        gap: 2px;

        height: 38px;
        margin-top: 12px;
    }

    .hp-heat span {
        flex: 1;
        min-width: 3px;

        background: rgba(237, 164, 35, var(--a, 0.08));
        border-radius: 3px;

        opacity: 1;
        transition: opacity 0.5s ease;
    }

    .js .hp-heat:not(.on) span {
        opacity: 0;
    }

    .hp-heat.on span {
        opacity: 1;
    }

    /* 3) STATUS MIX — horizontal stacked bar (CSS) */
    .hp-mixbar {
        display: flex;

        height: 14px;
        margin-top: 12px;

        background: #F0F1F6;
        border-radius: 999px;
        overflow: hidden;
    }

    .hp-mixbar span {
        width: 0%;
        transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .hp-mixbar.on span {
        width: var(--w, 0%);
    }

    .hp-mixbar-empty {
        display: block;
        margin-top: 12px;
        padding: 4px 10px;

        border: 1px dashed rgba(28, 42, 56, 0.2);
        border-radius: 999px;

        font-size: 10.5px;
        color: #8B93A6;
        text-align: center;
    }

    .hp-mix-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;

        margin-top: 9px;
    }

    .hp-mix-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;

        font-size: 10.5px;
        color: #5d6875;
    }

    .hp-mix-dot {
        width: 9px;
        height: 9px;
        flex-shrink: 0;

        border-radius: 50%;
    }

    /* 4) DAILY EARNINGS — SVG area/line chart */
    .pv-chart {
        margin-top: 20px;
    }

    .pv-chart svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .pv-line {
        fill: none;
        stroke: #eda423;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .js .pv-anim-line {
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        transition: stroke-dashoffset 1.6s ease 0.15s;
    }

    .pv-chart.on .pv-anim-line {
        stroke-dashoffset: 0;
    }

    .pv-area {
        fill: rgba(237, 164, 35, 0.16);
        opacity: 1;
        transition: opacity 0.9s ease 0.8s;
    }

    .js .pv-chart:not(.on) .pv-area {
        opacity: 0;
    }

    .pv-chart.on .pv-area {
        opacity: 1;
    }

    .pv-dot {
        fill: #ffffff;
        stroke: #eda423;
        stroke-width: 2;
        cursor: pointer;
        transition: r 0.12s ease;
    }

    .pv-dot:hover {
        r: 4.5;
    }

    .pv-grid {
        stroke: rgba(28, 42, 56, 0.08);
        stroke-width: 1;
    }

    .pv-txt {
        font-size: 10px;
        fill: #8B93A6;
        font-family: "Poppins", sans-serif;
    }

    .pv-xlabels {
        display: flex;
        justify-content: space-between;

        margin-top: 6px;

        font-size: 10px;
        color: #8B93A6;
    }

    .pv-empty-note {
        margin: 8px 0 0;

        font-size: 11.5px;
        font-style: italic;
        color: #8B93A6;
    }

    /* Dark mode variants */
    body[data-theme="dark"] .hp-status-occupied,
    html[data-theme-preview="1"] .hp-status-occupied {
        background: rgba(51, 81, 126, 0.28);
        color: #9fc0ef;
    }

    body[data-theme="dark"] .hp-tenant-strip,
    html[data-theme-preview="1"] .hp-tenant-strip {
        background: #1a222b;
        border-color: rgba(232, 236, 241, 0.14);
    }

    body[data-theme="dark"] .hp-tenant-name,
    html[data-theme-preview="1"] .hp-tenant-name {
        color: #e8ecf1;
    }

    body[data-theme="dark"] .hp-tenant-meta,
    html[data-theme-preview="1"] .hp-tenant-meta {
        color: #8d99a5;
    }

    body[data-theme="dark"] .hp-stat-sub,
    html[data-theme-preview="1"] .hp-stat-sub {
        color: #8d99a5;
    }

    body[data-theme="dark"] .hp-mixbar,
    html[data-theme-preview="1"] .hp-mixbar {
        background: #0d1218;
    }

    body[data-theme="dark"] .hp-mix-item,
    html[data-theme-preview="1"] .hp-mix-item {
        color: #8d99a5;
    }

    body[data-theme="dark"] .hp-mixbar-empty,
    html[data-theme-preview="1"] .hp-mixbar-empty {
        border-color: rgba(232, 236, 241, 0.18);
        color: #8d99a5;
    }

    body[data-theme="dark"] .pv-grid,
    html[data-theme-preview="1"] .pv-grid {
        stroke: rgba(232, 236, 241, 0.10);
    }

    body[data-theme="dark"] .pv-txt,
    html[data-theme-preview="1"] .pv-txt {
        fill: #8d99a5;
    }

    body[data-theme="dark"] .pv-dot,
    html[data-theme-preview="1"] .pv-dot {
        fill: #1a222b;
    }

    body[data-theme="dark"] .pv-area,
    html[data-theme-preview="1"] .pv-area {
        fill: rgba(237, 164, 35, 0.22);
    }

    body[data-theme="dark"] .pv-xlabels,
    html[data-theme-preview="1"] .pv-xlabels {
        color: #8d99a5;
    }

    body[data-theme="dark"] .pv-empty-note,
    html[data-theme-preview="1"] .pv-empty-note {
        color: #8d99a5;
    }

    @media (prefers-reduced-motion: reduce) {
        .hp-mini-spark span,
        .hp-heat span,
        .hp-mixbar span {
            transition: none;
        }

        .js .hp-mini-spark:not(.on) span {
            height: var(--h, 5%) !important;
        }

        .js .hp-heat:not(.on) span {
            opacity: 1;
        }

        .js .hp-mixbar:not(.on) span {
            width: var(--w, 0%);
        }

        .js .pv-anim-line {
            transition: none;
            stroke-dashoffset: 0 !important;
        }

        .js .pv-chart:not(.on) .pv-area {
            opacity: 1;
        }
    }
</style>
</head>
<body>

    <?php include __DIR__ . '/host_navbar.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- HERO — REMOVED. Total earnings show in Performance
     Overview; monthly figures in the Earnings Trend card. -->

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard">

  <?php include __DIR__ . '/host_sidebar.php'; ?>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <!-- PROFILE INFORMATION -->
    <section class="hp-card hp-spotlight hp-reveal">

      <div class="hp-card-header">
        <h3>Profile Information</h3>
        <a href="/webprogg/host/hosteditprofile.php" class="hp-btn-outline" style="text-decoration:none;">Edit Profile</a>
      </div>

      <div class="hp-profile-body">

        <div class="hp-profile-photo">
          <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" id="hostProfileAvatarImg">
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

    <!-- =====================================================
         PERFORMANCE OVERVIEW — LIVE, ICON-FREE
         Bars (bookings) | Donut (occupancy) | Heatmap
         (earnings) | Stacked bar (status mix) + the daily
         earnings area/line chart below.
    ====================================================== -->
    <section class="hp-card hp-spotlight hp-reveal" style="--i: 1;">
      <div class="hp-card-header">
        <h3>Performance Overview</h3>
        <span class="hp-muted">Last 30 days &middot; live</span>
      </div>

      <div class="hp-stats">

        <!-- BOOKINGS — vertical bar sparkline -->
        <div class="hp-stat-card hp-reveal" style="--i: 0;">
          <div style="min-width:0; width:100%;">
            <span class="hp-stat-label">Bookings &mdash; 30 days</span>
            <strong
                data-count="<?php echo (int) $bookings_30d; ?>"
                data-decimals="0"><?php echo h($bookings_30d); ?></strong>
            <span class="hp-stat-sub"><?php echo h($total_bookings); ?> all-time &middot; one bar per day</span>

            <div class="hp-mini-spark" data-spark aria-hidden="true">
                <?php foreach ($dailySeries as $p):
                    $h = $dailyBookMax > 0
                        ? max(6, (int) round(($p['bookings'] / $dailyBookMax) * 100))
                        : 6;
                ?>
                <span
                    style="--h: <?php echo $h; ?>%;"
                    title="<?php echo h($p['label']); ?>: <?php echo (int) $p['bookings']; ?> booking<?php echo $p['bookings'] === 1 ? '' : 's'; ?>"
                ></span>
                <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- OCCUPANCY — SVG donut ring -->
        <div class="hp-stat-card hp-reveal" style="--i: 1;">
          <div class="hp-donut" data-pct="<?php echo (int) $occupancy_rate; ?>">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <circle class="hp-donut-bg" cx="24" cy="24" r="20"></circle>
                <circle class="hp-donut-fg" cx="24" cy="24" r="20"></circle>
            </svg>
            <span class="hp-donut-num"><?php echo (int) $occupancy_rate; ?>%</span>
          </div>
          <div>
            <span class="hp-stat-label">Occupancy Rate</span>
            <strong>30-day window</strong>
            <span class="hp-stat-sub"><?php echo h($total_occupied_nights); ?> occupied nights</span>
          </div>
        </div>

        <!-- EARNINGS — heatmap strip -->
        <div class="hp-stat-card hp-reveal" style="--i: 2;">
          <div style="min-width:0; width:100%;">
            <span class="hp-stat-label">Earnings &mdash; 30 days</span>
            <strong>
                &#8369;<span
                    data-count="<?php echo (float) $earnings_30d; ?>"
                    data-decimals="2"><?php echo h(number_format($earnings_30d, 2)); ?></span>
            </strong>
            <span class="hp-stat-sub">&#8369; <?php echo h(number_format($total_earnings, 2)); ?> all-time &middot; darker = more</span>

            <div class="hp-heat" data-spark aria-hidden="true">
                <?php foreach ($dailySeries as $p):
                    $alpha = $dailyEarnMaxRaw > 0
                        ? max(0.08, round($p['earnings'] / $dailyEarnMaxRaw, 2))
                        : 0.08;
                ?>
                <span
                    style="--a: <?php echo $alpha; ?>;"
                    title="<?php echo h($p['label']); ?>: &#8369;<?php echo h(number_format($p['earnings'], 2)); ?>"
                ></span>
                <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- BOOKING STATUS MIX — horizontal stacked bar -->
        <div class="hp-stat-card hp-reveal" style="--i: 3;">
          <div style="min-width:0; width:100%;">
            <span class="hp-stat-label">Booking Status Mix</span>
            <strong
                data-count="<?php echo (int) $mixTotal; ?>"
                data-decimals="0"><?php echo h($mixTotal); ?></strong>
            <span class="hp-stat-sub">all bookings on your listings</span>

            <?php if ($mixTotal > 0): ?>
                <div class="hp-mixbar" data-spark aria-hidden="true">
                    <?php foreach ($mixSegments as $seg):
                        if ($seg['count'] <= 0) { continue; }
                        $w = round(($seg['count'] / $mixTotal) * 100, 1);
                    ?>
                    <span
                        style="--w: <?php echo $w; ?>%; background: <?php echo $seg['color']; ?>;"
                        title="<?php echo h($seg['label']); ?>: <?php echo (int) $seg['count']; ?> (<?php echo $w; ?>%)"
                    ></span>
                    <?php endforeach; ?>
                </div>

                <div class="hp-mix-legend">
                    <?php foreach ($mixSegments as $seg): ?>
                    <span class="hp-mix-item">
                        <span class="hp-mix-dot" style="background: <?php echo $seg['color']; ?>;"></span>
                        <?php echo h($seg['label']); ?>: <?php echo (int) $seg['count']; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <span class="hp-mixbar-empty">No bookings yet</span>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- ===============================================
           DAILY EARNINGS — SVG area/line chart
      ================================================ -->
      <div class="pv-chart" id="pvDailyChart">

        <div class="hp-card-header" style="margin-bottom: 4px;">
            <span class="hp-stat-label" style="font-size: 12.5px; font-weight: 800; color: #1c2a38;">
                Daily Earnings &mdash; 30 days
            </span>
            <span class="hp-muted">hover a point for details</span>
        </div>

        <svg viewBox="0 0 600 160" role="img" aria-label="Daily earnings for the last 30 days">

            <!-- Gridlines: max / mid / zero -->
            <line class="pv-grid" x1="8" y1="18"  x2="592" y2="18"></line>
            <line class="pv-grid" x1="8" y1="84"  x2="592" y2="84"></line>
            <line class="pv-grid" x1="8" y1="150" x2="592" y2="150"></line>

            <text class="pv-txt" x="2" y="13">&#8369;<?php echo h(number_format($chartEarnMax, 0)); ?></text>
            <text class="pv-txt" x="2" y="147">0</text>

            <!-- Area fill under the line -->
            <path class="pv-area" d="<?php echo h($pvAreaPath); ?>"></path>

            <!-- The line (animates via pathLength trick) -->
            <polyline
                class="pv-line pv-anim-line"
                pathLength="1"
                points="<?php echo h($pvPointsStr); ?>"
            ></polyline>

            <!-- Hover points (native tooltips) -->
            <?php foreach ($dailySeries as $i => $p):
                $x = 8 + ($i / max(1, $n30 - 1)) * 584;
                $y = 18 + (1 - ($p['earnings'] / $chartEarnMax)) * 132;
            ?>
            <circle
                class="pv-dot"
                cx="<?php echo round($x, 1); ?>"
                cy="<?php echo round($y, 1); ?>"
                r="3"
            >
                <title>&#8369; <?php echo h(number_format($p['earnings'], 2)); ?> &mdash; <?php echo h($p['label']); ?></title>
            </circle>
            <?php endforeach; ?>

        </svg>

        <!-- X-axis day labels -->
        <div class="pv-xlabels">
            <?php foreach ([0, 6, 12, 18, 24, 29] as $xi): ?>
                <span><?php echo h($dailySeries[$xi]['label'] ?? ''); ?></span>
            <?php endforeach; ?>
        </div>

        <?php if ($dailyEarnMaxRaw <= 0): ?>
            <p class="pv-empty-note">
                No confirmed earnings in the last 30 days yet —
                new confirmed bookings will plot here automatically.
            </p>
        <?php endif; ?>

      </div>
    </section>

    <!-- EARNINGS TREND (real 6-month bar chart) -->
    <section class="hp-card hp-spotlight hp-reveal" style="--i: 2;">
      <div class="hp-card-header">
        <h3>Earnings Trend</h3>
        <span class="hp-muted">Last 6 months</span>
      </div>

      <div class="hp-earn-summary">

        <strong>
            &#8369; <?php echo h(number_format($sixMonthTotal, 2)); ?>
        </strong>

        <span>
            earned since <?php echo h(date('M Y', strtotime('-5 months'))); ?>
        </span>

      </div>

      <div class="hp-bars hp-earn-chart" id="hpEarnChart">

        <?php foreach ($earnMonths as $m):

            $pct = $maxMonthTotal > 0
                ? (int) round(($m['total'] / $maxMonthTotal) * 100)
                : 0;
        ?>

            <div
                class="hp-bar-col"
                title="&#8369; <?php echo h(number_format($m['total'], 2)); ?> &mdash; <?php echo h($m['label']); ?>"
            >

                <span class="hp-bar-value">
                    <?php echo $m['total'] > 0
                        ? '&#8369;' . h(number_format($m['total'] / 1000, 1)) . 'k'
                        : '&ndash;'; ?>
                </span>

                <div class="hp-bar-track">
                    <div class="hp-bar" data-h="<?php echo (int) $pct; ?>" style="height:0%;"></div>
                </div>

                <span class="hp-bar-label"><?php echo h($m['label']); ?></span>

            </div>

        <?php endforeach; ?>

      </div>
    </section>

    <!-- MY LISTINGS -->
    <section class="hp-card hp-spotlight hp-reveal" style="--i: 3;">
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

          <?php foreach ($listings as $listing):

              $isOccupied = isset($currentOccupants[$listing['id']]);
              $occupant   = $isOccupied ? $currentOccupants[$listing['id']] : null;
          ?>
            <div class="hp-listing-row">

              <div class="hp-listing-info">
                <img src="<?php echo h(resolve_photo($listing['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="<?php echo h($listing['title']); ?>">
                <div>
                  <h4><?php echo h($listing['title']); ?></h4>
                  <p><?php echo h($listing['location']); ?></p>

                  <?php if ($isOccupied && $occupant !== null): ?>
                    <!-- current tenant details -->
                    <div class="hp-tenant-strip" title="Current occupant of this space">
                      <img src="<?php echo h($occupant['avatar']); ?>" alt="">
                      <div>
                        <span class="hp-tenant-name">
                            &#128100; <?php echo h($occupant['name']); ?>
                        </span>
                        <span class="hp-tenant-meta">
                            &#9993; <?php echo h($occupant['email']); ?>
                        </span>
                        <span class="hp-tenant-meta">
                            Stay: <?php echo $occupant['checkin']; ?> &rarr; <?php echo $occupant['checkout']; ?><?php if ($occupant['phone'] !== ''): ?>
                                &middot; &#9742; <?php echo h($occupant['phone']); ?>
                            <?php endif; ?>
                        </span>
                      </div>
                    </div>
                  <?php endif; ?>
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
                <?php if ($isOccupied): ?>
                  <span
                    class="hp-status hp-status-occupied"
                    title="Currently occupied by <?php echo h($occupant['name']); ?> (<?php echo $occupant['checkin']; ?> &rarr; <?php echo $occupant['checkout']; ?>)"
                  >
                    Occupied
                  </span>
                <?php else: ?>
                  <span class="hp-status <?php echo hp_status_class($listing['status']); ?>">
                    <?php echo h(hp_status_label($listing['status'])); ?>
                  </span>
                <?php endif; ?>

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

                    <?php if ($isOccupied): ?>
                      <span class="hp-menu-item hp-menu-locked" aria-disabled="true"
                            title="This space is currently occupied and cannot be deleted">
                        &#128274; Occupied &mdash; can't delete
                      </span>
                    <?php else: ?>
                      <button type="button" class="hp-menu-item hp-menu-delete" data-listing-id="<?php echo h($listing['id']); ?>" data-listing-title="<?php echo h($listing['title']); ?>">
                        Delete Listing
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

            </div>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>
    </section>

    <!-- GROW YOUR HOSTING BUSINESS — REMOVED -->

  </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>

<!-- =========================================================
     SCRIPT — reveal, count-ups, donut, trend bars, spotlight,
     sparklines, heatmap, stacked bar, daily chart. Native JS
     only. Self-contained.
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- Scroll reveal ---- */
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".hp-reveal"));
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

    /* ---- Count-ups (stat cards) ---- */
    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var cIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                cIO.unobserve(el);

                var target = parseFloat(el.getAttribute("data-count")) || 0;
                var dec = parseInt(el.getAttribute("data-decimals"), 10) || 0;
                var t0 = null;

                var step = function (ts) {
                    if (!t0) t0 = ts;
                    var k = Math.min((ts - t0) / 1400, 1);
                    var eased = 1 - Math.pow(1 - k, 3);
                    el.textContent = (target * eased).toLocaleString(
                        undefined,
                        { minimumFractionDigits: dec, maximumFractionDigits: dec }
                    );
                    if (k < 1) window.requestAnimationFrame(step);
                };

                window.requestAnimationFrame(step);
            });
        }, { threshold: 0.6 });
        Array.prototype.forEach.call(counters, function (el) { cIO.observe(el); });
    }

    /* ---- Occupancy donut ---- */
    var C = 125.66; /* 2 x PI x r(20) */

    function setDonut(d) {
        var fg = d.querySelector(".hp-donut-fg");
        var pct = parseFloat(d.getAttribute("data-pct")) || 0;
        if (fg) {
            fg.style.strokeDashoffset = (C * (1 - pct / 100)).toFixed(2);
        }
    }

    var donuts = document.querySelectorAll(".hp-donut");
    if (donuts.length) {
        if (reduced || !("IntersectionObserver" in window)) {
            donuts.forEach(setDonut);
        } else {
            var dIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var d = entry.target;
                    dIO.unobserve(d);
                    window.setTimeout(function () { setDonut(d); }, 250);
                });
            }, { threshold: 0.5 });
            donuts.forEach(function (d) { dIO.observe(d); });
        }
    }

    /* ---- Trend bars grow when the chart scrolls into view ---- */
    var chart = document.getElementById("hpEarnChart");

    if (chart) {
        var bars = chart.querySelectorAll(".hp-bar[data-h]");

        var grow = function () {
            bars.forEach(function (bar, i) {
                bar.style.transitionDelay = (i * 70) + "ms";
                bar.style.height = (bar.getAttribute("data-h") || 0) + "%";
            });
        };

        if (reduced || !("IntersectionObserver" in window)) {
            grow();
        } else {
            var bIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    bIO.unobserve(entry.target);
                    window.setTimeout(grow, 200);
                });
            }, { threshold: 0.35 });
            bIO.observe(chart);
        }
    }

    /* =====================================================
       MINI CHARTS — bars / heatmap / stacked bar
       Start hidden (CSS), animate in when scrolled into
       view. Stacked-bar segments stagger their widths.
    ====================================================== */
    document.querySelectorAll(".hp-mini-spark, .hp-heat, .hp-mixbar").forEach(function (viz) {

        if (reduced || !("IntersectionObserver" in window)) {
            viz.classList.add("on");
            return;
        }

        var vIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                vIO.unobserve(entry.target);

                /* stagger the segments */
                entry.target.querySelectorAll("span").forEach(function (seg, i) {
                    seg.style.transitionDelay = (i * 16) + "ms";
                });

                entry.target.classList.add("on");
            });
        }, { threshold: 0.4 });

        vIO.observe(viz);
    });

    /* =====================================================
       DAILY EARNINGS CHART (draw-in + area fade)
    ====================================================== */
    var pvChart = document.getElementById("pvDailyChart");

    if (pvChart) {
        if (reduced || !("IntersectionObserver" in window)) {
            pvChart.classList.add("on");
        } else {
            var pIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    pIO.unobserve(entry.target);
                    entry.target.classList.add("on");
                });
            }, { threshold: 0.35 });
            pIO.observe(pvChart);
        }
    }

    /* ---- Cursor spotlight on cards ---- */
    if (!reduced && window.matchMedia("(hover: hover)").matches) {
        document.querySelectorAll(".hp-spotlight").forEach(function (card) {
            card.addEventListener("pointermove", function (e) {
                var r = card.getBoundingClientRect();
                card.style.setProperty("--mx", (e.clientX - r.left) + "px");
                card.style.setProperty("--my", (e.clientY - r.top) + "px");
            });
        });
    }
})();
</script>

<!-- =========================================================
     LISTING 3-DOT MENU + DELETE (occupied rows don't render
     a delete button, so they can't reach this)
========================================================= -->
<script>
(function () {
    document.querySelectorAll('.hp-listing-menu').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const wrap = btn.closest('.hp-menu-wrap');
            const wasOpen = wrap.classList.contains('open');

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

    document.addEventListener('click', function () {
        document.querySelectorAll('.hp-menu-wrap.open').forEach(function (w) {
            w.classList.remove('open');
        });
    });

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

</body>
</html>