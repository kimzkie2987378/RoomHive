<?php
require_once __DIR__ . '/host_init.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   EARNINGS + FULL TRANSACTION LEDGER
   - Transactions table shows ALL bookings (any status).
     Pending bookings are listed but NOT counted in earnings
     until they are confirmed/completed.
   - Earnings totals / chart / avg = confirmed+completed,
     non-refunded rows only — PLUS the 2% cancellation fee
     the host keeps from every refunded booking.
   - REFUND FEE RULE: a refunded booking still earns the
     host 2% of its amount (the guest gets 98% back to
     their wallet — same rule as the booking pages).
     Adjust HP_REFUND_FEE_RATE below to change the cut.
   - Payment status derived in PHP:
       refunded_at set OR refunded_amount > 0   -> refunded
       amount_paid >= total                     -> paid (full)
       0 < amount_paid < total                  -> partial
       amount_paid = 0                          -> pending
   - SELF-HEALING: columns are detected via SHOW COLUMNS and
     the SELECT is built only from what exists.
   - ?debug=1 shows what was detected and row counts.

   === THIS VERSION (icons out, charts varied) ===
   1. Stat-pill IMG ICONS deleted — replaced with pure-CSS
      text badges (no images, no icon fonts).
   2. NEW SVG area/line chart of the 6-month earnings added
      alongside the existing monthly bars — two distinct
      native chart types, zero libraries (max compatibility),
      animated via IntersectionObserver with reduced-motion
      and no-JS fallbacks + dark-mode variants.
   3. All ledger logic unchanged (2% refund fee, filters,
      search, CSV, pagination, debug).
========================================================= */

/* Host keeps 2% of every refunded booking (guest refunds 98%).
   Change here if the platform ever takes the fee instead. */
 $refundFeeRate = 0.02;

 $dbError = null;
 $debug   = isset($_GET['debug']) && $_GET['debug'] === '1';

/* -----------------------------------------------------
   INPUT: filter / search / pagination
   $filter can ONLY be a whitelisted key or 'all'.
----------------------------------------------------- */
 $allowedFilters = ['all', 'paid', 'partial', 'pending', 'refunded'];
 $rawFilter = isset($_GET['filter']) && is_string($_GET['filter']) ? trim($_GET['filter']) : '';
 $filter    = in_array($rawFilter, $allowedFilters, true) ? $rawFilter : 'all';

 $search  = trim((string) ($_GET['q'] ?? ''));
 $page    = max(1, (int) ($_GET['page'] ?? 1));
 $perPage = 20;

/* -----------------------------------------------------
   SCHEMA DETECTION — build the SELECT from real columns
----------------------------------------------------- */
 $bookingCols = [];
 $userCols    = [];
try {
    $bookingCols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    $userCols    = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('earnings: SHOW COLUMNS failed, using fallback list: ' . $e->getMessage());
    $bookingCols = ['id', 'total', 'amount_paid', 'host_payout_amount', 'status',
                    'created_at', 'checkin_date', 'payout_at', 'refunded_at'];
    $userCols    = ['id', 'name'];
}

 $hasB = function (string $c) use ($bookingCols): bool { return in_array($c, $bookingCols, true); };
 $hasU = function (string $c) use ($userCols): bool    { return in_array($c, $userCols, true); };

 $sel = [];
 $sel[] = 'b.id';
 $sel[] = 'l.title AS listing_title';
 $sel[] = $hasB('total')              ? 'COALESCE(b.total, 0) AS total'           : '0 AS total';
 $sel[] = $hasB('amount_paid')        ? 'COALESCE(b.amount_paid, 0) AS amount_paid' : '0 AS amount_paid';
 $sel[] = $hasB('host_payout_amount') ? 'b.host_payout_amount'                    : 'NULL AS host_payout_amount';
 $sel[] = $hasB('platform_fee_amount')? 'b.platform_fee_amount'                   : 'NULL AS platform_fee_amount';
 $sel[] = $hasB('refunded_amount')    ? 'COALESCE(b.refunded_amount, 0) AS refunded_amount' : '0 AS refunded_amount';
 $sel[] = 'b.status';
 $sel[] = 'b.created_at';
 $sel[] = $hasB('checkin_date')       ? 'b.checkin_date'                          : 'NULL AS checkin_date';
 $sel[] = $hasB('checkout_date')      ? 'b.checkout_date'                         : 'NULL AS checkout_date';
 $sel[] = $hasB('payout_at')          ? 'b.payout_at'                             : 'NULL AS payout_at';
 $sel[] = $hasB('refunded_at')        ? 'b.refunded_at'                           : 'NULL AS refunded_at';

 $canGuestJoin = $hasU('name') || $hasU('email');
 if ($hasU('name')) {
    $sel[] = 'g.name AS guest_name';
 } elseif ($hasU('email')) {
    $sel[] = 'g.email AS guest_name';
 } else {
    $sel[] = "'' AS guest_name";
 }

 $sql = "SELECT " . implode(",\n       ", $sel) . "
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id"
     . ($canGuestJoin ? "\n     LEFT JOIN users g ON g.id = b.user_id" : "") . "
     WHERE l.user_id = :id
     ORDER BY COALESCE(b.checkin_date, DATE(b.created_at)) DESC, b.id DESC";

