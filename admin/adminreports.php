<?php
require_once __DIR__ . '/admin_init.php';

/* =========================================================
   ROOMHIVE ADMIN — REPORTS
   Platform performance over the last 6 months.

   === THIS VERSION — NATIVE CHARTS (Chart.js removed) ===
   The Chart.js CDN + configs are gone. Each dataset now uses
   a best-fit pure SVG/CSS chart (renders with no internet,
   no libraries) — and every point/bar shows its NUMBER:
     - Revenue 6 months  -> vertical bars, ₱ value above each
     - Bookings 6 months -> SVG area/line, count above each dot
     - New Users 6 months-> horizontal bars (data was already
       queried but never charted — now visualized)
   Animated via IntersectionObserver with stagger; no-JS and
   prefers-reduced-motion render charts fully and instantly.
========================================================= */

/* ---- Build 6-month series ---- */
 $months = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -{$i} months");
    $months[date('Y-m', $ts)] = [
        'label'    => date('M Y', $ts),
        'short'    => date('M', $ts),
        'bookings' => 0,
        'revenue'  => 0.0,
        'users'    => 0,
    ];
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

 $monthRows     = array_values($months);
 $chartLabels   = array_column($monthRows, 'label');
 $bookingSeries = array_column($monthRows, 'bookings');
 $revenueSeries = array_column($monthRows, 'revenue');
 $userSeries    = array_column($monthRows, 'users');

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

/* =========================================================
   NATIVE CHART PREP — pre-build SVG/CSS values in PHP
========================================================= */

/* --- 1) Bookings 6-month area/line (with per-point numbers) --- */
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
        'ly' => round(max(11, $y - 9), 1), /* number sits above the dot */
        'v'  => (int) $v,
        'label' => $chartLabels[$i],
    ];
}
 $bookPointsStr = implode(' ', $bookPts);
 $bookAreaPath  = 'M8,150 L' . implode(' L', $bookPts) . ' L592,150 Z';

/* --- 2) Revenue 6-month vertical bars (with ₱ values) --- */
 $revMax = max(0.01, max($revenueSeries ?: [0]));
 $repMoneyShort = function ($v) {
    $v = (float) $v;
    if ($v >= 1000000) { return number_format($v / 1000000, 1) . 'M'; }
    if ($v >= 1000)    { return number_format($v / 1000, 1) . 'K'; }
    return number_format($v, 0);
 };
 $revBars = [];
foreach ($monthRows as $m) {
    $revBars[] = [
        'label' => $m['short'],
        'full'  => $m['label'],
        'v'     => (float) $m['revenue'],
        'short' => '₱' . $repMoneyShort($m['revenue']),
        'pct'   => $m['revenue'] > 0 ? max(2, (int) round(($m['revenue'] / $revMax) * 100)) : 2,
    ];
}

/* --- 3) New Users 6-month horizontal bars (with counts) --- */
 $userMax = max(1, max($userSeries ?: [0]));
 $userBars = [];
foreach ($monthRows as $m) {
    $userBars[] = [
        'label' => $m['label'],
        'v'     => (int) $m['users'],
        'pct'   => $m['users'] > 0 ? max(3, (int) round(($m['users'] / $userMax) * 100)) : 0,
    ];
}
?>
<?php admin_page_start('RoomHive Admin — Reports', 'Reports'); ?>

<!-- Progressive enhancement: enable chart animations only
     when JS runs (charts render fully without it). -->
<script>document.documentElement.classList.add("js");</script>

