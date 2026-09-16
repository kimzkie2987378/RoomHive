<?php
/* =========================================================
   ROOMHIVE — SHARED USER NAVBAR (partial include)
   includes/usernav.php

   One navbar for every tenant / "My Account" page.

   ANIMATED VERSION:
     - Navbar drops in from the top on page load
     - Gold underline grows on link hover
     - Dropdown menu items cascade in with a stagger
     - Bell swings on hover, badge pulses when unread
     - Avatar lifts slightly on hover
     - Navbar gains a soft shadow once the page scrolls

   All motion is disabled automatically for users with
   "prefers-reduced-motion" enabled.

   The page MUST set these BEFORE requiring this file:
     $dbUser             — row from users (needs is_host)
     $navAvatar          — resolved avatar URL
     $notification_count — integer (0 is fine)

   Safe defaults are applied so this include can never
   white-screen a page.
========================================================= */

if (!isset($navAvatar) || !is_string($navAvatar) || $navAvatar === '') {
    $navAvatar = '/webprogg/images/default-avatar.png';
}

if (!isset($notification_count) || !is_numeric($notification_count)) {
    $notification_count = 0;
}

 $userIsHost = !empty($dbUser['is_host']);
?>

<style>
    /* =====================================================
       ANIMATIONS — all scoped to this navbar include.
       Theme gold: #b07708 / hover wash: #fdf1dc
    ====================================================== */

    /* -----------------------------------------------
       1. NAVBAR DROP-IN (page load)
       Slides the whole fixed navbar down from the top.
    ------------------------------------------------ */
    @keyframes navbarDropIn {
        from { opacity: 0; transform: translateY(-100%); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .navbar {
        animation: navbarDropIn 0.5s cubic-bezier(0.22, 0.68, 0.43, 1) both;

        /* Soft shadow fades in once the page scrolls (see .nav-scrolled) */
        transition: box-shadow 0.3s ease;
    }

    .navbar.nav-scrolled {
        box-shadow: 0 8px 24px rgba(28, 42, 56, 0.10);
    }

    /* -----------------------------------------------
       2. LINK HOVER — gold underline grows from center
       Applies to the plain text links only (HOME,
       LISTINGS, ...). Bell + dropdown are excluded.
    ------------------------------------------------ */
    .navbar .nav-links > a:not(.nav-bell) {
        position: relative;
    }

    .navbar .nav-links > a:not(.nav-bell)::after {
        content: "";

        position: absolute;
        left: 50%;
        bottom: -5px;

        transform: translateX(-50%);

        width: 0;
        height: 2px;

        background: #b07708;
        border-radius: 2px;

        transition: width 0.25s ease;
    }

    .navbar .nav-links > a:not(.nav-bell):hover::after {
        width: calc(100% - 4px);
    }

    /* -----------------------------------------------
       3. BELL — swings on hover, badge pulses when unread
    ------------------------------------------------ */
    .navbar .nav-bell img {
        transform-origin: top center;
    }

    @keyframes bellSwing {
        0%   { transform: rotate(0deg); }
        15%  { transform: rotate(14deg); }
        30%  { transform: rotate(-11deg); }
        45%  { transform: rotate(8deg); }
        60%  { transform: rotate(-5deg); }
        75%  { transform: rotate(2deg); }
        100% { transform: rotate(0deg); }
    }

    .navbar .nav-bell:hover img {
        animation: bellSwing 0.7s ease;
    }

    @keyframes badgePulse {
        0%, 100% { transform: scale(1); }
        50%      { transform: scale(1.18); }
    }

    .navbar .nav-bell-badge {
        animation: badgePulse 2s ease-in-out infinite;
    }

    /* -----------------------------------------------
       4. DROPDOWN — items cascade in with a stagger
       Menu opens (slide + fade) as before, then each
       link follows 40ms apart, sliding in from the right.
    ------------------------------------------------ */
    .user-dd {
        position: relative;
    }

    .user-dd .my-account {
        display: flex;
        align-items: center;

        gap: 8px;

        background: none;
        border: none;

        cursor: pointer;

        font: inherit;
        color: inherit;
    }

    .user-dd .account-circle {
        display: inline-flex;

        transition: transform 0.2s ease;
    }

    .user-dd .account-circle img {
        width: 42px;
        height: 42px;

        padding: 5px;

        background: #1c2a38;

        border-radius: 50%;

        object-fit: contain;

        display: block;

        transition: box-shadow 0.2s ease;
    }

    /* Avatar lifts + gets a gold ring on hover */
    .user-dd .my-account:hover .account-circle {
        transform: translateY(-1px);
    }

    .user-dd .my-account:hover .account-circle img {
        box-shadow: 0 0 0 3px rgba(176, 119, 8, 0.30);
    }

    .user-dd .my-account span:not(.account-circle) {
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;

        color: #1c2a38;
    }

    .user-dd .dropdown-caret {
        font-size: 0.75em;

        transition: transform 0.25s ease;
    }

    .user-dd.open .dropdown-caret {
        transform: rotate(180deg);
    }

    .user-dd-menu {
        display: none;

        position: absolute;

        top: calc(100% + 10px);
        right: 0;

        min-width: 190px;

        padding: 6px;

        background: #ffffff;

        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 12px;

        box-shadow: 0 14px 30px rgba(28, 42, 56, 0.14);

        flex-direction: column;

        z-index: 1100;

        transform-origin: top right;
    }

    @keyframes userDDIn {
        from { opacity: 0; transform: translateY(-6px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .user-dd.open .user-dd-menu {
        display: flex;

        animation: userDDIn 0.2s ease;
    }

    @keyframes userDDItem {
        from { opacity: 0; transform: translateX(10px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    /* Items start hidden, then cascade in only while open */
    .user-dd .user-dd-menu a {
        display: block;

        padding: 10px 12px;

        border-radius: 8px;

        color: #1c2a38;

        font-size: 13px;
        font-weight: 600;

        text-decoration: none;
        white-space: nowrap;

        opacity: 0;

        transition: background 0.15s ease, color 0.15s ease, padding-left 0.15s ease;
    }

    .user-dd.open .user-dd-menu a {
        animation: userDDItem 0.28s ease forwards;
    }

    /* Stagger: each item trails the previous by 40ms */
    .user-dd.open .user-dd-menu a:nth-child(1) { animation-delay: 0.04s; }
    .user-dd.open .user-dd-menu a:nth-child(2) { animation-delay: 0.08s; }
    .user-dd.open .user-dd-menu a:nth-child(3) { animation-delay: 0.12s; }
    .user-dd.open .user-dd-menu a:nth-child(4) { animation-delay: 0.16s; }
    .user-dd.open .user-dd-menu a:nth-child(5) { animation-delay: 0.20s; }
    .user-dd.open .user-dd-menu a:nth-child(6) { animation-delay: 0.24s; }

    /* Hover: gold wash + slight indent nudge */
    .user-dd-menu a:hover {
        background: #fdf1dc;
        color: #b07708;
        padding-left: 16px;
    }

    /* -----------------------------------------------
       5. REDUCED MOTION — turn everything off
    ------------------------------------------------ */
    @media (prefers-reduced-motion: reduce) {
        .navbar,
        .navbar .nav-bell-badge,
        .navbar .nav-bell:hover img,
        .user-dd-menu,
        .user-dd .user-dd-menu a {
            animation: none !important;
        }

        .user-dd .user-dd-menu a {
            opacity: 1 !important;
        }

        .navbar .nav-links > a:not(.nav-bell)::after,
        .user-dd .account-circle,
        .user-dd .account-circle img,
        .user-dd .dropdown-caret,
        .user-dd-menu a {
            transition: none !important;
        }
    }
</style>

<!-- NAVBAR -->
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

        <!-- NOTIFICATIONS BELL -->
        <a href="/webprogg/user/notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <!-- MY ACCOUNT DROPDOWN — self-contained, same pattern as host navbar -->
        <div class="user-dd">

            <button
                type="button"
                class="my-account user-dd-toggle"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <span class="account-circle">
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                </span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="user-dd-menu">

                <?php if ($userIsHost): ?>
                    <a href="/webprogg/host/hostprofile.php">
                        Host Profile
                    </a>
                <?php endif; ?>

                <a href="/webprogg/user/userprofile.php">
                    My Profile
                </a>

                <a href="/webprogg/user/editprofile.php">
                    Profile Settings
                </a>

                <a href="/webprogg/user/usermessages.php">
                    Messages
                </a>

                <a href="/webprogg/auth/logout.php">
                    Logout
                </a>

            </div>

        </div>

    </nav>

</header>

<!-- NOTIFICATION DROPDOWN PANEL (shared with the bell) -->
<?php
 $__notifDropdown = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php';
if (is_file($__notifDropdown)) {
    include $__notifDropdown;
}
?>

<!-- Dropdown toggle + scroll shadow — self-contained, guarded -->
<script>
(function () {
    "use strict";

    if (window.__userNavDD) { return; }
    window.__userNavDD = true;

    /* ---- Dropdown open/close (unchanged behavior) ---- */
    document.addEventListener("click", function (event) {

        var toggle = event.target.closest
            ? event.target.closest(".user-dd-toggle")
            : null;

        if (toggle) {
            var dd = toggle.closest(".user-dd");

            if (dd) {
                var isOpen = dd.classList.toggle("open");
                toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            }

            return;
        }

        document.querySelectorAll(".user-dd.open").forEach(function (dd) {
            dd.classList.remove("open");
            var btn = dd.querySelector(".user-dd-toggle");
            if (btn) { btn.setAttribute("aria-expanded", "false"); }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            document.querySelectorAll(".user-dd.open").forEach(function (dd) {
                dd.classList.remove("open");
                var btn = dd.querySelector(".user-dd-toggle");
                if (btn) { btn.setAttribute("aria-expanded", "false"); }
            });
        }
    });

    /* ---- NEW: scroll shadow — navbar lifts off the page ---- */
    var nav = document.querySelector(".navbar");

    if (nav) {
        var onScroll = function () {
            nav.classList.toggle("nav-scrolled", window.scrollY > 8);
        };

        window.addEventListener("scroll", onScroll, { passive: true });
        onScroll();
    }
})();
</script>