/* -----------------------------------------------------
   RUN THE QUERY — main attempt, then a minimal fallback,
   then a visible error (never a silent empty page)
----------------------------------------------------- */
 $allBookings = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute(['id' => $_SESSION['user_id']]);
    $allBookings = $st->fetchAll();
} catch (PDOException $e) {
    error_log('earnings: main query failed: ' . $e->getMessage());
    try {
        /* Minimal query using only the columns the original page used */
        $st = $pdo->prepare(
            "SELECT b.id,
                    COALESCE(b.total, 0) AS total,
                    b.amount_paid, b.host_payout_amount, b.status,
                    b.created_at, b.checkin_date, b.payout_at, b.refunded_at,
                    0 AS refunded_amount,
                    l.title AS listing_title
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = :id
             ORDER BY COALESCE(b.checkin_date, DATE(b.created_at)) DESC"
        );
        $st->execute(['id' => $_SESSION['user_id']]);
        $allBookings = $st->fetchAll();
    } catch (PDOException $e2) {
        $dbError = $e2->getMessage();
        error_log('earnings: fallback query failed: ' . $e2->getMessage());
    }
}

/* -----------------------------------------------------
   DERIVE PAYMENT STATUS IN PHP
   Refund detection catches refunded_amount > 0 too (what
   the wallet refund system writes), not just refunded_at.
----------------------------------------------------- */
 $derivePayment = function (array $b): string {
    $refundedAmount = (float) ($b['refunded_amount'] ?? 0);
    if (!empty($b['refunded_at']) || $refundedAmount > 0.005) { return 'refunded'; }
    $paid  = (float) ($b['amount_paid'] ?? 0);
    $total = (float) ($b['total'] ?? 0);
    if ($paid > 0.005) {
        return ($total > 0 && $paid >= $total - 0.004) ? 'paid' : 'partial';
    }
    return 'pending';
 };

foreach ($allBookings as $k => $b) {
    $allBookings[$k]['payment_status'] = $derivePayment($b);
}

 $bookingAmount = function (array $b): float {
    $payout = $b['host_payout_amount'] ?? null;
    return (float) ($payout !== null ? $payout : ($b['amount_paid'] ?? 0));
 };

/* What the host keeps from a refunded booking (2%) */
 $refundFeeFn = function (array $b) use ($refundFeeRate, $bookingAmount): float {
    return round($bookingAmount($b) * $refundFeeRate, 2);
 };

 $bookingTotalFn = function (array $b): float {
    return (float) ($b['total'] ?? 0);
 };
 $bookingBalanceFn = function (array $b): float {
    return max(0, round((float) ($b['total'] ?? 0) - (float) ($b['amount_paid'] ?? 0), 2));
 };

/* Earnings-eligible = confirmed/completed and not refunded
   (refunded rows earn their 2% fee separately below) */
 $isEarningRow = function (array $b): bool {
    return in_array($b['status'], ['confirmed', 'completed'], true)
        && $b['payment_status'] !== 'refunded';
 };

 $paymentMeta = [
    'paid'     => ['Fully Paid', 'hp-status-active'],
    'partial'  => ['Partial',    'hp-status-partial'],
    'pending'  => ['Pending',    'hp-status-pending'],
    'refunded' => ['Refunded',   'hp-status-refunded'],
 ];

 $matchSearch = function (array $b) use ($search): bool {
    if ($search === '') { return true; }
    $hay = strtolower(
        ($b['listing_title'] ?? '') . ' ' . ($b['guest_name'] ?? '') . ' #' . $b['id'] . ' '
        . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT)
    );
    return strpos($hay, strtolower($search)) !== false;
 };

