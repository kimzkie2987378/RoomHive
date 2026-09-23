<?php
/* =========================================================
   ROOMHIVE — HOST BOOKINGS
   hostbookings.php

   Uses the shared host dashboard shell (host_init.php +
   host_navbar.php + host_sidebar.php + host_footer.php).

   NEW (Hive Club Phase 3):
     - Stay-completion sweep wired in: confirmed bookings past
       checkout auto-complete on page load, and completed
       bookings award Hive Club points to the tenant
       (1 pt per P10, ledger-guarded, idempotent).
     - "Mark Completed" button on CONFIRMED rows + floating
       confirm card -> complete-booking.php. Long-term stays
       (no checkout date) complete ONLY through this button.

   NEW (consistency):
     - 'rejected' status: Declined badge + tab + filter
       (bookingaction.php's unified decline sets 'rejected';
       previously those bookings only appeared under "All").

   KEPT: receipt integration (payment pill, View Receipt,
   Signed note), live search, tabs, CSRF accept/decline.
========================================================= */

require_once __DIR__ . '/host_init.php';

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change to becomeahost.php once appropriate
    exit;
}

/* -----------------------------------------------------
   HIVE CLUB — stay-completion sweep + point awards.
   Lazy: flips stale confirmed stays to completed and awards
   points once per booking (ledger-guarded). Cheap no-op
   when there is nothing to do.
----------------------------------------------------- */
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/booking_autocomplete.php';

/* -----------------------------------------------------
   CSRF TOKEN
----------------------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['csrf_token'];

/* -----------------------------------------------------
   BOOKINGS
----------------------------------------------------- */
 $bookingsStmt = $pdo->prepare(
    "SELECT
        b.id,
        b.status,
        b.total,
        b.amount_paid,
        b.host_payout_amount,
        b.checkin_date,
        b.checkout_date,
        b.guests,
        b.created_at,
        l.title    AS listing_title,
        l.location AS listing_location,
        u.name     AS guest_name,
        (SELECT lp.photo_path
         FROM listing_photos lp
         WHERE lp.listing_id = l.id AND lp.photo_type = 'cover'
         ORDER BY lp.created_at ASC
         LIMIT 1) AS cover_photo
     FROM bookings b
     INNER JOIN listings l ON l.id = b.listing_id
     INNER JOIN users u    ON u.id = b.user_id
     WHERE l.user_id = :host_id
     ORDER BY b.created_at DESC"
);
 $bookingsStmt->execute(['host_id' => $_SESSION['user_id']]);
 $bookings = $bookingsStmt->fetchAll();

/* Maps the real bookings.status enum to a badge style/label. */
 $statusMeta = [
    'pending'   => ['label' => 'Pending Approval', 'class' => 'hp-badge-yellow'],
    'confirmed' => ['label' => 'Confirmed',        'class' => 'hp-badge-green'],
    'completed' => ['label' => 'Completed',        'class' => 'hp-badge-blue'],
    'cancelled' => ['label' => 'Cancelled',        'class' => 'hp-badge-red'],
    'rejected'  => ['label' => 'Declined',         'class' => 'hp-badge-red'],
];

 $total     = count($bookings);
 $pending   = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
 $confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
 $completed = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
 $cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
 $rejected  = count(array_filter($bookings, fn($b) => $b['status'] === 'rejected'));

/* Tab filter: ?filter=all|pending|confirmed|completed|cancelled|rejected */
 $filter = $_GET['filter'] ?? 'all';
 $filtered = array_filter($bookings, function ($b) use ($filter) {
    if ($filter === 'pending')   return $b['status'] === 'pending';
    if ($filter === 'confirmed') return $b['status'] === 'confirmed';
    if ($filter === 'completed') return $b['status'] === 'completed';
    if ($filter === 'cancelled') return $b['status'] === 'cancelled';
    if ($filter === 'rejected')  return $b['status'] === 'rejected';
    return true;
});

