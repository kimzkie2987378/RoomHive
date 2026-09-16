<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   usernotificationsettings.php

   Notification preferences — lets the user choose which
   events trigger an email, an in-app bell notification, or
   both. Persisted to the `notification_settings` table
   (one row per user, created lazily on first save).

   ASSUMED SCHEMA (adjust the SELECT/INSERT/UPDATE below if
   your real table differs):
       notification_settings (
           user_id            INT PRIMARY KEY,
           email_bookings     TINYINT(1) DEFAULT 1,
           email_messages     TINYINT(1) DEFAULT 1,
           email_promotions   TINYINT(1) DEFAULT 0,
           push_bookings      TINYINT(1) DEFAULT 1,
           push_messages      TINYINT(1) DEFAULT 1,
           push_promotions    TINYINT(1) DEFAULT 0,
           updated_at         TIMESTAMP NULL
       )

   If the table doesn't exist yet, run:
       CREATE TABLE notification_settings (
           user_id INT NOT NULL PRIMARY KEY,
           email_bookings TINYINT(1) NOT NULL DEFAULT 1,
           email_messages TINYINT(1) NOT NULL DEFAULT 1,
           email_promotions TINYINT(1) NOT NULL DEFAULT 0,
           push_bookings TINYINT(1) NOT NULL DEFAULT 1,
           push_messages TINYINT(1) NOT NULL DEFAULT 1,
           push_promotions TINYINT(1) NOT NULL DEFAULT 0,
           updated_at TIMESTAMP NULL DEFAULT NULL
       );
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* AUTH GUARD */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* USER DATA */
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
 $activeSidebar = 'notificationsettings';

/* =========================================================
   PREFERENCE KEYS
   key => [column, default]
========================================================= */
 $prefKeys = [
    'email_bookings'     => ['email_bookings', 1],
    'email_messages'     => ['email_messages', 1],
    'email_promotions'   => ['email_promotions', 0],
    'push_bookings'      => ['push_bookings', 1],
    'push_messages'      => ['push_messages', 1],
    'push_promotions'    => ['push_promotions', 0],
];

/* -----------------------------------------------------
   LOAD CURRENT PREFERENCES
   Missing row = first visit — every group falls back to
   its defaults. A missing TABLE is caught too, so the
   page renders (with defaults) instead of fataling before
   the CREATE TABLE above has been run.
----------------------------------------------------- */
 $prefs = [];
 $prefsTableReady = true;

foreach ($prefKeys as $key => [$column, $default]) {
    $prefs[$key] = $default;
}

try {

    $prefsStmt = $pdo->prepare(
        "SELECT * FROM notification_settings WHERE user_id = :id LIMIT 1"
    );
    $prefsStmt->execute(['id' => $_SESSION['user_id']]);
    $prefsRow = $prefsStmt->fetch();

    if ($prefsRow) {
        foreach ($prefKeys as $key => [$column, $default]) {
            $prefs[$key] = isset($prefsRow[$column]) ? (bool) $prefsRow[$column] : $default;
        }
    }

} catch (PDOException $e) {
    /* Table missing — render with defaults; the save below
       will also fail until the CREATE TABLE is run, and its
       error banner will say so. */
    $prefsTableReady = false;
    error_log('usernotificationsettings: notification_settings table missing? ' . $e->getMessage());
}

