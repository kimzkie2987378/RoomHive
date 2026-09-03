<?php
session_start();
/*
 * =========================================================
 * ROOMHIVE - HOST DASHBOARD ("Host Profile") hostdashboard.php
 * =========================================================
 *
 * DEMO / PROTOTYPE VERSION - no database yet.
 * Everything here is stored in $_SESSION so you can see the
 * whole flow working end-to-end today. When you're ready to
 * wire up MySQL, swap the $_SESSION reads/writes below for
 * real queries and the rest of the page stays the same.
 *
 * HOW A USER GETS HERE:
 *   1. They log in (loginform.php sets $_SESSION['logged_in']).
 *   2. They complete becomeahost.php ("List Your Space" /
 *      host application). That page now sets
 *      $_SESSION['is_host'] = true and sends them here.
 *   3. From then on, this page IS their host profile.
 */

// ---------------------------------------------------------
// AUTH CHECKS
// ---------------------------------------------------------

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: loginform.php");
    exit();
}

// Must have completed the host application before seeing this page.
if (!isset($_SESSION['is_host']) || $_SESSION['is_host'] !== true) {
    header("Location: becomeahost.php");
    exit();
}

$userName = $_SESSION['user_name'] ?? 'Host';

// Info collected back in becomeahost.php (step 1 of the host application)
$hostApplication = $_SESSION['host_application'] ?? [];

$fullName = $hostApplication['full_name'] ?? $userName;
$email    = $hostApplication['email'] ?? ($_SESSION['user_email'] ?? '');
$phone    = $hostApplication['phone'] ?? '';
$location = $hostApplication['location'] ?? '';
$bio      = $_SESSION['host_bio'] ?? 'Tell renters a bit about yourself. Click "Edit Profile" to add a short bio.';

// Member since - stamp the first time we ever see this host
if (!isset($_SESSION['host_member_since'])) {
    $_SESSION['host_member_since'] = date('F Y');
}
$memberSince = $_SESSION['host_member_since'];

// Avatar - falls back to a default placeholder image
$avatarImage = $_SESSION['host_avatar'] ?? 'DefaultAvatar.png';


// ---------------------------------------------------------
// LISTINGS (session-backed for now)
// ---------------------------------------------------------

if (!isset($_SESSION['host_listings']) || !is_array($_SESSION['host_listings'])) {
    $_SESSION['host_listings'] = [];
}

// ----- Handle "quick add" listing form (stand-in for the real
//       Add Your Space / Upload Photos steps, which don't exist
//       yet). Remove this block once those steps are built and
//       push finished listings into $_SESSION['host_listings']
//       from there instead. -----
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_listing'])) {
    $newTitle    = trim($_POST['listing_title'] ?? '');
    $newLocation = trim($_POST['listing_location'] ?? '');
    $newPrice    = trim($_POST['listing_price'] ?? '');

    if ($newTitle === '' || $newLocation === '') {
        $formError = 'Please enter at least a title and location for your space.';
    } else {
        $_SESSION['host_listings'][] = [
            'id'        => uniqid('listing_'),
            'title'     => $newTitle,
            'location'  => $newLocation,
            'price'     => $newPrice,
            'image'     => 'ListingPlaceholder.png',
            'bookings'  => 0,
            'occupancy' => 0,
            'earnings'  => 0,
            'status'    => 'Pending Review',
        ];

        // Redirect (Post/Redirect/Get) so refreshing the page
        // doesn't resubmit the form.
        header("Location: hostdashboard.php");
        exit();
    }
}

// ----- Handle listing removal -----
if (isset($_GET['remove_listing'])) {
    $removeId = $_GET['remove_listing'];

    $_SESSION['host_listings'] = array_values(array_filter(
        $_SESSION['host_listings'],
        fn($listing) => $listing['id'] !== $removeId
    ));

    header("Location: hostdashboard.php");
    exit();
}

$listings = $_SESSION['host_listings'];


// ---------------------------------------------------------
// PERFORMANCE OVERVIEW
// Derived from the listings above. Views/percent-change have
// no real analytics behind them yet, so they simply show 0
// until you have real traffic/booking data to compute from.
// ---------------------------------------------------------

$totalBookings  = array_sum(array_column($listings, 'bookings'));
$totalEarnings  = array_sum(array_column($listings, 'earnings'));
$occupancyList  = array_column($listings, 'occupancy');
$avgOccupancy   = count($occupancyList) > 0
    ? round(array_sum($occupancyList) / count($occupancyList))
    : 0;
$totalViews     = $_SESSION['host_total_views'] ?? 0;

