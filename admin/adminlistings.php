<?php
/* =========================================================
   ROOMHIVE ADMIN — LISTINGS
   adminlistings.php

   FIXED:
   1. Approve / reject / unlist / delete never notified the
      host — now they do (bell notification, same wording as
      listingapplication.php's decisions).
   2. Delete DESTROYED conversations + messages. It now
      DETACHES them (listing_id = NULL) so the (tenant, host)
      chat history survives — matching listingapplication.php.
========================================================= */

require_once __DIR__ . '/admin_init.php';

/* ---- Standalone notifier ---- */
if (!function_exists('admin_notify_user')) {
    function admin_notify_user($pdo, $userId, $message, $link) {
        try {
            if ((int) $userId <= 0 || trim((string) $message) === '') { return false; }
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                 VALUES (:u, :m, :l, 0, NOW())"
            );
            $stmt->execute([
                'u' => (int) $userId,
                'm' => mb_substr(trim((string) $message), 0, 240),
                'l' => (string) $link,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('adminlistings notify failed: ' . $e->getMessage());
            return false;
        }
    }
}

/* ---- Actions: approve / reject / unlist / delete ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['listing_id'], $_POST['csrf_token'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'])) {
        admin_flash_set('error', 'That request could not be verified.');
        header('Location: /webprogg/admin/adminlistings.php');
        exit();
    }
    $id     = (int) $_POST['listing_id'];
    $action = $_POST['action'];

    /* owner + title needed for notifications */
    $ownerStmt = $pdo->prepare("SELECT user_id, title FROM listings WHERE id = :id LIMIT 1");
    $ownerStmt->execute(['id' => $id]);
    $listingRow = $ownerStmt->fetch();

    if (in_array($action, ['approve', 'reject', 'unlist'], true)) {
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'unlist' => 'unlisted'];
        $pdo->prepare("UPDATE listings SET status = :s, updated_at = NOW() WHERE id = :id")
            ->execute(['s' => $map[$action], 'id' => $id]);
        admin_flash_set('success', "Listing #$id marked " . $map[$action] . '.');

        if ($listingRow) {
            if ($action === 'approve') {
                admin_notify_user(
                    $pdo, (int) $listingRow['user_id'],
                    'Your listing "' . $listingRow['title'] . '" was approved and is now live on RoomHive.',
                    '/webprogg/Listings/listing-detail.php?id=' . $id
                );
            } elseif ($action === 'reject') {
                admin_notify_user(
                    $pdo, (int) $listingRow['user_id'],
                    'Your listing "' . $listingRow['title'] . '" was not approved. Edit and resubmit it from My Listings.',
                    '/webprogg/user/mylistings.php'
                );
            } else {
                admin_notify_user(
                    $pdo, (int) $listingRow['user_id'],
                    'Your listing "' . $listingRow['title'] . '" was unlisted by an administrator and is hidden from search.',
                    '/webprogg/user/mylistings.php'
                );
            }
        }
    } elseif ($action === 'delete') {
        $pdo->beginTransaction();
        try {
            /* DETACH conversations instead of deleting them — the
               (tenant, host) chat may still matter outside this
               listing. */
            $pdo->prepare("UPDATE conversations SET listing_id = NULL WHERE listing_id = :id")
                ->execute(['id' => $id]);

            foreach (['listing_photos', 'bookings', 'reviews', 'wishlist'] as $tbl) {
                $pdo->prepare("DELETE FROM $tbl WHERE listing_id = :id")->execute(['id' => $id]);
            }
            $pdo->prepare("DELETE FROM listings WHERE id = :id")->execute(['id' => $id]);
            $pdo->commit();
            admin_flash_set('success', "Listing #$id permanently deleted.");

            if ($listingRow) {
                admin_notify_user(
                    $pdo, (int) $listingRow['user_id'],
                    'Your listing "' . $listingRow['title'] . '" was removed by an administrator.',
                    '/webprogg/user/mylistings.php'
                );
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            admin_flash_set('error', 'Could not delete that listing.');
        }
    }
    header('Location: /webprogg/admin/adminlistings.php?' . http_build_query([
        'status' => $_POST['ret_status'] ?? 'all', 'page' => $_POST['ret_page'] ?? 1,
    ]));
    exit();
}

/* ---- Filters ---- */
 $status = $_GET['status'] ?? 'all';
 $valid  = ['all', 'pending', 'approved', 'rejected', 'unlisted'];
if (!in_array($status, $valid, true)) $status = 'all';
 $page = max(1, (int) ($_GET['page'] ?? 1));
 $perPage = 12;

 $where = []; $params = [];
