<?php
/* =========================
   hostpublicprofile.php
   Public "meet the host" page, linked from listing-detail.php's
   "View Host Profile" button (?id= is the host's users.id).
========================== */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* =========================
   LOGIN STATUS
========================== */
$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

$userName = $_SESSION['user_name'] ?? 'Guest';

/* Same nav-avatar staleness handling as listing.php / listing-detail.php,
   so a freshly-uploaded profile photo shows immediately in the navbar
   without a re-login. */
$navAvatar = '/webprogg/images/default-avatar.png';

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $navAvatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $navAvatarStmt->execute(['id' => $_SESSION['user_id']]);
    $navAvatarRow = $navAvatarStmt->fetch();
    $navAvatar = !empty($navAvatarRow['avatar_path']) ? $navAvatarRow['avatar_path'] : $navAvatar;
}

/* =========================
   RESOLVE HOST FROM ?id=
========================== */

$hostId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

/*
 * NOTE: this install's `users` table has no `bio` column, so the
 * "About this host" section always falls back to the generic line
 * below. If a bio column gets added later (e.g.
 * ALTER TABLE users ADD COLUMN bio TEXT NULL;), add `, bio` to the
 * SELECT below and this page will pick it up automatically — see
 * the $host['bio'] assignment further down.
 */
$hostStmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host, created_at
     FROM users
     WHERE id = :id AND is_host = 1
     LIMIT 1"
);
$hostStmt->execute(['id' => $hostId]);
$hostRow = $hostStmt->fetch();

/* No such host, or the account isn't (or is no longer) an approved
   host — send back to Listings rather than show a broken profile. */
if ($hostRow === false) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

$isOwnProfile = $isLoggedIn && (int) ($_SESSION['user_id'] ?? 0) === (int) $hostRow['id'];

/* =========================
   HOST'S APPROVED LISTINGS
   Same cover-photo path resolution as listing.php's $allListings —
   host-step3.php writes covers to
   /webprogg/uploads/listing_photos/cover/, so that's the only
   folder this needs to check.
========================== */

$hostListingsStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.category, l.location, l.price, l.bedrooms, l.created_at,
            p.photo_path AS cover_photo
     FROM listings l
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE l.user_id = :host_id AND l.status = 'approved'
     ORDER BY l.created_at DESC"
);
$hostListingsStmt->execute(['host_id' => $hostRow['id']]);

$hostListings = array_map(function ($row) {
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'image'    => !empty($row['cover_photo'])
            ? '/webprogg/uploads/listing_photos/cover/' . basename($row['cover_photo'])
            : '/webprogg/images/ListingPlaceholder.png',
        'location' => $row['location'],
        'category' => $row['category'],
        'price'    => (float) $row['price'],
        'bedrooms' => (int) $row['bedrooms'],
    ];
}, $hostListingsStmt->fetchAll());

/* =========================
   HOST'S REVIEW STATS
   Aggregated across every listing this host owns — same query
   shape as $hostReviewsStmt in listing-detail.php.
========================== */

$hostReviewsStmt = $pdo->prepare(
    "SELECT r.rating
     FROM reviews r
     JOIN listings l2 ON l2.id = r.listing_id
     WHERE l2.user_id = :host_id"
);
$hostReviewsStmt->execute(['host_id' => $hostRow['id']]);
$hostReviewRatings = array_map('floatval', array_column($hostReviewsStmt->fetchAll(), 'rating'));

$hostRating  = count($hostReviewRatings) > 0 ? round(array_sum($hostReviewRatings) / count($hostReviewRatings), 1) : 0;
$hostReviews = count($hostReviewRatings);

/* =========================
   ASSEMBLE HOST
========================== */

$host = [
    'id'            => (int) $hostRow['id'],
    'name'          => $hostRow['name'],
    'avatar'        => !empty($hostRow['avatar_path'])
                            ? $hostRow['avatar_path']
                            : '/webprogg/images/default-avatar.png',
    'bio'           => trim($hostRow['bio'] ?? ''),
    'member_since'  => date('F Y', strtotime($hostRow['created_at'])),
    'rating'        => $hostRating,
    'reviews'       => $hostReviews,
    /* Placeholders, same as listing-detail.php's $listing['host'] —
       wire these up to real verification logic once it exists. */
    'verified'      => false,
    'superhost'     => false,
    'response_time' => 'within a day',
];

