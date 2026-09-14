<?php

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 4)
   SPACE SUBMITTED — AWAITING ADMIN APPROVAL
   ========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* Only logged-in users can access this page. */
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: /webprogg/auth/loginform.php");
    exit();
}

/* Step 2 and Step 3 must be completed first — listing_id is
   set by host-step2.php's INSERT, and cover_photo is set by
   host-step3.php, so both being present proves the earlier
   steps actually ran. */
if (
    !isset($_SESSION["host_application"]["listing_id"]) ||
    !isset($_SESSION["host_application"]["cover_photo"])
) {
    header("Location: /webprogg/host/becomeahost.php");
    exit();
}

 $isLoggedIn = true;
 $userName = $_SESSION["user_name"] ?? "User";

/*
 * Whether the ADMIN has already approved this user's host
 * application. This is the real gate now — not "did they
 * finish the upload wizard". It only flips true from
 * hostapplication.php once an admin clicks Approve.
 */
 $isHostApproved = isset($_SESSION["is_host"]) && $_SESSION["is_host"] === true;

 $currentPage = $isHostApproved ? "/webprogg/host/hostprofile.php" : "/webprogg/user/userprofile.php";
 $currentStep = 4;

/* ---------------------------------------------------------
   HOSTING STEPS
--------------------------------------------------------- */
 $hostSteps = [
    [
        "number" => 1,
        "icon" => "/webprogg/images/CreateIcon-BecomeAHost.png",
        "alt" => "Create Account",
        "title" => "Create Account",
        "description" => "Sign up as a host in minutes"
    ],
    [
        "number" => 2,
        "icon" => "/webprogg/images/AddYourSpaceIcon-BecomeAHost.png",
        "alt" => "Add Your Space",
        "title" => "Add Your Space",
        "description" => "Tell us about your property"
    ],
    [
        "number" => 3,
        "icon" => "/webprogg/images/UploadPhotosIcon-BecomeAHost.png",
        "alt" => "Upload Photos",
        "title" => "Upload Photos",
        "description" => "Show your space to attract renters"
    ],
    [
        "number" => 4,
        "icon" => "/webprogg/images/GetBookingsIcon-BecomeAHost.png",
        "alt" => "Get Bookings",
        "title" => "Get Bookings",
        "description" => "Approve bookings and start earning"
    ]
];

/* ---------------------------------------------------------
   FINALIZE THE LISTING SUBMISSION (runs once)
   Flips the real `listings` row from 'draft' to 'pending' so
   it shows up in listingapplication.php's queue for an admin
   to review. This does NOT touch is_host or hosting status —
   that only happens once an admin approves the underlying
   host_applications row (see hostapplication.php). Guarded by
   "finalized" so refreshing this page doesn't re-run the
   update.
--------------------------------------------------------- */
if (empty($_SESSION["host_application"]["finalized"])) {

    $listingId = $_SESSION["host_application"]["listing_id"];

    $pdo->prepare("UPDATE listings SET status = 'pending' WHERE id = :id")
        ->execute(['id' => $listingId]);

    $_SESSION["host_application"]["finalized"] = true;
}

