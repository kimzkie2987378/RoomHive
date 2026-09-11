<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userwishlist.php

   Full wishlist page — the "View All" destination from the
   mini Wishlist widget on userprofile.php. Same navbar,
   sidebar, and footer as the rest of the /my-account pages
   so it reads as one continuous dashboard.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
   Same shape as userprofile.php so the shared navbar/sidebar
   render identically on both pages.
----------------------------------------------------- */
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

/* This is a regular-user account page — hosts land on the
   host dashboard instead, same redirect rule as userprofile.php. */
if ($dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

/* FIX: this page previously set $_SESSION['avatar_path'] by
   hand and then checked $_SESSION['is_host'] === true in the
   dropdown below without ever setting it — so the Host Profile
   link could never appear even for a host, and this page was
   out of sync with the fix already applied on userprofile.php.
   sync_user_session() does both consistently. */
$navAvatar = sync_user_session($dbUser);

$user = [
    'name'   => $dbUser['name'],
    'avatar' => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
];

$notification_count = 0;

/* -----------------------------------------------------
   WISHLIST — FULL LIST
   Every listing this user has saved, most recently saved
   first. `wishlist` links a user to a listing (user_id,
   listing_id, created_at); joined to `listings` for the
   card details and `listing_photos` for the cover image.
----------------------------------------------------- */
$wishlistStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.location, l.price, l.status,
            p.photo_path AS cover_photo,
            w.created_at AS saved_at
     FROM wishlist w
     JOIN listings l ON l.id = w.listing_id
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE w.user_id = :id
     ORDER BY w.created_at DESC"
);
$wishlistStmt->execute(['id' => $_SESSION['user_id']]);

$wishlist = array_map(function ($row) {
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'location' => $row['location'],
        'thumb'    => resolve_photo($row['cover_photo']),
        'price'    => number_format((float) $row['price'], 0),
        'rating'   => 0,
        'reviews'  => 0,
        'saved_at' => date('M j, Y', strtotime($row['saved_at'])),
        'unlisted' => $row['status'] === 'unlisted',
    ];
}, $wishlistStmt->fetchAll());

$wishlist_total = count($wishlist);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Wishlist — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<style>
    /* Grid for the full wishlist page — a bit roomier than the
       3-column mini widget on the Overview page, since this is
       the whole content column instead of a small card. */
    .uw-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
    }

    @media (max-width: 900px) {
        .uw-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .uw-grid {
            grid-template-columns: 1fr;
        }
    }

    .uw-saved-date {
        margin: 2px 0 0;
        font-size: 11px;
        color: #999999;
    }

    .uw-unlisted-tag {
        display: inline-block;
        margin-top: 6px;
        padding: 2px 9px;
        background: #f0f0f0;
        color: #777777;
        font-size: 10.5px;
        font-weight: 700;
        border-radius: 999px;
    }
</style>
</head>
<body>

<!-- =========================================================
     NAVBAR (identical to userprofile.php)
========================================================= -->
<header class="navbar">

    <!-- LOGO -->
    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION -->
    <nav class="nav-links">

        <a href="/webprogg/user/usershome.php">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="/webprogg/host/becomeahost.php">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>

        <a href="/webprogg/user/notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <!-- MY ACCOUNT -->
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
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <?php /* FIX: was checking $_SESSION['is_host'] === true, which
                         this page never set — now reads the real DB value,
                         same fix already applied on userprofile.php. */ ?>
                <?php if ($dbUser['is_host']): ?>
                    <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                <?php endif; ?>
                <a href="/webprogg/user/userprofile.php">My Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>

        </div>

    </nav>

</header>

<!-- =========================================================
     WELCOME BANNER
========================================================= -->
<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Your saved stays,</p>
    <h1>Wishlist</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Everything you've saved while browsing listings, all in one place.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/livingroomicon-userprofile.png" alt="">
  </div>
</section>

<!-- =========================================================
     MAIN DASHBOARD LAYOUT