/* -----------------------------------------------------
   CSV EXPORT — all matching records (runs before HTML)
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
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Booking Ref', 'Booking ID', 'Check-in', 'Check-out', 'Listing', 'Guest',
        'Booking Status', 'Payment Status', 'Total', 'Amount Paid', 'Balance',
        'Platform Fee', 'Host Payout', 'Booked At', 'Payout At', 'Refunded At',
    ]);
    foreach ($exportRows as $b) {
        /* Refunded rows: payout column = the 2% fee kept */
        $hostPayout = $b['payment_status'] === 'refunded'
            ? $refundFeeFn($b)
            : $bookingAmount($b);

        fputcsv($out, [
            '#' . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT),
            $b['id'],
            ($b['checkin_date'] ?? '') ?: '',
            ($b['checkout_date'] ?? '') ?: '',
            $b['listing_title'] ?? '',
            ($b['guest_name'] ?? '') ?: '—',
            $b['status'],
            $b['payment_status'],
            number_format($bookingTotalFn($b), 2, '.', ''),
            number_format((float) ($b['amount_paid'] ?? 0), 2, '.', ''),
            number_format($bookingBalanceFn($b), 2, '.', ''),
            number_format((float) ($b['platform_fee_amount'] ?? 0), 2, '.', ''),
            number_format($hostPayout, 2, '.', ''),
            substr((string) ($b['created_at'] ?? ''), 0, 16),
            !empty($b['payout_at']) ? substr((string) $b['payout_at'], 0, 16) : '',
            !empty($b['refunded_at']) ? substr((string) $b['refunded_at'], 0, 16) : '',
        ]);
    }
    fclose($out);
    exit;
 }

/* -----------------------------------------------------
   TOTALS — confirmed/completed rows at full value, refunded
   rows at the 2% cancellation fee
----------------------------------------------------- */
 $totalEarnings = 0.0;
 $thisMonth     = 0.0;
 $refundedTotal = 0.0;
 $refundedCount = 0;
 $refundFeesEarned = 0.0;
 $earningCount  = 0;
 $monthMap      = [];
 $currentMonth  = date('Y-m');

foreach ($allBookings as $b) {
    $amt = $bookingAmount($b);

    if ($b['payment_status'] === 'refunded') {
        $refundedTotal += $amt;
        $refundedCount++;

        $fee = $refundFeeFn($b);
        $totalEarnings    += $fee;
        $refundFeesEarned += $fee;
        $earningCount++;

        $stayDate = ($b['checkin_date'] ?? '') ?: substr((string) ($b['created_at'] ?? 'now'), 0, 10);
        $key = date('Y-m', strtotime($stayDate));
        $monthMap[$key] = ($monthMap[$key] ?? 0) + $fee;

        if ($key === $currentMonth) { $thisMonth += $fee; }
        continue;
    }

    if (!$isEarningRow($b)) {
        continue; /* pending / declined / cancelled — listed but not earned */
    }

    $totalEarnings += $amt;
    $earningCount++;

    $stayDate = ($b['checkin_date'] ?? '') ?: substr((string) ($b['created_at'] ?? 'now'), 0, 10);
    $key = date('Y-m', strtotime($stayDate));
    $monthMap[$key] = ($monthMap[$key] ?? 0) + $amt;

    if ($key === $currentMonth) { $thisMonth += $amt; }
}

 $chart = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("first day of -{$i} months");
    $key = date('Y-m', $ts);
    $chart[] = ['label' => date('M', $ts), 'value' => $monthMap[$key] ?? 0.0];
}
 $maxMonth = 1.0;
foreach ($chart as $c) { if ($c['value'] > $maxMonth) { $maxMonth = $c['value']; } }

 $avgBooking = $earningCount > 0 ? $totalEarnings / $earningCount : 0.0;

