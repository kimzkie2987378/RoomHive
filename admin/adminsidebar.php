<?php
/* =========================================================
   ROOMHIVE — SHARED ADMIN SIDEBAR (partial include)
   includes/adminsidebar.php

   One left navigation for every admin page. Replaces the
   copy-pasted <aside class="sidebar"> block.

   Set BEFORE requiring:
     $adminActivePage — one of:
       dashboard | users | bookings | listings | listingapps |
       hostapps | payouts | reviews | messages | reports |
       settings

   Provides (guarded, so pages that define their own keep
   theirs and pages that don't get them for free):
     icon(), emptyState(), statusBadgeClass()

   Also ships the shared admin UI script (sidebar toggle,
   dropdown, topbar shadow, scroll reveal, count-up) so every
   page gets full behavior without extra JS.
========================================================= */

if (!function_exists('icon')) {
    function icon($name, $class = '') {
        $icons = [
            'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
            'users' => '<circle cx="9" cy="8" r="3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 8.5a3 3 0 1 1 0-6"/><path d="M15 14.5c3 0 6.5 1.4 6.5 5.5"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'listing' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9 20v-5h6v5"/>',
            'clipboard' => '<rect x="5" y="4.5" width="14" height="17" rx="2"/><path d="M9 4.5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8v.7"/><path d="M9 11.5h6M9 15.5h6M9 7.5h1"/>',
            'user-check' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="m16 11 2 2 3.5-3.5"/>',
            'wallet' => '<rect x="2.5" y="6.5" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2"/>',
            'star' => '<path d="M12 3.5l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.9l-5.2 2.6 1-5.75-4.2-4.1 5.8-.85z"/>',
            'message' => '<path d="M3.5 12a8.2 8.2 0 1 1 3.3 6.5L3 20l1.3-3.8A8.1 8.1 0 0 1 3.5 12Z"/>',
            'bar-chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.8 1.8 0 0 0 .36 2l.04.04a2.2 2.2 0 1 1-3.1 3.1l-.04-.04a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.1 1.65V20a2.2 2.2 0 1 1-4.4 0v-.06a1.8 1.8 0 0 0-1.18-1.65 1.8 1.8 0 0 0-2 .36l-.04.04a2.2 2.2 0 1 1-3.1-3.1l.04-.04a1.8 1.8 0 0 0 .36-2 1.8 1.8 0 0 0-1.65-1.1H4a2.2 2.2 0 1 1 0-4.4h.06a1.8 1.8 0 0 0 1.65-1.18 1.8 1.8 0 0 0-.36-2l-.04-.04a2.2 2.2 0 1 1 3.1-3.1l.04.04a1.8 1.8 0 0 0 2 .36H10.5a1.8 1.8 0 0 0 1.1-1.65V4a2.2 2.2 0 1 1 4.4 0v.06a1.8 1.8 0 0 0 1.1 1.65 1.8 1.8 0 0 0 2-.36l.04-.04a2.2 2.2 0 1 1 3.1 3.1l-.04.04a1.8 1.8 0 0 0-.36 2v.09a1.8 1.8 0 0 0 1.65 1.1H20a2.2 2.2 0 1 1 0 4.4h-.06a1.8 1.8 0 0 0-1.65 1.1Z"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
            'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 6.5-2.5 8-2.5 8h17S18 14.5 18 8Z"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'headphones' => '<path d="M3 13.5v-1.7a9 9 0 0 1 18 0v1.7"/><rect x="3" y="13.5" width="5" height="6.5" rx="1.6"/><rect x="16" y="13.5" width="5" height="6.5" rx="1.6"/>',
            'user-add' => '<circle cx="9" cy="8" r="3.2"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M18 8v5M15.5 10.5h5"/>',
            'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
            'inbox' => '<path d="M3 12h4.5l1.5 3h6l1.5-3H21"/><path d="M5.5 5.5h13l2.5 6.5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
            'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        ];
        $path = $icons[$name] ?? '';
        return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
    }
}

if (!function_exists('emptyState')) {
    function emptyState($text) {
        echo '<div class="empty-state">';
        echo '<div class="empty-icon">'.icon('inbox').'</div>';
        echo '<p>'.htmlspecialchars($text).'</p>';
        echo '</div>';
    }
}

if (!function_exists('statusBadgeClass')) {
    function statusBadgeClass($status) {
        $map = [
            'Pending'   => 'badge-pending',
            'Approved'  => 'badge-approved',
            'Confirmed' => 'badge-confirmed',
            'Cancelled' => 'badge-cancelled',
            'Completed' => 'badge-completed',
            'Rejected'  => 'badge-rejected',
        ];
        return $map[$status] ?? '';
    }
}

 $adminActivePage = $adminActivePage ?? 'dashboard';

 $adminNavItems = [
    ['key' => 'dashboard',   'label' => 'Dashboard',            'icon' => 'home',       'href' => '/webprogg/admin/admin.php'],
    ['key' => 'users',       'label' => 'Users',                'icon' => 'users',      'href' => '/webprogg/admin/adminusers.php'],
    ['key' => 'bookings',    'label' => 'Bookings',             'icon' => 'calendar',   'href' => '/webprogg/admin/adminbookings.php'],
    ['key' => 'listings',    'label' => 'Listings',             'icon' => 'listing',    'href' => '/webprogg/admin/adminlistings.php'],
    ['key' => 'listingapps', 'label' => 'Listings Application', 'icon' => 'clipboard',  'href' => '/webprogg/admin/listingapplication.php'],
    ['key' => 'hostapps',    'label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['key' => 'payouts',     'label' => 'Payouts',              'icon' => 'wallet',     'href' => '/webprogg/admin/adminpayouts.php'],
    ['key' => 'reviews',     'label' => 'Reviews',              'icon' => 'star',       'href' => '/webprogg/admin/adminreviews.php'],
    ['key' => 'messages',    'label' => 'Messages',             'icon' => 'message',    'href' => '/webprogg/admin/adminmessages.php'],
    ['key' => 'reports',     'label' => 'Reports',              'icon' => 'bar-chart',  'href' => '/webprogg/admin/adminreports.php'],
    ['key' => 'settings',    'label' => 'Settings',             'icon' => 'settings',   'href' => '/webprogg/admin/adminsettings.php'],
];
?>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar">

    <div class="brand">
        <div class="brand-mark">
            <?= icon('home', 'brand-icon') ?>
        </div>
        <div class="brand-text">
            <span class="brand-name">RoomHive</span>
            <span class="brand-tag">FIND. STAY. FEEL AT HOME.</span>
        </div>
    </div>

    <nav class="nav">
        <?php foreach ($adminNavItems as $item): ?>
            <a
                href="<?= htmlspecialchars($item['href']) ?>"
                class="nav-item<?= $adminActivePage === $item['key'] ? ' active' : '' ?>"
            >
                <?= icon($item['icon']) ?>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="help-card">
        <div class="help-icon"><?= icon('headphones') ?></div>
        <p class="help-title">Need Help?</p>
        <p class="help-text">Our support team is here to assist you.</p>
        <button class="btn-support">Contact Support</button>
    </div>

</aside>

<!-- =====================================================
     SHARED ADMIN UI SCRIPT — sidebar toggle, dropdown,
     topbar shadow, scroll reveal, count-up. Runs once,
     on every admin page that includes this sidebar.
====================================================== -->
<script>
(function () {
    "use strict";

    if (window.__adminUI) { return; }
    window.__adminUI = true;

    /* ---- Mobile sidebar toggle ---- */
    var layout  = document.getElementById('adminLayout');
    var menuBtn = document.getElementById('menuBtn');
    var overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() {
        if (layout) { layout.classList.remove('sidebar-open'); }
    }

    if (menuBtn && layout) {
        menuBtn.addEventListener('click', function (e) {
            layout.classList.toggle('sidebar-open');
            e.stopPropagation();
        });
    }
    if (overlay) { overlay.addEventListener('click', closeSidebar); }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeSidebar(); }
    });

    /* ---- Admin chip dropdown ---- */
    var chip = document.getElementById('adminChip');
    if (chip) {
        chip.addEventListener('click', function (e) {
            chip.classList.toggle('open');
            e.stopPropagation();
        });

        document.addEventListener('click', function () {
            chip.classList.remove('open');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { chip.classList.remove('open'); }
        });
    }

    /* ---- Topbar shadow on scroll ---- */
    var topbar = document.getElementById('adminTopbar');
    if (topbar) {
        var onScroll = function () {
            topbar.classList.toggle('topbar-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---- Scroll reveal + count-up ---- */
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.classList.add("in-view");
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -30px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    var counters = document.querySelectorAll("[data-count]");
    if (counters.length && !reduced && "IntersectionObserver" in window) {
        var countIo = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                countIo.unobserve(el);

                var target   = parseFloat(el.getAttribute("data-count")) || 0;
                var decimals = parseInt(el.getAttribute("data-decimals"), 10) || 0;
                var prefix   = el.getAttribute("data-prefix") || "";
                var suffix   = el.getAttribute("data-suffix") || "";
                var t0 = null;
                var DURATION = 1200;

                var stepFn = function (ts) {
                    if (!t0) t0 = ts;
                    var k = Math.min((ts - t0) / DURATION, 1);
                    var eased = 1 - Math.pow(1 - k, 3);
                    var formatted = (target * eased).toLocaleString(
                        undefined,
                        { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
                    );
                    el.textContent = prefix + formatted + suffix;
                    if (k < 1) window.requestAnimationFrame(stepFn);
                };

                window.requestAnimationFrame(stepFn);
            });
        }, { threshold: 0.6 });
        Array.prototype.forEach.call(counters, function (el) { countIo.observe(el); });
    }
})();
</script>