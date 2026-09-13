<?php
require_once __DIR__ . '/host_init.php';

/* Requires the `payouts` table (SQL at top of this conversation).
   Available balance = confirmed/completed earnings minus
   completed/processing and pending payouts. */

 $totalEarnings = hp_total_earnings($pdo, $_SESSION['user_id']);

 $payouts = [];
 $tableExists = true;
try {
    $st = $pdo->prepare("SELECT * FROM payouts WHERE user_id = :id ORDER BY requested_at DESC");
    $st->execute(['id' => $_SESSION['user_id']]);
    $payouts = $st->fetchAll();
} catch (PDOException $e) {
    $tableExists = false;
}

 $paidOut = 0.0;
 $pendingOut = 0.0;
foreach ($payouts as $p) {
    if ($p['status'] === 'completed' || $p['status'] === 'processing') $paidOut += (float) $p['amount'];
    if ($p['status'] === 'pending') $pendingOut += (float) $p['amount'];
}
 $available = max(0, $totalEarnings - $paidOut - $pendingOut);

function hp_payout_status_class($status) {
    switch ($status) {
        case 'completed':  return 'hp-status-active';
        case 'processing': return 'hp-status-pending';
        case 'pending':    return 'hp-status-pending';
        case 'failed':     return 'hp-status-rejected';
        default:           return 'hp-status-unlisted';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? 'GCash');

    if (!$tableExists) {
        hp_flash_set('error', 'Payouts table missing — run the CREATE TABLE SQL first.');
    } elseif ($amount < 500) {
        hp_flash_set('error', 'Minimum payout request is ₱500.');
    } elseif ($amount > $available) {
        hp_flash_set('error', 'Requested amount exceeds your available balance of ₱' . number_format($available, 2) . '.');
    } else {
        try {
            $ins = $pdo->prepare(
                "INSERT INTO payouts (user_id, amount, method, status) VALUES (:u, :a, :m, 'pending')"
            );
            $ins->execute(['u' => $_SESSION['user_id'], 'a' => $amount, 'm' => $method]);
            hp_flash_set('success', 'Payout request of ₱' . number_format($amount, 2) . ' submitted for review.');
        } catch (PDOException $e) {
            hp_flash_set('error', 'Could not submit your payout request.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

 $flash = hp_flash_take();
 $activePage = 'payouts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payouts — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Payouts</h1>
                <p class="hp-page-subtitle">Withdraw your earnings to your payout method.</p>
            </div>
            <div class="hp-stat-pillbox">
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon"><img src="/webprogg/images/totalspenticon-userprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">Available</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($available, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-yellow"><img src="/webprogg/images/paymentsicon-userprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">Pending</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($pendingOut, 2)); ?></p>
                    </div>
                </div>
                <div class="hp-stat-pill-item">
                    <div class="hp-stat-pill-icon hp-green"><img src="/webprogg/images/viewsicon-hostprofile.png" alt=""></div>
                    <div>
                        <p class="hp-stat-pill-label">Paid Out</p>
                        <p class="hp-stat-pill-value">&#8369; <?php echo h(number_format($paidOut, 2)); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Request payout -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>Request a Payout</h3>
                <a href="/webprogg/host/payoutmethods.php" class="hp-link-view-all">Manage Payout Methods</a>
            </div>
            <form method="POST">
                <div class="hp-form-grid">
                    <div class="hp-field">
                        <label for="amount">Amount (&#8369;)</label>
                        <input class="hp-input" type="number" name="amount" id="amount"
                               min="500" max="<?php echo (int) floor($available); ?>" step="0.01"
                               value="<?php echo (int) floor($available); ?>" required>
                    </div>
                    <div class="hp-field">
                        <label for="method">Payout Method</label>
                        <select class="hp-select" name="method" id="method">
                            <option>GCash</option>
                            <option>PayMaya</option>
                            <option>Bank Transfer</option>
                        </select>
                    </div>
                </div>
                <div class="hp-form-actions">
                    <button type="submit" name="request_payout" value="1" class="hp-btn-primary">REQUEST PAYOUT</button>
                    <span class="hp-muted" style="align-self:center;">Minimum &#8369;500 &middot; processed within 1&ndash;3 business days</span>
                </div>
            </form>
        </section>

        <!-- History -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Payout History</h3></div>

            <?php if (!$tableExists): ?>
                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">Payouts table not created yet</p>
                    <p>Run the CREATE TABLE SQL in phpMyAdmin first.</p>
                </div>
            <?php elseif (empty($payouts)): ?>
                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No payouts yet</p>
                    <p>Your payout requests and their status will show up here.</p>
                </div>
            <?php else: ?>
                <table class="hp-table">
                    <thead>
                        <tr><th>Requested</th><th>Method</th><th>Status</th><th style="text-align:right;">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payouts as $p): ?>
                        <tr>
                            <td><?php echo h(date('M j, Y', strtotime($p['requested_at']))); ?></td>
                            <td><?php echo h($p['method']); ?></td>
                            <td><span class="hp-status <?php echo hp_payout_status_class($p['status']); ?>"><?php echo h(ucfirst($p['status'])); ?></span></td>
                            <td class="hp-amount" style="text-align:right;">&#8369; <?php echo h(number_format((float)$p['amount'], 2)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>