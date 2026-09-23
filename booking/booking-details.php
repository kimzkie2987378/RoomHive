<?php
/* =========================================================
   ROOMHIVE — BOOKING DETAILS (tenant + host read-access)
   booking-details.php?id=<booking_id>

   VIEWERS:
   - TENANT (owner): full view — Pay Balance, Cancel Booking
     (with the floating 2% fee warning card), review modal.
   - HOST (listing owner): read access via "View Details" on
     hostbookings.php — status banners, payment summary,
     refund info. NO tenant-only actions.

   The 2%-fee cancel flow, floating warning card, and
   post-cancel review modal are all wired and tenant-only.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* ---------- AUTH ---------- */
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
    /* Hosts land on their dashboard — but they may still view
       a booking's details via hostbookings.php's View Details
       link, so we do NOT hard-redirect here. */
}

 $navAvatar = sync_user_session($dbUser);

 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
);
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* ---------- LOAD BOOKING ---------- */
 $bookingId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

 $bStmt = $pdo->prepare(
    "SELECT b.id, b.user_id, b.total, b.amount_paid, b.status, b.payment_status,
            b.booked_at, b.checkin_date, b.checkout_date, b.guests,
            b.host_payout_amount, b.refunded_amount, b.refunded_at,
            l.id AS listing_id, l.title, l.location, l.exact_address,
            l.price AS monthly_price,
            p.photo_path AS cover_photo,
            h.id AS host_id, h.name AS host_name, h.avatar_path AS host_avatar
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users h    ON h.id = l.user_id
     LEFT JOIN listing_photos p
            ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE b.id = :id
     LIMIT 1"
);
 $bStmt->execute(['id' => $bookingId]);
 $booking = $bStmt->fetch();

/* FIXED: b.user_id is actually selected above, so this guard
   passes for the tenant AND the listing's host, and bounces
   everyone else. */
 $isTenantViewer = $booking && ((int) $booking['user_id'] === (int) $_SESSION['user_id']);
 $isHostViewer   = $booking && ((int) $booking['host_id']   === (int) $_SESSION['user_id']);

if (!$booking || (!$isTenantViewer && !$isHostViewer)) {
    header("Location: /webprogg/booking/userbookings.php");
    exit;
}

/* ---------- DERIVED FIGURES ---------- */
 $total      = round((float) $booking['total'], 2);
 $paid       = round((float) $booking['amount_paid'], 2);
 $balance    = round(max(0, $total - $paid), 2);
 $refunded   = round((float) ($booking['refunded_amount'] ?? 0), 2);
 $paidPct    = $total > 0 ? (int) round(min(100, ($paid / $total) * 100)) : 0;
 $fullyPaid  = $paid > 0.005 && $balance <= 0.005;
 $cancellable = in_array($booking['status'], ['pending', 'confirmed'], true);

 $isLongTerm = ($booking['checkin_date'] !== null && $booking['checkout_date'] === null);
 $payBalanceUrl = '/webprogg/booking/listingpayment.php'
    . '?listing_id=' . (int) $booking['listing_id']
    . '&pay_balance=' . (int) $booking['id'];
 $messageHostUrl = '/webprogg/user/start-conversation.php?host_id='
    . (int) $booking['host_id'] . '&listing_id=' . (int) $booking['listing_id'];

/* ---------- REVIEW STATE (post-cancel rating) ---------- */
 $canReview = $isTenantViewer && in_array($booking['status'], ['cancelled', 'completed'], true);

 $existingReview = null;
if ($canReview) {
    $rvStmt = $pdo->prepare(
        "SELECT id, rating FROM reviews WHERE user_id = :u AND listing_id = :l LIMIT 1"
    );
    $rvStmt->execute([
        'u' => $_SESSION['user_id'],
        'l' => (int) $booking['listing_id'],
    ]);
    $existingReview = $rvStmt->fetch();
}
 $hasReviewed = $existingReview !== null;

 $activeSidebar = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking #<?php echo (int) $booking['id']; ?> — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>