/* ---------------------------------------------------------
   NAVIGATION
--------------------------------------------------------- */
 $navigation = [
    "HOME" => "/webprogg/user/usershome.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => "/webprogg/host/becomeahost.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

 $quickLinks = [
    "About Us" => "/webprogg/index.php",
    "How It Works" => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Hive Club" => "/webprogg/hiveclub.php",
    "Contacts" => "/webprogg/misc/contacts.php"
];

 $listingCategories = [
    "Shared Bedroom" => "shared-bedroom",
    "Private Room" => "private-room",
    "Entire House" => "entire-house",
    "Boarding House" => "boarding-house",
    "Studio Loft" => "studio-loft"
];

/*
 * NAVBAR AVATAR
 * $_SESSION['avatar_path'] is only set at login time, so if the
 * user uploaded a new profile photo since then, re-check the DB
 * so the navbar's account icon reflects it immediately instead
 * of only after logging back in.
 */
 $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
 $avatarStmt->execute(['id' => $_SESSION['user_id']]);
 $avatarRow = $avatarStmt->fetch();
 $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
 $fullName = $_SESSION["host_application"]["full_name"] ?? $userName;

/* Notification bell badge count — same placeholder used across
   every logged-in page's navbar until real notifications land. */
 $notification_count = 0;

/* NEW — stepper progress fill.
   The track spans from the first circle's center (12.5%) to the
   last circle's center (87.5%) — a total of 75% of the container.
   With 3 of 4 steps completed, 75% is exactly the full track, so
   the honey line reaches the final circle. */
 $stepProgress = (($currentStep - 1) / count($hostSteps)) * 100;
 $stepProgress = max(0, min(75, $stepProgress));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive - <?php echo $isHostApproved ? 'Space Added Successfully' : 'Awaiting Approval'; ?></title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/webprogg/assets/style.css">

    <!-- NEW: enables JS-gated entrance animations -->
    <script>document.documentElement.classList.add("js");</script>

    <!-- =====================================================
         STEP 4 — SUCCESS PAGE STYLES
         Scoped to body.rh-step4, palette aligned to the
         site-wide hive tokens (honey #eda423 / moss #2f9e5b /
         ink #1c2a38). CHANGED: the old .success-* styles and
         the custom success-footer are replaced — the stepper
         now uses the same continuous track + progress-fill
         pattern as steps 2 and 3, and the footer is the
         standard site-footer used everywhere else.
    ====================================================== -->

    <style>

        :root {
            --hs-honey: #eda423;
            --hs-honey-light: #f6c04e;
            --hs-moss: #2f9e5b;
            --hs-ink: #1c2a38;
            --hs-muted: #5d6875;
        }

        @media (prefers-reduced-motion: reduce) {
            .hs-blob,
            .hs-pulse-dot::after,
            .hs-shimmer,
            .hs-ring,
            .hs-step.current .hs-step-circle,
            .hs-icon-dots,
            .hs-btn-primary::after {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .js .hs-anim {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .hs-success-icon {
                animation: none !important;
                opacity: 1 !important;
            }
        }

        body.rh-step4 {
            background: #ffffff;
            font-family: "Poppins", sans-serif;
            color: var(--hs-ink);
        }

        /* =========================================================
           HERO (compact) — honeycomb + blobs + stepper
        ========================================================= */

        .hs-hero {
            position: relative;

            overflow: hidden;

            background:
                radial-gradient(900px 420px at 85% -10%, rgba(237, 164, 35, 0.16), transparent 60%),
                radial-gradient(700px 380px at -10% 110%, rgba(237, 164, 35, 0.12), transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #fff8ec 100%);
        }

        .hs-hero::before {
            content: "";

            position: absolute;
            inset: 0;

            background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.08' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 49px;

            -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 80%);
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 80%);

            pointer-events: none;
        }

        .hs-blob {
            position: absolute;

            border-radius: 50%;
            filter: blur(70px);

            pointer-events: none;
        }

        .hs-blob-1 {
            width: 380px;
            height: 380px;

            top: -140px;
            right: -110px;

            background: radial-gradient(circle at 30% 30%, rgba(246, 196, 78, 0.85), rgba(237, 164, 35, 0.25) 60%, transparent 75%);

            animation: hsDrift 14s ease-in-out infinite alternate;
        }

        .hs-blob-2 {
            width: 280px;
            height: 280px;

            bottom: -130px;
            left: -100px;

            background: radial-gradient(circle at 60% 40%, rgba(246, 196, 78, 0.7), rgba(237, 164, 35, 0.2) 60%, transparent 75%);

            animation: hsDrift 18s ease-in-out infinite alternate-reverse;
        }

        @keyframes hsDrift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, -24px) scale(1.08); }
        }

        .hs-hero-inner {
            position: relative;
            z-index: 1;

            max-width: 900px;

            margin: 0 auto;

            padding: 56px 24px 130px;

            text-align: center;
        }

        /* ---- Eyebrow badge ---- */

        .hs-eyebrow {
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

        .hs-pulse-dot {
            position: relative;

            width: 8px;
            height: 8px;

            background: var(--hs-honey);
            border-radius: 50%;
        }

        .hs-pulse-dot::after {
            content: "";

            position: absolute;
            inset: 0;

            background: var(--hs-honey);
            border-radius: 50%;

            animation: hsPing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        @keyframes hsPing {
            0%   { transform: scale(1); opacity: 0.7; }
            80%, 100% { transform: scale(2.6); opacity: 0; }
        }

        .hs-hero h1 {
            margin: 20px 0 6px;

            color: var(--hs-ink);

            font-size: clamp(32px, 4.4vw, 48px);
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1.1;
        }

        .hs-shimmer {
            background: linear-gradient(92deg, #eda423 0%, #f6c04e 45%, #eda423 90%);
            background-size: 200% auto;

            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;

            animation: hsShimmer 3.5s linear infinite;
        }

        @keyframes hsShimmer {
            to { background-position: 200% center; }
        }

        /* =========================================================
           STEPPER — same track + fill pattern as steps 2 and 3
        ========================================================= */

        .hs-stepper {
            position: relative;

            margin-top: 44px;
        }

        .hs-stepper-track {
            position: absolute;

            top: 26px;
            left: 12.5%;
            right: 12.5%;

            height: 2px;

            background: #e8e1cf;

            z-index: 1;
        }

        .hs-stepper-fill {
            position: absolute;

            top: 26px;
            left: 12.5%;

            height: 2px;

            background: linear-gradient(90deg, #f6b93b, var(--hs-honey));

            transition: width 1s cubic-bezier(0.22, 1, 0.36, 1);

            z-index: 1;
        }

        .hs-steps {
            position: relative;
            z-index: 2;

            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .hs-step {
            position: relative;

            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;

            padding: 0 10px;
        }

        .hs-step-circle {
            position: relative;

            width: 52px;
            height: 52px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #ffffff;

            border: 2px solid #e8e1cf;
            border-radius: 50%;

            flex-shrink: 0;

            transition: border-color 0.3s ease, background 0.3s ease;
        }

        .hs-step-circle img {
            width: 22px;
            height: 22px;

            object-fit: contain;

            display: block;

            opacity: 0.55;
            filter: grayscale(1);

            transition: opacity 0.3s ease, filter 0.3s ease;
        }

        /* Completed steps: moss green, white icon + check tick */
        .hs-step.completed .hs-step-circle {
            background: var(--hs-moss);

            border-color: var(--hs-moss);
        }

        .hs-step.completed .hs-step-circle img {
            opacity: 1;
            filter: brightness(0) invert(1);
        }

        .hs-step.completed .hs-step-circle::after {
            content: "";

            position: absolute;

            width: 9px;
            height: 5px;

            right: 6px;
            bottom: 6px;

            border-left: 2.5px solid #ffffff;
            border-bottom: 2.5px solid #ffffff;

            transform: rotate(-45deg);
        }

        /* Current step: honey glow + breathing ring */
        .hs-step.current .hs-step-circle {
            background: #FDF4E3;

            border-color: var(--hs-honey);

            animation: hsStepPulse 2.4s ease-in-out infinite;
        }

        .hs-step.current .hs-step-circle img {
            opacity: 1;
            filter: none;
        }

        @keyframes hsStepPulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.18); }
            50%      { box-shadow: 0 0 0 9px rgba(237, 164, 35, 0.09); }
        }

        /* Number badge nested inside the circle — same anchor
           pattern as steps 2 and 3. */
        .hs-step-number {
            position: absolute;

            top: -4px;
            right: -4px;

            width: 20px;
            height: 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: var(--hs-ink);

            border: 2px solid #fff8ec;
            border-radius: 50%;
            box-sizing: border-box;

            color: #fff;

            font-size: 11px;
            font-weight: 600;

            z-index: 3;
        }

        .hs-step.current .hs-step-number {
            background: var(--hs-honey-dark);
        }

        .hs-step.completed .hs-step-number {
            background: var(--hs-moss);
        }

        .hs-step h3 {
            margin: 14px 0 2px;

            color: var(--hs-ink);

            font-size: 14px;
            font-weight: 600;
        }

        .hs-step p {
            margin: 0 auto;

            max-width: 150px;

            color: var(--hs-muted);

            font-size: 12.5px;
            line-height: 1.4;
        }

        .hs-step:not(.current) h3 {
            color: var(--hs-muted);
        }

        /* ---- Wave divider ---- */

        .hs-wave {
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;

            width: 100%;
            height: 60px;

            display: block;
        }

        /* =========================================================
           SUCCESS CARD
        ========================================================= */

        .hs-card-wrap {
            max-width: 640px;

            margin: -70px auto 0;

            padding: 0 24px 90px;

            position: relative;
            z-index: 2;
        }

        .hs-card {
            position: relative;

            padding: 46px 44px 40px;

            background: #ffffff;

            border: 1px solid #e8e1cf;
            border-radius: 24px;

            box-shadow: 0 30px 70px rgba(28, 42, 56, 0.16);

            text-align: center;

            overflow: hidden;
        }

        /* Honey accent bar across the top */
        .hs-card::before {
            content: "";

            position: absolute;
            top: 0;
            left: 0;
            right: 0;

            height: 6px;

            background: linear-gradient(90deg, #eda423, #f6c04e, #eda423);
        }

        /* ---- Status badge ---- */

        .hs-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 8px 18px;

            margin-bottom: 22px;

            background: #fff4e8;

            border: 1px solid rgba(237, 164, 35, 0.5);
            border-radius: 999px;

            color: #8a5a10;

            font-size: 12.5px;
            font-weight: 700;
        }

        /* ---- Icon: confetti dots + pulsing rings + drop-in ---- */

        .hs-icon-wrap {
            position: relative;

            width: 200px;
            max-width: 100%;

            margin: 0 auto 10px;
        }

        .hs-icon-dots {
            position: absolute;
            inset: -14%;

            background:
                radial-gradient(circle at 8% 22%, #f3c467 0 4px, transparent 5px),
                radial-gradient(circle at 18% 68%, #e2544c 0 3px, transparent 4px),
                radial-gradient(circle at 90% 18%, #f3c467 0 4px, transparent 5px),
                radial-gradient(circle at 80% 72%, #eda423 0 3px, transparent 4px),
                radial-gradient(circle at 96% 52%, #f3c467 0 3px, transparent 4px),
                radial-gradient(circle at 4% 82%, #2f9e5b 0 3px, transparent 4px),
                radial-gradient(circle at 50% 4%, #eda423 0 3px, transparent 4px);
            background-repeat: no-repeat;

            animation: hsDotsFloat 4s ease-in-out infinite;
        }

        @keyframes hsDotsFloat {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-8px); }
        }

        .hs-ring {
            position: absolute;

            width: 72%;
            height: 72%;

            top: 14%;
            left: 14%;

            border: 3px solid rgba(237, 164, 35, 0.45);
            border-radius: 50%;

            opacity: 0;

            animation: hsRing 2.6s ease-out infinite;
        }

        .hs-ring-2 {
            animation-delay: 1.3s;
        }

        @keyframes hsRing {
            0%   { transform: scale(0.7); opacity: 0.8; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .hs-success-icon {
            position: relative;
            z-index: 1;

            width: 100%;

            display: block;

            object-fit: contain;

            filter: drop-shadow(0 18px 28px rgba(237, 164, 35, 0.3));

            animation: hsIconDrop 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }

        @keyframes hsIconDrop {
            0%   { opacity: 0; transform: translateY(-30px) scale(0.7); }
            70%  { transform: translateY(4px) scale(1.04); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ---- Copy ---- */

        .hs-card h2 {
            margin: 0 0 14px;

            color: var(--hs-ink);

            font-size: clamp(24px, 3vw, 30px);
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .hs-message {
            margin: 0 auto 30px;

            max-width: 440px;

            color: var(--hs-muted);

            font-size: 14.5px;
            line-height: 1.75;
        }

        /* ---- Action buttons ---- */

        .hs-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .hs-btn {
            position: relative;
            overflow: hidden;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            min-height: 50px;

            padding: 0 28px;

            border-radius: 12px;

            font-family: "Poppins", sans-serif;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.6px;

            text-decoration: none;

            transition:
                transform 0.22s ease,
                box-shadow 0.22s ease,
                background 0.22s ease,
                color 0.22s ease,
                border-color 0.22s ease;
        }

        .hs-btn:hover {
            transform: translateY(-2px);
        }

        .hs-btn:active {
            transform: translateY(0) scale(0.98);
        }

        .hs-btn-primary {
            background: linear-gradient(135deg, #f6b93b, var(--hs-honey));

            color: var(--hs-ink);

            box-shadow: 0 10px 22px rgba(237, 164, 35, 0.4);
        }

        .hs-btn-primary:hover {
            box-shadow: 0 14px 28px rgba(237, 164, 35, 0.5);
        }

        .hs-btn-secondary {
            background: #ffffff;

            color: var(--hs-ink);

            border: 1.5px solid #dfe4ea;
        }

        .hs-btn-secondary:hover {
            border-color: var(--hs-honey);

            color: #b07708;

            box-shadow: 0 8px 20px rgba(28, 42, 56, 0.08);
        }

        .hs-btn img {
            width: 18px;
            height: 18px;

            object-fit: contain;

            filter: brightness(0);
        }

        /* Shine sweep on the primary button */
        .hs-btn-primary::after {
            content: "";

            position: absolute;
            top: 0;
            left: -80%;

            width: 50%;
            height: 100%;

            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.55), transparent);

            transform: skewX(-20deg);

            animation: hsSweep 3.6s ease-in-out infinite;

            pointer-events: none;
        }

        @keyframes hsSweep {
            0%        { left: -80%; }
            45%, 100% { left: 130%; }
        }

        .hs-note {
            margin: 26px 0 0;

            color: #9aa3af;

            font-size: 12.5px;
        }

        /* =========================================================
           CONFETTI (JS-spawned pieces)
        ========================================================= */

        .hs-confetti-layer {
            position: fixed;
            inset: 0;

            pointer-events: none;

            z-index: 500;

            overflow: hidden;
        }

        .hs-confetti {
            position: absolute;

            top: -12px;

            width: 9px;
            height: 14px;

            border-radius: 2px;

            opacity: 0.95;

            animation: hsFall linear forwards;
        }

        @keyframes hsFall {
            0%   { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(105vh) rotate(540deg); opacity: 0; }
        }

        /* =========================================================
           ENTRANCE (JS-gated)
        ========================================================= */

        @keyframes hsRise {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .js .hs-anim {
            opacity: 0;

            animation: hsRise 0.7s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) forwards;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 700px) {
            .hs-hero-inner {
                padding: 44px 18px 120px;
            }

            .hs-step h3 {
                font-size: 11.5px;
            }

            .hs-step p {
                display: none;
            }

            .hs-card {
                padding: 36px 22px 32px;
            }

            .hs-actions {
                flex-direction: column;
                width: 100%;
            }

            .hs-actions .hs-btn {
                width: 100%;
            }
        }

    </style>
</head>

<body class="rh-step4">

<?php
 $guestCtaHref = '/webprogg/host/becomeahost.php';
include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php';
?>


<!-- =========================================================
     HERO + STEPPER
========================================================= -->

<section class="hs-hero">

    <div aria-hidden="true">
        <span class="hs-blob hs-blob-1"></span>
        <span class="hs-blob hs-blob-2"></span>
    </div>

    <div class="hs-hero-inner">

        <span class="hs-eyebrow hs-anim" style="--d: .05s;">

            <span class="hs-pulse-dot"></span>

            Add Your Space &middot; Step 4 of 4

        </span>


        <h1 class="hs-anim" style="--d: .15s;">

            You're almost

            <span class="hs-shimmer">there!</span>

        </h1>


        <!-- =====================================================
             STEPPER — same continuous track + progress-fill
             pattern as steps 2 and 3. Steps 1-3 completed
             (moss + check tick), step 4 current (honey glow).
             The fill animates from 0 to 75% via JS.
        ====================================================== -->

        <div class="hs-stepper hs-anim" style="--d: .25s;">

            <div class="hs-stepper-track"></div>

            <div
                class="hs-stepper-fill"
                id="hsStepperFill"
                style="width: 0%;"
                data-progress="<?php echo (int) $stepProgress; ?>%"
            ></div>

            <div class="hs-steps">

                <?php foreach ($hostSteps as $step): ?>

                    <?php $isActive = ($step["number"] === $currentStep); ?>
                    <?php $isDone = ($step["number"] < $currentStep); ?>

                    <div class="hs-step<?php echo $isActive ? ' current' : ''; ?><?php echo $isDone ? ' completed' : ''; ?>">

                        <div class="hs-step-circle">

                            <img
                                src="<?php echo htmlspecialchars($step["icon"]); ?>"
                                alt="<?php echo htmlspecialchars($step["alt"]); ?>"
                            >

                            <span class="hs-step-number">

                                <?php echo $step["number"]; ?>

                            </span>

                        </div>


                        <h3><?php echo htmlspecialchars($step["title"]); ?></h3>

                        <p><?php echo htmlspecialchars($step["description"]); ?></p>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>


    <svg class="hs-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>


<!-- =========================================================
     SUCCESS CARD
========================================================= -->

<div class="hs-card-wrap">

    <div class="hs-confetti-layer" id="hsConfettiLayer" aria-hidden="true"></div>

    <section class="hs-card hs-anim" style="--d: .35s;">

        <?php if (!$isHostApproved): ?>

            <span class="hs-status-badge">

                <span class="hs-pulse-dot"></span>

                Awaiting Admin Approval

            </span>

        <?php endif; ?>


        <div class="hs-icon-wrap">

            <span class="hs-icon-dots" aria-hidden="true"></span>

            <span class="hs-ring" aria-hidden="true"></span>

            <span class="hs-ring hs-ring-2" aria-hidden="true"></span>

            <img
                src="/webprogg/images/SpaceSuccessfullyAddIcon.png"
                alt="Space Submitted"
                class="hs-success-icon"
            >

        </div>


        <?php if ($isHostApproved): ?>

            <h2>Space Added Successfully!</h2>

            <p class="hs-message">
                Your space has been added and is now awaiting approval.<br>
                You can manage your listing, update details,<br>
                and start receiving bookings once it's live.
            </p>

            <div class="hs-actions">

                <a href="/webprogg/host/hostprofile.php" class="hs-btn hs-btn-primary">
                    <img src="/webprogg/images/AddYourSpaceIcon-BecomeAHost.png" alt="">
                    <span>GO TO HOST PROFILE</span>
                </a>

                <a href="/webprogg/host/becomeahost.php" class="hs-btn hs-btn-secondary">
                    <img src="/webprogg/images/AddAnotherButtonIcon.png" alt="">
                    <span>ADD ANOTHER SPACE</span>
                </a>

            </div>

            <p class="hs-note">
                Continue to your host profile to manage your spaces.
            </p>


        <?php else: ?>

            <h2>Submitted for Review!</h2>

            <p class="hs-message">
                Your space and host application have been submitted.<br>
                An admin needs to review and approve your application<br>
                before you can access your Host Profile and manage listings.
            </p>

            <div class="hs-actions">

                <a href="/webprogg/user/userprofile.php" class="hs-btn hs-btn-primary">
                    <img src="/webprogg/images/AddYourSpaceIcon-BecomeAHost.png" alt="">
                    <span>GO TO MY PROFILE</span>
                </a>

            </div>

            <p class="hs-note">
                We'll let you know as soon as your host application is approved.
            </p>

        <?php endif; ?>

    </section>

</div>


<!-- =========================================================
     FOOTER — standard site-footer, same as steps 1-3
========================================================= -->

<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->

        <div class="footer-brand">

            <a href="/webprogg/index.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>

            <p class="footer-tagline">
                Your trusted platform for finding and listing
                quality living spaces — made simple, safe,
                and stress-free.
            </p>

        </div>

        <!-- LISTINGS -->

        <div class="footer-links">

            <span class="footer-heading">LISTINGS</span>

            <?php foreach ($listingCategories as $category => $type): ?>
                <a href="/webprogg/Listings/listing.php?type=<?php echo urlencode($type); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>

        </div>

        <!-- QUICK LINKS -->

        <div class="footer-links">

            <span class="footer-heading">QUICK LINKS</span>

            <?php foreach ($quickLinks as $name => $link): ?>
                <a href="<?php echo htmlspecialchars($link); ?>">
                    <?php echo htmlspecialchars($name); ?>
                </a>
            <?php endforeach; ?>

        </div>

        <!-- GET THE APP -->

        <div class="footer-contact">

            <span class="footer-heading">GET THE APP</span>

            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="Phone">
                <span>+63 927 569 3574</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="Email">
                <span>hello@roomhive.ph</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="Location">
                <span>Dumaguete City, Negros Oriental</span>
            </div>

        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> RoomHive. All rights reserved.</p>
    </div>

</footer>


<script src="/webprogg/assets/javaScript.js"></script>

<!-- =========================================================
     STEP 4 SCRIPT — stepper fill + confetti burst
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- Animate the stepper fill to its target width ---- */
    var fill = document.getElementById("hsStepperFill");

    if (fill) {
        var target = fill.getAttribute("data-progress") || "0%";

        if (reduced) {
            fill.style.width = target;
        } else {
            window.setTimeout(function () {
                fill.style.width = target;
            }, 500);
        }
    }

    /* ---- One-time confetti burst ---- */
    var layer = document.getElementById("hsConfettiLayer");

    if (layer && !reduced) {
        var colors = ["#eda423", "#f6c04e", "#2f9e5b", "#e2544c", "#1c2a38"];

        for (var i = 0; i < 18; i++) {
            (function (index) {
                var piece = document.createElement("span");

                piece.className = "hs-confetti";

                piece.style.left = (12 + Math.random() * 76) + "%";
                piece.style.background = colors[index % colors.length];

                piece.style.animationDuration = (1.9 + Math.random() * 1.1) + "s";
                piece.style.animationDelay = (0.5 + Math.random() * 0.7) + "s";

                piece.addEventListener("animationend", function () {
                    piece.remove();
                });

                layer.appendChild(piece);
            })(i);
        }
    }
})();
</script>

</body>
</html>