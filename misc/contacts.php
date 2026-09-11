<?php
session_start();

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
    $avatarStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
    $avatarStmt->execute(['id' => $_SESSION['user_id']]);
    $avatarRow = $avatarStmt->fetch();
    $_SESSION['avatar_path'] = $avatarRow['avatar_path'] ?? null;
}
$navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

// =========================================================
// ROOMHIVE - CONTACTS PAGE
// =========================================================

// Current year
$currentYear = date("Y");

// Navigation links
$navigation = [
     "HOME" => $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php",
    "LISTINGS" => "/webprogg/listings/listing.php",
    "HOW IT WORKS" => "/webprogg/host/howitworks.php",
    "BECOME A HOST" => $isLoggedIn ? "/webprogg/host/becomeahost.php" : "/webprogg/auth/loginform.php",
    "HIVE CLUB" => "/webprogg/hiveclub.php",
    "CONTACTS" => "/webprogg/misc/contacts.php"
];

// Form status
$successMessage = "";
$errorMessage = "";

// =========================================================
// HANDLE CONTACT FORM
// =========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName = trim($_POST["first_name"] ?? "");
    $lastName  = trim($_POST["last_name"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $subject   = trim($_POST["subject"] ?? "");
    $message   = trim($_POST["message"] ?? "");

    // Check required fields
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


        $successMessage = "Thank you, $firstName! Your message has been received.";

        // Clear form values after successful submission
        $firstName = "";
        $lastName  = "";
        $email     = "";
        $subject   = "";
        $message   = "";
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- RoomHive CSS -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >

</head>


<body class="contacts-page">


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<header class="navbar">

    <!-- LOGO -->
    <div class="logo">

        <a href="<?php echo $isLoggedIn ? '/webprogg/user/myaccount.php' : '/webprogg/index.php'; ?>">

            <img
                src="/webprogg/images/RoomHiveLogos.png"
                alt="RoomHive Logo"
            >

        </a>

    </div>


    <!-- NAVIGATION -->
    <nav class="nav-links">

        <?php foreach ($navigation as $name => $link): ?>

            <a
                href="<?php echo htmlspecialchars($link); ?>"
                class="<?php echo ($name === 'CONTACTS') ? 'active' : ''; ?>"
            >

                <?php echo htmlspecialchars($name); ?>

            </a>

        <?php endforeach; ?>


        <?php if ($isLoggedIn): ?>

            <!-- MY ACCOUNT DROPDOWN -->
            <div class="account-dropdown">

                <button
                    type="button"
                    class="my-account"
                    id="accountDropdownToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    onclick="toggleAccountMenu()"
                >
                    <span class="account-circle">
                        <img src="<?php echo htmlspecialchars($navAvatar); ?>" alt="My Account">
                    </span>
                    <span>MY PROFILE</span>
                    <span class="dropdown-caret">&#9662;</span>
                </button>

                <div class="account-dropdown-menu" id="accountDropdownMenu">

                    <a href="/webprogg/user/userprofile.php">
                        My Profile
                    </a>

                    <a href="/webprogg/auth/logout.php">
                        Logout
                    </a>

                </div>

            </div>

        <?php else: ?>

            <!-- LIST YOUR SPACE -->

            <a
                href="/webprogg/auth/loginform.php"
                class="list-space"
            >

                LIST YOUR SPACE

            </a>

        <?php endif; ?>

    </nav>

</header>

<?php if ($isLoggedIn): ?>
<style>
    .account-dropdown {
        position: relative;
    }

    .account-dropdown .my-account {
        display: flex;
        align-items: center;
        gap: 6px;
        background: none;
        border: none;
        cursor: pointer;
        font: inherit;
        color: inherit;
    }

    .account-dropdown .dropdown-caret {
        font-size: 0.7em;
        transition: transform 0.15s ease;
    }

    .account-dropdown.open .dropdown-caret {
        transform: rotate(180deg);
    }

    .account-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        min-width: 160px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        z-index: 100;
        margin-top: 8px;
    }

    .account-dropdown.open .account-dropdown-menu {
        display: block;
    }

    .account-dropdown-menu a {
        display: block;
        padding: 10px 16px;
        text-decoration: none;
        color: #333;
        white-space: nowrap;
    }

    .account-dropdown-menu a:hover {
        background: #f5f5f5;
    }
</style>

<script>
    function toggleAccountMenu() {
        const dropdown = document.getElementById('accountDropdownToggle').closest('.account-dropdown');
        const toggle = document.getElementById('accountDropdownToggle');
        const isOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        const dropdown = document.querySelector('.account-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove('open');
            document.getElementById('accountDropdownToggle').setAttribute('aria-expanded', 'false');
        }
    });
