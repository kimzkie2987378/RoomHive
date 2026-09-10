<?php
/* =========================================================
   ROOMHIVE — MY LISTINGS
   mylistings.php

   Same navbar / sidebar / dashboard shell as hostprofile.php.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   HOST GUARD
   Same reasoning as hostprofile.php — see that file for the
   full note on why this redirects to hostprofile.php for now.
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php"); // TODO: change back to becomeahost.php once that file exists
    exit;
}

/* Keep the navbar's account icon in sync too — same reasoning
   as hostprofile.php / userprofile.php. */
$_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

/* -----------------------------------------------------
   HOST DATA (sidebar card)
----------------------------------------------------- */
$host = [
    'name'         => $dbUser['name'],
    'avatar'       => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

$notification_count = 0; // TODO: wire up once a notifications table exists

/* -----------------------------------------------------
   MY LISTINGS
   Same query as the mini table on hostprofile.php. Bedrooms,
   bathrooms, sqm, views, bookings and rating aren't columns
   on `listings` yet (and views/bookings need tables that
   don't exist yet either), so those show "—" instead of
   invented numbers until the schema supports them.
----------------------------------------------------- */
/* pending_booking_id / pending_tenant_name come from whichever
   booking on that listing is still awaiting the host's decision
   (status = 'pending') — that's the tenant Accept/Reject in the
   3-dot menu below acts on. A listing only ever has one active
   (pending or confirmed) booking at a time, so this LEFT JOIN
   won't duplicate rows. */
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
   Same rule as hostprofile.php: confirmed/completed only
   (pending is still just an application, not a real booking).
   Views and Rating still have no backing table/column, so
   those stay as "—" for now.
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

/* -----------------------------------------------------
   PENDING TENANTS COUNT
   Same badge count as hostprofile.php's sidebar — how many
   tenant applications across all listings are still sitting
   at status = 'pending'.
----------------------------------------------------- */
$pendingTenantsCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :id AND b.status = 'pending'"
);
$pendingTenantsCountStmt->execute(['id' => $_SESSION['user_id']]);
$pending_tenants_count = (int) $pendingTenantsCountStmt->fetchColumn();

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------
   PHOTO PATH FIX
   Uploaded photo_path values (listing_photos.photo_path) are
   saved relative to /webprogg — e.g. "uploads/listings/abc.jpg".
   Printed as-is, the browser resolves that against the CURRENT
   page's folder instead of the site root, which is why cover
   photos 404 here even though the host's own avatar (already
   stored as a full "/webprogg/..." path by uploadavatar.php)
   works fine.

   FIX: the old version trusted ANY leading "/" as proof the
   path was already a correct, absolute site-root path. But
   some rows were saved as "/uploads/listing_photos/xyz.jpg"
   (leading slash, no "webprogg" segment) — that got returned
   untouched, so the browser looked for it at the actual server
   root instead of inside /webprogg/, and the image 404'd. Now
   every path is normalized — strip any leading slash and any
   accidental leading "webprogg/" segment — and then rebuilt as
   "/webprogg/..." every single time, so it comes out correct
   no matter how it was originally saved.
----------------------------------------------------- */
function resolve_photo($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path; // full remote URL — leave it alone
    }
    $normalized = ltrim($path, '/');
    if (stripos($normalized, 'webprogg/') === 0) {
        $normalized = substr($normalized, strlen('webprogg/'));
    }
    return '/webprogg/' . $normalized;
}

/* Maps a listings.status value to a status-pill class (same map as hostprofile.php) */
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

/* Cache-buster for the stylesheet so browsers don't keep serving a
   stale cached copy after edits (e.g. this fix) are deployed. Bump
   the number any time hostprofile.css changes and you're not seeing
   the update reflected live. */
$hp_css_version = '3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Listings — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=<?php echo h($hp_css_version); ?>">
</head>
<body>

<!-- =========================================================
     NAVBAR (identical markup/classes to hostprofile.php)