<style>
    /* =====================================================
       NATIVE CHARTS — no libraries; numbers always visible
    ====================================================== */

    /* ---- shared animation gate ---- */
    .js [data-anim-chart]:not(.on) .al-area { opacity: 0; }
    .js [data-anim-chart]:not(.on) .al-line { stroke-dashoffset: 1; }
    .js [data-anim-chart]:not(.on) .al-val  { opacity: 0; }
    .js [data-anim-chart]:not(.on) .vbar    { height: 0 !important; }
    .js [data-anim-chart]:not(.on) .hbar    { width: 0 !important; }

    /* ---- SVG area/line (Bookings) ---- */
    .rp-linechart { margin-top: 6px; }
    .rp-linechart svg { width: 100%; height: auto; display: block; }
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

    /* ---- Vertical bars (Revenue) ---- */
    .rp-vbars {
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

    /* ---- Horizontal bars (New Users) ---- */
    .rp-hbars {
        display: flex;
        flex-direction: column;
        gap: 10px;

        padding: 6px 0;
    }
    .hbar-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .hbar-label {
        width: 70px;
        flex-shrink: 0;

        font-size: 11.5px;
        font-weight: 600;
        color: #5B6172;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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
    .hbar-purple { background: linear-gradient(90deg, #b08ae0, #9B6BC3); }
    .hbar-val {
        width: 34px;
        flex-shrink: 0;

        font-size: 12px;
        font-weight: 700;
        color: #14142B;
        text-align: right;
    }

    /* ---- sub-section headings inside the big panel ---- */
    .rp-subhead {
        margin: 18px 0 6px;
        font-size: 13px;
        font-weight: 700;
        color: #14142B;
    }
    .rp-subhead:first-child { margin-top: 0; }

    @media (prefers-reduced-motion: reduce) {
        .al-line,
        .al-area,
        .al-val,
        .vbar,
        .hbar {
            transition: none !important;
        }
        .js [data-anim-chart]:not(.on) .al-area { opacity: 1; }
        .js [data-anim-chart]:not(.on) .al-line { stroke-dashoffset: 0; }
        .js [data-anim-chart]:not(.on) .al-val  { opacity: 1; }
        .js [data-anim-chart]:not(.on) .vbar    { height: var(--h) !important; }
        .js [data-anim-chart]:not(.on) .hbar    { width: var(--w) !important; }
    }
</style>

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
        <div class="panel-header"><h2>Last 6 Months</h2></div>

        <!-- ===============================================
             1) REVENUE — vertical bars, ₱ value above each
        ================================================= -->
        <p class="rp-subhead">Revenue (₱)</p>
        <div class="rp-vbars" data-anim-chart>
            <?php foreach ($revBars as $b): ?>
            <div class="vbar-col" title="<?= htmlspecialchars($b['full']) ?>: ₱<?= number_format($b['v'], 2) ?>">
                <span class="vbar-val"><?= htmlspecialchars($b['short']) ?></span>
                <div class="vbar-track">
                    <div class="vbar" style="--h: <?= (int) $b['pct'] ?>%;"></div>
                </div>
                <span class="vbar-lbl"><?= htmlspecialchars($b['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===============================================
             2) BOOKINGS — SVG area/line, count above each dot
        ================================================= -->
        <p class="rp-subhead">Bookings</p>
        <div class="rp-linechart" data-anim-chart>
            <svg viewBox="0 0 600 160" role="img" aria-label="Bookings for the last 6 months">
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
                <!-- the number for each month -->
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

        <!-- ===============================================
             3) NEW USERS — horizontal bars (was queried but
                never charted before)
        ================================================= -->
        <p class="rp-subhead">New Users</p>
        <div class="rp-hbars" data-anim-chart>
            <?php foreach ($userBars as $ub): ?>
            <div class="hbar-row" title="<?= htmlspecialchars($ub['label']) ?>: <?= (int) $ub['v'] ?> new user<?= $ub['v'] === 1 ? '' : 's' ?>">
                <span class="hbar-label"><?= htmlspecialchars($ub['label']) ?></span>
                <div class="hbar-track">
                    <div class="hbar hbar-purple" style="--w: <?= (int) $ub['pct'] ?>%;"></div>
                </div>
                <span class="hbar-val"><?= (int) $ub['v'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
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

<!-- =====================================================
     NATIVE CHART ANIMATIONS — one observer, staggered.
     Charts are fully rendered in the markup; .on just
     triggers their CSS transitions.
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

            entry.target.querySelectorAll(".vbar, .hbar").forEach(function (seg, i) {
                seg.style.transitionDelay = (i * 50) + "ms";
            });

            entry.target.classList.add("on");
        });
    }, { threshold: 0.3 });

    charts.forEach(function (c) { cIO.observe(c); });
})();
</script>

<?php admin_page_end(); ?>