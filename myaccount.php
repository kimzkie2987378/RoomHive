<?php
session_start();
/*
 * =========================================================
 * ROOMHIVE - MY ACCOUNT (RENTER DASHBOARD)
 * =========================================================
 *
 * DEMO / PROTOTYPE VERSION - no database yet, same approach
 * as hostdashboard.php: everything lives in $_SESSION so the
 * whole page is testable today. Swap the $_SESSION reads/
 * writes for real queries once you have a database.
 *
 * This is the account page every logged-in user sees
 * ("My Account" in the navbar dropdown) whether or not
 * they are also a host. Hosts get an extra "Host Dashboard"
 * link alongside this one.
 */

// ---------------------------------------------------------
// AUTH CHECK
// ---------------------------------------------------------

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: loginform.php");
    exit();
}

$userName  = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$isHost    = isset($_SESSION['is_host']) && $_SESSION['is_host'] === true;

// Renter profile details (location / phone / bio). Falls back
// to sensible placeholders until the user edits their profile.
if (!isset($_SESSION['renter_profile']) || !is_array($_SESSION['renter_profile'])) {
    $_SESSION['renter_profile'] = [
        'location' => '',
        'phone'    => '',
        'bio'      => '',
    ];
}
$renterProfile = $_SESSION['renter_profile'];

if (!isset($_SESSION['renter_member_since'])) {
    $_SESSION['renter_member_since'] = date('F Y');
}
$memberSince = $_SESSION['renter_member_since'];

$avatarImage = $_SESSION['renter_avatar'] ?? 'DefaultAvatar.png';


// ---------------------------------------------------------
// BOOKINGS (session-backed for now)
// ---------------------------------------------------------

if (!isset($_SESSION['bookings']) || !is_array($_SESSION['bookings'])) {
    $_SESSION['bookings'] = [];
}

$bookingFormError = '';

// ----- Quick-add booking (stand-in for a real booking flow,
//       which doesn't exist yet). Remove once listing.php can
//       create real bookings, and push those into
//       $_SESSION['bookings'] from there instead. -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    $title    = trim($_POST['booking_title'] ?? '');
    $location = trim($_POST['booking_location'] ?? '');
    $dates    = trim($_POST['booking_dates'] ?? '');
    $amount   = trim($_POST['booking_amount'] ?? '');
    $status   = trim($_POST['booking_status'] ?? 'Upcoming');
    $rating   = trim($_POST['booking_rating'] ?? '');

    if ($title === '' || $location === '') {
        $bookingFormError = 'Please enter at least a listing name and location.';
    } else {
        $_SESSION['bookings'][] = [
            'id'       => uniqid('booking_'),
            'title'    => $title,
            'location' => $location,
            'dates'    => $dates,
            'amount'   => is_numeric($amount) ? (float)$amount : 0,
            'status'   => $status !== '' ? $status : 'Upcoming',
            'rating'   => ($rating !== '' && is_numeric($rating)) ? (float)$rating : null,
            'image'    => 'ListingPlaceholder.png',
        ];

        header("Location: myaccount.php");
        exit();
    }
}

if (isset($_GET['remove_booking'])) {
    $removeId = $_GET['remove_booking'];
    $_SESSION['bookings'] = array_values(array_filter(
        $_SESSION['bookings'],
        fn($b) => $b['id'] !== $removeId
    ));
    header("Location: myaccount.php");
    exit();
}

$bookings = $_SESSION['bookings'];

// Most recent first for the "Recent Bookings" list
$recentBookings = array_slice(array_reverse($bookings), 0, 3);


// ---------------------------------------------------------
// WISHLIST (session-backed for now)
// ---------------------------------------------------------

