<?php
/**listingapplication.php
 * RoomHive Admin — Listings Application
 * Review listings submitted by hosts before they go live: approve or reject them.
 *
 * Pulls real rows from `listings` (excluding drafts — a host hasn't
 * finished the wizard yet if status is still 'draft'), joined to
 * `users` for the host's name, and actually updates `listings.status`
 * when you click Approve / Reject.
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: /webprogg/auth/adminlogin.php');
    exit();
}

/* ---------- Sidebar navigation ---------- */
$navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'href' => '#'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '#'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '#'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'href' => '/webprogg/admin/listingapplication.php', 'active' => true],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '#'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '#'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '#'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '#'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '#'],
];

$notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();

/* =========================================================
   HANDLE APPROVE / REJECT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {

    $targetId = (int) $_POST['id'];
    $action   = $_POST['action'];

    if (in_array($action, ['approve', 'reject'], true)) {

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $pdo->prepare(
            "UPDATE listings SET status = :status, updated_at = NOW() WHERE id = :id"
        )->execute([
            'status' => $newStatus,
            'id'     => $targetId,
        ]);

        header('Location: /webprogg/admin/listingapplication.php?' . http_build_query([
            'filter' => $_GET['filter'] ?? 'all',
            'page'   => $_GET['page'] ?? 1,
            'id'     => $targetId,
        ]));
        exit();
    }
}

/* =========================================================
   LOAD LISTINGS FROM THE DATABASE (skip unfinished drafts)
   ========================================================= */
$rows = $pdo->query(
    "SELECT l.id, l.title, l.location, l.property_type, l.price,
            l.description, l.amenities, l.status, l.created_at,
            u.name AS host_name
     FROM listings l
     JOIN users u ON u.id = l.user_id
     WHERE l.status != 'draft'
     ORDER BY l.created_at DESC"
)->fetchAll();

$listingApps = array_map(function ($r) {
    return [
        'id'        => (int) $r['id'],
        'title'     => $r['title'],
        'host'      => $r['host_name'],
        'city'      => $r['location'],
        'type'      => ucfirst($r['property_type']),
        'price'     => '₱' . number_format((float) $r['price']) . ' / month',
        'date'      => date('M j, Y', strtotime($r['created_at'])),
        'time'      => date('g:i A', strtotime($r['created_at'])),
        'status'    => ucfirst($r['status']),
        'desc'      => $r['description'],
        'amenities' => json_decode($r['amenities'] ?? '[]', true) ?? [],
    ];
}, $rows);

$requiredDocuments = ['Land Title / Lease Contract', 'Barangay Business Clearance', 'Property Photos (Exterior & Interior)', 'Fire Safety Certificate'];

foreach ($listingApps as &$l) {
    $l['avatar'] = 'https://ui-avatars.com/api/?background=1C2A38&color=fff&bold=true&name=' . urlencode($l['host']);
}
unset($l);

/* ---------- Filter ---------- */
$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $validFilters, true)) { $filter = 'all'; }

$counts = [
    'all'      => count($listingApps),
    'pending'  => count(array_filter($listingApps, fn($a) => $a['status'] === 'Pending')),
    'approved' => count(array_filter($listingApps, fn($a) => $a['status'] === 'Approved')),
    'rejected' => count(array_filter($listingApps, fn($a) => $a['status'] === 'Rejected')),
];

$filtered = $filter === 'all'
    ? $listingApps
    : array_values(array_filter($listingApps, fn($a) => strtolower($a['status']) === $filter));

/* ---------- Pagination ---------- */
$perPage = 8;
$totalItems = count($filtered);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pageItems = array_slice($filtered, $offset, $perPage);

/* ---------- Selected listing (for the right-hand detail panel) ---------- */
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : ($pageItems[0]['id'] ?? null);
$selected = null;
foreach ($listingApps as $l) {
    if ($l['id'] === $selectedId) { $selected = $l; break; }
}

function appLink($id, $filter, $page) {
    return '?' . http_build_query(['filter' => $filter, 'page' => $page, 'id' => $id]);
}

function eyeLink($id, $filter, $page) {
    return '/webprogg/admin/listingapplicationeye.php?' . http_build_query(['filter' => $filter, 'page' => $page, 'id' => $id]);
}

