<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   sidebar.php  (Lucide icons + collapsible, v2 spacing)

   USAGE: set $activeSidebar before including:
       $activeSidebar = 'profile';
       require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';

   v2 CHANGES:
   - Collapsed: icons are perfectly CENTERED using
     justify-content:center (robust against any padding the
     host stylesheet adds — no width math needed).
   - Expanded: spacing fully controlled by variables —
     even gap between links, consistent icon↔label gap,
     legacy margins normalized to 0.
========================================================= */

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

 $sidebarLinks = [
    'overview'      => ['/webprogg/user/userprofile.php',              'layout-dashboard',  'Overview'],
    'bookings'      => ['/webprogg/booking/userbookings.php',          'calendar-check',    'My Bookings'],
    'wishlist'      => ['/webprogg/user/userwishlist.php',             'heart',             'Wishlist'],
    'payments'      => ['/webprogg/user/userpayments.php',             'credit-card',       'Payments'],
    'reviews'       => ['/webprogg/user/userreviews.php',              'star',              'Reviews'],
    'messages'      => ['/webprogg/user/usermessages.php',             'message-square',    'Messages'],
    'notifcenter'   => ['/webprogg/user/notifications.php',            'bell',              'Notifications'],
    'profile'       => ['/webprogg/user/editprofile.php',              'user-round',        'Profile &amp; Account'],
    'security'      => ['/webprogg/user/security.php',                 'settings',          'Settings'],
    'notifications' => ['/webprogg/user/usernotificationsettings.php', 'bell-ring',         'Notification Settings'],
    'savedsearches' => ['/webprogg/user/savedsearches.php',            'search',            'Saved Searches'],
    'help'          => ['/webprogg/user/helpcenter.php',               'circle-help',       'Help Center'],
];
?>

<style>
/* =========================================================
   Collapsible account sidebar — this block fully owns the
   sidebar's layout & spacing. Load it AFTER your old
   userprofile stylesheet so these rules win.
   ========================================================= */
.up-sidebar {
    /* ---- spacing knobs — tweak these only ---- */
    --up-w: 240px;             /* expanded width */
    --up-w-collapsed: 66px;    /* collapsed width */
    --up-icon: 18px;           /* icon size */
    --up-gap: 12px;            /* gap: icon ↔ label (expanded) */
    --up-link-pad-y: 10px;     /* vertical padding per link */
    --up-link-pad-x: 14px;     /* horizontal padding per link */
    --up-link-gap: 4px;        /* vertical space BETWEEN links */
    --up-side-pad: 10px;       /* sidebar's own inner padding */

    /* ---- flex column = perfectly even spacing between links ---- */
    display: flex;
    flex-direction: column;
    gap: var(--up-link-gap);

    width: var(--up-w);
    padding: 8px var(--up-side-pad);
    box-sizing: border-box;    /* width INCLUDES padding — keeps math sane */

    transition: width .3s ease;
    overflow: hidden;
}

/* ---- chevron toggle ------------------------------------ */
.up-side-toggle {
    display: flex;
    align-items: center;
    justify-content: flex-end;  /* arrow hugs the right edge (expanded) */
    width: 100%;
    padding: 6px 4px;
    margin: 0;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: inherit;
    font: inherit;
    cursor: pointer;
    transition: background .2s ease;
}

.up-side-toggle:hover {
    background: rgba(0, 0, 0, 0.06);
}

.up-side-toggle svg {
    width: 20px;
    height: 20px;
    transition: transform .3s ease;
}

/* ---- links — normalized, consistent ---------------------- */
.up-side-link {
    display: flex;
    align-items: center;
    gap: var(--up-gap);
    padding: var(--up-link-pad-y) var(--up-link-pad-x);
    margin: 0;                  /* kill any legacy margins */
    white-space: nowrap;
    transition: background .2s ease;
}

.up-side-link svg {
    width: var(--up-icon);
    height: var(--up-icon);
    flex-shrink: 0;
}

.up-side-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;    /* long labels clip cleanly */
}

/* ---- COLLAPSED state ------------------------------------ */
.up-sidebar.collapsed {
    width: var(--up-w-collapsed);
}

/* left arrow visually becomes a right arrow */
.up-sidebar.collapsed .up-side-toggle svg {
    transform: rotate(180deg);
}

.up-sidebar.collapsed .up-side-toggle {
    justify-content: center;
}

/* labels hidden; justify-content:center puts each icon
   exactly in the middle of the link — no math, always
   correct regardless of sidebar padding or scrollbar. */
.up-sidebar.collapsed .up-side-label {
    display: none;
}

.up-sidebar.collapsed .up-side-link {
    justify-content: center;
    gap: 0;
    padding-left: 0;
    padding-right: 0;
}

/* breathing room before Log Out */
.up-side-logout {
    margin-top: 10px;
}

@media (prefers-reduced-motion: reduce) {
    .up-sidebar,
    .up-side-toggle svg,
    .up-side-link {
        transition: none;
    }
}
</style>

<aside class="up-sidebar" id="upSidebar">

    <!-- Expand / Collapse toggle -->
    <button
        type="button"
        class="up-side-toggle"
        id="upSidebarToggle"
        aria-expanded="true"
        aria-label="Collapse sidebar"
    >
        <i data-lucide="chevron-left"></i>
    </button>

    <?php foreach ($sidebarLinks as $key => $link): ?>
        <a
            href="<?php echo h($link[0]); ?>"
            class="up-side-link<?php echo ($activeSidebar ?? '') === $key ? ' active' : ''; ?>"
        >
            <i data-lucide="<?php echo h($link[1]); ?>"></i>
            <span class="up-side-label"><?php echo $link[2]; /* pre-escaped literal */ ?></span>
        </a>
    <?php endforeach; ?>

    <a href="/webprogg/auth/logout.php" class="up-side-link up-side-logout">
        <i data-lucide="log-out"></i>
        <span class="up-side-label">Log Out</span>
    </a>

</aside>

<script>
(function () {
    var sidebar = document.getElementById("upSidebar");
    var toggle  = document.getElementById("upSidebarToggle");
    if (!sidebar || !toggle) { return; }

    var STORAGE_KEY = "roomhive_sidebar_collapsed";
    var links = sidebar.querySelectorAll(".up-side-link");

    /* Collapsed mode: hovering a bare icon shows its name as a
       tooltip. Expanded mode: no tooltips (labels are visible). */
    function updateTitles(collapsed) {
        links.forEach(function (link) {
            var label = link.querySelector(".up-side-label");
            if (collapsed) {
                link.setAttribute("title", label ? label.textContent : "");
            } else {
                link.removeAttribute("title");
            }
        });
    }

    function applyState(collapsed) {
        sidebar.classList.toggle("collapsed", collapsed);
        toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
        toggle.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
        updateTitles(collapsed);
    }

    /* Restore saved choice across pages */
    var savedCollapsed = false;
    try { savedCollapsed = localStorage.getItem(STORAGE_KEY) === "1"; } catch (e) {}
    applyState(savedCollapsed);

    toggle.addEventListener("click", function () {
        var nowCollapsed = !sidebar.classList.contains("collapsed");
        applyState(nowCollapsed);
        try {
            localStorage.setItem(STORAGE_KEY, nowCollapsed ? "1" : "0");
        } catch (e) { /* private mode */ }
    });
})();
</script>

<!-- Lucide icon renderer -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>