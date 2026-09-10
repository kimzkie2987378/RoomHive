<?php
/**admin.php
 * RoomHive Admin Dashboard — now backed by real queries
 * Markup + data. All visual styling lives in admin.css
 *
 * Every section below used to be a hardcoded empty/zeroed
 * array. It now pulls from the real tables (users, listings,
 * bookings, reviews, host_applications, listing_photos) via
 * $pdo. If the platform is genuinely still empty, the same
 * empty-state UI you already had kicks in automatically —
 * nothing about the *look* of an empty dashboard changes,
 * only where the numbers come from.
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

/*
 * =========================================================
 * HOST APPLICATION ACTIONS (Approve / Reject)
 * =========================================================
 * Triggered by the Approve/Reject buttons on the Recent Host
 * Applications panel below. Runs before any HTML is echoed,
 * so the header() redirect at the end is always safe to send.
 *
 * Approving does TWO things in one transaction:
 *   1. host_applications.status -> 'approved'
 *   2. users.is_host -> 1 for that application's user_id
 * Step 2 is the part that was missing before — every host
 * guard (hostprofile.php, etc.) reads users.is_host, not the
 * application's own status column, so without it the admin
 * dashboard could show "Approved" while the user still
 * couldn't access anything host-only.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['host_app_action'], $_POST['application_id'])) {
    $applicationId = (int) $_POST['application_id'];
    $action        = $_POST['host_app_action'];

    if ($applicationId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, status FROM host_applications WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $applicationId]);
        $application = $stmt->fetch();

        if ($application) {
            $pdo->beginTransaction();
            try {
                if ($action === 'approve') {
                    $pdo->prepare(
                        "UPDATE host_applications SET status = 'approved' WHERE id = :id"
                    )->execute(['id' => $applicationId]);

                    $pdo->prepare(
                        "UPDATE users SET is_host = 1 WHERE id = :user_id"
                    )->execute(['user_id' => $application['user_id']]);
                } else {
                    $pdo->prepare(
                        "UPDATE host_applications SET status = 'rejected' WHERE id = :id"
                    )->execute(['id' => $applicationId]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                // Leave the application as-is (still pending) rather than
                // showing a false "approved" state if either update failed.
            }
        }
    }

    // Redirect so refreshing the dashboard never resubmits the action.
    header('Location: /webprogg/admin/admin.php');
    exit();
}

/* ---------- Sidebar navigation ---------- */
$navItems = [
    ['label' => 'Dashboard',          'icon' => 'home',      'active' => true, 'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',              'icon' => 'users',     'href' => '/webprogg/admin/adminusers.php'],
    ['label' => 'Bookings',           'icon' => 'calendar',  'href' => '#'],
    ['label' => 'Listings',           'icon' => 'listing',   'href' => '#'],
    ['label' => 'Listings Application', 'icon' => 'clipboard', 'href' => '/webprogg/admin/listingapplication.php'],
    ['label' => 'Host Applications',  'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',            'icon' => 'wallet',    'href' => '#'],
    ['label' => 'Reviews',            'icon' => 'star',      'href' => '#'],
    ['label' => 'Messages',           'icon' => 'message',   'href' => '#'],
    ['label' => 'Reports',            'icon' => 'bar-chart', 'href' => '#'],
    ['label' => 'Settings',           'icon' => 'settings',  'href' => '#'],
];

/* =========================================================
   TOP STAT CARDS
   ========================================================= */
$totalBookings  = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue   = (float) $pdo->query(
    "SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN ('confirmed','completed')"
)->fetchColumn();
$activeListings = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();
$totalUsers     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$avgRatingRow   = $pdo->query("SELECT AVG(rating) FROM reviews")->fetchColumn();
$avgRating      = $avgRatingRow !== null ? round((float) $avgRatingRow, 1) : null;
$totalReviews   = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

$stats = [
    ['label' => 'Total Bookings',  'value' => number_format($totalBookings),               'delta' => '', 'up' => true, 'icon' => 'calendar-solid'],
    ['label' => 'Total Revenue',   'value' => '₱' . number_format($totalRevenue),           'delta' => '', 'up' => true, 'icon' => 'wallet-solid'],
    ['label' => 'Active Listings', 'value' => number_format($activeListings),               'delta' => '', 'up' => true, 'icon' => 'listing-solid'],
    ['label' => 'Total Users',     'value' => number_format($totalUsers),                   'delta' => '', 'up' => true, 'icon' => 'users-solid'],
    ['label' => 'Average Rating',  'value' => ($avgRating !== null ? $avgRating : '—') . ' / 5', 'delta' => '', 'up' => true, 'icon' => 'star-solid'],
];
$statCaptions = [
    $totalBookings > 0  ? 'All-time bookings'          : 'No bookings yet',
    $totalRevenue > 0   ? 'From confirmed & completed'  : 'No revenue yet',
    $activeListings > 0 ? 'Currently live on the site'  : 'No listings yet',
    $totalUsers > 0     ? 'Registered accounts'         : 'No users yet',
    $totalReviews > 0   ? "From {$totalReviews} reviews" : 'No ratings yet',
];