if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$wishlistFormError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_wishlist'])) {
    $wTitle    = trim($_POST['wishlist_title'] ?? '');
    $wLocation = trim($_POST['wishlist_location'] ?? '');
    $wPrice    = trim($_POST['wishlist_price'] ?? '');

    if ($wTitle === '' || $wLocation === '') {
        $wishlistFormError = 'Please enter at least a listing name and location.';
    } else {
        $_SESSION['wishlist'][] = [
            'id'       => uniqid('wishlist_'),
            'title'    => $wTitle,
            'location' => $wLocation,
            'price'    => is_numeric($wPrice) ? (float)$wPrice : 0,
            'image'    => 'ListingPlaceholder.png',
        ];

        header("Location: myaccount.php");
        exit();
    }
}

if (isset($_GET['remove_wishlist'])) {
    $removeId = $_GET['remove_wishlist'];
    $_SESSION['wishlist'] = array_values(array_filter(
        $_SESSION['wishlist'],
        fn($w) => $w['id'] !== $removeId
    ));
    header("Location: myaccount.php");
    exit();
}

$wishlist = $_SESSION['wishlist'];


// ---------------------------------------------------------
// PAYMENT METHODS (session-backed for now)
// NOTE: only ever store the card brand + last 4 digits +
// expiry for a demo like this - never a full card number.
// ---------------------------------------------------------

if (!isset($_SESSION['payment_methods']) || !is_array($_SESSION['payment_methods'])) {
    $_SESSION['payment_methods'] = [];
}

$paymentFormError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $brand   = trim($_POST['card_brand'] ?? '');
    $last4   = trim($_POST['card_last4'] ?? '');
    $expiry  = trim($_POST['card_expiry'] ?? '');

    if ($brand === '' || !preg_match('/^\d{4}$/', $last4) || $expiry === '') {
        $paymentFormError = 'Please choose a card type, enter the last 4 digits, and an expiry date.';
    } else {
        $_SESSION['payment_methods'][] = [
            'id'     => uniqid('card_'),
            'brand'  => $brand,
            'last4'  => $last4,
            'expiry' => $expiry,
        ];

        header("Location: myaccount.php");
        exit();
    }
}

if (isset($_GET['remove_payment'])) {
    $removeId = $_GET['remove_payment'];
    $_SESSION['payment_methods'] = array_values(array_filter(
        $_SESSION['payment_methods'],
        fn($p) => $p['id'] !== $removeId
    ));
    header("Location: myaccount.php");
    exit();
}

$paymentMethods = $_SESSION['payment_methods'];


// ---------------------------------------------------------
// ACCOUNT SECURITY
// ---------------------------------------------------------

if (!isset($_SESSION['two_factor_enabled'])) {
    $_SESSION['two_factor_enabled'] = false;
}

if (isset($_GET['toggle_2fa'])) {
    $_SESSION['two_factor_enabled'] = !$_SESSION['two_factor_enabled'];
    header("Location: myaccount.php");
    exit();
}

$twoFactorEnabled = $_SESSION['two_factor_enabled'];


// ---------------------------------------------------------
// STATS
// ---------------------------------------------------------

$bookingsCount = count($bookings);
$wishlistCount = count($wishlist);
$totalSpent    = array_sum(array_column($bookings, 'amount'));

$ratings = array_filter(array_column($bookings, 'rating'), fn($r) => $r !== null);
$averageRating = count($ratings) > 0
    ? round(array_sum($ratings) / count($ratings), 1)
    : null;


$navLinks = [
    ['label' => 'HOME', 'href' => 'usershome.php'],
    ['label' => 'LISTINGS', 'href' => 'listing.php'],
    ['label' => 'HOW IT WORKS', 'href' => 'howitworks.php'],
    ['label' => 'BECOME A HOST', 'href' => 'becomeahost.php'],
    ['label' => 'HIVE CLUB', 'href' => 'hiveclub.php'],
    ['label' => 'CONTACTS', 'href' => 'contacts.php'],
];

