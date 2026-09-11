<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   security.php

   Security + account preferences: change password, toggle
   2FA, set language/currency, and deactivate the account.

   Schema (per phpMyAdmin designer view of `users`):
   - users.password              — the hashed password column
                                    (NOT password_hash — that
                                    column doesn't exist)
   - users.two_factor_enabled, users.language, users.currency,
     users.status — added via:

       ALTER TABLE users
         ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
         ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en',
         ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
         ADD COLUMN status ENUM('active','deactivated') NOT NULL DEFAULT 'active';

   Adjust the column names below if the real schema differs
   further.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
----------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id, name, email, password, avatar_path, is_host,
            two_factor_enabled, language, currency
     FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

$navAvatar = sync_user_session($dbUser);

$notification_count = 0;
$activeSidebar = 'security';

$two_factor_enabled = (bool) ($dbUser['two_factor_enabled'] ?? false);
$language = $dbUser['language'] ?? 'en';
$currency = $dbUser['currency'] ?? 'PHP';

$passwordErrors = [];
$passwordSaved  = false;
$prefsSaved     = false;

/* -----------------------------------------------------
   CHANGE PASSWORD
----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'password') {
    if (!csrf_verify()) {
        $passwordErrors[] = 'Your session expired. Please try again.';
    }

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($passwordErrors) && !password_verify($current, $dbUser['password'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $passwordErrors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $passwordErrors[] = 'New password and confirmation do not match.';
    }

    if (empty($passwordErrors)) {
        $updateStmt = $pdo->prepare("UPDATE users SET password = :hash WHERE id = :id");
        $updateStmt->execute([
            'hash' => password_hash($new, PASSWORD_DEFAULT),
            'id'   => $_SESSION['user_id'],
        ]);
        $passwordSaved = true;
    }
}

/* -----------------------------------------------------
   2FA + LANGUAGE + CURRENCY
----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'preferences' && csrf_verify()) {
    $two_factor_enabled = isset($_POST['two_factor_enabled']);
    $language = $_POST['language'] ?? 'en';
    $currency = $_POST['currency'] ?? 'PHP';

    $updateStmt = $pdo->prepare(
        "UPDATE users SET two_factor_enabled = :tfa, language = :language, currency = :currency WHERE id = :id"
    );
    $updateStmt->execute([
        'tfa'      => $two_factor_enabled ? 1 : 0,
        'language' => $language,
        'currency' => $currency,
        'id'       => $_SESSION['user_id'],
    ]);

    $prefsSaved = true;
}

/* -----------------------------------------------------
   DEACTIVATE ACCOUNT
   Requires the exact word DEACTIVATE typed in, on top of
   the current password, so this can't be triggered by
   accident.
----------------------------------------------------- */
$deactivateErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'deactivate') {
    if (!csrf_verify()) {
        $deactivateErrors[] = 'Your session expired. Please try again.';
    }

    $confirmWord = trim($_POST['confirm_word'] ?? '');
    $currentPw   = $_POST['deactivate_password'] ?? '';

    if (empty($deactivateErrors) && !password_verify($currentPw, $dbUser['password'])) {
        $deactivateErrors[] = 'Password is incorrect.';
    }
    if (strtoupper($confirmWord) !== 'DEACTIVATE') {
        $deactivateErrors[] = 'Type DEACTIVATE to confirm.';
    }

    if (empty($deactivateErrors)) {
        $deactivateStmt = $pdo->prepare("UPDATE users SET status = 'deactivated' WHERE id = :id");
        $deactivateStmt->execute(['id' => $_SESSION['user_id']]);

        session_destroy();
        header("Location: /webprogg/auth/loginform.php?deactivated=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
</head>
<body>

<header class="navbar">
    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>

    <nav class="nav-links">
        <a href="/webprogg/user/usershome.php">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="/webprogg/host/becomeahost.php">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>

        <a href="notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>

        <div class="account-dropdown js-account-dropdown">
            <button type="button" class="my-account js-account-toggle" id="accountDropdownToggle" aria-haspopup="true" aria-expanded="false">
                <span class="account-circle">
                    <img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg">
                </span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>

            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <?php if ($dbUser['is_host']): ?>
                    <a href="/webprogg/host/hostprofile.php">Host Profile</a>
                <?php endif; ?>
                <a href="/webprogg/user/userprofile.php">My Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
</header>

<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Settings</p>
    <h1>Security &amp; account preferences</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Manage your password, two-factor login, and account defaults.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/lockicon-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <div class="up-two-col">

      <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- CHANGE PASSWORD -->
        <section class="up-card up-account-security">
          <div class="up-card-header">
            <h3>Change Password</h3>
          </div>

          <?php if ($passwordSaved): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#eaf7ee; border:1px solid #2f9e5c; color:#1f6b3b; font-size:13px; margin-bottom:14px;">
              Your password has been updated.
            </div>
          <?php endif; ?>

          <?php if (!empty($passwordErrors)): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#fdeceb; border:1px solid #e0524d; color:#a1332e; font-size:13px; margin-bottom:14px;">
              <?php foreach ($passwordErrors as $error): ?>
                <p style="margin:0;"><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="security.php" style="display:flex; flex-direction:column; gap:12px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="password">

            <label style="display:block;">
              <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Current Password</span>
              <input type="password" name="current_password" required
                     style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
            </label>

            <label style="display:block;">
              <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">New Password</span>
              <input type="password" name="new_password" required minlength="8"
                     style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
            </label>

            <label style="display:block;">
              <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Confirm New Password</span>
              <input type="password" name="confirm_password" required minlength="8"
                     style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
            </label>

            <button type="submit" class="up-btn-solid" style="align-self:flex-start;">UPDATE PASSWORD</button>
          </form>
        </section>

        <!-- 2FA + LANGUAGE + CURRENCY -->
        <section class="up-card up-account-security">
          <div class="up-card-header">
            <h3>Account Preferences</h3>
          </div>

          <?php if ($prefsSaved): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#eaf7ee; border:1px solid #2f9e5c; color:#1f6b3b; font-size:13px; margin-bottom:14px;">
              Your preferences have been saved.
            </div>
          <?php endif; ?>

          <form method="POST" action="security.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="preferences">

            <div class="up-security-row" style="align-items:flex-start;">
              <div>
                <strong style="display:block; font-size:14px; color:var(--up-navy, #1c2a38);">Two-Factor Authentication</strong>
                <span style="font-size:12.5px; color:#777777;">Require a one-time code in addition to your password when signing in.</span>
              </div>

              <label style="position:relative; display:inline-block; width:42px; height:24px; flex-shrink:0;">
                <input type="checkbox" name="two_factor_enabled" <?php echo $two_factor_enabled ? 'checked' : ''; ?>
                       style="opacity:0; width:0; height:0;" class="up-toggle-input">
                <span class="up-toggle-track" style="position:absolute; inset:0; background:<?php echo $two_factor_enabled ? 'var(--up-orange, #e0693a)' : '#cccccc'; ?>; border-radius:24px; transition:background .15s;"></span>
                <span class="up-toggle-thumb" style="position:absolute; top:3px; left:<?php echo $two_factor_enabled ? '21px' : '3px'; ?>; width:18px; height:18px; background:#ffffff; border-radius:50%; transition:left .15s; box-shadow:0 1px 2px rgba(0,0,0,.3);"></span>
              </label>
            </div>

            <div style="display:flex; gap:14px; margin-top:16px; flex-wrap:wrap;">
              <label style="flex:1; min-width:160px; display:block;">
                <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Language</span>
                <select name="language" style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
                  <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                  <option value="fil" <?php echo $language === 'fil' ? 'selected' : ''; ?>>Filipino</option>
                  <option value="ceb" <?php echo $language === 'ceb' ? 'selected' : ''; ?>>Bisaya / Cebuano</option>
                </select>
              </label>

              <label style="flex:1; min-width:160px; display:block;">
                <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Currency</span>
                <select name="currency" style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
                  <option value="PHP" <?php echo $currency === 'PHP' ? 'selected' : ''; ?>>&#8369; PHP &mdash; Philippine Peso</option>
                  <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>$ USD &mdash; US Dollar</option>
                </select>
              </label>
            </div>

            <button type="submit" class="up-btn-solid" style="margin-top:16px;">SAVE PREFERENCES</button>
          </form>
        </section>

      </div>

      <div class="up-right-col">

        <div class="up-need-help">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about your account or security? We're here 24/7.</p>
            <a href="helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

        <!-- DEACTIVATE ACCOUNT -->
        <section class="up-card up-account-security" style="border:1px solid #e0524d;">
          <div class="up-card-header">
            <h3 style="color:#a1332e;">Deactivate Account</h3>
          </div>
          <p style="font-size:13px; color:#777777; margin:0 0 14px;">
            This signs you out and hides your account from RoomHive. It doesn't
            cancel any active bookings — cancel those first from My Bookings.
          </p>

          <?php if (!empty($deactivateErrors)): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#fdeceb; border:1px solid #e0524d; color:#a1332e; font-size:13px; margin-bottom:14px;">
              <?php foreach ($deactivateErrors as $error): ?>
                <p style="margin:0;"><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="security.php" style="display:flex; flex-direction:column; gap:10px;"
                onsubmit="return confirm('This will deactivate your RoomHive account. Continue?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="deactivate">

            <label style="display:block;">
              <span style="display:block; font-size:12px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:4px;">Password</span>
              <input type="password" name="deactivate_password" required
                     style="width:100%; padding:9px 12px; border:1px solid var(--up-border); border-radius:8px; font-size:13px; box-sizing:border-box;">
            </label>

            <label style="display:block;">
              <span style="display:block; font-size:12px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:4px;">Type DEACTIVATE to confirm</span>
              <input type="text" name="confirm_word" required
                     style="width:100%; padding:9px 12px; border:1px solid var(--up-border); border-radius:8px; font-size:13px; box-sizing:border-box;">
            </label>

            <button type="submit" style="background:#e0524d; color:#ffffff; border:none; border-radius:8px; padding:10px; font-weight:700; font-size:13px; cursor:pointer;">
              DEACTIVATE MY ACCOUNT
            </button>
          </form>
        </section>

      </div>

    </div>
  </div>
</main>

<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>
            <p class="footer-tagline">
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>
            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="">
                <span>0927 569 3574</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="">
                <span>kimdivino55@gmail.com</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>
        </div>

        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>

        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>

<script>
document.querySelectorAll('.up-toggle-input').forEach(function (input) {
    input.addEventListener('change', function () {
        const track = input.nextElementSibling;
        const thumb = track.nextElementSibling;
        track.style.background = input.checked ? 'var(--up-orange, #e0693a)' : '#cccccc';
        thumb.style.left = input.checked ? '21px' : '3px';
    });
});
</script>
</body>
</html>