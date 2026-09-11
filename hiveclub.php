<?php

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';


// =====================================================
// ROOMHIVE - HIVE CLUB
// =====================================================


// =====================================================
// LOGIN STATUS
// =====================================================

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);


// =====================================================
// KEEP is_host IN SYNC WITH THE DATABASE
// $_SESSION['is_host'] is only set at login time, so if a
// host application gets approved mid-session, the flag goes
// stale and the nav keeps showing the wrong state. Re-check
// the real column on every load.
// =====================================================

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    $hostCheckStmt = $pdo->prepare("SELECT is_host, avatar_path FROM users WHERE id = :id LIMIT 1");
    $hostCheckStmt->execute(['id' => $_SESSION['user_id']]);
    $hostRow = $hostCheckStmt->fetch();
    $_SESSION['is_host'] = $hostRow ? (bool) $hostRow['is_host'] : false;
    $_SESSION['avatar_path'] = $hostRow['avatar_path'] ?? null;
}

/* Same staleness reasoning as is_host above: the navbar's
   account icon should reflect a freshly-uploaded profile photo
   without requiring the user to log out and back in. */
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';


// =====================================================
// CURRENT YEAR
// =====================================================

$currentYear = date("Y");


// =====================================================
// NAVIGATION
// =====================================================

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
// DEFAULT MEMBER INFORMATION
// =====================================================

$memberId = "RH " . date("Y") . " 0000";
$memberTier = "Bronze Member";
$memberPoints = 0;
$memberStatus = "none";

// True only once the user has actually joined Hive Club
// (has a hive_members row with an active status). This is
// what decides JOIN vs UPGRADE and HOW IT WORKS vs STOP
// SUBSCRIBE in the hero below.
$isHiveMember = false;


// =====================================================
// DEFAULT TIER INFORMATION
// =====================================================

$nextTier = "Gold";
$nextTierPoints = 5000;

$progress = 0;


// =====================================================
// GET LOGGED-IN USER ID
// =====================================================

$userId = $_SESSION["user_id"] ?? null;


// Some login systems may use "id" instead.
if (!$userId && isset($_SESSION["id"])) {
    $userId = $_SESSION["id"];
}


// =====================================================
// LOAD HIVE CLUB MEMBER
// =====================================================
//
// NOTE: this used to auto-create a Bronze row for every
// logged-in user the moment they visited this page, which
// meant everyone was instantly "a member" and the JOIN /
// UPGRADE distinction had no way to work. Membership rows
// are now only created when someone actually completes the
// join flow (via membership.php -> completepurchase.php),
// so this block just reads whatever is there.
// =====================================================

