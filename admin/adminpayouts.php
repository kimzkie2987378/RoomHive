<?php
require_once __DIR__ . '/admin_init.php';

/* ---- Action: update payout status ---- */
 $payoutTableExists = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payout_status'], $_POST['payout_id'], $_POST['csrf_token'])) {
    if (hash_equals($csrfToken, $_POST['csrf_token']) && in_array($_POST['payout_status'], ['pending', 'processing', 'completed', 'failed'], true)) {
        try {
            $setProcessed = $_POST['payout_status'] === 'completed' ? ', processed_at = NOW()' : ', processed_at = NULL';
            $pdo->prepare("UPDATE payouts SET status = :s $setProcessed WHERE id = :id")
                ->execute(['s' => $_POST['payout_status'], 'id' => (int) $_POST['payout_id']]);
            admin_flash_set('success', 'Payout updated.');
        } catch (PDOException $e) {
            admin_flash_set('error', 'Could not update that payout.');
        }
    }
    header('Location: /webprogg/admin/adminpayouts.php?status=' . urlencode($_POST['ret_status'] ?? 'all'));
    exit();
}

/* ---- Platform earnings (platform fee = commission) ---- */
 $platformEarnings = (float) $pdo->query(
    "SELECT COALESCE(SUM(platform_fee_amount),0) FROM bookings WHERE status IN ('confirmed','completed')"
)->fetchColumn();
 $hostEarnings = (float) $pdo->query(
    "SELECT COALESCE(SUM(COALESCE(host_payout_amount, amount_paid)),0) FROM bookings WHERE status IN ('confirmed','completed')"
)->fetchColumn();

/* ---- Payouts list ---- */
 $status = $_GET['status'] ?? 'all';
 $valid = ['all', 'pending', 'processing', 'completed', 'failed'];
if (!in_array($status, $valid, true)) $status = 'all';

 $payouts = [];
try {
    $sql = "SELECT p.*, u.name AS host_name
            FROM payouts p JOIN users u ON u.id = p.user_id";
    $params = [];
    if ($status !== 'all') { $sql .= " WHERE p.status = :st"; $params['st'] = $status; }
    $sql .= " ORDER BY p.requested_at DESC LIMIT 100";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $payouts = $st->fetchAll();

    $sums = $pdo->query("SELECT status, COALESCE(SUM(amount),0) s FROM payouts GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $payoutTableExists = false;
    $sums = [];
}

 $pendingAmt  = (float) ($sums['pending'] ?? 0) + (float) ($sums['processing'] ?? 0);
 $completedAmt = (float) ($sums['completed'] ?? 0);

 $flash = admin_flash_take();
?>
<?php admin_page_start('RoomHive Admin — Payouts', 'Payouts'); ?>

<div class="page-heading">
    <h1>Payouts</h1>
    <p>Host withdrawal requests and platform commission overview.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-icon"><?= icon('wallet') ?></div>
        <div class="stat-body">
            <span class="stat-label">Platform Commission</span>
            <div class="stat-value-row"><span class="stat-value">₱<?= h(number_format($platformEarnings, 2)) ?></span></div>
            <span class="stat-caption">From confirmed & completed bookings</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= icon('user-check') ?></div>
        <div class="stat-body">
            <span class="stat-label">Host Earnings</span>
            <div class="stat-value-row"><span class="stat-value">₱<?= h(number_format($hostEarnings, 2)) ?></span></div>
            <span class="stat-caption">Owed / paid to hosts (gross of payouts)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <div class="stat-body">
            <span class="stat-label">Pending Payout Requests</span>
            <div class="stat-value-row"><span class="stat-value">₱<?= h(number_format($pendingAmt, 2)) ?></span></div>
            <span class="stat-caption">₱<?= h(number_format($completedAmt, 2)) ?> already sent</span>
        </div>
    </div>
</div>

<div class="panel">
    <div class="filter-tabs">
        <?php foreach ($valid as $f): ?>
            <a class="filter-tab <?= $status === $f ? 'active' : '' ?>" href="?status=<?= $f ?>"><?= ucfirst($f) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$payoutTableExists): ?>
        <?php emptyState('The `payouts` table does not exist yet — run the CREATE TABLE SQL from the host dashboard setup.'); ?>
    <?php elseif (empty($payouts)): ?>
        <?php emptyState('No payout requests in this view.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead>
                    <tr><th>Host</th><th>Method</th><th>Requested</th><th>Processed</th><th>Amount</th><th>Status</th><th>Update</th></tr>
                </thead>
                <tbody>
                <?php foreach ($payouts as $p): ?>
                    <tr>
                        <td><span class="people-name"><?= h($p['host_name']) ?></span></td>
                        <td><?= h($p['method']) ?></td>
                        <td><?= h(date('M j, Y g:i A', strtotime($p['requested_at']))) ?></td>
                        <td><?= $p['processed_at'] ? h(date('M j, Y g:i A', strtotime($p['processed_at']))) : '—' ?></td>
                        <td><strong>₱<?= h(number_format((float)$p['amount'], 2)) ?></strong></td>
                        <td><span class="badge <?= statusBadgeClass(ucfirst($p['status'])) ?>"><?= h(ucfirst($p['status'])) ?></span></td>
                        <td>
                            <form method="post" style="display:flex; gap:6px;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="ret_status" value="<?= h($status) ?>">
                                <select name="payout_status" class="period-select">
                                    <?php foreach (['pending', 'processing', 'completed', 'failed'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $p['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
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
    <?php endif; ?>
</div>

<?php admin_page_end(); ?>