/* ---------- Inline icon helper (same set as admin.php) ---------- */
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
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'eye' => '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.3 2.4 2.4 4.6-5.4"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'person' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/>',
        'pin' => '<path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.2"/>',
        'calendar-small' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'phone' => '<path d="M5 4h3.5l1.5 5-2.2 1.6a11 11 0 0 0 5.6 5.6L14.5 14l5 1.5V19a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'file' => '<path d="M7 3.5h7l4.5 4.5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4.5"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'inbox' => '<path d="M3 12h4.5l1.5 3h6l1.5-3H21"/><path d="M5.5 5.5h13l2.5 6.5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
        'tag' => '<path d="M11.5 3h6.5a1 1 0 0 1 1 1v6.5a1 1 0 0 1-.3.7l-9 9a1 1 0 0 1-1.4 0l-6.5-6.5a1 1 0 0 1 0-1.4l9-9a1 1 0 0 1 .7-.3Z"/><circle cx="15.5" cy="7.5" r="1.3"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}

function statusBadgeClass($status) {
    $map = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected'];
    return $map[$status] ?? '';
}
function emptyState($text) {
    echo '<div class="empty-state"><div class="empty-icon">'.icon('inbox').'</div><p>'.htmlspecialchars($text).'</p></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RoomHive Admin — Listings Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/admin.css">
</head>
<body>

<div class="layout">

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark"><?= icon('home', 'brand-icon') ?></div>
            <div class="brand-text">
                <span class="brand-name">RoomHive</span>
                <span class="brand-tag">FIND. STAY. FEEL AT HOME.</span>
            </div>
        </div>

        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-item <?= !empty($item['active']) ? 'active' : '' ?>">
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

    <!-- ============ MAIN ============ -->
    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" aria-label="Toggle menu"><?= icon('menu') ?></button>
            <div class="search-box">
                <?= icon('search') ?>
                <input type="text" placeholder="Search users, bookings, properties...">
            </div>
            <div class="topbar-right">
                <button class="icon-btn bell-btn" aria-label="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($notificationCount > 0): ?><span class="bell-badge"><?= $notificationCount ?></span><?php endif; ?>
                </button>
                <div class="admin-chip">
                    <div class="admin-avatar admin-avatar-fallback">A</div>
                    <div class="admin-info">
                        <span class="admin-name">Admin User</span>
                        <span class="admin-role">Administrator</span>
                    </div>
                    <?= icon('chevron-down', 'chevron') ?>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-heading">
                <h1>Listings Application</h1>
                <p>Review and manage listings submitted by hosts before they go live.</p>
            </div>

            <div class="applications-content">
                <!-- ============ LEFT: filters + table ============ -->
                <div class="applications-main">
                    <div class="filter-tabs">
                        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                            <?= icon('tag') ?> All (<?= $counts['all'] ?>)
                        </a>
                        <a href="?filter=pending" class="filter-tab tab-pending <?= $filter === 'pending' ? 'active' : '' ?>">
                            <?= icon('clock') ?> Pending (<?= $counts['pending'] ?>)
                        </a>
                        <a href="?filter=approved" class="filter-tab tab-approved <?= $filter === 'approved' ? 'active' : '' ?>">
                            <?= icon('check-circle') ?> Approved (<?= $counts['approved'] ?>)
                        </a>
                        <a href="?filter=rejected" class="filter-tab tab-rejected <?= $filter === 'rejected' ? 'active' : '' ?>">
                            <?= icon('x-circle') ?> Rejected (<?= $counts['rejected'] ?>)
                        </a>
                    </div>

                    <div class="table-scroll">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Listing</th>
                                    <th>Date Submitted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pageItems)): ?>
                                    <tr><td colspan="4"><?php emptyState($counts['all'] === 0 ? 'No listing applications yet.' : 'No listing applications match this filter.'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($pageItems as $l): ?>
                                        <tr class="<?= $l['id'] === $selectedId ? 'selected' : '' ?>"
                                            onclick="location.href='<?= appLink($l['id'], $filter, $page) ?>'">
                                            <td>
                                                <div class="applicant-cell">
                                                    <img src="<?= htmlspecialchars($l['avatar']) ?>" alt="<?= htmlspecialchars($l['host']) ?>">
                                                    <div class="people-info">
                                                        <span class="people-name"><?= htmlspecialchars($l['title']) ?></span>
                                                        <span class="people-sub">by <?= htmlspecialchars($l['host']) ?> · <?= htmlspecialchars($l['city']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="date-applied"><?= htmlspecialchars($l['date']) ?><span class="time"><?= htmlspecialchars($l['time']) ?></span></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= statusBadgeClass($l['status']) ?>"><?= htmlspecialchars($l['status']) ?></span>
                                            </td>
                                            <td>
                                                <a class="btn-view" href="<?= eyeLink($l['id'], $filter, $page) ?>" onclick="event.stopPropagation()" aria-label="View listing"><?= icon('eye') ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalItems > 0): ?>
                        <div class="pagination">
                            <a class="page-btn" href="?<?= http_build_query(['filter'=>$filter,'page'=>max(1,$page-1)]) ?>"><?= icon('chevron-left') ?></a>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a class="page-btn <?= $p === $page ? 'active' : '' ?>" href="?<?= http_build_query(['filter'=>$filter,'page'=>$p]) ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <a class="page-btn" href="?<?= http_build_query(['filter'=>$filter,'page'=>min($totalPages,$page+1)]) ?>"><?= icon('chevron-right') ?></a>
                            <span class="pagination-info">Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?> results</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============ RIGHT: detail panel ============ -->
                <div class="detail-panel-wrap">
                    <div class="detail-panel">
                        <?php if (!$selected): ?>
                            <div class="detail-empty">
                                <div class="empty-icon"><?= icon('inbox') ?></div>
                                <p><?= $counts['all'] === 0 ? 'No listing applications yet. Once a host submits a listing for review, it will show up here.' : 'Select a listing to view its details.' ?></p>
                            </div>
                        <?php else: ?>
                            <div class="detail-header">
                                <h2>Listing Details</h2>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <a href="<?= eyeLink($selected['id'], $filter, $page) ?>" class="btn-view" aria-label="View full application and photos"><?= icon('eye') ?></a>
                                    <a href="?<?= http_build_query(['filter'=>$filter,'page'=>$page]) ?>" class="icon-btn" aria-label="Close">✕</a>
                                </div>
                            </div>

                            <div class="detail-profile">
                                <img class="detail-avatar" src="<?= htmlspecialchars($selected['avatar']) ?>" alt="<?= htmlspecialchars($selected['host']) ?>">
                                <div>
                                    <p class="detail-name"><?= htmlspecialchars($selected['title']) ?></p>
                                    <div class="detail-meta-row"><?= icon('person') ?>Hosted by <?= htmlspecialchars($selected['host']) ?></div>
                                    <div class="detail-meta-row"><?= icon('pin') ?><?= htmlspecialchars($selected['city']) ?></div>
                                </div>
                            </div>

                            <div class="info-grid" style="margin-top:6px;">
                                <div class="info-item"><span class="info-label">Property Type</span><span class="info-value"><?= htmlspecialchars($selected['type']) ?></span></div>
                                <div class="info-item"><span class="info-label">Price</span><span class="info-value"><?= htmlspecialchars($selected['price']) ?></span></div>
                            </div>

                            <div class="detail-section">
                                <h3>Description</h3>
                                <p class="detail-text"><?= htmlspecialchars($selected['desc']) ?></p>
                            </div>

                            <div class="detail-section">
                                <h3>Amenities</h3>
                                <div class="amenity-tags">
                                    <?php if (empty($selected['amenities'])): ?>
                                        <span class="amenity-tag">None listed</span>
                                    <?php else: ?>
                                        <?php foreach ($selected['amenities'] as $am): ?>
                                            <span class="amenity-tag"><?= htmlspecialchars($am) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3>Documents Required</h3>
                                <div class="doc-list">
                                    <?php foreach ($requiredDocuments as $doc): ?>
                                        <div class="doc-item"><?= icon('file') ?><?= htmlspecialchars($doc) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="submitted-row">
                                <span class="info-label">Submitted on</span>
                                <span class="info-value"><?= htmlspecialchars($selected['date']) ?> <?= htmlspecialchars($selected['time']) ?></span>
                            </div>

                            <?php if ($selected['status'] === 'Pending'): ?>
                                <div class="detail-actions">
                                    <form method="POST" action="<?= appLink($selected['id'], $filter, $page) ?>" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $selected['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject" onclick="return confirm('Reject the listing \'<?= htmlspecialchars(addslashes($selected['title'])) ?>\'?')">Reject Listing</button>
                                    </form>
                                    <form method="POST" action="<?= appLink($selected['id'], $filter, $page) ?>" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $selected['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve" onclick="return confirm('Approve the listing \'<?= htmlspecialchars(addslashes($selected['title'])) ?>\'?')">Approve Listing</button>
                                    </form>
                                </div>
                                <div class="detail-note"><?= icon('lock') ?>Approving publishes this listing on RoomHive. The host will be notified of your decision.</div>
                            <?php else: ?>
                                <div class="detail-note"><?= icon('lock') ?>This listing has already been <?= strtolower($selected['status']) ?>.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>