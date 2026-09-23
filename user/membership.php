<?php
/* =========================================================
   ROOMHIVE - MEMBERSHIP SELECTION / ACCOUNT HUB

   Phase 6: for an active member this page leads with the
   membership status card (tier, expiry countdown, progress
   to next tier, purchase history) and positions the other
   plans as RENEW / UPGRADE. Guests and non-members still
   get the plain plan picker.

   FIXED: h() was previously defined as a closure variable
   ($h) but called as a plain function throughout the markup
   -> fatal "Call to undefined function h()" at the hero
   medal <img>, which truncated the page after the styled
   card background. Now a proper guarded function.

   Requires: Phase 1 schema (membership_expires_at on
   hive_members, expires_at on hiveclub_transactions).
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

if (!$isLoggedIn) {
    header("Location: /webprogg/auth/loginform.php?redirect=" .
        urlencode("/webprogg/user/membership.php"));
    exit;
}

 $userId    = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;
 $currentYear = date("Y");

/* ---------------------------------------------
   HIVE CLUB ENGINE — sweep, member state
--------------------------------------------- */
hive_expiry_sweep($pdo);
 $member = hive_member($pdo, $userId);

 $isActiveMember  = $member && $member['membership_status'] === 'active' && hive_active($member);
 $isLapsedMember  = $member && $member['membership_status'] !== 'active' && (int) $member['lifetime_points'] > 0;
 $memberTier      = $member ? (string) $member['tier'] : null;
 $lifetimePoints  = $member ? (int) $member['lifetime_points'] : 0;
 $redeemablePts   = $member ? (int) $member['redeemable_points'] : 0;
 $daysLeft        = $member ? hive_days_until_expiry($member) : null;
 $nextTier        = hive_next_tier($lifetimePoints);
 $progress        = 0;

if ($nextTier !== null && $nextTier['min'] > 0) {
    $prevMin = 0;
    foreach (hive_config()['tiers'] as $t) {
        if ($t['min'] < $nextTier['min'] && $t['min'] > $prevMin) { $prevMin = $t['min']; }
    }
    $span = $nextTier['min'] - $prevMin;
    if ($span > 0) {
        $progress = max(0, min(100, (int) round((($lifetimePoints - $prevMin) / $span) * 100)));
    }
} elseif ($lifetimePoints >= 25000) {
    $progress = 100;
}

/* Expiry pill state */
 $expiryState = 'none';
if ($isActiveMember && $daysLeft !== null) {
    if ($daysLeft <= 7)      { $expiryState = 'urgent'; }
    elseif ($daysLeft <= 30) { $expiryState = 'soon'; }
    else                     { $expiryState = 'ok'; }
}

/* ---------------------------------------------
   PURCHASE HISTORY
--------------------------------------------- */
 $purchaseHistory = [];
if ($userId) {
    $phStmt = $pdo->prepare(
        "SELECT plan, amount, payment_method, payment_status, purchased_at, expires_at
         FROM hiveclub_transactions
         WHERE user_id = ?
         ORDER BY purchased_at DESC
         LIMIT 10"
    );
    $phStmt->execute([$userId]);
    $purchaseHistory = $phStmt->fetchAll();
}

/* ---------------------------------------------
   NAVIGATION
--------------------------------------------- */
 $navigation = [
    "HOME"          => "/webprogg/user/usershome.php",
    "LISTINGS"      => "/webprogg/Listings/listing.php",
    "HOW IT WORKS"  => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB"     => "/webprogg/hiveclub.php",
    "CONTACTS"      => "/webprogg/misc/contacts.php",
];

/* ---------------------------------------------
   ESCAPER — FIXED: a real guarded function (was a
   closure assigned to $h, which the markup never used).
--------------------------------------------- */
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

 $statusIcon = "/webprogg/images/BronzeIcon-HiveClub.png";
