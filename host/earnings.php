<?php
require_once __DIR__ . '/host_init.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/* Real columns: amount_paid / host_payout_amount / platform_fee_amount /
   payout_at / refunded_at. There is NO payment_status column, so we derive it:
     - refunded_at set                                  -> refunded
     - amount_paid > 0 AND amount_paid >= total         -> paid (full)
     - amount_paid > 0 AND amount_paid < total          -> partial (50% reserve)
     - otherwise                                        -> pending
   Grouped by check-in date, falling back to created_at. */

/* -----------------------------------------------------
   INPUT: filter / search / pagination
----------------------------------------------------- */
 $allowedFilters = ['all', 'paid', 'partial', 'pending', 'refunded'];
 $filter = in_array($_GET['filter'] ?? 'all', $allowedFilters, true) ? $_GET['filter'] : 'all';
 $search = trim((string) ($_GET['q'] ?? ''));
 $page   = max(1, (int) ($_GET['page'] ?? 1));
 $perPage = 20;

/* -----------------------------------------------------
   FETCH ALL RECORDS (source of truth for everything)
----------------------------------------------------- */
 $earnStmt = $pdo->prepare(
    "SELECT b.id, COALESCE(b.total, 0) AS total,
            COALESCE(b.amount_paid, 0) AS amount_paid,
            b.host_payout_amount, b.platform_fee_amount,
            b.status, b.created_at, b.checkin_date, b.checkout_date,
            b.payout_at, b.refunded_at,
            CASE
                WHEN b.refunded_at IS NOT NULL THEN 'refunded'
                WHEN b.amount_paid IS NOT NULL AND b.amount_paid > 0
                     AND b.amount_paid >= COALESCE(b.total, 0) - 0.004 THEN 'paid'
                WHEN b.amount_paid IS NOT NULL AND b.amount_paid > 0 THEN 'partial'
                ELSE 'pending'
            END AS payment_status,
            l.title AS listing_title,
            g.name AS guest_name
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     LEFT JOIN users g ON g.id = b.user_id
     WHERE l.user_id = :id
       AND b.status IN ('confirmed','completed')
     ORDER BY COALESCE(b.checkin_date, DATE(b.created_at)) DESC, b.id DESC"
 );
 $earnStmt->execute(['id' => $_SESSION['user_id']]);
 $allBookings = $earnStmt->fetchAll();

 $bookingAmount = function (array $b): float {
    return (float) ($b['host_payout_amount'] !== null ? $b['host_payout_amount'] : $b['amount_paid']);
 };
 $bookingTotalFn = function (array $b): float {
    return (float) ($b['total'] ?? 0);
 };
 $bookingBalanceFn = function (array $b): float {
    return max(0, round((float) ($b['total'] ?? 0) - (float) ($b['amount_paid'] ?? 0), 2));
 };

 $paymentMeta = [
    'paid'     => ['Fully Paid', 'hp-status-active'],
    'partial'  => ['Partial',    'hp-status-partial'],
    'pending'  => ['Pending',    'hp-status-pending'],
    'refunded' => ['Refunded',   'hp-status-refunded'],
 ];

/* Search matcher — listing title, guest name, booking id/ref */
 $matchSearch = function (array $b) use ($search): bool {
    if ($search === '') { return true; }
    $hay = strtolower(
        $b['listing_title'] . ' ' . ($b['guest_name'] ?? '') . ' #' . $b['id'] . ' '
        . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT)
    );
    return strpos($hay, strtolower($search)) !== false;
 };

