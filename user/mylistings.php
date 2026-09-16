<?php
/* =========================================================
   ROOMHIVE — MY LISTINGS
   mylistings.php

   Uses the shared host shell (host_init + host_navbar +
   host_sidebar + host_footer). Lives in /user/ but is a
   host page, so it includes host_init from /host/.
========================================================= */

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_init.php';

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change to becomeahost.php once appropriate
    exit;
}

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

/* -----------------------------------------------------
   PER-LISTING BOOKINGS COUNT
   Confirmed/completed only (pending = still an application).
   Views/Rating have no backing table/column yet -> "—".
----------------------------------------------------- */
 $listingBookingCounts = [];
foreach ($listings as $l) {
    $listingBookingCounts[$l['id']] = 0;
}

if (!empty($listings)) {
    $bookingCountsStmt = $pdo->prepare(
        "SELECT b.listing_id, COUNT(*) AS booking_count
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         WHERE l.user_id = :id
           AND b.status IN ('confirmed', 'completed')
         GROUP BY b.listing_id"
    );
    $bookingCountsStmt->execute(['id' => $_SESSION['user_id']]);
    foreach ($bookingCountsStmt->fetchAll() as $row) {
        $listingBookingCounts[(int) $row['listing_id']] = (int) $row['booking_count'];
    }
}

/* Maps a listings.status value to a status-pill class
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

 $activePage = 'listings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Listings — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=5">
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_navbar.php'; ?>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_sidebar.php'; ?>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">My Listings</h1>
        <p class="hp-page-subtitle">Manage your properties and keep track of your listings all in one place.</p>
      </div>
      <a href="/webprogg/host/becomeahost.php" class="hp-btn-outline" style="text-decoration:none;">+ Add New Listing</a>
    </div>

    <div class="hp-mylistings-list">
      <?php if (empty($listings)): ?>

        <div class="hp-empty-state">
          <p class="hp-empty-state-title">No listings yet</p>
          <p>Click "Add New Listing" above to publish your first property.</p>
        </div>

      <?php else: foreach ($listings as $l): ?>
      <div class="hp-mylisting-card">
        <div class="hp-mylisting-img-wrap">
          <img class="hp-mylisting-img" src="<?php echo h(resolve_photo($l['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="<?php echo h($l['title']); ?>">
          <span class="hp-status <?php echo hp_status_class($l['status']); ?>" style="position:absolute; top:10px; left:10px;">
            <?php echo h(hp_status_label($l['status'])); ?>
          </span>
        </div>

        <div class="hp-mylisting-info">
          <h3><?php echo h($l['title']); ?></h3>
          <div class="hp-mylisting-location">
            <img src="/webprogg/images/locationicon-userprofile.png" alt="">
            <?php echo h($l['location']); ?>
          </div>
          <div class="hp-mylisting-price-row">
            <span class="hp-mylisting-price">&#8369; <?php echo number_format($l['price']); ?></span>
            <span> / month</span>
          </div>
        </div>

        <div class="hp-mylisting-stats">
          <div class="hp-mylisting-stat-line">
            <img src="/webprogg/images/viewsicon-hostprofile.png" alt="">
            Views <b>&mdash;</b>
          </div>
          <div class="hp-mylisting-stat-line">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            Bookings <b><?php echo h($listingBookingCounts[$l['id']]); ?></b>
          </div>
          <div class="hp-mylisting-stat-line">
            <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
            Rating <b>&mdash;</b>
          </div>
        </div>

        <?php if (!empty($l['pending_booking_id'])): ?>
          <p class="hp-mylisting-applicant">
            <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
            <?php echo h($l['pending_tenant_name']); ?> applied &mdash; awaiting your decision
          </p>
        <?php endif; ?>

        <div class="hp-mylisting-actions">
          <button type="button" class="hp-btn-outline">Edit Listing</button>
          <div class="hp-menu-wrap">
            <button type="button" class="hp-listing-menu" data-listing-id="<?php echo h($l['id']); ?>" aria-haspopup="true" aria-expanded="false" aria-label="More options">&#8942;</button>
            <div class="hp-menu-dropdown">
              <?php if (!empty($l['pending_booking_id'])): ?>
                <button type="button" class="hp-menu-item hp-menu-accept" data-booking-id="<?php echo h($l['pending_booking_id']); ?>" data-tenant-name="<?php echo h($l['pending_tenant_name']); ?>">
                  Accept Tenant
                </button>
                <button type="button" class="hp-menu-item hp-menu-reject" data-booking-id="<?php echo h($l['pending_booking_id']); ?>" data-tenant-name="<?php echo h($l['pending_tenant_name']); ?>">
                  Reject Tenant
                </button>
              <?php endif; ?>
              <button type="button" class="hp-menu-item hp-menu-delete" data-listing-id="<?php echo h($l['id']); ?>" data-listing-title="<?php echo h($l['title']); ?>">
                Delete Listing
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_footer.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<!-- =========================================================
     LISTING 3-DOT MENU + DELETE
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
    .hp-mylisting-applicant {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 8px 0 0;
        padding: 6px 10px;
        background: #FFF4E0;
        border: 1px solid #F7941D;
        border-radius: 8px;
        font-size: 12px;
        color: #8A5A10;
    }
    .hp-mylisting-applicant img { width: 14px; height: 14px; }
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
                        const card = btn.closest('.hp-mylisting-card');
                        if (card) card.remove();
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