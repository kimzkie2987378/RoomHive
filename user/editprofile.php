<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   editprofile.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $stmt = $pdo->prepare(
    "SELECT id, name, email, phone, age, location, avatar_path, is_host FROM users WHERE id = :id LIMIT 1"
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

 $errors = [];
 $saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $age      = trim($_POST['age'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '') {
            $errors[] = 'Full name is required.';
        }
        if ($age !== '' && (!ctype_digit($age) || (int) $age < 18 || (int) $age > 120)) {
            $errors[] = 'Age must be a number between 18 and 120.';
        }

        if (empty($errors)) {
            $updateStmt = $pdo->prepare(
                "UPDATE users SET name = :name, phone = :phone, age = :age, location = :location WHERE id = :id"
            );
            $updateStmt->execute([
                'name'     => $name,
                'phone'    => $phone !== '' ? $phone : null,
                'age'      => $age !== '' ? (int) $age : null,
                'location' => $location !== '' ? $location : null,
                'id'       => $_SESSION['user_id'],
            ]);

            $dbUser['name']     = $name;
            $dbUser['phone']    = $phone;
            $dbUser['age']      = $age;
            $dbUser['location'] = $location;

            $saved = true;
        }
    }
}

 $user = [
    'name'     => $dbUser['name'],
    'avatar'   => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'email'    => $dbUser['email'],
    'phone'    => $dbUser['phone'] ?? '',
    'age'      => $dbUser['age'] ?? '',
    'location' => $dbUser['location'] ?? '',
];

 $activeSidebar = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile &amp; Account — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">

<script>document.documentElement.classList.add("js");</script>
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

<!-- HERO -->
<section class="up-hero up-hero-sub">

    <div aria-hidden="true">
        <span class="up-hero-blob up-hero-blob-1"></span>
        <span class="up-hero-blob up-hero-blob-2"></span>
    </div>

    <div class="up-hero-inner">

        <div class="up-hero-text">

            <span class="up-hero-badge up-anim" style="--d: .05s;">
                <span class="up-pulse-dot"></span>
                Profile &amp; Account
            </span>

            <h1 class="up-anim" style="--d: .15s;">
                Keep your details <span class="up-shimmer">fresh</span>
            </h1>

            <span class="up-welcome-underline up-anim" style="--d: .22s;"></span>

            <p class="up-hero-sub up-anim" style="--d: .28s;">
                Hosts and support use this info to reach you
                about your bookings.
            </p>

        </div>

        <div class="up-hero-art up-hero-art-contain up-anim" style="--d: .3s;">
            <span class="up-art-glow" aria-hidden="true"></span>
            <img src="/webprogg/images/profile&accounticon-userprofile.png" alt="">
        </div>

    </div>

    <svg class="up-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if ($saved): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; Your profile has been updated.</p>
      </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <section class="up-alert up-alert-error">
        <?php foreach ($errors as $error): ?>
          <p><?php echo h($error); ?></p>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <div class="up-two-col">

      <form method="POST" action="/webprogg/user/editprofile.php" class="up-card up-reveal" style="display:block;">
        <?php echo csrf_field(); ?>

        <div class="up-profile-photo" style="margin-bottom:20px;">
          <img src="<?php echo h($user['avatar']); ?>" alt="<?php echo h($user['name']); ?>">
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">

          <div class="up-field">
            <label for="epName">Full Name</label>
            <input type="text" id="epName" name="name" value="<?php echo h($user['name']); ?>" required>
          </div>

          <div class="up-field">
            <label for="epEmail">Email</label>
            <input type="email" id="epEmail" value="<?php echo h($user['email']); ?>" disabled>
            <span class="up-field-hint">Contact support to change the email on your account.</span>
          </div>

          <div class="up-field">
            <label for="epPhone">Phone Number</label>
            <input type="tel" id="epPhone" name="phone" value="<?php echo h($user['phone']); ?>" placeholder="09XX XXX XXXX">
          </div>

          <div class="up-field">
            <label for="epAge">Age</label>
            <input type="number" id="epAge" name="age" value="<?php echo h($user['age']); ?>" min="18" max="120">
          </div>

          <div class="up-field">
            <label for="epLocation">Location</label>
            <input type="text" id="epLocation" name="location" value="<?php echo h($user['location']); ?>" placeholder="City, Province">
          </div>

        </div>

        <button type="submit" class="up-btn-solid" style="margin-top:20px;">SAVE CHANGES</button>
      </form>

      <div class="up-right-col">

        <div class="up-card up-account-security up-reveal" style="--i: 1;">
          <div class="up-card-header">
            <h3>Change Password</h3>
          </div>
          <p style="font-size:13px; color:#777777; margin:0 0 12px;">Update the password you use to sign in.</p>
          <a href="/webprogg/user/security.php" class="up-btn-outline up-manage-security">Manage Security</a>
        </div>

        <div class="up-card up-account-security up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Photo</h3>
          </div>
          <p style="font-size:13px; color:#777777; margin:0 0 12px;">Change your profile photo from the Overview page.</p>
          <a href="/webprogg/user/userprofile.php" class="up-btn-outline">GO TO OVERVIEW</a>
        </div>

        <div class="up-need-help up-reveal" style="--i: 3;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about your account? We're here 24/7.</p>
            <a href="/webprogg/user/helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

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
            <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>kimdivino55@gmail.com</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
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
(function () {
    "use strict";
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    io.unobserve(el);
                    el.classList.add("in-view");
                    window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
                });
            },
            { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
        );
        revealEls.forEach(function (el) { io.observe(el); });
    }
})();
</script>

</body>
</html>