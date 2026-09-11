<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   helpcenter.php

   FAQ accordion plus a "contact support" form. Assumes a
   `support_tickets` table (id, user_id, subject, message,
   status, created_at) to record submissions — adjust the
   INSERT below if the real schema names these differently.
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
   FAQ CONTENT
   Static for now — move to a `faqs` table later if these
   need to be editable from an admin screen.
----------------------------------------------------- */
$faqs = [
    ['q' => 'How do I book a stay?', 'a' => 'Open a listing you like and send a booking request with your dates. The host has 24 hours to accept or decline before the request expires.'],
    ['q' => 'When am I charged for a booking?', 'a' => 'Nothing is charged while a booking sits at "Pending." Once the host accepts, the charge goes through and the booking moves to "Confirmed."'],
    ['q' => 'How do I cancel a booking?', 'a' => 'Go to My Bookings, open the booking, and choose Cancel. Refund amounts depend on the listing\'s cancellation policy, shown on the listing page.'],
    ['q' => 'What is Hive Club?', 'a' => 'Hive Club is RoomHive\'s membership program. Paid tiers unlock perks like discounted rates and priority support — see the Hive Club page for details.'],
    ['q' => 'How do I message a host?', 'a' => 'Open the listing and use Contact Host, or continue an existing conversation from the Messages tab in your account.'],
    ['q' => 'How do I change my password?', 'a' => 'Go to Profile & Account, then Manage Security, to set a new password.'],
];

/* -----------------------------------------------------
   CONTACT SUPPORT — SUBMIT
----------------------------------------------------- */
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
    <p class="up-welcome-eyebrow">Help Center</p>
    <h1>How can we help, <?php echo h($dbUser['name']); ?>?</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Answers to common questions, or send our team a message directly.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/needhelpicon-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <div class="up-two-col">

      <!-- FAQ -->
      <section class="up-card up-bookings-card">
        <div class="up-card-header">
          <h3>Frequently Asked Questions</h3>
        </div>

        <div class="up-faq-list">
          <?php foreach ($faqs as $i => $faq): ?>
            <div class="up-faq-item" style="border-bottom:1px solid var(--up-border);">
              <button type="button" class="up-faq-question js-faq-toggle"
                      style="width:100%; text-align:left; background:none; border:none; cursor:pointer; padding:14px 4px; display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:14px; font-weight:700; color:var(--up-navy, #1c2a38);">
                <span><?php echo h($faq['q']); ?></span>
                <span class="up-faq-caret" style="transition:transform .15s; flex-shrink:0;">&#9662;</span>
              </button>
              <div class="up-faq-answer" style="display:none; padding:0 4px 16px; font-size:13.5px; color:#555555; line-height:1.5;">
                <?php echo h($faq['a']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- CONTACT SUPPORT -->
      <div class="up-right-col">
        <div class="up-card up-account-security">
          <div class="up-card-header">
            <h3>Contact Support</h3>
          </div>

          <?php if ($sent): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#eaf7ee; border:1px solid #2f9e5c; color:#1f6b3b; font-size:13px; margin-bottom:14px;">
              Message sent — our team will reply to <?php echo h($dbUser['email']); ?> soon.
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div style="padding:12px 14px; border-radius:8px; background:#fdeceb; border:1px solid #e0524d; color:#a1332e; font-size:13px; margin-bottom:14px;">
              <?php foreach ($errors as $error): ?>
                <p style="margin:0;"><?php echo h($error); ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/helpcenter.php" style="display:flex; flex-direction:column; gap:12px;">
            <?php echo csrf_field(); ?>
            <label style="display:block;">
              <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Subject</span>
              <select name="subject" required style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box;">
                <option value="">Choose a topic</option>
                <option value="Booking issue">Booking issue</option>
                <option value="Payment issue">Payment issue</option>
                <option value="Account access">Account access</option>
                <option value="Hive Club">Hive Club</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label style="display:block;">
              <span style="display:block; font-size:12.5px; font-weight:700; color:var(--up-navy, #1c2a38); margin-bottom:5px;">Message</span>
              <textarea name="message" rows="5" required placeholder="Tell us what's going on..."
                        style="width:100%; padding:10px 14px; border:1px solid var(--up-border); border-radius:8px; font-size:13.5px; box-sizing:border-box; resize:vertical;"></textarea>
            </label>

            <button type="submit" class="up-btn-solid">SEND MESSAGE</button>
          </form>
        </div>

        <div class="up-card up-account-security">
          <div class="up-card-header">
            <h3>Other Ways to Reach Us</h3>
          </div>
          <p style="font-size:13px; color:#555555; margin:0 0 8px;">&#128222; 0927 569 3574</p>
          <p style="font-size:13px; color:#555555; margin:0;">&#9993; kimdivino55@gmail.com</p>
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

<!-- FAQ accordion -->
<script>
document.querySelectorAll('.js-faq-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const answer = btn.nextElementSibling;
        const caret = btn.querySelector('.up-faq-caret');
        const isOpen = answer.style.display === 'block';

        answer.style.display = isOpen ? 'none' : 'block';
        caret.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
    });
});
</script>
</body>
</html>