$navLinks = [
    ['label' => 'HOME', 'href' => 'usershome.php'],
    ['label' => 'LISTINGS', 'href' => 'listing.php'],
    ['label' => 'HOW IT WORKS', 'href' => 'howitworks.php'],
    ['label' => 'BECOME A HOST', 'href' => 'becomeahost.php'],
    ['label' => 'HIVE CLUB', 'href' => 'hiveclub.php'],
    ['label' => 'CONTACTS', 'href' => 'contacts.php'],
];

$sidebarLinks = [
    ['label' => 'Overview',              'href' => 'hostdashboard.php', 'icon' => '&#8962;', 'active' => true],
    ['label' => 'My Listings',           'href' => '#',                 'icon' => '&#8962;'],
    ['label' => 'Bookings',               'href' => '#',                 'icon' => '&#128197;'],
    ['label' => 'Earnings',               'href' => '#',                 'icon' => '&#128176;'],
    ['label' => 'Payouts',                'href' => '#',                 'icon' => '&#128179;'],
    ['label' => 'Messages',               'href' => '#',                 'icon' => '&#9733;'],
    ['label' => 'Profile & Account',      'href' => '#',                 'icon' => '&#128100;'],
    ['label' => 'Verification',           'href' => '#',                 'icon' => '&#10003;'],
    ['label' => 'Payout Methods',         'href' => '#',                 'icon' => '&#127974;'],
    ['label' => 'Notification Settings',  'href' => '#',                 'icon' => '&#128276;'],
    ['label' => 'Security',               'href' => '#',                 'icon' => '&#128274;'],
    ['label' => 'Help Center',            'href' => '#',                 'icon' => '&#10067;'],
];