// ---------------------------------------------------------
// SIDEBAR NAVIGATION
// ---------------------------------------------------------
//
// 'href'  - where the link goes. Items with a matching
//           section on THIS page use an in-page anchor
//           (#ma-...) so the link actually does something;
//           smooth scrolling is already on via style.css's
//           `html { scroll-behavior: smooth; }`.
// 'built' - true  = a real, working link (in-page anchor or
//                    its own page).
//           false = not built yet. Rendered as a disabled
//                    "Soon" item instead of a dead `#` link
//                    so it doesn't look broken.
// ---------------------------------------------------------

$sidebarLinks = [
    ['label' => 'Overview',              'href' => 'myaccount.php', 'icon' => '&#8962;',   'active' => true, 'built' => true],
    ['label' => 'My Bookings',           'href' => '#ma-bookings',  'icon' => '&#128197;', 'built' => true],
    ['label' => 'Wishlist',              'href' => '#ma-wishlist',  'icon' => '&#9825;',   'built' => true],
    ['label' => 'Reviews',               'href' => '#',             'icon' => '&#9733;',   'built' => false],
    ['label' => 'Payments',              'href' => '#ma-payments',  'icon' => '&#128179;', 'built' => true],
    ['label' => 'Messages',              'href' => '#',             'icon' => '&#9993;',   'built' => false],
    ['label' => 'Profile & Account',     'href' => '#ma-profile',   'icon' => '&#128100;', 'built' => true],
    ['label' => 'Notification Settings', 'href' => '#',             'icon' => '&#128276;', 'built' => false],
    ['label' => 'Saved Searches',        'href' => '#',             'icon' => '&#128269;', 'built' => false],
    ['label' => 'Help Center',           'href' => '#ma-help',      'icon' => '&#10067;',  'built' => true],
];

