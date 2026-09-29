<?php
/* =========================================================
   Shared host sidebar  (Lucide icons + collapsible)
   host/host_sidebar.php

   Set $activePage before including:
   overview | listings | pending | bookings | earnings | payouts |
   reviews | messages | notifications | editprofile | verification |
   payoutmethods | notificationsettings | security | quithosting |
   helpcenter
   Requires host_init.php ($host, $pending_tenants_count) — or a
   host page that builds $host itself (e.g. pendingtenants.php).

   ★ FIXED: profile card now FULLY self-contained. Its sizing
   used to come from page-level CSS that was deleted during the
   sidebar refactor, so the card vanished when collapsed. All
   card styles (expanded + collapsed) now live here, scoped
   under .hp-sidebar.
========================================================= */

 $activePage = $activePage ?? '';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hp_side_active')) {
    function hp_side_active($activePage, $key) {
        return $activePage === $key ? ' active' : '';
    }
}

/* -----------------------------------------------------
   UNREAD NOTIFICATION COUNT (for the sidebar badge)
----------------------------------------------------- */
 $hostNotifUnread = $hostNotifUnread ?? null;

if ($hostNotifUnread === null && isset($_SESSION['user_id'])) {
    try {
        $hnuStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
        );
        $hnuStmt->execute(['u' => $_SESSION['user_id']]);
        $hostNotifUnread = (int) $hnuStmt->fetchColumn();
    } catch (Exception $e) {
        $hostNotifUnread = 0;
    }
}

/* -----------------------------------------------------
   SIDEBAR LINK MAPS — [url, lucide-icon, label]
----------------------------------------------------- */
 $hostSidebarMain = [
    'overview'      => ['/webprogg/host/hostprofile.php',       'layout-dashboard', 'Overview'],
    'listings'      => ['/webprogg/user/mylistings.php',        'building-2',       'My Listings'],
    'pending'       => ['/webprogg/booking/pendingtenants.php', 'user-plus',        'Pending Tenants'],
    'bookings'      => ['/webprogg/host/hostbookings.php',      'calendar-check',   'Bookings'],
    'earnings'      => ['/webprogg/host/hostearnings.php',      'banknote',         'Earnings'],
    'payouts'       => ['/webprogg/host/payouts.php',           'hand-coins',       'Payouts'],
    'reviews'       => ['/webprogg/host/hostreviews.php',       'star',             'Reviews'],
    'messages'      => ['/webprogg/host/hostmessages.php',      'message-square',   'Messages'],
    'notifications' => ['/webprogg/host/hostnotifications.php', 'bell',             'Notifications'],
];

 $hostSidebarSettings = [
    'editprofile'          => ['/webprogg/host/hosteditprofile.php',          'user-round',  'Profile &amp; Account'],
    'payoutmethods'        => ['/webprogg/host/payoutmethods.php',            'credit-card', 'Payout Methods'],
    'notificationsettings' => ['/webprogg/host/hostnotificationsettings.php', 'bell-ring',   'Notification Settings'],
    'security'             => ['/webprogg/host/hostsecurity.php',             'shield',      'Security'],
    'quithosting'          => ['/webprogg/host/quithosting.php',              'door-open',   'Quit Hosting'],
];

 $hostSidebarFooter = [
    'helpcenter' => ['/webprogg/host/helpcenter.php', 'circle-help', 'Help Center'],
];

/* Badge counts per link key */
 $hostSidebarBadges = [
    'pending'       => (int) ($pending_tenants_count ?? 0),
    'notifications' => (int) ($hostNotifUnread ?? 0),
];

/* Renders one sidebar link (shared by all groups) */
if (!function_exists('hp_side_link')) {
    function hp_side_link($key, $link, $activePage, $badges) {
        $extraClass = $key === 'quithosting' ? ' hp-side-quit' : '';
        $active     = hp_side_active($activePage, $key);
        $badge      = $badges[$key] ?? 0;
        ?>
        <a href="<?php echo h($link[0]); ?>" class="hp-side-link<?php echo $active . $extraClass; ?>">
            <i data-lucide="<?php echo h($link[1]); ?>"></i>
            <span class="hp-side-label"><?php echo $link[2]; /* pre-escaped literal */ ?></span>
            <?php if ($badge > 0): ?>
                <span class="hp-side-badge"><?php echo h($badge); ?></span>
            <?php endif; ?>
        </a>
        <?php
    }
}
?>

