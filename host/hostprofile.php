<?php
/* =========================================================
   ROOMHIVE — HOST PROFILE (Overview) — DELUXE
   hostprofile.php

   Uses the shared host shell (host_init + host_navbar +
   host_sidebar + host_footer).

   SCHEMA NOTE: bookings has NO `total` and NO `paid_at`.
   Money = host_payout_amount (falls back to amount_paid);
   confirmed/completed status is treated as paid.

   NEW (real data): time-based greeting, days-as-host,
   6-month monthly earnings (for the trend chart + sparkline).
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

/* Views — no page-view tracking table exists yet */
 $total_views = 0;

/* -----------------------------------------------------
   BOOKING-BASED METRICS
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

 $earningsNote = $total_bookings > 0
    ? 'From ' . $total_bookings . ' confirmed booking' . ($total_bookings === 1 ? '' : 's')
    : 'No earnings data yet';

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
   NEW — GREETING + TENURE
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
   NEW — MONTHLY EARNINGS, LAST 6 MONTHS (real query)
   Powers the trend chart + the sparkline in the float card.
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
</head>
<body>

    <?php include __DIR__ . '/host_navbar.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- =========================================================
     HERO — greeting, shimmer name, floating hexes,
     parallax image, animated earnings card + sparkline
========================================================= -->
<section class="hp-hero">

    <div aria-hidden="true">
        <span class="hp-blob hp-blob-1"></span>
        <span class="hp-blob hp-blob-2"></span>
        <span class="hp-hex-float hp-hex-1"></span>
        <span class="hp-hex-float hp-hex-2"></span>
        <span class="hp-hex-float hp-hex-3"></span>
    </div>

    <div class="hp-hero-text">

        <span class="hp-hero-badge hp-anim" style="--d: .05s;">

            <span class="hp-pulse-dot"></span>

            Host Dashboard &middot; Day <?php echo (int) $daysAsHost; ?>

        </span>


        <h1 class="hp-anim" style="--d: .15s;">

            <?php echo h($greeting); ?>,

            <span class="hp-shimmer"><?php echo h($firstName); ?></span>!

        </h1>


        <span class="hp-hero-underline hp-anim" style="--d: .22s;"></span>


        <p class="hp-anim" style="--d: .28s;">

            You're managing
            <strong><?php echo (int) $listings_total; ?>
                listing<?php echo $listings_total === 1 ? '' : 's'; ?></strong>
            — track bookings, occupancy, and payouts below.

        </p>

    </div>


    <div class="hp-hero-image hp-anim" style="--d: .3s;">

        <img
            src="/webprogg/images/hostprofile-hero.jpg"
            alt=""
            onerror="this.style.display='none';"
        >

    </div>


    <div class="hp-earnings-float" id="hpEarningsFloat">

        <span class="hp-muted">Total Earnings</span>

        <strong>
            &#8369;
            <span
                data-count="<?php echo (float) $total_earnings; ?>"
                data-decimals="2"
            ><?php echo h(number_format($total_earnings, 2)); ?></span>
        </strong>

        <span class="hp-earnings-note"><?php echo h($earningsNote); ?></span>

        <!-- NEW: 6-month sparkline (real monthly data) -->
        <div class="hp-spark" aria-hidden="true">

            <?php foreach ($earnMonths as $m):

                $pct = $maxMonthTotal > 0
                    ? (int) round(($m['total'] / $maxMonthTotal) * 100)
                    : 0;

                $pct = max(6, $pct); /* keep a visible nub */
            ?>

                <span
                    class="hp-spark-bar"
                    style="--h: <?php echo (int) $pct; ?>%;"
                    title="<?php echo h($m['label']); ?>"
                ></span>

            <?php endforeach; ?>

        </div>

    </div>

