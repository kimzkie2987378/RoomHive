<?php
/**admin.php
 * RoomHive Admin Dashboard — backed by real queries.
 *
 * KEPT: all-status revenue, CSRF-verified host-app actions,
 * reject demotes is_host, both decisions notify the applicant,
 * nav alignment (☰ off-canvas sidebar, topbar shadow),
 * Hive Club nav entry.
 *
 * NATIVE CHARTS, BEST-FIT TYPES (Chart.js removed):
 *   - Bookings 7d        -> SVG area/line (trend over time)
 *   - Bookings by Status -> SVG donut (parts of a whole)
 *   - Revenue 7d         -> vertical bars (magnitude compare)
 *   - Growth 6 months    -> grouped bars (2-series compare)
 *   - Listings by Status -> SVG donut
 *   - Rating Distribution-> horizontal bars (ordered scale)
 *   - Top Locations      -> horizontal bars (ranking)
 *
 * === THIS VERSION ===
   1. BOOKINGS OVERVIEW NUMBERS: each point on the 7-day
      line now shows its booking count as a label above the
      dot (plus a mid-value gridline label) — previously only
      the max/0 axis labels existed, so the per-day numbers
      were invisible.
   2. RATING DISTRIBUTION + TOP LOCATIONS MOVED UP into the
      upper cluster (Row 2 left column, under Revenue ->
      Platform Summary -> Growth/Listings pair). The old
      bottom row is removed — all chart panels now occupy
      the space beside the tall list column.
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/*
 * =========================================================
 * ADMIN AUTH GUARD
 * =========================================================
 */
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: /webprogg/auth/adminlogin.php');
    exit();
}

 $adminName  = $_SESSION['admin_name']  ?? 'Admin User';
 $adminEmail = $_SESSION['admin_email'] ?? '';

/* ---- CSRF token (per-session) — used by the host-app forms ---- */
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
 $adminCsrf = $_SESSION['admin_csrf'];

/* =========================================================
   PHOTO RESOLVER
========================================================= */
if (!function_exists('resolve_photo')) {
    function resolve_photo($path, $fallback = '/webprogg/images/ListingPlaceholder.png') {
        if (empty($path)) {
            return $fallback;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        if (stripos($normalized, 'webprogg/') === 0) {
            $normalized = substr($normalized, strlen('webprogg/'));
        }
        return '/webprogg/' . $normalized;
    }
}

/* ---------- Shared notifier for dashboard actions ---------- */
if (!function_exists('admin_notify_user')) {
    function admin_notify_user($pdo, $userId, $message, $link) {
        try {
            if ((int) $userId <= 0 || trim((string) $message) === '') { return false; }
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                 VALUES (:u, :m, :l, 0, NOW())"
            );
            $stmt->execute([
                'u' => (int) $userId,
                'm' => mb_substr(trim((string) $message), 0, 240),
                'l' => (string) $link,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('admin.php notify failed: ' . $e->getMessage());
            return false;
        }
    }
}

/*
 * =========================================================
 * HOST APPLICATION ACTIONS (Approve / Reject)
 * CSRF-verified; reject DEMOTES is_host to 0; both
 * decisions notify the applicant.
 * =========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['host_app_action'], $_POST['application_id'], $_POST['csrf_token'])) {

    if (!hash_equals($adminCsrf, $_POST['csrf_token'])) {
        header('Location: /webprogg/admin/admin.php');
        exit();
    }

    $applicationId = (int) $_POST['application_id'];
    $action        = $_POST['host_app_action'];

    if ($applicationId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, status FROM host_applications WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $applicationId]);
        $application = $stmt->fetch();

        if ($application) {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';

            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE host_applications SET status = :status, updated_at = NOW() WHERE id = :id"
                )->execute(['status' => $newStatus, 'id' => $applicationId]);

                $pdo->prepare(
                    "UPDATE users SET is_host = :v WHERE id = :user_id"
                )->execute([
                    'v'       => $action === 'approve' ? 1 : 0,
                    'user_id' => $application['user_id'],
                ]);

                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
            }

            if ($action === 'approve') {
                admin_notify_user(
                    $pdo, (int) $application['user_id'],
                    'Congratulations! Your host application was approved. You can now list your space on RoomHive.',
                    '/webprogg/host/hostprofile.php'
                );
            } else {
                admin_notify_user(
                    $pdo, (int) $application['user_id'],
                    'Your host application was not approved this time. You can reapply anytime from the Become a Host page.',
                    '/webprogg/host/becomeahost.php'
                );
            }
        }
    }

    header('Location: /webprogg/admin/admin.php');
    exit();
}

/* ---------- Sidebar navigation (Hive Club added) ---------- */
 $navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'active' => true, 'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'href' => '/webprogg/admin/adminusers.php'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '/webprogg/admin/adminbookings.php'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '/webprogg/admin/adminlistings.php'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'href' => '/webprogg/admin/listingapplication.php'],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '/webprogg/admin/adminpayouts.php'],
    ['label' => 'Hive Club',            'icon' => 'tag',        'href' => '/webprogg/admin/adminhiveclub.php'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '/webprogg/admin/adminreviews.php'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '/webprogg/admin/adminmessages.php'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '/webprogg/admin/adminreports.php'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '/webprogg/admin/adminsettings.php'],
];

/* =========================================================
   TOP STAT CARDS
   ========================================================= */
 $totalBookings  = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

 $totalRevenue   = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE status IN ('confirmed','completed')"
)->fetchColumn();

 $activeListings = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();
 $totalUsers     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
 $avgRatingRow   = $pdo->query("SELECT AVG(rating) FROM reviews")->fetchColumn();
 $avgRating      = $avgRatingRow !== null ? round((float) $avgRatingRow, 1) : null;
 $totalReviews   = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

 $stats = [
    ['label' => 'Total Bookings',  'value' => number_format($totalBookings),               'delta' => '', 'up' => true, 'icon' => 'calendar'],
    ['label' => 'Total Revenue',   'value' => '₱' . number_format($totalRevenue),           'delta' => '', 'up' => true, 'icon' => 'wallet'],
    ['label' => 'Active Listings', 'value' => number_format($activeListings),               'delta' => '', 'up' => true, 'icon' => 'listing'],
    ['label' => 'Total Users',     'value' => number_format($totalUsers),                   'delta' => '', 'up' => true, 'icon' => 'users'],
    ['label' => 'Average Rating',  'value' => ($avgRating !== null ? $avgRating : '—') . ' / 5', 'delta' => '', 'up' => true, 'icon' => 'star'],
];
 $statCaptions = [
    $totalBookings > 0  ? 'All-time bookings'           : 'No bookings yet',
    $totalRevenue > 0   ? 'From confirmed & completed'  : 'No revenue yet',
    $activeListings > 0 ? 'Currently live on the site'  : 'No listings yet',
    $totalUsers > 0     ? 'Registered accounts'         : 'No users yet',
    $totalReviews > 0   ? "From {$totalReviews} reviews" : 'No ratings yet',
];