/* =========================================================
   BOOKINGS BY STATUS (donut)
   ========================================================= */
$statusColors = [
    'confirmed' => '#2FA84F',
    'completed' => '#2F7DE1',
    'cancelled' => '#E14B4B',
    'pending'   => '#F5A623',
];
$statusCountsRaw = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$totalBookingsForDonut = array_sum($statusCountsRaw);

$statusBreakdown = [];
foreach (['confirmed', 'completed', 'cancelled', 'pending'] as $statusKey) {
    $count = (int) ($statusCountsRaw[$statusKey] ?? 0);
    $pct = $totalBookingsForDonut > 0 ? round(($count / $totalBookingsForDonut) * 100) . '%' : '0%';
    $statusBreakdown[] = [
        'label' => ucfirst($statusKey),
        'value' => $count,
        'pct'   => $pct,
        'color' => $statusColors[$statusKey],
    ];
}

/* =========================================================
   RECENT HOST APPLICATIONS (pending, most recent 4)
   ========================================================= */
$hostApplications = array_map(function ($row) {
    return [
        'id'         => (int) $row['id'],
        'name'       => $row['full_name'],
        'city'       => $row['location'],
        'status'     => ucfirst($row['status']),
        'raw_status' => $row['status'],
        'img'        => 'https://ui-avatars.com/api/?background=EDA423&color=fff&bold=true&name=' . urlencode($row['full_name']),
    ];
}, $pdo->query(
    "SELECT id, full_name, location, status
     FROM host_applications
     ORDER BY created_at DESC
     LIMIT 4"
)->fetchAll());

/* =========================================================
   TOP PERFORMING LISTINGS (by revenue, top 4)
   ========================================================= */
$topListings = array_map(function ($row) {
    return [
        'name'     => $row['title'],
        'city'     => $row['location'],
        'img'      => $row['cover_photo'] ?? '/webprogg/images/ListingPlaceholder.png',
        'revenue'  => '₱' . number_format((float) $row['revenue']),
        'bookings' => (int) $row['booking_count'] . ' bookings',
    ];
}, $pdo->query(
    "SELECT l.title, l.location,
            p.photo_path AS cover_photo,
            COUNT(b.id) AS booking_count,
            COALESCE(SUM(CASE WHEN b.status IN ('confirmed','completed') THEN b.total ELSE 0 END), 0) AS revenue
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
        'img'    => $row['cover_photo'] ?? '/webprogg/images/ListingPlaceholder.png',
        'date'   => date('M j, Y', strtotime($row['booked_at'])),
        'amount' => '₱' . number_format((float) $row['total']),
        'status' => ucfirst($row['status']),
    ];
}, $pdo->query(
    "SELECT l.title, l.location, p.photo_path AS cover_photo, b.booked_at, b.total, b.status
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     ORDER BY b.booked_at DESC
     LIMIT 4"
)->fetchAll());

/* =========================================================
   PLATFORM SUMMARY (this calendar month)
   ========================================================= */
$monthStart = date('Y-m-01 00:00:00');

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN ('confirmed','completed') AND booked_at >= :start"
);
$stmt->execute(['start' => $monthStart]);
$payoutsThisMonth = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= :start");
$stmt->execute(['start' => $monthStart]);
$newUsersThisMonth = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE created_at >= :start");
$stmt->execute(['start' => $monthStart]);
$newListingsThisMonth = (int) $stmt->fetchColumn();

/* No `messages` table exists yet — wire this up once one does. */
$messagesThisMonth = 0;

$platformSummary = [
    ['label' => 'Payouts This Month', 'value' => '₱' . number_format($payoutsThisMonth), 'icon' => 'wallet'],
    ['label' => 'New Users',          'value' => number_format($newUsersThisMonth),       'icon' => 'user-add'],
    ['label' => 'New Listings',       'value' => number_format($newListingsThisMonth),    'icon' => 'listing'],
    ['label' => 'Messages',           'value' => number_format($messagesThisMonth),       'icon' => 'message'],
];

/* ---------- Notifications: count of pending applications ---------- */
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
        "SELECT COUNT(*), COALESCE(SUM(CASE WHEN status IN ('confirmed','completed') THEN total ELSE 0 END),0)
         FROM bookings WHERE DATE(booked_at) = :day"
    );
    $stmt->execute(['day' => $day]);
    [$dayCount, $dayRevenue] = $stmt->fetch(PDO::FETCH_NUM);

    $bookingSeries[] = (int) $dayCount;
    $revenueSeries[] = (float) $dayRevenue;
}

