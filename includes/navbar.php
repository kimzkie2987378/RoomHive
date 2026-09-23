<?php
/* =========================================================
   ROOMHIVE — SHARED NAVIGATION HEADER
   /webprogg/includes/navbar.php

   Same design as host_navbar.php: 42px avatar, 12px bold
   MY PROFILE label, white rounded dropdown with honey hover.

   Dropdown links are ROLE-AWARE:
     - Host     → Host Dashboard (hostprofile.php)
     - Non-host → User Profile (userprofile.php)
     - Both     → Profile Settings + Messages (each side's
                  own inbox) + Logout

   HOME + LOGO (CHANGED):
     - Guest            → /webprogg/index.php (public landing)
     - Logged-in (ANY)  → /webprogg/user/usershome.php
   The old role-crossing logic (hosts → hostprofile.php) was
   REMOVED. HOME now goes to userhome only, never to
   hostprofile.php or userprofile.php. A page can still
   override by setting $homeHref BEFORE including this file.

   Before including, set:
     $isLoggedIn (bool), $navigation (array),
     $currentPage (string)
   Optional (safe defaults):
     $navAvatar, $notification_count, $isHost,
     $logoHref, $homeHref, $guestCtaHref, $guestCtaLabel
========================================================= */

/* Fallback: derive login state from the session if the page
   forgot to set it (fixes the "guest navbar on auth-gated
   pages" bug). */
 $isLoggedIn = $isLoggedIn ?? (($_SESSION['logged_in'] ?? false) === true);

/* Derive host flag from the session as a fallback too, so
   role-aware dropdown links work even on pages that never
   set $isHost. */
 $isHost = $isHost ?? false;
if (!$isHost && (($_SESSION['is_host'] ?? false) === true)) {
    $isHost = true;
}

 $navigation  = $navigation  ?? [];
 $currentPage = $currentPage ?? '';

/* =========================================================
   HOME LINK (CHANGED — no more role crossing)
   Logged-in users ALWAYS go to userhome. Guests get the
   public landing page. Override per page via $homeHref.
========================================================= */
 $navHomeHref = '/webprogg/index.php';
if ($isLoggedIn) {
    $navHomeHref = '/webprogg/user/usershome.php';
}
/* Optional per-page override (wins over the default) */
if (isset($homeHref) && is_string($homeHref) && $homeHref !== '') {
    $navHomeHref = $homeHref;
}

/* Logo follows HOME unless the page explicitly set one */
 $logoHref = $logoHref ?? $navHomeHref;

 $guestCtaHref  = $guestCtaHref  ?? '/webprogg/auth/loginform.php';
 $guestCtaLabel = $guestCtaLabel ?? 'LIST YOUR SPACE';

 $navAvatar          = $navAvatar          ?? '/webprogg/images/default-avatar.png';
 $notification_count = $notification_count ?? 0;

/* ---- Role-aware dropdown links (dropdown unchanged) ---- */
 $messagesHref = $isHost
    ? '/webprogg/host/hostmessages.php'
    : '/webprogg/user/usermessages.php';

 $profileHref  = $isHost
    ? '/webprogg/host/hostprofile.php'
    : '/webprogg/user/userprofile.php';

 $profileLabel = $isHost ? 'Host Dashboard' : 'User Profile';

 $settingsHref = $isHost
    ? '/webprogg/host/hosteditprofile.php'
    : '/webprogg/user/editprofile.php';
?>