/* Live search: ?q= matches guest name, listing title, or location */
 $q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filtered = array_filter($filtered, function ($b) use ($q) {
        return stripos($b['guest_name'], $q)      !== false
            || stripos($b['listing_title'], $q)   !== false
            || stripos($b['listing_location'], $q) !== false;
    });
}

/* Helper so tab links preserve an active search */
 $tabHref = function ($key) use ($q) {
    return '?filter=' . $key . ($q !== '' ? '&q=' . urlencode($q) : '');
};

/* Flash from bookingaction.php's redirect (?msg=accepted|declined|error),
   plus any session flash set by hp_flash_set(). */
 $flashText = [
    'accepted'  => ['type' => 'success', 'text' => 'Booking accepted.'],
    'declined'  => ['type' => 'success', 'text' => 'Booking declined.'],
    'error'     => ['type' => 'error',   'text' => 'That booking could not be updated. It may have already been handled.'],
][ $_GET['msg'] ?? '' ] ?? null;

 $sessionFlash = hp_flash_take();

 $activePage = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=5">

<!-- =====================================================
     BOOKING CARD ADD-ONS: payment pill, signed note,
     view-receipt button, mark-completed (scoped hb-/hbc-)
====================================================== -->
<style>
    .hb-pay-pill {
        display: inline-block;
        margin-top: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        white-space: nowrap;
    }
    .hb-pay-full {
        background: #E8F8F1;
        color: #1FA971;
        border: 1px solid #B9E3C5;
    }
    .hb-pay-advance {
        background: #FDF1DC;
        color: #B07708;
        border: 1px dashed rgba(237, 164, 35, 0.5);
    }
    .hb-pay-none {
        background: #F0F0F0;
        color: #777777;
        border: 1px solid #E0E0E0;
    }

    .hb-signed-note {
        display: inline-block;
        margin-top: 5px;
        font-size: 10.5px;
        font-weight: 700;
        color: #1FA971;
    }
    .hb-unsigned-note {
        display: inline-block;
        margin-top: 5px;
        font-size: 10.5px;
        font-weight: 600;
        color: #AAB1BE;
    }

    .hb-receipt-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;

        padding: 8px 14px;
        border-radius: 10px;

        background: #FDF1DC;
        border: 1px solid rgba(237, 164, 35, 0.5);

        color: #B07708;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;

        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .hb-receipt-btn:hover {
        background: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.25);
    }

    .hb-complete-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;

        padding: 8px 14px;
        border-radius: 10px;
        border: none;

        background: #E7F7EC;

        color: #1FA971;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;

        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .hb-complete-btn:hover {
        background: #1FA971;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(31, 169, 113, 0.3);
    }

    .hp-booking-actions { align-items: stretch; }

    /* ---- Mark Completed floating card (hbc-) ---- */
    .hbc-backdrop{position:fixed;inset:0;z-index:1300;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(28,42,56,.45);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .28s ease,visibility .28s ease;}
    .hbc-backdrop.open{opacity:1;visibility:visible;pointer-events:auto;}
    .hbc-card{position:relative;width:100%;max-width:380px;background:#fff;border-radius:22px;padding:30px 26px 24px;box-shadow:0 30px 70px rgba(28,42,56,.35);font-family:"Poppins",sans-serif;opacity:0;transform:translateY(26px) scale(.96);transition:opacity .3s cubic-bezier(.22,1,.36,1),transform .3s cubic-bezier(.22,1,.36,1);}
    .hbc-backdrop.open .hbc-card{opacity:1;transform:translateY(0) scale(1);}
    .hbc-card::before{content:"";position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#2fa84f,#5bc98a);border-radius:22px 22px 0 0;}
    .hbc-icon{width:54px;height:54px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;background:#E7F7EC;border:1px solid #BFE8CF;border-radius:50%;font-size:24px;}
    .hbc-title{margin:0 0 8px;text-align:center;color:#1c2a38;font-size:17px;font-weight:800;}
    .hbc-sub{margin:0 0 20px;text-align:center;color:#5d6875;font-size:12.5px;line-height:1.6;}
    .hbc-error{display:none;padding:10px 12px;margin-bottom:14px;background:#fdecec;border:1px solid #f3b9b9;border-radius:10px;color:#a4302f;font-size:12.5px;text-align:center;}
    .hbc-error.show{display:block;}
    .hbc-btn{width:100%;height:46px;border:none;border-radius:12px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;margin-bottom:10px;transition:transform .18s ease,box-shadow .18s ease;}
    .hbc-btn:disabled{opacity:.65;cursor:not-allowed;transform:none!important;}
    .hbc-confirm{background:linear-gradient(135deg,#3fbd6e,#2fa84f);color:#fff;box-shadow:0 8px 18px rgba(47,168,79,.35);}
    .hbc-confirm:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 12px 24px rgba(47,168,79,.45);}
    .hbc-cancel{background:#fff;border:1.5px solid #dfe4ea;color:#1c2a38;margin-bottom:0;}
    .hbc-cancel:hover:not(:disabled){border-color:#2fa84f;color:#2fa84f;}
</style>
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <?php include __DIR__ . '/host_sidebar.php'; ?>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">My Bookings</h1>
        <p class="hp-page-subtitle">View and manage all your booking requests and confirmed stays.</p>
      </div>

      <div class="hp-stat-pillbox">
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Total Bookings</p>
            <p class="hp-stat-pill-value"><?php echo h($total); ?></p>
          </div>
        </div>
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon hp-green">
            <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Confirmed Stays</p>
            <p class="hp-stat-pill-value"><?php echo h($confirmed); ?></p>
          </div>
        </div>
        <div class="hp-stat-pill-item">
          <div class="hp-stat-pill-icon hp-yellow">
            <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
          </div>
          <div>
            <p class="hp-stat-pill-label">Pending Requests</p>
            <p class="hp-stat-pill-value"><?php echo h($pending); ?></p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flashText): ?>
      <div class="hp-flash hp-flash-<?php echo h($flashText['type']); ?>">
        <?php echo h($flashText['text']); ?>
      </div>
    <?php endif; ?>

    <?php if ($sessionFlash): ?>
      <div class="hp-flash <?php echo $sessionFlash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
        <?php echo h($sessionFlash['message']); ?>
      </div>
    <?php endif; ?>

    <div class="hp-controls">
      <div class="hp-tabs">
        <a class="hp-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" href="<?php echo h($tabHref('all')); ?>">All (<?php echo h($total); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="<?php echo h($tabHref('pending')); ?>">Pending (<?php echo h($pending); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>" href="<?php echo h($tabHref('confirmed')); ?>">Confirmed (<?php echo h($confirmed); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>" href="<?php echo h($tabHref('completed')); ?>">Completed (<?php echo h($completed); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" href="<?php echo h($tabHref('cancelled')); ?>">Cancelled (<?php echo h($cancelled); ?>)</a>
        <a class="hp-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>" href="<?php echo h($tabHref('rejected')); ?>">Declined (<?php echo h($rejected); ?>)</a>
      </div>

      <!-- Live search (submit keeps current tab) -->
      <form class="hp-search-wrap" method="GET" action="/webprogg/host/hostbookings.php">
        <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
        <img src="/webprogg/images/searchicon-userprofile.png" alt="">
        <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Search by guest, property or location...">
      </form>

      <button type="button" class="hp-filter-btn">
        <img src="/webprogg/images/filtericon-userprofile.png" alt="">
        Filter
      </button>
    </div>

    <div class="hp-booking-list">
      <?php if (empty($bookings)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No bookings yet</p>
          <p>Once guests start booking your listings, their requests and stays will show up here.</p>
        </div>

      <?php elseif (empty($filtered)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">
            <?php echo $q !== '' ? 'No bookings match your search.' : 'No bookings in this filter yet.'; ?>
          </p>
        </div>

      <?php else: foreach ($filtered as $b):
        $meta = $statusMeta[$b['status']] ?? ['label' => ucfirst($b['status']), 'class' => 'hp-badge-yellow'];

        /* Host net payout, falling back to what the guest paid */
        $amount = $b['host_payout_amount'] !== null ? $b['host_payout_amount'] : $b['amount_paid'];
        $imageSrc = resolve_photo($b['cover_photo'], '/webprogg/images/listing-placeholder.jpg');

        $guestsNum = (int) $b['guests'];

        /* ------------------------------------------------
           PAYMENT STATE (same logic as the receipt):
           full / advance / none, from amount_paid vs total
        ------------------------------------------------- */
        $bBookingTotal = (float) ($b['total'] ?? 0);
        $bPaid         = (float) ($b['amount_paid'] ?? 0);
        $bLeft         = max(0, round($bBookingTotal - $bPaid, 2));

        if ($bPaid > 0.005 && $bLeft <= 0.005) {
            $payState  = 'full';
            $payLabel  = 'Fully Paid';
            $payClass  = 'hb-pay-full';
        } elseif ($bPaid > 0.005) {
            $payState  = 'advance';
            $payLabel  = 'Advance Paid · ₱' . number_format($bLeft, 2) . ' left';
            $payClass  = 'hb-pay-advance';
        } else {
            $payState  = 'none';
            $payLabel  = 'No Payment Yet';
            $payClass  = 'hb-pay-none';
        }

        /* Receipt link — only when the guest actually paid */
        $receiptUrl = $bPaid > 0.005
            ? '/webprogg/booking/payment-confirmation.php?id=' . (int) $b['id']
            : '';

        /* Signature status (saved by sign-receipt.php) */
        $signed = file_exists(
            $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/signatures/booking-' . $b['id'] . '.png'
        );
      ?>
      <div class="hp-booking-card">
        <img class="hp-booking-img" src="<?php echo h($imageSrc); ?>" alt="<?php echo h($b['listing_title']); ?>">
        <div class="hp-booking-info">
          <h4><?php echo h($b['listing_title']); ?></h4>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/locationicon-userprofile.png" alt="">
            <?php echo h($b['listing_location']); ?>
          </div>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
            <?php echo h($b['guest_name']); ?>
          </div>
          <?php if (!empty($b['checkin_date'])): ?>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            <?php echo h(date('M j, Y', strtotime($b['checkin_date']))); ?>
            <?php if (!empty($b['checkout_date'])): ?>
              &ndash; <?php echo h(date('M j, Y', strtotime($b['checkout_date']))); ?>
            <?php endif; ?>
            <?php if ($guestsNum > 0): ?>
              &middot; <?php echo h($guestsNum); ?> guest<?php echo $guestsNum === 1 ? '' : 's'; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="hp-booking-meta">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            Booked <?php echo h(date('M j, Y', strtotime($b['created_at']))); ?>
          </div>
        </div>

        <div class="hp-booking-right">
          <div class="hp-booking-status-col">
            <span class="hp-badge <?php echo h($meta['class']); ?>"><?php echo h($meta['label']); ?></span>

            <!-- Payment state pill (receipt logic) -->
            <span class="hb-pay-pill <?php echo h($payClass); ?>"><?php echo h($payLabel); ?></span>

            <!-- Signature status -->
            <?php if ($signed): ?>
              <span class="hb-signed-note">&#9997; Signed</span>
            <?php else: ?>
              <span class="hb-unsigned-note">Not signed yet</span>
            <?php endif; ?>

            <?php if ($b['status'] === 'confirmed'): ?>
              <!-- NEW — Hive Club: stays auto-complete after checkout;
                   long-term stays (no checkout date) complete here only -->
              <?php if (empty($b['checkout_date'])): ?>
                <span class="hb-unsigned-note">Long Term &middot; complete manually</span>
              <?php else: ?>
                <span class="hb-unsigned-note">Auto-completes after checkout</span>
              <?php endif; ?>
            <?php endif; ?>

            <p class="hp-booking-rent-label">Your Payout</p>
            <p class="hp-booking-rent-value">
              <?php echo $amount !== null
                  ? '&#8369; ' . h(number_format((float) $amount, 2))
                  : '&mdash;'; ?>
            </p>
          </div>

          <?php if ($b['status'] === 'pending'): ?>
            <div class="hp-booking-actions">
              <?php if ($receiptUrl !== ''): ?>
                <a class="hb-receipt-btn" href="<?php echo h($receiptUrl); ?>" target="_blank" rel="noopener">
                  &#128196; View Receipt
                </a>
              <?php endif; ?>
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-accept">Accept</button>
              </form>
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;"
                    onsubmit="return confirm('Decline this booking request? The guest will be refunded in full and notified.');">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-decline">Decline</button>
              </form>
            </div>
          <?php else: ?>
            <div class="hp-booking-actions">
              <?php if ($receiptUrl !== ''): ?>
                <a class="hb-receipt-btn" href="<?php echo h($receiptUrl); ?>" target="_blank" rel="noopener">
                  &#128196; View Receipt
                </a>
              <?php endif; ?>

              <?php if ($b['status'] === 'confirmed'): ?>
                <!-- NEW — Hive Club Phase 3: mark the stay completed;
                     the guest earns their points immediately -->
                <button type="button"
                        class="hb-complete-btn js-complete-booking"
                        data-booking-id="<?php echo (int) $b['id']; ?>">
                  &#9989; Mark Completed
                </button>
              <?php endif; ?>

                            <a href="/webprogg/booking/booking-details.php?id=<?php echo (int) $b['id']; ?>" class="hp-btn-outline" style="text-decoration:none;">View Details</a>
              <button type="button" class="hp-listing-menu" aria-label="More options">&#8942;</button>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</main>

<!-- =========================================================
     MARK COMPLETED — FLOATING CONFIRM CARD
========================================================== -->
<div class="hbc-backdrop" id="hbcBackdrop" aria-hidden="true">
    <div class="hbc-card" role="dialog" aria-modal="true">
        <div class="hbc-icon">&#9989;</div>
        <h3 class="hbc-title">Mark this stay as completed?</h3>
        <p class="hbc-sub">
            The guest will earn their Hive Club points for this stay,
            and the booking moves to the Completed tab. This cannot be undone.
        </p>
        <div class="hbc-error" id="hbcError"></div>
        <button type="button" class="hbc-btn hbc-confirm" id="hbcConfirm">Yes, Mark Completed</button>
        <button type="button" class="hbc-btn hbc-cancel" id="hbcCancel">Not Yet</button>
    </div>
</div>

<?php include __DIR__ . '/host_footer.php'; ?>

<script>
/* ---- Mark Completed card ---- */
(function () {
    "use strict";
    var backdrop = document.getElementById('hbcBackdrop');
    if (!backdrop) return;

    var confirmBtn = document.getElementById('hbcConfirm');
    var errorEl    = document.getElementById('hbcError');
    var currentId  = null;
    var busy       = false;

    document.querySelectorAll('.js-complete-booking').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentId = btn.getAttribute('data-booking-id');
            errorEl.classList.remove('show');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Yes, Mark Completed';
            backdrop.classList.add('open');
        });
    });

    function close() {
        if (busy) return;
        backdrop.classList.remove('open');
    }
    document.getElementById('hbcCancel').addEventListener('click', close);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) close();
    });

    confirmBtn.addEventListener('click', function () {
        if (busy || !currentId) return;
        busy = true;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Completing\u2026';
        errorEl.classList.remove('show');

        fetch('/webprogg/booking/complete-booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + encodeURIComponent(currentId),
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.success) { window.location.reload(); return; }
            busy = false;
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Yes, Mark Completed';
            errorEl.textContent = (data && data.message) || 'Could not complete this booking.';
            errorEl.classList.add('show');
        })
        .catch(function () {
            busy = false;
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Yes, Mark Completed';
            errorEl.textContent = "Couldn't reach the server. Please try again.";
            errorEl.classList.add('show');
        });
    });
})();
</script>

</body>
</html>