if ($status !== 'all') { $where[] = 'l.status = :st'; $params['st'] = $status; }
 $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

 $countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings l $whereSql");
 $countStmt->execute($params);
 $totalItems = (int) $countStmt->fetchColumn();
 $totalPages = max(1, (int) ceil($totalItems / $perPage));
 $page = min($page, $totalPages);
 $offset = ($page - 1) * $perPage;

 $stmt = $pdo->prepare(
    "SELECT l.id, l.title, l.location, l.property_type, l.price, l.status, l.created_at,
            u.name AS host_name,
            (SELECT photo_path FROM listing_photos lp WHERE lp.listing_id = l.id AND lp.photo_type = 'cover' LIMIT 1) AS cover_photo,
            (SELECT COUNT(*) FROM listing_photos lp WHERE lp.listing_id = l.id) AS photo_count,
            (SELECT COUNT(*) FROM bookings b WHERE b.listing_id = l.id) AS booking_count
     FROM listings l
     JOIN users u ON u.id = l.user_id
     $whereSql
     ORDER BY l.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
 $stmt->execute($params);
 $listings = $stmt->fetchAll();

 $counts = $pdo->query("SELECT status, COUNT(*) c FROM listings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
 $cnt = fn($k) => (int) ($counts[$k] ?? 0);
 $cntAll = array_sum($counts);

 $flash = admin_flash_take();
 $baseParams = array_filter(['status' => $status]);
?>
<?php admin_page_start('RoomHive Admin — Listings', 'Listings'); ?>

<div class="page-heading">
    <h1>Listings</h1>
    <p>All properties on the platform — approve, unlist, or remove them.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<div class="panel">
    <div class="filter-tabs">
        <a class="filter-tab <?= $status === 'all' ? 'active' : '' ?>" href="?status=all"><?= icon('tag') ?> All (<?= $cntAll ?>)</a>
        <a class="filter-tab tab-pending <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending"><?= icon('clock') ?> Pending (<?= $cnt('pending') ?>)</a>
        <a class="filter-tab tab-approved <?= $status === 'approved' ? 'active' : '' ?>" href="?status=approved"><?= icon('check-circle') ?> Approved (<?= $cnt('approved') ?>)</a>
        <a class="filter-tab tab-rejected <?= $status === 'rejected' ? 'active' : '' ?>" href="?status=rejected"><?= icon('x-circle') ?> Rejected (<?= $cnt('rejected') ?>)</a>
        <a class="filter-tab <?= $status === 'unlisted' ? 'active' : '' ?>" href="?status=unlisted"><?= icon('eye') ?> Unlisted (<?= $cnt('unlisted') ?>)</a>
    </div>

    <?php if (empty($listings)): ?>
        <?php emptyState('No listings match this filter.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead>
                    <tr><th>Listing</th><th>Host</th><th>Price</th><th>Activity</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($listings as $l): ?>
                    <tr>
                        <td>
                            <div class="applicant-cell">
                                <img src="<?= h(admin_resolve_photo($l['cover_photo'], '/webprogg/images/ListingPlaceholder.png')) ?>" alt="" style="border-radius:10px;">
                                <div class="people-info">
                                    <span class="people-name"><?= h($l['title']) ?></span>
                                    <span class="people-sub"><?= h($l['location']) ?> · <?= (int)$l['photo_count'] ?> photo(s)</span>
                                </div>
                            </div>
                        </td>
                        <td><?= h($l['host_name']) ?></td>
                        <td><strong>₱<?= h(number_format((float)$l['price'])) ?></strong><span class="people-sub">/mo</span></td>
                        <td><?= (int)$l['booking_count'] ?> booking(s)<br><span class="people-sub"><?= h(date('M j, Y', strtotime($l['created_at']))) ?></span></td>
                        <td><span class="badge <?= statusBadgeClass(ucfirst($l['status'])) ?>"><?= h(ucfirst($l['status'])) ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <a class="btn-view" href="/webprogg/admin/listingapplicationeye.php?id=<?= (int)$l['id'] ?>" aria-label="View"><?= icon('eye') ?></a>
                                <span class="action-divider"></span>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                    <input type="hidden" name="ret_status" value="<?= h($status) ?>">
                                    <input type="hidden" name="ret_page" value="<?= $page ?>">
                                    <?php if ($l['status'] !== 'approved'): ?>
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="view-all" onclick="return confirm('Approve this listing?');">Approve</button>
                                    <?php elseif ($l['status'] === 'approved'): ?>
                                        <input type="hidden" name="action" value="unlist">
                                        <button type="submit" class="view-all" onclick="return confirm('Unlist this listing? It will be hidden from search.');">Unlist</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                    <input type="hidden" name="ret_status" value="<?= h($status) ?>">
                                    <input type="hidden" name="ret_page" value="<?= $page ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="view-all" style="border-color:var(--red);color:var(--red);" onclick="return confirm('Permanently delete this listing, its photos, bookings and reviews? Conversations are kept but detached.');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php admin_pagination($page, $totalPages, $baseParams); ?>
    <?php endif; ?>
</div>

<?php admin_page_end(); ?>