/* -----------------------------------------------------
   SAVE
   One UPSERT: INSERT ... ON DUPLICATE KEY UPDATE handles
   both first-save and every save after, keyed on the
   user_id primary key.
----------------------------------------------------- */
 $saved = false;
 $saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify()) {

        $saveError = 'Your session expired. Please try again.';

    } elseif (!$prefsTableReady) {

        $saveError = 'The notification_settings table is missing. Run the CREATE TABLE statement noted at the top of this file.';

    } else {

        /* Checkboxes: absent from POST = unchecked = 0 */
        $values = [];
        foreach ($prefKeys as $key => [$column, $default]) {
            $values[$column] = isset($_POST[$key]) ? 1 : 0;
        }

        try {

            $saveStmt = $pdo->prepare(
                "INSERT INTO notification_settings
                    (user_id, email_bookings, email_messages, email_promotions,
                     push_bookings, push_messages, push_promotions, updated_at)
                 VALUES
                    (:user_id, :email_bookings, :email_messages, :email_promotions,
                     :push_bookings, :push_messages, :push_promotions, NOW())
                 ON DUPLICATE KEY UPDATE
                    email_bookings   = VALUES(email_bookings),
                    email_messages   = VALUES(email_messages),
                    email_promotions = VALUES(email_promotions),
                    push_bookings    = VALUES(push_bookings),
                    push_messages    = VALUES(push_messages),
                    push_promotions  = VALUES(push_promotions),
                    updated_at       = NOW()"
            );

            $saveStmt->execute(array_merge(
                ['user_id' => $_SESSION['user_id']],
                $values
            ));

            /* Reflect immediately without a re-query */
            foreach ($prefKeys as $key => [$column, $default]) {
                $prefs[$key] = isset($_POST[$key]);
            }

            $saved = true;

        } catch (PDOException $e) {
            $saveError = 'Something went wrong saving your preferences. Please try again.';
            error_log('usernotificationsettings save failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notification Settings — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>
<!-- PAGE HEADER — PLAIN -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Notification Settings</span>
    <h1>Choose what you're notified about</h1>
    <p class="ub-lead">Pick which events reach you by email, in-app notification, or both. Changes save instantly.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if ($saved): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; Your notification preferences have been saved.</p>
      </section>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
      <section class="up-alert up-alert-error">
        <p><?php echo h($saveError); ?></p>
      </section>
    <?php endif; ?>

    <form
        method="POST"
        action="/webprogg/user/usernotificationsettings.php"
        class="up-card up-reveal"
        style="max-width: 640px;"
    >
        <?php echo csrf_field(); ?>

        <!-- =========================
             BOOKINGS GROUP
        ========================== -->
        <h3 style="margin:0 0 4px; color:var(--up-navy); font-size:15px; font-weight:800;">
            Bookings
        </h3>
        <p style="margin:0 0 14px; color:var(--up-text-muted); font-size:12.5px;">
            New inquiries, host approvals, and booking updates.
        </p>

        <div class="up-security-row" style="align-items:center; padding:10px 0;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">Email notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Booking updates sent to <?php echo h($dbUser['email']); ?></span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="email_bookings" <?php echo $prefs['email_bookings'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <div class="up-security-row" style="align-items:center; padding:10px 0 16px;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">In-app notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Show booking updates on the bell icon</span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="push_bookings" <?php echo $prefs['push_bookings'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <div class="form-section-divider" style="height:1px; background:var(--up-border); margin:0 0 20px;"></div>

        <!-- =========================
             MESSAGES GROUP
        ========================== -->
        <h3 style="margin:0 0 4px; color:var(--up-navy); font-size:15px; font-weight:800;">
            Messages
        </h3>
        <p style="margin:0 0 14px; color:var(--up-text-muted); font-size:12.5px;">
            New messages from hosts in your inbox.
        </p>

        <div class="up-security-row" style="align-items:center; padding:10px 0;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">Email notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Message alerts sent to your email</span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="email_messages" <?php echo $prefs['email_messages'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <div class="up-security-row" style="align-items:center; padding:10px 0 16px;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">In-app notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Show message alerts on the bell icon</span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="push_messages" <?php echo $prefs['push_messages'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <div class="form-section-divider" style="height:1px; background:var(--up-border); margin:0 0 20px;"></div>

        <!-- =========================
             PROMOTIONS GROUP
        ========================== -->
        <h3 style="margin:0 0 4px; color:var(--up-navy); font-size:15px; font-weight:800;">
            Promotions &amp; News
        </h3>
        <p style="margin:0 0 14px; color:var(--up-text-muted); font-size:12.5px;">
            Hive Club offers, discounts, and RoomHive updates.
        </p>

        <div class="up-security-row" style="align-items:center; padding:10px 0;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">Email notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Offers and news sent to your email</span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="email_promotions" <?php echo $prefs['email_promotions'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <div class="up-security-row" style="align-items:center; padding:10px 0 16px;">
            <div>
                <strong style="display:block; font-size:13.5px; color:var(--up-navy);">In-app notifications</strong>
                <span style="font-size:12px; color:var(--up-text-muted);">Show offers and news on the bell icon</span>
            </div>
            <label class="up-switch">
                <input type="checkbox" name="push_promotions" <?php echo $prefs['push_promotions'] ? 'checked' : ''; ?>>
                <span class="up-switch-track"></span>
            </label>
        </div>

        <button
            type="submit"
            class="up-btn-solid"
            style="margin-top:22px; align-self:flex-start;"
        >
            SAVE PREFERENCES
        </button>

    </form>

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

<!-- Reveal (self-contained) -->
<script>
(function () {
    "use strict";
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
})();
</script>

</body>
</html>