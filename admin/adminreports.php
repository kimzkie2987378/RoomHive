<?php
require_once __DIR__ . '/admin_init.php';

/* ---- Build 6-month series ---- */
 $months = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -{$i} months");
    $months[date('Y-m', $ts)] = ['label' => date('M Y', $ts), 'bookings' => 0, 'revenue' => 0.0, 'users' => 0];
}
 $windowStart = date('Y-m-01 00:00:00', strtotime('-5 months'));

 $bk = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c, COALESCE(SUM(amount_paid),0) r
     FROM bookings WHERE created_at >= :s GROUP BY ym"
);
 $bk->execute(['s' => $windowStart]);
foreach ($bk->fetchAll() as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['bookings'] = (int) $row['c'];
        $months[$row['ym']]['revenue']  = (float) $row['r'];
    }
}

 $us = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c
     FROM users WHERE created_at >= :s GROUP BY ym"
);
 $us->execute(['s' => $windowStart]);
foreach ($us->fetchAll() as $row) {
    if (isset($months[$row['ym']])) $months[$row['ym']]['users'] = (int) $row['c'];
}

 $chartLabels   = array_column(array_values($months), 'label');
 $bookingSeries = array_column(array_values($months), 'bookings');
 $revenueSeries = array_column(array_values($months), 'revenue');
 $userSeries    = array_column(array_values($months), 'users');

/* ---- Top hosts by earnings ---- */
 $topHosts = $pdo->query(
    "SELECT u.id, u.name, COUNT(b.id) AS bookings,
            COALESCE(SUM(COALESCE(b.host_payout_amount, b.amount_paid)),0) AS earned
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = l.user_id
     WHERE b.status IN ('confirmed','completed')
     GROUP BY u.id, u.name
     ORDER BY earned DESC
     LIMIT 5"
)->fetchAll();

/* ---- Top listings by bookings ---- */
 $topListings = $pdo->query(
    "SELECT l.id, l.title, COUNT(b.id) AS bookings,
            COALESCE(SUM(b.amount_paid),0) AS volume
     FROM listings l
     JOIN bookings b ON b.listing_id = l.id AND b.status IN ('confirmed','completed')
     GROUP BY l.id, l.title
     ORDER BY bookings DESC
     LIMIT 5"
)->fetchAll();

/* ---- Lifetime totals ---- */
 $lifeRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM bookings WHERE status IN ('confirmed','completed')")->fetchColumn();
 $lifeBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
 $lifeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
 $lifeListings = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();
?>
<?php admin_page_start('RoomHive Admin — Reports', 'Reports'); ?>

<div class="page-heading">
    <h1>Reports</h1>
    <p>Platform performance over the last 6 months.</p>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon"><?= icon('wallet') ?></div><div class="stat-body">
        <span class="stat-label">Lifetime Volume</span>
        <div class="stat-value-row"><span class="stat-value">₱<?= h(number_format($lifeRevenue)) ?></span></div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('calendar') ?></div><div class="stat-body">
        <span class="stat-label">Lifetime Bookings</span>
        <div class="stat-value-row"><span class="stat-value"><?= h(number_format($lifeBookings)) ?></span></div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><div class="stat-body">
        <span class="stat-label">Total Users</span>
        <div class="stat-value-row"><span class="stat-value"><?= h(number_format($lifeUsers)) ?></span></div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('listing') ?></div><div class="stat-body">
        <span class="stat-label">Approved Listings</span>
        <div class="stat-value-row"><span class="stat-value"><?= h(number_format($lifeListings)) ?></span></div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('bar-chart') ?></div><div class="stat-body">
        <span class="stat-label">6-Month Users</span>
        <div class="stat-value-row"><span class="stat-value"><?= h(number_format(array_sum($userSeries))) ?></span></div></div></div>
</div>

<div class="grid-3">
    <div class="panel span-2">
        <div class="panel-header"><h2>Bookings & Revenue — Last 6 Months</h2></div>
        <canvas id="revenueChart" height="130"></canvas>
        <canvas id="bookingsChart" height="130"></canvas>
    </div>

    <div class="col-stack">
        <div class="panel">
            <div class="panel-header"><h2>Top Hosts</h2></div>
            <?php if (empty($topHosts)): ?><?php emptyState('No confirmed bookings yet.'); ?>
            <?php else: ?>
            <ul class="people-list">
                <?php foreach ($topHosts as $i => $t): ?>
                    <li>
                        <span class="rank"><?= $i + 1 ?></span>
                        <div class="people-info">
                            <span class="people-name"><?= h($t['name']) ?></span>
                            <span class="people-sub"><?= (int)$t['bookings'] ?> booking(s)</span>
                        </div>
                        <div class="listing-figures"><span class="listing-revenue">₱<?= h(number_format((float)$t['earned'])) ?></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Top Listings</h2></div>
            <?php if (empty($topListings)): ?><?php emptyState('No confirmed bookings yet.'); ?>
            <?php else: ?>
            <ul class="listing-list">
                <?php foreach ($topListings as $i => $t): ?>
                    <li>
                        <span class="rank"><?= $i + 1 ?></span>
                        <div class="people-info">
                            <span class="people-name"><?= h($t['title']) ?></span>
                            <span class="people-sub"><?= (int)$t['bookings'] ?> booking(s)</span>
                        </div>
                        <div class="listing-figures"><span class="listing-revenue">₱<?= h(number_format((float)$t['volume'])) ?></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($chartLabels) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels: labels, datasets: [{ data: <?= json_encode($revenueSeries) ?>, backgroundColor: '#2FA84F', borderRadius: 3, maxBarThickness: 18, label: 'Revenue' }] },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { ticks: { color: '#8B93A6', font: { size: 10 } }, grid: { display: false } },
                  y: { ticks: { color: '#8B93A6', callback: v => v >= 1000 ? (v/1000)+'K' : v, font: { size: 10 } }, grid: { color: '#EEF1F6' }, beginAtZero: true } } }
});

new Chart(document.getElementById('bookingsChart'), {
    type: 'line',
    data: { labels: labels, datasets: [{ data: <?= json_encode($bookingSeries) ?>, borderColor: '#2F7DE1', backgroundColor: 'rgba(47,125,225,0.1)', fill: true, tension: 0.35, pointRadius: 3, label: 'Bookings' }] },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { x: { ticks: { color: '#8B93A6', font: { size: 10 } }, grid: { display: false } },
                  y: { ticks: { color: '#8B93A6', stepSize: 1, font: { size: 10 } }, grid: { color: '#EEF1F6' }, beginAtZero: true } } }
});
</script>

<?php admin_page_end(); ?>