$currentYear = date('Y');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Host Dashboard - RoomHive</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="host-dashboard.css" />
  </head>
  <body class="hd-body">

    <!-- =========================
         HOST DASHBOARD NAVBAR
    ========================== -->
    <nav class="hd-navbar">

        <a href="usershome.php" class="logo">
            <img src="images/RoomHiveLogos.png" alt="RoomHive Logo">
        </a>

        <div class="hd-nav-links">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?php echo htmlspecialchars($link['href']); ?>">
                    <?php echo htmlspecialchars($link['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="hd-nav-right">

            <button type="button" class="hd-bell" aria-label="Notifications">
                &#128276;
                <span class="hd-bell-badge">3</span>
            </button>

            <div class="hd-account-dropdown">
                <button
                    type="button"
                    class="hd-account-toggle"
                    id="hdAccountToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    onclick="hdToggleAccountMenu()"
                >
                    <img src="<?php echo htmlspecialchars($avatarImage); ?>" alt="" class="hd-nav-avatar">
                    <span><?php echo htmlspecialchars($fullName); ?></span>
                </button>

                <div class="hd-account-menu" id="hdAccountMenu">
                    <a href="hostdashboard.php">Host Dashboard</a>
                    <a href="usershome.php">Switch to Renter View</a>
                    <a href="myaccount.php">My Account</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>

        </div>

    </nav>

    <!-- =========================
         WELCOME HERO
    ========================== -->
    <section class="hd-hero">
        <img class="hd-hero-image" src="images/HostDashboardHero.png" alt="" />
        <div class="hd-hero-overlay"></div>

        <div class="hd-hero-content">
            <p class="hd-hero-eyebrow">Welcome back,</p>
            <h1><?php echo htmlspecialchars($fullName); ?>!</h1>
            <p class="hd-hero-sub">
                Manage your account, listings, and payouts all in one place.
            </p>
        </div>

        <div class="hd-earnings-card">
            <span class="hd-earnings-label">Total Earnings</span>
            <span class="hd-earnings-amount">&#8369; <?php echo number_format($totalEarnings); ?></span>
            <span class="hd-earnings-period">This Month</span>
        </div>
    </section>

    <!-- =========================
         MAIN LAYOUT
    ========================== -->
    <div class="hd-layout">

        <!-- =====================
             SIDEBAR
        ====================== -->
        <aside class="hd-sidebar">

            <div class="hd-profile-mini">
                <img src="<?php echo htmlspecialchars($avatarImage); ?>" alt="" class="hd-mini-avatar">
                <div class="hd-mini-info">
                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                    <span class="hd-host-badge">Host</span>
                    <span class="hd-member-since">Member since <?php echo htmlspecialchars($memberSince); ?></span>
                </div>
            </div>

            <ul class="hd-sidebar-nav">
                <?php foreach ($sidebarLinks as $link): ?>
                    <li>
                        <a
                            href="<?php echo htmlspecialchars($link['href']); ?>"
                            class="<?php echo !empty($link['active']) ? 'active' : ''; ?>"
                        >
                            <span class="hd-sidebar-icon"><?php echo $link['icon']; ?></span>
                            <?php echo htmlspecialchars($link['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a href="logout.php" class="hd-logout">
                        <span class="hd-sidebar-icon">&#8618;</span>
                        Log Out
                    </a>
                </li>
            </ul>

        </aside>

        <!-- =====================
             MAIN CONTENT
        ====================== -->
        <main class="hd-main">

            <!-- PROFILE INFORMATION -->
            <section class="hd-card">
                <div class="hd-card-header">
                    <h2>Profile Information</h2>
                    <a href="#" class="hd-btn-outline">Edit Profile</a>
                </div>

                <div class="hd-profile-grid">
                    <div class="hd-profile-photo">
                        <img src="<?php echo htmlspecialchars($avatarImage); ?>" alt="">
                        <span class="hd-photo-edit">&#128247;</span>
                    </div>

                    <div class="hd-profile-col">
                        <div class="hd-field">
                            <span class="hd-field-label">Full Name</span>
                            <span class="hd-field-value"><?php echo htmlspecialchars($fullName ?: '—'); ?></span>
                        </div>
                        <div class="hd-field">
                            <span class="hd-field-label">Email Address</span>
                            <span class="hd-field-value"><?php echo htmlspecialchars($email ?: '—'); ?></span>
                        </div>
                        <div class="hd-field">
                            <span class="hd-field-label">Phone Number</span>
                            <span class="hd-field-value"><?php echo htmlspecialchars($phone ?: '—'); ?></span>
                        </div>
                    </div>

                    <div class="hd-profile-col">
                        <div class="hd-field">
                            <span class="hd-field-label">Location</span>
                            <span class="hd-field-value">&#9679; <?php echo htmlspecialchars($location ?: '—'); ?></span>
                        </div>
                        <div class="hd-field">
                            <span class="hd-field-label">Bio</span>
                            <span class="hd-field-value hd-bio"><?php echo htmlspecialchars($bio); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PERFORMANCE OVERVIEW -->
            <section class="hd-card">
                <div class="hd-card-header">
                    <h2>Performance Overview</h2>
                    <select class="hd-period-select">
                        <option>This Month</option>
                        <option>Last Month</option>
                        <option>This Year</option>
                    </select>
                </div>

                <div class="hd-stats-grid">

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#128065;</span>
                        <span class="hd-stat-label">Views</span>
                        <span class="hd-stat-value"><?php echo number_format($totalViews); ?></span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#128197;</span>
                        <span class="hd-stat-label">Bookings</span>
                        <span class="hd-stat-value"><?php echo number_format($totalBookings); ?></span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#128202;</span>
                        <span class="hd-stat-label">Occupancy Rate</span>
                        <span class="hd-stat-value"><?php echo $avgOccupancy; ?>%</span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#128176;</span>
                        <span class="hd-stat-label">Earnings</span>
                        <span class="hd-stat-value">&#8369; <?php echo number_format($totalEarnings); ?></span>
                    </div>

                </div>
            </section>

            <!-- MY LISTINGS -->
            <section class="hd-card">
                <div class="hd-card-header">
                    <h2>My Listings</h2>
                    <a href="listing.php" class="hd-btn-outline">View All Listings</a>
                </div>

                <?php if (empty($listings)): ?>

                    <div class="hd-empty-state">
                        <p>You haven't listed a space yet. Add your first one below to see it appear here.</p>
                    </div>

                <?php else: ?>

                    <div class="hd-listings-list">
                        <?php foreach ($listings as $listing): ?>
                            <div class="hd-listing-row">

                                <img
                                    src="<?php echo htmlspecialchars($listing['image']); ?>"
                                    alt=""
                                    class="hd-listing-thumb"
                                >

                                <div class="hd-listing-main">
                                    <strong><?php echo htmlspecialchars($listing['title']); ?></strong>
                                    <span><?php echo htmlspecialchars($listing['location']); ?></span>
                                </div>

                                <div class="hd-listing-metric">
                                    <span class="hd-metric-label">Bookings</span>
                                    <span class="hd-metric-value"><?php echo (int)$listing['bookings']; ?></span>
                                </div>

                                <div class="hd-listing-metric">
                                    <span class="hd-metric-label">Occupancy</span>
                                    <span class="hd-metric-value"><?php echo (int)$listing['occupancy']; ?>%</span>
                                </div>

                                <div class="hd-listing-metric">
                                    <span class="hd-metric-label">Earnings</span>
                                    <span class="hd-metric-value">&#8369; <?php echo number_format($listing['earnings']); ?></span>
                                </div>

                                <span class="hd-status-pill hd-status-<?php echo strtolower(str_replace(' ', '-', $listing['status'])); ?>">
                                    <?php echo htmlspecialchars($listing['status']); ?>
                                </span>

                                <div class="hd-listing-menu">
                                    <button type="button" class="hd-kebab" onclick="hdToggleListingMenu(this)">&#8942;</button>
                                    <div class="hd-listing-menu-panel">
                                        <a href="#">Edit</a>
                                        <a
                                            href="hostdashboard.php?remove_listing=<?php echo urlencode($listing['id']); ?>"
                                            onclick="return confirm('Remove this listing?');"
                                        >Remove</a>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <!-- =====================================================
                     QUICK ADD LISTING
                     Stand-in for the real "Add Your Space" / "Upload
                     Photos" host-onboarding steps, which don't exist
                     yet. Once those are built, remove this form and
                     push completed listings into $_SESSION['host_listings']
                     from there instead.
                ====================================================== -->
                <details class="hd-quick-add">
                    <summary>+ Add a listing</summary>

                    <?php if ($formError !== ''): ?>
                        <p class="hd-form-error"><?php echo htmlspecialchars($formError); ?></p>
                    <?php endif; ?>

                    <form method="POST" action="hostdashboard.php" class="hd-quick-add-form">
                        <input type="hidden" name="add_listing" value="1">

                        <div class="hd-quick-add-grid">
                            <input type="text" name="listing_title" placeholder="Listing title (e.g. Cozy Studio Apartment)" required>
                            <input type="text" name="listing_location" placeholder="City, Province" required>
                            <input type="number" name="listing_price" placeholder="Price per month (&#8369;)" min="0">
                        </div>

                        <button type="submit" class="hd-btn-primary">List This Space</button>
                    </form>
                </details>

            </section>

            <!-- GROW YOUR HOSTING BUSINESS -->
            <section class="hd-grow-banner">
                <img src="images/HostGrowIllustration.png" alt="" class="hd-grow-illustration">

                <div class="hd-grow-text">
                    <h3>Grow Your Hosting Business</h3>
                    <p>Get more bookings and increase your earnings with these host tools.</p>
                </div>

                <div class="hd-grow-tiles">
                    <a href="#" class="hd-grow-tile">
                        <span class="hd-grow-tile-icon">&#128640;</span>
                        <strong>Boost Your Listing</strong>
                        <span>Get more visibility</span>
                    </a>
                    <a href="#" class="hd-grow-tile">
                        <span class="hd-grow-tile-icon">&#128161;</span>
                        <strong>Host Tips</strong>
                        <span>Learn and improve</span>
                    </a>
                    <a href="#" class="hd-grow-tile">
                        <span class="hd-grow-tile-icon">&#129309;</span>
                        <strong>Invite &amp; Earn</strong>
                        <span>Earn more rewards</span>
                    </a>
                </div>
            </section>

        </main>

    </div>

    <!-- =========================
         FOOTER
    ========================== -->
    <footer class="site-footer">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
                <div class="footer-contact-line">
                    <img src="images/PhoneIcon.jpg" alt="Phone">
                    <span>+639275693574</span>
                </div>
                <div class="footer-contact-line">
                    <img src="images/EmailIcon.jpg" alt="Email">
                    <span>RoomHive@gmail.com</span>
                </div>
            </div>

            <div class="footer-links">
                <span class="footer-heading">LISTINGS</span>
                <a href="listing.php?type=studio-loft">Studios</a>
                <a href="listing.php?type=shared-bedroom">Shared Rooms</a>
                <a href="listing.php?type=entire-house">Entire House</a>
                <a href="listing.php">Featured Stays</a>
            </div>

            <div class="footer-links">
                <span class="footer-heading">QUICK LINKS</span>
                <a href="index.php">About Us</a>
                <a href="contacts.php">Contact</a>
                <a href="becomeahost.php">Become A Host</a>
                <a href="hiveclub.php">Hive Club</a>
            </div>

            <div class="footer-contact">
                <span class="footer-heading">GET THE APP</span>
                <div class="footer-app-badges">
                    <img src="images/AppStore.jpg" alt="Download on the App Store">
                    <img src="images/GooglePlay.jpg" alt="Get it on Google Play">
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo htmlspecialchars($currentYear); ?> RoomHive. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function hdToggleAccountMenu() {
            const menu = document.getElementById('hdAccountMenu');
            const toggle = document.getElementById('hdAccountToggle');
            const isOpen = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        function hdToggleListingMenu(button) {
            const panel = button.nextElementSibling;
            const isOpen = panel.classList.contains('open');

            document.querySelectorAll('.hd-listing-menu-panel.open').forEach(function (p) {
                p.classList.remove('open');
            });

            if (!isOpen) {
                panel.classList.add('open');
            }
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.hd-account-dropdown')) {
                document.getElementById('hdAccountMenu').classList.remove('open');
            }
            if (!event.target.closest('.hd-listing-menu')) {
                document.querySelectorAll('.hd-listing-menu-panel.open').forEach(function (p) {
                    p.classList.remove('open');
                });
            }
        });
    </script>

  </body>
</html>