<?php

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hive_reminder.php';


// =====================================================
// ROOMHIVE - HIVE CLUB (Phases 2-8 integrated)
// =====================================================


 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
 );

 $autoOpenLoginPopup = !$isLoggedIn && empty($_SESSION['admin_logged_in']);


// =====================================================
// KEEP is_host IN SYNC WITH THE DATABASE
// =====================================================

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
    $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
    $hostRow = $hostCheckStmt->fetch();
    $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
    $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
}

 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $currentPage = "/webprogg/hiveclub.php";
 $isHost = $_SESSION['is_host'] ?? false;

/* REAL unread bell count (was hardcoded 0) */
 $notification_count = 0;
if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $ncStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
    );
    $ncStmt->execute(['u' => $_SESSION['user_id']]);
    $notification_count = (int) $ncStmt->fetchColumn();
}

 $currentYear = date("Y");

 $navigation = [
    "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => $isLoggedIn
        ? "/webprogg/host/becomeahost.php"
        : "/webprogg/auth/loginform.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];


// =====================================================
// MEMBER STATE — via the engine (sweep + dual buckets)
// =====================================================

 $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;

 hive_expiry_sweep($pdo);
 $hiveMember = hive_member($pdo, $userId ?: 0);

 $memberId         = $hiveMember ? $hiveMember['member_id'] : "RH " . date("Y") . " 0000";
 $memberTier       = $hiveMember ? $hiveMember['tier'] . " Member" : "Bronze Member";
 $memberPoints     = $hiveMember ? (int) $hiveMember['lifetime_points'] : 0;
 $memberRedeemable = $hiveMember ? (int) $hiveMember['redeemable_points'] : 0;
 $memberStatus     = $hiveMember ? $hiveMember['membership_status'] : "none";
 $isHiveMember     = ($memberStatus === "active");

/* Tier progress on LIFETIME points (engine-driven) */
 $hiveNext = hive_next_tier($memberPoints);
 $nextTier        = $hiveNext ? $hiveNext['name'] : null;
 $nextTierPoints  = $hiveNext ? $hiveNext['min'] : 25000;
 $progress        = 0;

if ($hiveNext !== null) {
    $prevMin = 0;
    foreach (hive_config()['tiers'] as $t) {
        if ($t['min'] < $hiveNext['min'] && $t['min'] > $prevMin) { $prevMin = $t['min']; }
    }
    $span = $hiveNext['min'] - $prevMin;
    if ($span > 0) {
        $progress = max(0, min(100, (($memberPoints - $prevMin) / $span) * 100));
    }
} elseif ($memberPoints >= 25000) {
    $nextTier = "Platinum";
    $nextTierPoints = 25000;
    $progress = 100;
}

 $joinHiveClubLink = $isLoggedIn
    ? "/webprogg/user/membership.php"
    : "/webprogg/auth/loginform.php?redirect=" .
      urlencode("/webprogg/hiveclub.php");

 $statusIcon = "/webprogg/images/BronzeIcon-HiveClub.png";
if ($memberTier === "Gold Member") {
    $statusIcon = "/webprogg/images/GoldIcon-HiveClub.png";
} elseif ($memberTier === "Platinum Member") {
    $statusIcon = "/webprogg/images/PlatinumIcon-HiveClub.png";
}

/* =====================================================
   PHASE 5 — REWARDS DATA (View Rewards needs these)
===================================================== */

 $hivePesoPerPoint = hive_config()['peso_per_point'];

 $hiveRewards = $pdo->query(
    "SELECT id, name, description, cost_points, reward_value
     FROM hive_rewards
     WHERE is_active = 1
     ORDER BY sort_order ASC, cost_points ASC"
)->fetchAll();

 $alreadyRedeemedIds = [];
 $hiveRedemptions    = [];

if ($userId) {
    $arStmt = $pdo->prepare("SELECT reward_id FROM hive_redemptions WHERE user_id = :u");
    $arStmt->execute(['u' => $userId]);
    $alreadyRedeemedIds = array_map('intval', array_column($arStmt->fetchAll(), 'reward_id'));

    $hrStmt = $pdo->prepare(
        "SELECT hr.points_spent, hr.wallet_credited, hr.created_at,
                r.name AS reward_name
         FROM hive_redemptions hr
         JOIN hive_rewards r ON r.id = hr.reward_id
         WHERE hr.user_id = :u
         ORDER BY hr.created_at DESC
         LIMIT 5"
    );
    $hrStmt->execute(['u' => $userId]);
    $hiveRedemptions = $hrStmt->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RoomHive - Hive Club</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >

    <script>document.documentElement.classList.add("js");</script>

    <!-- =====================================================
         HIVE CLUB REDESIGN + REWARDS + LOGIN MODAL STYLES
         (your original hv-/lx- styles are byte-identical here;
          new .hv-rw-* and .rwc-* blocks appended at the end)
    ====================================================== -->
    <style>

/* =========================================================
   TOKENS
========================================================= */

.hive-club-page {
  --hv-honey: #eda423;
  --hv-honey-light: #f6c04e;
  --hv-dark: #1c2a38;
  --hv-muted: #5d6875;
  --hv-muted-2: #6b7684;
  --hv-line: rgba(28, 42, 56, 0.08);
  --hv-glow: 0 18px 34px rgba(237, 164, 35, 0.16);
}

.hv-notice {
  max-width: 1180px;
  margin: 90px auto 0;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #eaf7ee;
  border: 1px solid #b9e3c5;
  border-radius: 12px;
  color: #206a3c;
  font-size: 14px;
  font-weight: 500;
}

.hv-hero {
  position: relative;
  margin-top: 70px;
  overflow: hidden;
  background:
    radial-gradient(900px 420px at 85% -10%, rgba(237, 164, 35, 0.18), transparent 60%),
    radial-gradient(700px 380px at -10% 110%, rgba(237, 164, 35, 0.12), transparent 60%),
    linear-gradient(180deg, #ffffff 0%, #fff8ec 100%);
}

.hv-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.08' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
  background-size: 28px 49px;
  -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 80%);
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 80%);
  pointer-events: none;
}

.hv-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(70px);
  pointer-events: none;
}

.hv-blob-1 {
  width: 420px;
  height: 420px;
  top: -140px;
  right: -120px;
  background: radial-gradient(circle at 30% 30%, rgba(246, 196, 78, 0.85), rgba(237, 164, 35, 0.25) 60%, transparent 75%);
  animation: hvDrift 14s ease-in-out infinite alternate;
}

.hv-blob-2 {
  width: 300px;
  height: 300px;
  bottom: -120px;
  left: -100px;
  background: radial-gradient(circle at 60% 40%, rgba(246, 196, 78, 0.7), rgba(237, 164, 35, 0.2) 60%, transparent 75%);
  animation: hvDrift 18s ease-in-out infinite alternate-reverse;
}

.hv-hero-inner {
  position: relative;
  z-index: 1;
  max-width: 1180px;
  margin: 0 auto;
  padding: 70px 5% 150px;
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  align-items: center;
  gap: 40px;
}

.hv-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #ffffff;
  border: 1px solid rgba(237, 164, 35, 0.35);
  border-radius: 999px;
  box-shadow: 0 4px 14px rgba(237, 164, 35, 0.12);
  color: #b07708;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

.hv-pulse-dot {
  position: relative;
  width: 8px;
  height: 8px;
  background: var(--hv-honey);
  border-radius: 50%;
}