/* -----------------------------------------------------
   CSV EXPORT — streams ALL matching records, then exits.
   Must run before any HTML output.
----------------------------------------------------- */
 if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = array_values(array_filter($allBookings, function (array $b) use ($filter, $matchSearch) {
        if ($filter !== 'all' && $b['payment_status'] !== $filter) { return false; }
        return $matchSearch($b);
    }));

    if (ob_get_length()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="roomhive-earnings-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); /* UTF-8 BOM so Excel opens it cleanly */
    fputcsv($out, [
        'Booking Ref', 'Booking ID', 'Check-in', 'Check-out', 'Listing', 'Guest',
        'Booking Status', 'Payment Status', 'Total', 'Amount Paid', 'Balance',
        'Platform Fee', 'Host Payout', 'Booked At', 'Payout At', 'Refunded At',
    ]);

    foreach ($exportRows as $b) {
        fputcsv($out, [
            '#' . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT),
            $b['id'],
            $b['checkin_date'] ?: '',
            $b['checkout_date'] ?: '',
            $b['listing_title'],
            $b['guest_name'] ?: '—',
            $b['status'],
            $b['payment_status'],
            number_format($bookingTotalFn($b), 2, '.', ''),
            number_format((float) $b['amount_paid'], 2, '.', ''),
            number_format($bookingBalanceFn($b), 2, '.', ''),
            number_format((float) ($b['platform_fee_amount'] ?? 0), 2, '.', ''),
            number_format($bookingAmount($b), 2, '.', ''),
            substr((string) $b['created_at'], 0, 16),
            $b['payout_at'] ? substr((string) $b['payout_at'], 0, 16) : '',
            $b['refunded_at'] ? substr((string) $b['refunded_at'], 0, 16) : '',
        ]);
    }
    fclose($out);
    exit;
 }

/* -----------------------------------------------------
   TOTALS — computed over ALL records (refunds excluded
   from earnings so payouts aren't overstated)
----------------------------------------------------- */
 $totalEarnings  = 0.0;
 $thisMonth      = 0.0;
 $refundedTotal  = 0.0;
 $refundedCount  = 0;
 $monthMap       = [];
 $currentMonth   = date('Y-m');
 $earningCount   = 0;

