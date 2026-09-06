<?php
/**admin.php
 * RoomHive Admin Dashboard — fresh/empty state
 * Markup + data. All visual styling lives in admin.css
 *
 * This version reflects a brand-new platform: no bookings, users,
 * listings, hosts, or revenue yet. Every $-array below is empty or
 * zeroed on purpose — wire these up to your real queries once data
 * starts coming in, and the empty-state UI will automatically stop
 * showing.
 */

/* ---------- Sidebar navigation ---------- */
$navItems = [
    ['label' => 'Dashboard',          'icon' => 'home',      'active' => true],
    ['label' => 'Users',              'icon' => 'users'],
    ['label' => 'Bookings',           'icon' => 'calendar'],
    ['label' => 'Listings',           'icon' => 'listing'],
    ['label' => 'Listings Application', 'icon' => 'clipboard', 'href' => 'listingapplication.php'],
    ['label' => 'Host Applications',  'icon' => 'user-check', 'href' => 'hostapplication.php'],
    ['label' => 'Payouts',            'icon' => 'wallet'],
    ['label' => 'Reviews',            'icon' => 'star'],
    ['label' => 'Messages',           'icon' => 'message'],
    ['label' => 'Reports',            'icon' => 'bar-chart'],
    ['label' => 'Settings',           'icon' => 'settings'],
];

/* ---------- Top stat cards (all zero — nothing has happened yet) ---------- */
$stats = [
    ['label' => 'Total Bookings',  'value' => '0',      'delta' => '', 'up' => true, 'icon' => 'calendar-solid'],
    ['label' => 'Total Revenue',   'value' => '₱0',     'delta' => '', 'up' => true, 'icon' => 'wallet-solid'],
    ['label' => 'Active Listings', 'value' => '0',      'delta' => '', 'up' => true, 'icon' => 'listing-solid'],
    ['label' => 'Total Users',     'value' => '0',      'delta' => '', 'up' => true, 'icon' => 'users-solid'],
    ['label' => 'Average Rating',  'value' => '— / 5',  'delta' => '', 'up' => true, 'icon' => 'star-solid'],
];
$statCaptions = [
    'No bookings yet',
    'No revenue yet',
    'No listings yet',
    'No users yet',
    'No ratings yet',
];

/* ---------- Bookings by status (donut) — empty until bookings exist ---------- */
$statusBreakdown = [
    ['label' => 'Confirmed', 'value' => 0, 'pct' => '0%', 'color' => '#2FA84F'],
    ['label' => 'Completed', 'value' => 0, 'pct' => '0%', 'color' => '#2F7DE1'],
    ['label' => 'Cancelled', 'value' => 0, 'pct' => '0%', 'color' => '#E14B4B'],
    ['label' => 'Pending',   'value' => 0, 'pct' => '0%', 'color' => '#F5A623'],
];
$totalBookingsForDonut = array_sum(array_column($statusBreakdown, 'value'));

/* ---------- Recent host applications — none submitted yet ---------- */
$hostApplications = [];

/* ---------- Top performing listings — none published yet ---------- */
$topListings = [];

/* ---------- Recent bookings — none made yet ---------- */
$recentBookings = [];

/* ---------- Platform summary ---------- */
$platformSummary = [
    ['label' => 'Payouts This Month', 'value' => '₱0', 'icon' => 'wallet'],
    ['label' => 'New Users',          'value' => '0',  'icon' => 'user-add'],
    ['label' => 'New Listings',       'value' => '0',  'icon' => 'listing'],
    ['label' => 'Messages',           'value' => '0',  'icon' => 'message'],
];

/* ---------- Notifications ---------- */
$notificationCount = 0;

/* ---------- Chart data (Bookings Overview / Revenue Overview) — flat at zero ---------- */
$chartLabels  = ['May 1','May 6','May 11','May 16','May 21','May 26','May 31'];
$bookingSeries = array_fill(0, 7, 0);
$revenueSeries = array_fill(0, 7, 0);

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
        'eye' => '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.3 2.4 2.4 4.6-5.4"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'person' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/>',
        'pin' => '<path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.2"/>',
        'calendar-small' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'phone' => '<path d="M5 4h3.5l1.5 5-2.2 1.6a11 11 0 0 0 5.6 5.6L14.5 14l5 1.5V19a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'file' => '<path d="M7 3.5h7l4.5 4.5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4.5"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'home-solid' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
        'tag' => '<path d="M11.5 3h6.5a1 1 0 0 1 1 1v6.5a1 1 0 0 1-.3.7l-9 9a1 1 0 0 1-1.4 0l-6.5-6.5a1 1 0 0 1 0-1.4l9-9a1 1 0 0 1 .7-.3Z"/><circle cx="15.5" cy="7.5" r="1.3"/>',
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
<link rel="stylesheet" href="admin.css">
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
                <div class="admin-chip">
                    <div class="admin-avatar admin-avatar-fallback">A</div>
                    <div class="admin-info">
                        <span class="admin-name">Admin User</span>
                        <span class="admin-role">Administrator</span>
                    </div>
                    <?= icon('chevron-down', 'chevron') ?>
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
                        <select class="period-select"><option>This Month</option><option>Last Month</option></select>
                    </div>
                    <canvas id="bookingsChart" height="230"></canvas>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Bookings by Status</h2>
                        <select class="period-select"><option>This Month</option><option>Last Month</option></select>
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
                            <select class="period-select"><option>This Month</option><option>Last Month</option></select>
                        </div>
                        <div class="revenue-total-row">
                            <span class="revenue-total">₱0</span>
                            <span class="stat-caption">No revenue yet</span>
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
                            <a href="hostapplication.php" class="view-all">View All</a>
                        </div>
                        <?php if (empty($hostApplications)): ?>
                            <?php emptyState('No host applications yet.'); ?>
                        <?php else: ?>
                            <ul class="people-list">
                                <?php foreach ($hostApplications as $h): ?>
                                    <li>
                                        <img src="<?= $h['img'] ?>" alt="<?= htmlspecialchars($h['name']) ?>">
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($h['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($h['city']) ?></span>
                                        </div>
                                        <span class="badge <?= statusBadgeClass($h['status']) ?>"><?= $h['status'] ?></span>
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
                                        <img src="<?= $l['img'] ?>" alt="<?= htmlspecialchars($l['name']) ?>">
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($l['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($l['city']) ?></span>
                                        </div>
                                        <div class="listing-figures">
                                            <span class="listing-revenue"><?= $l['revenue'] ?></span>
                                            <span class="listing-bookings"><?= $l['bookings'] ?></span>
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
                                        <img src="<?= $b['img'] ?>" alt="<?= htmlspecialchars($b['name']) ?>">
                                        <div class="people-info">
                                            <span class="people-name"><?= htmlspecialchars($b['name']) ?></span>
                                            <span class="people-sub"><?= htmlspecialchars($b['city']) ?></span>
                                        </div>
                                        <div class="booking-figures">
                                            <span class="booking-date"><?= $b['date'] ?></span>
                                            <span class="booking-amount"><?= $b['amount'] ?></span>
                                            <span class="badge <?= statusBadgeClass($b['status']) ?>"><?= $b['status'] ?></span>
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
            borderColor: '#E9E4D8',
            backgroundColor: 'rgba(233,228,216,0.25)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointRadius: 0,
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
                beginAtZero: true,
                max: 5
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
            backgroundColor: '#EEEAE0',
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
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>
</body>
</html>