<style>
/* =========================================================
   Collapsible host sidebar — layout & collapse behavior.
   Loads after your host stylesheet, so these rules win.
   ========================================================= */
.hp-sidebar {
    /* ---- spacing knobs ---- */
    --hp-w: 250px;
    --hp-w-collapsed: 66px;
    --hp-icon: 18px;
    --hp-gap: 10px;          /* icon ↔ label */
    --hp-link-pad-y: 10px;
    --hp-link-pad-x: 14px;

    display: flex;
    flex-direction: column;
    gap: 3px;

    width: var(--hp-w);
    box-sizing: border-box;
    transition: width .3s ease;
    overflow: hidden;
}

/* ---- chevron toggle ------------------------------------ */
.hp-side-toggle {
    display: flex;
    align-items: center;
    justify-content: flex-end;
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

.hp-side-toggle:hover {
    background: rgba(0, 0, 0, 0.06);
}

.hp-side-toggle svg {
    width: 20px;
    height: 20px;
    transition: transform .3s ease;
}

/* ---- links ---------------------------------------------- */
.hp-side-link {
    display: flex;
    align-items: center;
    gap: var(--hp-gap);
    padding: var(--hp-link-pad-y) var(--hp-link-pad-x);
    margin: 0;
    white-space: nowrap;
    position: relative;      /* anchor for collapsed badges */
    transition: background .2s ease;
}

.hp-side-link svg {
    width: var(--hp-icon);
    height: var(--hp-icon);
    flex-shrink: 0;
}

.hp-side-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ---- badges ---------------------------------------------- */
.hp-side-badge {
    margin-left: auto;
    background: #E14B4B;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    padding: 3px 7px;
    border-radius: 999px;
}

/* =========================================================
   ★ FIXED — PROFILE CARD (fully owned by the sidebar)
   Scoped under .hp-sidebar so page CSS can't break it.
   ========================================================= */
.hp-sidebar .hp-sidebar-card {
    flex-shrink: 0;
    min-width: 0;
    text-align: center;
    padding: 12px 10px 10px;
    margin: 0 0 6px;
}

.hp-sidebar .hp-profile-photo {
    position: relative;
    width: 72px;
    height: 72px;
    margin: 0 auto 10px;
}

.hp-sidebar .hp-sidebar-avatar {
    display: block;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    transition: width .3s ease, height .3s ease;
}

.hp-sidebar .hp-photo-edit {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 26px;
    height: 26px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid rgba(28, 42, 56, 0.15);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
}

.hp-sidebar .hp-photo-edit img {
    width: 14px;
    height: 14px;
    display: block;
}

.hp-sidebar .hp-sidebar-card h4 {
    margin: 0 0 5px;
    font-size: 15px;
    color: #1c2a38;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hp-sidebar .hp-host-badge {
    display: inline-block;
    padding: 3px 10px;
    background: #FDF1DC;
    border: 1px solid rgba(237, 164, 35, 0.5);
    color: #B07708;
    font-size: 11px;
    font-weight: 800;
    border-radius: 999px;
}

.hp-sidebar .hp-member-since {
    margin: 6px 0 0;
    font-size: 11px;
    color: #6b7684;
}

/* ---- Settings group heading ------------------------------- */
.hp-side-heading {
    margin: 14px 10px 4px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #9aa5b1;
}

.hp-side-quit {
    color: #b3261e;
}
.hp-side-quit:hover {
    background: #fdecea;
    color: #b3261e;
}

/* =========================================================
   COLLAPSED STATE
   ========================================================= */
.hp-sidebar.collapsed {
    width: var(--hp-w-collapsed);
}

/* left arrow visually becomes a right arrow */
.hp-sidebar.collapsed .hp-side-toggle {
    justify-content: center;
}
.hp-sidebar.collapsed .hp-side-toggle svg {
    transform: rotate(180deg);
}

/* labels + group heading hidden; icons centered */
.hp-sidebar.collapsed .hp-side-label,
.hp-sidebar.collapsed .hp-side-heading {
    display: none;
}

.hp-sidebar.collapsed .hp-side-link {
    justify-content: center;
    gap: 0;
    padding-left: 0;
    padding-right: 0;
}

/* badge becomes a small dot pinned to the icon's top-right */
.hp-sidebar.collapsed .hp-side-badge {
    position: absolute;
    top: 4px;
    left: calc(50% + 2px);
    margin-left: 0;
    min-width: 15px;
    height: 15px;
    padding: 0 4px;
    font-size: 9px;
    line-height: 15px;
    text-align: center;
}

/* =========================================================
   ★ FIXED — COLLAPSED PROFILE CARD
   Exactly one centered 40px avatar; everything else hides.
   ========================================================= */
.hp-sidebar.collapsed .hp-sidebar-card {
    padding: 6px 0 4px;
}

.hp-sidebar.collapsed .hp-profile-photo {
    width: 40px;
    height: 40px;
    margin: 0 auto;
}

.hp-sidebar.collapsed .hp-sidebar-avatar {
    width: 40px;
    height: 40px;
}

/* camera button is useless on the tiny rail — hide it */
.hp-sidebar.collapsed .hp-photo-edit {
    display: none;
}

.hp-sidebar.collapsed .hp-sidebar-card h4,
.hp-sidebar.collapsed .hp-sidebar-card .hp-host-badge,
.hp-sidebar.collapsed .hp-sidebar-card .hp-member-since {
    display: none;
}

.hp-side-logout {
    margin-top: 10px;
}

@media (prefers-reduced-motion: reduce) {
    .hp-sidebar,
    .hp-side-toggle svg,
    .hp-side-link,
    .hp-sidebar .hp-sidebar-avatar {
        transition: none;
    }
}
</style>

<aside class="hp-sidebar" id="hpSidebar">

    <!-- Expand / Collapse toggle -->
    <button
        type="button"
        class="hp-side-toggle"
        id="hpSidebarToggle"
        aria-expanded="true"
        aria-label="Collapse sidebar"
    >
        <i data-lucide="chevron-left"></i>
    </button>

    <!-- Profile card -->
    <div class="hp-sidebar-card">
        <div class="hp-profile-photo">
            <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar" id="hostSidebarAvatarImg">
            <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
                <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
            </button>
            <input type="file" id="hostAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>
        <h4><?php echo h($host['name']); ?></h4>
        <span class="hp-host-badge">Host</span>
        <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <!-- Main links -->
    <?php foreach ($hostSidebarMain as $key => $link): ?>
        <?php hp_side_link($key, $link, $activePage, $hostSidebarBadges); ?>
    <?php endforeach; ?>

    <!-- Settings group -->
    <span class="hp-side-heading">Settings</span>
    <?php foreach ($hostSidebarSettings as $key => $link): ?>
        <?php hp_side_link($key, $link, $activePage, $hostSidebarBadges); ?>
    <?php endforeach; ?>

    <!-- Footer -->
    <?php foreach ($hostSidebarFooter as $key => $link): ?>
        <?php hp_side_link($key, $link, $activePage, $hostSidebarBadges); ?>
    <?php endforeach; ?>

    <a href="/webprogg/auth/logout.php" class="hp-side-link hp-side-logout">
        <i data-lucide="log-out"></i>
        <span class="hp-side-label">Log Out</span>
    </a>

</aside>

<script>
/* Sidebar collapse — same behavior as the user sidebar but
   with its OWN localStorage key. */
(function () {
    var sidebar = document.getElementById("hpSidebar");
    var toggle  = document.getElementById("hpSidebarToggle");
    if (!sidebar || !toggle) { return; }

    var STORAGE_KEY = "roomhive_host_sidebar_collapsed";
    var links = sidebar.querySelectorAll(".hp-side-link");

    function updateTitles(collapsed) {
        links.forEach(function (link) {
            var label = link.querySelector(".hp-side-label");
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

<script>
/* Sidebar avatar upload — runs on every host page that
   includes this sidebar. */
(function () {
    const photoButton  = document.getElementById('hostPhotoButton');
    const fileInput    = document.getElementById('hostAvatarFileInput');
    const sidebarImg   = document.getElementById('hostSidebarAvatarImg');
    const navAvatarImg = document.getElementById('navAccountAvatarImg');

    if (!photoButton || !fileInput || !sidebarImg) return;

    photoButton.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please choose a JPG, PNG, or WEBP image.');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('That image is too large. Please choose one under 5MB.');
            fileInput.value = '';
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        const previousSrc = sidebarImg.src;
        sidebarImg.src = previewUrl;
        if (navAvatarImg) navAvatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/user/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                sidebarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                sidebarImg.src = previousSrc;
                if (navAvatarImg) navAvatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function () {
            sidebarImg.src = previousSrc;
            if (navAvatarImg) navAvatarImg.src = previousSrc;
            alert('Something went wrong uploading your photo. Please try again.');
        })
        .finally(function () {
            URL.revokeObjectURL(previewUrl);
            photoButton.disabled = false;
            fileInput.value = '';
        });
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