/* ---------- Inline icon helper (lucide-style strokes) ---------- */
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
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.8 1.8 0 0 0 .36 2l.04.04a2.2 2.2 0 1 1-3.1 3.1l-.04-.04a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.1 1.65V20a2.2 2.2 0 1 1-4.4 0v-.06a1.8 1.8 0 0 0-1.18-1.65 1.8 1.8 0 0 0-2 .36l-.04.04a2.2 2.2 0 1 1-3.1-3.1l.04-.04a1.8 1.8 0 0 0 .36-2 1.8 1.8 0 0 0-1.65-1.1H4a2.2 2.2 0 1 1 0-4.4h.06a1.8 1.8 0 0 0 1.65-1.18 1.8 1.8 0 0 0-.36-2l-.04-.04a2.2 2.2 0 1 1 3.1-3.1l.04.04a1.8 1.8 0 0 0 2 .36H10.5a1.8 1.8 0 0 0 1.1-1.65V4a2.2 2.2 0 1 1 4.4 0v.06a1.8 1.8 0 0 0 1.1 1.65 1.8 1.8 0 0 0 2-.36l.04-.04a2.2 2.2 0 1 1 3.1 3.1l-.04.04a1.8 1.8 0 0 0-.36 2v.09a1.8 1.8 0 0 0 1.65 1.1H20a2.2 2.2 0 1 1 0 4.4h-.06a1.8 1.8 0 0 0-1.65 1.1Z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 6.5-2.5 8-2.5 8h17S18 14.5 18 8Z"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'headphones' => '<path d="M3 13.5v-1.7a9 9 0 0 1 18 0v1.7"/><rect x="3" y="13.5" width="5" height="6.5" rx="1.6"/><rect x="16" y="13.5" width="5" height="6.5" rx="1.6"/>',
        'user-add' => '<circle cx="9" cy="8" r="3.2"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M18 8v5M15.5 10.5h5"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'inbox' => '<path d="M3 12h4.5l1.5 3h6l1.5-3H21"/><path d="M5.5 5.5h13l2.5 6.5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
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

