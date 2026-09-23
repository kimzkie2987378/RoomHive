<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userpayments.php

   === ACCURATE PAYMENT TRACKING (this version) ===
   - BOOKINGS: now use amount_paid vs total — a 50% reserve
     shows as "Partial" with its remaining balance, not as a
     fully-spent total. Refunded bookings are tracked as
     refunds, not spending.
   - HIVE CLUB: pending / paid / refunded / cancelled all
     represented with their real statuses.
   - TOTALS (all real money):
       * Paid This Week / Paid All Time = sum(amount_paid)
         excluding refunds
       * Pending to Pay = unpaid booking balances
         (total - amount_paid, non-cancelled) + pending
         Hive Club transactions
       * Refunded = refunded amounts
   - FILTER TABS: All / Bookings / Hive Club / Paid /
     Pending / Refunded — with "X of Y" result counts.
   - RESILIENT: LEFT JOIN so bookings whose listing was
     deleted still appear; each query guarded.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host FROM users WHERE id = :id LIMIT 1"
);
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);

/* Real unread bell count */
 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
 );
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* =========================================================
   BOOKING PAYMENTS — real paid vs remaining
========================================================= */
 $bookingPayments = [];
 $bookingsTotal   = 0;

try {
    $bookingsStmt = $pdo->prepare(
        "SELECT b.id, b.total, b.amount_paid, b.status, b.payment_status,
                b.booked_at, COALESCE(l.title, 'Listing removed') AS title
         FROM bookings b
         LEFT JOIN listings l ON l.id = b.listing_id
         WHERE b.user_id = :id
         ORDER BY b.booked_at DESC"
    );
    $bookingsStmt->execute(['id' => $_SESSION['user_id']]);
    $rows = $bookingsStmt->fetchAll();

    $bookingsTotal = count($rows);

    $bookingPayments = array_map(function ($row) {
        $total     = (float) $row['total'];
        $paid      = (float) ($row['amount_paid'] ?? 0);
        $remaining = round(max(0, $total - $paid), 2);
        $refunded  = ($row['payment_status'] === 'refunded');

        /* Accurate payment state */
        if ($refunded) {
            $payState = 'refunded';
        } elseif ($paid > 0.005 && $remaining <= 0.005) {
            $payState = 'paid';
        } elseif ($paid > 0.005) {
            $payState = 'partial';
        } else {
            $payState = 'unpaid';
        }

        return [
            'cat'       => 'booking',
            'type'      => 'Booking',
            'label'     => $row['title'],
            'ref'       => 'Booking #' . (int) $row['id'],
            'link'      => '/webprogg/booking/booking-details.php?id=' . (int) $row['id'],
            'total'     => $total,
            'paid'      => $paid,
            'remaining' => $remaining,
            'date'      => $row['booked_at'],
            'status'    => $row['status'],
            'pay_status'=> $row['payment_status'],
            'pay_state' => $payState,
        ];
    }, $rows);
} catch (PDOException $e) {
    error_log('userpayments: bookings query failed: ' . $e->getMessage());
    $bookingPayments = [];
}

/* =========================================================
   HIVE CLUB PAYMENTS
========================================================= */
 $membershipPayments = [];
 $membershipTotal    = 0;

try {
    $txnStmt = $pdo->prepare(
        "SELECT id, amount, purchased_at, payment_status
         FROM hiveclub_transactions
         WHERE user_id = :id
         ORDER BY purchased_at DESC"
    );
    $txnStmt->execute(['id' => $_SESSION['user_id']]);
    $rows = $txnStmt->fetchAll();

    $membershipTotal = count($rows);

    $membershipPayments = array_map(function ($row) {
        $amount   = (float) $row['amount'];
        $refunded = ($row['payment_status'] === 'refunded');

        if ($refunded) {
            $payState = 'refunded';
        } elseif ($row['payment_status'] === 'paid') {
            $payState = 'paid';
        } elseif ($row['payment_status'] === 'cancelled') {
            $payState = 'refunded'; /* cancelled = no money movement */
        } else {
            $payState = 'unpaid';
        }

        return [
            'cat'       => 'hive',
            'type'      => 'Hive Club',
            'label'     => 'Membership payment',
            'ref'       => 'Transaction #' . (int) $row['id'],
            'link'      => '/webprogg/user/membership.php',
            'total'     => $amount,
            'paid'      => ($payState === 'paid') ? $amount : 0,
            'remaining' => ($payState === 'unpaid') ? $amount : 0,
            'date'      => $row['purchased_at'],
            'status'    => $row['payment_status'],
            'pay_status'=> $row['payment_status'],
            'pay_state' => $payState,
        ];
    }, $rows);
} catch (PDOException $e) {
    error_log('userpayments: hiveclub query failed: ' . $e->getMessage());
    $membershipPayments = [];
}