/* =========================
   LISTING DETAIL LINK HELPER
   (mirrors roomhive_detail_url() in listing.php)
========================== */
function roomhive_detail_url($listing)
{
    return '/webprogg/Listings/listing-detail.php?id=' . urlencode($listing['id']);
}

$listingCount = count($hostListings);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive - <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?></title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/host-profile.css">

</head>

<body>

<!-- =========================
     NAVIGATION BAR
     (identical markup to listing.php / listing-detail.php)
========================== -->

<header class="navbar">

    <a href="<?= $isLoggedIn ? '/webprogg/user/usershome.php' : '/webprogg/index.php' ?>" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <nav class="nav-links">

        <a href="<?= $isLoggedIn ? '/webprogg/user/usershome.php' : '/webprogg/index.php' ?>">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="<?= $isLoggedIn ? '/webprogg/host/becomeahost.php' : '/webprogg/auth/loginform.php' ?>">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>

        <?php if ($isLoggedIn): ?>

            <div class="account-dropdown js-account-dropdown">

                <button type="button" class="my-account js-account-toggle" id="accountDropdownToggle" aria-haspopup="true" aria-expanded="false">
                    <span class="account-circle">
                        <img src="<?= htmlspecialchars($navAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">
                    <a href="/webprogg/user/userprofile.php">My Profile</a>
                    <a href="/webprogg/auth/logout.php">Logout</a>
                </div>

            </div>

        <?php else: ?>

            <a href="/webprogg/auth/loginform.php" class="list-space">LIST YOUR SPACE</a>

        <?php endif; ?>

    </nav>

</header>

