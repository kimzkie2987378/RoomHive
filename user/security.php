<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   security.php

   FIXED: the old file opened its own <header class="navbar">
   with a logo, then required usernav.php — which renders a
   SECOND complete navbar inside it (nested/double navbars,
   unclosed tags, broken layout). The stray wrapper is removed;
   usernav.php is the one and only navbar.

   Schema: users.password, two_factor_enabled, language,
   currency, status.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

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

/* CHANGE PASSWORD */
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

/* 2FA + LANGUAGE + CURRENCY */
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

/* DEACTIVATE ACCOUNT */
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

<!-- ANTI-FLASH BOOTSTRAP: apply saved theme before first paint -->
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>
</head>
<body data-theme-base="light">

<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<!-- PAGE HEADER — PLAIN -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Settings</span>
    <h1>Security &amp; account preferences</h1>
    <p class="ub-lead">Manage your password, appearance, two-factor login, and account defaults.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <div class="up-two-col">

      <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- CHANGE PASSWORD -->
        <section class="up-card up-reveal">
          <div class="up-card-header">
            <h3>Change Password</h3>
          </div>

          <?php if ($passwordSaved): ?>
            <div class="up-alert up-alert-success" style="margin-bottom:16px;">
              <p>&#10003; Your password has been updated.</p>
            </div>
          <?php endif; ?>

          <?php if (!empty($passwordErrors)): ?>
            <div class="up-alert up-alert-error" style="margin-bottom:16px;">
              <?php foreach ($passwordErrors as $error): ?>
                <p><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/security.php" style="display:flex; flex-direction:column; gap:16px;" id="pwForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="password">

            <div class="up-field">
              <label for="curPw">Current Password</label>
              <input type="password" id="curPw" name="current_password" required>
            </div>

            <div class="up-field">
              <label for="newPw">New Password</label>
              <input type="password" id="newPw" name="new_password" required minlength="8">
              <div class="up-strength"><div class="up-strength-fill" id="pwStrengthFill"></div></div>
              <span class="up-strength-label" id="pwStrengthLabel">Enter at least 8 characters</span>
            </div>

            <div class="up-field">
              <label for="confPw">Confirm New Password</label>
              <input type="password" id="confPw" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="up-btn-solid" style="align-self:flex-start;">UPDATE PASSWORD</button>
          </form>
        </section>

        <!-- PREFERENCES -->
        <section class="up-card up-reveal" style="--i: 1;">
          <div class="up-card-header">
            <h3>Account Preferences</h3>
          </div>

          <?php if ($prefsSaved): ?>
            <div class="up-alert up-alert-success" style="margin-bottom:16px;">
              <p>&#10003; Your preferences have been saved.</p>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/security.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="preferences">

            <div class="up-security-row" style="align-items:flex-start; padding-top:0;">
              <div>
                <strong style="display:block; font-size:14px; color:var(--up-navy);">Two-Factor Authentication</strong>
                <span style="font-size:12.5px; color:var(--up-text-muted);">Require a one-time code in addition to your password when signing in.</span>
              </div>

              <label class="up-switch">
                <input type="checkbox" name="two_factor_enabled" <?php echo $two_factor_enabled ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
              </label>
            </div>

            <div style="display:flex; gap:14px; margin-top:18px; flex-wrap:wrap;">
              <div class="up-field" style="flex:1; min-width:160px;">
                <label for="lang">Language</label>
                <select id="lang" name="language">
                  <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                  <option value="fil" <?php echo $language === 'fil' ? 'selected' : ''; ?>>Filipino</option>
                  <option value="ceb" <?php echo $language === 'ceb' ? 'selected' : ''; ?>>Bisaya / Cebuano</option>
                </select>
              </div>

              <div class="up-field" style="flex:1; min-width:160px;">
                <label for="curr">Currency</label>
                <select id="curr" name="currency">
                  <option value="PHP" <?php echo $currency === 'PHP' ? 'selected' : ''; ?>>&#8369; PHP &mdash; Philippine Peso</option>
                  <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>$ USD &mdash; US Dollar</option>
                </select>
              </div>
            </div>

            <button type="submit" class="up-btn-solid" style="margin-top:18px;">SAVE PREFERENCES</button>
          </form>
        </section>

      </div>

      <div class="up-right-col">

        <!-- APPEARANCE / DARK MODE (client-side, localStorage) -->
        <div class="up-card up-reveal" style="--i: 0;">
          <div class="up-card-header">
            <h3>Appearance</h3>
          </div>

          <div class="up-darkmode-row">
            <div class="up-darkmode-copy">
              <strong>Dark Mode</strong>
              <span>Apply a dark theme across your entire account.</span>
            </div>

            <label class="up-switch">
              <input type="checkbox" id="darkModeToggle" aria-label="Toggle dark mode">
              <span class="up-switch-track"></span>
            </label>
          </div>

          <p class="up-field-hint" style="margin-top:0;">
            Your choice is remembered on this device.
          </p>
        </div>

        <div class="up-need-help up-reveal" style="--i: 1;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about your account or security? We're here 24/7.</p>
            <a href="/webprogg/user/helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

        <!-- DEACTIVATE -->
        <section class="up-card upr-danger-card up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3 style="color:#a1332e;">Deactivate Account</h3>
          </div>
          <p style="font-size:13px; color:var(--up-text-muted); margin:0 0 14px;">
            This signs you out and hides your account from RoomHive. It doesn't
            cancel any active bookings — cancel those first from My Bookings.
          </p>

          <?php if (!empty($deactivateErrors)): ?>
            <div class="up-alert up-alert-error" style="margin-bottom:14px;">
              <?php foreach ($deactivateErrors as $error): ?>
                <p><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/security.php" style="display:flex; flex-direction:column; gap:12px;"
                onsubmit="return confirm('This will deactivate your RoomHive account. Continue?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="deactivate">

            <div class="up-field">
              <label for="deaPw">Password</label>
              <input type="password" id="deaPw" name="deactivate_password" required>
            </div>

            <div class="up-field">
              <label for="deaWord">Type DEACTIVATE to confirm</label>
              <input type="text" id="deaWord" name="confirm_word" required>
            </div>

            <button type="submit"
                    style="background:#e0524d; color:#ffffff; border:none; border-radius:10px; padding:11px; font-family:'Poppins',sans-serif; font-weight:700; font-size:13px; cursor:pointer; transition:background .2s ease;"
                    onmouseover="this.style.background='#c84642'"
                    onmouseout="this.style.background='#e0524d'">
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
            <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
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