.hv-pulse-dot::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--hv-honey);
  border-radius: 50%;
  animation: hvPing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.hv-hero-text h1 {
  margin: 18px 0 16px;
  color: var(--hv-dark);
  font-size: clamp(38px, 5vw, 58px);
  font-weight: 800;
  letter-spacing: -1px;
  line-height: 1.08;
}

.hv-shimmer {
  background: linear-gradient(92deg, #eda423 0%, #f6c04e 45%, #eda423 90%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent;
  animation: hvShimmer 3.5s linear infinite;
}

.hv-hero-text > p {
  margin: 0;
  max-width: 460px;
  color: var(--hv-muted);
  font-size: 15.5px;
  line-height: 1.75;
}

.hv-hero-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 28px;
}

.hv-hero-art {
  position: relative;
  display: flex;
  justify-content: center;
}

.hv-art-glow {
  position: absolute;
  width: 72%;
  height: 72%;
  top: 14%;
  background: radial-gradient(circle, rgba(237, 164, 35, 0.32), transparent 65%);
  filter: blur(30px);
  animation: hvGlowPulse 4.5s ease-in-out infinite;
}

.hv-art-img {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 460px;
  height: auto;
  filter: drop-shadow(0 30px 40px rgba(28, 42, 56, 0.22));
  animation: hvFloat 5.5s ease-in-out infinite;
}

.hv-chip {
  position: absolute;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(237, 164, 35, 0.18);
  border-radius: 12px;
  box-shadow: 0 10px 24px rgba(28, 42, 56, 0.14);
  color: var(--hv-dark);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  animation: hvFloat 6s ease-in-out infinite;
}

.hv-chip img {
  width: 20px;
  height: 20px;
  object-fit: contain;
}

.hv-chip-1 { top: 8%; left: -2%; animation-delay: 0.4s; }
.hv-chip-2 { top: 40%; right: -4%; animation-delay: 1.2s; }
.hv-chip-3 { bottom: 8%; left: 4%; animation-delay: 2s; }

.hv-hero-wave {
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  width: 100%;
  height: 70px;
  display: block;
}

.js .hv-anim {
  opacity: 0;
  animation: hvHeroUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) forwards;
}

.hv-btn {
  position: relative;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  padding: 0 26px;
  border: none;
  border-radius: 10px;
  font-family: "Poppins", sans-serif;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-decoration: none;
  cursor: pointer;
  transition:
    transform 0.25s ease,
    box-shadow 0.25s ease,
    background 0.25s ease,
    color 0.25s ease,
    border-color 0.25s ease;
}

.hv-btn:hover { transform: translateY(-2px); }
.hv-btn:active { transform: translateY(0) scale(0.98); }

.hv-btn-honey {
  background: linear-gradient(135deg, #f6b93b, #eda423);
  color: var(--hv-dark);
  box-shadow: 0 8px 20px rgba(237, 164, 35, 0.35);
}

.hv-btn-honey:hover { box-shadow: 0 12px 26px rgba(237, 164, 35, 0.45); }

.hv-btn-outline {
  background: #ffffff;
  color: var(--hv-dark);
  border: 1.5px solid #dfe4ea;
}

.hv-btn-outline:hover {
  border-color: var(--hv-honey);
  color: #b07708;
  box-shadow: 0 8px 20px rgba(28, 42, 56, 0.08);
}

.hv-btn-arrow {
  display: inline-block;
  transition: transform 0.25s ease;
}

.hv-btn:hover .hv-btn-arrow { transform: translateX(4px); }

.hv-btn-shine::after {
  content: "";
  position: absolute;
  top: 0;
  left: -80%;
  width: 50%;
  height: 100%;
  background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.55), transparent);
  transform: skewX(-20deg);
  animation: hvSweep 3.6s ease-in-out infinite;
}

.hv-main {
  max-width: 1180px;
  margin: 0 auto;
  padding: 70px 5% 20px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 40px;
  align-items: start;
}

.hv-section-head {
  text-align: center;
  margin-bottom: 34px;
}

.hv-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 16px;
  background: rgba(237, 164, 35, 0.12);
  border-radius: 999px;
  color: #b07708;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1.2px;
  text-transform: uppercase;
}

.hv-section-head h2 {
  margin: 16px 0 10px;
  color: var(--hv-dark);
  font-size: clamp(26px, 3.2vw, 36px);
  font-weight: 800;
  letter-spacing: -0.5px;
}

.hv-section-head p {
  margin: 0;
  color: var(--hv-muted);
  font-size: 14.5px;
}

.hv-benefits {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}

.hv-benefit {
  padding: 26px 18px;
  background: #ffffff;
  border: 1px solid var(--hv-line);
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(28, 42, 56, 0.05);
  text-align: center;
  transition:
    transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 0.3s ease,
    border-color 0.3s ease;
}

.hv-benefit:hover {
  transform: translateY(-6px);
  border-color: rgba(237, 164, 35, 0.4);
  box-shadow: var(--hv-glow);
}

.hv-benefit-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(237, 164, 35, 0.16), rgba(237, 164, 35, 0.06));
  border-radius: 50%;
  transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

.hv-benefit:hover .hv-benefit-icon {
  transform: rotate(-8deg) scale(1.08);
}

.hv-benefit-icon img {
  width: 30px;
  height: 30px;
  object-fit: contain;
}

.hv-benefit h3 {
  margin: 0 0 8px;
  color: var(--hv-dark);
  font-size: 14.5px;
  font-weight: 700;
}

.hv-benefit p {
  margin: 0 auto;
  max-width: 180px;
  color: var(--hv-muted-2);
  font-size: 12.5px;
  line-height: 1.55;
}

.hv-tier-head {
  margin: 46px 0 8px;
  text-align: center;
}

.hv-tier-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}

.hv-tier {
  position: relative;
  padding: 24px 20px;
  background: #ffffff;
  border: 1.5px solid #ead5b7;
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(28, 42, 56, 0.05);
  transition:
    transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 0.3s ease;
}

.hv-tier:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 30px rgba(237, 164, 35, 0.14);
}