</script>
<?php endif; ?>


<!-- =====================================================
     CONTACT HERO
===================================================== -->

<section class="contacts-hero">

    <div class="contacts-hero-content">

        <span class="contacts-eyebrow">
            GET IN TOUCH
        </span>

        <h1>
            Contact Us
        </h1>

        <p>
            We're here to help! Reach out to us<br>
            for any questions or support.
        </p>

    </div>


    <div class="contacts-hero-image">

        <img
            src="/webprogg/images/WholeLivingRoom-Contacts.png"
            alt="RoomHive living room"
        >

    </div>

</section>


<!-- =====================================================
     CONTACT INFORMATION + MESSAGE
===================================================== -->

<section class="contacts-main">


    <!-- =================================================
         CONTACT INFORMATION
    ================================================== -->

    <div class="contacts-information">

        <h2>
            Contact Information
        </h2>

        <p class="contacts-small-text">
            Choose the best way to reach us.
        </p>


        <!-- PHONE -->

        <div class="contacts-info-item">

            <div class="contacts-info-icon">
                ☎
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

        <div class="contacts-info-item">

            <div class="contacts-info-icon">
                ✉
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

        <div class="contacts-info-item">

            <div class="contacts-info-icon">

                <img
                    src="/webprogg/images/AddressIcon-Contacts.png"
                    alt="Address"
                >

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

        <div class="contacts-info-item">

            <div class="contacts-info-icon">

                <img
                    src="/webprogg/images/SocialMediaIcon-Contacts.png"
                    alt="Social Media"
                >

            </div>

            <div>

                <h4>
                    Social Media
                </h4>

                <p>
                    Follow us on social media!
                </p>


                <div class="contacts-socials">

                    <a
                        href="#"
                        aria-label="Facebook"
                    >
                        f
                    </a>

                    <a
                        href="#"
                        aria-label="Instagram"
                    >
                        ◎
                    </a>

                    <a
                        href="#"
                        aria-label="TikTok"
                    >
                        ♪
                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         SEND MESSAGE
    ================================================== -->

    <div class="contacts-form-wrapper">

        <h2>
            Send Us a Message
        </h2>

        <p class="contacts-small-text">
            Fill out the form below and we'll get back to you.
        </p>


        <!-- SUCCESS MESSAGE -->

        <?php if (!empty($successMessage)): ?>

            <div class="contact-success">

                <?php echo htmlspecialchars($successMessage); ?>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if (!empty($errorMessage)): ?>

            <div class="contact-error">

                <?php echo htmlspecialchars($errorMessage); ?>

            </div>

        <?php endif; ?>


        <!-- CONTACT FORM -->

        <form
            action="/webprogg/misc/contacts.php"
            method="POST"
        >


            <!-- NAME -->

            <div class="contacts-form-row">

                <div class="contacts-form-group">

                    <input
                        type="text"
                        name="first_name"
                        placeholder="First Name"
                        value="<?php echo htmlspecialchars($firstName ?? ''); ?>"
                        required
                    >

                </div>


                <div class="contacts-form-group">

                    <input
                        type="text"
                        name="last_name"
                        placeholder="Last Name"
                        value="<?php echo htmlspecialchars($lastName ?? ''); ?>"
                        required
                    >

                </div>

            </div>


            <!-- EMAIL -->

            <div class="contacts-form-group">

                <input
                    type="email"
                    name="email"
                    placeholder="Email Address"
                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                    required
                >

            </div>


            <!-- SUBJECT -->

            <div class="contacts-form-group">

                <input
                    type="text"
                    name="subject"
                    placeholder="Subject"
                    value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                    required
                >

            </div>


            <!-- MESSAGE -->

            <div class="contacts-form-group">

                <textarea
                    name="message"
                    placeholder="Your Message"
                    rows="5"
                    required
                ><?php echo htmlspecialchars($message ?? ''); ?></textarea>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="contacts-submit"
            >

                SEND MESSAGE

                <span>
                    ➤
                </span>

            </button>

        </form>

    </div>

</section>


<!-- =====================================================
     NEED HELP
===================================================== -->