========================================================= -->
<header class="navbar">

    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <nav class="nav-links">

        <a href="/webprogg/user/usershome.php">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="/webprogg/host/becomeahost.php">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>

        <a href="notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <div class="account-dropdown js-account-dropdown">

            <button
                type="button"
                class="my-account js-account-toggle"
                id="accountDropdownToggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <span class="account-circle">
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                </span>
                <span>MY ACCOUNT</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <a href="/webprogg/user/myaccount.php">My Account</a>
                <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="hp-dashboard hp-dashboard--flush-top">

  <!-- SIDEBAR (identical to hostprofile.php, "My Listings" active) -->
  <aside class="hp-sidebar">

    <div class="hp-sidebar-card">
      <div class="hp-profile-photo" style="margin: 0 auto 12px;">
        <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar" id="hostSidebarAvatarImg">
        <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
          <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
        </button>
        <input type="file" id="hostAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
      </div>
      <h4><?php echo h($host['name']); ?></h4>
      <span class="hp-host-badge">Host</span>
      <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="/webprogg/host/hostprofile.php" class="hp-side-link">
      <img src="/webprogg/images/overviewicon-userprofile.png" alt="">
      Overview
    </a>
    <a href="/webprogg/user/mylistings.php" class="hp-side-link active">
      <img src="/webprogg/images/mylistingsicon-hostprofile.png" alt="">
      My Listings
    </a>
    <a href="/webprogg/booking/pendingtenants.php" class="hp-side-link hp-side-link-badged">
      <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
      Pending Tenants
      <?php if ($pending_tenants_count > 0): ?>
        <span class="hp-side-badge"><?php echo h($pending_tenants_count); ?></span>
      <?php endif; ?>
    </a>
    <a href="/webprogg/host/hostbookings.php" class="hp-side-link">
      <img src="/webprogg/images/bookingsicon-userprofile.png" alt="">
      Bookings
    </a>
    <a href="earnings.php" class="hp-side-link">
      <img src="/webprogg/images/totalspenticon-userprofile.png" alt="">
      Earnings
    </a>
    <a href="payouts.php" class="hp-side-link">
      <img src="/webprogg/images/paymentsicon-userprofile.png" alt="">
      Payouts
    </a>
    <a href="hostreviews.php" class="hp-side-link">
      <img src="/webprogg/images/averageratinsicon-userprofile.png" alt="">
      Reviews
    </a>
    <a href="hostmessages.php" class="hp-side-link">
      <img src="/webprogg/images/messagesicon-userprofile.png" alt="">
      Messages
    </a>
    <a href="hosteditprofile.php" class="hp-side-link">
      <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
      Profile &amp; Account
    </a>
    <a href="verification.php" class="hp-side-link">
      <img src="/webprogg/images/verifiedicon-userprofile.png" alt="">
      Verification
    </a>
    <a href="payoutmethods.php" class="hp-side-link">
      <img src="/webprogg/images/payoutmethodsicon-hostprofile.png" alt="">
      Payout Methods
    </a>
    <a href="hostnotificationsettings.php" class="hp-side-link">
      <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
      Notification Settings
    </a>
    <a href="hostsecurity.php" class="hp-side-link">
      <img src="/webprogg/images/lockicon-userprofile.png" alt="">
      Security
    </a>
    <a href="helpcenter.php" class="hp-side-link">
      <img src="/webprogg/images/needhelpicon-userprofile.png" alt="">
      Help Center
    </a>
    <a href="/webprogg/auth/logout.php" class="hp-side-link hp-side-logout">
      <img src="/webprogg/images/logouticon-userprofile.png" alt="">
      Log Out
    </a>

  </aside>

  <!-- CONTENT COLUMN -->
  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">My Listings</h1>
        <p class="hp-page-subtitle">Manage your properties and keep track of your listings all in one place.</p>
      </div>
      <a href="/webprogg/host/becomeahost.php" class="hp-btn-outline">+ Add New Listing</a>
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
          <!-- FIX: was falling back to '/webprogg/images/listing-placeholder.jpg',
               a file that does not exist on disk (wrong case + wrong extension),
               which produced a broken-image icon overlapping the title. Now
               points at the same placeholder every other page already uses. -->
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

<!-- =========================================================
     FOOTER (identical to hostprofile.php)
========================================================= -->
<footer class="site-footer">

    <div class="footer-top">

        <div class="footer-brand">

            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>

            <p class="footer-tagline">
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>

            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="">
                <span>0927 569 3574</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="">
                <span>kimdivino55@gmail.com</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>

        </div>

        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
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
    .hp-side-link-badged { position: relative; display: flex; align-items: center; gap: 10px; }
    .hp-side-badge {
        margin-left: auto;
        background: #E14B4B;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        padding: 3px 7px;
        border-radius: 999px;
    }
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

    // Accept or reject a tenant's application (booking) for a listing.
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
                    // Listing's pending/status details changed — reload
                    // so the card, status pill, and menu reflect it.
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

<!-- =========================================================
     SIDEBAR PROFILE PHOTO UPLOAD
     Same block as hostprofile.php, minus the hostProfileAvatarImg
     element (this page only has the sidebar copy of the avatar).
========================================================= -->
<script>
(function () {
    const photoButton  = document.getElementById('hostPhotoButton');
    const fileInput    = document.getElementById('hostAvatarFileInput');
    const sidebarImg   = document.getElementById('hostSidebarAvatarImg');
    const navAvatarImg = document.getElementById('navAccountAvatarImg');

    if (!photoButton || !fileInput || !sidebarImg) return;

    photoButton.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please choose a JPG, PNG, or WEBP image.');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('That image is too large. Please choose one under 5MB.');
            fileInput.value = '';
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        const previousSrc = sidebarImg.src;
        sidebarImg.src = previewUrl;
        if (navAvatarImg) navAvatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/upload/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                sidebarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                sidebarImg.src = previousSrc;
                if (navAvatarImg) navAvatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function () {
            sidebarImg.src = previousSrc;
            if (navAvatarImg) navAvatarImg.src = previousSrc;
            alert('Something went wrong uploading your photo. Please try again.');
        })
        .finally(function () {
            URL.revokeObjectURL(previewUrl);
            photoButton.disabled = false;
            fileInput.value = '';
        });
    });
})();
</script>

</body>
</html>