$currentYear = date('Y');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account - RoomHive</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="host-dashboard.css" />
    <link rel="stylesheet" href="myaccount.css" />
  </head>
  <body class="hd-body">

    <!-- =========================
         NAVBAR (shared look with the host dashboard)
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
            </button>

            <div class="hd-account-dropdown js-account-dropdown">
                <button
                    type="button"
                    class="hd-account-toggle js-account-toggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <img src="<?php echo htmlspecialchars($avatarImage); ?>" alt="" class="hd-nav-avatar">
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </button>

                <div class="hd-account-menu">
                    <?php if ($isHost): ?>
                        <a href="hostdashboard.php">Host Dashboard</a>
                    <?php endif; ?>
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
        <img class="hd-hero-image" src="images/RenterDashboardHero.png" alt="" />
        <div class="hd-hero-overlay"></div>

        <div class="hd-hero-content">
            <p class="hd-hero-eyebrow">Welcome back,</p>
            <h1><?php echo htmlspecialchars($userName); ?>!</h1>
            <p class="hd-hero-sub">
                Manage your bookings, favorites, and account settings all in one place.
            </p>
        </div>
    </section>

    <!-- =========================
         MAIN LAYOUT
    ========================== -->
    <div class="hd-layout">

        <!-- SIDEBAR (reuses host dashboard sidebar styling) -->
        <aside class="hd-sidebar">
            <ul class="hd-sidebar-nav" id="maSidebarNav">
                <?php foreach ($sidebarLinks as $link): ?>
                    <li>
                        <?php if (!empty($link['built'])): ?>

                            <a
                                href="<?php echo htmlspecialchars($link['href']); ?>"
                                class="<?php echo !empty($link['active']) ? 'active' : ''; ?>"
                                <?php if (substr($link['href'], 0, 1) === '#' && $link['href'] !== '#'): ?>
                                    data-ma-nav-target="<?php echo htmlspecialchars(substr($link['href'], 1)); ?>"
                                <?php endif; ?>
                            >
                                <span class="hd-sidebar-icon"><?php echo $link['icon']; ?></span>
                                <?php echo htmlspecialchars($link['label']); ?>
                            </a>

                        <?php else: ?>

                            <a
                                href="javascript:void(0)"
                                class="ma-sidebar-soon"
                                aria-disabled="true"
                                title="<?php echo htmlspecialchars($link['label']); ?> - coming soon"
                            >
                                <span class="hd-sidebar-icon"><?php echo $link['icon']; ?></span>
                                <?php echo htmlspecialchars($link['label']); ?>
                                <span class="ma-soon-badge">Soon</span>
                            </a>

                        <?php endif; ?>
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

        <!-- MAIN CONTENT -->
        <main class="hd-main">

            <!-- PROFILE INFORMATION -->
            <section class="hd-card" id="ma-profile">
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
                            <span class="hd-field-value"><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="hd-field">
                            <span class="hd-field-label">Location</span>
                            <span class="hd-field-value">&#9679; <?php echo htmlspecialchars($renterProfile['location'] ?: 'Not set yet'); ?></span>
                        </div>
                    </div>

                    <div class="hd-profile-col">
                        <div class="hd-field">
                            <span class="hd-field-label">Email Address</span>
                            <span class="hd-field-value"><?php echo htmlspecialchars($userEmail ?: '—'); ?></span>
                        </div>
                        <div class="hd-field">
                            <span class="hd-field-label">Phone Number</span>
                            <span class="hd-field-value"><?php echo htmlspecialchars($renterProfile['phone'] ?: 'Not set yet'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="ma-about-me">
                    <span class="hd-field-label">About Me</span>
                    <p><?php echo htmlspecialchars($renterProfile['bio'] ?: 'Add a short bio so hosts get to know you a bit before you book.'); ?></p>
                </div>
            </section>

            <!-- STATS -->
            <section class="hd-card">
                <div class="hd-stats-grid">

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#128197;</span>
                        <span class="hd-stat-label">Bookings</span>
                        <span class="hd-stat-value"><?php echo $bookingsCount; ?></span>
                        <span class="ma-stat-sub">Total</span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#9825;</span>
                        <span class="hd-stat-label">Wishlist</span>
                        <span class="hd-stat-value"><?php echo $wishlistCount; ?></span>
                        <span class="ma-stat-sub">Properties</span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#9733;</span>
                        <span class="hd-stat-label">Average Rating</span>
                        <span class="hd-stat-value"><?php echo $averageRating !== null ? $averageRating : '—'; ?></span>
                        <span class="ma-stat-sub">From Reviews</span>
                    </div>

                    <div class="hd-stat-tile">
                        <span class="hd-stat-icon">&#8369;</span>
                        <span class="hd-stat-label">Total Spent</span>
                        <span class="hd-stat-value">&#8369; <?php echo number_format($totalSpent); ?></span>
                        <span class="ma-stat-sub">All Time</span>
                    </div>

                </div>
            </section>

            <!-- BOOKINGS + PAYMENTS SPLIT -->
            <div class="ma-content-grid">

                <!-- RECENT BOOKINGS -->
                <section class="hd-card" id="ma-bookings">
                    <div class="hd-card-header">
                        <h2>Recent Bookings</h2>
                        <a href="#" class="hd-btn-outline">View All</a>
                    </div>

                    <?php if (empty($recentBookings)): ?>

                        <div class="hd-empty-state">
                            <p>You don't have any bookings yet. Once you book a stay it'll show up here.</p>
                        </div>

                    <?php else: ?>

                        <div class="ma-booking-list">
                            <?php foreach ($recentBookings as $booking): ?>
                                <div class="ma-booking-row">

                                    <img
                                        src="<?php echo htmlspecialchars($booking['image']); ?>"
                                        alt=""
                                        class="ma-booking-thumb"
                                    >

                                    <div class="ma-booking-main">
                                        <strong><?php echo htmlspecialchars($booking['title']); ?></strong>
                                        <span><?php echo htmlspecialchars($booking['location']); ?></span>
                                        <?php if ($booking['dates'] !== ''): ?>
                                            <span class="ma-booking-dates"><?php echo htmlspecialchars($booking['dates']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <span class="hd-status-pill hd-status-<?php echo strtolower(str_replace(' ', '-', $booking['status'])); ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>

                                    <div class="ma-booking-amount">
                                        <span class="ma-metric-value">&#8369; <?php echo number_format($booking['amount']); ?></span>
                                        <span class="ma-metric-label">Total Paid</span>
                                    </div>

                                    <a
                                        href="myaccount.php?remove_booking=<?php echo urlencode($booking['id']); ?>"
                                        class="ma-remove-link"
                                        onclick="return confirm('Remove this booking?');"
                                    >&times;</a>

                                </div>
                            <?php endforeach; ?>
                        </div>

                        <a href="#" class="hd-btn-outline ma-view-all-btn">VIEW ALL BOOKINGS</a>

                    <?php endif; ?>

                    <!-- QUICK ADD BOOKING (demo stand-in for a real booking flow) -->
                    <details class="hd-quick-add">
                        <summary>+ Add a booking (demo)</summary>

                        <?php if ($bookingFormError !== ''): ?>
                            <p class="hd-form-error"><?php echo htmlspecialchars($bookingFormError); ?></p>
                        <?php endif; ?>

                        <form method="POST" action="myaccount.php" class="hd-quick-add-form">
                            <input type="hidden" name="add_booking" value="1">

                            <div class="hd-quick-add-grid ma-booking-form-grid">
                                <input type="text" name="booking_title" placeholder="Listing name" required>
                                <input type="text" name="booking_location" placeholder="City, Province" required>
                                <input type="text" name="booking_dates" placeholder="e.g. Mar 15 - Mar 18">
                                <input type="number" name="booking_amount" placeholder="Amount paid (&#8369;)" min="0">
                                <select name="booking_status">
                                    <option value="Upcoming">Upcoming</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                <input type="number" name="booking_rating" placeholder="Your rating (1-5)" min="1" max="5" step="0.1">
                            </div>

                            <button type="submit" class="hd-btn-primary">Save Booking</button>
                        </form>
                    </details>
                </section>

                <!-- RIGHT COLUMN: PAYMENTS / SECURITY -->
                <div class="ma-side-stack">

                    <section class="hd-card" id="ma-payments">
                        <div class="hd-card-header">
                            <h2>Payment Summary</h2>
                            <a href="#" class="hd-btn-outline">View All</a>
                        </div>
                        <div class="ma-payment-summary">
                            <span class="ma-payment-summary-label">Total Spent</span>
                            <span class="ma-payment-summary-value">&#8369; <?php echo number_format($totalSpent); ?></span>
                        </div>
                    </section>

                    <section class="hd-card">
                        <div class="hd-card-header">
                            <h2>Payment Methods</h2>
                        </div>

                        <?php if (empty($paymentMethods)): ?>
                            <div class="hd-empty-state">
                                <p>No cards saved yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="ma-card-list">
                                <?php foreach ($paymentMethods as $card): ?>
                                    <div class="ma-payment-card">
                                        <span class="ma-card-brand"><?php echo htmlspecialchars($card['brand']); ?></span>
                                        <span class="ma-card-number">&#8226;&#8226;&#8226;&#8226; <?php echo htmlspecialchars($card['last4']); ?></span>
                                        <span class="ma-card-expiry"><?php echo htmlspecialchars($card['expiry']); ?></span>
                                        <a
                                            href="myaccount.php?remove_payment=<?php echo urlencode($card['id']); ?>"
                                            class="ma-remove-link"
                                            onclick="return confirm('Remove this card?');"
                                        >&times;</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <details class="hd-quick-add">
                            <summary>+ Add New Card</summary>

                            <?php if ($paymentFormError !== ''): ?>
                                <p class="hd-form-error"><?php echo htmlspecialchars($paymentFormError); ?></p>
                            <?php endif; ?>

                            <form method="POST" action="myaccount.php" class="hd-quick-add-form">
                                <input type="hidden" name="add_payment" value="1">

                                <div class="ma-payment-form-grid">
                                    <select name="card_brand" required>
                                        <option value="" disabled selected>Card type</option>
                                        <option value="Visa">Visa</option>
                                        <option value="Mastercard">Mastercard</option>
                                        <option value="Amex">Amex</option>
                                    </select>
                                    <input type="text" name="card_last4" placeholder="Last 4 digits" maxlength="4" pattern="\d{4}" required>
                                    <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5" required>
                                </div>

                                <button type="submit" class="hd-btn-primary">Save Card</button>
                            </form>
                        </details>
                    </section>

                    <section class="hd-card">
                        <div class="hd-card-header">
                            <h2>Account Security</h2>
                        </div>
                        <div class="ma-security-row">
                            <div>
                                <strong>Two-Factor Authentication</strong>
                                <span class="ma-security-status <?php echo $twoFactorEnabled ? 'ma-enabled' : 'ma-disabled'; ?>">
                                    <?php echo $twoFactorEnabled ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <a href="myaccount.php?toggle_2fa=1" class="hd-btn-outline">
                                <?php echo $twoFactorEnabled ? 'Disable' : 'Manage Security'; ?>
                            </a>
                        </div>
                    </section>

                </div>

            </div>

            <!-- WISHLIST -->
            <section class="hd-card" id="ma-wishlist">
                <div class="hd-card-header">
                    <h2>Wishlist (<?php echo $wishlistCount; ?>)</h2>
                    <a href="#" class="hd-btn-outline">View All</a>
                </div>

                <?php if (empty($wishlist)): ?>

                    <div class="hd-empty-state">
                        <p>Save listings you like and they'll show up here.</p>
                    </div>

                <?php else: ?>

                    <div class="ma-wishlist-grid">
                        <?php foreach ($wishlist as $item): ?>
                            <div class="ma-wishlist-card">
                                <div class="ma-wishlist-image-wrap">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                    <a
                                        href="myaccount.php?remove_wishlist=<?php echo urlencode($item['id']); ?>"
                                        class="ma-wishlist-remove"
                                        onclick="return confirm('Remove from wishlist?');"
                                        aria-label="Remove from wishlist"
                                    >&hearts;</a>
                                </div>
                                <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                <span><?php echo htmlspecialchars($item['location']); ?></span>
                                <span class="ma-wishlist-price">&#8369; <?php echo number_format($item['price']); ?> / night</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <!-- QUICK ADD WISHLIST -->
                <details class="hd-quick-add">
                    <summary>+ Add to wishlist (demo)</summary>

                    <?php if ($wishlistFormError !== ''): ?>
                        <p class="hd-form-error"><?php echo htmlspecialchars($wishlistFormError); ?></p>
                    <?php endif; ?>

                    <form method="POST" action="myaccount.php" class="hd-quick-add-form">
                        <input type="hidden" name="add_wishlist" value="1">

                        <div class="hd-quick-add-grid">
                            <input type="text" name="wishlist_title" placeholder="Listing name" required>
                            <input type="text" name="wishlist_location" placeholder="City, Province" required>
                            <input type="number" name="wishlist_price" placeholder="Price per night (&#8369;)" min="0">
                        </div>

                        <button type="submit" class="hd-btn-primary">Add to Wishlist</button>
                    </form>
                </details>
            </section>

            <!-- NEED HELP -->
            <section class="ma-help-banner" id="ma-help">
                <img src="images/NeedHelpIllustration.png" alt="" class="ma-help-illustration">
                <div class="ma-help-text">
                    <h3>Need Help?</h3>
                    <p>Our support team is here to assist you.</p>
                </div>
                <a href="contacts.php" class="hd-btn-primary">CONTACT SUPPORT</a>
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

    <script src="javaScript.js"></script>
    <script src="myaccount-nav.js"></script>

  </body>
</html>