.hv-tier.gold { border-color: #f1c24f; }
.hv-tier.platinum { border-color: #cbd6dd; }

.hv-tier.current {
  border-color: var(--hv-honey);
  box-shadow:
    0 0 0 4px rgba(237, 164, 35, 0.18),
    var(--hv-glow);
}

.hv-current-badge {
  position: absolute;
  top: -12px;
  right: 14px;
  padding: 5px 12px;
  background: linear-gradient(135deg, #f6b93b, #eda423);
  border-radius: 999px;
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.6px;
  box-shadow: 0 6px 14px rgba(237, 164, 35, 0.4);
  animation: hvFloat 3.5s ease-in-out infinite;
}

.hv-tier-top {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 14px;
}

.hv-tier-icon {
  width: 56px;
  height: 56px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f6ead7;
  border-radius: 14px;
}

.hv-tier.gold .hv-tier-icon { background: #fff0c9; }
.hv-tier.platinum .hv-tier-icon { background: #e8f0f5; }

.hv-tier-icon img {
  width: 34px;
  height: 34px;
  object-fit: contain;
}

.hv-tier-top h3 {
  margin: 0 0 3px;
  color: var(--hv-dark);
  font-size: 17px;
  font-weight: 800;
}

.hv-tier-top strong {
  color: var(--hv-muted-2);
  font-size: 12px;
  font-weight: 600;
}

.hv-tier ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.hv-tier li {
  position: relative;
  margin-bottom: 6px;
  padding-left: 20px;
  color: var(--hv-muted-2);
  font-size: 12.5px;
  line-height: 1.5;
}

.hv-tier li::before {
  content: "✓";
  position: absolute;
  left: 0;
  color: var(--hv-honey);
  font-weight: 700;
}

.hv-sidebar {
  position: sticky;
  top: 90px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.hv-status-card,
.hv-earn-card {
  padding: 26px 24px;
  background: #ffffff;
  border: 1px solid var(--hv-line);
  border-radius: 18px;
  box-shadow: 0 10px 26px rgba(28, 42, 56, 0.07);
}

.hv-status-card h2,
.hv-earn-card h2 {
  margin: 0 0 18px;
  color: var(--hv-dark);
  font-size: 16px;
  font-weight: 800;
}

.hv-status-profile {
  display: flex;
  align-items: center;
  gap: 14px;
}

.hv-status-medal {
  width: 58px;
  height: 58px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f6b93b, #eda423);
  border-radius: 50%;
  box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
  animation: hvFloat 4.5s ease-in-out infinite;
}

.hv-status-medal img {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.hv-status-profile h3 {
  margin: 0;
  color: #b07708;
  font-size: 16px;
  font-weight: 800;
}

.hv-status-profile strong {
  display: block;
  margin-top: 3px;
  color: var(--hv-dark);
  font-size: 15px;
}

.hv-status-profile p {
  margin: 3px 0 0;
  color: var(--hv-muted-2);
  font-size: 11px;
}

.hv-progress {
  width: 100%;
  height: 10px;
  margin-top: 20px;
  background: #eee3d0;
  border-radius: 999px;
  overflow: hidden;
}

.hv-progress-fill {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, #f6b93b, #eda423);
  border-radius: 999px;
  transition: width 1.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.hv-progress-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
  color: var(--hv-muted-2);
  font-size: 11px;
  font-weight: 500;
}

.hv-rewards-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 44px;
  margin-top: 20px;
  background: linear-gradient(135deg, #f6b93b, #eda423);
  border-radius: 10px;
  color: var(--hv-dark);
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.6px;
  box-shadow: 0 8px 18px rgba(237, 164, 35, 0.3);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.hv-rewards-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(237, 164, 35, 0.42);
}

.hv-not-member {
  margin: 0 0 18px;
  color: var(--hv-muted);
  font-size: 13.5px;
  line-height: 1.65;
}

.hv-earn-item {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.hv-earn-item:last-child { margin-bottom: 0; }

.hv-earn-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(237, 164, 35, 0.16), rgba(237, 164, 35, 0.06));
  border-radius: 10px;
  color: var(--hv-honey);
  font-size: 16px;
}

.hv-earn-item strong {
  display: block;
  color: var(--hv-dark);
  font-size: 13px;
}

.hv-earn-item p {
  margin: 3px 0 0;
  color: var(--hv-muted-2);
  font-size: 11.5px;
}

.hv-cta-wrap {
  padding: 50px 5% 90px;
  background: #ffffff;
}

.hv-cta {
  position: relative;
  max-width: 1180px;
  margin: 0 auto;
  padding: 42px 46px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;
  background: linear-gradient(120deg, #1c2a38 0%, #24384d 55%, #1c2a38 100%);
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 24px 50px rgba(28, 42, 56, 0.28);
}

.hv-cta::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23f6c04e' fill-opacity='0.06' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
  background-size: 28px 49px;
  pointer-events: none;
}

.hv-cta::after {
  content: "";
  position: absolute;
  width: 340px;
  height: 340px;
  top: -160px;
  right: -100px;
  background: radial-gradient(circle, rgba(237, 164, 35, 0.35), transparent 65%);
  border-radius: 50%;
  filter: blur(20px);
  animation: hvGlowPulse 5s ease-in-out infinite;
  pointer-events: none;
}

.hv-cta-left {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 20px;
}

.hv-cta-crown {
  width: 62px;
  height: 62px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 8px 16px rgba(237, 164, 35, 0.4));
  animation: hvFloat 5s ease-in-out infinite;
}

.hv-cta-text h2 {
  margin: 0 0 6px;
  color: #ffffff;
  font-size: clamp(19px, 2.4vw, 26px);
  font-weight: 800;
}

.hv-cta-text p {
  margin: 0;
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
  line-height: 1.6;
}

.hv-cta-buttons {
  position: relative;
  z-index: 1;
}

.js .hv-reveal {
  opacity: 0;
  transform: translateY(26px);
  transition:
    opacity 0.65s ease calc(var(--i, 0) * 90ms),
    transform 0.65s cubic-bezier(0.22, 1, 0.36, 1) calc(var(--i, 0) * 90ms);
  will-change: opacity, transform;
}

.js .hv-reveal.in-view {
  opacity: 1;
  transform: translateY(0);
}

@keyframes hvHeroUp {
  from { opacity: 0; transform: translateY(24px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes hvFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-14px); }
}

@keyframes hvDrift {
  from { transform: translate(0, 0) scale(1); }
  to { transform: translate(30px, -24px) scale(1.08); }
}

@keyframes hvGlowPulse {
  0%, 100% { opacity: 0.7; transform: scale(1); }
  50% { opacity: 1; transform: scale(1.12); }
}

@keyframes hvPing {
  0% { transform: scale(1); opacity: 0.7; }
  80%, 100% { transform: scale(2.6); opacity: 0; }
}

@keyframes hvShimmer {
  to { background-position: 200% center; }
}

@keyframes hvSweep {
  0% { left: -80%; }
  45%, 100% { left: 130%; }
}

@media (max-width: 1023px) {
  .hv-main {
    grid-template-columns: 1fr;
    gap: 46px;
  }
  .hv-sidebar { position: static; }
  .hv-benefits { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px) {
  .hv-hero-inner {
    grid-template-columns: 1fr;
    padding: 55px 6% 120px;
    text-align: center;
  }
  .hv-hero-text > p { margin: 0 auto; }
  .hv-hero-actions { justify-content: center; }
  .hv-hero-art { max-width: 380px; margin: 10px auto 0; }
  .hv-chip-1 { left: 0; }
  .hv-chip-2 { right: 0; }
  .hv-tier-grid { grid-template-columns: 1fr; }
  .hv-cta { flex-direction: column; text-align: center; padding: 36px 28px; }
  .hv-cta-left { flex-direction: column; }
}

@media (max-width: 640px) {
  .hv-notice { margin: 90px 15px 0; }
  .hv-hero-text h1 { font-size: 36px; }
  .hv-hero-actions { flex-direction: column; width: 100%; }
  .hv-hero-actions .hv-btn { width: 100%; }
  .hv-hero-wave { height: 40px; }
  .hv-main { padding: 55px 18px 20px; }
  .hv-benefits { grid-template-columns: 1fr; }
  .hv-cta-buttons { width: 100%; }
  .hv-cta-buttons .hv-btn { width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
  .hv-anim,
  .hv-blob,
  .hv-art-img,
  .hv-art-glow,
  .hv-chip,
  .hv-shimmer,
  .hv-btn,
  .hv-status-medal,
  .hv-cta-crown,
  .hv-current-badge,
  .hv-pulse-dot::after,
  .hv-btn-shine::after,
  .hv-cta::after {
    animation: none !important;
    opacity: 1 !important;
    transform: none !important;
  }
  .hv-progress-fill { transition: none; }
  .js .hv-reveal {
    opacity: 1;
    transform: none;
    transition: none;
  }
}

/* =========================================================
   PHASE 5 — REWARDS STYLES (hv-rw-* + rwc-*)
========================================================= */

.hv-rewards { margin-top: 52px; }

.hv-rw-balance {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 22px 26px;
  margin-bottom: 24px;
  background: linear-gradient(120deg, #1c2a38 0%, #24384d 100%);
  border-radius: 18px;
  color: #ffffff;
}

.hv-rw-balance-icon {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f6b93b, #eda423);
  border-radius: 50%;
  font-size: 24px;
  box-shadow: 0 8px 18px rgba(237, 164, 35, 0.4);
}

.hv-rw-balance-num {
  font-size: 34px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.5px;
}

.hv-rw-balance-caption {
  margin: 4px 0 0;
  color: rgba(255, 255, 255, 0.65);
  font-size: 12.5px;
}

.hv-rw-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}

@media (max-width: 900px) {
  .hv-rw-grid { grid-template-columns: 1fr; }
}

.hv-rw-card {
  display: flex;
  flex-direction: column;
  padding: 24px 20px;
  background: #ffffff;
  border: 1.5px solid #ead5b7;
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(28, 42, 56, 0.05);
  transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.3s ease;
}

.hv-rw-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 30px rgba(237, 164, 35, 0.14);
}

.hv-rw-credit {
  font-size: 26px;
  font-weight: 800;
  color: #b07708;
  letter-spacing: -0.5px;
}

.hv-rw-card h4 {
  margin: 8px 0 6px;
  color: var(--hv-dark);
  font-size: 15px;
  font-weight: 800;
}

.hv-rw-card p {
  margin: 0 0 16px;
  color: var(--hv-muted-2);
  font-size: 12.5px;
  line-height: 1.55;
  flex: 1;
}

.hv-rw-cost {
  display: inline-block;
  margin-bottom: 14px;
  padding: 5px 12px;
  background: #FDF1DC;
  border-radius: 999px;
  color: #b07708;
  font-size: 12px;
  font-weight: 800;
}

.hv-rw-btn {
  width: 100%;
  height: 44px;
  border: none;
  border-radius: 10px;
  font-family: "Poppins", sans-serif;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.6px;
  cursor: pointer;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.hv-rw-btn-ready {
  background: linear-gradient(135deg, #f6b93b, #eda423);
  color: #1c2a38;
  box-shadow: 0 8px 18px rgba(237, 164, 35, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.hv-rw-btn-ready:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(237, 164, 35, 0.42);
}

.hv-rw-btn-locked {
  background: #F0F1F6;
  color: #8B93A6;
  cursor: not-allowed;
}

.hv-rw-btn-claimed {
  background: #E8F8F1;
  color: #1FA971;
  cursor: default;
}

.hv-rw-history { margin-top: 26px; }

.hv-rw-history h4 {
  margin: 0 0 12px;
  color: var(--hv-dark);
  font-size: 14px;
  font-weight: 800;
}

.hv-rw-history-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 14px;
  margin-bottom: 8px;
  background: #ffffff;
  border: 1px solid var(--hv-line);
  border-radius: 12px;
  font-size: 12.5px;
}

.hv-rw-history-item strong { color: var(--hv-dark); }
.hv-rw-history-item span { color: var(--hv-muted-2); }
.hv-rw-history-item .hv-rw-hist-credit {
  color: #1FA971;
  font-weight: 800;
  white-space: nowrap;
}

/* ---- Redeem confirm card (rwc-) ---- */
.rwc-backdrop {
  position: fixed; inset: 0; z-index: 1300;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  background: rgba(28, 42, 56, 0.45);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  opacity: 0; visibility: hidden; pointer-events: none;
  transition: opacity 0.28s ease, visibility 0.28s ease;
}
.rwc-backdrop.open { opacity: 1; visibility: visible; pointer-events: auto; }

.rwc-card {
  position: relative;
  width: 100%; max-width: 390px;
  background: #ffffff; border-radius: 22px;
  padding: 30px 26px 24px;
  box-shadow: 0 30px 70px rgba(28, 42, 56, 0.35);
  font-family: "Poppins", sans-serif;
  opacity: 0; transform: translateY(26px) scale(0.96);
  transition: opacity 0.3s cubic-bezier(0.22,1,0.36,1), transform 0.3s cubic-bezier(0.22,1,0.36,1);
}
.rwc-backdrop.open .rwc-card { opacity: 1; transform: translateY(0) scale(1); }

.rwc-card::before {
  content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
  background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
  border-radius: 22px 22px 0 0;
}

.rwc-close {
  position: absolute; top: 12px; right: 14px;
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  background: #f4f1e7; border: none; border-radius: 50%;
  color: #6b7684; font-size: 16px; line-height: 1; cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.rwc-close:hover { background: #eda423; color: #fff; transform: rotate(90deg); }

.rwc-icon {
  width: 54px; height: 54px; margin: 0 auto 14px;
  display: flex; align-items: center; justify-content: center;
  background: #FDF1DC; border: 1px solid #F5C77E; border-radius: 50%;
  font-size: 24px;
}

.rwc-title { margin: 0 0 6px; text-align: center; color: #1c2a38; font-size: 17px; font-weight: 800; }
.rwc-sub { margin: 0 0 18px; text-align: center; color: #5d6875; font-size: 12.5px; line-height: 1.6; }

.rwc-breakdown {
  margin-bottom: 18px; padding: 14px 16px;
  background: #FBF7EF; border: 1px dashed #E8D9BC; border-radius: 14px;
}

.rwc-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 0; font-size: 13px; color: #5d6875;
}
.rwc-row + .rwc-row { border-top: 1px dashed #E8D9BC; }
.rwc-row strong { color: #1c2a38; font-weight: 700; }
.rwc-row-spend strong { color: #C77800; }
.rwc-row-credit strong { color: #1e7a3d; font-size: 15px; font-weight: 800; }

.rwc-note { margin: 0 0 18px; text-align: center; color: #8d99a5; font-size: 11.5px; line-height: 1.6; }

.rwc-error {
  display: none; padding: 10px 12px; margin-bottom: 14px;
  background: #fdecec; border: 1px solid #f3b9b9; border-radius: 10px;
  color: #a4302f; font-size: 12.5px; text-align: center;
}
.rwc-error.show { display: block; }

.rwc-actions { display: flex; flex-direction: column; gap: 10px; }

.rwc-btn {
  width: 100%; height: 46px;
  border: none; border-radius: 12px;
  font-family: inherit; font-size: 13px; font-weight: 700;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.rwc-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none !important; }

.rwc-confirm {
  background: linear-gradient(135deg, #f6b93b, #eda423);
  color: #1c2a38; box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
}
.rwc-confirm:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45); }

.rwc-cancel { background: #ffffff; border: 1.5px solid #dfe4ea; color: #1c2a38; }
.rwc-cancel:hover:not(:disabled) { border-color: #eda423; color: #b07708; }

@media (prefers-reduced-motion: reduce) {
  .rwc-backdrop, .rwc-card { transition: none; }
}

/* =========================================================
   FLOATING LOGIN MODAL (lx-*) — unchanged from your file;
   kept identical so the guest popup still works.
   (Full lx- block from your original is preserved below.)
========================================================= */

.lx-modal { position: fixed; inset: 0; z-index: 1200; display: flex; align-items: center; justify-content: center; padding: 24px; visibility: hidden; pointer-events: none; }
.lx-modal.open { visibility: visible; pointer-events: auto; }
.lx-modal-backdrop { position: absolute; inset: 0; background: rgba(28, 42, 56, 0.38); backdrop-filter: blur(9px); -webkit-backdrop-filter: blur(9px); opacity: 0; transition: opacity 0.3s ease; }
.lx-modal.open .lx-modal-backdrop { opacity: 1; }
.lx-modal-card { position: relative; z-index: 1; width: 362px; max-height: calc(100vh - 48px); overflow-y: auto; background: #ffffff; border-radius: 22px; padding: 32px 30px 26px; box-shadow: 0 30px 70px rgba(28, 42, 56, 0.35); opacity: 0; transform: translateY(26px) scale(0.96); transition: opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1), transform 0.32s cubic-bezier(0.22, 1, 0.36, 1); }
.lx-modal.open .lx-modal-card { opacity: 1; transform: translateY(0) scale(1); animation: hvFloat 5s ease-in-out 0.4s infinite; }
.lx-modal-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #eda423, #f6c04e, #eda423); border-radius: 22px 22px 0 0; }
.lx-modal-close { position: absolute; top: 12px; right: 14px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: #f4f1e7; border: none; border-radius: 50%; color: #6b7684; font-size: 17px; line-height: 1; cursor: pointer; transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease; }
.lx-modal-close:hover { background: #eda423; color: #ffffff; transform: rotate(90deg); }
.lx-modal-logo { text-align: center; margin-bottom: 10px; }
.lx-modal-logo img { width: 96px; display: inline-block; }
.lx-modal-title { margin: 0 0 16px; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; text-align: center; color: #1c2a38; }
.lx-error { padding: 11px 14px; margin-bottom: 14px; background: #fdecec; border: 1px solid #f3b9b9; border-radius: 11px; color: #a4302f; font-size: 0.82rem; font-weight: 500; text-align: center; }
.lx-field { margin-bottom: 12px; }
.lx-field label { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; color: #1c2a38; font-size: 0.82rem; font-weight: 500; }
.lx-field label img { width: 16px; height: 16px; object-fit: contain; }
.lx-input { width: 100%; height: 44px; padding: 0 13px; background: #fbfcfd; border: 1.5px solid #e3e7ec; border-radius: 11px; outline: none; color: #1c2a38; font-family: "Poppins", sans-serif; font-size: 0.9rem; transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease; }
.lx-input:focus { background: #ffffff; border-color: #eda423; box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.15); }
.lx-forgot { text-align: center; margin: 4px 0 12px; }
.lx-forgot a { color: #1c2a38; font-size: 0.8rem; font-weight: 600; text-decoration: none; }
.lx-forgot a:hover { color: #b07708; }
.lx-submit { width: 100%; height: 46px; background: linear-gradient(135deg, #f6b93b, #eda423); border: none; border-radius: 12px; color: #1c2a38; font-family: "Poppins", sans-serif; font-size: 0.92rem; font-weight: 700; cursor: pointer; box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35); transition: transform 0.2s ease, box-shadow 0.2s ease; }
.lx-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45); }
.lx-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.lx-divider { display: flex; align-items: center; gap: 10px; margin: 16px 0; color: #6b7684; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
.lx-divider::before, .lx-divider::after { content: ""; flex: 1; height: 1px; background: #e3e7ec; }
.lx-social { width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; gap: 9px; background: #ffffff; border: 1.5px solid #e3e7ec; border-radius: 11px; color: #1c2a38; font-family: "Poppins", sans-serif; font-size: 0.85rem; font-weight: 500; cursor: pointer; margin-bottom: 10px; transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease; }
.lx-social:hover { border-color: #eda423; background: #fff8ec; transform: translateY(-1px); }
.lx-social img { width: 17px; height: 17px; object-fit: contain; }
.lx-create { margin: 6px 0 0; text-align: center; color: #6b7684; font-size: 0.83rem; }
.lx-create a { color: #b07708; font-weight: 700; text-decoration: none; }
.lx-create a:hover { text-decoration: underline; }

@media (max-width: 480px) {
  .lx-modal { padding: 14px; }
  .lx-modal-card { width: 100%; padding: 26px 20px 22px; }
}

@media (prefers-reduced-motion: reduce) {
  .lx-modal-card, .lx-modal-backdrop { transition: none; }
  .lx-modal.open .lx-modal-card { animation: none; }
}

    </style>

</head>


<body class="hive-club-page">


<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<?php if (isset($_GET["cancelled"])): ?>

    <div class="hv-notice">

        <span>&#10003;</span>

        You've stopped your Hive Club subscription. Your points are
        safe — you're welcome to rejoin anytime!

    </div>

<?php endif; ?>

<?php if (isset($_GET["redeemed"])): ?>

    <div class="hv-notice">

        <span>&#10003;</span>

        Reward redeemed! The wallet credit is available instantly —
        see it in Redemption History below.

    </div>

<?php endif; ?>


<section class="hv-hero">

    <div aria-hidden="true">
        <span class="hv-blob hv-blob-1"></span>
        <span class="hv-blob hv-blob-2"></span>
    </div>

    <div class="hv-hero-inner">

        <div class="hv-hero-text">

            <span class="hv-hero-badge hv-anim" style="--d: .05s;">
                <span class="hv-pulse-dot"></span>
                Rewards Program
            </span>

            <h1 class="hv-anim" style="--d: .15s;">
                Welcome to<br>
                <span class="hv-shimmer">Hive Club!</span>
            </h1>

            <p class="hv-anim" style="--d: .25s;">
                Join our rewards club and enjoy exclusive perks,
                discounts, and special offers every time you stay.
            </p>

            <div class="hv-hero-actions hv-anim" style="--d: .35s;">

                <?php if ($isHiveMember): ?>

                    <a href="/webprogg/user/membership.php" class="hv-btn hv-btn-honey hv-btn-shine">
                        UPGRADE
                        <span class="hv-btn-arrow">&rarr;</span>
                    </a>

                    <form method="POST" action="/webprogg/user/cancelmembership.php"
                          onsubmit="return confirm('Stop your Hive Club subscription? You keep every point — only the tier discounts pause.');">
                        <button type="submit" class="hv-btn hv-btn-outline">
                            STOP SUBSCRIBE
                        </button>
                    </form>

                <?php else: ?>

                    <a href="<?php echo htmlspecialchars($joinHiveClubLink); ?>" class="hv-btn hv-btn-honey hv-btn-shine">
                        JOIN HIVE CLUB
                        <span class="hv-btn-arrow">&rarr;</span>
                    </a>

                    <a href="#rewards" class="hv-btn hv-btn-outline">
                        HOW IT WORKS
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <div class="hv-hero-art hv-anim" style="--d: .3s;">

            <span class="hv-art-glow" aria-hidden="true"></span>

            <img class="hv-art-img" src="/webprogg/images/HiveCardIcon-HiveClub.png" alt="Hive Club Member Card">

            <div class="hv-chip hv-chip-1">
                <img src="/webprogg/images/ExclusiveDiscountIcon-HiveClub.png" alt="">
                <span>Up to 15% Off</span>
            </div>

            <div class="hv-chip hv-chip-2">
                <img src="/webprogg/images/EarnPointIcon-HiveClub.png" alt="">
                <span>Earn Points</span>
            </div>

            <div class="hv-chip hv-chip-3">
                <img src="/webprogg/images/EarlyAccessIcon-HiveClub.png" alt="">
                <span>Early Access</span>
            </div>

        </div>

    </div>

    <svg class="hv-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>


<section class="hv-main" id="membership">

    <div class="hv-left">

        <div class="hv-section-head hv-reveal">
            <span class="hv-eyebrow">Member Benefits</span>
            <h2>The more you book,<br>the more you earn</h2>
            <p>Every stay brings you closer to the next tier.</p>
        </div>

        <div class="hv-benefits" id="benefits">

            <div class="hv-benefit hv-reveal" style="--i: 0;">
                <div class="hv-benefit-icon">
                    <img src="/webprogg/images/ExclusiveDiscountIcon-HiveClub.png" alt="Exclusive Discounts">
                </div>
                <h3>Exclusive Discounts</h3>
                <p>Get up to 15% off on selected stays.</p>
            </div>

            <div class="hv-benefit hv-reveal" style="--i: 1;">
                <div class="hv-benefit-icon">
                    <img src="/webprogg/images/SpecialOffersIcon-HiveClub.png" alt="Special Offers">
                </div>
                <h3>Special Offers</h3>
                <p>Access members-only promotions and bundles.</p>
            </div>

            <div class="hv-benefit hv-reveal" style="--i: 2;">
                <div class="hv-benefit-icon">
                    <img src="/webprogg/images/EarnPointIcon-HiveClub.png" alt="Earn Points">
                </div>
                <h3>Earn Points</h3>
                <p>Earn 1 point per ₱10 spent — redeem for wallet credit.</p>
            </div>

            <div class="hv-benefit hv-reveal" style="--i: 3;">
                <div class="hv-benefit-icon">
                    <img src="/webprogg/images/EarlyAccessIcon-HiveClub.png" alt="Early Access">
                </div>
                <h3>Early Access</h3>
                <p>Be the first to know about new listings and deals.</p>
            </div>

        </div>

        <div class="hv-tier-head hv-reveal">
            <span class="hv-eyebrow">Climb The Hive</span>
        </div>

        <div class="hv-tier-grid">

            <div class="hv-tier bronze hv-reveal<?php echo ($memberTier === 'Bronze Member' && $isHiveMember) ? ' current' : ''; ?>" style="--i: 0;">
                <?php if ($memberTier === 'Bronze Member' && $isHiveMember): ?>
                    <span class="hv-current-badge">CURRENT TIER</span>
                <?php endif; ?>
                <div class="hv-tier-top">
                    <div class="hv-tier-icon">
                        <img src="/webprogg/images/BronzeIcon-HiveClub.png" alt="Bronze">
                    </div>
                    <div>
                        <h3>Bronze</h3>
                        <strong>0 - 4,999 pts</strong>
                    </div>
                </div>
                <ul>
                    <li>5% off on stays</li>
                    <li>Member-only offers</li>
                </ul>
            </div>

            <div class="hv-tier gold hv-reveal<?php echo ($memberTier === 'Gold Member' && $isHiveMember) ? ' current' : ''; ?>" style="--i: 1;">
                <?php if ($memberTier === 'Gold Member' && $isHiveMember): ?>
                    <span class="hv-current-badge">CURRENT TIER</span>
                <?php endif; ?>
                <div class="hv-tier-top">
                    <div class="hv-tier-icon">
                        <img src="/webprogg/images/GoldIcon-HiveClub.png" alt="Gold">
                    </div>
                    <div>
                        <h3>Gold</h3>
                        <strong>5,000 - 24,999 pts</strong>
                    </div>
                </div>
                <ul>
                    <li>10% off on stays</li>
                    <li>Priority customer support</li>
                    <li>Early access to promos</li>
                </ul>
            </div>

            <div class="hv-tier platinum hv-reveal<?php echo ($memberTier === 'Platinum Member' && $isHiveMember) ? ' current' : ''; ?>" style="--i: 2;">
                <?php if ($memberTier === 'Platinum Member' && $isHiveMember): ?>
                    <span class="hv-current-badge">CURRENT TIER</span>
                <?php endif; ?>
                <div class="hv-tier-top">
                    <div class="hv-tier-icon">
                        <img src="/webprogg/images/PlatinumIcon-HiveClub.png" alt="Platinum">
                    </div>
                    <div>
                        <h3>Platinum</h3>
                        <strong>25,000+ pts</strong>
                    </div>
                </div>
                <ul>
                    <li>15% off on stays</li>
                    <li>Free upgrades (subject to availability)</li>
                    <li>VIP deals &amp; exclusive perks</li>
                </ul>
            </div>

        </div>

        <!-- =====================================================
             REWARDS SECTION (Phase 5) — "View Rewards" target
        ====================================================== -->
        <section class="hv-rewards" id="rewards">

            <div class="hv-section-head hv-reveal">
                <span class="hv-eyebrow">Redeem Points</span>
                <h2>Turn points into<br>real wallet credit</h2>
                <p>Points are earned money — spend them any time, member or not.</p>
            </div>

            <div class="hv-rw-balance hv-reveal">
                <span class="hv-rw-balance-icon">&#127856;</span>
                <div>
                    <div class="hv-rw-balance-num"><?php echo number_format($memberRedeemable); ?></div>
                    <p class="hv-rw-balance-caption">
                        points spendable right now
                        &middot; worth about &#8369;<?php echo number_format($memberRedeemable * $hivePesoPerPoint, 0); ?>
                    </p>
                </div>
            </div>

            <div class="hv-rw-grid">

                <?php foreach ($hiveRewards as $rw):
                    $cost    = (int) $rw['cost_points'];
                    $claimed = in_array((int) $rw['id'], $alreadyRedeemedIds, true);
                    $afford  = $memberRedeemable >= $cost;
                ?>

                <div class="hv-rw-card hv-reveal">

                    <span class="hv-rw-credit">&#8369;<?php echo number_format((float) $rw['reward_value'], 0); ?></span>

                    <h4><?php echo htmlspecialchars($rw['name']); ?></h4>

                    <p><?php echo htmlspecialchars($rw['description']); ?></p>

                    <span class="hv-rw-cost"><?php echo number_format($cost); ?> points</span>

                    <?php if ($claimed): ?>

                        <button type="button" class="hv-rw-btn hv-rw-btn-claimed" disabled>
                            &#10003; Claimed
                        </button>

                    <?php elseif (!$isLoggedIn): ?>

                        <a href="/webprogg/auth/loginform.php?redirect=<?php echo urlencode('/webprogg/hiveclub.php#rewards'); ?>"
                           class="hv-rw-btn hv-rw-btn-ready">
                            LOG IN TO REDEEM
                        </a>

                    <?php elseif ($afford): ?>

                        <button
                            type="button"
                            class="hv-rw-btn hv-rw-btn-ready js-redeem-btn"
                            data-reward-id="<?php echo (int) $rw['id']; ?>"
                            data-reward-name="<?php echo htmlspecialchars($rw['name'], ENT_QUOTES); ?>"
                            data-cost="<?php echo $cost; ?>"
                            data-credit="<?php echo number_format((float) $rw['reward_value'], 2, '.', ''); ?>"
                            data-balance="<?php echo $memberRedeemable; ?>"
                        >
                            REDEEM NOW
                        </button>

                    <?php else: ?>

                        <button type="button" class="hv-rw-btn hv-rw-btn-locked" disabled>
                            Need <?php echo number_format($cost - $memberRedeemable); ?> more pts
                        </button>

                    <?php endif; ?>

                </div>

                <?php endforeach; ?>

            </div>

            <?php if (!empty($hiveRedemptions)): ?>

                <div class="hv-rw-history hv-reveal">
                    <h4>Redemption History</h4>
                    <?php foreach ($hiveRedemptions as $hr): ?>
                        <div class="hv-rw-history-item">
                            <div>
                                <strong><?php echo htmlspecialchars($hr['reward_name']); ?></strong><br>
                                <span>
                                    <?php echo number_format((int) $hr['points_spent']); ?> pts
                                    &middot; <?php echo htmlspecialchars(date('M j, Y', strtotime($hr['created_at']))); ?>
                                </span>
                            </div>
                            <span class="hv-rw-hist-credit">+&#8369;<?php echo number_format((float) $hr['wallet_credited'], 2); ?> wallet</span>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </section>

    </div>

    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <aside class="hv-sidebar">

        <div class="hv-status-card hv-reveal">

            <h2>Your Hive Club Status</h2>

            <?php if ($isHiveMember): ?>

            <div class="hv-status-profile">

                <div class="hv-status-medal">
                    <img src="<?php echo $statusIcon; ?>" alt="<?php echo htmlspecialchars($memberTier); ?>">
                </div>

                <div>
                    <h3><?php echo htmlspecialchars($memberTier); ?></h3>

                    <strong>
                        <span data-count="<?php echo (int) $memberPoints; ?>">
                            <?php echo number_format($memberPoints); ?>
                        </span>
                        pts lifetime
                    </strong>

                    <!-- NEW — spendable balance sub-line -->
                    <span style="display:block; margin-top:6px; color:#6b7684; font-size:11.5px; font-weight:600;">
                        &#128176; Spendable: <?php echo number_format($memberRedeemable); ?> pts
                    </span>

                    <p>Member ID: <?php echo htmlspecialchars($memberId); ?></p>

                </div>

            </div>

            <div class="hv-progress">
                <div class="hv-progress-fill" data-progress="<?php echo (int) $progress; ?>%" style="width: 0%;"></div>
            </div>

            <div class="hv-progress-labels">
                <span>Current: <?php echo number_format($memberPoints); ?> pts</span>
                <span>
                    <?php echo $nextTier !== null
                        ? 'Next: ' . htmlspecialchars($nextTier) . ' at ' . number_format($nextTierPoints)
                        : 'Top tier!'; ?>
                </span>
            </div>

            <!-- NEW — next-tier caption (Phase 8 polish) -->
            <?php if ($hiveNext !== null): ?>
                <span style="display:block; margin-top:8px; color:#8a5a10; font-size:11.5px; font-weight:700;">
                    <?php echo number_format($hiveNext['needed']); ?> pts to
                    <?php echo htmlspecialchars($hiveNext['name']); ?>
                    (<?php echo hive_tier_discount($hiveNext['name']); ?>% off stays)
                </span>
            <?php else: ?>
                <span style="display:block; margin-top:8px; color:#b07708; font-size:11.5px; font-weight:800;">
                    &#128081; Top tier reached — Platinum
                </span>
            <?php endif; ?>

            <a href="#rewards" class="hv-rewards-btn">VIEW REWARDS</a>

            <?php else: ?>

            <p class="hv-not-member">
                You're not a Hive Club member yet. Join for free to start
                earning points on every completed stay — points are spendable
                even without a paid plan.
            </p>

            <div class="hv-status-profile" style="margin-bottom:16px;">
                <div class="hv-status-medal">
                    <img src="/webprogg/images/BronzeIcon-HiveClub.png" alt="Bronze">
                </div>
                <div>
                    <h3>Not a member yet</h3>
                    <strong>
                        <span data-count="0">0</span> pts
                    </strong>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars($joinHiveClubLink); ?>" class="hv-rewards-btn" style="text-align:center;">
                JOIN / VIEW REWARDS
            </a>

            <?php endif; ?>

        </div>

        <div class="hv-earn-card hv-reveal">

            <h2>How To Earn</h2>

            <div class="hv-earn-item">
                <span class="hv-earn-icon">&#127968;</span>
                <div>
                    <strong>Complete a stay</strong>
                    <p>Every ₱10 actually paid = 1 point, awarded when your stay completes.</p>
                </div>
            </div>

            <div class="hv-earn-item">
                <span class="hv-earn-icon">&#128176;</span>
                <div>
                    <strong>Buy a plan</strong>
                    <p>Gold grants +5,000 points; Platinum grants +25,000 instantly.</p>
                </div>
            </div>

            <div class="hv-earn-item">
                <span class="hv-earn-icon">&#127856;</span>
                <div>
                    <strong>Redeem anytime</strong>
                    <p>200 pts = ₱100 wallet credit. Points never expire.</p>
                </div>
            </div>

        </div>

    </aside>

</section>


<section class="hv-cta-wrap">
    <div class="hv-cta">

        <div class="hv-cta-left">

            <img src="/webprogg/images/Crown_Logo.png" alt="Hive Club Crown" class="hv-cta-crown">

            <div class="hv-cta-text">
                <h2>Ready to earn your first points?</h2>
                <p>Book a stay, complete it, and watch your Hive Club balance grow —<br>then redeem for real wallet credit.</p>
            </div>

        </div>

        <div class="hv-cta-buttons">
            <a href="/webprogg/Listings/listing.php" class="hv-btn hv-btn-honey hv-btn-shine">
                BROWSE LISTINGS
                <span class="hv-btn-arrow">&rarr;</span>
            </a>
        </div>

    </div>
</section>


<!-- =========================================================
     FLOATING LOGIN MODAL (guests) — unchanged
========================================================= -->
<div class="lx-modal" id="lxModal" aria-hidden="true">

    <div class="lx-modal-backdrop" data-lx-close></div>

    <div class="lx-modal-card" role="dialog" aria-modal="true" aria-label="Log in to RoomHive">

        <button type="button" class="lx-modal-close" data-lx-close aria-label="Close">
            &times;
        </button>

        <div class="lx-modal-logo">
            <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive logo">
        </div>

        <h2 class="lx-modal-title">Welcome back!</h2>

        <div class="lx-error" id="lxModalError" hidden></div>

        <form id="lxModalForm" novalidate>

            <input type="hidden" name="redirect" value="">

            <div class="lx-field">
                <label for="lx-email">
                    <img src="/webprogg/images/EmailIcon.jpg" alt="">
                    Email Address
                </label>
                <input class="lx-input" type="email" id="lx-email" name="email" autocomplete="email" required>
            </div>

            <div class="lx-field">
                <label for="lx-password">
                    <img src="/webprogg/images/LockIcon.png" alt="">
                    Password
                </label>
                <input class="lx-input" type="password" id="lx-password" name="password" autocomplete="current-password" required>
            </div>

            <div class="lx-forgot">
                <a href="/webprogg/auth/forgotpassword.php">Forgot Password?</a>
            </div>

            <button type="submit" class="lx-submit">Log in</button>

        </form>

        <div class="lx-divider">or</div>

        <button type="button" class="lx-social" onclick="window.location.href='/webprogg/auth/google-login.php'">
            <img src="/webprogg/images/Googlecons.png" alt="">
            Continue with Google
        </button>

        <button type="button" class="lx-social" onclick="window.location.href='/webprogg/auth/apple-login.php'">
            <img src="/webprogg/images/AppleIcons.png" alt="">
            Continue with Apple
        </button>

        <p class="lx-create">
            Not registered yet?
            <a href="/webprogg/auth/createaccount.php">Create Account Here</a>
        </p>

    </div>

</div>


<!-- =========================================================
     REDEEM CONFIRMATION CARD (floating)
========================================================== -->
<div class="rwc-backdrop" id="rwcBackdrop" aria-hidden="true">

    <div class="rwc-card" role="dialog" aria-modal="true">

        <button type="button" class="rwc-close" data-rwc-close aria-label="Close">&times;</button>

        <div class="rwc-icon">&#127856;</div>

        <h3 class="rwc-title" id="rwcTitle">Redeem this reward?</h3>

        <p class="rwc-sub">
            Points are spent instantly and the credit lands in your
            RoomHive wallet right away.
        </p>

        <div class="rwc-breakdown">
            <div class="rwc-row">
                <span>Cost</span>
                <strong id="rwcCost">0 pts</strong>
            </div>
            <div class="rwc-row rwc-row-spend">
                <span>Your balance after</span>
                <strong id="rwcAfter">0 pts</strong>
            </div>
            <div class="rwc-row rwc-row-credit">
                <span>You'll receive</span>
                <strong id="rwcCredit">&#8369;0.00</strong>
            </div>
        </div>

        <p class="rwc-note">
            Each reward can be claimed once per account.
            Wallet credit applies automatically to any booking payment.
        </p>

        <div class="rwc-error" id="rwcError"></div>

        <div class="rwc-actions">
            <button type="button" class="rwc-btn rwc-confirm" id="rwcConfirm">Confirm Redemption</button>
            <button type="button" class="rwc-btn rwc-cancel" data-rwc-close>Maybe Later</button>
        </div>

    </div>

</div>


<script src="/webprogg/assets/javaScript.js"></script>

<!-- =========================================================
     SCROLL REVEAL + PROGRESS BAR + COUNT-UP
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(
        document.querySelectorAll(".hv-reveal")
    );

    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;

                    var el = entry.target;
                    io.unobserve(el);
                    el.classList.add("in-view");

                    window.setTimeout(function () {
                        el.style.setProperty("--i", "0");
                    }, 1200);
                });
            },
            { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
        );

        revealEls.forEach(function (el) { io.observe(el); });
    }

    /* Progress bar animates to its data-progress value */
    var fill = document.querySelector('.hv-progress-fill[data-progress]');
    if (fill) {
        var target = fill.getAttribute('data-progress') || '0%';
        if (reduced) {
            fill.style.width = target;
        } else {
            window.setTimeout(function () { fill.style.width = target; }, 400);
        }
    }

    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var countIo = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                countIo.unobserve(el);

                var target = parseFloat(el.getAttribute("data-count")) || 0;
                var t0 = null;
                var DURATION = 1200;

                var stepFn = function (ts) {
                    if (!t0) t0 = ts;
                    var k = Math.min((ts - t0) / DURATION, 1);
                    var eased = 1 - Math.pow(1 - k, 3);
                    el.textContent = Math.round(target * eased).toLocaleString();
                    if (k < 1) window.requestAnimationFrame(stepFn);
                };

                window.requestAnimationFrame(stepFn);
            });
        }, { threshold: 0.6 });
        Array.prototype.forEach.call(counters, function (el) { countIo.observe(el); });
    }
})();
</script>

<!-- =========================================================
     REDEEM CARD SCRIPT
========================================================= -->
<script>
(function () {
    "use strict";

    var backdrop = document.getElementById('rwcBackdrop');
    if (!backdrop) { return; }

    var costEl    = document.getElementById('rwcCost');
    var afterEl   = document.getElementById('rwcAfter');
    var creditEl  = document.getElementById('rwcCredit');
    var confirmEl = document.getElementById('rwcConfirm');
    var errorEl   = document.getElementById('rwcError');

    var currentId = null;
    var busy      = false;

    function openCard(btn) {
        currentId = btn.getAttribute('data-reward-id');

        var cost   = parseInt(btn.getAttribute('data-cost'), 10) || 0;
        var credit = parseFloat(btn.getAttribute('data-credit')) || 0;
        var bal    = parseInt(btn.getAttribute('data-balance'), 10) || 0;

        costEl.textContent   = cost.toLocaleString() + ' pts';
        afterEl.textContent  = Math.max(0, bal - cost).toLocaleString() + ' pts';
        creditEl.textContent = '\u20B1' + credit.toFixed(2);

        errorEl.classList.remove('show');
        confirmEl.disabled = false;
        confirmEl.textContent = 'Confirm Redemption';

        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeCard() {
        if (busy) { return; }
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-redeem-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openCard(btn); });
    });

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) { closeCard(); }
    });
    document.querySelectorAll('[data-rwc-close]').forEach(function (el) {
        el.addEventListener('click', closeCard);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) { closeCard(); }
    });

    confirmEl.addEventListener('click', function () {
        if (busy || !currentId) { return; }

        busy = true;
        confirmEl.disabled = true;
        confirmEl.textContent = 'Redeeming\u2026';
        errorEl.classList.remove('show');

        fetch('/webprogg/booking/redeem-reward.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'reward_id=' + encodeURIComponent(currentId),
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.success) {
                busy = false;
                window.location.href = '/webprogg/hiveclub.php?redeemed=1#rewards';
                return;
            }
            busy = false;
            confirmEl.disabled = false;
            confirmEl.textContent = 'Confirm Redemption';
            errorEl.textContent = (data && data.message) || 'Could not complete the redemption.';
            errorEl.classList.add('show');
        })
        .catch(function () {
            busy = false;
            confirmEl.disabled = false;
            confirmEl.textContent = 'Confirm Redemption';
            errorEl.textContent = "Couldn't reach the server. Please try again.";
            errorEl.classList.add('show');
        });
    });
})();
</script>

<!-- =========================================================
     FLOATING LOGIN MODAL SCRIPT (guests) — unchanged
========================================================= -->
<script>
(function () {
    "use strict";

    var modal = document.getElementById("lxModal");
    var form  = document.getElementById("lxModalForm");

    if (!modal || !form) {
        return;
    }

    var errorBox   = document.getElementById("lxModalError");
    var emailInput = document.getElementById("lx-email");

    function openModal(redirectTarget) {
        var redirectInput = form.querySelector('input[name="redirect"]');

        if (redirectInput) {
            redirectInput.value = redirectTarget || "";
        }

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";

        if (emailInput) {
            window.setTimeout(function () {
                emailInput.focus();
            }, 350);
        }
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";

        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = "";
        }
    }

    var autoOpen = <?php echo $autoOpenLoginPopup ? "true" : "false"; ?>;

    if (autoOpen) {
        var alreadyShown = false;

        try {
            alreadyShown =
                sessionStorage.getItem("rhLoginModalShown") === "1";
            sessionStorage.setItem("rhLoginModalShown", "1");
        } catch (err) {
            /* storage unavailable — just show it */
        }

        if (!alreadyShown) {
            window.setTimeout(function () {
                openModal("");
            }, 700);
        }
    }

    document.addEventListener("click", function (event) {
        if (!event.target || !event.target.closest) {
            return;
        }

        var loginLink = event.target.closest('a[href*="loginform.php"]');

        if (loginLink) {
            event.preventDefault();

            var redirect = "";

            try {
                var url = new URL(loginLink.href, window.location.origin);
                redirect = url.searchParams.get("redirect") || "";
            } catch (err) {
                redirect = "";
            }

            openModal(redirect);
            return;
        }

        if (event.target.closest("[data-lx-close]")) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("open")) {
            closeModal();
        }
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (errorBox) {
            errorBox.hidden = true;
        }

        var submitBtn = form.querySelector(".lx-submit");
        var originalLabel = submitBtn ? submitBtn.textContent : "";

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Logging in...";
        }

        fetch("/webprogg/auth/loginform.php", {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: new FormData(form),
            credentials: "same-origin"
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    window.location.href = data.redirect;
                    return;
                }

                if (errorBox) {
                    errorBox.textContent =
                        (data && data.error) || "Something went wrong.";
                    errorBox.hidden = false;
                }
            })
            .catch(function () {
                if (errorBox) {
                    errorBox.textContent =
                        "Couldn't reach the server. Please try again.";
                    errorBox.hidden = false;
                }
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalLabel || "Log in";
                }
            });
    });
})();
</script>


</body>

</html>