/* =========================================================
   MERGE + SORT (newest first)
========================================================= */
 $paymentHistory = array_merge($bookingPayments, $membershipPayments);
usort($paymentHistory, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});

 $historyTotal = count($paymentHistory);

/* =========================================================
   TOTALS — real money movement
========================================================= */
 $oneWeekAgo      = strtotime('-7 days');
 $spent_this_week = 0.0;
 $spent_all_time  = 0.0;
 $pending_to_pay  = 0.0;
 $refunded_total  = 0.0;

foreach ($paymentHistory as $p) {

    if ($p['pay_state'] === 'refunded') {
        $refunded_total += $p['paid'];
        continue;
    }

    /* money actually paid */
    $spent_all_time += $p['paid'];
    if (strtotime($p['date']) >= $oneWeekAgo) {
        $spent_this_week += $p['paid'];
    }

    /* money still owed */
    if ($p['pay_state'] === 'unpaid' || $p['pay_state'] === 'partial') {
        if (!($p['cat'] === 'booking' && in_array($p['status'], ['cancelled', 'rejected'], true))) {
            $pending_to_pay += $p['remaining'];
        }
    }
}

 $payment_methods = [];
 $activeSidebar   = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>
<style>
    /* Filter tabs (reuses .ub-tabs base from myaccount.css) */
    .upay-filters {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        padding: 4px;
        background: #f6f7f9;
        border-radius: 14px;
        margin-bottom: 18px;
        width: fit-content;
        max-width: 100%;
    }
    .upay-tab {
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: var(--up-text-muted, #6b7684);
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .upay-tab:hover { color: var(--up-navy, #1c2a38); }
    .upay-tab.active {
        background: #ffffff;
        color: #b07708;
        box-shadow: 0 2px 8px rgba(28, 42, 56, 0.1);
    }
    .upay-tab .cnt { opacity: .65; margin-left: 3px; }

    /* Payment row extras */
    .upay-ref { font-size: 10.5px; color: #999999; }
    .upay-remaining { display: block; font-size: 10.5px; color: #C77A00; font-weight: 600; }
    .upay-total { display: block; font-size: 10.5px; color: #999999; }

    /* Refunded row dimming */
    .up-booking-row.upay-refunded { opacity: .62; }
    .up-booking-row.upay-refunded:hover { transform: none; }

    .up-status-paid     { background: var(--up-green-bg, #e8f8f1); color: var(--up-green, #1fa971); }
    .up-status-partial  { background: #FFF1DC; color: #B07708; }
    .up-status-unpaid   { background: #F0F0F0; color: #777777; }
    .up-status-refunded { background: #EAF2FE; color: #2F7DE1; }

    .js-hidden { display: none !important; }
</style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<!-- PAGE HEADER -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Payments</span>
    <h1>Your payment history</h1>
    <p class="ub-lead">Every booking charge and Hive Club payment, tracked down to the peso.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <!-- TOTALS ROW (real money: paid / pending / refunded) -->
    <section class="up-stats">
      <div class="up-stat-card up-reveal" style="--i: 0;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($spent_this_week); ?>" data-decimals="2"><?php echo h(number_format($spent_this_week, 2)); ?></span></strong>
          <span>Paid This Week</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 1;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($spent_all_time); ?>" data-decimals="2"><?php echo h(number_format($spent_all_time, 2)); ?></span></strong>
          <span>Total Paid All Time</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 2;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($pending_to_pay); ?>" data-decimals="2"><?php echo h(number_format($pending_to_pay, 2)); ?></span></strong>
          <span>Pending to Pay</span>
        </div>
      </div>
      <div class="up-stat-card up-reveal" style="--i: 3;">
        <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
        <div>
          <strong>&#8369; <span data-count="<?php echo h($refunded_total); ?>" data-decimals="2"><?php echo h(number_format($refunded_total, 2)); ?></span></strong>
          <span>Refunded</span>
        </div>
      </div>
    </section>

    <div class="up-two-col">

      <!-- PAYMENT HISTORY -->
      <div class="up-card up-bookings-card up-reveal" style="--i: 1;">
        <div class="up-card-header">
          <h3>Payment History</h3>
          <span class="up-link-view-all" id="upayShown" style="text-decoration:none; cursor:default;">
            <?php echo h($historyTotal); ?> record<?php echo $historyTotal === 1 ? '' : 's'; ?>
          </span>
        </div>

        <!-- FILTER TABS -->
        <div class="upay-filters" id="upayTabs">
          <button type="button" class="upay-tab active" data-filter="all">All <span class="cnt">(<?php echo (int) $historyTotal; ?>)</span></button>
          <button type="button" class="upay-tab" data-filter="booking">Bookings <span class="cnt">(<?php echo (int) $bookingsTotal; ?>)</span></button>
          <button type="button" class="upay-tab" data-filter="hive">Hive Club <span class="cnt">(<?php echo (int) $membershipTotal; ?>)</span></button>
          <button type="button" class="upay-tab" data-filter="paid">Paid</button>
          <button type="button" class="upay-tab" data-filter="pending">Pending</button>
          <button type="button" class="upay-tab" data-filter="refunded">Refunded</button>
        </div>

        <?php if (empty($paymentHistory)): ?>

          <div class="up-bookings-empty">
            <p class="up-bookings-empty-title">No payments yet</p>
            <p class="up-bookings-empty-text">Charges from bookings and Hive Club will show up here.</p>
            <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
          </div>

        <?php else: ?>

          <div id="upayList">

          <?php foreach ($paymentHistory as $payment): ?>
            <div
              class="up-booking-row up-row-static upay-row<?php echo $payment['pay_state'] === 'refunded' ? ' upay-refunded' : ''; ?>"
              data-cat="<?php echo h($payment['cat']); ?>"
              data-pay="<?php echo h($payment['pay_state']); ?>"
            >
              <div class="up-booking-info">
                <h4><?php echo h($payment['label']); ?></h4>
                <p class="up-booking-location">
                  <?php echo h($payment['type']); ?>
                  &middot; <span class="upay-ref"><?php echo h($payment['ref']); ?></span>
                </p>
                <p class="up-booking-dates">
                  <img src="/webprogg/images/calendaricon-userprofile.png" alt="">
                  <?php echo h(date('M j, Y', strtotime($payment['date']))); ?>
                </p>
              </div>
              <div class="up-booking-side">
                <span class="up-status up-status-<?php echo h($payment['pay_state']); ?>">
                  <?php
                    echo h([
                        'paid'     => 'Paid',
                        'partial'  => 'Partially Paid',
                        'unpaid'   => 'Unpaid',
                        'refunded' => 'Refunded',
                    ][$payment['pay_state']] ?? ucfirst($payment['pay_state']));
                  ?>
                </span>
                <strong>&#8369; <?php echo h(number_format($payment['paid'], 2)); ?></strong>

                <?php if ($payment['pay_state'] === 'partial'): ?>
                  <span class="upay-remaining">
                    &#8369; <?php echo h(number_format($payment['remaining'], 2)); ?> remaining
                  </span>
                <?php elseif ($payment['pay_state'] === 'unpaid'): ?>
                  <span class="upay-remaining">
                    &#8369; <?php echo h(number_format($payment['remaining'], 2)); ?> to pay
                  </span>
                <?php elseif ($payment['pay_state'] === 'paid'): ?>
                  <span class="upay-total">of &#8369; <?php echo h(number_format($payment['total'], 2)); ?></span>
                <?php else: ?>
                  <span class="upay-total">refunded</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>

          </div>

          <!-- Empty result for the active filter -->
          <div class="up-bookings-empty js-hidden" id="upayEmpty">
            <p class="up-bookings-empty-title">Nothing matches this filter</p>
            <p class="up-bookings-empty-text">Try a different tab to see other payments.</p>
          </div>

        <?php endif; ?>
      </div>

      <!-- PAYMENT METHODS + HELP -->
      <div class="up-right-col">
        <div class="up-card up-payment-methods up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Payment Methods</h3>
          </div>

          <?php if (empty($payment_methods)): ?>
            <div class="up-payment-methods-empty">
              <p>No payment methods yet</p>
              <p>Payments are settled with the host or support.</p>
            </div>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <div class="up-card-item">
                <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>">
                <div>
                  <strong>&#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; &#8226;&#8226;&#8226;&#8226; <?php echo h($method['last4']); ?></strong>
                  <span>Expires <?php echo h($method['expires']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="up-need-help up-reveal" style="--i: 3;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about a charge? We're here 24/7.</p>
            <a href="/webprogg/misc/contacts.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>
            <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
            <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>kimdivino55@gmail.com</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
        </div>
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studio-loft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=shared-bedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entire-house">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>
        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/host/howitworks.php">How It Works</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>
        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>

<!-- Reveal + money count-up + FILTER TABS (self-contained) -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
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

    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var countIo = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                countIo.unobserve(el);

                var target = parseFloat(el.getAttribute("data-count")) || 0;
                var decimals = parseInt(el.getAttribute("data-decimals"), 10) || 0;
                var t0 = null;
                var DURATION = 1300;

                var stepFn = function (ts) {
                    if (!t0) t0 = ts;
                    var k = Math.min((ts - t0) / DURATION, 1);
                    var eased = 1 - Math.pow(1 - k, 3);
                    el.textContent = (target * eased).toLocaleString(
                        undefined,
                        { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
                    );
                    if (k < 1) window.requestAnimationFrame(stepFn);
                };

                window.requestAnimationFrame(stepFn);
            });
        }, { threshold: 0.6 });
        Array.prototype.forEach.call(counters, function (el) { countIo.observe(el); });
    }

    /* =========================================
       PAYMENT FILTER TABS
       all | booking | hive | paid | pending | refunded
    ========================================== */
    var tabs   = document.getElementById('upayTabs');
    var list   = document.getElementById('upayList');
    var empty  = document.getElementById('upayEmpty');
    var shown  = document.getElementById('upayShown');

    if (!tabs || !list) { return; }

    var rows    = Array.prototype.slice.call(list.querySelectorAll('.upay-row'));
    var current = 'all';

    function applyFilter() {
        var visible = 0;

        rows.forEach(function (row) {
            var cat = row.getAttribute('data-cat');
            var pay = row.getAttribute('data-pay');

            var show = false;

            if (current === 'all') {
                show = true;
            } else if (current === 'booking' || current === 'hive') {
                show = (cat === current);
            } else if (current === 'paid') {
                show = (pay === 'paid' || pay === 'partial');
            } else if (current === 'pending') {
                show = (pay === 'unpaid' || pay === 'partial');
            } else if (current === 'refunded') {
                show = (pay === 'refunded');
            }

            row.classList.toggle('js-hidden', !show);
            if (show) { visible++; }
        });

        /* toggle empty state */
        if (empty) {
            empty.classList.toggle('js-hidden', visible > 0);
        }
        /* update the shown counter */
        if (shown) {
            shown.textContent = visible + ' shown';
        }
    }

    tabs.querySelectorAll('.upay-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.querySelectorAll('.upay-tab').forEach(function (t) {
                t.classList.remove('active');
            });
            tab.classList.add('active');
            current = tab.getAttribute('data-filter');
            applyFilter();
        });
    });
})();
</script>

</body>
</html>