</section>

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

    <!-- PERFORMANCE OVERVIEW (occupancy = animated donut) -->
    <section class="hp-card hp-spotlight hp-reveal" style="--i: 1;">
      <div class="hp-card-header">
        <h3>Performance Overview</h3>
        <span class="hp-muted">Last 30 days</span>
      </div>

      <div class="hp-stats">

        <div class="hp-stat-card hp-reveal" style="--i: 0;">
          <img src="/webprogg/images/viewsicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Views</span>
            <strong
                data-count="<?php echo (int) $total_views; ?>"
                data-decimals="0"><?php echo h($total_views); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card hp-reveal" style="--i: 1;">
          <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Bookings</span>
            <strong
                data-count="<?php echo (int) $total_bookings; ?>"
                data-decimals="0"><?php echo h($total_bookings); ?></strong>
          </div>
        </div>

        <!-- NEW: occupancy as an animated donut ring -->
        <div class="hp-stat-card hp-reveal" style="--i: 2;">
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
          </div>
        </div>

        <div class="hp-stat-card hp-reveal" style="--i: 3;">
          <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Earnings</span>
            <strong>
                &#8369;
                <span
                    data-count="<?php echo (float) $total_earnings; ?>"
                    data-decimals="2"><?php echo h(number_format($total_earnings, 2)); ?></span>
            </strong>
          </div>
        </div>

      </div>
    </section>

    <!-- NEW — EARNINGS TREND (real 6-month chart) -->
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

          <?php foreach ($listings as $listing): ?>
            <div class="hp-listing-row">

              <div class="hp-listing-info">
                <img src="<?php echo h(resolve_photo($listing['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="<?php echo h($listing['title']); ?>">
                <div>
                  <h4><?php echo h($listing['title']); ?></h4>
                  <p><?php echo h($listing['location']); ?></p>
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
                <span class="hp-status <?php echo hp_status_class($listing['status']); ?>">
                  <?php echo h(hp_status_label($listing['status'])); ?>
                </span>

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
                    <button type="button" class="hp-menu-item hp-menu-delete" data-listing-id="<?php echo h($listing['id']); ?>" data-listing-title="<?php echo h($listing['title']); ?>">
                      Delete Listing
                    </button>
                  </div>
                </div>
              </div>

            </div>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>
    </section>

    <!-- GROW YOUR HOSTING BUSINESS -->
    <section class="hp-grow-banner hp-reveal">

      <div class="hp-grow-text">
        <img src="/webprogg/images/grow-hosting-illustration.png" alt="" class="hp-grow-illustration" onerror="this.style.display='none';">
        <div>
          <h3>Grow Your Hosting Business</h3>
          <p>Get more bookings and increase your earnings with these host tools.</p>
        </div>
      </div>

      <div class="hp-grow-links">

        <a href="/webprogg/host/helpcenter.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128640;</span>
          <strong>Boost Your Listing</strong>
          <span>Get more visibility</span>
        </a>

        <a href="/webprogg/host/helpcenter.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128161;</span>
          <strong>Host Tips</strong>
          <span>Learn and improve</span>
        </a>

        <a href="/webprogg/misc/contacts.php" class="hp-grow-card">
          <span class="hp-grow-icon">&#128101;</span>
          <strong>Invite &amp; Earn</strong>
          <span>Earn more rewards</span>
        </a>

      </div>

    </section>

  </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>

<!-- =========================================================
     DELUXE SCRIPT — theme, reveal, count-ups, donut, bars,
     spotlight, tilt, parallax. Self-contained.
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

    /* ---- Count-ups (earnings float + stat cards) ---- */
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

    /* ---- 3D tilt on the earnings float ---- */
    var fl = document.getElementById("hpEarningsFloat");

    if (fl && !reduced && window.matchMedia("(hover: hover)").matches) {
        fl.addEventListener("animationend", function () {
            fl.classList.add("no-anim");
        }, { once: true });

        fl.addEventListener("pointermove", function (e) {
            var r = fl.getBoundingClientRect();
            var px = (e.clientX - r.left) / r.width - 0.5;
            var py = (e.clientY - r.top) / r.height - 0.5;

            fl.style.transform =
                "perspective(700px) rotateX(" + (-py * 8).toFixed(2) + "deg)" +
                " rotateY(" + (px * 8).toFixed(2) + "deg) translateY(-2px)";
        });

        fl.addEventListener("pointerleave", function () {
            fl.style.transform = "";
        });
    }

    /* ---- Gentle parallax on the hero image ---- */
    var heroImg = document.querySelector(".hp-hero-image");

    if (heroImg && !reduced && window.innerWidth > 900) {
        window.addEventListener("scroll", function () {
            var y = window.scrollY;
            if (y < 700) {
                heroImg.style.transform = "translateY(" + (y * 0.06).toFixed(1) + "px)";
            }
        }, { passive: true });
    }
})();
</script>

<!-- =========================================================
     LISTING 3-DOT MENU + DELETE (unchanged)
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