foreach ($allBookings as $b) {
    $isRefunded = ($b['payment_status'] === 'refunded');
    $amt = $bookingAmount($b);

    if ($isRefunded) {
        $refundedTotal += $amt;
        $refundedCount++;
        continue; /* refunded money is not earnings */
    }

    $totalEarnings += $amt;
    $earningCount++;

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

 $avgBooking = $earningCount > 0 ? $totalEarnings / $earningCount : 0.0;

/* -----------------------------------------------------
   FILTER + TAB COUNTS + PAGINATION (over all records)
----------------------------------------------------- */
 $tabCounts = ['all' => 0, 'paid' => 0, 'partial' => 0, 'pending' => 0, 'refunded' => 0];
foreach ($allBookings as $b) {
    if (!$matchSearch($b)) { continue; }
    $tabCounts['all']++;
    $tabCounts[$b['payment_status']]++;
}

 $filteredBookings = array_values(array_filter($allBookings, function (array $b) use ($filter, $matchSearch) {
    if ($filter !== 'all' && $b['payment_status'] !== $filter) { return false; }
    return $matchSearch($b);
 }));

 $totalRecords = count($filteredBookings);
 $totalPages   = max(1, (int) ceil($totalRecords / $perPage));
 $page         = min($page, $totalPages);
 $offset       = ($page - 1) * $perPage;
 $pageRecords  = array_slice($filteredBookings, $offset, $perPage);

 $viewPayoutSum = 0.0;
foreach ($filteredBookings as $b) {
    if ($b['payment_status'] !== 'refunded') { $viewPayoutSum += $bookingAmount($b); }
}

/* URL builder preserving filter/search/page */
 $buildUrl = function (array $overrides = []) use ($filter, $search, $page) {
    $params = ['filter' => $filter, 'q' => $search, 'page' => $page];
    foreach ($overrides as $k => $v) {
        if ($v === null) { unset($params[$k]); } else { $params[$k] = $v; }
    }
    if (($params['filter'] ?? '') === 'all') { unset($params['filter']); }
    if (($params['q'] ?? '') === '')         { unset($params['q']); }
    if ((int) ($params['page'] ?? 1) <= 1)   { unset($params['page']); }
    $qs = http_build_query($params);
    return $qs !== '' ? '?' . $qs : '';
 };

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
<style>
    /* ===== FILTER TOOLBAR ===== */
    .hp-toolbar {
        display: flex; flex-wrap: wrap; gap: 12px;
        align-items: center; justify-content: space-between;
        margin-bottom: 16px;
    }
    .hp-filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
    .hp-filter-tab {
        padding: 7px 13px; border-radius: 999px;
        border: 1px solid #E3E7EF; background: #fff;
        font-size: 12.5px; font-weight: 700; color: #5B6172;
        text-decoration: none; transition: all .15s ease;
    }
    .hp-filter-tab:hover { border-color: #F5A623; color: #B07708; }
    .hp-filter-tab.active { background: #14142B; border-color: #14142B; color: #fff; }
    .hp-filter-tab .cnt { opacity: .65; font-weight: 600; margin-left: 4px; }

    .hp-toolbar-right { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .hp-search { display: flex; gap: 8px; align-items: center; }
    .hp-search input {
        padding: 9px 12px; border: 1px solid #DADEE6;
        border-radius: 10px; font-size: 13px; font-family: inherit;
        min-width: 190px;
    }
    .hp-search input:focus { outline: none; border-color: #F5A623; box-shadow: 0 0 0 3px rgba(245,166,35,.15); }
    .hp-btn-mini {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 14px; border-radius: 10px;
        font-size: 12.5px; font-weight: 700; text-decoration: none;
        border: 1.5px solid #EEF1F6; background: #fff; color: #14142B;
        cursor: pointer; font-family: inherit; transition: all .15s ease;
    }
    .hp-btn-mini:hover { background: #F6F7FB; border-color: #E3E7EF; }
    .hp-btn-mini.hp-btn-export { border-color: #F5C77E; color: #B07708; background: #FFF9F0; }
    .hp-btn-mini.hp-btn-export:hover { background: #FFF3E0; }

    /* ===== EXTRA STATUS COLORS ===== */
    .hp-status-partial  { background: #FDF1DC; color: #B07708; border: 1px solid rgba(237,164,35,.5); }
    .hp-status-refunded { background: #FCEAEA; color: #B3261E; border: 1px solid #F5B5B5; }
    .hp-status-pending  { background: #F0F1F6; color: #5B6172; border: 1px solid #E3E7EF; }

    .hp-cell-sub { display: block; font-size: 11px; color: #8B93A6; margin-top: 2px; }
    .hp-balance-note { display: block; font-size: 11px; color: #C77800; margin-top: 2px; }

    /* ===== PAGINATION ===== */
    .hp-pagination {
        display: flex; gap: 6px; flex-wrap: wrap;
        align-items: center; justify-content: center;
        margin-top: 18px;
    }
    .hp-page-btn {
        min-width: 34px; padding: 7px 10px;
        border: 1px solid #E3E7EF; border-radius: 9px;
        text-align: center; font-size: 12.5px; font-weight: 700;
        color: #5B6172; text-decoration: none; background: #fff;
        transition: all .15s ease;
    }
    .hp-page-btn:hover { border-color: #F5A623; color: #B07708; }
    .hp-page-btn.active { background: #F5A623; border-color: #F5A623; color: #fff; }
    .hp-page-btn.disabled { opacity: .45; pointer-events: none; }
    .hp-page-info { font-size: 12px; color: #8B93A6; margin: 0 8px; }

    .hp-tfoot td {
        padding: 12px; border-top: 2px solid #EEF1F6;
        font-weight: 800; color: #14142B; font-size: 13.5px;
        background: #FAFAFD;
    }
</style>
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Earnings</h1>
                <p class="hp-page-subtitle">Every confirmed and completed booking — filter, search, page through, or export the full history. Refunded payments are excluded from earnings.</p>
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
                        <p class="hp-stat-pill-label">Transactions</p>
                        <p class="hp-stat-pill-value"><?php echo h(count($allBookings)); ?></p>
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

        <!-- ALL transactions (filter + search + pagination) -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>All Transactions</h3>
                <span class="hp-muted">
                    Avg &#8369;<?php echo h(number_format($avgBooking, 2)); ?> per booking
                    <?php if ($refundedCount > 0): ?>
                        &middot; &#8369;<?php echo h(number_format($refundedTotal, 2)); ?> refunded (<?php echo (int) $refundedCount; ?>)
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($allBookings)): ?>

                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No earnings yet</p>
                    <p>Earnings appear here once a booking is confirmed or completed.</p>
                </div>

            <?php else: ?>

                <!-- Filter tabs + search + export -->
                <div class="hp-toolbar">
                    <div class="hp-filter-tabs">
                        <a class="hp-filter-tab<?php echo $filter === 'all' ? ' active' : ''; ?>"
                           href="<?php echo h($buildUrl(['filter' => 'all', 'page' => null])); ?>">
                            All<span class="cnt">(<?php echo (int) $tabCounts['all']; ?>)</span>
                        </a>
                        <a class="hp-filter-tab<?php echo $filter === 'paid' ? ' active' : ''; ?>"
                           href="<?php echo h($buildUrl(['filter' => 'paid', 'page' => null])); ?>">
                            Fully Paid<span class="cnt">(<?php echo (int) $tabCounts['paid']; ?>)</span>
                        </a>
                        <a class="hp-filter-tab<?php echo $filter === 'partial' ? ' active' : ''; ?>"
                           href="<?php echo h($buildUrl(['filter' => 'partial', 'page' => null])); ?>">
                            Partial<span class="cnt">(<?php echo (int) $tabCounts['partial']; ?>)</span>
                        </a>
                        <a class="hp-filter-tab<?php echo $filter === 'pending' ? ' active' : ''; ?>"
                           href="<?php echo h($buildUrl(['filter' => 'pending', 'page' => null])); ?>">
                            Pending<span class="cnt">(<?php echo (int) $tabCounts['pending']; ?>)</span>
                        </a>
                        <a class="hp-filter-tab<?php echo $filter === 'refunded' ? ' active' : ''; ?>"
                           href="<?php echo h($buildUrl(['filter' => 'refunded', 'page' => null])); ?>">
                            Refunded<span class="cnt">(<?php echo (int) $tabCounts['refunded']; ?>)</span>
                        </a>
                    </div>

                    <div class="hp-toolbar-right">
                        <form class="hp-search" method="GET">
                            <?php if ($filter !== 'all'): ?>
                                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                            <?php endif; ?>
                            <input type="text" name="q" value="<?php echo h($search); ?>"
                                   placeholder="Search listing, guest, #ref&hellip;">
                            <button type="submit" class="hp-btn-mini">Search</button>
                            <?php if ($search !== ''): ?>
                                <a class="hp-btn-mini" href="<?php echo h($buildUrl(['q' => null, 'page' => null])); ?>">&times;</a>
                            <?php endif; ?>
                        </form>
                        <a class="hp-btn-mini hp-btn-export"
                           href="<?php echo h($buildUrl(['export' => 'csv', 'page' => null])); ?>">
                            &#11015; Export CSV
                        </a>
                    </div>
                </div>

                <?php if (empty($pageRecords)): ?>

                    <div class="hp-empty-state">
                        <p class="hp-empty-state-title">No matching records</p>
                        <p>Try a different filter or clear your search.</p>
                    </div>

                <?php else: ?>

                    <table class="hp-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Check-in</th>
                                <th>Listing</th>
                                <th>Guest</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th style="text-align:right;">Your Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRecords as $t):
                                $payLabel = $paymentMeta[$t['payment_status']][0] ?? ucfirst((string) $t['payment_status']);
                                $payClass = $paymentMeta[$t['payment_status']][1] ?? 'hp-status-pending';
                                $balance  = $bookingBalanceFn($t);
                            ?>
                            <tr>
                                <td>
                                    #<?php echo h(str_pad((string) $t['id'], 6, '0', STR_PAD_LEFT)); ?>
                                    <span class="hp-cell-sub">Booked <?php echo h(date('M j, Y', strtotime($t['created_at']))); ?></span>
                                </td>
                                <td>
                                    <?php echo h($t['checkin_date'] ? date('M j, Y', strtotime($t['checkin_date'])) : date('M j, Y', strtotime($t['created_at']))); ?>
                                    <?php if (!empty($t['checkout_date'])): ?>
                                        <span class="hp-cell-sub">&rarr; <?php echo h(date('M j, Y', strtotime($t['checkout_date']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($t['listing_title']); ?></td>
                                <td><?php echo h($t['guest_name'] !== null && $t['guest_name'] !== '' ? $t['guest_name'] : '—'); ?></td>
                                <td>
                                    <span class="hp-status <?php echo h($payClass); ?>">
                                        <?php echo h($payLabel); ?>
                                    </span>
                                    <?php if ($t['payment_status'] === 'partial'): ?>
                                        <span class="hp-balance-note">&#8369;<?php echo h(number_format($balance, 2)); ?> left</span>
                                    <?php elseif ($t['payment_status'] === 'pending'): ?>
                                        <span class="hp-balance-note">&#8369;<?php echo h(number_format($balance, 2)); ?> due</span>
                                    <?php elseif ($t['payment_status'] === 'refunded' && !empty($t['refunded_at'])): ?>
                                        <span class="hp-cell-sub"><?php echo h(date('M j, Y', strtotime($t['refunded_at']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hp-status <?php echo $t['status'] === 'completed' ? 'hp-status-active' : 'hp-status-pending'; ?>">
                                        <?php echo h(ucfirst($t['status'])); ?>
                                    </span>
                                    <?php if (!empty($t['payout_at'])): ?>
                                        <span class="hp-cell-sub">Paid out <?php echo h(date('M j, Y', strtotime($t['payout_at']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hp-amount" style="text-align:right;">
                                    &#8369; <?php echo h(number_format($bookingAmount($t), 2)); ?>
                                    <span class="hp-cell-sub">of &#8369;<?php echo h(number_format($bookingTotalFn($t), 2)); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="hp-tfoot">
                                <td colspan="6">
                                    <?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $perPage, $totalRecords); ?>
                                    of <?php echo (int) $totalRecords; ?> record<?php echo $totalRecords === 1 ? '' : 's'; ?>
                                    (payout total for this view, excl. refunds)
                                </td>
                                <td style="text-align:right;">&#8369; <?php echo h(number_format($viewPayoutSum, 2)); ?></td>
                            </tr>
                        </tfoot>
                    </table>

                    <?php if ($totalPages > 1): ?>
                    <div class="hp-pagination">
                        <a class="hp-page-btn<?php echo $page <= 1 ? ' disabled' : ''; ?>"
                           href="<?php echo h($buildUrl(['page' => max(1, $page - 1)])); ?>">&larr; Prev</a>

                        <?php
                        $winStart = max(1, $page - 2);
                        $winEnd   = min($totalPages, $winStart + 4);
                        if ($winStart > 1): ?>
                            <a class="hp-page-btn" href="<?php echo h($buildUrl(['page' => 1])); ?>">1</a>
                            <?php if ($winStart > 2): ?><span class="hp-page-info">&hellip;</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
                            <a class="hp-page-btn<?php echo $p === $page ? ' active' : ''; ?>"
                               href="<?php echo h($buildUrl(['page' => $p])); ?>"><?php echo (int) $p; ?></a>
                        <?php endfor; ?>

                        <?php if ($winEnd < $totalPages): ?>
                            <?php if ($winEnd < $totalPages - 1): ?><span class="hp-page-info">&hellip;</span><?php endif; ?>
                            <a class="hp-btn-page hp-page-btn" href="<?php echo h($buildUrl(['page' => $totalPages])); ?>"><?php echo (int) $totalPages; ?></a>
                        <?php endif; ?>

                        <a class="hp-page-btn<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"
                           href="<?php echo h($buildUrl(['page' => min($totalPages, $page + 1)])); ?>">Next &rarr;</a>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>