<?php
/* =========================================================
   ROOMHIVE — SHARED NAVIGATION HEADER (SELF-CONTAINED)
   /webprogg/includes/navbar.php

   HOW TO USE
   ----------
   Before including this file, set:

     $isLoggedIn      (bool)  required
     $navigation      array   ["LABEL" => "/url", ...]  required
     $currentPage     string  the URL that should be marked active

   Optional — safe defaults are applied below, so the navbar
   can never crash a page that forgot one:

     $navAvatar            string  avatar URL (logged in)
     $notification_count   int     unread notification count
     $isHost               bool    adds "Host Profile" link
     $logoHref             string
     $guestCtaHref         string
     $guestCtaLabel        string

   Then:
     include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php';
   ========================================================= */

 $isLoggedIn = $isLoggedIn ?? false;

 $navigation  = $navigation  ?? [];
 $currentPage = $currentPage ?? '';

 $isHost        = $isHost        ?? false;
 $logoHref      = $logoHref      ?? ($isLoggedIn ? '/webprogg/user/usershome.php' : '/webprogg/index.php');
 $guestCtaHref  = $guestCtaHref  ?? '/webprogg/auth/loginform.php';
 $guestCtaLabel = $guestCtaLabel ?? 'LIST YOUR SPACE';

/* These two caused the "Undefined variable" warnings before —
   they are now ALWAYS defined, even for guests. */
 $navAvatar          = $navAvatar          ?? '/webprogg/images/default-avatar.png';
 $notification_count = $notification_count ?? 0;
?>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<style>
    /* =====================================================
       ACCOUNT DROPDOWN CSS
       Lives INSIDE navbar.php so EVERY page gets it for
       free. Scoped under header.navbar so it wins over
       style.css / myaccount.css.
    ====================================================== */

    header.navbar .account-dropdown {
        position: relative;
    }

    header.navbar .account-dropdown .my-account {
        display: flex;
        align-items: center;
        gap: 6px;
        background: none;
        border: none;
        cursor: pointer;
        font: inherit;
        color: inherit;
    }

    header.navbar .account-dropdown .dropdown-caret {
        font-size: 0.7em;
        transition: transform 0.15s ease;
    }

    header.navbar .account-dropdown.open .dropdown-caret {
        transform: rotate(180deg);
    }

    header.navbar .account-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        min-width: 160px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        z-index: 9999;
        margin-top: 8px;
    }

    header.navbar .account-dropdown.open .account-dropdown-menu {
        display: block;
    }

    header.navbar .account-dropdown-menu a {
        display: block;
        padding: 10px 16px;
        text-decoration: none;
        color: #333;
        white-space: nowrap;
    }

    header.navbar .account-dropdown-menu a:hover {
        background: #f5f5f5;
    }

    /* Insurance: if style.css ever sets overflow:hidden on the
       navbar or .nav-links, the open menu would be clipped and
       look "broken" even though the JS works. Force visible. */
    header.navbar,
    header.navbar .nav-links {
        overflow: visible;
    }
</style>

<header class="navbar">

    <!-- LOGO -->
    <a href="<?php echo htmlspecialchars($logoHref); ?>" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <!-- NAVIGATION -->
    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>
            <a
                href="<?php echo htmlspecialchars($link); ?>"
                class="<?php echo ($link === $currentPage) ? 'active' : ''; ?>"
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

            <!-- MY ACCOUNT -->
            <div class="account-dropdown" id="accountDropdown">

                <button
                    type="button"
                    class="my-account"
                    id="accountDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span class="account-circle">
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">
                    <?php if ($isHost): ?>
                        <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                    <?php endif; ?>
                    <a href="/webprogg/user/userprofile.php">My Profile</a>
                    <a href="/webprogg/auth/logout.php">Logout</a>
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

<?php if ($isLoggedIn): ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var toggle = document.getElementById('accountDropdownToggle');

    /* Guard: element missing, or already bound by a leftover
       script on the page (double-binding makes ONE click
       toggle the menu open AND closed, so it looks dead). */
    if (!toggle || toggle.dataset.dropdownBound) {
        return;
    }

    toggle.dataset.dropdownBound = '1';

    var dropdown = toggle.closest('.account-dropdown');
    if (!dropdown) {
        return;
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();

        var isOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>

<?php endif; ?>