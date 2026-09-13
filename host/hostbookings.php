<?php
/* =========================================================
   ROOMHIVE — HOST BOOKINGS
   hostbookings.php

   Uses the shared host dashboard shell (host_init.php +
   host_navbar.php + host_sidebar.php + host_footer.php).

   SCHEMA NOTE (real `bookings` columns):
     - NO `total`, NO `booked_at` — ordering uses created_at
     - amount_paid / host_payout_amount for money
     - checkin_date / checkout_date / guests DO exist and
       are shown on each card
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
   CSRF TOKEN
   One token per session, reused by every Accept/Decline
   form on this page and checked by bookingaction.php.
----------------------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['csrf_token'];

/* -----------------------------------------------------
   BOOKINGS
   Real columns only. Amount shown = host's net payout
   (host_payout_amount), falling back to amount_paid —
   same convention as earnings.php.
----------------------------------------------------- */
 $bookingsStmt = $pdo->prepare(
    "SELECT
        b.id,
        b.status,
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
];

 $total     = count($bookings);
 $pending   = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
 $confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
 $completed = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
 $cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));

/* Tab filter: ?filter=all|pending|confirmed|completed|cancelled */
 $filter = $_GET['filter'] ?? 'all';
 $filtered = array_filter($bookings, function ($b) use ($filter) {
    if ($filter === 'pending')   return $b['status'] === 'pending';
    if ($filter === 'confirmed') return $b['status'] === 'confirmed';
    if ($filter === 'completed') return $b['status'] === 'completed';
    if ($filter === 'cancelled') return $b['status'] === 'cancelled';
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
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

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
            <p class="hp-booking-rent-label">Your Payout</p>
            <p class="hp-booking-rent-value">
              <?php echo $amount !== null
                  ? '&#8369; ' . h(number_format((float) $amount, 2))
                  : '&mdash;'; ?>
            </p>
          </div>

          <?php if ($b['status'] === 'pending'): ?>
            <div class="hp-booking-actions">
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-accept">Accept</button>
              </form>
              <form method="POST" action="/webprogg/booking/bookingaction.php" style="display:inline;"
                    onsubmit="return confirm('Decline this booking request?');">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($b['id']); ?>">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
                <button type="submit" class="hp-btn-outline hp-btn-decline">Decline</button>
              </form>
            </div>
          <?php else: ?>
            <div class="hp-booking-actions">
              <button type="button" class="hp-btn-outline">View Details</button>
              <button type="button" class="hp-listing-menu" aria-label="More options">&#8942;</button>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>

</body>
</html>