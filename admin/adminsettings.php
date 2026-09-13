<?php
require_once __DIR__ . '/admin_init.php';

/* ---- Load settings ---- */
 $defaults = [
    'site_name'        => 'RoomHive',
    'support_email'    => 'hello@roomhive.ph',
    'support_phone'    => '0927 569 3574',
    'maintenance_mode' => '0',
];
 $settings = $defaults;
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM platform_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $rows);
    $settingsTableExists = true;
} catch (PDOException $e) {
    $settingsTableExists = false;
}

/* ---- Save ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'], $_POST['csrf_token'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'])) {
        admin_flash_set('error', 'That request could not be verified.');
    } elseif (!$settingsTableExists) {
        admin_flash_set('error', 'platform_settings table missing — run the CREATE TABLE SQL first.');
    } else {
        try {
            $siteName = trim($_POST['site_name'] ?? '');
            $supEmail = trim($_POST['support_email'] ?? '');
            $supPhone = trim($_POST['support_phone'] ?? '');
            $maint    = isset($_POST['maintenance_mode']) ? '1' : '0';

            if ($siteName === '') {
                admin_flash_set('error', 'Site name cannot be empty.');
            } elseif (!filter_var($supEmail, FILTER_VALIDATE_EMAIL)) {
                admin_flash_set('error', 'Support email must be a valid email address.');
            } else {
                $up = $pdo->prepare(
                    "INSERT INTO platform_settings (setting_key, setting_value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
                );
                $up->execute([':k' => 'site_name', ':v' => $siteName]);
                $up->execute([':k' => 'support_email', ':v' => $supEmail]);
                $up->execute([':k' => 'support_phone', ':v' => $supPhone]);
                $up->execute([':k' => 'maintenance_mode', ':v' => $maint]);
                admin_flash_set('success', 'Settings saved.');
            }
        } catch (PDOException $e) {
            admin_flash_set('error', 'Could not save settings.');
        }
    }
    header('Location: /webprogg/admin/adminsettings.php');
    exit();
}

 $flash = $settingsTableExists ? admin_flash_take() : admin_flash_take();
?>
<?php admin_page_start('RoomHive Admin — Settings', 'Settings'); ?>

<div class="page-heading">
    <h1>Settings</h1>
    <p>Platform-wide configuration and your admin account.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<div class="grid-3" style="grid-template-columns: 1.4fr 1fr;">
    <div class="panel">
        <div class="panel-header"><h2>Platform Settings</h2></div>
        <?php if (!$settingsTableExists): ?>
            <?php emptyState('Run the platform_settings CREATE TABLE SQL first.'); ?>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="info-grid" style="gap:16px;">
                <div class="info-item">
                    <span class="info-label">Site Name</span>
                    <input class="period-select" style="width:100%; padding:9px 12px;" type="text" name="site_name" value="<?= h($settings['site_name']) ?>" required>
                </div>
                <div class="info-item">
                    <span class="info-label">Support Email</span>
                    <input class="period-select" style="width:100%; padding:9px 12px;" type="email" name="support_email" value="<?= h($settings['support_email']) ?>" required>
                </div>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <span class="info-label">Support Phone</span>
                    <input class="period-select" style="width:100%; padding:9px 12px;" type="text" name="support_phone" value="<?= h($settings['support_phone']) ?>">
                </div>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
                <div>
                    <span class="people-name">Maintenance Mode</span>
                    <div class="stat-caption">Shows a maintenance notice to guests and hosts (frontend wiring optional).</div>
                </div>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
                    <span class="stat-caption">Enabled</span>
                </label>
            </div>
            <div style="margin-top:18px;">
                <button type="submit" name="save_settings" value="1" class="btn-approve" style="padding:11px 22px;">Save Settings</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Admin Account</h2></div>
        <div class="detail-profile">
            <div class="admin-avatar admin-avatar-fallback" style="width:64px;height:64px;font-size:22px;">
                <?= h(strtoupper(substr($adminName, 0, 1))) ?>
            </div>
            <div>
                <p class="detail-name"><?= h($adminName) ?></p>
                <div class="detail-meta-row"><?= icon('mail') ?><?= h($adminEmail ?: 'No email on session') ?></div>
                <div class="detail-meta-row"><?= icon('lock') ?>Administrator</div>
            </div>
        </div>
        <div class="detail-section">
            <h3>Session</h3>
            <div class="doc-list">
                <div class="doc-item"><?= icon('clock') ?>Logged in this browser session</div>
            </div>
        </div>
        <div class="detail-actions">
            <a href="/webprogg/auth/logout.php" class="btn-reject" style="text-align:center; text-decoration:none;">Log Out</a>
        </div>
    </div>
</div>

<?php admin_page_end(); ?>