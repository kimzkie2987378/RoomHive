<?php
/* =========================================================
   ROOMHIVE — SHARED FOOTER INCLUDE
   ---------------------------------------------------------
   Usage (any page, right before </body>):
       <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>

   Optional variables a page may set BEFORE the include:
       $currentPage          — href of the nav/footer link to mark active
       $isLoggedIn           — makes the logo link go to usershome for logged-in users
       $footerQuickLinks     — override the QUICK LINKS array
       $footerListings       — override the LISTINGS array
       $footerShowAdminLink  — true shows the small Admin link (homepage only)
       $isAdminLoggedIn      — sends the Admin link to the dashboard vs login
   ========================================================= */

/* ---- Fallbacks so the include never fatals on any page ---- */
 $isLoggedIn         = $isLoggedIn ?? false;
 $currentPage        = $currentPage ?? '';
 $isAdminLoggedIn    = $isAdminLoggedIn ?? false;
 $footerShowAdminLink = $footerShowAdminLink ?? false;

 $footerListings = $footerListings ?? [
    "Shared Bedroom" => "shared-bedroom",
    "Private Room"   => "private-room",
    "Entire House"   => "entire-house",
    "Boarding House" => "boarding-house",
    "Studio Loft"    => "studio-loft",
];

 $footerQuickLinks = $footerQuickLinks ?? [
    "About Us"      => "/webprogg/index.php",
    "How It Works"  => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Contacts"      => "/webprogg/misc/contacts.php",
];

/* HIVE CLUB REMOVED — this include is now the single source
   of truth for footer links. Do not re-add hiveclub.php. */

 $footerBrandHref = $isLoggedIn ? "/webprogg/user/usershome.php" : "/webprogg/index.php";
 $footerAdminHref = $isAdminLoggedIn ? "/webprogg/admin/admin.php" : "/webprogg/auth/adminlogin.php";
?>

<!-- Styles for the optional admin link — travels with the include -->
<style>
    .footer-bottom {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .footer-admin-link {
        font-size: 12px;
        color: #8B93A6;
        text-decoration: none;
        opacity: 0.8;
    }
    .footer-admin-link:hover { opacity: 1; text-decoration: underline; }
</style>

<footer class="site-footer">

    <div class="footer-top">

        <!-- BRAND -->
        <div class="footer-brand">

            <a href="<?php echo htmlspecialchars($footerBrandHref); ?>">
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

            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="Phone">
                <span>+63 927 569 3574</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="Email">
                <span>hello@roomhive.ph</span>
            </div>

        </div>

        <!-- LISTINGS -->
        <div class="footer-links">

            <span class="footer-heading">LISTINGS</span>

            <?php foreach ($footerListings as $label => $type): ?>
                <a href="/webprogg/Listings/listing.php?type=<?php echo urlencode($type); ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>

        </div>

        <!-- QUICK LINKS -->
        <div class="footer-links">

            <span class="footer-heading">QUICK LINKS</span>

            <?php foreach ($footerQuickLinks as $label => $href): ?>
                <a
                    href="<?php echo htmlspecialchars($href); ?>"
                    class="<?php echo ($href === $currentPage) ? 'active' : ''; ?>"
                >
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>

        </div>

        <!-- GET THE APP -->
        <div class="footer-contact">

            <span class="footer-heading">GET THE APP</span>

            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="Location">
                <span>Dumaguete City, Negros Oriental</span>
            </div>

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            &copy; <?php echo date("Y"); ?> RoomHive. All rights reserved.
        </p>

        <?php if ($footerShowAdminLink): ?>
            <a
                href="<?php echo htmlspecialchars($footerAdminHref); ?>"
                class="footer-admin-link"
            >
                Admin
            </a>
        <?php endif; ?>

    </div>

</footer>