if ($isLoggedIn && $userId) {

    try {

        $memberQuery = $pdo->prepare("
            SELECT *
            FROM hive_members
            WHERE user_id = ?
            LIMIT 1
        ");

        $memberQuery->execute([$userId]);

        $member = $memberQuery->fetch();


        if ($member) {

            $memberId = $member["member_id"];

            $memberTier = $member["tier"] . " Member";

            $memberPoints = (int)$member["points"];

            $memberStatus = $member["membership_status"];

            $isHiveMember = ($memberStatus === "active");
        }


    } catch (PDOException $e) {

        error_log(
            "Hive Club database error: " .
            $e->getMessage()
        );

    }

}


// =====================================================
// DETERMINE NEXT TIER
// =====================================================

if ($memberPoints >= 25000) {

    $nextTier = "Platinum";
    $nextTierPoints = 25000;
    $progress = 100;

} elseif ($memberPoints >= 5000) {

    $nextTier = "Platinum";
    $nextTierPoints = 25000;

    $progress = (
        ($memberPoints - 5000) /
        20000
    ) * 100;

} else {

    $nextTier = "Gold";
    $nextTierPoints = 5000;

    $progress = (
        $memberPoints /
        5000
    ) * 100;
}


// Prevent invalid progress

if ($progress < 0) {
    $progress = 0;
}

if ($progress > 100) {
    $progress = 100;
}


// =====================================================
// JOIN / UPGRADE HIVE CLUB LINK
// =====================================================
//
// Both JOIN and UPGRADE point to the same plan-picker page.
// If the user isn't logged in yet, send them to login first
// and bring them back to Hive Club afterward.

$joinHiveClubLink = $isLoggedIn
    ? "/webprogg/user/membership.php"
    : "/webprogg/auth/loginform.php?redirect=" .
      urlencode("/webprogg/hiveclub.php");

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


    <!-- Poppins -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >

</head>


<body>


<!-- =====================================================
     NAVIGATION BAR
===================================================== -->

<header class="navbar">

    <!-- LOGO -->

    <div class="logo">

        <a
            href="<?php echo $isLoggedIn
                ? '/webprogg/user/usershome.php'
                : '/webprogg/index.php'; ?>"
        >

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive Logo"
            >

        </a>

    </div>


    <!-- NAVIGATION -->

    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>

            <a
                href="<?php echo htmlspecialchars($link); ?>"
                class="<?php echo (
                    $name === 'HIVE CLUB'
                ) ? 'active' : ''; ?>"
            >

                <?php echo htmlspecialchars($name); ?>

            </a>

        <?php endforeach; ?>


        <?php if ($isLoggedIn): ?>

            <!-- MY ACCOUNT -->

            <div class="account-dropdown">

                <button
                    type="button"
                    class="my-account"
                    id="accountDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    onclick="toggleAccountMenu()"
                >

                    <span class="account-circle">

                        <img
                            src="<?php echo htmlspecialchars($navAvatar); ?>"
                            alt="My Account"
                        >

                    </span>

                    <span>MY PROFILE</span>

                    <span class="dropdown-caret">
                        &#9662;
                    </span>

                </button>


                <div
                    class="account-dropdown-menu"
                    id="accountDropdownMenu"
                >

                    <a href="/webprogg/user/userprofile.php">
                        My Profile
                    </a>

                    <a href="/webprogg/auth/logout.php">
                        Logout
                    </a>

                </div>

            </div>


        <?php else: ?>


            <!-- LIST YOUR SPACE -->

            <a
                href="/webprogg/auth/loginform.php"
                class="list-space"
            >
                LIST YOUR SPACE
            </a>


        <?php endif; ?>

    </nav>

</header>


<?php if ($isLoggedIn): ?>

<style>

.account-dropdown {
    position: relative;
}

.account-dropdown .my-account {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    font: inherit;
    color: inherit;
}

.account-dropdown .dropdown-caret {
    font-size: 0.7em;
    transition: transform 0.15s ease;
}

.account-dropdown.open .dropdown-caret {
    transform: rotate(180deg);
}

.account-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 160px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    overflow: hidden;
    z-index: 100;
    margin-top: 8px;
}

.account-dropdown.open .account-dropdown-menu {
    display: block;
}

.account-dropdown-menu a {
    display: block;
    padding: 10px 16px;
    text-decoration: none;
    color: #333;
    white-space: nowrap;
}

.account-dropdown-menu a:hover {
    background: #f5f5f5;
}

</style>


<script>

function toggleAccountMenu() {

    const dropdown =
        document
        .getElementById("accountDropdownToggle")
        .closest(".account-dropdown");

    const toggle =
        document.getElementById("accountDropdownToggle");

    const isOpen =
        dropdown.classList.toggle("open");

    toggle.setAttribute(
        "aria-expanded",
        isOpen ? "true" : "false"
    );
}


document.addEventListener("click", function(event) {

    const dropdown =
        document.querySelector(".account-dropdown");

    if (
        dropdown &&
        !dropdown.contains(event.target)
    ) {

        dropdown.classList.remove("open");

        document
            .getElementById("accountDropdownToggle")
            .setAttribute(
                "aria-expanded",
                "false"
            );
    }

});

</script>

<?php endif; ?>


<?php if (isset($_GET["cancelled"])): ?>

    <div class="hive-notice">
        You've stopped your Hive Club subscription. You're
        welcome to rejoin anytime!
    </div>

<?php endif; ?>


<!-- =====================================================
     HIVE CLUB HERO
===================================================== -->