<style>
    .bd-hero {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .bd-photo {
        width: 190px;
        height: 130px;
        border-radius: 14px;
        object-fit: cover;
        flex-shrink: 0;
        background: #F0EEE6;
    }
    .bd-title { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: var(--up-navy, #1c2a38); letter-spacing: -0.4px; }
    .bd-location { display: flex; align-items: center; gap: 6px; margin: 0 0 10px; font-size: 13px; color: var(--up-text-muted, #5d6875); }
    .bd-location img { width: 14px; height: 14px; }
    .bd-pills { display: flex; gap: 8px; flex-wrap: wrap; }

    .bd-facts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .bd-fact {
        background: #F6F7FB;
        border: 1px solid rgba(28, 42, 56, 0.06);
        border-radius: 12px;
        padding: 12px 14px;
    }
    .bd-fact span { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: #8B93A6; margin-bottom: 4px; }
    .bd-fact strong { font-size: 14px; color: var(--up-navy, #1c2a38); font-weight: 700; }

    .bd-host {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid rgba(28, 42, 56, 0.08);
        flex-wrap: wrap;
    }
    .bd-host-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #FFE3B3; background: #F6F4EE; }
    .bd-host-info { flex: 1; min-width: 150px; }
    .bd-host-info strong { display: block; font-size: 14px; color: var(--up-navy, #1c2a38); }
    .bd-host-info span { font-size: 12px; color: #8B93A6; }

    .bd-pay-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        font-size: 14px;
        color: var(--up-text-muted, #5d6875);
    }
    .bd-pay-row + .bd-pay-row { border-top: 1px dashed rgba(28, 42, 56, 0.10); }
    .bd-pay-row strong { color: var(--up-navy, #1c2a38); font-weight: 700; }
    .bd-pay-row.bd-total strong { color: #C77800; font-size: 17px; font-weight: 800; }
    .bd-pay-row.bd-refund strong { color: #1e7a3d; }

    .bd-progress {
        height: 8px;
        border-radius: 999px;
        background: #EEF1F6;
        overflow: hidden;
        margin: 12px 0 6px;
    }
    .bd-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #FFC96B, #F5A623);
        transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .bd-progress-caption { font-size: 11.5px; color: #8B93A6; margin: 0 0 14px; }

    .bd-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .bd-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.55;
        margin-bottom: 18px;
    }
    .bd-banner-amber { background: #FFF6E9; border: 1px solid #F5C77E; color: #8A5A10; }
    .bd-banner-green { background: #E9F7EF; border: 1px solid #BFE8CF; color: #1e7a3d; }
    .bd-banner-red   { background: #fdecea; border: 1px solid #f5c6c2; color: #a1332e; }
    .bd-banner-blue  { background: #EAF2FE; border: 1px solid #C9D9F5; color: #1A56DB; }

    /* ---- floating cancel card (cbc-) ---- */
    .cbc-backdrop {
        position: fixed; inset: 0; z-index: 1300;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        background: rgba(28, 42, 56, 0.45);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        opacity: 0; visibility: hidden; pointer-events: none;
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }
    .cbc-backdrop.open { opacity: 1; visibility: visible; pointer-events: auto; }
    .cbc-card {
        position: relative;
        width: 100%; max-width: 400px; max-height: calc(100vh - 40px); overflow-y: auto;
        background: #ffffff; border-radius: 22px;
        padding: 30px 26px 24px;
        box-shadow: 0 30px 70px rgba(28, 42, 56, 0.35);
        font-family: "Poppins", sans-serif;
        opacity: 0; transform: translateY(26px) scale(0.96);
        transition: opacity 0.3s cubic-bezier(0.22,1,0.36,1), transform 0.3s cubic-bezier(0.22,1,0.36,1);
    }
    .cbc-backdrop.open .cbc-card { opacity: 1; transform: translateY(0) scale(1); }
    .cbc-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
        background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
        border-radius: 22px 22px 0 0;
    }
    .cbc-close {
        position: absolute; top: 12px; right: 14px;
        width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        background: #f4f1e7; border: none; border-radius: 50%;
        color: #6b7684; font-size: 16px; line-height: 1; cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .cbc-close:hover { background: #eda423; color: #fff; transform: rotate(90deg); }
    .cbc-icon {
        width: 54px; height: 54px; margin: 0 auto 14px;
        display: flex; align-items: center; justify-content: center;
        background: #FFF6E9; border: 1px solid #F5C77E; border-radius: 50%;
        color: #C77800; font-size: 24px;
    }
    .cbc-title { margin: 0 0 6px; text-align: center; color: #1c2a38; font-size: 18px; font-weight: 800; letter-spacing: -0.3px; }
    .cbc-subtitle { margin: 0 0 18px; text-align: center; color: #5d6875; font-size: 12.5px; line-height: 1.6; }
    .cbc-breakdown {
        margin-bottom: 18px; padding: 14px 16px;
        background: #FBF7EF; border: 1px dashed #E8D9BC; border-radius: 14px;
    }
    .cbc-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 7px 0; font-size: 13px; color: #5d6875;
    }
    .cbc-row + .cbc-row { border-top: 1px dashed #E8D9BC; }
    .cbc-row strong { color: #1c2a38; font-weight: 700; }
    .cbc-row-fee strong { color: #C77800; }
    .cbc-row-refund strong { color: #1e7a3d; font-size: 15px; font-weight: 800; }
    .cbc-note { margin: 0 0 18px; text-align: center; color: #8d99a5; font-size: 11.5px; line-height: 1.6; }
    .cbc-error {
        display: none; padding: 10px 12px; margin-bottom: 14px;
        background: #fdecec; border: 1px solid #f3b9b9; border-radius: 10px;
        color: #a4302f; font-size: 12.5px; text-align: center;
    }
    .cbc-error.show { display: block; }
    .cbc-actions { display: flex; flex-direction: column; gap: 10px; }
    .cbc-btn {
        width: 100%; height: 46px;
        border: none; border-radius: 12px;
        font-family: inherit; font-size: 13px; font-weight: 700; letter-spacing: 0.3px;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    }
    .cbc-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none !important; }
    .cbc-btn-keep { background: #ffffff; border: 1.5px solid #dfe4ea; color: #1c2a38; }
    .cbc-btn-keep:hover:not(:disabled) { border-color: #eda423; color: #b07708; transform: translateY(-1px); }
    .cbc-btn-confirm {
        background: linear-gradient(135deg, #e0524d, #c84642);
        color: #ffffff; box-shadow: 0 8px 18px rgba(224, 82, 77, 0.35);
    }
    .cbc-btn-confirm:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(224, 82, 77, 0.45); }

    /* ---- floating review modal (rvw-) ---- */
    .rvw-backdrop {
        position: fixed; inset: 0; z-index: 1300;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        background: rgba(28, 42, 56, 0.45);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        opacity: 0; visibility: hidden; pointer-events: none;
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }
    .rvw-backdrop.open { opacity: 1; visibility: visible; pointer-events: auto; }
    .rvw-card {
        position: relative;
        width: 100%; max-width: 380px;
        background: #ffffff; border-radius: 22px;
        padding: 30px 26px 24px;
        box-shadow: 0 30px 70px rgba(28, 42, 56, 0.35);
        font-family: "Poppins", sans-serif;
        opacity: 0; transform: translateY(26px) scale(0.96);
        transition: opacity 0.3s cubic-bezier(0.22,1,0.36,1), transform 0.3s cubic-bezier(0.22,1,0.36,1);
    }
    .rvw-backdrop.open .rvw-card { opacity: 1; transform: translateY(0) scale(1); }
    .rvw-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
        background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
        border-radius: 22px 22px 0 0;
    }
    .rvw-close {
        position: absolute; top: 12px; right: 14px;
        width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        background: #f4f1e7; border: none; border-radius: 50%;
        color: #6b7684; font-size: 16px; line-height: 1; cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .rvw-close:hover { background: #eda423; color: #fff; transform: rotate(90deg); }
    .rvw-title { margin: 0 0 4px; text-align: center; color: #1c2a38; font-size: 17px; font-weight: 800; }
    .rvw-sub { margin: 0 0 16px; text-align: center; color: #5d6875; font-size: 12.5px; }
    .rvw-stars { display: flex; justify-content: center; gap: 6px; margin-bottom: 16px; }
    .rvw-star {
        width: 42px; height: 42px;
        background: none; border: none;
        font-size: 30px; line-height: 1; color: #d9dee4;
        cursor: pointer; transition: color 0.12s ease, transform 0.12s ease;
    }
    .rvw-star.lit { color: #F5B301; }
    .rvw-star:hover { transform: scale(1.15); }
    .rvw-error {
        display: none; padding: 9px 12px; margin-bottom: 12px;
        background: #fdecec; border: 1px solid #f3b9b9; border-radius: 10px;
        color: #a4302f; font-size: 12.5px; text-align: center;
    }
    .rvw-error.show { display: block; }
    .rvw-textarea {
        width: 100%; box-sizing: border-box;
        min-height: 90px; padding: 12px 14px;
        border: 1.5px solid #e3e7ec; border-radius: 12px;
        font-family: inherit; font-size: 13px; resize: vertical;
        margin-bottom: 14px; outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .rvw-textarea:focus { border-color: #eda423; box-shadow: 0 0 0 3px rgba(237, 164, 35, 0.15); }
    .rvw-submit {
        width: 100%; height: 46px;
        background: linear-gradient(135deg, #f6b93b, #eda423);
        border: none; border-radius: 12px;
        color: #1c2a38; font-family: inherit; font-size: 13px; font-weight: 800;
        cursor: pointer; box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .rvw-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45); }
    .rvw-submit:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

    @media (max-width: 520px) {
        .bd-photo { width: 100%; height: 170px; }
        .cbc-card, .rvw-card { padding: 26px 18px 20px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .cbc-backdrop, .cbc-card, .rvw-backdrop, .rvw-card { transition: none; }
        .bd-progress-fill { transition: none; }
    }
</style>
</head>
<body>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<!-- PAGE HEADER -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Booking Details</span>
    <h1>Booking #<?php echo (int) $booking['id']; ?></h1>
    <p class="ub-lead">Everything about this stay — dates, payment state, and your host.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if (isset($_GET['cancelled'])): ?>
      <section class="up-alert up-alert-success" style="margin-bottom:18px;">
        <p>&#10003; Booking cancelled. Your refund (minus the 2% cancellation fee) has been credited to your RoomHive wallet.</p>
      </section>
    <?php endif; ?>

    <?php if (isset($_GET['reviewed'])): ?>
      <section class="up-alert up-alert-success" style="margin-bottom:18px;">
        <p>&#9733; Thanks! Your review has been saved.</p>
      </section>
    <?php endif; ?>

    <!-- ============ BOOKING OVERVIEW ============ -->
    <section class="up-card up-reveal">

      <?php if ($isHostViewer && !$isTenantViewer): ?>
        <div class="bd-banner bd-banner-blue">
          <span>&#128065;</span>
          <span>
            You're viewing this booking as the <strong>host</strong>.
            <?php echo h($booking['guest_name'] ?? 'The guest'); ?> is the tenant on this stay —
            Accept/Decline and Mark Completed live on your Bookings page.
          </span>
        </div>
      <?php endif; ?>

      <?php if ($booking['status'] === 'pending'): ?>
        <div class="bd-banner bd-banner-amber">
          <span>&#8987;</span>
          <span>
            Application pending — <?php echo h($booking['host_name']); ?> has 24 hours to respond
            before it is auto-declined and your reserve is refunded in full.
          </span>
        </div>
      <?php elseif ($booking['status'] === 'confirmed'): ?>
        <?php if ($fullyPaid): ?>
          <div class="bd-banner bd-banner-green">
            <span>&#9989;</span>
            <span>Accepted by the host and fully paid — you're all set to enjoy your stay!</span>
          </div>
        <?php else: ?>
          <div class="bd-banner bd-banner-green">
            <span>&#9989;</span>
            <span>Accepted by the host — the host has signed your official receipt. Pay the remaining balance below to fully enjoy your stay.</span>
          </div>
        <?php endif; ?>
      <?php elseif ($booking['status'] === 'rejected'): ?>
        <div class="bd-banner bd-banner-red">
          <span>&#10060;</span>
          <span>
            Not accepted by the host.
            <?php if ($refunded > 0): ?>
              Your full payment of &#8369;<?php echo h(number_format($refunded, 2)); ?> has been refunded to your wallet.
            <?php endif; ?>
          </span>
        </div>
      <?php elseif ($booking['status'] === 'cancelled'): ?>
        <div class="bd-banner bd-banner-amber">
          <span>&#8505;&#65039;</span>
          <span>
            This booking was cancelled.
            <?php if ($refunded > 0): ?>
              &#8369;<?php echo h(number_format($refunded, 2)); ?> was refunded to your wallet
              (after the 2% cancellation fee).
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>

      <div class="bd-hero">
        <img class="bd-photo" src="<?php echo h(resolve_photo($booking['cover_photo'])); ?>" alt="<?php echo h($booking['title']); ?>">
        <div style="flex:1; min-width:220px;">
          <h2 class="bd-title"><?php echo h($booking['title']); ?></h2>
          <p class="bd-location">
            <img src="/webprogg/images/GPSIcon.png" alt="">
            <?php echo h($booking['exact_address'] !== '' ? $booking['exact_address'] . ', ' : ''); ?><?php echo h($booking['location']); ?>
          </p>
          <div class="bd-pills">
            <span class="up-status up-status-<?php echo h($booking['status']); ?>">
              <?php echo h(ucfirst($booking['status'])); ?>
            </span>
            <span class="up-status up-status-<?php echo $fullyPaid ? 'confirmed' : h($booking['payment_status']); ?>">
              <?php echo $fullyPaid ? 'Fully Paid' : h(ucfirst((string) $booking['payment_status'])); ?>
            </span>
          </div>
        </div>
      </div>

      <div class="bd-facts">
        <div class="bd-fact">
          <span>Check-in</span>
          <strong><?php echo $booking['checkin_date'] ? h(date('M j, Y', strtotime($booking['checkin_date']))) : '&mdash;'; ?></strong>
        </div>
        <div class="bd-fact">
          <span><?php echo $isLongTerm ? 'Duration' : 'Check-out'; ?></span>
          <strong><?php echo $isLongTerm ? 'Long Term' : ($booking['checkout_date'] ? h(date('M j, Y', strtotime($booking['checkout_date']))) : '&mdash;'); ?></strong>
        </div>
        <div class="bd-fact">
          <span>Guests</span>
          <strong><?php echo h($booking['guests'] ?: '1'); ?></strong>
        </div>
        <div class="bd-fact">
          <span>Booked on</span>
          <strong><?php echo h(date('M j, Y', strtotime($booking['booked_at']))); ?></strong>
        </div>
      </div>

      <div class="bd-host">
        <img class="bd-host-avatar" src="<?php echo h(resolve_photo($booking['host_avatar'], '/webprogg/images/default-avatar.png')); ?>" alt="<?php echo h($booking['host_name']); ?>">
        <div class="bd-host-info">
          <strong><?php echo h($booking['host_name']); ?></strong>
          <span><?php echo $isHostViewer ? 'You are the host of this stay' : 'Your host for this stay'; ?></span>
        </div>
        <?php if (!$isHostViewer): ?>
          <a href="<?php echo h($messageHostUrl); ?>" class="up-btn-outline" style="text-decoration:none; padding:10px 18px;">MESSAGE HOST</a>
        <?php else: ?>
          <a href="/webprogg/host/hostbookings.php" class="up-btn-outline" style="text-decoration:none; padding:10px 18px;">HOST BOOKINGS</a>
        <?php endif; ?>
      </div>

    </section>

    <!-- ============ PAYMENT SUMMARY ============ -->
    <section class="up-card up-reveal" style="--i: 1;">
      <div class="up-card-header">
        <h3>Payment Summary</h3>
        <a href="/webprogg/user/userpayments.php" class="up-link-view-all">Payment History</a>
      </div>

      <div class="bd-pay-row">
        <span>Booking Total</span>
        <strong>&#8369; <?php echo h(number_format($total, 2)); ?></strong>
      </div>
      <div class="bd-pay-row">
        <span>Amount Paid</span>
        <strong>&#8369; <?php echo h(number_format($paid, 2)); ?></strong>
      </div>
      <?php if (!$fullyPaid && $paid > 0): ?>
        <div class="bd-progress" role="progressbar" aria-valuenow="<?php echo $paidPct; ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="bd-progress-fill" style="width: <?php echo $paidPct; ?>%;"></div>
        </div>
        <p class="bd-progress-caption"><b><?php echo $paidPct; ?>%</b> of the booking is paid</p>
      <?php endif; ?>

      <?php if ($refunded > 0 && in_array($booking['status'], ['cancelled', 'rejected'], true)): ?>
        <div class="bd-pay-row bd-refund">
          <span>Refunded to wallet</span>
          <strong>&#8369; <?php echo h(number_format($refunded, 2)); ?></strong>
        </div>
      <?php else: ?>
        <div class="bd-pay-row bd-total">
          <span><?php echo $fullyPaid ? 'Fully Paid' : 'Remaining Balance'; ?></span>
          <strong>&#8369; <?php echo h(number_format($fullyPaid ? 0 : $balance, 2)); ?></strong>
        </div>
      <?php endif; ?>

      <div class="bd-actions">
        <?php if ($isTenantViewer && $cancellable && !$fullyPaid && $balance > 0): ?>
          <a href="<?php echo h($payBalanceUrl); ?>" class="up-btn-solid" style="text-decoration:none; padding:12px 22px;">
            PAY BALANCE &#8369;<?php echo h(number_format($balance, 2)); ?>
          </a>
        <?php elseif ($fullyPaid): ?>
          <span class="up-status up-status-confirmed" style="padding:10px 18px;">&#10003; Nothing left to pay</span>
        <?php endif; ?>

        <a href="<?php echo $isHostViewer ? '/webprogg/host/hostbookings.php' : '/webprogg/booking/userbookings.php'; ?>" class="up-btn-outline" style="text-decoration:none; padding:12px 22px;">
          <?php echo $isHostViewer ? 'BACK TO HOST BOOKINGS' : 'BACK TO MY BOOKINGS'; ?>
        </a>
      </div>

      <?php if ($cancellable && $isTenantViewer): ?>
        <div class="bd-actions" style="margin-top:12px; padding-top:16px; border-top:1px dashed rgba(28,42,56,0.10);">
          <button
              type="button"
              class="up-btn-outline js-cancel-booking"
              style="border-color:#f3b9b9; color:#a1332e; padding:12px 22px;"
              data-booking-id="<?php echo (int) $booking['id']; ?>"
              data-amount-paid="<?php echo number_format($paid, 2, '.', ''); ?>"
          >
              CANCEL BOOKING
          </button>
        </div>
      <?php endif; ?>
    </section>

    <!-- ============ REVIEW (post-cancel / post-stay) ============ -->
    <?php if ($canReview): ?>
      <section class="up-card up-reveal" style="--i: 2;">
        <div class="up-card-header">
          <h3><?php echo $hasReviewed ? 'Your Review' : 'Rate Your Stay'; ?></h3>
        </div>
        <?php if ($hasReviewed): ?>
          <p style="margin:0; font-size:13.5px; color:var(--up-text-muted, #5d6875);">
            &#9733; You rated this stay <strong><?php echo h(number_format((float) $existingReview['rating'], 0)); ?>/5</strong>.
            Thank you — you can update it by leaving a new review.
          </p>
          <div class="bd-actions" style="margin-top:14px;">
            <button type="button" class="up-btn-outline js-open-review" style="text-decoration:none; padding:11px 20px;">
              UPDATE REVIEW
            </button>
          </div>
        <?php else: ?>
          <p style="margin:0 0 14px; font-size:13.5px; color:var(--up-text-muted, #5d6875);">
            How was "<?php echo h($booking['title']); ?>"? Your rating helps other renters.
          </p>
          <button type="button" class="up-btn-solid js-open-review" style="padding:12px 22px;">
            &#9733; LEAVE A REVIEW
          </button>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </div>
</main>

<!-- =========================================================
     FLOATING CANCEL WARNING CARD — 2% fee breakdown
========================================================== -->
<div class="cbc-backdrop" id="cbcBackdrop" aria-hidden="true">
    <div class="cbc-card" role="dialog" aria-modal="true" aria-labelledby="cbcTitle">
        <button type="button" class="cbc-close" data-cbc-close aria-label="Close">&times;</button>

        <div class="cbc-icon">&#9888;&#65039;</div>

        <h3 class="cbc-title" id="cbcTitle">Cancel this booking?</h3>
        <p class="cbc-subtitle" id="cbcSubtitle">
            A 2% cancellation fee applies to paid bookings.
            The fee goes to the host as compensation.
        </p>

        <div class="cbc-breakdown" id="cbcBreakdown">
            <div class="cbc-row">
                <span>Amount you paid</span>
                <strong id="cbcPaid">&#8369;0.00</strong>
            </div>
            <div class="cbc-row cbc-row-fee">
                <span>Cancellation fee (2% &rarr; host)</span>
                <strong id="cbcFee">&minus; &#8369;0.00</strong>
            </div>
            <div class="cbc-row cbc-row-refund">
                <span>You'll get back</span>
                <strong id="cbcRefund">&#8369;0.00</strong>
            </div>
        </div>

        <p class="cbc-note" id="cbcNote">
            The refund is credited to your RoomHive wallet instantly.
            The dates are released to other renters right away.
        </p>

        <div class="cbc-error" id="cbcError"></div>

        <div class="cbc-actions">
            <button type="button" class="cbc-btn cbc-btn-confirm" id="cbcConfirm">
                Cancel Booking &amp; Accept Fee
            </button>
            <button type="button" class="cbc-btn cbc-btn-keep" data-cbc-close>
                Keep My Booking
            </button>
        </div>
    </div>
</div>

<!-- =========================================================
     FLOATING REVIEW MODAL
========================================================== -->
<div class="rvw-backdrop" id="rvwBackdrop" aria-hidden="true">
    <div class="rvw-card" role="dialog" aria-modal="true" aria-labelledby="rvwTitle">
        <button type="button" class="rvw-close" id="rvwClose" aria-label="Close">&times;</button>

        <h3 class="rvw-title" id="rvwTitle">Rate Your Stay</h3>
        <p class="rvw-sub"><?php echo h($booking['title']); ?></p>

        <div class="rvw-stars" id="rvwStars">
            <?php for ($s = 1; $s <= 5; $s++): ?>
                <button type="button" class="rvw-star" data-star="<?php echo $s; ?>" aria-label="<?php echo $s; ?> star<?php echo $s === 1 ? '' : 's'; ?>">&#9733;</button>
            <?php endfor; ?>
        </div>

        <div class="rvw-error" id="rvwError"></div>

        <textarea class="rvw-textarea" id="rvwComment" placeholder="Share a few details about your stay (optional)..." maxlength="600"></textarea>

        <button type="button" class="rvw-submit" id="rvwSubmit" disabled>
            Submit Review
        </button>
    </div>
</div>

<script src="/webprogg/assets/javaScript.js"></script>

<!-- Reveal -->
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

<!-- Cancel warning card + Review modal -->
<script>
(function () {
    "use strict";

    /* ================= CANCEL WARNING CARD ================= */
    var backdrop = document.getElementById('cbcBackdrop');

    if (backdrop) {
        var paidEl    = document.getElementById('cbcPaid');
        var feeEl     = document.getElementById('cbcFee');
        var refundEl  = document.getElementById('cbcRefund');
        var confirmEl = document.getElementById('cbcConfirm');
        var errorEl   = document.getElementById('cbcError');

        var FEE_RATE    = 0.02;
        var currentId   = null;
        var busy        = false;

        function peso(n) {
            return '\u20B1' + Number(n).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        function openModal(bookingId, amountPaid) {
            currentId = bookingId;
            var paid  = parseFloat(amountPaid) || 0;
            var fee    = Math.round(paid * FEE_RATE * 100) / 100;
            var refund = Math.round((paid - fee) * 100) / 100;

            paidEl.textContent   = peso(paid);
            feeEl.textContent    = '\u2212 ' + peso(fee);
            refundEl.textContent = peso(refund);

            var unpaid = paid <= 0.005;
            document.getElementById('cbcSubtitle').textContent = unpaid
                ? 'No payment was made on this booking, so nothing will be deducted.'
                : 'A 2% cancellation fee applies to paid bookings. The fee goes to the host as compensation.';
            document.getElementById('cbcBreakdown').style.display = unpaid ? 'none' : '';
            confirmEl.textContent = unpaid
                ? 'Cancel Booking'
                : 'Cancel Booking & Accept ' + peso(fee) + ' Fee';

            errorEl.classList.remove('show');
            confirmEl.disabled = false;

            backdrop.classList.add('open');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (busy) { return; }
            backdrop.classList.remove('open');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.js-cancel-booking').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(
                    btn.getAttribute('data-booking-id'),
                    btn.getAttribute('data-amount-paid')
                );
            });
        });

        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) { closeModal(); }
        });
        document.querySelectorAll('[data-cbc-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) { closeModal(); }
        });

        confirmEl.addEventListener('click', function () {
            if (busy || !currentId) { return; }

            busy = true;
            confirmEl.disabled = true;
            confirmEl.textContent = 'Cancelling\u2026';
            errorEl.classList.remove('show');

            fetch('/webprogg/booking/cancel-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'booking_id=' + encodeURIComponent(currentId),
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    busy = false;
                    window.location.href =
                        '/webprogg/booking/booking-details.php?id=' + currentId + '&cancelled=1';
                    return;
                }
                busy = false;
                confirmEl.disabled = false;
                errorEl.textContent = (data && data.message) || 'Could not cancel this booking. Please try again.';
                errorEl.classList.add('show');
                confirmEl.textContent = 'Cancel Booking & Accept Fee';
            })
            .catch(function () {
                busy = false;
                confirmEl.disabled = false;
                errorEl.textContent = "Couldn't reach the server. Please try again.";
                errorEl.classList.add('show');
                confirmEl.textContent = 'Cancel Booking & Accept Fee';
            });
        });
    }

    /* ================= REVIEW MODAL ================= */
    var rvwBackdrop = document.getElementById('rvwBackdrop');

    if (rvwBackdrop) {
        var stars     = rvwBackdrop.querySelectorAll('.rvw-star');
        var rvwSubmit = document.getElementById('rvwSubmit');
        var rvwError  = document.getElementById('rvwError');
        var rvwRating = 0;

        /* Pre-light stars if updating an existing review. */
        <?php if ($hasReviewed): ?>
        rvwRating = <?php echo (int) $existingReview['rating']; ?>;
        stars.forEach(function (st) {
            if (parseInt(st.getAttribute('data-star'), 10) <= rvwRating) {
                st.classList.add('lit');
            }
        });
        rvwSubmit.disabled = false;
        <?php endif; ?>

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                rvwRating = parseInt(star.getAttribute('data-star'), 10) || 0;
                stars.forEach(function (st) {
                    st.classList.toggle(
                        'lit',
                        (parseInt(st.getAttribute('data-star'), 10) || 0) <= rvwRating
                    );
                });
                rvwSubmit.disabled = rvwRating < 1;
            });
        });

        function openRvw() {
            rvwError.classList.remove('show');
            rvwBackdrop.classList.add('open');
            rvwBackdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function closeRvw() {
            rvwBackdrop.classList.remove('open');
            rvwBackdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.js-open-review').forEach(function (btn) {
            btn.addEventListener('click', openRvw);
        });
        document.getElementById('rvwClose').addEventListener('click', closeRvw);
        rvwBackdrop.addEventListener('click', function (e) {
            if (e.target === rvwBackdrop) { closeRvw(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && rvwBackdrop.classList.contains('open')) { closeRvw(); }
        });

        rvwSubmit.addEventListener('click', function () {
            if (rvwRating < 1) {
                rvwError.textContent = 'Please pick a star rating first.';
                rvwError.classList.add('show');
                return;
            }

            rvwSubmit.disabled = true;
            rvwSubmit.textContent = 'Saving\u2026';
            rvwError.classList.remove('show');

            var body = 'booking_id=' + encodeURIComponent(<?php echo (int) $booking['id']; ?>)
                     + '&rating=' + encodeURIComponent(rvwRating)
                     + '&comment=' + encodeURIComponent(document.getElementById('rvwComment').value);

            fetch('/webprogg/booking/submit-review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    window.location.href =
                        '/webprogg/booking/booking-details.php?id=' + <?php echo (int) $booking['id']; ?> + '&reviewed=1';
                    return;
                }
                rvwSubmit.disabled = false;
                rvwSubmit.textContent = 'Submit Review';
                rvwError.textContent = (data && data.message) || 'Could not save your review. Please try again.';
                rvwError.classList.add('show');
            })
            .catch(function () {
                rvwSubmit.disabled = false;
                rvwSubmit.textContent = 'Submit Review';
                rvwError.textContent = "Couldn't reach the server. Please try again.";
                rvwError.classList.add('show');
            });
        });
    }
})();
</script>

</body>
</html>