if ($memberTier === "Gold") {
    $statusIcon = "/webprogg/images/GoldIcon-HiveClub.png";
} elseif ($memberTier === "Platinum") {
    $statusIcon = "/webprogg/images/PlatinumIcon-HiveClub.png";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoomHive - Hive Club Membership</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/webprogg/assets/hiveclub-section.css">

    <style>
        /* ---- Membership status hero (ml-) ---- */
        .ml-wrap { max-width: 1180px; margin: 100px auto 0; padding: 0 5% 90px; }

        .ml-hero {
            position: relative;
            overflow: hidden;

            padding: 40px 44px;
            margin-bottom: 44px;

            background:
                radial-gradient(700px 300px at 90% -20%, rgba(237, 164, 35, 0.18), transparent 60%),
                linear-gradient(120deg, #1c2a38 0%, #24384d 60%, #1c2a38 100%);
            border-radius: 24px;
            color: #ffffff;

            box-shadow: 0 24px 50px rgba(28, 42, 56, 0.28);
        }

        .ml-hero-top {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .ml-medal {
            width: 76px; height: 76px;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #f6b93b, #eda423);
            border-radius: 50%;
            box-shadow: 0 10px 22px rgba(237, 164, 35, 0.45);
        }
        .ml-medal img { width: 42px; height: 42px; object-fit: contain; }

        .ml-hero-text { flex: 1; min-width: 220px; }

        .ml-hero-text h1 {
            margin: 0 0 4px;
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .ml-hero-text p {
            margin: 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13.5px;
        }

        .ml-badges { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }

        .ml-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 15px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .ml-pill-ok      { background: rgba(47, 168, 79, 0.18);  color: #7fe0a5; border: 1px solid rgba(47, 168, 79, 0.45); }
        .ml-pill-soon    { background: rgba(237, 164, 35, 0.18); color: #f6c04e; border: 1px solid rgba(237, 164, 35, 0.5); }
        .ml-pill-urgent  { background: rgba(225, 75, 75, 0.18);  color: #ff9d9d; border: 1px solid rgba(225, 75, 75, 0.5); }
        .ml-pill-lapsed  { background: rgba(255, 255, 255, 0.12); color: #cfd8e3; border: 1px solid rgba(255, 255, 255, 0.25); }

        .ml-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 26px;
        }

        @media (max-width: 700px) {
            .ml-stats { grid-template-columns: 1fr; }
        }

        .ml-stat {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 16px 18px;
        }
        .ml-stat span {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .ml-stat strong {
            display: block;
            margin-top: 5px;
            font-size: 22px;
            font-weight: 800;
        }
        .ml-stat small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.55);
            font-size: 11.5px;
        }

        .ml-progress {
            height: 10px;
            margin-top: 24px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 999px;
            overflow: hidden;
        }
        .ml-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #f6b93b, #eda423);
            transition: width 1.2s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .ml-progress-caption {
            margin: 8px 0 0;
            color: rgba(255, 255, 255, 0.65);
            font-size: 12px;
        }

        /* ---- Plans grid ---- */
        .ml-section-title {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .ml-section-title h2 {
            margin: 0;
            color: #1c2a38;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.4px;
        }
        .ml-section-title p { margin: 0; color: #6b7684; font-size: 13px; }

        .membership-plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .ml-plan-tag {
            display: inline-block;
            margin-bottom: 10px;
            padding: 4px 12px;
            border-radius: 999px;
            background: #FDF1DC;
            border: 1px solid rgba(237, 164, 35, 0.5);
            color: #b07708;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        /* ---- Purchase history ---- */
        .ml-history {
            margin-top: 44px;
            background: #ffffff;
            border: 1px solid rgba(28, 42, 56, 0.08);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 6px 18px rgba(28, 42, 56, 0.05);
        }
        .ml-history h3 {
            margin: 0 0 16px;
            color: #1c2a38;
            font-size: 16px;
            font-weight: 800;
        }
        .ml-hist-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 0;
            border-bottom: 1px solid #F1F3F8;
            font-size: 13px;
        }
        .ml-hist-row:last-child { border-bottom: none; }
        .ml-hist-plan { font-weight: 800; color: #1c2a38; text-transform: capitalize; }
        .ml-hist-meta { color: #8B93A6; font-size: 12px; }
        .ml-hist-amount { font-weight: 800; color: #C77800; white-space: nowrap; }
        .ml-hist-status {
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 999px;
            background: #E8F8F1;
            color: #1FA971;
        }
        .ml-hist-status.failed, .ml-hist-status.cancelled {
            background: #FCEAEA;
            color: #E14B4B;
        }

        @media (max-width: 700px) {
            .ml-wrap { margin-top: 90px; padding: 0 18px 70px; }
            .ml-hero { padding: 28px 22px; }
            .ml-badges { align-items: flex-start; }
        }
    </style>
</head>

<body>

<div class="ml-wrap">

    <a href="/webprogg/hiveclub.php" class="back-to-hive" style="display:inline-flex; margin-bottom:18px; text-decoration:none;">
        &larr; Back to Hive Club
    </a>

    <?php if ($member && ($isActiveMember || $isLapsedMember)): ?>
    <!-- =====================================================
         MEMBERSHIP STATUS HERO
    ====================================================== -->
    <div class="ml-hero">

        <div class="ml-hero-top">

            <div class="ml-medal">
                <img src="<?php echo h($statusIcon); ?>" alt="<?php echo h($memberTier); ?>">
            </div>

            <div class="ml-hero-text">
                <h1>
                    <?php echo h($memberTier); ?> Member
                </h1>
                <p>
                    <?php echo $isActiveMember
                        ? 'Your perks are active — discounts apply automatically at checkout.'
                        : 'Your plan has lapsed — your points are safe, renew to reactivate discounts.'; ?>
                </p>
            </div>

            <div class="ml-badges">
                <?php if ($isActiveMember && $daysLeft !== null): ?>
                    <span class="ml-pill ml-pill-<?php echo h($expiryState); ?>">
                        &#128337; <?php echo $daysLeft; ?> day<?php echo $daysLeft === 1 ? '' : 's'; ?> left
                    </span>
                    <span class="ml-pill ml-pill-ok">
                        Renews <?php echo h(date('M j, Y', strtotime($member['membership_expires_at']))); ?>
                    </span>
                <?php elseif ($isActiveMember): ?>
                    <span class="ml-pill ml-pill-ok">&#10003; Active &mdash; never expires (Bronze)</span>
                <?php else: ?>
                    <span class="ml-pill ml-pill-lapsed">Plan lapsed &mdash; renew below</span>
                <?php endif; ?>
            </div>

        </div>

        <div class="ml-stats">

            <div class="ml-stat">
                <span>Lifetime Points</span>
                <strong><?php echo number_format($lifetimePoints); ?></strong>
                <small>drives your tier &mdash; never spent</small>
            </div>

            <div class="ml-stat">
                <span>Spendable Points</span>
                <strong><?php echo number_format($redeemablePts); ?></strong>
                <small>worth about &#8369;<?php echo number_format($redeemablePts * 0.5, 0); ?> in wallet credit</small>
            </div>

            <div class="ml-stat">
                <span>Booking Discount</span>
                <strong><?php echo $isActiveMember ? hive_tier_discount($memberTier) : 0; ?>% off</strong>
                <small>applied automatically at checkout</small>
            </div>

        </div>

        <?php if ($nextTier !== null): ?>
        <div class="ml-progress">
            <div class="ml-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
        </div>
        <p class="ml-progress-caption">
            <?php echo number_format($nextTier['needed']); ?> more lifetime points to reach
            <strong><?php echo h($nextTier['name']); ?></strong> (<?php echo hive_tier_discount($nextTier['name']); ?>% off stays)
        </p>
        <?php else: ?>
        <div class="ml-progress">
            <div class="ml-progress-fill" style="width: 100%;"></div>
        </div>
        <p class="ml-progress-caption">
            &#128081; You're at the top tier — Platinum. Enjoy 15% off every stay.
        </p>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- =====================================================
         PLANS
    ====================================================== -->
    <div class="ml-section-title">
        <h2>
            <?php echo $isActiveMember
                ? 'Renew or Upgrade'
                : 'Choose Your Membership'; ?>
        </h2>
        <p>
            <?php echo $isActiveMember
                ? 'Renewing adds a full year on top of your remaining days.'
                : "Select the plan that's right for you."; ?>
        </p>
    </div>

    <div class="membership-plans">

        <!-- ============ BRONZE ============ -->
        <div class="membership-plan">

            <div class="plan-icon">
                <img src="/webprogg/images/BronzeIcon-HiveClub.png" alt="Bronze">
            </div>

            <h3>Bronze</h3>

            <span class="plan-points">0 - 4,999 pts</span>

            <ul class="plan-features">
                <li><span class="check">&#10003;</span> 5% off on stays</li>
                <li><span class="check">&#10003;</span> Earn points on every completed stay</li>
            </ul>

            <div class="plan-price">FREE</div>

            <a href="/webprogg/hiveclub.php" class="plan-select-button">
                <?php echo $member ? 'YOUR BASE TIER' : 'INCLUDED FREE'; ?>
            </a>

        </div>

        <!-- ============ GOLD ============ -->
        <div class="membership-plan popular">

            <span class="popular-badge">MOST POPULAR</span>

            <div class="plan-icon">
                <img src="/webprogg/images/GoldIcon-HiveClub.png" alt="Gold">
            </div>

            <?php if ($isActiveMember && $memberTier === 'Gold'): ?>
                <span class="ml-plan-tag">&#10003; YOUR CURRENT PLAN</span>
            <?php endif; ?>

            <h3 class="gold-text">Gold</h3>

            <span class="plan-points">5,000 - 24,999 pts</span>

            <ul class="plan-features">
                <li><span class="check">&#10003;</span> 10% off on stays</li>
                <li><span class="check">&#10003;</span> Priority customer support</li>
                <li><span class="check">&#10003;</span> +5,000 bonus points on purchase</li>
            </ul>

            <div class="plan-price">
                <span class="peso">&#8369;</span> 399
                <span class="per">/ year</span>
            </div>

            <a
                href="/webprogg/misc/completepurchase.php?plan=gold"
                class="plan-select-button primary"
            >
                <?php
                    if ($isActiveMember && $memberTier === 'Gold') {
                        echo 'RENEW — ADD 1 YEAR';
                    } elseif ($isActiveMember && $memberTier === 'Platinum') {
                        echo 'NOT AVAILABLE'; /* never downgrade visually; link inert below */
                    } else {
                        echo 'GET STARTED';
                    }
                ?>
            </a>

        </div>

        <!-- ============ PLATINUM ============ -->
        <div class="membership-plan">

            <div class="plan-icon">
                <img src="/webprogg/images/PlatinumIcon-HiveClub.png" alt="Platinum">
            </div>

            <?php if ($isActiveMember && $memberTier === 'Platinum'): ?>
                <span class="ml-plan-tag">&#128081; YOUR CURRENT PLAN</span>
            <?php endif; ?>

            <h3>Platinum</h3>

            <span class="plan-points">25,000+ pts</span>

            <ul class="plan-features">
                <li><span class="check">&#10003;</span> 15% off on stays</li>
                <li><span class="check">&#10003;</span> Free upgrades (subject to availability)</li>
                <li><span class="check">&#10003;</span> +25,000 bonus points on purchase</li>
            </ul>

            <div class="plan-price">
                <span class="peso">&#8369;</span> 799
                <span class="per">/ year</span>
            </div>

            <a
                href="/webprogg/misc/completepurchase.php?plan=platinum"
                class="plan-select-button<?php echo ($isActiveMember && $memberTier === 'Platinum') ? '' : ' primary'; ?>"
            >
                <?php
                    if ($isActiveMember && $memberTier === 'Platinum') {
                        echo 'RENEW — ADD 1 YEAR';
                    } else {
                        echo 'GET STARTED';
                    }
                ?>
            </a>

        </div>

    </div>

    <!-- =====================================================
         PURCHASE HISTORY
    ====================================================== -->
    <?php if (!empty($purchaseHistory)): ?>

        <div class="ml-history">

            <h3>Purchase History</h3>

            <?php foreach ($purchaseHistory as $txn): ?>

                <div class="ml-hist-row">

                    <div>
                        <span class="ml-hist-plan"><?php echo h($txn['plan'] ?: 'membership'); ?></span><br>
                        <span class="ml-hist-meta">
                            <?php echo h(date('M j, Y', strtotime($txn['purchased_at']))); ?>
                            <?php if (!empty($txn['expires_at'])): ?>
                                &middot; valid until <?php echo h(date('M j, Y', strtotime($txn['expires_at']))); ?>
                            <?php endif; ?>
                            &middot; <?php echo h(strtoupper((string) $txn['payment_method'])); ?>
                        </span>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px;">
                        <span class="ml-hist-status <?php echo h((string) $txn['payment_status']); ?>">
                            <?php echo h(ucfirst((string) $txn['payment_status'])); ?>
                        </span>
                        <span class="ml-hist-amount">&#8369; <?php echo h(number_format((float) $txn['amount'], 2)); ?></span>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <!-- Footer note -->
    <div class="membership-modal-footer" style="margin-top:44px;">
        <span class="footer-crown">&#9819;</span>
        <div>
            <strong>The more you book, the more you earn.</strong>
            <p>You can upgrade, renew, or cancel anytime. Points never expire.</p>
        </div>
    </div>

</div>

<script>
/* Animate the progress bar after paint */
window.addEventListener('load', function () {
    var fills = document.querySelectorAll('.ml-progress-fill');
    fills.forEach(function (fill) {
        var target = fill.style.width;
        fill.style.width = '0%';
        window.setTimeout(function () { fill.style.width = target; }, 120);
    });
});
</script>

</body>
</html>