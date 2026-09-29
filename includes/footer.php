<?php
/* =========================================================
   ROOMHIVE — SHARED FOOTER INCLUDE (single merged version)
   /webprogg/includes/footer.php
   ---------------------------------------------------------
   Usage (any page, right before </body>):
       <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/footer.php'; ?>

   Optional variables a page may set BEFORE the include:
       $footerQuickLinks     — override the QUICK LINKS array
       $footerListings       — override the LISTINGS array
       $footerShowAdminLink  — true shows the small Admin link (homepage only)
       $isAdminLoggedIn      — sends the Admin link to dashboard vs login
       $currentPage          — href to mark active in QUICK LINKS

   Auto-derived (pages no longer need to set these):
       $isLoggedIn           — from $_SESSION['logged_in']
       $isAdminLoggedIn      — from $_SESSION['admin_logged_in']
   ========================================================= */

/* ---- Fallbacks so the include never fatals on any page ---- */
 $isLoggedIn          = $isLoggedIn ?? (($_SESSION['logged_in'] ?? false) === true);
 $currentPage         = $currentPage ?? '';
 $isAdminLoggedIn     = $isAdminLoggedIn ?? (($_SESSION['admin_logged_in'] ?? false) === true);
 $footerShowAdminLink = $footerShowAdminLink ?? false;

/* ---------------------------------------------------------
   LISTING URL BUILDER
   ⚠ If listing.php reads $_GET['type'] instead of
   $_GET['category'], change 'category' HERE — one place
   fixes every listing link in every page's footer.
--------------------------------------------------------- */
 $footerListingParam = $footerListingParam ?? 'category';

 $footerListings = $footerListings ?? [
    "Studios"        => "studio-loft",
    "Shared Rooms"   => "shared-bedroom",
    "Entire House"   => "entire-house",
    "Featured Stays" => "",   /* empty slug = plain listing.php */
];

 $footerQuickLinks = $footerQuickLinks ?? [
    "About Us"      => "/webprogg/index.php",
    "How It Works"  => "/webprogg/host/howitworks.php",
    "Become a Host" => "/webprogg/host/becomeahost.php",
    "Contacts"      => "/webprogg/misc/contacts.php",
];

/* HIVE CLUB REMOVED — same defensive filter as navbar.php.
   Strips any override entry pointing at hiveclub.php. */
foreach ($footerQuickLinks as $fqLabel => $fqHref) {
    if (stripos((string) $fqHref, 'hiveclub.php') !== false) {
        unset($footerQuickLinks[$fqLabel]);
    }
}

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
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>

            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="Phone">
                <span>+63 927 569 3574</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.png" alt="Email">
                <span>hello@roomhive.ph</span>
            </div>

            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="Location">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>

        </div>

        <!-- LISTINGS -->
        <div class="footer-links">

            <span class="footer-heading">LISTINGS</span>

            <?php foreach ($footerListings as $label => $slug): ?>
                <?php if ($slug === ''): ?>
                    <a href="/webprogg/Listings/listing.php">
                        <?php echo htmlspecialchars($label); ?>
                    </a>
                <?php else: ?>
                    <a href="/webprogg/Listings/listing.php?<?php echo urlencode($footerListingParam); ?>=<?php echo urlencode($slug); ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </a>
                <?php endif; ?>
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