<style>
    /* =====================================================
       SHARED NAVBAR — matches host_navbar.php's design.
       Scoped under header.navbar.
    ====================================================== */

    header.navbar .account-dd {
        position: relative;
    }

    header.navbar .account-dd .my-account {
        display: flex;
        align-items: center;

        gap: 8px;

        background: none;
        border: none;

        cursor: pointer;

        font: inherit;
        color: inherit;
    }

    header.navbar .account-dd .account-circle img {
        width: 42px;
        height: 42px;

        padding: 5px;

        background: #1c2a38;

        border-radius: 50%;

        object-fit: contain;

        display: block;
    }

    header.navbar .account-dd .my-account span:not(.account-circle) {
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;

        color: #1c2a38;
    }

    header.navbar .account-dd .dropdown-caret {
        font-size: 0.75em;

        transition: transform 0.15s ease;
    }

    header.navbar .account-dd.open .dropdown-caret {
        transform: rotate(180deg);
    }

    header.navbar .account-dd-menu {
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
    }

    header.navbar .account-dd.open .account-dd-menu {
        display: flex;

        animation: sharedDDIn 0.2s ease;
    }

    @keyframes sharedDDIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    header.navbar .account-dd-menu a {
        display: block;

        padding: 10px 12px;

        border-radius: 8px;

        color: #1c2a38;

        font-size: 13px;
        font-weight: 600;

        text-decoration: none;
        white-space: nowrap;

        transition: 0.15s ease;
    }

    header.navbar .account-dd-menu a:hover {
        background: #fdf1dc;
        color: #b07708;
    }

    /* Never clip the open menu */
    header.navbar,
    header.navbar .nav-links {
        overflow: visible;
    }

    /* =====================================================
       NAV LINK — sliding underline
    ====================================================== */

    header.navbar .nav-links a {
        position: relative;
        padding-bottom: 6px;
        color: #1c1c1c;
        transition: color 0.2s ease;
    }

    header.navbar .nav-links a::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: 0;
        height: 2px;
        width: 100%;
        background: #dd930f;
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }

    header.navbar .nav-links a:hover,
    header.navbar .nav-links a.active {
        color: #dd930f;
    }

    header.navbar .nav-links a:hover::after,
    header.navbar .nav-links a.active::after {
        transform: scaleX(1);
    }

    header.navbar .nav-links a.list-space::after,
    header.navbar .account-dd .my-account::after {
        display: none;
    }
</style>

<header class="navbar">

    <!-- LOGO — same target as HOME (userhome for logged-in) -->
    <a href="<?php echo htmlspecialchars($logoHref); ?>" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION -->
    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>
            <?php
                /* HOME is always forced to the fixed target
                   (userhome for logged-in users), regardless of
                   what the page's $navigation array hardcoded. */
                $effectiveLink = $link;
                if (strcasecmp((string) $name, 'HOME') === 0) {
                    $effectiveLink = $navHomeHref;
                }
            ?>
            <a
                href="<?php echo htmlspecialchars($effectiveLink); ?>"
                class="<?php echo ($effectiveLink === $currentPage) ? 'active' : ''; ?>"
            >
                <?php echo htmlspecialchars($name); ?>
            </a>
        <?php endforeach; ?>

        <?php if ($isLoggedIn): ?>

            <!-- NOTIFICATIONS -->
            <a href="/webprogg/user/notifications.php" class="nav-bell">
                <img src="/webprogg/images/bellicon.png" alt="Notifications">
                <?php if ($notification_count > 0): ?>
                    <span class="nav-bell-badge"><?php echo htmlspecialchars($notification_count); ?></span>
                <?php endif; ?>
            </a>

            <!-- MY ACCOUNT — role-aware dropdown -->
            <div class="account-dd">

                <button
                    type="button"
                    class="my-account account-dd-toggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span class="account-circle">
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dd-menu">

                    <?php if ($isHost): ?>
                        <a href="/webprogg/host/hostprofile.php">
                            Host Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/webprogg/user/userprofile.php">
                            User Profile
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo htmlspecialchars($settingsHref); ?>">
                        Profile Settings
                    </a>

                    <a href="<?php echo htmlspecialchars($messagesHref); ?>">
                        Messages
                    </a>

                    <a href="/webprogg/auth/logout.php">
                        Logout
                    </a>

                </div>

            </div>

        <?php else: ?>

            <!-- GUEST CTA -->
            <a href="<?php echo htmlspecialchars($guestCtaHref); ?>" class="list-space">
                <?php echo htmlspecialchars($guestCtaLabel); ?>
            </a>

        <?php endif; ?>

    </nav>

</header>

<!-- Shared dropdown script — self-contained, guarded -->
<script>
(function () {
    "use strict";

    if (window.__sharedNavDD) { return; }
    window.__sharedNavDD = true;

    document.addEventListener("click", function (event) {

        var toggle = event.target.closest
            ? event.target.closest(".account-dd-toggle")
            : null;

        if (toggle) {
            var dd = toggle.closest(".account-dd");

            if (dd) {
                var isOpen = dd.classList.toggle("open");
                toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            }

            return;
        }

        document.querySelectorAll(".account-dd.open").forEach(function (dd) {
            dd.classList.remove("open");
            var btn = dd.querySelector(".account-dd-toggle");
            if (btn) { btn.setAttribute("aria-expanded", "false"); }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            document.querySelectorAll(".account-dd.open").forEach(function (dd) {
                dd.classList.remove("open");
                var btn = dd.querySelector(".account-dd-toggle");
                if (btn) { btn.setAttribute("aria-expanded", "false"); }
            });
        }
    });
})();
</script>