========================================================= -->
<main class="up-dashboard">

  <?php
  /* FIX: this sidebar previously used its own absolute paths,
     three of which pointed at filenames that don't exist on
     disk (usereditprofile.php, usersavedsearches.php,
     userhelpcenter.php) — 404s on Profile & Account, Saved
     Searches, and Help Center. The shared partial below uses
     the real filenames everywhere. */
  $activeSidebar = 'wishlist';
  require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';
  ?>

  <!-- CONTENT COLUMN -->
  <div class="up-content">

    <!-- WISHLIST -->
    <section class="up-card up-wishlist">
      <div class="up-card-header">
        <h3>My Wishlist (<span class="up-wishlist-count"><?php echo h($wishlist_total); ?></span>)</h3>
        <a href="/webprogg/Listings/listing.php" class="up-link-view-all">Browse Listings</a>
      </div>

      <div class="uw-grid" id="up-wishlist-grid" <?php if (empty($wishlist)): ?>style="display:none;"<?php endif; ?>>
        <?php foreach ($wishlist as $item): ?>
          <a href="/webprogg/Listings/listing.php?id=<?php echo h($item['id']); ?>" class="listing-box" data-listing-id="<?php echo h($item['id']); ?>">
            <div class="up-wishlist-thumb">
              <img src="<?php echo h($item['thumb']); ?>" alt="<?php echo h($item['title']); ?>">
              <button type="button"
                      class="rh-save-btn saved"
                      data-listing-id="<?php echo h($item['id']); ?>"
                      aria-label="Remove from wishlist">
                &#9829;
              </button>
            </div>
            <h4><?php echo h($item['title']); ?></h4>
            <p class="up-wishlist-location"><?php echo h($item['location']); ?></p>
            <div class="up-wishlist-meta">
              <span class="up-wishlist-price">&#8369; <?php echo h($item['price']); ?> / night</span>
              <span class="up-wishlist-rating">&#9733; <?php echo h($item['rating']); ?> (<?php echo h($item['reviews']); ?>)</span>
            </div>
            <p class="uw-saved-date">Saved <?php echo h($item['saved_at']); ?></p>
            <?php if ($item['unlisted']): ?>
              <span class="uw-unlisted-tag">No longer listed</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Shown when the wishlist is empty on load, and also by
           JS once every remaining item has been removed. -->
      <div class="up-wishlist-empty" id="up-wishlist-empty" style="<?php echo empty($wishlist) ? '' : 'display:none; '; ?>text-align:center; padding:56px 12px; color:#777777;">
        <p style="margin:0 0 4px; font-weight:700; color:var(--up-navy, #1c2a38); font-size:15px;">Your wishlist is empty</p>
        <p style="margin:0 0 18px; font-size:13px;">Save listings you like while browsing and they'll show up here.</p>
        <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
      </div>
    </section>

  </div>
</main>

<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->
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

        <!-- LISTINGS -->
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <!-- QUICK LINKS -->
        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>

        <!-- GET THE APP -->
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
     WISHLIST — REMOVE LISTING
     Calls togglewishlist.php to delete the row from the
     `wishlist` table for this user + listing, then updates
     the count and empty-state in the DOM without a reload.
========================================================= -->
<script>
(function () {

    const grid = document.getElementById('up-wishlist-grid');
    const emptyState = document.getElementById('up-wishlist-empty');
    const countEl = document.querySelector('.up-wishlist-count');

    function syncWishlistUI() {
        const remaining = grid ? grid.querySelectorAll('.listing-box').length : 0;

        if (countEl) countEl.textContent = remaining;

        if (grid && emptyState) {
            grid.style.display = remaining === 0 ? 'none' : '';
            emptyState.style.display = remaining === 0 ? '' : 'none';
        }
    }

    if (!grid) return;

    grid.querySelectorAll('.rh-save-btn').forEach(function (btn) {

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const box = btn.closest('.listing-box');
            const listingId = btn.getAttribute('data-listing-id');
            if (!box || !listingId) return;

            btn.disabled = true;

            fetch('/webprogg/user/togglewishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    box.remove();
                    syncWishlistUI();
                } else {
                    btn.disabled = false;
                    alert(data.message || 'Could not update your wishlist.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Something went wrong. Please try again.');
            });
        });

    });

})();
</script>

</body>
</html>