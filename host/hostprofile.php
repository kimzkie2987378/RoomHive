<?php
/* =========================================================
   ROOMHIVE — HOST PROFILE (Overview)
   hostprofile.php

   Uses the shared host shell (host_init + host_navbar +
   host_sidebar + host_footer).

   SCHEMA NOTE: bookings has NO `total` and NO `paid_at`.
   Money = host_payout_amount (falls back to amount_paid);
   confirmed/completed status is treated as paid.
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

/* -----------------------------------------------------
   VIEWS — no page-view tracking table exists yet
----------------------------------------------------- */
 $total_views = 0;

/* -----------------------------------------------------
   BOOKING-BASED METRICS (FIXED for real schema)
   Was: SELECT b.total, b.paid_at  -> fatal, columns don't exist.
   Now: amount_paid / host_payout_amount; confirmed/completed
   counts as paid, so there's no paid_at condition.
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

/* Overlap (in nights) between a booking's stay and the
   30-day occupancy window. Null checkout (long-term/
   ongoing) counts as occupying through the end of the window. */
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

/* Per-listing tallies, seeded so every listing shows real
   zeros instead of missing keys if it has no bookings. */
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

    /* FIX: was `if (!empty($b['paid_at'])) ... += $b['total']`.
       Confirmed/completed = paid. Host take-home is
       host_payout_amount, falling back to amount_paid. */
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

/* Hero note is now real instead of hardcoded "No earnings data yet" */
 $earningsNote = $total_bookings > 0
    ? 'From ' . $total_bookings . ' confirmed booking' . ($total_bookings === 1 ? '' : 's')
    : 'No earnings data yet';

/* Maps a listings.status value to a small status-pill class
   (NOT in host_init, so a local definition is safe here). */
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

 $activePage = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Host Profile — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=5">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

<!-- =========================================================
     HOST PROFILE HERO
========================================================= -->
<section class="hp-hero">
  <div class="hp-hero-text">
    <h1>Host Profile</h1>
    <span class="hp-hero-underline"></span>
    <p>Manage your account, listings, and payouts all in one place.</p>
  </div>

  <div class="hp-hero-image">
    <img src="/webprogg/images/hostprofile-hero.jpg" alt="">
  </div>

  <div class="hp-earnings-float">
    <span class="hp-muted">Total Earnings</span>
    <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
    <span class="hp-earnings-note"><?php echo h($earningsNote); ?></span>
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
    <section class="hp-card hp-profile-card">

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

    <!-- PERFORMANCE OVERVIEW -->
    <section class="hp-card">
      <div class="hp-card-header">
        <h3>Performance Overview</h3>
      </div>

      <div class="hp-stats">

        <div class="hp-stat-card">
          <img src="/webprogg/images/viewsicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Views</span>
            <strong><?php echo h($total_views); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Bookings</span>
            <strong><?php echo h($total_bookings); ?></strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/occupancyicon-hostprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Occupancy Rate</span>
            <strong><?php echo h($occupancy_rate); ?>%</strong>
          </div>
        </div>

        <div class="hp-stat-card">
          <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
          <div>
            <span class="hp-stat-label">Earnings</span>
            <strong>&#8369; <?php echo h(number_format($total_earnings, 2)); ?></strong>
          </div>
        </div>

      </div>
    </section>

    <!-- MY LISTINGS -->
    <section class="hp-card">
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
    <section class="hp-grow-banner">

      <div class="hp-grow-text">
        <img src="/webprogg/images/grow-hosting-illustration.png" alt="" class="hp-grow-illustration">
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
     LISTING 3-DOT MENU + DELETE (endpoints unchanged)
========================================================= -->
<style>
    .hp-menu-wrap { position: relative; display: inline-block; }
    .hp-menu-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 160px;
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(20, 20, 43, 0.12);
        padding: 6px;
        z-index: 50;
    }
    .hp-menu-wrap.open .hp-menu-dropdown { display: block; }
    .hp-menu-item {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        cursor: pointer;
        color: #14142B;
    }
    .hp-menu-item:hover { background: #F6F7FB; }
    .hp-menu-delete { color: #E14B4B; }
    .hp-menu-delete:hover { background: #FCEAEA; }
    .hp-menu-accept { color: #1E7A3D; }
    .hp-menu-accept:hover { background: #E6F6EC; }
    .hp-menu-reject { color: #E14B4B; }
    .hp-menu-reject:hover { background: #FCEAEA; }
</style>
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