/* =========================================================
   BOOKINGS BY STATUS — DYNAMIC (native donut)
   ========================================================= */
 $statusCountsRaw = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

 $totalBookingsForDonut = array_sum($statusCountsRaw);

 $statusColorMap = [
    'confirmed' => '#2FA84F',
    'completed' => '#2F7DE1',
    'cancelled' => '#E14B4B',
    'pending'   => '#F5A623',
    'rejected'  => '#B03A3A',
    'failed'    => '#B03A3A',
    'refunded'  => '#9B6BC3',
];
 $fallbackPalette = ['#8B93A6', '#5BC0BE', '#E58BB1', '#C7A252', '#7A8CE8', '#9B6BC3'];

 $statusBreakdown = [];
 $fbIdx = 0;
foreach ($statusCountsRaw as $rawStatus => $count) {
    $key   = strtolower((string) $rawStatus);
    $color = $statusColorMap[$key] ?? $fallbackPalette[$fbIdx++ % count($fallbackPalette)];
    $count = (int) $count;
    $statusBreakdown[] = [
        'label' => ucfirst($key),
        'value' => $count,
        'pct'   => $totalBookingsForDonut > 0 ? round(($count / $totalBookingsForDonut) * 100) . '%' : '0%',
        'color' => $color,
    ];
}

/* =========================================================
   RECENT HOST APPLICATIONS (most recent 4)
   ========================================================= */
 $hostApplications = array_map(function ($row) {
    $initialsAvatar = 'https://ui-avatars.com/api/?background=EDA423&color=fff&bold=true&name=' . urlencode($row['full_name']);
    return [
        'id'         => (int) $row['id'],
        'name'       => $row['full_name'],
        'city'       => $row['location'],
        'status'     => ucfirst($row['status']),
        'raw_status' => $row['status'],
        'img'        => resolve_photo($row['avatar_path'], $initialsAvatar),
    ];
}, $pdo->query(
    "SELECT ha.id, ha.full_name, ha.location, ha.status,
            u.avatar_path
     FROM host_applications ha
     LEFT JOIN users u ON u.id = ha.user_id
     ORDER BY ha.created_at DESC
     LIMIT 4"
)->fetchAll());

/* =========================================================
   TOP PERFORMING LISTINGS (by revenue, top 4)
   ========================================================= */
 $topListings = array_map(function ($row) {
    return [
        'name'     => $row['title'],
        'city'     => $row['location'],
        'img'      => resolve_photo($row['cover_photo']),
        'revenue'  => '₱' . number_format((float) $row['revenue']),
        'bookings' => (int) $row['booking_count'] . ' bookings',
    ];
}, $pdo->query(
    "SELECT l.title, l.location,
            p.photo_path AS cover_photo,
            COUNT(b.id) AS booking_count,
            COALESCE(SUM(CASE WHEN b.status IN ('confirmed','completed') THEN b.amount_paid ELSE 0 END), 0) AS revenue
     FROM listings l
     LEFT JOIN bookings b ON b.listing_id = l.id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     GROUP BY l.id, l.title, l.location, p.photo_path
     ORDER BY revenue DESC, booking_count DESC
     LIMIT 4"
)->fetchAll());

/* =========================================================
   RECENT BOOKINGS (most recent 4)
   ========================================================= */
 $recentBookings = array_map(function ($row) {
    return [
        'name'   => $row['title'],
        'city'   => $row['location'],
        'img'    => resolve_photo($row['cover_photo']),
        'date'   => date('M j, Y', strtotime($row['created_at'])),
        'amount' => '₱' . number_format((float) $row['amount_paid']),
        'status' => ucfirst($row['status']),
    ];
}, $pdo->query(
    "SELECT l.title, l.location, p.photo_path AS cover_photo, b.created_at, b.amount_paid, b.status
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     ORDER BY b.created_at DESC
     LIMIT 4"
)->fetchAll());

/* =========================================================
   PLATFORM SUMMARY (this calendar month)
   ========================================================= */
 $monthStart = date('Y-m-01 00:00:00');

 $stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE status IN ('confirmed','completed') AND created_at >= :start"
);
 $stmt->execute(['start' => $monthStart]);
 $payoutsThisMonth = (float) $stmt->fetchColumn();

 $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= :start");
 $stmt->execute(['start' => $monthStart]);
 $newUsersThisMonth = (int) $stmt->fetchColumn();

 $stmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE created_at >= :start");
 $stmt->execute(['start' => $monthStart]);
 $newListingsThisMonth = (int) $stmt->fetchColumn();

 $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversations WHERE created_at >= :start");
 $stmt->execute(['start' => $monthStart]);
 $newConversationsThisMonth = (int) $stmt->fetchColumn();

 $platformSummary = [
    ['label' => 'Payouts This Month', 'value' => '₱' . number_format($payoutsThisMonth), 'icon' => 'wallet'],
    ['label' => 'New Users',          'value' => number_format($newUsersThisMonth),       'icon' => 'user-add'],
    ['label' => 'New Listings',       'value' => number_format($newListingsThisMonth),    'icon' => 'listing'],
    ['label' => 'New Conversations',  'value' => number_format($newConversationsThisMonth), 'icon' => 'message'],
];

/* ---------- Notifications: pending applications ---------- */
 $pendingHostApps = (int) $pdo->query("SELECT COUNT(*) FROM host_applications WHERE status = 'pending'")->fetchColumn();
 $pendingListings = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
 $notificationCount = $pendingHostApps + $pendingListings;

/* =========================================================
   CHART DATA — last 7 days of bookings / revenue
   ========================================================= */
 $chartLabels   = [];
 $bookingSeries = [];
 $revenueSeries = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M j', strtotime($day));

    $stmt = $pdo->prepare(
        "SELECT COUNT(*), COALESCE(SUM(amount_paid),0)
         FROM bookings WHERE DATE(created_at) = :day"
    );
    $stmt->execute(['day' => $day]);
    [$dayCount, $dayRevenue] = $stmt->fetch(PDO::FETCH_NUM);

    $bookingSeries[] = (int) $dayCount;
    $revenueSeries[] = (float) $dayRevenue;
}

 $weekBookingTotal = array_sum($bookingSeries);
 $weekRevenueTotal = array_sum($revenueSeries);

