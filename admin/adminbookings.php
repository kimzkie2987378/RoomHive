<?php
/* =========================================================
   ROOMHIVE ADMIN — BOOKINGS
   adminbookings.php

   FIXED: 'rejected' was missing from the filter whitelist,
   tabs, and the status-override select — bookings declined by
   hosts (via the unified reject flow) only ever appeared
   under "All". Now a first-class tab, and admins can set it
   when correcting a stuck row.

   NOTE: this is an admin override tool — flipping a PAID
   booking to cancelled/rejected here moves no money (no
   wallet refund, no host clawback). It's for fixing stuck
   rows, not for normal cancellations.
========================================================= */

require_once __DIR__ . '/admin_init.php';

/* ---- Status update action ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['booking_id'], $_POST['csrf_token'])) {
    if (hash_equals($csrfToken, $_POST['csrf_token'])) {
        $bookingId = (int) $_POST['booking_id'];
        $newStatus = $_POST['update_status'];
        if (in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed', 'rejected'], true)) {
            $pdo->prepare("UPDATE bookings SET status = :s WHERE id = :id")
                ->execute(['s' => $newStatus, 'id' => $bookingId]);
            admin_flash_set('success', "Booking #$bookingId marked " . ucfirst($newStatus) . '.');
        }
    } else {
        admin_flash_set('error', 'That request could not be verified.');
    }
    header('Location: /webprogg/admin/adminbookings.php?' . http_build_query([
        'status' => $_POST['ret_status'] ?? 'all', 'q' => $_POST['ret_q'] ?? '', 'page' => $_POST['ret_page'] ?? 1,
    ]));
    exit();
}

/* ---- Filters ---- */
 $status = $_GET['status'] ?? 'all';
 $valid  = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'rejected'];
if (!in_array($status, $valid, true)) $status = 'all';
 $q = trim($_GET['q'] ?? '');
 $page = max(1, (int) ($_GET['page'] ?? 1));
 $perPage = 12;

 $where = []; $params = [];
if ($status !== 'all') { $where[] = 'b.status = :st'; $params['st'] = $status; }
if ($q !== '') {
    $where[] = '(l.title LIKE :q OR g.name LIKE :q OR hh.name LIKE :q OR l.location LIKE :q)';
    $params['q'] = "%$q%";
}
 $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

 $countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users g ON g.id = b.user_id
     JOIN users hh ON hh.id = l.user_id
     $whereSql"
);
 $countStmt->execute($params);
 $totalItems = (int) $countStmt->fetchColumn();
 $totalPages = max(1, (int) ceil($totalItems / $perPage));
 $page = min($page, $totalPages);
 $offset = ($page - 1) * $perPage;

/* NOTE: bookings has NO payment_method column — the real column
   is payment_status (enum: pending/paid/failed/cancelled). */
 $stmt = $pdo->prepare(
    "SELECT b.id, b.status, b.amount_paid, b.checkin_date, b.checkout_date, b.guests,
            b.created_at, b.payment_status,
            l.title, l.location,
            g.name AS guest_name,
            hh.name AS host_name
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users g ON g.id = b.user_id
     JOIN users hh ON hh.id = l.user_id
     $whereSql
     ORDER BY b.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
 $stmt->execute($params);
 $bookings = $stmt->fetchAll();

 $counts = $pdo->query(
    "SELECT status, COUNT(*) c FROM bookings GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
 $cnt = fn($k) => (int) ($counts[$k] ?? 0);
 $cntAll = array_sum($counts);

 $flash = admin_flash_take();
 $baseParams = array_filter(['status' => $status, 'q' => $q]);
?>
<?php admin_page_start('RoomHive Admin — Bookings', 'Bookings'); ?>

<div class="page-heading">
    <h1>Bookings</h1>
    <p>Every booking on the platform. You can correct a booking's status if a host or guest is stuck.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<div class="panel">
    <div class="filter-tabs">
        <a class="filter-tab <?= $status === 'all' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'all', 'q' => $q])) ?>"><?= icon('inbox') ?> All (<?= $cntAll ?>)</a>
        <a class="filter-tab tab-pending <?= $status === 'pending' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'pending', 'q' => $q])) ?>"><?= icon('clock') ?> Pending (<?= $cnt('pending') ?>)</a>
        <a class="filter-tab tab-approved <?= $status === 'confirmed' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'confirmed', 'q' => $q])) ?>"><?= icon('check-circle') ?> Confirmed (<?= $cnt('confirmed') ?>)</a>
        <a class="filter-tab <?= $status === 'completed' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'completed', 'q' => $q])) ?>"><?= icon('check-circle') ?> Completed (<?= $cnt('completed') ?>)</a>
        <a class="filter-tab tab-rejected <?= $status === 'cancelled' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'cancelled', 'q' => $q])) ?>"><?= icon('x-circle') ?> Cancelled (<?= $cnt('cancelled') ?>)</a>
        <a class="filter-tab tab-rejected <?= $status === 'rejected' ? 'active' : '' ?>" href="?<?= h(http_build_query(['status' => 'rejected', 'q' => $q])) ?>"><?= icon('x-circle') ?> Rejected (<?= $cnt('rejected') ?>)</a>
    </div>

    <form method="get" style="display:flex; gap:10px; margin-bottom:18px; align-items:center;">
        <input type="hidden" name="status" value="<?= h($status) ?>">
        <div class="search-box" style="max-width:340px;">
            <?= icon('search') ?>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search listing, guest, host, location...">
        </div>
        <button class="view-all" type="submit">Search</button>
    </form>

    <?php if (empty($bookings)): ?>
        <?php emptyState($q !== '' ? 'No bookings match your search.' : 'No bookings yet.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead>
                    <tr><th>Booking</th><th>Guest</th><th>Host</th><th>Stay</th><th>Amount</th><th>Status</th><th>Update</th></tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>
                            <div class="people-info">
                                <span class="people-name">#<?= (int) $b['id'] ?> · <?= h($b['title']) ?></span>
                                <span class="people-sub"><?= h($b['location']) ?> · <?= h(ucfirst($b['payment_status'] ?? '')) ?></span>
                            </div>
                        </td>
                        <td><?= h($b['guest_name']) ?></td>
                        <td><?= h($b['host_name']) ?></td>
                        <td>
                            <?php if (!empty($b['checkin_date'])): ?>
                                <?= h(date('M j, Y', strtotime($b['checkin_date']))) ?>
                                <?php if (!empty($b['checkout_date'])): ?>–<?= h(date('M j, Y', strtotime($b['checkout_date']))) ?><?php endif; ?>
                                <?php if ((int)$b['guests'] > 0): ?><span class="people-sub"> · <?= (int)$b['guests'] ?> guest(s)</span><?php endif; ?>
                            <?php else: ?>
                                <span class="people-sub">Booked <?= h(date('M j, Y', strtotime($b['created_at']))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong>₱<?= h(number_format((float) $b['amount_paid'], 2)) ?></strong></td>
                        <td><span class="badge <?= statusBadgeClass(ucfirst($b['status'])) ?>"><?= h(ucfirst($b['status'])) ?></span></td>
                        <td>
                            <form method="post" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="ret_status" value="<?= h($status) ?>">
                                <input type="hidden" name="ret_q" value="<?= h($q) ?>">
                                <input type="hidden" name="ret_page" value="<?= $page ?>">
                                <select name="update_status" class="period-select">
                                    <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $b['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="view-all">Save</button>
                            </form>
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