<section class="hive-hero">

    <div class="hero-pattern"></div>


    <!-- LEFT -->

    <div class="hive-hero-content">

        <h1>

            Welcome to<br>

            <span>Hive Club!</span>

        </h1>


        <p>

            Join our rewards club and enjoy exclusive perks,
            discounts, and special offers every time you stay.

        </p>


        <div class="hero-buttons">

            <?php if ($isHiveMember): ?>

                <!-- ALREADY A MEMBER -->

                <a
                    href="/webprogg/user/membership.php"
                    class="hero-btn primary"
                >

                    UPGRADE

                </a>


                <form
                    method="POST"
                    action="/webprogg/user/cancelmembership.php"
                    class="stop-subscribe-form"
                    onsubmit="return confirm(
                        'Stop your Hive Club subscription? ' +
                        'You will lose your current tier and perks.'
                    );"
                >

                    <button
                        type="submit"
                        class="hero-btn secondary"
                    >

                        STOP SUBSCRIBE

                    </button>

                </form>


            <?php else: ?>

                <!-- NOT YET A MEMBER -->

                <a
                    href="<?php echo htmlspecialchars(
                        $joinHiveClubLink
                    ); ?>"
                    class="hero-btn primary"
                >

                    JOIN HIVE CLUB

                </a>


                <a
                    href="#how-earn"
                    class="hero-btn secondary"
                >

                    HOW IT WORKS

                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- MEMBER CARD -->

    <div class="member-card-area">

        <img
            src="/webprogg/images/HiveCardIcon-HiveClub.png"
            class="member-card-image"
            alt="Hive Club Member Card"
        >

    </div>

</section>


<!-- =====================================================
     MEMBER BENEFITS
===================================================== -->

<section
    class="club-main"
    id="membership"
