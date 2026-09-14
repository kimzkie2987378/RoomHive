<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   helpcenter.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

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

/* FAQ CONTENT */
 $faqs = [
    ['q' => 'How do I book a stay?', 'a' => 'Open a listing you like and send a booking request with your dates. The host has 24 hours to accept or decline before the request expires.'],
    ['q' => 'When am I charged for a booking?', 'a' => 'Nothing is charged while a booking sits at "Pending." Once the host accepts, the charge goes through and the booking moves to "Confirmed."'],
    ['q' => 'How do I cancel a booking?', 'a' => 'Go to My Bookings, open the booking, and choose Cancel. Refund amounts depend on the listing\'s cancellation policy, shown on the listing page.'],
    ['q' => 'What is Hive Club?', 'a' => 'Hive Club is RoomHive\'s membership program. Paid tiers unlock perks like discounted rates and priority support — see the Hive Club page for details.'],
    ['q' => 'How do I message a host?', 'a' => 'Open the listing and use Contact Host, or continue an existing conversation from the Messages tab in your account.'],
    ['q' => 'How do I change my password?', 'a' => 'Go to Profile & Account, then Manage Security, to set a new password.'],
];

/* CONTACT SUPPORT — SUBMIT */
 $errors = [];
 $sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($subject === '') {
            $errors[] = 'Please choose a subject.';
        }
        if ($message === '') {
            $errors[] = 'Please describe your issue.';
        }

        if (empty($errors)) {
            $ticketStmt = $pdo->prepare(
                "INSERT INTO support_tickets (user_id, subject, message, status, created_at)
                 VALUES (:user_id, :subject, :message, 'open', NOW())"
            );
            $ticketStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'subject' => $subject,
                'message' => $message,
            ]);
            $sent = true;
        }
    }
}

 $activeSidebar = 'help';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help Center — RoomHive</title>

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
                Help Center
            </span>

            <h1 class="up-anim" style="--d: .15s;">
                How can we <span class="up-shimmer">help</span>?
            </h1>

            <span class="up-welcome-underline up-anim" style="--d: .22s;"></span>

            <p class="up-hero-sub up-anim" style="--d: .28s;">
                Answers to common questions, or send our team
                a message directly.
            </p>

        </div>

        <div class="up-hero-art up-hero-art-contain up-anim" style="--d: .3s;">
            <span class="up-art-glow" aria-hidden="true"></span>
            <img src="/webprogg/images/needhelpicon-userprofile.png" alt="">
        </div>

    </div>

    <svg class="up-hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z" fill="#ffffff"></path>
    </svg>

</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <div class="up-two-col">

      <!-- FAQ -->
      <section class="up-card up-reveal">
        <div class="up-card-header">
          <h3>Frequently Asked Questions</h3>
        </div>

        <div class="up-faq-list">
          <?php foreach ($faqs as $i => $faq): ?>
            <div class="up-faq-item<?php echo $i === 0 ? ' open' : ''; ?>">
              <button type="button" class="up-faq-question js-faq-toggle" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                <span><?php echo h($faq['q']); ?></span>
                <span class="up-faq-caret">&#9662;</span>
              </button>
              <div class="up-faq-answer">
                <?php echo h($faq['a']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- CONTACT SUPPORT -->
      <div class="up-right-col">

        <div class="up-card up-reveal" style="--i: 1;">
          <div class="up-card-header">
            <h3>Contact Support</h3>
          </div>

          <?php if ($sent): ?>
            <div class="up-alert up-alert-success" style="margin-bottom:16px;">
              <p>&#10003; Message sent — our team will reply to <?php echo h($dbUser['email']); ?> soon.</p>
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div class="up-alert up-alert-error" style="margin-bottom:16px;">
              <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/helpcenter.php" style="display:flex; flex-direction:column; gap:16px;">
            <?php echo csrf_field(); ?>

            <div class="up-field">
              <label for="hcSubject">Subject</label>
              <select id="hcSubject" name="subject" required>
                <option value="">Choose a topic</option>
                <option value="Booking issue">Booking issue</option>
                <option value="Payment issue">Payment issue</option>
                <option value="Account access">Account access</option>
                <option value="Hive Club">Hive Club</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="up-field">
              <label for="hcMessage">Message</label>
              <textarea id="hcMessage" name="message" rows="5" required placeholder="Tell us what's going on..."></textarea>
            </div>

            <button type="submit" class="up-btn-solid">SEND MESSAGE</button>
          </form>
        </div>

        <div class="up-card up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Other Ways to Reach Us</h3>
          </div>
          <p style="font-size:13px; color:var(--up-ink-soft, #5d6875); margin:0 0 8px;">&#128222; 0927 569 3574</p>
          <p style="font-size:13px; color:var(--up-ink-soft, #5d6875); margin:0;">&#9993; kimdivino55@gmail.com</p>
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

<!-- Reveal + FAQ accordion -->
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

    /* FAQ accordion — toggles a class; CSS handles the rest */
    document.querySelectorAll(".js-faq-toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var item = btn.closest(".up-faq-item");
            var isOpen = item.classList.toggle("open");
            btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });
    });
})();
</script>

</body>
</html>