<!-- Theme + reveal + password strength meter -->
<script>
(function () {
    "use strict";

    var body = document.body;
    var KEY  = "rhTheme";
    var darkToggle = document.getElementById("darkModeToggle");

    function applyTheme(dark) {
        if (dark) {
            body.setAttribute("data-theme", "dark");
            document.documentElement.removeAttribute("data-theme-preview");
        } else {
            body.removeAttribute("data-theme");
            document.documentElement.removeAttribute("data-theme-preview");
        }
    }

    var stored = null;
    try { stored = localStorage.getItem(KEY); } catch (e) {}
    applyTheme(stored === "dark");

    if (darkToggle) {
        darkToggle.checked = (stored === "dark");

        darkToggle.addEventListener("change", function () {
            var dark = darkToggle.checked;
            applyTheme(dark);
            try {
                localStorage.setItem(KEY, dark ? "dark" : "light");
            } catch (e) {}
        });
    }

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.classList.add("in-view");
                window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    var pw = document.getElementById("newPw");
    var fill = document.getElementById("pwStrengthFill");
    var label = document.getElementById("pwStrengthLabel");

    if (pw && fill && label) {
        pw.addEventListener("input", function () {
            var v = pw.value;
            var score = 0;

            if (v.length >= 8) score++;
            if (v.length >= 12) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;

            var levels = [
                { w: "10%",  c: "#e0524d", t: "Too weak" },
                { w: "30%",  c: "#e0524d", t: "Weak" },
                { w: "55%",  c: "#e0a02a", t: "Fair" },
                { w: "75%",  c: "#eda423", t: "Good" },
                { w: "100%", c: "#1fa971", t: "Strong" },
                { w: "100%", c: "#1fa971", t: "Excellent" }
            ];

            var lvl = v === "" ? null : levels[score];

            if (!lvl) {
                fill.style.width = "0%";
                label.textContent = "Enter at least 8 characters";
            } else {
                fill.style.width = lvl.w;
                fill.style.background = lvl.c;
                label.textContent = "Strength: " + lvl.t;
            }
        });
    }
})();
</script>

</body>
</html>