<style>
.account-dropdown { position: relative; }
.account-dropdown .my-account { display: flex; align-items: center; gap: 6px; background: none; border: none; cursor: pointer; font: inherit; color: inherit; }
.account-dropdown .dropdown-caret { font-size: 0.7em; transition: transform 0.15s ease; }
.account-dropdown.open .dropdown-caret { transform: rotate(180deg); }
.account-dropdown-menu { display: none; position: absolute; top: 100%; right: 0; min-width: 160px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.12); overflow: hidden; z-index: 1000; margin-top: 8px; }
.account-dropdown.open .account-dropdown-menu { display: block; }
.account-dropdown-menu a { display: block; padding: 10px 16px; text-decoration: none; color: #333; white-space: nowrap; }
.account-dropdown-menu a:hover { background: #f5f5f5; }
</style>

<!-- =========================
     HOST PROFILE PAGE
========================== -->

<main class="hp-page">

    <a href="/webprogg/Listings/listing.php" class="hp-back-link">&#8592; Back to Listings</a>

    <!-- =========================
         HERO
    ========================== -->

    <section class="hp-hero">

        <div class="hp-hero-inner">

            <div class="hp-avatar-frame">
                <img
                    src="<?= htmlspecialchars($host['avatar'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?>"
                    class="hp-avatar"
                >
            </div>

            <div class="hp-hero-text">

                <h1 class="hp-name"><?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?></h1>

                <p class="hp-joined">Hosting since <?= htmlspecialchars($host['member_since'], ENT_QUOTES, 'UTF-8') ?></p>

                <div class="hp-badges">

                    <?php if ($host['verified']): ?>
                        <span class="hp-badge hp-badge-verified">
                            <span class="hp-hex"></span> Verified ID
                        </span>
                    <?php endif; ?>

                    <?php if ($host['superhost']): ?>
                        <span class="hp-badge hp-badge-superhost">
                            <span class="hp-hex"></span> Superhost
                        </span>
                    <?php endif; ?>

                    <?php if ($host['reviews'] > 0): ?>
                        <span class="hp-rating">
                            &#9733; <?= number_format($host['rating'], 1) ?>
                            <span class="hp-rating-count">(<?= (int) $host['reviews'] ?> reviews)</span>
                        </span>
                    <?php else: ?>
                        <span class="hp-rating hp-rating-empty">No reviews yet</span>
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>

    <!-- =========================
         BODY
    ========================== -->

    <div class="hp-layout">

        <div class="hp-main">

            <section class="hp-about">

                <h2>About <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?></h2>

                <?php if ($host['bio'] !== ''): ?>
                    <p class="hp-about-text"><?= nl2br(htmlspecialchars($host['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php else: ?>
                    <p class="hp-about-text hp-about-empty">
                        <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?> hasn't added an introduction yet.
                    </p>
                <?php endif; ?>

            </section>

            <section class="hp-listings">

                <h2>
                    <?= $listingCount > 0
                        ? 'Listings from ' . htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8')
                        : 'No active listings' ?>
                </h2>

                <?php if ($listingCount === 0): ?>

                    <p class="hp-listings-empty">
                        <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?> doesn't have any approved
                        listings right now. Check back later.
                    </p>

                <?php else: ?>

                    <div class="hp-listings-grid">

                        <?php foreach ($hostListings as $listing): ?>

                            <a
                                href="<?= htmlspecialchars(roomhive_detail_url($listing), ENT_QUOTES, 'UTF-8') ?>"
                                class="listing-box"
                            >

                                <div class="rh-card-media">
                                    <img
                                        src="<?= htmlspecialchars($listing['image'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>

                                <div class="listing-box-info">

                                    <h4 class="listing-box-title">
                                        <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </h4>

                                    <div class="listing-box-location">
                                        <img src="/webprogg/images/GPSIcon.png" alt="">
                                        <span><?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="rh-bedrooms">
                                            &middot; <?= (int) $listing['bedrooms'] ?>
                                            <?= $listing['bedrooms'] === 1 ? 'bedroom' : 'bedrooms' ?>
                                        </span>
                                    </div>

                                    <div class="listing-box-price">
                                        <span class="peso">&#8369;</span>
                                        <?= number_format($listing['price']) ?>
                                        <span class="per">/month</span>
                                    </div>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

        <!-- =========================
             SIDEBAR
        ========================== -->

        <aside class="hp-sidebar">

            <div class="hp-card">

                <h3>Host details</h3>

                <div class="hp-detail-row">
                    <span class="hp-hex hp-hex-sm"></span>
                    <span>Response time</span>
                    <strong><?= htmlspecialchars($host['response_time'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="hp-detail-row">
                    <span class="hp-hex hp-hex-sm"></span>
                    <span>Active listings</span>
                    <strong><?= $listingCount ?></strong>
                </div>

                <div class="hp-detail-row">
                    <span class="hp-hex hp-hex-sm"></span>
                    <span>Member since</span>
                    <strong><?= htmlspecialchars($host['member_since'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <?php if ($isOwnProfile): ?>

                    <a href="/webprogg/user/userprofile.php" class="hp-btn hp-btn-primary">
                        Edit your profile
                    </a>

                <?php elseif ($isLoggedIn): ?>

                    <a
                        href="/webprogg/user/start-conversation.php?host_id=<?= (int) $host['id'] ?>"
                        class="hp-btn hp-btn-primary"
                    >
                        Message <?= htmlspecialchars(explode(' ', $host['name'])[0], ENT_QUOTES, 'UTF-8') ?>
                    </a>

                <?php else: ?>

                    <a href="/webprogg/auth/loginform.php" class="hp-btn hp-btn-primary">
                        Log in to message host
                    </a>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</main>

<!-- =========================
     FOOTER (shared markup)
========================== -->

<footer class="site-footer">

    <div class="footer-top">

        <div class="footer-brand">

            <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">

            <p class="footer-tagline">
                Find your next room, studio, or shared space —
                verified listings, no hidden fees.
            </p>

            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="">
                <span>0917 156 3974</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="">
                <span>iamroomhivehost@gmail.com</span>
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
        <p>&copy; <?= date('Y') ?> RoomHive. All rights reserved.</p>
    </div>

</footer>

<!-- MAIN JAVASCRIPT (handles account dropdown open/close) -->
<script src="/webprogg/assets/javaScript.js"></script>

</body>
</html>