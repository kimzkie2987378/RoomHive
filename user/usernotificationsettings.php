<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   usernotificationsettings.php

   Email/push toggles for the logged-in user. Assumes a
   `notification_preferences` table keyed by user_id with one
   boolean column per toggle below — adjust the column list
   if the real schema names these differently. If no row
   exists yet for this user, everything defaults to ON (the
   common "opted in until you turn it off" pattern).
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
    "SELECT id, name, email, avatar_path, is_host FROM users WHERE id = :id LIMIT 1"
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

/* -----------------------------------------------------
   TOGGLE DEFINITIONS
   key => [label, help text]. Same key list is used to read
   the saved row, build the form, and write the update.
----------------------------------------------------- */
$toggleGroups = [
    'Email' => [
        'email_booking_updates' => ['Booking updates', 'Confirmations, host replies, and status changes on your bookings.'],
        'email_messages'        => ['New messages', 'When a host sends you a message.'],
        'email_promotions'      => ['Promotions and offers', 'Deals, seasonal offers, and Hive Club perks.'],
        'email_reviews'         => ['Review reminders', 'Nudges to review a stay after checkout.'],
    ],
    'Push' => [
        'push_booking_updates' => ['Booking updates', 'Confirmations, host replies, and status changes on your bookings.'],
        'push_messages'        => ['New messages', 'When a host sends you a message.'],
    ],
];

$allKeys = array_merge(array_keys($toggleGroups['Email']), array_keys($toggleGroups['Push']));

/* -----------------------------------------------------
   SAVE
----------------------------------------------------- */
$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $values = [];
        foreach ($allKeys as $key) {
            $values[$key] = isset($_POST[$key]) ? 1 : 0;
        }

        $existsStmt = $pdo->prepare("SELECT user_id FROM notification_preferences WHERE user_id = :id LIMIT 1");
        $existsStmt->execute(['id' => $_SESSION['user_id']]);

        if ($existsStmt->fetch()) {
            $setSql = implode(', ', array_map(function ($k) { return "$k = :$k"; }, $allKeys));
            $updateStmt = $pdo->prepare("UPDATE notification_preferences SET $setSql WHERE user_id = :id");
            $updateStmt->execute($values + ['id' => $_SESSION['user_id']]);
        } else {
            $cols = array_merge(['user_id'], $allKeys);
            $placeholders = array_map(function ($k) { return ":$k"; }, $cols);
            $insertStmt = $pdo->prepare(
                "INSERT INTO notification_preferences (" . implode(', ', $cols) . ")
                 VALUES (" . implode(', ', $placeholders) . ")"
            );
            $insertStmt->execute($values + ['user_id' => $_SESSION['user_id']]);
        }

        $saved = true;
    }
}

/* -----------------------------------------------------
   CURRENT PREFERENCES
   Default everything to ON until a row exists for this user.
----------------------------------------------------- */
$prefsStmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = :id LIMIT 1");
$prefsStmt->execute(['id' => $_SESSION['user_id']]);
$savedPrefs = $prefsStmt->fetch();

$prefs = [];
foreach ($allKeys as $key) {
    $prefs[$key] = $savedPrefs ? (bool) $savedPrefs[$key] : true;
}

$activeSidebar = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notification Settings — RoomHive</title>

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

        <a href="/webprogg/user/notifications.php" class="nav-bell">
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
    <p class="up-welcome-eyebrow">Notification Settings</p>
    <h1>Choose what we send you</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Turn any notification on or off — you're always in control.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/notificationsettings-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if ($saved): ?>
      <section style="padding:12px 18px; border-radius:10px; background:#eaf7ee; border:1px solid #2f9e5c; color:#1f6b3b; font-size:0.9rem; margin-bottom:18px;">
        Your notification preferences have been saved.
      </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <section style="padding:12px 18px; border-radius:10px; background:#fdeceb; border:1px solid #e0524d; color:#a1332e; font-size:0.9rem; margin-bottom:18px;">
        <?php foreach ($errors as $error): ?>
          <p style="margin:0;"><?php echo h($error); ?></p>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <form method="POST" action="/webprogg/user/usernotificationsettings.php">
      <?php echo csrf_field(); ?>

      <?php foreach ($toggleGroups as $groupLabel => $toggles): ?>
        <section class="up-card up-bookings-card" style="margin-bottom:20px;">
          <div class="up-card-header">
            <h3><?php echo h($groupLabel); ?> Notifications</h3>
          </div>

          <?php foreach ($toggles as $key => $meta): ?>
            <div class="up-security-row" style="align-items:flex-start;">
              <div>
                <strong style="display:block; font-size:14px; color:var(--up-navy, #1c2a38);"><?php echo h($meta[0]); ?></strong>
                <span style="font-size:12.5px; color:#777777;"><?php echo h($meta[1]); ?></span>
              </div>

              <label style="position:relative; display:inline-block; width:42px; height:24px; flex-shrink:0;">
                <input type="checkbox" name="<?php echo h($key); ?>" <?php echo $prefs[$key] ? 'checked' : ''; ?>
                       style="opacity:0; width:0; height:0;" class="up-toggle-input">
                <span class="up-toggle-track" style="position:absolute; inset:0; background:<?php echo $prefs[$key] ? 'var(--up-orange, #e0693a)' : '#cccccc'; ?>; border-radius:24px; transition:background .15s;"></span>
                <span class="up-toggle-thumb" style="position:absolute; top:3px; left:<?php echo $prefs[$key] ? '21px' : '3px'; ?>; width:18px; height:18px; background:#ffffff; border-radius:50%; transition:left .15s; box-shadow:0 1px 2px rgba(0,0,0,.3);"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <button type="submit" class="up-btn-solid">SAVE PREFERENCES</button>
    </form>

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

<!-- Toggle switch visual state (checkbox itself carries the real value on submit) -->
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