>


    <!-- LEFT -->

    <div class="club-left">


        <div class="section-heading">

            <h2>Member Benefits</h2>

            <p>
                The more you book, the more you earn.
            </p>

        </div>


        <div
            class="benefits-grid"
            id="benefits"
        >


            <!-- BENEFIT 1 -->

            <div class="benefit-card">

                <div class="benefit-icon">

                    <img
                        src="/webprogg/images/ExclusiveDiscountIcon-HiveClub.png"
                        alt="Exclusive Discounts"
                    >

                </div>

                <h3>
                    Exclusive Discounts
                </h3>

                <p>
                    Get up to 15% off on selected stays.
                </p>

            </div>


            <!-- BENEFIT 2 -->

            <div class="benefit-card">

                <div class="benefit-icon">

                    <img
                        src="/webprogg/images/SpecialOffersIcon-HiveClub.png"
                        alt="Special Offers"
                    >

                </div>

                <h3>
                    Special Offers
                </h3>

                <p>
                    Access members-only promotions and bundles.
                </p>

            </div>


            <!-- BENEFIT 3 -->

            <div class="benefit-card">

                <div class="benefit-icon">

                    <img
                        src="/webprogg/images/EarnPointIcon-HiveClub.png"
                        alt="Earn Points"
                    >

                </div>

                <h3>
                    Earn Points
                </h3>

                <p>
                    Earn points for every booking and redeem easy rewards.
                </p>

            </div>


            <!-- BENEFIT 4 -->

            <div class="benefit-card">

                <div class="benefit-icon">

                    <img
                        src="/webprogg/images/EarlyAccessIcon-HiveClub.png"
                        alt="Early Access"
                    >

                </div>

                <h3>
                    Early Access
                </h3>

                <p>
                    Be the first to know about new listings and deals.
                </p>

            </div>

        </div>


        <!-- =================================================
             TIER LEVELS
        ================================================== -->

        <div class="tier-heading">
            Tier Levels
        </div>


        <div class="tier-grid">


            <!-- BRONZE -->

            <div
                class="tier-card bronze
                <?php echo (
                    $memberTier === 'Bronze Member'
                    && $isHiveMember
                ) ? 'current' : ''; ?>"
            >

                <?php if (
                    $memberTier === 'Bronze Member'
                    && $isHiveMember
                ): ?>

                    <span class="current-badge">
                        CURRENT TIER
                    </span>

                <?php endif; ?>


                <div class="tier-icon">

                    <img
                        src="/webprogg/images/BronzeIcon-HiveClub.png"
                        alt="Bronze"
                    >

                </div>


                <div class="tier-info">

                    <h3>Bronze</h3>

                    <strong>
                        0 - 4,999 pts
                    </strong>

                    <ul>

                        <li>
                            5% off on stays
                        </li>

                        <li>
                            Member-only offers
                        </li>

                    </ul>

                </div>

            </div>


            <!-- GOLD -->

            <div
                class="tier-card gold
                <?php echo (
                    $memberTier === 'Gold Member'
                    && $isHiveMember
                ) ? 'current' : ''; ?>"
            >

                <?php if (
                    $memberTier === 'Gold Member'
                    && $isHiveMember
                ): ?>

                    <span class="current-badge">
                        CURRENT TIER
                    </span>

                <?php endif; ?>


                <div class="tier-icon">

                    <img
                        src="/webprogg/images/GoldIcon-HiveClub.png"
                        alt="Gold"
                    >

                </div>


                <div class="tier-info">

                    <h3>Gold</h3>

                    <strong>
                        5,000 - 24,999 pts
                    </strong>

                    <ul>

                        <li>
                            10% off on stays
                        </li>

                        <li>
                            Priority customer support
                        </li>

                        <li>
                            Early access to promos
                        </li>

                    </ul>

                </div>

            </div>


            <!-- PLATINUM -->

            <div
                class="tier-card platinum
                <?php echo (
                    $memberTier === 'Platinum Member'
                    && $isHiveMember
                ) ? 'current' : ''; ?>"
            >

                <?php if (
                    $memberTier === 'Platinum Member'
                    && $isHiveMember
                ): ?>

                    <span class="current-badge">
                        CURRENT TIER
                    </span>

                <?php endif; ?>


                <div class="tier-icon">

                    <img
                        src="/webprogg/images/PlatinumIcon-HiveClub.png"
                        alt="Platinum"
                    >

                </div>


                <div class="tier-info">

                    <h3>Platinum</h3>

                    <strong>
                        25,000+ pts
                    </strong>

                    <ul>

                        <li>
                            15% off on stays
                        </li>

                        <li>
                            Free upgrades
                            (subject to availability)
                        </li>

                        <li>
                            VIP deals &amp; exclusive perks
                        </li>

                    </ul>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <aside class="club-sidebar">


        <!-- STATUS -->

        <div class="status-card">

            <h2>
                Your Hive Club Status
            </h2>


            <?php if ($isHiveMember): ?>

            <div class="status-profile">

                <div class="status-medal">

                    <?php

                    $statusIcon =
                        "/webprogg/images/BronzeIcon-HiveClub.png";

                    if ($memberTier === "Gold Member") {

                        $statusIcon =
                            "/webprogg/images/GoldIcon-HiveClub.png";

                    } elseif (
                        $memberTier === "Platinum Member"
                    ) {

                        $statusIcon =
                            "/webprogg/images/PlatinumIcon-HiveClub.png";

                    }

                    ?>

                    <img
                        src="<?php echo $statusIcon; ?>"
                        alt="<?php echo htmlspecialchars(
                            $memberTier
                        ); ?>"
                    >

                </div>


                <div>

                    <h3>
                        <?php echo htmlspecialchars(
                            $memberTier
                        ); ?>
                    </h3>


                    <strong>

                        <?php echo number_format(
                            $memberPoints
                        ); ?>

                        Points

                    </strong>


                    <p>

                        Member ID:
                        <?php echo htmlspecialchars(
                            $memberId
                        ); ?>

                    </p>

                </div>

            </div>


            <!-- PROGRESS -->

            <div class="progress-bar">

                <div
                    class="progress-fill"
                    style="width:
                        <?php echo $progress; ?>%;"
                ></div>

            </div>


            <div class="progress-labels">

                <span>

                    Next Tier:
                    <?php echo $nextTier; ?>

                </span>


                <span>

                    <?php echo number_format(
                        $nextTierPoints
                    ); ?>

                    pts

                </span>

            </div>


            <a
                href="#benefits"
                class="rewards-button"
            >

                VIEW MY REWARDS

            </a>

            <?php else: ?>

            <p class="not-a-member-note">

                You haven't joined Hive Club yet. Join now to
                start earning points and unlocking perks.

            </p>


            <a
                href="<?php echo htmlspecialchars(
                    $joinHiveClubLink
                ); ?>"
                class="rewards-button"
            >

                JOIN HIVE CLUB

            </a>

            <?php endif; ?>

        </div>


        <!-- =================================================
             HOW TO EARN
        ================================================== -->

        <div
            class="earn-card"
            id="how-earn"
        >

            <h2>
                How to Earn Points
            </h2>


            <div class="earn-item">

                <span class="earn-icon">
                    ♙
                </span>

                <div>

                    <strong>
                        Book a stay
                    </strong>

                    <p>
                        Earn 10 points per ₱100 spent
                    </p>

                </div>

            </div>


            <div class="earn-item">

                <span class="earn-icon">
                    ☆
                </span>

                <div>

                    <strong>
                        Write a review
                    </strong>

                    <p>
                        Earn 50 points
                    </p>

                </div>

            </div>


            <div class="earn-item">

                <span class="earn-icon">
                    ♔
                </span>

                <div>

                    <strong>
                        Refer a friend
                    </strong>

                    <p>
                        Earn 200 points
                    </p>

                </div>

            </div>


            <div class="earn-item">

                <span class="earn-icon">
                    ◎
                </span>

                <div>

                    <strong>
                        Stay more, earn more!
                    </strong>

                    <p>
                        Get bonus points for longer stays
                    </p>

                </div>

            </div>

        </div>

    </aside>

