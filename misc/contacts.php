<?php
/* =========================================================
   ROOMHIVE - CONTACTS PAGE
   The contact form SAVES to the database (contact_messages
   table, self-healing) so the Admin > Messages page can show
   real user questions. Logged-in users get name/email
   prefilled and their submission linked to their account.

   === NAVBAR ACTIVE FIX ===
   $currentPage is set so navbar.php marks CONTACTS as the
   active link.

   === FLOATING LOGIN MODAL — DELETED ===

   === HIVE CLUB REMOVED ===

   === SHARED FOOTER (this version) ===
   The hard-coded footer is gone — replaced by
   includes/footer.php (single source of truth). The
   $currentYear variable was footer-only and is removed;
   $currentPage marks "Contacts" active automatically.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

 $notification_count = 0;

/* Prefill values (used by the form fields) */
 $firstName = "";
 $lastName  = "";
 $email     = "";
 $subject   = "";
 $message   = "";

/* Logged-in users: fetch avatar + unread count + name/email
   (name and email prefill the contact form) */
if ($isLoggedIn && isset($_SESSION['user_id'])) {

    $userStmt = $pdo->prepare(
        "SELECT u.avatar_path,
                u.name,
                u.email,
                (SELECT COUNT(*)
                   FROM notifications n
                  WHERE n.user_id = u.id
                    AND n.is_read = 0) AS unread_count
           FROM users u
          WHERE u.id = :id
          LIMIT 1"
    );
    $userStmt->execute(['id' => $_SESSION['user_id']]);
    $userRow = $userStmt->fetch();

    $_SESSION['avatar_path'] = $userRow['avatar_path'] ?? null;
    $notification_count = (int)($userRow['unread_count'] ?? 0);

    if (!empty($userRow['name'])) {
        $parts     = preg_split('/\s+/', trim($userRow['name']), 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? '';
        $email     = $userRow['email'] ?? '';
    }
}

 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $navigation = [
    "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS" => "/webprogg/Listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => $isLoggedIn ? "/webprogg/host/becomeahost.php" : "/webprogg/auth/loginform.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

 $currentPage = $navigation['CONTACTS'];

 $successMessage = "";
 $errorMessage   = "";

/* =========================================================
   HANDLE CONTACT FORM — saves to the database
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName = trim($_POST["first_name"] ?? "");
    $lastName  = trim($_POST["last_name"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $subject   = trim($_POST["subject"] ?? "");
    $message   = trim($_POST["message"] ?? "");

    if (
        empty($firstName) ||
        empty($lastName) ||
        empty($email) ||
        empty($subject) ||
        empty($message)
    ) {

        $errorMessage = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errorMessage = "Please enter a valid email address.";

    } else {

        /* Self-heal: create the table if it doesn't exist */
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $ins = $pdo->prepare(
                "INSERT INTO contact_messages
                    (user_id, first_name, last_name, email, subject, message)
                 VALUES
                    (:u, :fn, :ln, :em, :sj, :msg)"
            );
            $ins->execute([
                ':u'   => ($isLoggedIn && isset($_SESSION['user_id'])) ? (int) $_SESSION['user_id'] : null,
                ':fn'  => $firstName,
                ':ln'  => $lastName,
                ':em'  => $email,
                ':sj'  => $subject,
                ':msg' => $message,
            ]);

            $successMessage = "Thank you, {$firstName}! Your message has been received — we'll reply to {$email} within 24 hours.";

            /* Clear the form after a successful send */
            $firstName = "";
            $lastName  = "";
            $email     = "";
            $subject   = "";
            $message   = "";

        } catch (PDOException $e) {
            error_log('Contact form insert failed: ' . $e->getMessage());
            $errorMessage = "Sorry, we couldn't send your message right now. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RoomHive - Contact Us</title>

    <!-- Poppins Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- RoomHive CSS -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css?v=2"
    >
    <link
        rel="stylesheet"
        href="/webprogg/assets/contacts.css"
    >

    <!-- Enables scroll-reveal only when JS is available -->
    <script>document.documentElement.classList.add("js");</script>

</head>


<body class="contacts-page">


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<?php
 $guestCtaHref = '/webprogg/host/becomeahost.php';
include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/navbar.php';
?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<!-- =====================================================
     CONTACT HERO
===================================================== -->

<section class="cx-hero">

    <!-- Decorative background: glow blobs + honeycomb texture -->
    <div aria-hidden="true">

        <span class="cx-blob cx-blob-1"></span>
        <span class="cx-blob cx-blob-2"></span>

    </div>


    <div class="cx-hero-inner">

        <!-- HERO TEXT -->
        <div class="cx-hero-text">

            <span class="cx-hero-badge cx-anim" style="--d: .05s;">

                <span class="cx-pulse-dot"></span>

                We're Here To Help

            </span>


            <h1 class="cx-anim" style="--d: .15s;">

                Get In

                <span class="cx-shimmer">Touch</span>

            </h1>


            <p class="cx-anim" style="--d: .25s;">

                Questions about a listing, a booking, or becoming a host?
                Reach out — a real human will get back to you fast.

            </p>


            <div class="cx-hero-actions cx-anim" style="--d: .35s;">

                <a
                    href="tel:+639275693574"
                    class="cx-btn cx-btn-honey"
                >

                    CALL NOW

                    <span class="cx-btn-arrow">&rarr;</span>

                </a>


                <a
                    href="mailto:hello@roomhive.ph"
                    class="cx-btn cx-btn-outline"
                >

                    EMAIL US

                </a>

            </div>

        </div>


        <!-- HERO IMAGE -->
        <div class="cx-hero-art cx-anim" style="--d: .3s;">

            <span class="cx-art-glow" aria-hidden="true"></span>

            <img
                class="cx-art-img"
                src="/webprogg/images/WholeLivingRoom-Contacts.png"
                alt="RoomHive living room"
            >


            <!-- Floating glass chips -->
            <div class="cx-chip cx-chip-1">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H13z"/>
                </svg>

                <span>Fast Replies</span>

            </div>


            <div class="cx-chip cx-chip-2">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>

                <span>Mon–Fri, 8AM–6PM</span>

            </div>


            <div class="cx-chip cx-chip-3">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l7 3v6c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>

                <span>Verified &amp; Trusted</span>

            </div>

        </div>

    </div>


    <!-- Wave divider -->
    <svg
        class="cx-hero-wave"
        viewBox="0 0 1440 90"
        preserveAspectRatio="none"
        aria-hidden="true"
    >

        <path
            d="M0,48 C240,90 480,6 760,30 C1040,54 1240,90 1440,40 L1440,90 L0,90 Z"
            fill="#ffffff"
        >

        </path>

    </svg>

</section>


<!-- =====================================================
     CONTACT INFORMATION + SEND MESSAGE
===================================================== -->

<section class="cx-main" id="contact-form">


    <!-- =================================================
         CONTACT INFORMATION
    ================================================== -->

    <div class="cx-info">

        <div class="cx-info-head cx-reveal">

            <h2>
                Contact Information
            </h2>

            <p>
                Choose the best way to reach us.
            </p>

        </div>


        <!-- PHONE -->

        <div class="cx-info-card cx-reveal" style="--i: 0;">

            <div class="cx-info-icon">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>
                </svg>

            </div>

            <div>

                <h4>
                    Phone
                </h4>

                <p>
                    +63 927 569 3574
                </p>

                <span>
                    Monday–Friday, 8:00 AM–6:00 PM
                </span>

            </div>

        </div>


        <!-- EMAIL -->

        <div class="cx-info-card cx-reveal" style="--i: 1;">

            <div class="cx-info-icon">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                    <path d="m3.5 7 8.5 6 8.5-6"/>
                </svg>

            </div>

            <div>

                <h4>
                    Email
                </h4>

                <p>
                    hello@roomhive.ph
                </p>

                <span>
                    We usually reply within 24 hours.
                </span>

            </div>

        </div>


        <!-- ADDRESS -->

        <div class="cx-info-card cx-reveal" style="--i: 2;">

            <div class="cx-info-icon">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s7-5.1 7-11a7 7 0 1 0-14 0c0 5.9 7 11 7 11z"/>
                    <circle cx="12" cy="10" r="2.6"/>
                </svg>

            </div>

            <div>

                <h4>
                    Address
                </h4>

                <p>
                    Dumaguete City, Negros Oriental
                </p>

                <span>
                    Philippines
                </span>

            </div>

        </div>


        <!-- SOCIAL MEDIA -->

        <div class="cx-info-card cx-reveal" style="--i: 3;">

            <div class="cx-info-icon">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="6" cy="12" r="2.5"/>
                    <circle cx="17.5" cy="6" r="2.5"/>
                    <circle cx="17.5" cy="18" r="2.5"/>
                    <path d="m8.3 10.8 7-3.6M8.3 13.2l7 3.6"/>
                </svg>

            </div>

            <div>

                <h4>
                    Social Media
                </h4>

                <p>
                    Follow us on social media!
                </p>


                <div class="cx-socials">

                    <a
                        href="#"
                        aria-label="Facebook"
                    >

                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5H16V5.1c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8V11H7.7v3h2.4v7h3.4z"/>
                        </svg>

                    </a>


                    <a
                        href="#"
                        aria-label="Instagram"
                    >

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/>
                            <circle cx="12" cy="12" r="3.8"/>
                            <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>
                        </svg>

                    </a>


                    <a
                        href="#"
                        aria-label="TikTok"
                    >

                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 1 1-2.59-2.59c.27 0 .53.04.77.12V9.77a5.76 5.76 0 0 0-.77-.05 5.66 5.66 0 1 0 5.66 5.66V9.01a7.35 7.35 0 0 0 4.29 1.38V7.3a4.28 4.28 0 0 1-3.21-1.48z"/>
                        </svg>

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         SEND MESSAGE
    ================================================== -->

    <div class="cx-form-card cx-reveal" style="--i: 1;">

        <h2>
            Send Us a Message
        </h2>

        <p>
            Fill out the form below and we'll get back to you
            within 24 hours.
        </p>


        <!-- SUCCESS MESSAGE -->

        <?php if (!empty($successMessage)): ?>

            <div
                class="cx-msg cx-msg-success"
                id="cxSuccessMsg"
                role="status"
            >

                <span class="cx-msg-ico">&#10003;</span>

                <span>
                    <?php echo htmlspecialchars($successMessage); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if (!empty($errorMessage)): ?>

            <div
                class="cx-msg cx-msg-error"
                role="alert"
            >

                <span class="cx-msg-ico">!</span>

                <span>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- CONTACT FORM -->

        <form
            action="/webprogg/misc/contacts.php"
            method="POST"
            id="cxContactForm"
        >


            <!-- NAME -->

            <div class="cx-row">

                <div class="cx-field">

                    <label for="cxFirstName">
                        First Name
                    </label>

                    <input
                        type="text"
                        id="cxFirstName"
                        name="first_name"
                        placeholder="Juan"
                        value="<?php echo htmlspecialchars($firstName); ?>"
                        required
                    >

                </div>


                <div class="cx-field">

                    <label for="cxLastName">
                        Last Name
                    </label>

                    <input
                        type="text"
                        id="cxLastName"
                        name="last_name"
                        placeholder="Dela Cruz"
                        value="<?php echo htmlspecialchars($lastName); ?>"
                        required
                    >

                </div>

            </div>


            <!-- EMAIL -->

            <div class="cx-field">

                <label for="cxEmail">
                    Email Address
                </label>

                <input
                    type="email"
                    id="cxEmail"
                    name="email"
                    placeholder="you@example.com"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >

            </div>


            <!-- SUBJECT -->

            <div class="cx-field">

                <label for="cxSubject">
                    Subject
                </label>

                <input
                    type="text"
                    id="cxSubject"
                    name="subject"
                    placeholder="How can we help?"
                    value="<?php echo htmlspecialchars($subject); ?>"
                    required
                >

            </div>


            <!-- MESSAGE -->

            <div class="cx-field">

                <label for="cxMessage">
                    Your Message
                </label>

                <textarea
                    id="cxMessage"
                    name="message"
                    placeholder="Tell us more about your question or concern..."
                    rows="5"
                    required
                ><?php echo htmlspecialchars($message); ?></textarea>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="cx-submit cx-btn-shine"
            >

                <span class="cx-submit-label">
                    SEND MESSAGE
                </span>

                <span>
                    &#10148;
                </span>

            </button>

        </form>

    </div>

</section>


<!-- =====================================================
     NEED HELP
===================================================== -->

<section class="cx-help">

    <div class="cx-help-head cx-reveal">

        <span class="cx-eyebrow">
            Quick Answers
        </span>

        <h2>
            Need Help?
        </h2>

        <p>
            Find quick answers to the most common questions —
            or reach our team directly.
        </p>

    </div>


    <div class="cx-help-grid">

        <a
            href="/webprogg/host/howitworks.php"
            class="cx-help-card cx-reveal"
            style="--i: 0;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                </svg>

            </span>

            <span>
                How to rent a room?
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>


        <a
            href="/webprogg/Listings/listing.php"
            class="cx-help-card cx-reveal"
            style="--i: 1;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                </svg>

            </span>

            <span>
                How to search listings?
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>


        <a
            href="/webprogg/host/becomeahost.php"
            class="cx-help-card cx-reveal"
            style="--i: 2;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="8" r="3.5"/>
                    <path d="M3.5 20a6.5 6.5 0 0 1 13 0"/>
                    <path d="M19 8v6M16 11h6"/>
                </svg>

            </span>

            <span>
                How to become a host?
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>


        <a
            href="/webprogg/host/howitworks.php"
            class="cx-help-card cx-reveal"
            style="--i: 3;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/>
                    <path d="M2.5 10h19"/>
                </svg>

            </span>

            <span>
                Payment &amp; booking
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>


        <a
            href="/webprogg/Listings/listing.php"
            class="cx-help-card cx-reveal"
            style="--i: 4;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s7-5.1 7-11a7 7 0 1 0-14 0c0 5.9 7 11 7 11z"/>
                    <circle cx="12" cy="10" r="2.6"/>
                </svg>

            </span>

            <span>
                Location help
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>


        <a
            href="#contact-form"
            class="cx-help-card cx-reveal"
            style="--i: 5;"
        >

            <span class="cx-help-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a8.5 8.5 0 0 1-12.4 7.5L3 21l1.6-5A8.5 8.5 0 1 1 21 12z"/>
                </svg>

            </span>

            <span>
                Contact Support
            </span>

            <span class="cx-help-arrow">
                &rsaquo;
            </span>

        </a>

    </div>

</section>


<!-- =====================================================
     SUPPORT CTA
===================================================== -->

<section class="cx-cta-wrap">

    <div class="cx-cta cx-reveal">

        <div class="cx-cta-left">

            <div class="cx-cta-ico">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 13a8 8 0 0 1 16 0"/>
                    <rect x="2.5" y="13" width="4" height="6" rx="2"/>
                    <rect x="17.5" y="13" width="4" height="6" rx="2"/>
                    <path d="M19.5 19a3.5 3.5 0 0 1-3.5 3h-2"/>
                </svg>

            </div>


            <div class="cx-cta-text">

                <span class="cx-cta-kicker">
                    We Reply Fast
                </span>

                <h2>
                    Still have questions?
                </h2>

                <p>
                    Our support team is always ready<br>
                    to assist you.
                </p>

            </div>

        </div>


        <div class="cx-cta-buttons">

            <a
                href="#contact-form"
                class="cx-btn cx-btn-honey cx-btn-shine"
            >

                CONTACT SUPPORT

                <span class="cx-btn-arrow">&rarr;</span>

            </a>

        </div>

    </div>

</section>


<!-- =========================================================
     SHARED FOOTER — single source of truth (includes/footer.php)
========================================================= -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>


<!-- =========================================================
     PAGE SCRIPT — scroll reveal, success auto-dismiss,
     double-submit guard
========================================================= -->
<script>
(function () {
    "use strict";

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- Scroll reveal ---- */
    var revealEls = Array.prototype.slice.call(
        document.querySelectorAll(".cx-reveal")
    );

    if (reduced || !("IntersectionObserver" in window)) {

        revealEls.forEach(function (el) {
            el.classList.add("in-view");
        });

    } else {

        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;

                    var el = entry.target;
                    io.unobserve(el);
                    el.classList.add("in-view");

                    window.setTimeout(function () {
                        el.style.setProperty("--i", "0");
                    }, 1200);
                });
            },
            { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
        );

        revealEls.forEach(function (el) {
            io.observe(el);
        });
    }


    /* ---- Auto-dismiss the success message after ~6s ---- */
    var successMsg = document.getElementById("cxSuccessMsg");

    if (successMsg) {
        window.setTimeout(function () {
            successMsg.classList.add("cx-out");

            window.setTimeout(function () {
                successMsg.style.display = "none";
            }, 450);
        }, 6000);
    }


    /* ---- Double-submit guard ---- */
    var form = document.getElementById("cxContactForm");

    if (form) {
        form.addEventListener("submit", function () {
            var btn = form.querySelector(".cx-submit");
            var label = form.querySelector(".cx-submit-label");

            if (btn && !btn.disabled) {
                btn.disabled = true;

                if (label) {
                    label.textContent = "SENDING...";
                }
            }
        });
    }
})();
</script>


<!-- =========================================================
     NAVBAR ACTIVE GLOW (fallback)
========================================================= -->
<script>
(function () {
    "use strict";

    var here = window.location.pathname.toLowerCase();

    document.querySelectorAll(".navbar .nav-links > a").forEach(function (link) {
        if (link.classList.contains("nav-bell")) { return; }

        try {
            var linkPath = new URL(link.href, window.location.origin).pathname.toLowerCase();

            if (linkPath === here) {
                link.classList.add("active");
            }
        } catch (e) {
            /* ignore malformed hrefs */
        }
    });
})();
</script>


</body>

</html>