/* =========================================================
   6-MONTH USER & LISTING GROWTH
   ========================================================= */
 $growthMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -{$i} months");
    $growthMonths[date('Y-m', $ts)] = ['label' => date('M Y', $ts), 'users' => 0, 'listings' => 0];
}
 $growthStart = date('Y-m-01 00:00:00', strtotime('-5 months'));

 $gUs = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM users WHERE created_at >= :s GROUP BY ym");
 $gUs->execute(['s' => $growthStart]);
foreach ($gUs->fetchAll() as $r) {
    if (isset($growthMonths[$r['ym']])) $growthMonths[$r['ym']]['users'] = (int) $r['c'];
}

 $gLs = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM listings WHERE created_at >= :s GROUP BY ym");
 $gLs->execute(['s' => $growthStart]);
foreach ($gLs->fetchAll() as $r) {
    if (isset($growthMonths[$r['ym']])) $growthMonths[$r['ym']]['listings'] = (int) $r['c'];
}

 $growthLabels   = array_column(array_values($growthMonths), 'label');
 $growthUsers    = array_column(array_values($growthMonths), 'users');
 $growthListings = array_column(array_values($growthMonths), 'listings');

/* =========================================================
   LISTINGS BY STATUS — DYNAMIC (native donut)
   ========================================================= */
 $listingStatusRaw = $pdo->query(
    "SELECT status, COUNT(*) c FROM listings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

 $totalListingsForDonut = array_sum($listingStatusRaw);

 $listingColorMap = [
    'approved' => '#2FA84F',
    'pending'  => '#F5A623',
    'rejected' => '#E14B4B',
    'unlisted' => '#8B93A6',
    'draft'    => '#C7CDD9',
];
 $listingBreakdown = [];
 $lfIdx = 0;
foreach ($listingStatusRaw as $rawStatus => $count) {
    $key   = strtolower((string) $rawStatus);
    $color = $listingColorMap[$key] ?? $fallbackPalette[$lfIdx++ % count($fallbackPalette)];
    $listingBreakdown[] = [
        'label' => ucfirst($key),
        'value' => (int) $count,
        'color' => $color,
    ];
}

/* =========================================================
   RATING DISTRIBUTION (1-5 stars)
   ========================================================= */
 $ratingDist = $pdo->query(
    "SELECT CAST(ROUND(rating) AS SIGNED) star, COUNT(*) c FROM reviews GROUP BY star"
)->fetchAll(PDO::FETCH_KEY_PAIR);

 $ratingLabels = ['5★', '4★', '3★', '2★', '1★'];
 $ratingSeries = [
    (int) ($ratingDist[5] ?? 0),
    (int) ($ratingDist[4] ?? 0),
    (int) ($ratingDist[3] ?? 0),
    (int) ($ratingDist[2] ?? 0),
    (int) ($ratingDist[1] ?? 0),
];

/* =========================================================
   TOP LOCATIONS BY BOOKINGS (top 6)
   ========================================================= */
 $topLocations = $pdo->query(
    "SELECT l.location, COUNT(b.id) c
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     GROUP BY l.location
     ORDER BY c DESC
     LIMIT 6"
)->fetchAll(PDO::FETCH_KEY_PAIR);

 $locLabels = array_keys($topLocations);
 $locSeries = array_map('intval', array_values($topLocations));

/* =========================================================
   NATIVE CHART PREP — pre-build all SVG/CSS values in PHP
========================================================= */

/* --- 1) Bookings 7-day line/area (with per-point numbers) --- */
 $bookMax = max(1, max($bookingSeries ?: [0]));
 $bookMid = (int) round($bookMax / 2);
 $bookPts = [];
 $bookDots = [];
foreach ($bookingSeries as $i => $v) {
    $x = 8 + ($i / max(1, count($bookingSeries) - 1)) * 584;
    $y = 18 + (1 - ($v / $bookMax)) * 132;
    $bookPts[] = round($x, 1) . ',' . round($y, 1);
    $bookDots[] = [
        'x'  => round($x, 1),
        'y'  => round($y, 1),
        'ly' => round(max(11, $y - 9), 1), /* label sits above the dot */
        'v'  => (int) $v,
        'label' => $chartLabels[$i],
    ];
}
 $bookPointsStr = implode(' ', $bookPts);
 $bookAreaPath  = 'M8,150 L' . implode(' L', $bookPts) . ' L592,150 Z';

/* --- 2) Revenue 7-day vertical bars --- */
 $revMax = max(0.01, max($revenueSeries ?: [0]));
 $admMoneyShort = function ($v) {
    $v = (float) $v;
    if ($v >= 1000000) { return number_format($v / 1000000, 1) . 'M'; }
    if ($v >= 1000)    { return number_format($v / 1000, 1) . 'K'; }
    return number_format($v, 0);
 };
 $revBars = [];
foreach ($revenueSeries as $i => $v) {
    $revBars[] = [
        'label' => $chartLabels[$i],
        'v'     => (float) $v,
        'short' => '₱' . $admMoneyShort($v),
        'pct'   => $v > 0 ? max(2, (int) round(($v / $revMax) * 100)) : 2,
    ];
}

/* --- 3/5) Donut segment builder (status + listings) --- */
if (!function_exists('admin_donut_segments')) {
    function admin_donut_segments(array $items, $total) {
        $r = 40;
        $C = 2 * M_PI * $r;
        $acc = 0.0;
        $out = [];
        foreach ($items as $it) {
            $pct = $total > 0 ? ((int) $it['value'] / $total) * 100 : 0;
            $seg = ($pct / 100) * $C;
            if ($seg <= 0.01) { continue; }
            $out[] = [
                'color'  => $it['color'],
                'label'  => $it['label'],
                'value'  => (int) $it['value'],
                'pctStr' => $total > 0 ? round(($it['value'] / $total) * 100) . '%' : '0%',
                'dash'   => round($seg, 2) . ' ' . round($C - $seg, 2),
                'offset' => round(-$acc, 2),
                'C'      => round($C, 2),
            ];
            $acc += $seg;
        }
        return $out;
    }
}
 $statusDonut  = admin_donut_segments($statusBreakdown, $totalBookingsForDonut);
 $listingDonut = admin_donut_segments($listingBreakdown, $totalListingsForDonut);

/* --- 4) Growth grouped bars --- */
 $growthMax = max(1, max(array_merge($growthUsers ?: [0], $growthListings ?: [0])));
 $growthRows = [];
foreach ($growthLabels as $i => $lbl) {
    $growthRows[] = [
        'label'        => $lbl,
        'users'        => (int) $growthUsers[$i],
        'users_pct'    => $growthUsers[$i] > 0    ? max(2, (int) round(($growthUsers[$i] / $growthMax) * 100))       : 2,
        'listings'     => (int) $growthListings[$i],
        'listings_pct' => $growthListings[$i] > 0 ? max(2, (int) round(($growthListings[$i] / $growthMax) * 100)) : 2,
    ];
}

/* --- 6) Rating horizontal bars --- */
 $ratingMax = max(1, max($ratingSeries ?: [0]));
 $ratingBars = [];
foreach ($ratingLabels as $i => $lbl) {
    $ratingBars[] = [
        'label' => $lbl,
        'v'     => (int) $ratingSeries[$i],
        'pct'   => $ratingSeries[$i] > 0 ? max(3, (int) round(($ratingSeries[$i] / $ratingMax) * 100)) : 0,
    ];
}

/* --- 7) Top locations horizontal bars --- */
 $locMax = max(1, max($locSeries ?: [0]));
 $locBars = [];
foreach ($locLabels as $i => $lbl) {
    $locBars[] = [
        'label' => (string) $lbl,
        'v'     => (int) $locSeries[$i],
        'pct'   => $locSeries[$i] > 0 ? max(3, (int) round(($locSeries[$i] / $locMax) * 100)) : 0,
    ];
}

/* ---------- Inline icon helper ---------- */
function icon($name, $class = '') {
    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 8.5a3 3 0 1 1 0-6"/><path d="M15 14.5c3 0 6.5 1.4 6.5 5.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'listing' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9 20v-5h6v5"/>',
        'clipboard' => '<rect x="5" y="4.5" width="14" height="17" rx="2"/><path d="M9 4.5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8v.7"/><path d="M9 11.5h6M9 15.5h6M9 7.5h1"/>',
        'user-check' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="m16 11 2 2 3.5-3.5"/>',
        'wallet' => '<rect x="2.5" y="6.5" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2"/>',
        'star' => '<path d="M12 3.5l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.9l-5.2 2.6 1-5.75-4.2-4.1 5.8-.85z"/>',
        'message' => '<path d="M3.5 12a8.2 8.2 0 1 1 3.3 6.5L3 20l1.3-3.8A8.1 8.1 0 0 1 3.5 12Z"/>',
        'bar-chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.8 1.8 0 0 0 .36 2l.04.04a2.2 2.2 0 1 1-3.1 3.1l-.04-.04a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.1 1.65V20a2.2 2.2 0 1 1-4.4 0v-.06a1.8 1.8 0 0 0-1.18-1.65 1.8 1.8 0 0 0-2 .36l-.04.04a2.2 2.2 0 1 1-3.1-3.1l.04-.04a1.8 1.8 0 0 0 .36-2 1.8 1.8 0 0 0-1.65-1.1H4a2.2 2.2 0 1 1 0-4.4h.06a1.8 1.8 0 0 0 1.65-1.18 1.8 1.8 0 0 0-.36-2l-.04-.04a2.2 2.2 0 1 1 3.1-3.1l.04.04a1.8 1.8 0 0 0 2 .36H10.5a1.8 1.8 0 0 0 1.1-1.65V4a2.2 2.2 0 1 1 4.4 0v.06a1.8 1.8 0 0 0 1.1 1.65 1.8 1.8 0 0 0 2-.36l-.04-.04a2.2 2.2 0 1 1 3.1 3.1l-.04.04a1.8 1.8 0 0 0-.36 2v.09a1.8 1.8 0 0 0 1.65 1.1H20a2.2 2.2 0 1 1 0 4.4h-.06a1.8 1.8 0 0 0-1.65 1.1Z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 6.5-2.5 8-2.5 8h17S18 14.5 18 8Z"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'headphones' => '<path d="M3 13.5v-1.7a9 9 0 0 1 18 0v1.7"/><rect x="3" y="13.5" width="5" height="6.5" rx="1.6"/><rect x="16" y="13.5" width="5" height="6.5" rx="1.6"/>',
        'user-add' => '<circle cx="9" cy="8" r="3.2"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M18 8v5M15.5 10.5h5"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'inbox' => '<path d="M3 12h4.5l1.5 3h6l1.5-3H21"/><path d="M5.5 5.5h13l2.5 6.5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}

function statusBadgeClass($status) {
    $map = [
        'Pending'   => 'badge-pending',
        'Approved'  => 'badge-approved',
        'Confirmed' => 'badge-confirmed',
        'Cancelled' => 'badge-cancelled',
        'Completed' => 'badge-completed',
        'Rejected'  => 'badge-rejected',
    ];
    return $map[$status] ?? '';
}

function emptyState($text) {
    echo '<div class="empty-state">';
    echo '<div class="empty-icon">'.icon('inbox').'</div>';
    echo '<p>'.htmlspecialchars($text).'</p>';
    echo '</div>';
}

function listImage($src, $alt, $class = '') {
    $safeSrc = htmlspecialchars($src);
    $safeAlt = htmlspecialchars($alt);
    return '<img src="' . $safeSrc . '" alt="' . $safeAlt . '"'
        . ($class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '')
        . ' onerror="this.onerror=null;this.src=\'/webprogg/images/ListingPlaceholder.png\';">';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RoomHive Admin — Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/admin.css">
<script>document.documentElement.classList.add("js");</script>
<style>
    .sidebar .nav { padding-top: 10px; }
    .admin-chip { position: relative; cursor: pointer; }
    .admin-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 200px;
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(20, 20, 43, 0.12);
        padding: 8px;
        z-index: 50;
    }
    .admin-chip.open .admin-menu { display: block; }
    .admin-menu-header {
        display: flex;
        flex-direction: column;
        padding: 8px 10px 10px;
        border-bottom: 1px solid #EEF1F6;
        margin-bottom: 6px;
    }
    .admin-menu-name { font-weight: 600; font-size: 13px; color: #14142B; }
    .admin-menu-email { font-size: 12px; color: #8B93A6; margin-top: 2px; }
    .admin-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13px;
        color: #14142B;
        text-decoration: none;
    }
    .admin-menu-item:hover { background: #F6F7FB; }
    .admin-menu-item .icon { width: 16px; height: 16px; }
    .admin-logout { color: #E14B4B; }

    /* ---- NAV ALIGNMENT: mobile sidebar toggle + topbar shadow ---- */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(20, 20, 43, 0.45);
        z-index: 90;
    }
    @media (max-width: 1000px) {
        .layout.sidebar-open .sidebar-overlay { display: block; }
        .layout.sidebar-open .sidebar {
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
    }
    .topbar.topbar-scrolled { box-shadow: 0 6px 18px rgba(20, 20, 43, 0.08); }

    .host-app-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .host-app-form { margin: 0; }
    .host-app-btn {
        border: none;
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .host-app-approve { background: #E7F7EC; color: #2FA84F; }
    .host-app-approve:hover { background: #2FA84F; color: #fff; }
    .host-app-reject { background: #FCEAEA; color: #E14B4B; }
    .host-app-reject:hover { background: #E14B4B; color: #fff; }

    .people-list img,
    .listing-list img,
    .booking-list img {
        background: #f6f4ee;
        object-fit: cover;
    }

    /* =====================================================
       LAYOUT — nested chart cluster inside Row 2's left
       column (Revenue -> Summary -> 4 chart panels).
    ====================================================== */
    .stack .inner-grid { margin-top: 4px; }
    .stack .inner-grid .panel {
        box-shadow: none;
        border: 1px solid #EEF1F6;
        background: #FBFCFE;
    }

    /* =====================================================
       NATIVE CHARTS — no libraries, best-fit per dataset
    ====================================================== */

    /* ---- shared animation gate ---- */
    .js [data-anim-chart]:not(.on) .al-area { opacity: 0; }
    .js [data-anim-chart]:not(.on) .al-line { stroke-dashoffset: 1; }
    .js [data-anim-chart]:not(.on) .al-val  { opacity: 0; }
    .js [data-anim-chart]:not(.on) .vbar    { height: 0 !important; }
    .js [data-anim-chart]:not(.on) .hbar    { width: 0 !important; }
    .js [data-anim-chart]:not(.on) .donut-seg { stroke-dasharray: 0 var(--C); }
    .js [data-anim-chart]:not(.on) .g-bar   { height: 0 !important; }

    /* ---- 1) SVG area/line (Bookings 7d, with numbers) ---- */
    .adm-linechart svg { width: 100%; height: auto; display: block; }
    .al-line {
        fill: none;
        stroke: #2F7DE1;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 1;
        stroke-dashoffset: 0;
        transition: stroke-dashoffset 1.4s ease 0.1s;
    }
    .al-area {
        fill: rgba(47, 125, 225, 0.12);
        opacity: 1;
        transition: opacity 0.8s ease 0.7s;
    }
    .al-dot {
        fill: #fff;
        stroke: #2F7DE1;
        stroke-width: 2;
        cursor: pointer;
        transition: r 0.12s ease;
    }
    .al-dot:hover { r: 4.5; }
    .al-grid { stroke: #EEF1F6; stroke-width: 1; }
    .al-txt { font-size: 10px; fill: #8B93A6; font-family: "Inter", sans-serif; }

    /* NEW — per-point value labels */
    .al-val {
        font-size: 10.5px;
        font-weight: 800;
        fill: #2F7DE1;
        font-family: "Inter", sans-serif;
        opacity: 1;
        transition: opacity 0.6s ease 1s;
        pointer-events: none;
    }

    .al-xlabels {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        font-size: 11px;
        color: #8B93A6;
    }

    /* ---- 2/5) SVG donuts ---- */
    .adm-donut {
        position: relative;
        width: 180px;
        height: 180px;
        margin: 12px auto;
    }
    .adm-donut svg { width: 100%; height: 100%; display: block; }
    .donut-track { fill: none; stroke: #F0F1F6; stroke-width: 14; }
    .donut-seg {
        fill: none;
        stroke-width: 14;
        stroke-dasharray: var(--dash);
        transition: stroke-dasharray 0.9s ease;
    }
    .donut-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    .donut-total { font-size: 22px; font-weight: 800; color: #14142B; line-height: 1; }
    .donut-label { font-size: 11px; color: #8B93A6; margin-top: 3px; }

    /* ---- 3) Vertical bars (Revenue 7d) ---- */
    .adm-vbars {
        display: flex;
        align-items: flex-end;
        gap: 10px;

        height: 190px;
        padding-top: 22px;
    }
    .vbar-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;

        height: 100%;
    }
    .vbar-track {
        flex: 1;
        width: 100%;
        max-width: 46px;

        display: flex;
        align-items: flex-end;

        background: #F6F7FB;
        border-radius: 8px;
        overflow: hidden;
    }
    .vbar {
        width: 100%;
        height: var(--h, 2%);

        background: linear-gradient(180deg, #43bd67, #2FA84F);
        border-radius: 8px 8px 0 0;

        transition: height 0.6s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .vbar-val { font-size: 10.5px; font-weight: 700; color: #14142B; white-space: nowrap; }
    .vbar-lbl { font-size: 11px; color: #8B93A6; white-space: nowrap; }

    /* ---- 4) Grouped bars (Growth, 2 series) ---- */
    .growth-legend {
        display: flex;
        gap: 16px;
        margin-bottom: 12px;
    }
    .growth-legend span.gli {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #5B6172;
    }
    .gli-dot { width: 10px; height: 10px; border-radius: 50%; }
    .adm-growth {
        display: flex;
        align-items: flex-end;
        gap: 12px;

        height: 170px;
    }
    .growth-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;

        height: 100%;
    }
    .growth-pair {
        flex: 1;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 4px;

        width: 100%;
    }
    .g-track {
        width: 16px;
        height: 100%;

        display: flex;
        align-items: flex-end;

        background: #F6F7FB;
        border-radius: 6px;
        overflow: hidden;
    }
    .g-bar {
        width: 100%;
        height: var(--h, 2%);

        border-radius: 6px 6px 0 0;

        transition: height 0.6s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .g-user    { background: linear-gradient(180deg, #4a86e8, #2F7DE1); }
    .g-listing { background: linear-gradient(180deg, #43bd67, #2FA84F); }
    .growth-lbl { font-size: 10.5px; color: #8B93A6; white-space: nowrap; }
    .growth-nums {
        display: flex;
        gap: 8px;
        font-size: 9.5px;
        color: #5B6172;
    }

    /* ---- 6/7) Horizontal bars (Ratings / Locations) ---- */
    .adm-hbars {
        display: flex;
        flex-direction: column;
        gap: 12px;

        padding: 6px 0;
    }
    .hbar-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .hbar-label {
        width: 34px;
        flex-shrink: 0;

        font-size: 12px;
        font-weight: 700;
        color: #5B6172;
        text-align: right;
    }
    .hbar-row.loc .hbar-label {
        width: 130px;

        font-weight: 600;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: left;
    }
    .hbar-track {
        flex: 1;
        height: 16px;

        background: #F6F7FB;
        border-radius: 999px;
        overflow: hidden;
    }
    .hbar {
        height: 100%;
        width: var(--w, 0%);

        border-radius: 999px;

        transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .hbar-gold { background: linear-gradient(90deg, #f6c04e, #eda423); }
    .hbar-blue { background: linear-gradient(90deg, #4a86e8, #2F7DE1); }
    .hbar-val {
        width: 40px;
        flex-shrink: 0;

        font-size: 12px;
        font-weight: 700;
        color: #14142B;
        text-align: right;
    }

    @media (prefers-reduced-motion: reduce) {
        .al-line,
        .al-area,
        .al-val,
        .vbar,
        .hbar,
        .g-bar,
        .donut-seg {
            transition: none !important;
        }
        .js [data-anim-chart]:not(.on) .al-area { opacity: 1; }
        .js [data-anim-chart]:not(.on) .al-line { stroke-dashoffset: 0; }
        .js [data-anim-chart]:not(.on) .al-val  { opacity: 1; }
        .js [data-anim-chart]:not(.on) .vbar    { height: var(--h) !important; }
        .js [data-anim-chart]:not(.on) .hbar    { width: var(--w) !important; }
        .js [data-anim-chart]:not(.on) .g-bar   { height: var(--h) !important; }
        .js [data-anim-chart]:not(.on) .donut-seg { stroke-dasharray: var(--dash); }
    }
</style>
</head>
<body>

<div class="layout" id="adminLayout">

    <!-- Mobile overlay (NAV ALIGNMENT) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>" class="nav-item <?= !empty($item['active']) ? 'active' : '' ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- ============ MAIN ============ -->
    <div class="main">

        <header class="topbar" id="adminTopbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="Toggle menu"><?= icon('menu') ?></button>

            <div class="search-box">
                <?= icon('search') ?>
                <input type="text" placeholder="Search users, bookings, properties...">
            </div>

            <div class="topbar-right">
                <button class="icon-btn bell-btn" aria-label="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($notificationCount > 0): ?>
                        <span class="bell-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="admin-chip" id="adminChip">
                    <div class="admin-avatar admin-avatar-fallback">
                        <?= htmlspecialchars(strtoupper(substr($adminName, 0, 1))) ?>
                    </div>
                    <div class="admin-info">
                        <span class="admin-name"><?= htmlspecialchars($adminName) ?></span>
                        <span class="admin-role">Administrator</span>
                    </div>
                    <?= icon('chevron-down', 'chevron') ?>

                    <div class="admin-menu" id="adminMenu">
                        <div class="admin-menu-header">
                            <span class="admin-menu-name"><?= htmlspecialchars($adminName) ?></span>
                            <?php if ($adminEmail): ?>
                                <span class="admin-menu-email"><?= htmlspecialchars($adminEmail) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="/webprogg/auth/logout.php" class="admin-menu-item admin-logout">
                            <?= icon('lock') ?>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-heading">
                <h1>Dashboard</h1>
                <p>Overview of your platform's performance and key activities.</p>
            </div>

            <!-- Stat cards -->
            <div class="stat-grid">
                <?php foreach ($stats as $i => $stat): ?>
                    <div class="stat-card">
                        <div class="stat-icon"><?= icon($stat['icon']) ?></div>
                        <div class="stat-body">
                            <span class="stat-label"><?= htmlspecialchars($stat['label']) ?></span>
                            <div class="stat-value-row">
                                <span class="stat-value"><?= htmlspecialchars($stat['value']) ?></span>
                                <?php if ($stat['delta']): ?>
                                    <span class="stat-delta <?= $stat['up'] ? 'up' : 'down' ?>">
                                        <?= icon('arrow-up', 'delta-arrow') ?><?= htmlspecialchars($stat['delta']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="stat-caption"><?= htmlspecialchars($statCaptions[$i]) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Row 1 -->
            <div class="grid-3">
                <div class="panel span-2">
                    <div class="panel-header">
                        <h2>Bookings Overview</h2>
                        <select class="period-select"><option>Last 7 Days</option></select>
                    </div>

                    <!-- NATIVE: SVG area/line with per-day NUMBERS -->
                    <div class="adm-linechart" data-anim-chart>
                        <svg viewBox="0 0 600 160" role="img" aria-label="Bookings for the last 7 days">
                            <line class="al-grid" x1="8" y1="18"  x2="592" y2="18"></line>
                            <line class="al-grid" x1="8" y1="84"  x2="592" y2="84"></line>
                            <line class="al-grid" x1="8" y1="150" x2="592" y2="150"></line>

                            <text class="al-txt" x="2" y="13"><?= (int) $bookMax ?></text>
                            <text class="al-txt" x="2" y="88"><?= (int) $bookMid ?></text>
                            <text class="al-txt" x="2" y="147">0</text>

                            <path class="al-area" d="<?= htmlspecialchars($bookAreaPath) ?>"></path>

                            <polyline
                                class="al-line"
                                pathLength="1"
                                points="<?= htmlspecialchars($bookPointsStr) ?>"
                            ></polyline>

                            <?php foreach ($bookDots as $d): ?>
                            <!-- NEW: the number for each day -->
                            <text
                                class="al-val"
                                x="<?= $d['x'] ?>"
                                y="<?= $d['ly'] ?>"
                                text-anchor="middle"
                            ><?= (int) $d['v'] ?></text>

                            <circle class="al-dot" cx="<?= $d['x'] ?>" cy="<?= $d['y'] ?>" r="3">
                                <title><?= (int) $d['v'] ?> booking<?= $d['v'] === 1 ? '' : 's' ?> — <?= htmlspecialchars($d['label']) ?></title>
                            </circle>
                            <?php endforeach; ?>
                        </svg>

                        <div class="al-xlabels">
                            <?php foreach ($chartLabels as $lbl): ?>
                                <span><?= htmlspecialchars($lbl) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Bookings by Status</h2>
                        <select class="period-select"><option>All Time</option></select>
                    </div>
                    <?php if ($totalBookingsForDonut === 0): ?>
                        <?php emptyState('No bookings yet — the chart will appear once bookings come in.'); ?>
                    <?php else: ?>
                        <!-- NATIVE: SVG donut (parts of a whole) -->
                        <div class="adm-donut" data-anim-chart>
                            <svg viewBox="0 0 100 100" role="img" aria-label="Bookings by status">
                                <g transform="rotate(-90 50 50)">
                                    <circle class="donut-track" cx="50" cy="50" r="40"></circle>
                                    <?php foreach ($statusDonut as $seg): ?>
                                    <circle
                                        class="donut-seg"
                                        cx="50" cy="50" r="40"
                                        style="--dash: <?= htmlspecialchars($seg['dash']) ?>; --C: <?= htmlspecialchars($seg['C']) ?>; stroke: <?= htmlspecialchars($seg['color']) ?>; stroke-dashoffset: <?= htmlspecialchars($seg['offset']); ?>;"
                                    >
                                        <title><?= htmlspecialchars($seg['label']) ?>: <?= (int) $seg['value'] ?> (<?= htmlspecialchars($seg['pctStr']) ?>)</title>
                                    </circle>
                                    <?php endforeach; ?>
                                </g>
                            </svg>
                            <div class="donut-center">
                                <span class="donut-total"><?= $totalBookingsForDonut ?></span>
                                <span class="donut-label">Total</span>
                            </div>
                        </div>
                        <ul class="legend-list">
                            <?php foreach ($statusBreakdown as $s): ?>
                                <li>
                                    <span class="dot" style="background:<?= $s['color'] ?>"></span>
                                    <span class="legend-label"><?= htmlspecialchars($s['label']) ?></span>
                                    <span class="legend-value"><?= $s['value'] ?> (<?= $s['pct'] ?>)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- =====================================================
                 Row 2 — LEFT column packs all chart panels:
                 Revenue -> Platform Summary -> [Growth | Listings
                 by Status] -> [Rating Distribution | Top Locations].
                 The old standalone bottom row is gone.
            ====================================================== -->
            <div class="grid-3">
                <div class="panel span-2 stack">
                    <div>
                        <div class="panel-header">
                            <h2>Revenue Overview</h2>
                            <select class="period-select"><option>Last 7 Days</option></select>
                        </div>
                        <div class="revenue-total-row">
                            <span class="revenue-total">₱<?= number_format($weekRevenueTotal) ?></span>
                            <span class="stat-caption"><?= $weekRevenueTotal > 0 ? 'Paid in the last 7 days (all bookings)' : 'No bookings in the last 7 days' ?></span>
                        </div>

                        <!-- NATIVE: vertical bars (magnitude per day) -->
                        <div class="adm-vbars" data-anim-chart>
                            <?php foreach ($revBars as $b): ?>
                            <div class="vbar-col" title="<?= htmlspecialchars($b['label']) ?>: ₱<?= number_format($b['v'], 2) ?>">
                                <span class="vbar-val"><?= htmlspecialchars($b['short']) ?></span>
                                <div class="vbar-track">
                                    <div class="vbar" style="--h: <?= (int) $b['pct'] ?>%;"></div>
                                </div>
                                <span class="vbar-lbl"><?= htmlspecialchars($b['label']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="platform-summary">
                        <h2 class="summary-heading">Platform Summary</h2>
                        <div class="summary-grid">
                            <?php foreach ($platformSummary as $p): ?>
                                <div class="summary-item">
                                    <div class="summary-icon"><?= icon($p['icon']) ?></div>
                                    <div>
                                        <span class="summary-label"><?= htmlspecialchars($p['label']) ?></span>
                                        <span class="summary-value"><?= htmlspecialchars($p['value']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ===== INNER CHART CLUSTER (moved up) ===== -->
                    <div class="inner-grid grid-3">

                        <!-- NATIVE: grouped bars (2-series comparison) -->
                        <div class="panel span-2">
                            <div class="panel-header">
                                <h2>User &amp; Listing Growth</h2>
                                <select class="period-select"><option>Last 6 Months</option></select>
                            </div>

                            <div class="growth-legend">
                                <span class="gli"><span class="gli-dot" style="background:#2F7DE1;"></span> New Users</span>
                                <span class="gli"><span class="gli-dot" style="background:#2FA84F;"></span> New Listings</span>
                            </div>

                            <div class="adm-growth" data-anim-chart>
                                <?php foreach ($growthRows as $gr): ?>
                                <div class="growth-col" title="<?= htmlspecialchars($gr['label']) ?>: <?= (int) $gr['users'] ?> users, <?= (int) $gr['listings'] ?> listings">
                                    <div class="growth-pair">
                                        <div class="g-track">
                                            <div class="g-bar g-user" style="--h: <?= (int) $gr['users_pct'] ?>%;"></div>
                                        </div>
                                        <div class="g-track">
                                            <div class="g-bar g-listing" style="--h: <?= (int) $gr['listings_pct'] ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="growth-nums">
                                        <span><?= (int) $gr['users'] ?></span>
                                        <span><?= (int) $gr['listings'] ?></span>
                                    </div>
                                    <span class="growth-lbl"><?= htmlspecialchars($gr['label']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header">
                                <h2>Listings by Status</h2>
                                <select class="period-select"><option>All Time</option></select>
                            </div>
                            <?php if ($totalListingsForDonut === 0): ?>
                                <?php emptyState('No listings yet.'); ?>
                            <?php else: ?>
                                <!-- NATIVE: SVG donut -->
                                <div class="adm-donut" data-anim-chart>
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Listings by status">
                                        <g transform="rotate(-90 50 50)">
                                            <circle class="donut-track" cx="50" cy="50" r="40"></circle>
                                            <?php foreach ($listingDonut as $seg): ?>
                                            <circle
                                                class="donut-seg"
                                                cx="50" cy="50" r="40"
                                                style="--dash: <?= htmlspecialchars($seg['dash']) ?>; --C: <?= htmlspecialchars($seg['C']) ?>; stroke: <?= htmlspecialchars($seg['color']) ?>; stroke-dashoffset: <?= htmlspecialchars($seg['offset']); ?>;"
                                            >
                                                <title><?= htmlspecialchars($seg['label']) ?>: <?= (int) $seg['value'] ?> (<?= htmlspecialchars($seg['pctStr']) ?>)</title>
                                            </circle>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                    <div class="donut-center">
                                        <span class="donut-total"><?= $totalListingsForDonut ?></span>
                                        <span class="donut-label">Total</span>
                                    </div>
                                </div>
                                <ul class="legend-list">
                                    <?php foreach ($listingBreakdown as $s): ?>
                                        <li>
                                            <span class="dot" style="background:<?= $s['color'] ?>"></span>
                                            <span class="legend-label"><?= htmlspecialchars($s['label']) ?></span>
                                            <span class="legend-value"><?= $s['value'] ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- ===== MOVED UP: Rating Distribution ===== -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2>Rating Distribution</h2>
                                <select class="period-select"><option>All Time</option></select>
                            </div>
                            <?php if ($totalReviews === 0): ?>
                                <?php emptyState('No reviews yet.'); ?>
                            <?php else: ?>
                                <!-- NATIVE: horizontal bars (ordered scale) -->
                                <div class="adm-hbars" data-anim-chart>
                                    <?php foreach ($ratingBars as $rb): ?>
                                    <div class="hbar-row" title="<?= htmlspecialchars($rb['label']) ?>: <?= (int) $rb['v'] ?> review<?= $rb['v'] === 1 ? '' : 's' ?>">
                                        <span class="hbar-label"><?= htmlspecialchars($rb['label']) ?></span>
                                        <div class="hbar-track">
                                            <div class="hbar hbar-gold" style="--w: <?= (int) $rb['pct'] ?>%;"></div>
                                        </div>
                                        <span class="hbar-val"><?= (int) $rb['v'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ===== MOVED UP: Top Locations ===== -->
                        <div class="panel span-2">
                            <div class="panel-header">
                                <h2>Top Locations by Bookings</h2>
                                <select class="period-select"><option>All Time</option></select>
                            </div>
                            <?php if (empty($locBars)): ?>
                                <?php emptyState('No bookings yet.'); ?>
                            <?php else: ?>
                                <!-- NATIVE: horizontal bars (ranking) -->
                                <div class="adm-hbars" data-anim-chart>
                                    <?php foreach ($locBars as $lb): ?>
                                    <div class="hbar-row loc" title="<?= htmlspecialchars($lb['label']) ?>: <?= (int) $lb['v'] ?> booking<?= $lb['v'] === 1 ? '' : 's' ?>">
                                        <span class="hbar-label"><?= htmlspecialchars($lb['label']) ?></span>
                                        <div class="hbar-track">
                                            <div class="hbar hbar-blue" style="--w: <?= (int) $lb['pct'] ?>%;"></div>
                                        </div>
                                        <span class="hbar-val"><?= (int) $lb['v'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                    <!-- ===== END INNER CHART CLUSTER ===== -->

                </div>

                <div class="col-stack">
                    <div class="panel">
                        <div class="panel-header">
                            <h2>Recent Host Applications</h2>
                            <a href="/webprogg/admin/hostapplication.php" class="view-all">View All</a>
                        </div>
                        <?php if (empty($hostApplications)): ?>
                            <?php emptyState('No host applications yet.'); ?>
                        <?php else: ?>
                            <ul class="people-list">
                                <?php foreach ($hostApplications as $h): ?>
                                    <li>
                                        <?= listImage($h['img'], $h['name']) ?>
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($h['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($h['city']) ?></span>
                                        </div>
                                        <?php if ($h['raw_status'] === 'pending'): ?>
                                            <div class="host-app-actions">
                                                <form method="post" action="/webprogg/admin/admin.php" class="host-app-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrf) ?>">
                                                    <input type="hidden" name="application_id" value="<?= $h['id'] ?>">
                                                    <input type="hidden" name="host_app_action" value="approve">
                                                    <button type="submit" class="host-app-btn host-app-approve" onclick="return confirm('Approve <?= htmlspecialchars(addslashes($h['name'])) ?>\'s host application?');">Approve</button>
                                                </form>
                                                <form method="post" action="/webprogg/admin/admin.php" class="host-app-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrf) ?>">
                                                    <input type="hidden" name="application_id" value="<?= $h['id'] ?>">
                                                    <input type="hidden" name="host_app_action" value="reject">
                                                    <button type="submit" class="host-app-btn host-app-reject" onclick="return confirm('Reject <?= htmlspecialchars(addslashes($h['name'])) ?>\'s host application?');">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge <?= statusBadgeClass($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2>Top Performing Listings</h2>
                            <a href="/webprogg/admin/adminlistings.php" class="view-all">View All</a>
                        </div>
                        <?php if (empty($topListings)): ?>
                            <?php emptyState('No listings published yet.'); ?>
                        <?php else: ?>
                            <ul class="listing-list">
                                <?php foreach ($topListings as $i => $l): ?>
                                    <li>
                                        <span class="rank"><?= $i + 1 ?></span>
                                        <?= listImage($l['img'], $l['name']) ?>
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($l['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($l['city']) ?></span>
                                        </div>
                                        <div class="listing-figures">
                                            <span class="listing-revenue"><?= htmlspecialchars($l['revenue']) ?></span>
                                            <span class="listing-bookings"><?= htmlspecialchars($l['bookings']) ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2>Recent Bookings</h2>
                            <a href="/webprogg/admin/adminbookings.php" class="view-all">View All</a>
                        </div>
                        <?php if (empty($recentBookings)): ?>
                            <?php emptyState('No bookings yet.'); ?>
                        <?php else: ?>
                            <ul class="booking-list">
                                <?php foreach ($recentBookings as $b): ?>
                                    <li>
                                        <?= listImage($b['img'], $b['name']) ?>
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($b['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($b['city']) ?></span>
                                        </div>
                                        <div class="booking-figures">
                                            <span class="booking-date"><?= htmlspecialchars($b['date']) ?></span>
                                            <span class="booking-amount"><?= htmlspecialchars($b['amount']) ?></span>
                                            <span class="badge <?= statusBadgeClass($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
/* =====================================================
   NAV ALIGNMENT — canonical shared admin UI script
   (chip dropdown, mobile sidebar toggle, topbar shadow)
====================================================== */
(function () {
    "use strict";

    var chip = document.getElementById('adminChip');
    if (chip) {
        chip.addEventListener('click', function (e) {
            chip.classList.toggle('open');
            e.stopPropagation();
        });
        document.addEventListener('click', function () {
            chip.classList.remove('open');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { chip.classList.remove('open'); }
        });
    }

    var layout  = document.getElementById('adminLayout');
    var menuBtn = document.getElementById('menuBtn');
    var overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() { if (layout) { layout.classList.remove('sidebar-open'); } }

    if (menuBtn && layout) {
        menuBtn.addEventListener('click', function (e) {
            layout.classList.toggle('sidebar-open');
            e.stopPropagation();
        });
    }
    if (overlay) { overlay.addEventListener('click', closeSidebar); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeSidebar(); }
    });

    var topbar = document.getElementById('adminTopbar');
    if (topbar) {
        var onScroll = function () {
            topbar.classList.toggle('topbar-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
</script>

<!-- =====================================================
     NATIVE CHART ANIMATIONS — one observer for all charts.
     No libraries. Charts are fully rendered in the markup;
     .on just triggers their CSS transitions (staggered).
====================================================== -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var charts = document.querySelectorAll("[data-anim-chart]");

    if (!charts.length) { return; }

    if (reduced || !("IntersectionObserver" in window)) {
        charts.forEach(function (c) { c.classList.add("on"); });
        return;
    }

    var cIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) { return; }
            cIO.unobserve(entry.target);

            /* stagger the bars/segments for a cascade effect */
            entry.target.querySelectorAll(".vbar, .hbar, .g-bar, .donut-seg").forEach(function (seg, i) {
                seg.style.transitionDelay = (i * 40) + "ms";
            });

            entry.target.classList.add("on");
        });
    }, { threshold: 0.3 });

    charts.forEach(function (c) { cIO.observe(c); });
})();
</script>

</body>
</html>