</section>


<!-- =====================================================
     BOOKING CTA
===================================================== -->

<section class="booking-cta">

    <div class="booking-text">

        <span class="booking-icon">
            ♛
        </span>

        <div>

            <h2>
                More stays. More points. More perks.
            </h2>

            <p>
                Thank you for being part of the RoomHive community!
            </p>

        </div>

    </div>


    <a
        href="/webprogg/Listings/listing.php"
        class="booking-button"
    >

        START BOOKING NOW

    </a>

</section>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="site-footer">

    <div class="footer-top">


        <!-- BRAND -->

        <div class="footer-brand">

            <a
                href="<?php echo $isLoggedIn
                    ? '/webprogg/user/usershome.php'
                    : '/webprogg/index.php'; ?>"
            >

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                    class="footer-logo"
                >

            </a>


            <p class="footer-tagline">

                Your trusted platform for finding and listing
                quality living spaces — made simple, safe,
                and stress-free.

            </p>

        </div>


        <!-- LISTINGS -->

        <div class="footer-links">

            <span class="footer-heading">
                LISTINGS
            </span>


            <a href="/webprogg/Listings/listing.php?type=shared-bedroom">
                Shared Bedroom
            </a>

            <a href="/webprogg/Listings/listing.php?type=private-room">
                Private Room
            </a>

            <a href="/webprogg/Listings/listing.php?type=entire-house">
                Entire House
            </a>

            <a href="/webprogg/Listings/listing.php?type=boarding-house">
                Boarding House
            </a>

            <a href="/webprogg/Listings/listing.php?type=studio-loft">
                Studio Loft
            </a>

        </div>


        <!-- QUICK LINKS -->

        <div class="footer-links">

            <span class="footer-heading">
                QUICK LINKS
            </span>


            <a href="/webprogg/index.php">
                About Us
            </a>

            <a href="/webprogg/host/howitworks.php">
                How It Works
            </a>

            <a href="/webprogg/host/becomeahost.php">
                Become a Host
            </a>

            <a
                href="/webprogg/hiveclub.php"
                class="active"
            >
                Hive Club
            </a>

            <a href="/webprogg/misc/contacts.php">
                Contacts
            </a>

        </div>


        <!-- CONTACT -->

        <div class="footer-contact">

            <span class="footer-heading">
                GET THE APP
            </span>


            <div class="footer-app-badges">

                <img
                    src="/webprogg/images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >

                <img
                    src="/webprogg/images/AppStore.jpg"
                    alt="Download on the App Store"
                >

            </div>


            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt="Phone"
                >

                <span>
                    +63 927 569 3574
                </span>

            </div>


            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt="Email"
                >

                <span>
                    hello@roomhive.ph
                </span>

            </div>


            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/GPSIcon.png"
                    alt="Location"
                >

                <span>
                    Bacolod City, Negros Occidental
                </span>

            </div>

        </div>

    </div>

    <div class="footer-bottom">

        <p>

            &copy;
            <?php echo $currentYear; ?>

            RoomHive.
            All rights reserved.

        </p>

    </div>

</footer>


</body>
</html>