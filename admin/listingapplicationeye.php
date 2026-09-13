<?php
/**listingapplicationeye.php
 * RoomHive Admin — Listing Application Viewer
 *
 * Reached via the eye icon on listingapplication.php. Shows
 * everything the host filled in across the listing wizard —
 * host-step2.php ("Add Your Space": category, property type,
 * address, price, capacity, amenities, description, house
 * rules) and host-step3.php ("Upload Photos": the cover photo
 * plus every additional photo) — with the actual images shown
 * inline, not just linked.
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

/* ---------- Sidebar navigation (same set as listingapplication.php) ---------- */
 $navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'href' => '/webprogg/admin/adminusers.php'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '/webprogg/admin/adminbookings.php'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '/webprogg/admin/adminlistings.php'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'active' => true, 'href' => '/webprogg/admin/listingapplication.php'],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '/webprogg/admin/adminpayouts.php'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '/webprogg/admin/adminreviews.php'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '/webprogg/admin/adminmessages.php'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '/webprogg/admin/adminreports.php'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '/webprogg/admin/adminsettings.php'],
];

$notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();

/* ---------------------------------------------------------
   INPUT — which listing are we viewing?
   filter/page are only carried along so Back returns the
   admin to exactly where they were in the table.
--------------------------------------------------------- */
$listingId = (int) ($_GET['id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';
$page      = (int) ($_GET['page'] ?? 1);

$backLink = '/webprogg/admin/listingapplication.php?' . http_build_query([
    'filter' => $filter,
    'page'   => $page,
    'id'     => $listingId,
]);

/* ---------------------------------------------------------
   HANDLE APPROVE / REJECT
   Same effect as the buttons on listingapplication.php — kept
   here too so the admin doesn't have to leave the photo/detail
   view just to make the call.
--------------------------------------------------------- */
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

        header('Location: /webprogg/admin/listingapplicationeye.php?' . http_build_query([
            'filter' => $filter,
            'page'   => $page,
            'id'     => $targetId,
        ]));
        exit();
    }
}

/* ---------------------------------------------------------
   LOAD WHAT WAS FILLED IN ON host-step2.php ("Add Your Space")
   Straight from `listings`, joined to `users` for the host's
   contact details and `host_applications` for the applicant
   name captured back in Step 1.
--------------------------------------------------------- */
$listingStmt = $pdo->prepare(
    "SELECT l.id, l.title, l.category, l.property_type, l.location, l.exact_address,
            l.price, l.capacity, l.bedrooms, l.bathrooms, l.size_sqm, l.floor,
            l.parking, l.amenities, l.description, l.house_rules, l.status,
            l.created_at,
            u.name AS host_name, u.email AS host_email, u.phone AS host_phone
     FROM listings l
     JOIN users u ON u.id = l.user_id
     WHERE l.id = :id
     LIMIT 1"
);
$listingStmt->execute(['id' => $listingId]);
$listing = $listingStmt->fetch();

if (!$listing) {
    header('Location: /webprogg/admin/listingapplication.php');
    exit();
}

/* ---------------------------------------------------------
   LOAD WHAT WAS UPLOADED ON host-step3.php ("Upload Photos")
   Cover photo (photo_type = 'cover') pulled separately from
   the additional gallery so the layout can feature it, same
   as it's featured on the listing wizard itself.
--------------------------------------------------------- */
$coverStmt = $pdo->prepare(
    "SELECT photo_path FROM listing_photos WHERE listing_id = :id AND photo_type = 'cover' LIMIT 1"
);
$coverStmt->execute(['id' => $listingId]);
$coverPhoto = $coverStmt->fetchColumn();

$additionalStmt = $pdo->prepare(
    "SELECT photo_path FROM listing_photos WHERE listing_id = :id AND photo_type = 'additional' ORDER BY sort_order ASC"
);
$additionalStmt->execute(['id' => $listingId]);
$additionalPhotos = $additionalStmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Amenity labels (same keys/labels as host-step2.php) ---------- */
$amenityLabels = [
    "wifi"             => "Wi-fi",
    "aircon"           => "Aircon",
    "pet-friendly"     => "Pet Friendly",
    "free-water"       => "Free Water",
    "free-electricity" => "Free Electricity",
    "security"         => "24/7 Security",
];

