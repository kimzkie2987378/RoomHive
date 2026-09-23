<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   userwishlist.php

   Reads from the SAME wishlist table that listing.php
   heart buttons write to via togglewishlist.php.

   === CHANGES ===
   - Embedded card styles (myaccount.css has none for
     .uw-grid), matching the RoomHive design system.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

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

if ($dbUser['is_host']) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);
 $notification_count = 0;

/* WISHLIST — FULL LIST (same table listing.php hearts write to) */
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
 $activeSidebar = 'wishlist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Wishlist — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>

<style>
/* =========================================================
   WISHLIST CARDS — self-contained, RoomHive design system
   (scoped under .uw-grid so it can't leak anywhere else)
========================================================= */

.uw-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 24px;
    margin-top: 18px;
}

/* Card */
.uw-grid .listing-box {
    display: flex;
    flex-direction: column;

    background: #ffffff;
    border: 1px solid #e8e1cf;
    border-radius: 18px;
    overflow: hidden;

    text-decoration: none;
    color: inherit;

    box-shadow: 0 6px 20px rgba(28, 43, 36, 0.07);

    transition:
        transform 0.25s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.25s ease,
        border-color 0.2s ease;
}

.uw-grid .listing-box:hover {
    transform: translateY(-5px);
    border-color: #f0dcb4;
    box-shadow: 0 18px 36px rgba(28, 43, 36, 0.14);
}

.uw-grid .listing-box:focus-visible {
    outline: 3px solid rgba(237, 164, 35, 0.55);
    outline-offset: 2px;
}

/* Image */
.uw-grid .up-wishlist-thumb {
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: #f4ecdc;
}

.uw-grid .up-wishlist-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.uw-grid .listing-box:hover .up-wishlist-thumb img {
    transform: scale(1.05);
}

/* Heart button */
.uw-grid .rh-save-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;

    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;

    background: rgba(255, 255, 255, 0.94);
    color: #dd5361;

    font-size: 1.1rem;
    line-height: 1;

    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);

    transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.uw-grid .rh-save-btn:hover {
    transform: scale(1.12);
}

.uw-grid .rh-save-btn:disabled {
    cursor: wait;
    opacity: 0.8;
}

/* Body */
.uw-grid .listing-box h4 {
    margin: 14px 16px 0;

    font-family: "Poppins", sans-serif;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;

    color: #1c2b24;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.uw-grid .up-wishlist-location {
    margin: 4px 16px 0;

    font-size: 0.83rem;
    color: #62705f;
}

/* Meta row: price + rating */
.uw-grid .up-wishlist-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;

    margin: 12px 16px 0;
    padding-top: 12px;
    border-top: 1px solid #f0ead9;
}

.uw-grid .up-wishlist-price {
    font-size: 1rem;
    font-weight: 700;
    color: #1c2b24;
}

.uw-grid .up-wishlist-price::first-letter {
    color: #b8760a;
}

.uw-grid .up-wishlist-rating {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1c2b24;
    white-space: nowrap;
}

/* Saved date + unlisted tag */
.uw-grid .uw-saved-date {
    margin: 6px 16px 16px;

    font-size: 0.74rem;
    color: #9aa79a;
}

.uw-grid .uw-unlisted-tag {
    display: inline-block;

    margin: 0 16px 16px;
    align-self: flex-start;

    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;

    color: #ffffff;
    background: #dd5361;

    padding: 4px 11px;
    border-radius: 999px;
}

/* Empty state */
.up-wishlist-empty a.up-btn-outline {
    text-decoration: none;
}

/* Responsive */
@media (max-width: 560px) {
    .uw-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>
<!-- PAGE HEADER — PLAIN -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Your Saved Stays</span>
    <h1>Wishlist</h1>
    <p class="ub-lead">Everything you've saved while browsing listings, all in one place.</p>
</header>

<main class="up-dashboard">
  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">
    <section class="up-card up-wishlist up-reveal">
      <div class="up-card-header">
        <h3>My Wishlist (<span class="up-wishlist-count"><?php echo h($wishlist_total); ?></span>)</h3>
        <a href="/webprogg/Listings/listing.php" class="up-link-view-all">Browse Listings</a>
      </div>

      <div class="uw-grid" id="up-wishlist-grid" <?php if (empty($wishlist)): ?>style="display:none;"<?php endif; ?>>
        <?php foreach ($wishlist as $item): ?>
          <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($item['id']); ?>" class="listing-box" data-listing-id="<?php echo h($item['id']); ?>">
            <div class="up-wishlist-thumb">
              <img src="<?php echo h($item['thumb']); ?>" alt="<?php echo h($item['title']); ?>">
              <button type="button" class="rh-save-btn saved" data-listing-id="<?php echo h($item['id']); ?>" aria-label="Remove from wishlist">&#9829;</button>
            </div>
            <h4><?php echo h($item['title']); ?></h4>
            <p class="up-wishlist-location"><?php echo h($item['location']); ?></p>
            <div class="up-wishlist-meta">
              <span class="up-wishlist-price">&#8369; <?php echo h($item['price']); ?> <small>/month</small></span>
              <span class="up-wishlist-rating">&#9733; <?php echo h($item['rating']); ?> <span>(<?php echo h($item['reviews']); ?>)</span></span>
            </div>
            <p class="uw-saved-date">Saved <?php echo h($item['saved_at']); ?></p>
            <?php if ($item['unlisted']): ?>
              <span class="uw-unlisted-tag">No longer listed</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="up-wishlist-empty" id="up-wishlist-empty" style="<?php echo empty($wishlist) ? '' : 'display:none;'; ?> text-align:center; padding:56px 12px; color:#777777;">
        <p style="margin:0 0 4px; font-weight:700; color:#1c2b24; font-size:15px;">Your wishlist is empty</p>
        <p style="margin:0 0 18px; font-size:13px;">Tap the heart on any listing and it will show up here.</p>
        <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
      </div>
    </section>
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

<!-- Reveal (self-contained) -->
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
})();
</script>

<!-- WISHLIST — REMOVE LISTING (same endpoint as listing.php hearts) -->
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
                } else if (data.login) {
                    window.location.href = '/webprogg/auth/loginform.php';
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