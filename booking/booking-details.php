<?php
/* =========================================================
   ROOMHIVE — BOOKING DETAILS
   booking-details.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

$isLoggedIn = isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true;
$navAvatar  = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';
$notification_count = 0;

/* -----------------------------------------------------
   RESOLVE BOOKING FROM ?id=
----------------------------------------------------- */
$bookingId = isset($_GET['id']) && is_numeric($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$bookingStmt = $pdo->prepare(
    "SELECT b.*,
            l.id AS listing_id, l.title AS listing_title, l.location AS listing_location,
            l.exact_address, l.category, l.price AS listing_price,
            u.name AS host_name, u.email AS host_email,
            p.photo_path AS cover_photo
     FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     JOIN users u ON u.id = l.user_id
     LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
     WHERE b.id = :id
     LIMIT 1"
);
$bookingStmt->execute(['id' => $bookingId]);
$booking = $bookingStmt->fetch();

if ($booking === false) {
    header('Location: /webprogg/booking/userbookings.php');
    exit;
}

/* Only the tenant who made this booking may view it. */
if ((int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
    header('Location: /webprogg/booking/userbookings.php');
    exit;
}

/* Small helper so we're not repeating htmlspecialchars() everywhere */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bd_status_label($status) {
    switch ($status) {
        case 'confirmed': return 'Accepted';
        case 'pending':   return 'Pending';
        case 'rejected':  return 'Rejected';
        case 'cancelled': return 'Cancelled';
        default:          return ucfirst($status);
    }
}

function bd_status_class($status) {
    switch ($status) {
        case 'confirmed': return 'bd-status-confirmed';
        case 'pending':   return 'bd-status-pending';
        case 'rejected':  return 'bd-status-rejected';
        case 'cancelled': return 'bd-status-cancelled';
        default:          return 'bd-status-pending';
    }
}

$canCancel = in_array($booking['status'], ['pending', 'confirmed'], true);

$checkinDate  = !empty($booking['checkin_date']) ? date('M j, Y', strtotime($booking['checkin_date'])) : null;
$checkoutDate = !empty($booking['checkout_date']) ? date('M j, Y', strtotime($booking['checkout_date'])) : null;
$guests       = $booking['guests'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Details — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
</head>
<body>


</header>

<main class="bd-page">

    <a href="/webprogg/booking/userbookings.php" class="bd-back-link">&#8592; Back to My Bookings</a>

    <div class="bd-layout">

        <div class="bd-main">

            <div class="bd-card">

                <div class="bd-header-row">
                    <h1>Booking Details</h1>
                    <span class="bd-status <?php echo bd_status_class($booking['status']); ?>">
                        <?php echo h(bd_status_label($booking['status'])); ?>
                    </span>
                </div>

                <div class="bd-listing-row">
                    <img
                        class="bd-listing-photo"
                        src="<?php echo h($booking['cover_photo'] ?: '/webprogg/images/ListingPlaceholder.png'); ?>"
                        alt="<?php echo h($booking['listing_title']); ?>"
                    >
                    <div>
                        <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($booking['listing_id']); ?>" class="bd-listing-title">
                            <?php echo h($booking['listing_title']); ?>
                        </a>
                        <p class="bd-listing-location">
                            <img src="/webprogg/images/GPSIcon.png" alt="">
                            <?php echo h($booking['exact_address'] . ', ' . $booking['listing_location']); ?>
                        </p>
                        <p class="bd-listing-price">
                            &#8369; <?php echo h(number_format((float) $booking['listing_price'])); ?> / month
                        </p>
                    </div>
                </div>

                <div class="bd-details-grid">

                    <div class="bd-detail">
                        <span class="bd-muted">Check-in</span>
                        <strong><?php echo $checkinDate ? h($checkinDate) : 'Not specified'; ?></strong>
                    </div>

                    <div class="bd-detail">
                        <span class="bd-muted">Check-out</span>
                        <strong><?php echo $checkoutDate ? h($checkoutDate) : 'Not specified'; ?></strong>
                    </div>

                    <div class="bd-detail">
                        <span class="bd-muted">Guests</span>
                        <strong><?php echo $guests ? h($guests) : 'Not specified'; ?></strong>
                    </div>

                    <div class="bd-detail">
                        <span class="bd-muted">Applied On</span>
                        <strong><?php echo h(date('M j, Y', strtotime($booking['booked_at']))); ?></strong>
                    </div>

                </div>

                <div class="bd-total-row">
                    <span class="bd-muted">Total</span>
                    <strong>&#8369; <?php echo h(number_format((float) $booking['total'], 2)); ?></strong>
                </div>

                <?php if ($booking['status'] === 'rejected'): ?>
                    <p class="bd-notice bd-notice-rejected">
                        The host did not accept this application. You're free to apply for another space.
                    </p>
                <?php elseif ($booking['status'] === 'cancelled'): ?>
                    <p class="bd-notice">
                        This booking was cancelled.
                    </p>
                <?php elseif ($booking['status'] === 'pending'): ?>
                    <p class="bd-notice bd-notice-pending">
                        Waiting on the host to accept or reject this application.
                    </p>
                <?php elseif ($booking['status'] === 'confirmed'): ?>
                    <p class="bd-notice bd-notice-confirmed">
                        The host has accepted this application.
                    </p>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                    <button
                        type="button"
                        id="bd-cancel-btn"
                        class="bd-btn bd-btn-cancel"
                        data-booking-id="<?php echo h($booking['id']); ?>"
                    >
                        Cancel Booking
                    </button>
                <?php endif; ?>

            </div>

        </div>

        <!-- HOST CARD -->
        <aside class="bd-sidebar">

            <div class="bd-card bd-host-card">
                <h3>Host</h3>
                <p class="bd-host-name"><?php echo h($booking['host_name']); ?></p>
                <p class="bd-host-email"><?php echo h($booking['host_email']); ?></p>
                <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($booking['listing_id']); ?>" class="bd-btn bd-btn-outline">
                    View Listing
                </a>
            </div>

        </aside>

    </div>

</main>


<script src="/webprogg/assets/javaScript.js"></script>

<!-- =========================================================
     BOOKING DETAILS — STYLES + CANCEL BOOKING
========================================================= -->
<style>
    .bd-page { max-width: 900px; margin: 0 auto; padding: 24px 20px 60px; }

    .bd-back-link {
        display: inline-block;
        margin-bottom: 16px;
        color: #14142B;
        text-decoration: none;
        font-weight: 600;
    }
    .bd-back-link:hover { text-decoration: underline; }

    .bd-layout { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }

    .bd-main { flex: 2; min-width: 280px; }
    .bd-sidebar { flex: 1; min-width: 220px; }

    .bd-card {
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 14px;
        padding: 20px;
    }

    .bd-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 16px;
    }
    .bd-header-row h1 { margin: 0; font-size: 20px; color: #14142B; }

    .bd-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .bd-status-pending   { background: #FFF4E0; color: #8A5A10; }
    .bd-status-confirmed { background: #E6F6EC; color: #1E7A3D; }
    .bd-status-rejected  { background: #FDECEC; color: #A3282E; }
    .bd-status-cancelled { background: #F0F0F0; color: #666666; }

    .bd-listing-row {
        display: flex;
        gap: 14px;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid #EEF1F6;
    }
    .bd-listing-photo { width: 96px; height: 96px; object-fit: cover; border-radius: 10px; }
    .bd-listing-title { font-weight: 700; color: #14142B; text-decoration: none; font-size: 15px; }
    .bd-listing-title:hover { text-decoration: underline; }
    .bd-listing-location {
        display: flex; align-items: center; gap: 4px;
        margin: 4px 0; font-size: 12px; color: #777777;
    }
    .bd-listing-location img { width: 12px; height: 12px; }
    .bd-listing-price { margin: 0; font-weight: 600; color: #14142B; }

    .bd-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-bottom: 16px;
    }
    .bd-detail { display: flex; flex-direction: column; gap: 2px; }
    .bd-muted { font-size: 12px; color: #777777; }

    .bd-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 14px;
        border-top: 1px solid #EEF1F6;
        margin-bottom: 16px;
    }

    .bd-notice {
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 13px;
        margin: 0 0 16px;
        background: #F6F7FB;
        color: #555;
    }
    .bd-notice-pending   { background: #FFF4E0; color: #8A5A10; }
    .bd-notice-confirmed { background: #E6F6EC; color: #1E7A3D; }
    .bd-notice-rejected  { background: #FDECEC; color: #A3282E; }

    .bd-btn {
        display: inline-block;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        border: 1px solid transparent;
    }
    .bd-btn-cancel { background: #FDECEC; color: #A3282E; border-color: #E14B4B; width: 100%; }
    .bd-btn-cancel:hover { background: #FCDADA; }
    .bd-btn-outline { background: #fff; color: #14142B; border-color: #EEF1F6; width: 100%; margin-top: 10px; }
    .bd-btn-outline:hover { background: #F6F7FB; }

    .bd-host-card h3 { margin: 0 0 10px; font-size: 15px; color: #14142B; }
    .bd-host-name { margin: 0; font-weight: 600; color: #14142B; }
    .bd-host-email { margin: 2px 0 0; font-size: 12px; color: #777777; }
</style>
<script>
(function () {
    const cancelBtn = document.getElementById('bd-cancel-btn');

    if (!cancelBtn) {
        return;
    }

    cancelBtn.addEventListener('click', function () {

        if (!confirm('Cancel this booking? This cannot be undone.')) {
            return;
        }

        const bookingId = cancelBtn.getAttribute('data-booking-id');

        cancelBtn.disabled = true;
        cancelBtn.textContent = 'Cancelling...';

        fetch('/webprogg/booking/cancel-booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + encodeURIComponent(bookingId)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not cancel this booking.');
                    cancelBtn.disabled = false;
                    cancelBtn.textContent = 'Cancel Booking';
                }
            })
            .catch(function () {
                alert('Something went wrong. Please try again.');
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Cancel Booking';
            });
    });
})();
</script>

</body>
</html>