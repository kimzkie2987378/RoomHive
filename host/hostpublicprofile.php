<?php
/* =========================
   hostpublicprofile.php
   Public "meet the host" page, linked from listing-detail.php's
   "View Host Profile" button (?id= is the host's users.id).

   FIXES:
   - Navbar uses the shared includes/navbar.php (role-aware).
   - === THIS FIX === a STRUCTURAL CSS layer was added to the
     inline <style>: the page's layout classes (.hp-hero-inner,
     .hp-avatar size, .hp-badges, .hp-hex shapes, .hp-layout,
     .hp-listings-grid, .hp-detail-row base, .hp-btn) were
     never defined — hostprofile.css is the DASHBOARD's css and
     does not contain them, so the page rendered unstyled.
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

/* Nav avatar staleness handling + viewer's is_host for navbar.php */
 $navAvatar = '/webprogg/images/default-avatar.png';
 $isHost    = false;

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $navAvatarStmt = $pdo->prepare("SELECT avatar_path, is_host FROM users WHERE id = :id LIMIT 1");
    $navAvatarStmt->execute(['id' => $_SESSION['user_id']]);
    $navAvatarRow = $navAvatarStmt->fetch();

    if ($navAvatarRow) {
        $navAvatar = !empty($navAvatarRow['avatar_path']) ? $navAvatarRow['avatar_path'] : $navAvatar;
        $isHost    = !empty($navAvatarRow['is_host']);
    }
}

 $notification_count = 0;

/* Shared navbar contract */
 $navigation = [
    "HOME"          => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS"      => "/webprogg/Listings/listing.php",
    "HOW IT WORKS"  => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB"     => "/webprogg/hiveclub.php",
    "CONTACTS"      => "/webprogg/misc/contacts.php",
];

 $currentPage = '';

/* =========================
   RESOLVE HOST FROM ?id=
========================== */
 $hostId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

 $hostStmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host, created_at
     FROM users
     WHERE id = :id AND is_host = 1
     LIMIT 1"
);
 $hostStmt->execute(['id' => $hostId]);
 $hostRow = $hostStmt->fetch();

if ($hostRow === false) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

 $isOwnProfile = $isLoggedIn && (int) ($_SESSION['user_id'] ?? 0) === (int) $hostRow['id'];

