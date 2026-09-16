<?php
/* =========================================================
   ROOMHIVE ADMIN — SHARED BOOTSTRAP + PAGE SHELL
   New admin pages start with:
     require_once __DIR__ . '/admin_init.php';
   then business logic (POST handlers FIRST), then:
     admin_page_start('Title', 'Nav Label', '<style>...</style>');
     ...content...
     admin_page_end();
   Existing pages (admin.php etc.) are NOT converted — they
   keep their own copies to avoid function redeclare errors.

   SIDEBAR CHANGE: RoomHive brand block and the
   "Need Help / Contact Support" card have been removed.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /webprogg/auth/adminlogin.php');
    exit();
}

 $adminName  = $_SESSION['admin_name']  ?? 'Admin User';
 $adminEmail = $_SESSION['admin_email'] ?? '';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['admin_csrf'];

 $navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'href' => '/webprogg/admin/adminusers.php'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '/webprogg/admin/adminbookings.php'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '/webprogg/admin/adminlistings.php'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'href' => '/webprogg/admin/listingapplication.php'],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '/webprogg/admin/adminpayouts.php'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '/webprogg/admin/adminreviews.php'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '/webprogg/admin/adminmessages.php'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '/webprogg/admin/adminreports.php'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '/webprogg/admin/adminsettings.php'],
];

 $pendingHostApps   = (int) $pdo->query("SELECT COUNT(*) FROM host_applications WHERE status = 'pending'")->fetchColumn();
 $pendingListings   = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
 $notificationCount = $pendingHostApps + $pendingListings;

/* ---------- Helpers ---------- */

function h($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function admin_flash_set($type, $text) {
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $text];
}

function admin_flash_take() {
    $f = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return $f;
}

function admin_resolve_photo($path, $fallback) {
    if (empty($path)) return $fallback;
    if (preg_match('#^https?://#i', $path)) return $path;
    $n = ltrim($path, '/');
    if (stripos($n, 'webprogg/') === 0) $n = substr($n, strlen('webprogg/'));
    return '/webprogg/' . $n;
}

function statusBadgeClass($status) {
    $map = [
        'Pending' => 'badge-pending', 'Approved' => 'badge-approved',
        'Confirmed' => 'badge-confirmed', 'Completed' => 'badge-completed',
        'Cancelled' => 'badge-cancelled', 'Rejected' => 'badge-rejected',
        'Processing' => 'badge-completed', 'Failed' => 'badge-rejected',
    ];
    return $map[$status] ?? '';
}

function emptyState($text) {
    echo '<div class="empty-state"><div class="empty-icon">' . icon('inbox') . '</div><p>' . h($text) . '</p></div>';
}

function admin_pagination(int $page, int $totalPages, array $params) {
    if ($totalPages <= 1) return;
    unset($params['page']);
    echo '<div class="pagination">';
    $params['page'] = max(1, $page - 1);
    echo '<a class="page-btn" href="?' . http_build_query($params) . '">' . icon('chevron-left') . '</a>';
    for ($p = 1; $p <= $totalPages; $p++) {
        $params['page'] = $p;
        echo '<a class="page-btn ' . ($p === $page ? 'active' : '') . '" href="?' . http_build_query($params) . '">' . $p . '</a>';
    }
    $params['page'] = min($totalPages, $page + 1);
    echo '<a class="page-btn" href="?' . http_build_query($params) . '">' . icon('chevron-right') . '</a>';
    echo '</div>';
}

/* ---------- Inline icon helper (superset used by every admin page) ---------- */
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
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'headphones' => '<path d="M3 13.5v-1.7a9 9 0 0 1 18 0v1.7"/><rect x="3" y="13.5" width="5" height="6.5" rx="1.6"/><rect x="16" y="13.5" width="5" height="6.5" rx="1.6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'trash' => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 14h10l1-14"/><path d="M9 7V4h6v3"/>',
        'alert-triangle' => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.3 2.4 2.4 4.6-5.4"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
        'person' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/>',
        'user-add' => '<circle cx="9" cy="8" r="3.2"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M18 8v5M15.5 10.5h5"/>',
        'pin' => '<path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.2"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'phone' => '<path d="M5 4h3.5l1.5 5-2.2 1.6a11 11 0 0 0 5.6 5.6L14.5 14l5 1.5V19a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'file' => '<path d="M7 3.5h7l4.5 4.5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.7"/><path d="m21 16-5.5-5.5L4 21"/>',
        'eye' => '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'inbox' => '<path d="M3 12h4.5l1.5 3h6l1.5-3H21"/><path d="M5.5 5.5h13l2.5 6.5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
        'tag' => '<path d="M11.5 3h6.5a1 1 0 0 1 1 1v6.5a1 1 0 0 1-.3.7l-9 9a1 1 0 0 1-1.4 0l-6.5-6.5a1 1 0 0 1 0-1.4l9-9a1 1 0 0 1 .7-.3Z"/><circle cx="15.5" cy="7.5" r="1.3"/>',
        'more-vertical' => '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'calendar-small' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'expand' => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