$selectedAmenities = json_decode($listing['amenities'] ?? '[]', true) ?? [];

function statusBadgeClass($status) {
    $map = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected'];
    return $map[$status] ?? '';
}

$statusLabel = ucfirst($listing['status']);

/* -----------------------------------------------------
   PHOTO PATH FIX
   listing_photos.photo_path is saved relative to /webprogg —
   e.g. "uploads/listing_photos/cover/abc.jpg" or
   "uploads/listing_photos/additional/xyz.jpg". Printed as-is
   (as this file previously did, with no fix applied at all),
   the browser resolves that against the CURRENT page's folder
   (/webprogg/admin/) instead of the site root, so both the
   cover photo and every gallery thumbnail 404'd here — even
   though the same photo_path values render fine on pages that
   already apply this fix (mylistings.php, pendingtenants.php,
   listingpayment.php, booking-details.php, hostprofile.php).
   This forces every photo path back to an absolute, site-root
   path so it loads correctly from any page.
----------------------------------------------------- */
function resolve_photo($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path; // full remote URL — leave it alone
    }
    $normalized = ltrim($path, '/');
    if (stripos($normalized, 'webprogg/') === 0) {
        $normalized = substr($normalized, strlen('webprogg/'));
    }
    return '/webprogg/' . $normalized;
}