/* -----------------------------------------------------
   NEW — SVG AREA/LINE TREND (6-month data, native SVG)
   Points pre-built in PHP so the markup stays static.
----------------------------------------------------- */
 $chartSum = 0.0;
foreach ($chart as $c) { $chartSum += $c['value']; }

 $chartMax = max($maxMonth, 0.01);

 $trPoints = [];
foreach ($chart as $i => $c) {
    $x = 8 + ($i / max(1, count($chart) - 1)) * 584;
    $y = 18 + (1 - ($c['value'] / $chartMax)) * 132;
    $trPoints[] = round($x, 1) . ',' . round($y, 1);
}
 $trPointsStr = implode(' ', $trPoints);
 $trAreaPath  = 'M8,150 L' . implode(' L', $trPoints) . ' L592,150 Z';

/* -----------------------------------------------------
   FILTER + TAB COUNTS + PAGINATION (over ALL records)
----------------------------------------------------- */
 $tabCounts = ['all' => 0, 'paid' => 0, 'partial' => 0, 'pending' => 0, 'refunded' => 0];
foreach ($allBookings as $b) {
    if (!$matchSearch($b)) { continue; }
    $tabCounts['all']++;
    $ps = $b['payment_status'];
    if (isset($tabCounts[$ps])) {
        $tabCounts[$ps]++;
    }
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

 $viewEarningsSum = 0.0;
foreach ($filteredBookings as $b) {
    if ($isEarningRow($b)) {
        $viewEarningsSum += $bookingAmount($b);
    } elseif ($b['payment_status'] === 'refunded') {
        $viewEarningsSum += $refundFeeFn($b);
    }
}

 $buildUrl = function (array $overrides = []) use ($filter, $search, $page) {
    $params = ['filter' => $filter, 'q' => $search, 'page' => $page];
    foreach ($overrides as $k => $v) {
        if ($v === null) { unset($params[$k]); } else { $params[$k] = $v; }
    }
    if (($params['filter'] ?? '') === 'all') { unset($params['filter']); }
    if (($params['q'] ?? '') === '')         { unset($params['q']); }
    if ((int) ($params['page'] ?? 1) <= 1)   { unset($params['page']); }
    $qs = http_build_query($params);

    /* Never return '' — an empty href reloads the CURRENT url
       WITH its stale query params. A bare '?' is a real reset. */
    return $qs !== '' ? '?' . $qs : '?';
 };

 $activePage = 'earnings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Earnings — RoomHive</title>

<!-- Anti-flash dark-mode bootstrap -->
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
<style>
    .hp-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
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
        border-radius: 10px; font-size: 13px; font-family: inherit; min-width: 190px;
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

    .hp-status-partial  { background: #FDF1DC; color: #B07708; border: 1px solid rgba(237,164,35,.5); }
    .hp-status-refunded { background: #FCEAEA; color: #B3261E; border: 1px solid #F5B5B5; }
    .hp-status-pending  { background: #F0F1F6; color: #5B6172; border: 1px solid #E3E7EF; }

    .hp-cell-sub { display: block; font-size: 11px; color: #8B93A6; margin-top: 2px; }
    .hp-balance-note { display: block; font-size: 11px; color: #C77800; margin-top: 2px; }
    .hp-notcounted { display: block; font-size: 11px; color: #8B93A6; margin-top: 2px; font-style: italic; }
    .hp-fee-note { display: block; font-size: 11px; color: #1e7a3d; margin-top: 2px; }

    .hp-db-error {
        background: #FDECEC; border: 1px solid #F5B5B5; color: #A3282E;
        border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; font-size: 13px;
    }
    .hp-db-error code { background: #fff; padding: 2px 6px; border-radius: 6px; font-size: 12px; }

    .hp-pagination { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; justify-content: center; margin-top: 18px; }
    .hp-page-btn {
        min-width: 34px; padding: 7px 10px;
        border: 1px solid #E3E7EF; border-radius: 9px;
        text-align: center; font-size: 12.5px; font-weight: 700;
        color: #5B6172; text-decoration: none; background: #fff; transition: all .15s ease;
    }
    .hp-page-btn:hover { border-color: #F5A623; color: #B07708; }
    .hp-page-btn.active { background: #F5A623; border-color: #F5A623; color: #fff; }
    .hp-page-btn.disabled { opacity: .45; pointer-events: none; }
    .hp-page-info { font-size: 12px; color: #8B93A6; margin: 0 8px; }

    .hp-tfoot td {
        padding: 12px; border-top: 2px solid #EEF1F6;
        font-weight: 800; color: #14142B; font-size: 13px; background: #FAFAFD;
    }

    /* =====================================================
       ICON-FREE STAT PILLS — pure-CSS text badges replace
       the deleted <img> icons.
    ====================================================== */
    .hp-stat-pill-icon span {
        display: flex;
        align-items: center;
        justify-content: center;

        width: 100%;
        height: 100%;

        border-radius: inherit;

        font-family: "Poppins", sans-serif;
        font-size: 15px;
        font-weight: 800;
        line-height: 1;
    }

    .hp-pill-gold   { background: linear-gradient(135deg, #f6b93b, #eda423); color: #1c2a38; }
    .hp-pill-green  { background: linear-gradient(135deg, #2fbe7d, #1fa971); color: #ffffff; }
    .hp-pill-blue   { background: linear-gradient(135deg, #4a6fa5, #33517E); color: #ffffff; }

    /* =====================================================
       SVG AREA/LINE TREND CHART (native, no libraries)
    ====================================================== */
    .tr-chart { margin-top: 4px; }

    .tr-chart svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .tr-line {
        fill: none;
        stroke: #eda423;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .js .tr-anim-line {
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        transition: stroke-dashoffset 1.6s ease 0.15s;
    }

    .tr-chart.on .tr-anim-line {
        stroke-dashoffset: 0;
    }

    .tr-area {
        fill: rgba(237, 164, 35, 0.16);
        opacity: 1;
        transition: opacity 0.9s ease 0.8s;
    }

    .js .tr-chart:not(.on) .tr-area {
        opacity: 0;
    }

    .tr-chart.on .tr-area {
        opacity: 1;
    }

    .tr-dot {
        fill: #ffffff;
        stroke: #eda423;
        stroke-width: 2;
        cursor: pointer;
        transition: r 0.12s ease;
    }

    .tr-dot:hover {
        r: 4.5;
    }

    .tr-grid {
        stroke: rgba(28, 42, 56, 0.08);
        stroke-width: 1;
    }

    .tr-txt {
        font-size: 10px;
        fill: #8B93A6;
        font-family: "Poppins", sans-serif;
    }

    .tr-xlabels {
        display: flex;
        justify-content: space-between;

        margin-top: 6px;

        font-size: 10.5px;
        font-weight: 600;
        color: #8B93A6;
    }

    .tr-empty-note {
        margin: 8px 0 0;

        font-size: 11.5px;
        font-style: italic;
        color: #8B93A6;
    }

    /* Dark mode variants */
    body[data-theme="dark"] .tr-grid,
    html[data-theme-preview="1"] .tr-grid {
        stroke: rgba(232, 236, 241, 0.10);
    }

    body[data-theme="dark"] .tr-txt,
    html[data-theme-preview="1"] .tr-txt {
        fill: #8d99a5;
    }

    body[data-theme="dark"] .tr-dot,
    html[data-theme-preview="1"] .tr-dot {
        fill: #1a222b;
    }

    body[data-theme="dark"] .tr-area,
    html[data-theme-preview="1"] .tr-area {
        fill: rgba(237, 164, 35, 0.22);
    }

    body[data-theme="dark"] .tr-xlabels,
    html[data-theme-preview="1"] .tr-xlabels {
        color: #8d99a5;
    }

    body[data-theme="dark"] .tr-empty-note,
    html[data-theme-preview="1"] .tr-empty-note {
        color: #8d99a5;
    }

    @media (prefers-reduced-motion: reduce) {
        .js .tr-anim-line {
            transition: none;
            stroke-dashoffset: 0 !important;
        }

        .js .tr-chart:not(.on) .tr-area {
            opacity: 1;
        }
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
                <p class="hp-page-subtitle">
                    Full transaction ledger — every booking on your listings.
                    Earnings count confirmed/completed bookings, plus the
                    <?php echo h(number_format($refundFeeRate * 100, 0)); ?>% cancellation fee
                    kept from refunded ones.
                </p>
            </div>
            <div class="hp-stat-pillbox">
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-pill-gold"><span>&#8369;</span></div>
                    <div>
                        <p class="hp-stat-pill-label">Total Earnings</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($totalEarnings, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-pill-green"><span>M</span></div>
                    <div>
                        <p class="hp-stat-pill-label">This Month</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($thisMonth, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-pill-blue"><span>#</span></div>
                    <div>
                        <p class="hp-stat-pill-label">Transactions</p>
                        <p class="hp-stat-pill-value"><?php echo h(count($allBookings)); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($dbError !== null): ?>
            <section class="hp-db-error">
                <strong>Database error — records could not be loaded.</strong><br>
                <?php echo h($dbError); ?><br><br>
                Check that the <code>bookings</code> table exists and contains the expected columns
                (<code>total</code>, <code>amount_paid</code>, <code>host_payout_amount</code>, <code>status</code>).
                The full error was also written to the PHP error log.
            </section>
        <?php elseif ($debug): ?>
            <section class="hp-db-error" style="background:#EAF2FE;border-color:#B9D2F5;color:#1A56DB;">
                <strong>Debug (remove <code>&amp;debug=1</code> when done):</strong><br>
                Records loaded: <?php echo count($allBookings); ?> &middot;
                Earning rows: <?php echo (int) $earningCount; ?> (incl. refunded fee rows) &middot;
                Refunded: <?php echo (int) $refundedCount; ?> &middot;
                Refund fees earned: &#8369;<?php echo h(number_format($refundFeesEarned, 2)); ?>
                (rate <?php echo h(number_format($refundFeeRate * 100, 0)); ?>%) &middot;
                bookings columns detected: total=<?php echo $hasB('total') ? 'yes' : 'NO'; ?>,
                amount_paid=<?php echo $hasB('amount_paid') ? 'yes' : 'NO'; ?>,
                host_payout_amount=<?php echo $hasB('host_payout_amount') ? 'yes' : 'NO'; ?>,
                platform_fee_amount=<?php echo $hasB('platform_fee_amount') ? 'yes' : 'NO'; ?>,
                refunded_amount=<?php echo $hasB('refunded_amount') ? 'yes' : 'NO'; ?>
                <br>
                Active filter: <strong><?php echo h($filter); ?></strong> &middot;
                Search: &ldquo;<?php echo h($search); ?>&rdquo; &middot;
                After filter/search: <strong><?php echo (int) $totalRecords; ?></strong> &middot;
                On this page: <?php echo count($pageRecords); ?> &middot;
                Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?>
            </section>
        <?php endif; ?>

        <!-- ===============================================
             Last 6 Months — TWO chart types:
             1) SVG area/line trend (this card, top)
             2) Monthly comparison bars (below)
             Both native — no libraries.
        ================================================ -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>Last 6 Months</h3>
                <span class="hp-muted">Grouped by check-in date &middot; confirmed/completed + <?php echo h(number_format($refundFeeRate * 100, 0)); ?>% refund fees</span>
            </div>

            <!-- 1) SVG area/line trend -->
            <div class="tr-chart" id="trTrendChart">

                <svg viewBox="0 0 600 160" role="img" aria-label="Monthly earnings trend for the last 6 months">

                    <!-- Gridlines: max / mid / zero -->
                    <line class="tr-grid" x1="8" y1="18"  x2="592" y2="18"></line>
                    <line class="tr-grid" x1="8" y1="84"  x2="592" y2="84"></line>
                    <line class="tr-grid" x1="8" y1="150" x2="592" y2="150"></line>

                    <text class="tr-txt" x="2" y="13">&#8369;<?php echo h(number_format($chartMax, 0)); ?></text>
                    <text class="tr-txt" x="2" y="147">0</text>

                    <!-- Area fill under the line -->
                    <path class="tr-area" d="<?php echo h($trAreaPath); ?>"></path>

                    <!-- The line (animates via pathLength trick) -->
                    <polyline
                        class="tr-line tr-anim-line"
                        pathLength="1"
                        points="<?php echo h($trPointsStr); ?>"
                    ></polyline>

                    <!-- Hover points (native tooltips) -->
                    <?php foreach ($chart as $i => $c):
                        $x = 8 + ($i / max(1, count($chart) - 1)) * 584;
                        $y = 18 + (1 - ($c['value'] / $chartMax)) * 132;
                    ?>
                    <circle
                        class="tr-dot"
                        cx="<?php echo round($x, 1); ?>"
                        cy="<?php echo round($y, 1); ?>"
                        r="3"
                    >
                        <title>&#8369; <?php echo h(number_format($c['value'], 2)); ?> &mdash; <?php echo h($c['label']); ?></title>
                    </circle>
                    <?php endforeach; ?>

                </svg>

                <!-- X-axis month labels -->
                <div class="tr-xlabels">
                    <?php foreach ($chart as $c): ?>
                        <span><?php echo h($c['label']); ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if ($chartSum <= 0): ?>
                    <p class="tr-empty-note">
                        No confirmed earnings in the last 6 months yet —
                        newly confirmed bookings will plot here automatically.
                    </p>
                <?php endif; ?>

            </div>

            <!-- 2) Monthly comparison bars (existing) -->
            <div class="hp-bars" style="margin-top: 18px;">
                <?php foreach ($chart as $m): $pct = round(($m['value'] / $maxMonth) * 100); ?>
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

        <!-- ALL transactions -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>All Transactions</h3>
                <span class="hp-muted">
                    Avg &#8369;<?php echo h(number_format($avgBooking, 2)); ?> per earning booking
                    <?php if ($refundedCount > 0): ?>
                        &middot; &#8369;<?php echo h(number_format($refundedTotal, 2)); ?> refunded (<?php echo (int) $refundedCount; ?>)
                        &middot; &#8369;<?php echo h(number_format($refundFeesEarned, 2)); ?> fee kept
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($allBookings)): ?>

                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No transactions yet</p>
                    <p>
                        Records appear here as soon as any booking exists on one of your listings —
                        including pending applications. If you expect records, confirm the bookings
                        belong to listings where <code>listings.user_id</code> = your account.
                    </p>
                </div>

            <?php else: ?>

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
                            Unpaid<span class="cnt">(<?php echo (int) $tabCounts['pending']; ?>)</span>
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

                        <?php if ($filter !== 'all' && $search !== ''): ?>
                            <p>
                                Nothing matches <strong>&ldquo;<?php echo h($search); ?>&rdquo;</strong>
                                in the <strong><?php echo h(ucfirst($filter)); ?></strong> view.
                                You have <?php echo (int) $tabCounts['all']; ?> transaction<?php echo $tabCounts['all'] === 1 ? '' : 's'; ?> in total.
                            </p>
                        <?php elseif ($filter !== 'all'): ?>
                            <p>
                                You have <strong>0</strong> <?php echo h(ucfirst($filter)); ?>
                                transaction<?php echo ($tabCounts[$filter] ?? 0) === 1 ? '' : 's'; ?> &mdash; but
                                <strong><?php echo (int) $tabCounts['all']; ?></strong> in total across all statuses.
                            </p>
                        <?php elseif ($search !== ''): ?>
                            <p>
                                Nothing matches <strong>&ldquo;<?php echo h($search); ?>&rdquo;</strong>.
                                You have <?php echo (int) $tabCounts['all']; ?> transaction<?php echo $tabCounts['all'] === 1 ? '' : 's'; ?> in total.
                            </p>
                        <?php endif; ?>

                        <a class="hp-btn-mini" href="?">Clear filters &amp; search</a>
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
                                <th>Booking Status</th>
                                <th style="text-align:right;">Your Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRecords as $t):
                                $payLabel = $paymentMeta[$t['payment_status']][0] ?? ucfirst((string) $t['payment_status']);
                                $payClass = $paymentMeta[$t['payment_status']][1] ?? 'hp-status-pending';
                                $balance  = $bookingBalanceFn($t);

                                $bookedAtRaw = (string) ($t['created_at'] ?? '');

                                if (in_array($t['status'], ['confirmed', 'completed'], true)) {
                                    $bookClass = 'hp-status-active';
                                } elseif ($t['status'] === 'pending') {
                                    $bookClass = 'hp-status-pending';
                                } else {
                                    $bookClass = 'hp-status-refunded';
                                }
                            ?>
                            <tr>
                                <td>
                                    #<?php echo h(str_pad((string) $t['id'], 6, '0', STR_PAD_LEFT)); ?>
                                    <span class="hp-cell-sub">Booked <?php echo h($bookedAtRaw !== '' ? date('M j, Y', strtotime($bookedAtRaw)) : '—'); ?></span>
                                </td>
                                <td>
                                    <?php echo h(($t['checkin_date'] ?? '') ? date('M j, Y', strtotime($t['checkin_date'])) : ($bookedAtRaw !== '' ? date('M j, Y', strtotime($bookedAtRaw)) : '—')); ?>
                                    <?php if (!empty($t['checkout_date'])): ?>
                                        <span class="hp-cell-sub">&rarr; <?php echo h(date('M j, Y', strtotime($t['checkout_date']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($t['listing_title'] ?? ''); ?></td>
                                <td><?php echo h(($t['guest_name'] ?? '') !== '' ? $t['guest_name'] : '—'); ?></td>
                                <td>
                                    <span class="hp-status <?php echo h($payClass); ?>"><?php echo h($payLabel); ?></span>
                                    <?php if ($t['payment_status'] === 'partial'): ?>
                                        <span class="hp-balance-note">&#8369;<?php echo h(number_format($balance, 2)); ?> left</span>
                                    <?php elseif ($t['payment_status'] === 'pending'): ?>
                                        <span class="hp-balance-note">&#8369;<?php echo h(number_format($balance, 2)); ?> due</span>
                                    <?php elseif ($t['payment_status'] === 'refunded' && !empty($t['refunded_at'])): ?>
                                        <span class="hp-cell-sub"><?php echo h(date('M j, Y', strtotime($t['refunded_at']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hp-status <?php echo h($bookClass); ?>"><?php echo h(ucfirst((string) $t['status'])); ?></span>
                                    <?php if (!empty($t['payout_at'])): ?>
                                        <span class="hp-cell-sub">Paid out <?php echo h(date('M j, Y', strtotime($t['payout_at']))); ?></span>
                                    <?php endif; ?>
                                    <?php if (!$isEarningRow($t) && $t['payment_status'] !== 'refunded'): ?>
                                        <span class="hp-notcounted">counts when confirmed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="hp-amount" style="text-align:right;">
                                    <?php if ($t['payment_status'] === 'refunded'): ?>
                                        <!-- refunded: host keeps the 2% fee -->
                                        &#8369; <?php echo h(number_format($refundFeeFn($t), 2)); ?>
                                        <span class="hp-fee-note"><?php echo h(number_format($refundFeeRate * 100, 0)); ?>% fee of &#8369;<?php echo h(number_format($bookingAmount($t), 2)); ?></span>
                                    <?php else: ?>
                                        &#8369; <?php echo h(number_format($bookingAmount($t), 2)); ?>
                                        <?php if ($bookingTotalFn($t) > 0): ?>
                                            <span class="hp-cell-sub">of &#8369;<?php echo h(number_format($bookingTotalFn($t), 2)); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="hp-tfoot">
                                <td colspan="6">
                                    <?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $perPage, $totalRecords); ?>
                                    of <?php echo (int) $totalRecords; ?> record<?php echo $totalRecords === 1 ? '' : 's'; ?>
                                    (full value + <?php echo h(number_format($refundFeeRate * 100, 0)); ?>% refund fees, this view)
                                </td>
                                <td style="text-align:right;">&#8369; <?php echo h(number_format($viewEarningsSum, 2)); ?></td>
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
                            <a class="hp-page-btn" href="<?php echo h($buildUrl(['page' => $totalPages])); ?>"><?php echo (int) $totalPages; ?></a>
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

<!-- =========================================================
     TREND CHART ANIMATION (self-contained, native JS)
     Line draw-in + area fade when the chart scrolls into
     view. Reduced-motion / no-JS render it fully immediately.
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var trChart = document.getElementById("trTrendChart");

    if (!trChart) { return; }

    if (reduced || !("IntersectionObserver" in window)) {
        trChart.classList.add("on");
        return;
    }

    var tIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) { return; }
            tIO.unobserve(entry.target);
            entry.target.classList.add("on");
        });
    }, { threshold: 0.35 });

    tIO.observe(trChart);
})();
</script>

</body>
</html>