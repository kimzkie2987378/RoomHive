<?php
/**
 * hostapplicationeye.php
 * RoomHive Admin — Become a Host Application Viewer
 *
 * Reached via the eye icon on hostapplication.php. Shows what the
 * applicant filled in on becomeahost.php — full name, email, phone,
 * location, ID type/number — plus the actual uploaded ID photo shown
 * inline, not just a link.
 *
 * FIXES (this version):
 * - ID PHOTO 404 FIXED: host_applications.id_file is stored relative
 *   to /webprogg (same convention as listing_photos.photo_path).
 *   Printed raw, the browser resolved it against /webprogg/admin/
 *   and the ID image 404'd. Now forced to a site-root absolute path.
 * - CSRF token added to any POST (defensive; this page has no forms
 *   today but the token is available if approve/reject move here).
 * - Boots through the shared admin_init.php shell — sidebar, topbar
 *   and admin dropdown come from admin_page_start().
 */

require_once __DIR__ . '/admin_init.php';

/* icon(), h(), statusBadgeClass(), admin_page_start(), admin_page_end(),
   $navItems, $csrfToken, $adminName, $adminEmail, $pdo all come from
   admin_init.php — do NOT redefine them here. */

/* ---------------------------------------------------------
   INPUT — which application are we viewing?
   filter/page are only carried along so Back returns the
   admin to exactly where they were in the table.
--------------------------------------------------------- */
 $applicationId = (int) ($_GET['id'] ?? 0);
 $filter        = $_GET['filter'] ?? 'all';
 $page          = max(1, (int) ($_GET['page'] ?? 1));

 $backLink = '/webprogg/admin/hostapplication.php?' . http_build_query([
    'filter' => $filter,
    'page'   => $page,
    'id'     => $applicationId,
]);

/* ---------------------------------------------------------
   LOAD WHAT WAS FILLED IN ON becomeahost.php
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
    header('Location: /webprogg/admin/hostapplication.php');
    exit();
}

 $statusLabel = ucfirst($application['status']);

/* ---------- ID PHOTO PATH FIX ----------
   host_applications.id_file is stored relative to /webprogg
   (same convention as listing_photos.photo_path). Printed raw,
   the browser resolves it against /webprogg/admin/ and the ID
   photo 404s — the same bug listingapplicationeye.php already
   fixed for listing photos. admin_resolve_photo() comes from
   admin_init.php and handles every stored format.
----------------------------------------- */
 $idFileUrl = admin_resolve_photo($application['id_file'] ?? '', '');

/* ---------- Page-specific CSS ---------- */
 $extraCss = <<<CSS
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
CSS;

admin_page_start('RoomHive Admin — Host Application', 'Host Applications', $extraCss);
?>

<a href="<?= h($backLink) ?>" class="eye-back-link">
    <?= icon('arrow-left') ?> Back to Host Applications
</a>

<div class="page-heading eye-page-heading">
    <h1>
        <?= h($application['full_name']) ?>'s Host Application
        <span class="badge <?= statusBadgeClass($statusLabel) ?>"><?= h($statusLabel) ?></span>
    </h1>
    <p>Exactly what was filled in on the "Become a Host" form.</p>
</div>

<div class="eye-layout">

    <!-- ============ LEFT: form fields ============ -->
    <div class="eye-panel">

        <div class="eye-applicant-header">
            <div class="eye-avatar"><?= h(strtoupper(substr($application['full_name'], 0, 1))) ?></div>
            <div>
                <p class="eye-applicant-name"><?= h($application['full_name']) ?></p>
            </div>
        </div>

        <div class="eye-info-grid">
            <div class="eye-info-item">
                <span class="info-label"><?= icon('mail') ?> Email</span>
                <span class="info-value"><?= h($application['email']) ?></span>
            </div>
            <div class="eye-info-item">
                <span class="info-label"><?= icon('phone') ?> Phone</span>
                <span class="info-value"><?= h($application['phone']) ?></span>
            </div>
            <div class="eye-info-item">
                <span class="info-label">Age</span>
                <span class="info-value"><?= h($application['age']) ?></span>
            </div>
            <div class="eye-info-item full-width">
                <span class="info-label"><?= icon('pin') ?> Location</span>
                <span class="info-value"><?= h($application['location']) ?></span>
            </div>
            <div class="eye-info-item">
                <span class="info-label">ID Type</span>
                <span class="info-value"><?= h(ucwords(str_replace('-', ' ', $application['id_type']))) ?></span>
            </div>
            <div class="eye-info-item">
                <span class="info-label">ID Number</span>
                <span class="info-value"><?= h($application['id_number']) ?></span>
            </div>
        </div>

        <div class="eye-submitted-row">
            <span class="info-label"><?= icon('calendar-small') ?> Submitted on</span>
            <span class="info-value"><?= h(date('M j, Y g:i A', strtotime($application['created_at']))) ?></span>
        </div>
    </div>

    <!-- ============ RIGHT: uploaded ID photo ============ -->
    <div class="eye-panel">
        <span class="info-label" style="display:block; margin-bottom:10px;">Uploaded ID</span>

        <?php if ($idFileUrl !== ''): ?>
            <div class="eye-id-photo-wrap">
                <img class="eye-id-photo" src="<?= h($idFileUrl) ?>" alt="Uploaded ID for <?= h($application['full_name']) ?>">
                <a class="eye-id-expand" href="<?= h($idFileUrl) ?>" target="_blank" rel="noopener">
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

<?php admin_page_end(); ?>