/* ---------- Inline icon helper (same set as listingapplication.php) ---------- */
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
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.7"/><path d="m21 16-5.5-5.5L4 21"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'calendar-small' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'expand' => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RoomHive Admin — Listing Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/admin.css">
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
        grid-template-columns: 1fr;
        gap: 20px;
        align-items: start;
        max-width: 1100px;
    }

    .eye-panel {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 22px;
    }

    .eye-panel h3 {
        margin: 0 0 14px;
        font-size: 15px;
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .eye-panel h3 svg { width: 16px; height: 16px; }

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
        grid-template-columns: 1fr 1fr 1fr;
        gap: 14px 18px;
        margin-top: 4px;
    }
    @media (max-width: 700px) {
        .eye-info-grid { grid-template-columns: 1fr 1fr; }
    }
    .eye-info-item .info-label { display: flex; align-items: center; gap: 6px; }
    .eye-info-item .info-label svg { width: 13px; height: 13px; }
    .eye-info-item.full-width { grid-column: 1 / -1; }

    .eye-detail-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); }
    .eye-detail-section h4 { margin: 0 0 8px; font-size: 13px; font-weight: 700; color: var(--text-dark); }
    .eye-detail-text { font-size: 13.5px; line-height: 1.6; color: var(--text-muted); white-space: pre-line; margin: 0; }

    .amenity-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .amenity-tag {
        background: var(--orange-light);
        color: var(--orange-deep);
        font-size: 12.5px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 999px;
    }

    /* ---------- Photo gallery ---------- */
    .eye-cover-wrap {
        position: relative;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--orange-light);
        margin-bottom: 18px;
    }
    .eye-cover-photo {
        width: 100%;
        max-height: 380px;
        object-fit: cover;
        display: block;
        background: #fff;
    }
    .eye-cover-label {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(15,15,20,.65);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 999px;
    }
    .eye-photo-expand, .eye-cover-expand {
        position: absolute; top: 10px; right: 10px;
        background: rgba(15,15,20,.65); color: #fff;
        border-radius: 999px; padding: 6px 10px;
        font-size: 12px; font-weight: 600;
        display: flex; align-items: center; gap: 5px;
        text-decoration: none;
    }
    .eye-photo-expand:hover, .eye-cover-expand:hover { background: rgba(15,15,20,.85); }
    .eye-photo-expand svg, .eye-cover-expand svg { width: 12px; height: 12px; }

    .eye-photo-missing {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; padding: 60px 12px; color: var(--text-soft); font-size: 13px;
    }
    .eye-photo-missing svg { width: 26px; height: 26px; }

    .eye-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
    }
    .eye-photo-thumb {
        position: relative;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm, 10px);
        overflow: hidden;
        aspect-ratio: 1 / 1;
        background: var(--orange-light);
    }
    .eye-photo-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .eye-photo-count {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 12px;
    }

    .eye-submitted-row {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border);
        font-size: 13px;
    }

    .detail-actions { display: flex; gap: 10px; margin-top: 18px; }
    .detail-note {
        display: flex; align-items: center; gap: 6px;
        font-size: 12.5px; color: var(--text-soft); margin-top: 10px;
    }
    .detail-note svg { width: 13px; height: 13px; flex-shrink: 0; }
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
                <?= icon('arrow-left') ?> Back to Listings Application
            </a>

            <div class="page-heading eye-page-heading">
                <h1>
                    <?= htmlspecialchars($listing['title']) ?>
                    <span class="badge <?= statusBadgeClass($statusLabel) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </h1>
                <p>Everything the host filled in on "Add Your Space" and "Upload Photos".</p>
            </div>

            <div class="eye-layout">

                <!-- ============ HOST + PROPERTY DETAILS (Step 2) ============ -->
                <div class="eye-panel">

                    <div class="eye-applicant-header">
                        <div class="eye-avatar"><?= htmlspecialchars(strtoupper(substr($listing['host_name'], 0, 1))) ?></div>
                        <div>
                            <p class="eye-applicant-name">Hosted by <?= htmlspecialchars($listing['host_name']) ?></p>
                        </div>
                    </div>

                    <div class="eye-info-grid">
                        <div class="eye-info-item">
                            <span class="info-label"><?= icon('mail') ?> Host Email</span>
                            <span class="info-value"><?= htmlspecialchars($listing['host_email']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label"><?= icon('phone') ?> Host Phone</span>
                            <span class="info-value"><?= $listing['host_phone'] !== '' && $listing['host_phone'] !== null ? htmlspecialchars($listing['host_phone']) : '&mdash;' ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Category</span>
                            <span class="info-value"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $listing['category']))) ?></span>
                        </div>

                        <div class="eye-info-item">
                            <span class="info-label">Property Type</span>
                            <span class="info-value"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $listing['property_type']))) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Price</span>
                            <span class="info-value">₱<?= htmlspecialchars(number_format((float) $listing['price'], 2)) ?> / month</span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Capacity</span>
                            <span class="info-value"><?= htmlspecialchars($listing['capacity']) ?> Guest(s)</span>
                        </div>

                        <div class="eye-info-item">
                            <span class="info-label">Bedrooms</span>
                            <span class="info-value"><?= htmlspecialchars($listing['bedrooms']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Bathrooms</span>
                            <span class="info-value"><?= htmlspecialchars($listing['bathrooms']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Size</span>
                            <span class="info-value"><?= htmlspecialchars($listing['size_sqm']) ?> sqm</span>
                        </div>

                        <div class="eye-info-item">
                            <span class="info-label">Floor</span>
                            <span class="info-value"><?= htmlspecialchars($listing['floor']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label">Parking</span>
                            <span class="info-value"><?= htmlspecialchars($listing['parking']) ?></span>
                        </div>
                        <div class="eye-info-item">
                            <span class="info-label"><?= icon('pin') ?> City / Location</span>
                            <span class="info-value"><?= htmlspecialchars($listing['location']) ?></span>
                        </div>

                        <div class="eye-info-item full-width">
                            <span class="info-label"><?= icon('pin') ?> Exact Address</span>
                            <span class="info-value"><?= htmlspecialchars($listing['exact_address']) ?></span>
                        </div>
                    </div>

                    <div class="eye-detail-section">
                        <h4>Amenities</h4>
                        <div class="amenity-tags">
                            <?php if (empty($selectedAmenities)): ?>
                                <span class="amenity-tag">None listed</span>
                            <?php else: ?>
                                <?php foreach ($selectedAmenities as $key): ?>
                                    <span class="amenity-tag"><?= htmlspecialchars($amenityLabels[$key] ?? ucwords(str_replace('-', ' ', $key))) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="eye-detail-section">
                        <h4>Description</h4>
                        <p class="eye-detail-text"><?= htmlspecialchars($listing['description']) ?></p>
                    </div>

                    <div class="eye-detail-section">
                        <h4>House Rules</h4>
                        <p class="eye-detail-text"><?= $listing['house_rules'] !== '' ? htmlspecialchars($listing['house_rules']) : 'No house rules added.' ?></p>
                    </div>

                    <div class="eye-submitted-row">
                        <span class="info-label"><?= icon('calendar-small') ?> Submitted on</span>
                        <span class="info-value"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($listing['created_at']))) ?></span>
                    </div>

                    <?php if ($listing['status'] === 'pending'): ?>
                        <div class="detail-actions">
                            <form method="POST" action="<?= htmlspecialchars('/webprogg/admin/listingapplicationeye.php?' . http_build_query(['filter' => $filter, 'page' => $page, 'id' => $listing['id']])) ?>" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $listing['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-reject" onclick="return confirm('Reject the listing \'<?= htmlspecialchars(addslashes($listing['title'])) ?>\'?')">Reject Listing</button>
                            </form>
                            <form method="POST" action="<?= htmlspecialchars('/webprogg/admin/listingapplicationeye.php?' . http_build_query(['filter' => $filter, 'page' => $page, 'id' => $listing['id']])) ?>" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $listing['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-approve" onclick="return confirm('Approve the listing \'<?= htmlspecialchars(addslashes($listing['title'])) ?>\'?')">Approve Listing</button>
                            </form>
                        </div>
                        <div class="detail-note"><?= icon('lock') ?>Approving publishes this listing on RoomHive. The host will be notified of your decision.</div>
                    <?php else: ?>
                        <div class="detail-note"><?= icon('lock') ?>This listing has already been <?= htmlspecialchars(strtolower($statusLabel)) ?>.</div>
                    <?php endif; ?>
                </div>

                <!-- ============ PHOTOS (Step 3) ============ -->
                <div class="eye-panel">
                    <h3><?= icon('image') ?> Uploaded Photos</h3>

                    <?php if (!empty($coverPhoto)): ?>
                        <?php $resolvedCover = resolve_photo($coverPhoto, ''); ?>
                        <div class="eye-cover-wrap">
                            <span class="eye-cover-label">Cover Photo</span>
                            <img class="eye-cover-photo" src="<?= htmlspecialchars($resolvedCover) ?>" alt="Cover photo for <?= htmlspecialchars($listing['title']) ?>">
                            <a class="eye-cover-expand" href="<?= htmlspecialchars($resolvedCover) ?>" target="_blank" rel="noopener">
                                <?= icon('expand') ?> Full Size
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="eye-cover-wrap">
                            <div class="eye-photo-missing">
                                <?= icon('file') ?>
                                <span>No cover photo on record</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="eye-photo-count"><?= count($additionalPhotos) ?> additional photo<?= count($additionalPhotos) === 1 ? '' : 's' ?></p>

                    <?php if (!empty($additionalPhotos)): ?>
                        <div class="eye-photo-grid">
                            <?php foreach ($additionalPhotos as $i => $photoPath): ?>
                                <?php $resolvedPhoto = resolve_photo($photoPath, ''); ?>
                                <a class="eye-photo-thumb" href="<?= htmlspecialchars($resolvedPhoto) ?>" target="_blank" rel="noopener">
                                    <img src="<?= htmlspecialchars($resolvedPhoto) ?>" alt="Photo <?= $i + 1 ?> for <?= htmlspecialchars($listing['title']) ?>">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="eye-photo-missing">
                            <?= icon('image') ?>
                            <span>No additional photos uploaded</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>

</body>
</html>