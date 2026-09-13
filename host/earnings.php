<?php
require_once __DIR__ . '/host_init.php';

/* Real columns: amount_paid / host_payout_amount / platform_fee_amount /
   payout_at / refunded_at. There is NO payment_status column, so we derive it:
     - refunded_at set   -> refunded
     - amount_paid > 0   -> paid
     - otherwise         -> pending
   Grouped by check-in date, falling back to created_at. */
$earnStmt = $pdo->prepare(
    "SELECT b.id, b.amount_paid, b.host_payout_amount, b.status,
            b.created_at, b.checkin_date, b.payout_at, b.refunded_at,
            CASE
                WHEN b.refunded_at IS NOT NULL THEN 'refunded'
                WHEN b.amount_paid IS NOT NULL AND b.amount_paid > 0 THEN 'paid'
                ELSE 'pending'
            END AS payment_status,
            l.title
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed','completed')
     ORDER BY COALESCE(b.checkin_date, DATE(b.created_at)) DESC"
);
$earnStmt->execute(['id' => $_SESSION['user_id']]);
$paidBookings = $earnStmt->fetchAll();

$bookingAmount = function (array $b): float {
    return (float) ($b['host_payout_amount'] !== null ? $b['host_payout_amount'] : $b['amount_paid']);
};

$totalEarnings = 0.0;
$thisMonth     = 0.0;
$monthMap      = [];
$currentMonth  = date('Y-m');

foreach ($paidBookings as $b) {
    $amt = $bookingAmount($b);
    $totalEarnings += $amt;

    $stayDate = $b['checkin_date'] ?: substr($b['created_at'], 0, 10);
    $key = date('Y-m', strtotime($stayDate));
    $monthMap[$key] = ($monthMap[$key] ?? 0) + $amt;

    if ($key === $currentMonth) $thisMonth += $amt;
}

/* Last 6 months chart */
$chart = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("first day of -{$i} months");
    $key = date('Y-m', $ts);
    $chart[] = ['label' => date('M', $ts), 'value' => $monthMap[$key] ?? 0.0];
}
$maxMonth = 1.0;
foreach ($chart as $c) { if ($c['value'] > $maxMonth) $maxMonth = $c['value']; }

$avgBooking = count($paidBookings) > 0 ? $totalEarnings / count($paidBookings) : 0.0;

$activePage = 'earnings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Earnings — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Earnings</h1>
                <p class="hp-page-subtitle">Your net earnings from confirmed and completed bookings.</p>
            </div>
            <div class="hp-stat-pillbox">
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon"><img src="/webprogg/images/totalspenticon-userprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">Total Earnings</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($totalEarnings, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-green"><img src="/webprogg/images/bookingsicon-userprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">This Month</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($thisMonth, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-yellow"><img src="/webprogg/images/viewsicon-hostprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">Paid Bookings</p>
                        <p class="hp-stat-pill-value"><?php echo h(count($paidBookings)); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly chart -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>Last 6 Months</h3>
                <span class="hp-muted">Grouped by check-in date</span>
            </div>
            <div class="hp-bars">
                <?php foreach ($chart as $m):
                    $pct = round(($m['value'] / $maxMonth) * 100);
                ?>
                <div class="hp-bar-col">
                    <span class="hp-bar-value">&#8369;<?php echo h(number_format($m['value'], 0)); ?></span>
                    <div class="hp-bar-track">
                        <div class="hp-bar" style="height: <?php echo $pct; ?>%;"></div>
                    </div>
                    <span class="hp-bar-label"><?php echo h($m['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Transactions -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>Recent Transactions</h3>
                <span class="hp-muted">Avg &#8369;<?php echo h(number_format($avgBooking, 2)); ?> per booking</span>
            </div>

            <?php if (empty($paidBookings)): ?>
                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No earnings yet</p>
                    <p>Earnings appear here once a booking is confirmed or completed.</p>
                </div>
            <?php else: ?>
                <table class="hp-table">
                    <thead>
                        <tr>
                            <th>Check-in</th>
                            <th>Listing</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th style="text-align:right;">Your Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($paidBookings, 0, 15) as $t): ?>
                        <tr>
                            <td><?php echo h($t['checkin_date'] ? date('M j, Y', strtotime($t['checkin_date'])) : date('M j, Y', strtotime($t['created_at']))); ?></td>
                            <td><?php echo h($t['title']); ?></td>
                            <td>
                                <span class="hp-status <?php echo $t['payment_status'] === 'paid' ? 'hp-status-active' : 'hp-status-pending'; ?>">
                                    <?php echo h(ucfirst((string) $t['payment_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="hp-status <?php echo $t['status'] === 'completed' ? 'hp-status-active' : 'hp-status-pending'; ?>">
                                    <?php echo h(ucfirst($t['status'])); ?>
                                </span>
                            </td>
                            <td class="hp-amount" style="text-align:right;">&#8369; <?php echo h(number_format($bookingAmount($t), 2)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>