<section class="contacts-need-help">

    <div class="contacts-help-image">

        <img
            src="/webprogg/images/HeadsetIcon-Contacts.png"
            alt="Need help"
        >

    </div>


    <div class="contacts-help-content">

        <h2>
            Need Help?
        </h2>

        <p>
            Find quick answers to your questions.
        </p>


        <div class="contacts-help-links">

            <a href="/webprogg/host/howitworks.php">

                <span class="help-icon">
                    ⌕
                </span>

                How to rent a room?

                <b>
                    ›
                </b>

            </a>


            <a href="/webprogg/listings/listing.php">

                <span class="help-icon">
                    ⌕
                </span>

                How to search listings?

                <b>
                    ›
                </b>

            </a>


            <a href="/webprogg/host/becomeahost.php">

                <span class="help-icon">
                    ♙
                </span>

                How to become a host?

                <b>
                    ›
                </b>

            </a>


            <a href="/webprogg/host/howitworks.php">

                <span class="help-icon">
                    ▣
                </span>

                Payment &amp; booking

                <b>
                    ›
                </b>

            </a>


            <a href="/webprogg/listings/listing.php">

                <span class="help-icon">
                    ⌖
                </span>

                Location help

                <b>
                    ›
                </b>

            </a>


            <a href="/webprogg/misc/contacts.php">

                <span class="help-icon">
                    ☎
                </span>

                Contact Support

                <b>
                    ›
                </b>

            </a>

        </div>

    </div>

</section>


<!-- =====================================================
     SUPPORT CTA
===================================================== -->

<section class="contacts-support">

    <div class="contacts-support-icon">

        <img
            src="/webprogg/images/StillHaveQuestionsIcon-Contacts.png"
            alt="Contact support"
        >

    </div>


    <div class="contacts-support-text">

        <h2>
            Still have questions?
        </h2>

        <p>
            Our support team is always ready<br>
            to assist you.
        </p>

    </div>


    <a
        href="/webprogg/misc/contacts.php"
        class="contacts-support-button"
    >
        CONTACT SUPPORT
    </a>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

    <div class="footer-top">


        <!-- BRAND -->

        <div class="footer-brand">

            <a href="<?php echo $isLoggedIn ? '/webprogg/user/myaccount.php' : '/webprogg/index.php'; ?>">

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                    class="footer-logo"
                >

            </a>

            <p class="footer-tagline">

                Your trusted platform for finding and listing
                quality living spaces — made simple, safe,
                and stress-free.

            </p>

        </div>


        <!-- LISTINGS -->

        <div class="footer-links">

            <span class="footer-heading">
                LISTINGS
            </span>

            <a href="/webprogg/listings/listing.php?type=shared-bedroom">
                Shared Bedroom
            </a>

            <a href="/webprogg/listings/listing.php?type=private-room">
                Private Room
            </a>

            <a href="/webprogg/listings/listing.php?type=entire-house">
                Entire House
            </a>

            <a href="/webprogg/listings/listing.php?type=boarding-house">
                Boarding House
            </a>

            <a href="/webprogg/listings/listing.php?type=studio-loft">
                Studio Loft
            </a>

        </div>


        <!-- QUICK LINKS -->

        <div class="footer-links">

            <span class="footer-heading">
                QUICK LINKS
            </span>

            <a href="/webprogg/index.php">
                About Us
            </a>

            <a href="/webprogg/host/howitworks.php">
                How It Works
            </a>

            <a href="/webprogg/host/becomeahost.php">
                Become a Host
            </a>

            <a href="/webprogg/hiveclub.php">
                Hive Club
            </a>

            <a href="/webprogg/misc/contacts.php" class="active">
                Contacts
            </a>

        </div>


        <!-- GET THE APP -->

        <div class="footer-contact">

            <span class="footer-heading">
                GET THE APP
            </span>


            <div class="footer-app-badges">

                <img
                    src="/webprogg/images/GooglePlay.jpg"
                    alt="Get it on Google Play"
                >

                <img
                    src="/webprogg/images/AppStore.jpg"
                    alt="Download on the App Store"
                >

            </div>


            <!-- PHONE -->

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/PhoneIcon.jpg"
                    alt="Phone"
                >

                <span>
                    +63 927 569 3574
                </span>

            </div>


            <!-- EMAIL -->

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/EmailIcon.jpg"
                    alt="Email"
                >

                <span>
                    hello@roomhive.ph
                </span>

            </div>


            <!-- LOCATION -->

            <div class="footer-contact-line">

                <img
                    src="/webprogg/images/GPSIcon.png"
                    alt="Location"
                >

                <span>
                    Dumaguete City, Negros Oriental
                </span>

            </div>

        </div>

    </div>


    <!-- =================================================
         FOOTER BOTTOM
    ================================================== -->

    <div class="footer-bottom">

        <p>

            &copy;
            <?php echo $currentYear; ?>

            RoomHive.
            All rights reserved.

        </p>

    </div>

</footer>


</body>
</html>