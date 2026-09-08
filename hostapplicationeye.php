<?php
/**hostapplicationeye.php
 * RoomHive Admin — Become a Host Application Viewer
 *
 * Reached via the eye icon on hostapplication.php. Shows what
 * the applicant filled in on becomeahost.php itself — full
 * name, email, phone, location, ID type/number — plus the
 * actual uploaded ID photo shown inline, not just a link.
 */

session_start();
require_once 'db_connect.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: adminlogin.php');
    exit();
}

/* ---------- Sidebar navigation (same set as hostapplication.php) ---------- */
$navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'href' => 'admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'href' => '#'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '#'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '#'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'href' => 'listingapplication.php'],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => 'hostapplication.php', 'active' => true],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '#'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '#'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '#'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '#'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '#'],
];

$notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM host_applications WHERE status = 'pending'")->fetchColumn();

/* ---------------------------------------------------------
   INPUT — which application are we viewing?
   filter/page are only carried along so Back returns the
   admin to exactly where they were in the table.
--------------------------------------------------------- */
$applicationId = (int) ($_GET['id'] ?? 0);
$filter        = $_GET['filter'] ?? 'all';
$page          = (int) ($_GET['page'] ?? 1);

$backLink = 'hostapplication.php?' . http_build_query([
    'filter' => $filter,
    'page'   => $page,
    'id'     => $applicationId,
]);

/* ---------------------------------------------------------
   LOAD WHAT WAS FILLED IN ON becomeahost.php
   Straight from host_applications — the exact fields that
   form's POST handler inserts: full_name, email, phone,
   location, id_type, id_number, id_file, plus when it was
   submitted and its current status.
--------------------------------------------------------- */
$appStmt = $pdo->prepare(
    "SELECT ha.id, ha.full_name, ha.email, ha.phone, ha.age, ha.location,
            ha.id_type, ha.id_number, ha.id_file, ha.status, ha.created_at
     FROM host_applications ha
     WHERE ha.id = :id
     LIMIT 1"
);
$appStmt->execute(['id' => $applicationId]);
$application = $appStmt->fetch();

if (!$application) {
    header('Location: hostapplication.php');
    exit();
}

function statusBadgeClass($status) {
    $map = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected'];
    return $map[$status] ?? '';
}

$statusLabel = ucfirst($application['status']);

/* ---------- Inline icon helper (same set as hostapplication.php) ---------- */
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
        'pin' => '<path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.2"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'phone' => '<path d="M5 4h3.5l1.5 5-2.2 1.6a11 11 0 0 0 5.6 5.6L14.5 14l5 1.5V19a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'file' => '<path d="M7 3.5h7l4.5 4.5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4.5"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'calendar-small' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'expand' => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RoomHive Admin — Host Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
<style>
    .eye-back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        margin-bottom: 14px;
    }
    .eye-back-link:hover { color: var(--orange-deep); }
    .eye-back-link svg { width: 15px; height: 15px; }

    .eye-page-heading h1 { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    .eye-layout {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 20px;
        align-items: start;
        max-width: 980px;
    }
    @media (max-width: 900px) {
        .eye-layout { grid-template-columns: 1fr; }
    }

    .eye-panel {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 22px;
    }

    .eye-applicant-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
    }
    .eye-avatar {
        width: 52px; height: 52px; border-radius: 50%;
        background: var(--orange);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 18px; flex-shrink: 0;
    }
    .eye-applicant-name { font-size: 17px; font-weight: 700; color: var(--text-dark); margin: 0 0 4px; }

    .eye-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 18px;
        margin-top: 4px;
    }
    .eye-info-item .info-label { display: flex; align-items: center; gap: 6px; }
    .eye-info-item .info-label svg { width: 13px; height: 13px; }
    .eye-info-item.full-width { grid-column: 1 / -1; }

    .eye-id-photo-wrap {
        position: relative;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--orange-light);
    }
    .eye-id-photo {
        width: 100%;
        max-height: 340px;
        object-fit: contain;
        display: block;
        background: #fff;
    }
    .eye-id-photo-missing {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; padding: 60px 12px; color: var(--text-soft); font-size: 13px;
    }
    .eye-id-photo-missing svg { width: 26px; height: 26px; }
    .eye-id-expand {
        position: absolute; top: 10px; right: 10px;
        background: rgba(15,15,20,.65); color: #fff;
        border-radius: 999px; padding: 6px 10px;
        font-size: 12px; font-weight: 600;
        display: flex; align-items: center; gap: 5px;
        text-decoration: none;
    }
    .eye-id-expand:hover { background: rgba(15,15,20,.85); }
    .eye-id-expand svg { width: 12px; height: 12px; }

    .eye-submitted-row {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border);
        font-size: 13px;
    }
</style>
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

            <a href="<?= htmlspecialchars($backLink) ?>" class="eye-back-link">
                <?= icon('arrow-left') ?> Back to Host Applications
            </a>

            <div class="page-heading eye-page-heading">
                <h1>
                    <?= htmlspecialchars($application['full_name']) ?>'s Host Application
                    <span class="badge <?= statusBadgeClass($statusLabel) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </h1>
                <p>Exactly what was filled in on the "Become a Host" form.</p>
            </div>

            <div class="eye-layout">

                <!-- ============ LEFT: form fields ============ -->
                <div class="eye-panel">

                    <div class="eye-applicant-header">
                        <div class="eye-avatar"><?= htmlspecialchars(strtoupper(substr($application['full_name'], 0, 1))) ?></div>
                        <div>
                            <p class="eye-applicant-name"><?= htmlspecialchars($application['full_name']) ?></p>
                        </div>
                    </div>

                    <div class="eye-info-grid">
                        <div class="eye-info-item">
                            <span class="info-label"><?= icon('mail') ?> Email</span>
                            <span class="info-value"><?= htmlspecialchars($application['email']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label"><?= icon('phone') ?> Phone</span>
                            <span class="info-value"><?= htmlspecialchars($application['phone']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Age</span>
                            <span class="info-value"><?= htmlspecialchars($application['age']) ?></span>
                        </div>
                        <div class="eye-info-item full-width">
                            <span class="info-label"><?= icon('pin') ?> Location</span>
                            <span class="info-value"><?= htmlspecialchars($application['location']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">ID Type</span>
                            <span class="info-value"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $application['id_type']))) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">ID Number</span>
                            <span class="info-value"><?= htmlspecialchars($application['id_number']) ?></span>
                        </div>
                    </div>

                    <div class="eye-submitted-row">
                        <span class="info-label"><?= icon('calendar-small') ?> Submitted on</span>
                        <span class="info-value"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($application['created_at']))) ?></span>
                    </div>
                </div>

                <!-- ============ RIGHT: uploaded ID photo ============ -->
                <div class="eye-panel">
                    <span class="info-label" style="display:block; margin-bottom:10px;">Uploaded ID</span>

                    <?php if (!empty($application['id_file'])): ?>
                        <div class="eye-id-photo-wrap">
                            <img class="eye-id-photo" src="<?= htmlspecialchars($application['id_file']) ?>" alt="Uploaded ID for <?= htmlspecialchars($application['full_name']) ?>">
                            <a class="eye-id-expand" href="<?= htmlspecialchars($application['id_file']) ?>" target="_blank" rel="noopener">
                                <?= icon('expand') ?> Full Size
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="eye-id-photo-wrap">
                            <div class="eye-id-photo-missing">
                                <?= icon('file') ?>
                                <span>No ID file on record</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>

</body>
</html>