/* =========================
   HOST'S APPROVED LISTINGS
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
    'verified'      => false,
    'superhost'     => false,
    'response_time' => 'within a day',
];

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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/host-profile.css">
    <link rel="stylesheet" href="/webprogg/assets/listings-style.css">

    <script>document.documentElement.classList.add("js");</script>

    <!-- =====================================================
         HOST PUBLIC PROFILE — LAYER 1: STRUCTURE (THE FIX)
         These classes were previously undefined anywhere:
         hostprofile.css is the DASHBOARD's css. This layer
         defines the public profile's own layout and loads
         after host-profile.css so it wins the cascade.
    ====================================================== -->

    <style>

        .hp-page {
            --hp-honey: #eda423;
            --hp-honey-light: #f6c04e;
            --hp-honey-dark: #d99218;
            --hp-moss: #2f9e5b;
            --hp-ink: #1c2a38;
            --hp-ink-soft: #5d6875;
            --hp-line: rgba(28, 42, 56, 0.08);
            --hp-gold-shadow: 0 14px 28px rgba(237, 164, 35, 0.16);

            position: relative;
            overflow-x: clip;

            /* === STRUCTURE === page shell, clears the fixed navbar */
            max-width: 1280px;
            margin: 0 auto;
            padding: 130px 40px 80px;
        }

        /* --- Hero: card-style, overrides the dashboard .hp-hero --- */
        .hp-page .hp-hero {
            display: block;
            margin-top: 0;
            padding: 30px 34px;
            position: relative;
            z-index: 1;

            background: #ffffff;
            border: 1px solid var(--hp-line);
            border-radius: 24px;
            box-shadow: 0 6px 20px rgba(28, 43, 36, 0.07);
        }

        .hp-hero-inner {
            display: flex;
            align-items: center;
            gap: 26px;
            flex-wrap: wrap;
        }

        .hp-avatar-frame {
            position: relative;
            flex-shrink: 0;
        }

        .hp-page .hp-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .hp-hero-text {
            flex: 1;
            min-width: 240px;
        }

        .hp-name {
            margin: 0 0 4px;
            font-size: clamp(26px, 3vw, 36px);
        }

        .hp-joined {
            margin: 0;
            font-size: 13px;
        }

        .hp-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .hp-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            background: #ffffff;
            border: 1px solid var(--hp-line);
        }

        /* Hex dot — honey tick used in badges + detail rows */
        .hp-hex {
            display: inline-block;
            width: 11px;
            height: 12px;
            flex-shrink: 0;
            background: var(--hp-honey);
            clip-path: polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0% 50%);
        }

        .hp-hex-sm {
            width: 9px;
            height: 10px;
        }

        .hp-rating {
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* --- Two-column body --- */
        .hp-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 26px;
            align-items: start;
            margin-top: 26px;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 900px) {
            .hp-layout { grid-template-columns: 1fr; }
        }

        .hp-main { min-width: 0; }

        .hp-about { margin-bottom: 34px; }

        .hp-about h2,
        .hp-listings h2 {
            margin: 0 0 16px;
            font-family: "Fraunces", serif;
            font-size: 1.3rem;
        }

        .hp-about-text {
            margin: 0;
            font-size: 14px;
        }

        .hp-about-empty,
        .hp-listings-empty {
            font-size: 14px;
        }

        .hp-listings { margin-bottom: 20px; }

        .hp-listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 18px;
        }

        /* --- Sidebar --- */
        .hp-page .hp-sidebar {
            position: sticky;
            top: 130px;
        }

        .hp-card h3 {
            margin: 0 0 12px;
            font-size: 15px;
        }

        .hp-detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 4px;
            border-bottom: 1px solid var(--hp-line);
            font-size: 13px;
            color: var(--hp-ink-soft);
        }

        .hp-detail-row:last-of-type { border-bottom: none; }

        /* label column stretches, value column sits right */
        .hp-detail-row span:nth-of-type(2) { flex: 1; }

        .hp-detail-row strong {
            font-size: 13px;
            color: var(--hp-ink);
        }

        .hp-btn {
            display: block;
            width: 100%;
            margin-top: 16px;
            text-align: center;
            text-decoration: none;
            font-family: inherit;
            box-sizing: border-box;
        }

        /* --- Mobile shell --- */
        @media (max-width: 700px) {
            .hp-page { padding: 120px 18px 60px; }
            .hp-page .hp-hero { padding: 24px 20px; }
            .hp-page .hp-sidebar { position: static; }
        }

    </style>

    <!-- =====================================================
         HOST PUBLIC PROFILE — LAYER 2: HIVE POLISH
         (colors, badges, cards, buttons, decorations)
    ====================================================== -->

    <style>

        /* =====================================================
           DECORATION LAYER — honeycomb + glow blobs
        ====================================================== */

        .hp-deco {
            position: absolute;
            inset: 0;

            background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.07' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 49px;

            -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 45%);
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 45%);

            pointer-events: none;

            z-index: 0;
        }

        .hp-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
        }

        .hp-blob-1 {
            width: 360px;
            height: 360px;
            top: -140px;
            right: -120px;
            background: radial-gradient(circle at 30% 30%, rgba(246, 196, 78, 0.8), rgba(237, 164, 35, 0.22) 60%, transparent 75%);
            animation: hpDrift 14s ease-in-out infinite alternate;
        }

        .hp-blob-2 {
            width: 260px;
            height: 260px;
            top: 380px;
            left: -140px;
            background: radial-gradient(circle at 60% 40%, rgba(246, 196, 78, 0.6), rgba(237, 164, 35, 0.18) 60%, transparent 75%);
            animation: hpDrift 18s ease-in-out infinite alternate-reverse;
        }

        @keyframes hpDrift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, -24px) scale(1.08); }
        }

        /* =====================================================
           ENTRANCE REVEALS (JS-gated)
        ====================================================== */

        @keyframes hpRise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .hp-reveal {
            opacity: 0;
            animation: hpRise 0.6s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) forwards;
        }

        /* =====================================================
           BACK LINK
        ====================================================== */

        .hp-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;

            color: var(--hp-ink-soft) !important;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease, transform 0.15s ease;
        }

        .hp-back-link:hover {
            color: var(--hp-honey-dark) !important;
            transform: translateX(-3px);
        }

        /* =====================================================
           HERO — name shimmer, gold-ring avatar, badges
        ====================================================== */

        .hp-avatar {
            border: 3px solid #ffffff !important;
            box-shadow:
                0 0 0 3px var(--hp-honey),
                0 14px 28px rgba(237, 164, 35, 0.3) !important;
            transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .hp-avatar:hover {
            transform: scale(1.04) rotate(-2deg);
        }

        .hp-name {
            color: var(--hp-ink) !important;
            font-weight: 800 !important;
            letter-spacing: -0.6px;

            background: linear-gradient(92deg, var(--hp-ink) 0%, var(--hp-ink) 55%, #eda423 85%, #f6c04e 100%);
            background-size: 200% auto;

            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;

            animation: hpShimmer 5s linear infinite;
        }

        @keyframes hpShimmer {
            to { background-position: 200% center; }
        }

        .hp-joined {
            color: var(--hp-ink-soft) !important;
        }

        .hp-badge {
            font-weight: 700 !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hp-badge:hover {
            transform: translateY(-2px);
        }

        .hp-badge-verified {
            background: linear-gradient(135deg, #f6b93b, var(--hp-honey)) !important;
            border-color: transparent !important;
            color: var(--hp-ink) !important;
            box-shadow: 0 6px 14px rgba(237, 164, 35, 0.35);
        }

        .hp-badge-superhost {
            background: var(--hp-moss) !important;
            border-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 6px 14px rgba(47, 158, 91, 0.35);
        }

        .hp-rating {
            color: #b07708 !important;
            font-weight: 700 !important;
        }

        .hp-rating-count {
            color: var(--hp-ink-soft) !important;
            font-weight: 500 !important;
        }

        .hp-rating-empty {
            color: var(--hp-ink-soft) !important;
        }

        /* =====================================================
           SECTION HEADINGS — gold accent bar
        ====================================================== */

        .hp-about h2,
        .hp-listings h2 {
            position: relative;
            display: inline-block;
            color: var(--hp-ink) !important;
            font-weight: 800 !important;
        }

        .hp-about h2::after,
        .hp-listings h2::after {
            content: "";
            position: absolute;
            width: 36px;
            height: 3px;
            left: 0;
            bottom: -7px;
            background: linear-gradient(90deg, #f6b93b, var(--hp-honey));
            border-radius: 2px;
        }

        .hp-about-text {
            color: var(--hp-ink-soft) !important;
            line-height: 1.75 !important;
        }

        .hp-about-empty,
        .hp-listings-empty {
            color: var(--hp-ink-soft) !important;
        }

        /* =====================================================
           LISTING CARDS — hover lift + image zoom + gold price
        ====================================================== */

        .hp-listings-grid .listing-box {
            border-radius: 16px;
            transition:
                transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease,
                border-color 0.3s ease;
        }

        .hp-listings-grid .listing-box:hover {
            transform: translateY(-6px);
            border-color: rgba(237, 164, 35, 0.45) !important;
            box-shadow: var(--hp-gold-shadow) !important;
        }

        .hp-listings-grid .rh-card-media {
            aspect-ratio: 4 / 3;
            overflow: hidden;
        }

        .hp-listings-grid .rh-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .hp-listings-grid .listing-box:hover .rh-card-media img {
            transform: scale(1.06);
        }

        .hp-listings-grid .listing-box-title {
            color: var(--hp-ink) !important;
            font-weight: 700 !important;
        }

        .hp-listings-grid .listing-box-location {
            color: var(--hp-ink-soft) !important;
        }

        .hp-listings-grid .listing-box-price {
            color: var(--hp-ink) !important;
        }

        .hp-listings-grid .listing-box-price .peso {
            color: var(--hp-honey-dark) !important;
        }

        /* =====================================================
           SIDEBAR CARD — honey top bar + hover rows
        ====================================================== */

        .hp-page .hp-card {
            border-top: 4px solid var(--hp-honey) !important;
            transition:
                box-shadow 0.25s ease,
                border-color 0.25s ease;
        }

        .hp-page .hp-card:hover {
            box-shadow: var(--hp-gold-shadow) !important;
        }

        .hp-card h3 {
            color: var(--hp-ink) !important;
            font-weight: 800 !important;
        }

        .hp-detail-row {
            transition: background 0.15s ease, transform 0.15s ease;
            border-radius: 10px;
        }

        .hp-detail-row:hover {
            background: #fff8ec;
            transform: translateX(3px);
        }

        .hp-detail-row strong {
            color: var(--hp-ink) !important;
        }

        /* Hex marks honey */
        .hp-hex { background: var(--hp-honey) !important; }
        .hp-hex-sm { background: var(--hp-honey) !important; }

        /* =====================================================
           BUTTONS — gradient honey primary
        ====================================================== */

        .hp-btn-primary {
            background: linear-gradient(135deg, #f6b93b, var(--hp-honey)) !important;
            border: none !important;
            color: var(--hp-ink) !important;
            font-weight: 700 !important;
            box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35) !important;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease !important;
        }

        .hp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45) !important;
        }

        .hp-btn-primary:active {
            transform: translateY(0) scale(0.98);
        }

        /* =====================================================
           RESPONSIVE / MOTION SAFETY
        ====================================================== */

        @media (max-width: 700px) {
            .hp-deco {
                -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.6), transparent 30%);
                mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.6), transparent 30%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .hp-deco,
            .hp-blob,
            .hp-name {
                animation: none !important;
            }

            .js .hp-reveal {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }

            .hp-avatar,
            .hp-badge,
            .hp-listings-grid .listing-box,
            .hp-listings-grid .rh-card-media img,
            .hp-detail-row,
            .hp-btn-primary,
            .hp-back-link {
                transition: none !important;
            }
        }

    </style>

</head>

<body>

<!-- =========================
     NAVIGATION BAR (shared)
========================== -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>

<!-- =========================
     HOST PROFILE PAGE
========================== -->

<main class="hp-page">

    <div class="hp-deco" aria-hidden="true">
        <span class="hp-blob hp-blob-1"></span>
        <span class="hp-blob hp-blob-2"></span>
    </div>

    <a href="/webprogg/Listings/listing.php" class="hp-back-link hp-reveal" style="--d: .05s;">&#8592; Back to Listings</a>

    <!-- =========================
         HERO
    ========================== -->

    <section class="hp-hero hp-reveal" style="--d: .1s;">

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

            <section class="hp-about hp-reveal" style="--d: .18s;">

                <h2>About <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?></h2>

                <?php if ($host['bio'] !== ''): ?>
                    <p class="hp-about-text"><?= nl2br(htmlspecialchars($host['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php else: ?>
                    <p class="hp-about-text hp-about-empty">
                        <?= htmlspecialchars($host['name'], ENT_QUOTES, 'UTF-8') ?> hasn't added an introduction yet.
                    </p>
                <?php endif; ?>

            </section>

            <section class="hp-listings hp-reveal" style="--d: .24s;">

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

        <aside class="hp-sidebar hp-reveal" style="--d: .2s;">

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

<script src="/webprogg/assets/javaScript.js"></script>

</body>
</html>