/** Renders a small "nothing here yet" placeholder inside a list panel. */
function emptyState($text) {
    echo '<div class="empty-state">';
    echo '<div class="empty-icon">'.icon('inbox').'</div>';
    echo '<p>'.htmlspecialchars($text).'</p>';
    echo '</div>';
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
<style>
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
</style>
</head>
<body>

<div class="layout">

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">
                <?= icon('home', 'brand-icon') ?>
            </div>
            <div class="brand-text">
                <span class="brand-name">RoomHive</span>
                <span class="brand-tag">FIND. STAY. FEEL AT HOME.</span>
            </div>
        </div>

        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>" class="nav-item <?= !empty($item['active']) ? 'active' : '' ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="help-card">
            <div class="help-icon"><?= icon('headphones') ?></div>
            <p class="help-title">Need Help?</p>
            <p class="help-text">Our support team is here to assist you.</p>
            <button class="btn-support">Contact Support</button>
        </div>
    </aside>

    <!-- ============ MAIN ============ -->
    <div class="main">

        <!-- Topbar -->
        <header class="topbar">
            <button class="icon-btn menu-btn" aria-label="Toggle menu"><?= icon('menu') ?></button>

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
                        <div class="stat-icon"><?= icon($stat['icon'] === 'calendar-solid' ? 'calendar' : ($stat['icon'] === 'wallet-solid' ? 'wallet' : ($stat['icon'] === 'listing-solid' ? 'listing' : ($stat['icon'] === 'users-solid' ? 'users' : 'star')))) ?></div>
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

            <!-- Row: Bookings Overview / Bookings by Status / Recent Host Applications -->
            <div class="grid-3">
                <div class="panel span-2">
                    <div class="panel-header">
                        <h2>Bookings Overview</h2>
                        <select class="period-select"><option>Last 7 Days</option></select>
                    </div>
                    <canvas id="bookingsChart" height="230"></canvas>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Bookings by Status</h2>
                        <select class="period-select"><option>All Time</option></select>
                    </div>
                    <div class="donut-wrap">
                        <canvas id="statusChart" width="180" height="180"></canvas>
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
                </div>
            </div>

            <!-- Row: Revenue Overview + Platform Summary / Top Listings / Recent Bookings -->
            <div class="grid-3">
                <div class="panel span-2 stack">
                    <div>
                        <div class="panel-header">
                            <h2>Revenue Overview</h2>
                            <select class="period-select"><option>Last 7 Days</option></select>
                        </div>
                        <div class="revenue-total-row">
                            <span class="revenue-total">₱<?= number_format(array_sum($revenueSeries)) ?></span>
                            <span class="stat-caption"><?= array_sum($revenueSeries) > 0 ? 'Last 7 days' : 'No revenue yet' ?></span>
                        </div>
                        <canvas id="revenueChart" height="190"></canvas>
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
                                        <img src="<?= htmlspecialchars($h['img']) ?>" alt="<?= htmlspecialchars($h['name']) ?>">
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($h['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($h['city']) ?></span>
                                        </div>
                                        <?php if ($h['raw_status'] === 'pending'): ?>
                                            <div class="host-app-actions">
                                                <form method="post" action="/webprogg/admin/admin.php" class="host-app-form">
                                                    <input type="hidden" name="application_id" value="<?= $h['id'] ?>">
                                                    <input type="hidden" name="host_app_action" value="approve">
                                                    <button type="submit" class="host-app-btn host-app-approve">Approve</button>
                                                </form>
                                                <form method="post" action="/webprogg/admin/admin.php" class="host-app-form">
                                                    <input type="hidden" name="application_id" value="<?= $h['id'] ?>">
                                                    <input type="hidden" name="host_app_action" value="reject">
                                                    <button type="submit" class="host-app-btn host-app-reject">Reject</button>
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
                            <a href="#" class="view-all">View All</a>
                        </div>
                        <?php if (empty($topListings)): ?>
                            <?php emptyState('No listings published yet.'); ?>
                        <?php else: ?>
                            <ul class="listing-list">
                                <?php foreach ($topListings as $i => $l): ?>
                                    <li>
                                        <span class="rank"><?= $i + 1 ?></span>
                                        <img src="<?= htmlspecialchars($l['img']) ?>" alt="<?= htmlspecialchars($l['name']) ?>">
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
                            <a href="#" class="view-all">View All</a>
                        </div>
                        <?php if (empty($recentBookings)): ?>
                            <?php emptyState('No bookings yet.'); ?>
                        <?php else: ?>
                            <ul class="booking-list">
                                <?php foreach ($recentBookings as $b): ?>
                                    <li>
                                        <img src="<?= htmlspecialchars($b['img']) ?>" alt="<?= htmlspecialchars($b['name']) ?>">
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
(function () {
    const chip = document.getElementById('adminChip');
    if (!chip) return;

    chip.addEventListener('click', function (e) {
        chip.classList.toggle('open');
        e.stopPropagation();
    });

    document.addEventListener('click', function () {
        chip.classList.remove('open');
    });
})();
</script>
<script>
const chartLabels = <?= json_encode($chartLabels) ?>;
const bookingSeries = <?= json_encode($bookingSeries) ?>;
const revenueSeries = <?= json_encode($revenueSeries) ?>;

const bookingsCtx = document.getElementById('bookingsChart');
new Chart(bookingsCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            data: bookingSeries,
            borderColor: '#2F7DE1',
            backgroundColor: 'rgba(47,125,225,0.12)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointRadius: 3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                ticks: { color: '#8B93A6', maxTicksLimit: 7, font: { size: 11 } },
                grid: { display: false }
            },
            y: {
                ticks: { color: '#8B93A6', stepSize: 1, font: { size: 11 } },
                grid: { color: '#EEF1F6' },
                beginAtZero: true
            }
        }
    }
});

const statusCtx = document.getElementById('statusChart');
const statusTotal = <?= $totalBookingsForDonut ?>;
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($statusBreakdown, 'label')) ?>,
        datasets: [{
            data: statusTotal > 0 ? <?= json_encode(array_column($statusBreakdown, 'value')) ?> : [1],
            backgroundColor: statusTotal > 0 ? <?= json_encode(array_column($statusBreakdown, 'color')) ?> : ['#EEEAE0'],
            borderWidth: 0,
        }]
    },
    options: {
        responsive: false,
        cutout: '68%',
        plugins: { legend: { display: false }, tooltip: { enabled: statusTotal > 0 } }
    }
});

const revenueCtx = document.getElementById('revenueChart');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [{
            data: revenueSeries,
            backgroundColor: '#2FA84F',
            borderRadius: 3,
            maxBarThickness: 14,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                ticks: { color: '#8B93A6', maxTicksLimit: 7, font: { size: 11 } },
                grid: { display: false }
            },
            y: {
                ticks: {
                    color: '#8B93A6',
                    font: { size: 11 },
                    callback: (v) => v === 0 ? '0' : (v / 1000) + 'K'
                },
                grid: { color: '#EEF1F6' },
                beginAtZero: true
            }
        }
    }
});
</script>
</body>
</html>