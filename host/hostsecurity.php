<?php
require_once __DIR__ . '/host_init.php';

 $userId = (int) $_SESSION['user_id'];

/* Fetch password hash + real 2FA flag */
 $secStmt = $pdo->prepare("SELECT password, two_factor_enabled FROM users WHERE id = :id LIMIT 1");
 $secStmt->execute(['id' => $userId]);
 $secRow = $secStmt->fetch();
 $storedHash = (string) ($secRow['password'] ?? '');
 $twoFactorEnabled = (int) ($secRow['two_factor_enabled'] ?? 0);

/* ---- Toggle 2FA flag (real column; login enforcement is a separate task) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    $new = $twoFactorEnabled ? 0 : 1;
    $pdo->prepare("UPDATE users SET two_factor_enabled = :v WHERE id = :id")
        ->execute(['v' => $new, 'id' => $userId]);
    hp_flash_set('success', $new
        ? 'Two-factor authentication enabled for your account.'
        : 'Two-factor authentication disabled.');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ---- Change password ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        hp_flash_set('error', 'Please fill in all password fields.');
    } elseif (!password_verify($current, $storedHash)) {
        hp_flash_set('error', 'Your current password is incorrect.');
    } elseif (strlen($new) < 8) {
        hp_flash_set('error', 'New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        hp_flash_set('error', 'New password and confirmation do not match.');
    } elseif (password_verify($new, $storedHash)) {
        hp_flash_set('error', 'New password must be different from your current one.');
    } else {
        $up = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
        $up->execute(['p' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]);
        hp_flash_set('success', 'Password changed successfully.');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

 $flash = hp_flash_take();
 $activePage = 'security';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security — RoomHive</title>
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
                <h1 class="hp-page-title">Security</h1>
                <p class="hp-page-subtitle">Protect your host account.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Change password -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Change Password</h3></div>
            <form method="POST">
                <div class="hp-form-grid">
                    <div class="hp-field">
                        <label for="current_password">Current Password</label>
                        <input class="hp-input" type="password" name="current_password" id="current_password" required>
                    </div>
                    <div></div>
                    <div class="hp-field">
                        <label for="new_password">New Password</label>
                        <input class="hp-input" type="password" name="new_password" id="new_password" minlength="8" required>
                    </div>
                    <div class="hp-field">
                        <label for="confirm_password">Confirm New Password</label>
                        <input class="hp-input" type="password" name="confirm_password" id="confirm_password" minlength="8" required>
                    </div>
                </div>
                <div class="hp-form-actions">
                    <button type="submit" name="change_password" value="1" class="hp-btn-primary">UPDATE PASSWORD</button>
                </div>
            </form>
        </section>

        <!-- Two-factor (persists to users.two_factor_enabled) -->
        <section class="hp-card">
            <div class="hp-card-header">
                <h3>Two-Factor Authentication</h3>
                <span class="hp-soon-badge"><?php echo $twoFactorEnabled ? 'ENABLED' : 'DISABLED'; ?></span>
            </div>
            <div class="hp-setting-row">
                <div>
                    <strong>Two-factor authentication</strong>
                    <p>Requires a verification code at login. Preference is saved to your account — code delivery at login is coming soon.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="toggle_2fa" value="1" class="hp-btn-outline">
                        <?php echo $twoFactorEnabled ? 'TURN OFF' : 'TURN ON'; ?>
                    </button>
                </form>
            </div>
        </section>

        <!-- Active sessions -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Active Sessions</h3></div>
            <div class="hp-session-row">
                <span class="hp-session-dot"></span>
                <div style="flex:1;">
                    <strong>This device — current session</strong>
                    <p>You are logged in here right now.</p>
                </div>
            </div>
            <div class="hp-form-actions">
                <a href="/webprogg/auth/logout.php" class="hp-btn-danger-outline" style="text-decoration:none;">LOG OUT</a>
            </div>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>