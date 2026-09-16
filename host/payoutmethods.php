<?php
require_once __DIR__ . '/host_init.php';

/* Requires `payout_methods` table (SQL at top of this conversation).
   type enum matches your bookings.payment_method options. */

 $tableExists = true;
 $methods = [];
try {
    $st = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = :id ORDER BY is_default DESC, id ASC");
    $st->execute(['id' => $_SESSION['user_id']]);
    $methods = $st->fetchAll();
} catch (PDOException $e) {
    $tableExists = false;
}

 $methodLabels = ['gcash' => 'GCash', 'paymaya' => 'PayMaya', 'bank' => 'Bank Transfer'];
 $methodIcons  = ['gcash' => '📱', 'paymaya' => '💳', 'bank' => '🏦'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_method'])) {
    $type    = $_POST['type'] ?? '';
    $accName = trim($_POST['account_name'] ?? '');
    $accNum  = trim($_POST['account_number'] ?? '');

    if (!isset($methodLabels[$type]) || $accName === '' || $accNum === '') {
        hp_flash_set('error', 'Please fill in all fields.');
    } elseif (!$tableExists) {
        hp_flash_set('error', 'payout_methods table missing — run the CREATE TABLE SQL first.');
    } else {
        try {
            $ins = $pdo->prepare(
                "INSERT INTO payout_methods (user_id, type, account_name, account_number) VALUES (:u,:t,:n,:num)"
            );
            $ins->execute(['u'=>$_SESSION['user_id'],'t'=>$type,'n'=>$accName,'num'=>$accNum]);
            hp_flash_set('success', 'Payout method added.');
        } catch (PDOException $e) {
            hp_flash_set('error', 'Could not save this payout method.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'])) {
    try {
        $pdo->prepare("UPDATE payout_methods SET is_default = 0 WHERE user_id = :u")->execute(['u'=>$_SESSION['user_id']]);
        $pdo->prepare("UPDATE payout_methods SET is_default = 1 WHERE id = :id AND user_id = :u")
            ->execute(['id'=>(int)$_POST['set_default'],'u'=>$_SESSION['user_id']]);
        hp_flash_set('success', 'Default payout method updated.');
    } catch (PDOException $e) {
        hp_flash_set('error', 'Could not update the default method.');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_method'])) {
    try {
        $pdo->prepare("DELETE FROM payout_methods WHERE id = :id AND user_id = :u")
            ->execute(['id'=>(int)$_POST['delete_method'],'u'=>$_SESSION['user_id']]);
        hp_flash_set('success', 'Payout method removed.');
    } catch (PDOException $e) {
        hp_flash_set('error', 'Could not remove this method.');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

 $flash = hp_flash_take();
 $activePage = 'payoutmethods';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payout Methods — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Payout Methods</h1>
                <p class="hp-page-subtitle">Where your earnings get sent when you request a payout.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!$tableExists): ?>
            <div class="hp-flash hp-flash-error">
                The <code>payout_methods</code> table doesn't exist yet — run the CREATE TABLE SQL in phpMyAdmin.
            </div>
        <?php endif; ?>

        <!-- Existing methods -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Your Methods</h3></div>

            <?php if (empty($methods)): ?>
                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No payout methods yet</p>
                    <p>Add one below so you can request payouts.</p>
                </div>
            <?php else: ?>
                <?php foreach ($methods as $m): ?>
                <div class="hp-method-card">
                    <div class="hp-method-icon"><?php echo $methodIcons[$m['type']] ?? '💳'; ?></div>
                    <div class="hp-method-info">
                        <strong>
                            <?php echo h($methodLabels[$m['type']] ?? $m['type']); ?>
                            <?php echo $m['is_default'] ? ' <span class="hp-default-badge">DEFAULT</span>' : ''; ?>
                        </strong>
                        <span><?php echo h($m['account_name']); ?> &middot; <?php echo h($m['account_number']); ?></span>
                    </div>
                    <div class="hp-method-actions">
                        <?php if (!$m['is_default']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="set_default" value="<?php echo (int)$m['id']; ?>">
                            <button type="submit" class="hp-mini-link">Set Default</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this payout method?');">
                            <input type="hidden" name="delete_method" value="<?php echo (int)$m['id']; ?>">
                            <button type="submit" class="hp-mini-link hp-danger">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Add new -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Add a Payout Method</h3></div>
            <form method="POST">
                <div class="hp-form-grid">
                    <div class="hp-field">
                        <label for="type">Type</label>
                        <select class="hp-select" name="type" id="type">
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="hp-field">
                        <label for="account_name">Account Name</label>
                        <input class="hp-input" type="text" name="account_name" id="account_name" required>
                    </div>
                    <div class="hp-field">
                        <label for="account_number">Account / Mobile Number</label>
                        <input class="hp-input" type="text" name="account_number" id="account_number" required>
                    </div>
                </div>
                <div class="hp-form-actions">
                    <button type="submit" name="add_method" value="1" class="hp-btn-primary">ADD METHOD</button>
                </div>
            </form>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>