/* ---------- Page shell ---------- */
function admin_page_start($title, $activeLabel, $extraCss = '') {
    global $navItems, $notificationCount, $adminName, $adminEmail;
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= h($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/admin.css">
<style>
    .sidebar .nav { padding-top: 10px; }
    .admin-chip { position: relative; cursor: pointer; }
    .admin-menu { display: none; position: absolute; top: calc(100% + 10px); right: 0; min-width: 200px;
        background: #fff; border: 1px solid #EEF1F6; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(20,20,43,0.12); padding: 8px; z-index: 50; }
    .admin-chip.open .admin-menu { display: block; }
    .admin-menu-header { display: flex; flex-direction: column; padding: 8px 10px 10px;
        border-bottom: 1px solid #EEF1F6; margin-bottom: 6px; }
    .admin-menu-name { font-weight: 600; font-size: 13px; color: #14142B; }
    .admin-menu-email { font-size: 12px; color: #8B93A6; margin-top: 2px; }
    .admin-menu-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px;
        border-radius: 8px; font-size: 13px; color: #14142B; }
    .admin-menu-item:hover { background: #F6F7FB; }
    .admin-menu-item .icon { width: 16px; height: 16px; }
    .admin-logout { color: #E14B4B; }
    .flash-banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px;
        border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .flash-banner.success { background: #E7F7EC; color: #2FA84F; }
    .flash-banner.error { background: #FCEAEA; color: #E14B4B; }
    .flash-banner .icon { width: 18px; height: 18px; flex-shrink: 0; }
</style>
<?= $extraCss ?>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= h($item['href']) ?>" class="nav-item <?= $item['label'] === $activeLabel ? 'active' : '' ?>">
                    <?= icon($item['icon']) ?><span><?= h($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" aria-label="Toggle menu"><?= icon('menu') ?></button>
            <div class="search-box"><?= icon('search') ?><input type="text" placeholder="Search users, bookings, properties..."></div>
            <div class="topbar-right">
                <button class="icon-btn bell-btn" aria-label="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($notificationCount > 0): ?><span class="bell-badge"><?= $notificationCount ?></span><?php endif; ?>
                </button>
                <div class="admin-chip" id="adminChip">
                    <div class="admin-avatar admin-avatar-fallback"><?= h(strtoupper(substr($adminName, 0, 1))) ?></div>
                    <div class="admin-info">
                        <span class="admin-name"><?= h($adminName) ?></span>
                        <span class="admin-role">Administrator</span>
                    </div>
                    <?= icon('chevron-down', 'chevron') ?>
                    <div class="admin-menu" id="adminMenu">
                        <div class="admin-menu-header">
                            <span class="admin-menu-name"><?= h($adminName) ?></span>
                            <?php if ($adminEmail): ?><span class="admin-menu-email"><?= h($adminEmail) ?></span><?php endif; ?>
                        </div>
                        <a href="/webprogg/auth/logout.php" class="admin-menu-item admin-logout"><?= icon('lock') ?><span>Log Out</span></a>
                    </div>
                </div>
            </div>
        </header>
        <div class="content">
<?php
}

function admin_page_end() {
    ?>
        </div>
    </div>
</div>
<script>
(function () {
    const chip = document.getElementById('adminChip');
    if (!chip) return;
    chip.addEventListener('click', function (e) { chip.classList.toggle('open'); e.stopPropagation(); });
    document.addEventListener('click', function () { chip.classList.remove('open'); });
})();
</script>
</body>
</html>
<?php
}