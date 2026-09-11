<?php
        /* =========================================================
        ROOMHIVE — LISTING PAYMENT (Inquiry Step 2: Payment)
        listingpayment.php

        UPDATED: this page now handles two cases —

          1) NEW INQUIRY (existing behaviour)
             /listingpayment.php?listing_id=..&checkin_date=..&...
             Charges the flat $1,000 reservation fee and, on
             success, process-payment.php creates a new booking.

          2) PAY REMAINING BALANCE (new)
             /listingpayment.php?listing_id=..&pay_balance=<booking_id>
             Looks the booking up SERVER-SIDE and charges exactly
             `total - amount_paid` for that booking — the amount is
             never trusted from the URL/query string, only ever
             computed here from the DB. On success,
             process-payment.php should add this payment to that
             booking's amount_paid instead of creating a new row.
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

        /* Small helper so we're not repeating htmlspecialchars() everywhere */
        function h($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        /* -----------------------------------------------------
        PHOTO PATH FIX
        Uploaded photo_path / avatar_path values (listing_photos.photo_path,
        other users' avatar_path) are saved relative to /webprogg — e.g.
        "listing_photos/abc.jpg" or "avatars/xyz.jpg". Printed as-is, the
        browser resolves that against the CURRENT page's folder
        (/webprogg/booking/) instead of the site root, which is why the
        listing cover photo and host avatar were 404ing on this page even
        though other pages (userprofile.php, mylistings.php,
        pendingtenants.php) already handle this. This forces every photo
        path back to an absolute, site-root path so it loads correctly
        from any page.
        ----------------------------------------------------- */
        function resolve_photo($path, $fallback) {
            if (empty($path)) {
                return $fallback;
            }
            if (preg_match('#^(https?://|/)#i', $path)) {
                return $path; // already absolute — leave it alone
            }
            return '/webprogg/' . ltrim($path, '/');
        }

        /* -----------------------------------------------------
        RESOLVE LISTING + INQUIRY DETAILS FROM STEP 1

        Field names below match listing-detail.php's #rd-inquiry-form
        exactly (checkin_date / checkout_date / guests / long_term) —
        keeping the same names end-to-end avoids the kind of silent
        param-name mismatch that was dropping dates on the floor here
        before.
        ----------------------------------------------------- */
        $listingId = isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])
            ? (int) $_GET['listing_id']
            : 0;

        /* Validate the date strings rather than trusting them as-is —
        same check used in book.php, so a hand-edited URL can't pass
        through a malformed value. */
        function payment_valid_date($value) {
            if (!is_string($value) || $value === '') {
                return null;
            }
            $d = DateTime::createFromFormat('Y-m-d', $value);
            return ($d && $d->format('Y-m-d') === $value) ? $value : null;
        }

        $checkin  = payment_valid_date($_GET['checkin_date'] ?? null) ?? '';
        $checkout = payment_valid_date($_GET['checkout_date'] ?? null) ?? '';

        $longTerm = isset($_GET['long_term']) && $_GET['long_term'] === '1';

        if ($longTerm) {
            // Long-term inquiries don't carry a checkout date.
            $checkout = '';
        }

        /* Guests comes from a <select> of '1' / '2' / '3' / '4+' — keep
        it as the same string set process-payment.php (and book.php)
        already expect, instead of forcing is_numeric() and silently
        discarding "4+". */
        $allowedGuestOptions = ['1', '2', '3', '4+'];
        $guestsInput = $_GET['guests'] ?? null;
        $guests = in_array($guestsInput, $allowedGuestOptions, true) ? $guestsInput : '1';

        /* -----------------------------------------------------
        PAY-REMAINING-BALANCE MODE
        ?pay_balance=<booking id> switches this page from "new
        inquiry, flat $1,000 fee" into "charge exactly what's left
        owed on an existing booking". The amount is ALWAYS computed
        here from the DB — never taken from the query string — so a
        hand-edited URL can't under- or over-charge.
        ----------------------------------------------------- */
        $payBalanceBookingId = isset($_GET['pay_balance']) && is_numeric($_GET['pay_balance'])
            ? (int) $_GET['pay_balance']
            : null;

        $balanceBooking = null;

        if ($payBalanceBookingId !== null) {
            $balanceStmt = $pdo->prepare(
                "SELECT id, user_id, listing_id, total, amount_paid, status,
                        checkin_date, checkout_date, guests
                 FROM bookings
                 WHERE id = :id
                 LIMIT 1"
            );
            $balanceStmt->execute(['id' => $payBalanceBookingId]);
            $balanceBooking = $balanceStmt->fetch();

            $balanceIsValid = $balanceBooking !== false
                && (int) $balanceBooking['user_id'] === (int) $_SESSION['user_id']
                && (int) $balanceBooking['listing_id'] === $listingId
                && in_array($balanceBooking['status'], ['pending', 'confirmed'], true);

            if (!$balanceIsValid) {
                // Not this user's booking, wrong listing, or nothing left
                // to pay — bounce back rather than show a bad payment page.
                header('Location: /webprogg/booking/userbookings.php');
                exit;
            }

            $remaining = round(
                (float) $balanceBooking['total'] - (float) $balanceBooking['amount_paid'],
                2
            );

            if ($remaining <= 0.005) {
                // Already paid in full — nothing to do here.
                header('Location: /webprogg/booking/booking-details.php?id=' . $payBalanceBookingId);
                exit;
            }

            // Reuse the booking's own dates/guests for display, since a
            // balance payment has no Step-1 inquiry form behind it.
            $checkin  = $balanceBooking['checkin_date']  ?? $checkin;
            $checkout = $balanceBooking['checkout_date'] ?? $checkout;
            $guests   = $balanceBooking['guests']        ?? $guests;
        }

        $listingStmt = $pdo->prepare(
            "SELECT l.id, l.title, l.location, l.exact_address, l.category, l.property_type, l.price,
                    l.user_id AS host_id,
                    l.bedrooms, l.bathrooms, l.size_sqm, l.floor, l.parking,
                    u.name AS host_name, u.avatar_path AS host_avatar, u.created_at AS host_since,
                    p.photo_path AS cover_photo
            FROM listings l
            JOIN users u ON u.id = l.user_id
            LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
            WHERE l.id = :id
            LIMIT 1"
        );
        $listingStmt->execute(['id' => $listingId]);
        $listing = $listingStmt->fetch();

        if ($listing === false) {
            header('Location: /webprogg/Listings/listing.php');
            exit;
        }

        /* -----------------------------------------------------
        LISTING RATING (from `reviews`, keyed by listing_id)
        ----------------------------------------------------- */
        $listingReviewsStmt = $pdo->prepare(
            "SELECT rating FROM reviews WHERE listing_id = :id"
        );
        $listingReviewsStmt->execute(['id' => $listingId]);
        $listingRatings = array_map('floatval', array_column($listingReviewsStmt->fetchAll(), 'rating'));

        $listing_rating_avg   = count($listingRatings) > 0 ? round(array_sum($listingRatings) / count($listingRatings), 1) : 0;
        $listing_rating_count = count($listingRatings);

        /* -----------------------------------------------------
        HOST RATING (from `reviews`, keyed by user_id — same
        pattern used on userprofile.php)
        ----------------------------------------------------- */
        $hostReviewsStmt = $pdo->prepare(
            "SELECT rating FROM reviews WHERE user_id = :id"
        );
        $hostReviewsStmt->execute(['id' => $listing['host_id']]);
        $hostRatings = array_map('floatval', array_column($hostReviewsStmt->fetchAll(), 'rating'));

        $host_rating_avg   = count($hostRatings) > 0 ? round(array_sum($hostRatings) / count($hostRatings), 1) : 0;
        $host_rating_count = count($hostRatings);

        /* SuperHost badge: no dedicated column for this yet — treat
        a strong, review-backed rating as SuperHost status. Swap
        for a real `users.is_superhost` column if one gets added. */
        $isSuperhost = $host_rating_count >= 5 && $host_rating_avg >= 4.8;

        /* -----------------------------------------------------
        BOOKING SUMMARY FIGURES
        ----------------------------------------------------- */
        $monthlyRent = (float) $listing['price'];

        if ($balanceBooking !== null) {
            /* Paying down an existing booking — charge exactly what's
               left owed, computed server-side above. */
            $totalDueToday   = $remaining;
            $bookingTotal    = (float) $balanceBooking['total'];
            $amountPaidSoFar = (float) $balanceBooking['amount_paid'];
        } else {
            /* New inquiry — flat reservation/processing fee charged
               today to lock in the inquiry. Everything else is settled
               with the host once the booking is confirmed. Not a
               DB-backed value yet; pull from a settings table/config
               if one exists in your schema. */
            $totalDueToday   = 1000.00;
            $bookingTotal    = null;
            $amountPaidSoFar = null;
        }

        /* -----------------------------------------------------
        PAYMENT METHODS OFFERED
        ----------------------------------------------------- */
        $paymentMethods = [
            [
                'id'          => 'gcash',
                'label'       => 'GCash',
                'description' => 'Pay securely with GCash',
                'icon'        => '/webprogg/images/GCashIcon.png',
                'recommended' => true,
            ],
            [
                'id'          => 'maya',
                'label'       => 'Maya',
                'description' => 'Pay with Maya',
                'icon'        => '/webprogg/images/paymayaicon.png',
                'recommended' => false,
            ],
            [
                'id'          => 'card',
                'label'       => 'Credit/Debit Card',
                'description' => 'Visa, Mastercard, JCB and more.',
                'icon'        => '/webprogg/images/paymentsicon-userprofile.png',
                'recommended' => false,
            ],
        ];

        $mapsQuery = trim($listing['exact_address'] . ', ' . $listing['location']);
        $mapsUrl   = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $balanceBooking !== null ? 'Pay Balance' : 'Payment Method'; ?> — RoomHive</title>
        <link rel="stylesheet" href="/webprogg/assets/style.css">
        <link rel="stylesheet" href="/webprogg/assets/myaccount.css">
        </head>
        <body>



        <main class="bp-page">

            <?php if ($balanceBooking !== null): ?>
                <a href="/webprogg/booking/booking-details.php?id=<?php echo h($payBalanceBookingId); ?>" class="bp-back-link">
                    &#8592; Back to Booking
                </a>
            <?php else: ?>
                <a href="/webprogg/Listings/listing-detail.php?id=<?php echo h($listingId); ?>" class="bp-back-link">
                    &#8592; Back to Listing
                </a>
            <?php endif; ?>

            <?php if ($balanceBooking === null): ?>
            <!-- STEP TRACKER (new-inquiry flow only) -->
            <div class="bp-steps">
                <div class="bp-step bp-step-done">
                    <span class="bp-step-circle">1</span>
                    <span class="bp-step-label">Details</span>
                </div>
                <div class="bp-step-line bp-step-line-done"></div>
                <div class="bp-step bp-step-active">
                    <span class="bp-step-circle">2</span>
                    <span class="bp-step-label">Payment</span>
                </div>
                <div class="bp-step-line"></div>
                <div class="bp-step">
                    <span class="bp-step-circle">3</span>
                    <span class="bp-step-label">Confirmation</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="bp-layout">

                <!-- MAIN: PAYMENT METHOD SELECTION -->
                <div class="bp-main">

                    <div class="bp-card">

                        <?php if ($balanceBooking !== null): ?>
                            <h1>Pay Remaining Balance</h1>
                            <p class="bp-subtext">
                                Settle the remaining &#8369;<?php echo h(number_format($totalDueToday, 2)); ?>
                                owed on this booking.
                            </p>
                        <?php else: ?>
                            <h1>Payment Method</h1>
                            <p class="bp-subtext">Choose your preferred payment method to complete your inquiry.</p>
                        <?php endif; ?>

                        <div class="bp-secure-banner">
                            <img src="/webprogg/images/LockIcon.png" alt="">
                            <div>
                                <strong>Your payment is secure and encrypted</strong>
                                <p>We use trusted payment providers to keep your information safe.</p>
                            </div>
                        </div>

                        <form id="bp-payment-form" method="POST" action="/webprogg/booking/process-payment.php">

                            <input type="hidden" name="listing_id" value="<?php echo h($listingId); ?>">
                            <input type="hidden" name="checkin_date" value="<?php echo h($checkin); ?>">
                            <input type="hidden" name="checkout_date" value="<?php echo h($checkout); ?>">
                            <input type="hidden" name="guests" value="<?php echo h($guests); ?>">
                            <input type="hidden" name="long_term" value="<?php echo $longTerm ? '1' : '0'; ?>">

                            <?php if ($balanceBooking !== null): ?>
                                <!-- Tells process-payment.php to top up amount_paid on this
                                     existing booking instead of creating a new one. The
                                     amount itself is re-derived server-side there too —
                                     never trust a client-supplied total. -->
                                <input type="hidden" name="booking_id" value="<?php echo h($payBalanceBookingId); ?>">
                                <input type="hidden" name="payment_purpose" value="balance">
                            <?php else: ?>
                                <input type="hidden" name="payment_purpose" value="reservation">
                            <?php endif; ?>

                            <div class="bp-methods">
                                <?php foreach ($paymentMethods as $i => $method): ?>
                                    <label class="bp-method <?php echo $i === 0 ? 'bp-method-selected' : ''; ?>">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="<?php echo h($method['id']); ?>"
                                            <?php echo $i === 0 ? 'checked' : ''; ?>
                                        >
                                        <span class="bp-method-icon">
                                            <img src="<?php echo h($method['icon']); ?>" alt="<?php echo h($method['label']); ?>">
                                        </span>
                                        <span class="bp-method-text">
                                            <span class="bp-method-name">
                                                <?php echo h($method['label']); ?>
                                                <?php if ($method['recommended']): ?>
                                                    <span class="bp-badge-recommended">Recommended</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="bp-method-desc"><?php echo h($method['description']); ?></span>
                                        </span>
                                        <span class="bp-method-radio"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($balanceBooking === null): ?>
                            <div class="bp-howitworks">
                                <div class="bp-howitworks-title">
                                    <span class="bp-howitworks-icon">&#8505;</span>
                                    How it works
                                </div>
                                <div class="bp-howitworks-steps">
                                    <div class="bp-hiw-step">
                                        <span class="bp-hiw-num">1</span>
                                        <strong>Send Inquiry</strong>
                                        <p>You'll be redirected to the payment page.</p>
                                    </div>
                                    <span class="bp-hiw-arrow">&#8594;</span>
                                    <div class="bp-hiw-step">
                                        <span class="bp-hiw-num">2</span>
                                        <strong>Make Payment</strong>
                                        <p>Complete your payment using your selected method.</p>
                                    </div>
                                    <span class="bp-hiw-arrow">&#8594;</span>
                                    <div class="bp-hiw-step">
                                        <span class="bp-hiw-num">3</span>
                                        <strong>Confirm Booking</strong>
                                        <p>Your booking will be confirmed once payment is verified.</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="bp-btn-pay">
                                Pay Now &#8369; <?php echo h(number_format($totalDueToday, 0)); ?>
                            </button>

                            <?php if ($balanceBooking !== null): ?>
                                <a
                                    href="/webprogg/booking/booking-details.php?id=<?php echo h($payBalanceBookingId); ?>"
                                    class="bp-back-inquiry"
                                >
                                    &#8592; Back to Booking
                                </a>
                            <?php else: ?>
                                <a
                                    href="/webprogg/Listings/listing-detail.php?id=<?php echo h($listingId); ?>"
                                    class="bp-back-inquiry"
                                >
                                    &#8592; Back to Inquiry
                                </a>
                            <?php endif; ?>

                        </form>

                    </div>

                </div>

                <!-- SIDEBAR: LISTING / BOOKING SUMMARY / HOST / LOCATION -->
                <aside class="bp-sidebar">

                    <div class="bp-card">

                        <img
                            class="bp-listing-photo"
                            src="<?php echo h(resolve_photo($listing['cover_photo'], '/webprogg/images/ListingPlaceholder.png')); ?>"
                            alt="<?php echo h($listing['title']); ?>"
                        >

                        <h3 class="bp-listing-title"><?php echo h($listing['title']); ?></h3>
                        <p class="bp-listing-location">
                            <img src="/webprogg/images/GPSIcon.png" alt="">
                            <?php echo h($listing['location']); ?>
                        </p>
                        <p class="bp-listing-rating">
                            &#9733; <?php echo h($listing_rating_avg); ?>
                            <span>(<?php echo h($listing_rating_count); ?> reviews)</span>
                        </p>

                        <?php if ($checkin): ?>
                            <div class="bp-specs">
                                <div class="bp-spec-row">
                                    <span>Check-in</span>
                                    <strong><?php echo h($checkin); ?></strong>
                                </div>
                                <?php if ($checkout): ?>
                                    <div class="bp-spec-row">
                                        <span>Check-out</span>
                                        <strong><?php echo h($checkout); ?></strong>
                                    </div>
                                <?php elseif ($longTerm): ?>
                                    <div class="bp-spec-row">
                                        <span>Duration</span>
                                        <strong>Long Term</strong>
                                    </div>
                                <?php endif; ?>
                                <div class="bp-spec-row">
                                    <span>Guests</span>
                                    <strong><?php echo h($guests); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="bp-specs">
                            <div class="bp-spec-row">
                                <span>Property Type</span>
                                <strong><?php echo h($listing['property_type'] ?: 'Not specified'); ?></strong>
                            </div>
                            <div class="bp-spec-row">
                                <span>Bedrooms</span>
                                <strong><?php echo h($listing['bedrooms'] ?? 'Not specified'); ?></strong>
                            </div>
                            <div class="bp-spec-row">
                                <span>Bathrooms</span>
                                <strong><?php echo h($listing['bathrooms'] ?? 'Not specified'); ?></strong>
                            </div>
                            <div class="bp-spec-row">
                                <span>Size</span>
                                <strong><?php echo isset($listing['size_sqm']) ? h($listing['size_sqm']) . ' m&sup2;' : 'Not specified'; ?></strong>
                            </div>
                            <div class="bp-spec-row">
                                <span>Floor</span>
                                <strong><?php echo h($listing['floor'] ?? 'Not specified'); ?></strong>
                            </div>
                            <div class="bp-spec-row">
                                <span>Parking</span>
                                <strong><?php echo h($listing['parking'] ?: 'Not specified'); ?></strong>
                            </div>
                        </div>

                        <div class="bp-booking-summary">
                            <h4><?php echo $balanceBooking !== null ? 'Payment Summary' : 'Booking Summary'; ?></h4>

                            <?php if ($balanceBooking !== null): ?>
                                <div class="bp-summary-row">
                                    <span>Booking Total</span>
                                    <strong>&#8369; <?php echo h(number_format($bookingTotal, 2)); ?></strong>
                                </div>
                                <div class="bp-summary-row">
                                    <span>Already Paid</span>
                                    <strong>&#8369; <?php echo h(number_format($amountPaidSoFar, 2)); ?></strong>
                                </div>
                                <div class="bp-summary-row bp-summary-row-total">
                                    <span>Due Today</span>
                                    <strong>&#8369; <?php echo h(number_format($totalDueToday, 2)); ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="bp-summary-row">
                                    <span>Monthly Rent</span>
                                    <strong>&#8369; <?php echo h(number_format($monthlyRent, 0)); ?></strong>
                                </div>
                                <div class="bp-summary-row bp-summary-row-total">
                                    <span>Total Due Today</span>
                                    <strong>&#8369; <?php echo h(number_format($totalDueToday, 0)); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- HOST CARD -->
                    <div class="bp-card bp-host-card">
                        <h3>Host</h3>
                        <div class="bp-host-row">
                            <img
                                class="bp-host-avatar"
                                src="<?php echo h(resolve_photo($listing['host_avatar'], '/webprogg/images/default-avatar.png')); ?>"
                                alt="<?php echo h($listing['host_name']); ?>"
                            >
                            <div>
                                <p class="bp-host-name">
                                    <?php echo h($listing['host_name']); ?>
                                    <?php if ($isSuperhost): ?>
                                        <span class="bp-badge-superhost">SuperHost</span>
                                    <?php endif; ?>
                                </p>
                                <p class="bp-host-since">
                                    Member since <?php echo h(date('F Y', strtotime($listing['host_since']))); ?>
                                </p>
                                <p class="bp-host-rating">
                                    &#9733; <?php echo h($host_rating_avg); ?>
                                    <span>(<?php echo h($host_rating_count); ?> reviews)</span>
                                </p>
                            </div>
                        </div>
                        <p class="bp-host-response">Usually responds within a few hours</p>
                    </div>

                    <!-- LOCATION CARD -->
                    <div class="bp-card bp-location-card">
                        <h3>Location</h3>
                        <p class="bp-location-address">
                            <?php echo h($listing['location']); ?>
                        </p>
                        <a
                            href="<?php echo h($mapsUrl); ?>"
                            target="_blank"
                            rel="noopener"
                            class="bp-map-thumb"
                            aria-label="View on Google Maps"
                        >
                            <img src="/webprogg/images/MapPlaceholder.png" alt="Map preview">
                            <span class="bp-map-pin">&#128205;</span>
                        </a>
                        <a href="<?php echo h($mapsUrl); ?>" target="_blank" rel="noopener" class="bp-btn-outline">
                            View on Google Maps
                        </a>
                    </div>

                </aside>

            </div>

        </main>



        <script src="/webprogg/assets/javaScript.js"></script>

        <!-- =========================================================
            PAYMENT METHOD — SELECTION HIGHLIGHT
        ========================================================= -->
        <script>
        (function () {
            const methods = document.querySelectorAll('.bp-method');

            methods.forEach(function (label) {
                const input = label.querySelector('input[type="radio"]');
                if (!input) return;

                input.addEventListener('change', function () {
                    methods.forEach(function (l) { l.classList.remove('bp-method-selected'); });
                    if (input.checked) label.classList.add('bp-method-selected');
                });
            });
        })();
        </script>

        <!-- =========================================================
            PAY NOW — SUBMIT + LOADING STATE
        ========================================================= -->
        <script>
        (function () {
            const form = document.getElementById('bp-payment-form');
            if (!form) return;

            form.addEventListener('submit', function () {
                const btn = form.querySelector('.bp-btn-pay');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Redirecting to payment...';
                }
            });
        })();
        </script>

        <!-- =========================================================
            BOOKING PAYMENT — STYLES
        ========================================================= -->
        <style>
            .bp-page { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }

            .bp-back-link {
                display: inline-block;
                margin-bottom: 16px;
                color: #14142B;
                text-decoration: none;
                font-weight: 600;
            }
            .bp-back-link:hover { text-decoration: underline; }

            /* STEP TRACKER */
            .bp-steps {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-bottom: 28px;
            }
            .bp-step { display: flex; flex-direction: column; align-items: center; gap: 6px; }
            .bp-step-circle {
                width: 28px; height: 28px;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                background: #EEF1F6;
                color: #999;
                font-weight: 700;
                font-size: 13px;
            }
            .bp-step-label { font-size: 12px; color: #999; font-weight: 600; }
            .bp-step-done .bp-step-circle { background: #FFA726; color: #fff; }
            .bp-step-done .bp-step-label { color: #14142B; }
            .bp-step-active .bp-step-circle { background: #FFA726; color: #fff; }
            .bp-step-active .bp-step-label { color: #14142B; }
            .bp-step-line { width: 60px; height: 2px; background: #EEF1F6; }
            .bp-step-line-done { background: #FFA726; }

            .bp-layout { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
            .bp-main { flex: 2; min-width: 300px; }
            .bp-sidebar { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 16px; }

            .bp-card {
                background: #fff;
                border: 1px solid #EEF1F6;
                border-radius: 14px;
                padding: 20px;
            }

            .bp-main h1 { margin: 0 0 4px; font-size: 20px; color: #14142B; }
            .bp-subtext { margin: 0 0 18px; font-size: 13px; color: #777; }

            .bp-secure-banner {
                display: flex;
                gap: 10px;
                align-items: flex-start;
                background: #FFF6E9;
                border: 1px solid #FFE0B2;
                border-radius: 10px;
                padding: 12px 14px;
                margin-bottom: 20px;
                font-size: 13px;
            }
            .bp-secure-banner img { width: 18px; height: 18px; margin-top: 2px; }
            .bp-secure-banner strong { display: block; color: #14142B; font-size: 13px; }
            .bp-secure-banner p { margin: 2px 0 0; color: #8A5A10; font-size: 12px; }

            .bp-methods { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
            .bp-method {
                display: flex;
                align-items: center;
                gap: 12px;
                border: 1px solid #EEF1F6;
                border-radius: 10px;
                padding: 14px;
                cursor: pointer;
                position: relative;
            }
            .bp-method input { position: absolute; opacity: 0; pointer-events: none; }
            .bp-method-selected { border-color: #FFA726; background: #FFF9F0; }
            .bp-method-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
            .bp-method-icon img { max-width: 100%; max-height: 100%; }
            .bp-method-text { flex: 1; display: flex; flex-direction: column; gap: 2px; }
            .bp-method-name { font-weight: 700; color: #14142B; font-size: 14px; display: flex; align-items: center; gap: 8px; }
            .bp-method-desc { font-size: 12px; color: #777; }
            .bp-badge-recommended {
                font-size: 10px; font-weight: 700;
                background: #E6F6EC; color: #1E7A3D;
                padding: 2px 8px; border-radius: 999px;
            }
            .bp-method-radio {
                width: 18px; height: 18px;
                border-radius: 50%;
                border: 2px solid #D8D8D8;
                flex-shrink: 0;
            }
            .bp-method-selected .bp-method-radio {
                border-color: #FFA726;
                background: radial-gradient(#FFA726 0 40%, transparent 44%);
            }

            .bp-howitworks {
                background: #F6F7FB;
                border-radius: 10px;
                padding: 16px;
                margin-bottom: 22px;
            }
            .bp-howitworks-title {
                display: flex; align-items: center; gap: 6px;
                font-weight: 700; color: #14142B; font-size: 13px; margin-bottom: 12px;
            }
            .bp-howitworks-icon {
                width: 18px; height: 18px;
                border-radius: 50%;
                background: #14142B; color: #fff;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: 11px;
            }
            .bp-howitworks-steps { display: flex; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
            .bp-hiw-step { flex: 1; min-width: 140px; }
            .bp-hiw-num {
                width: 22px; height: 22px;
                border-radius: 50%;
                background: #fff; border: 1px solid #D8D8D8;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: 11px; font-weight: 700; color: #14142B;
                margin-bottom: 6px;
            }
            .bp-hiw-step strong { display: block; font-size: 12.5px; color: #14142B; margin-bottom: 2px; }
            .bp-hiw-step p { margin: 0; font-size: 11.5px; color: #777; }
            .bp-hiw-arrow { color: #C9C9C9; font-size: 16px; padding-top: 2px; }

            .bp-btn-pay {
                display: block;
                width: 100%;
                padding: 14px;
                border-radius: 10px;
                border: none;
                background: #FFA726;
                color: #fff;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                margin-bottom: 12px;
            }
            .bp-btn-pay:hover { background: #FB983F; }
            .bp-btn-pay:disabled { opacity: 0.7; cursor: default; }

            .bp-back-inquiry {
                display: block;
                text-align: center;
                color: #777;
                font-size: 13px;
                text-decoration: none;
                font-weight: 600;
            }
            .bp-back-inquiry:hover { text-decoration: underline; }

            /* SIDEBAR: LISTING SUMMARY */
            .bp-listing-photo { width: 100%; height: 150px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; }
            .bp-listing-title { margin: 0 0 4px; font-size: 15px; color: #14142B; }
            .bp-listing-location {
                display: flex; align-items: center; gap: 4px;
                margin: 0 0 6px; font-size: 12px; color: #777;
            }
            .bp-listing-location img { width: 12px; height: 12px; }
            .bp-listing-rating { margin: 0 0 14px; font-size: 12.5px; color: #14142B; }
            .bp-listing-rating span { color: #777; font-weight: 400; }

            .bp-specs {
                border-top: 1px solid #EEF1F6;
                border-bottom: 1px solid #EEF1F6;
                padding: 12px 0;
                margin-bottom: 14px;
                display: flex; flex-direction: column; gap: 8px;
            }
            .bp-spec-row { display: flex; justify-content: space-between; font-size: 12.5px; }
            .bp-spec-row span { color: #777; }
            .bp-spec-row strong { color: #14142B; }

            .bp-booking-summary h4 { margin: 0 0 10px; font-size: 13px; color: #14142B; }
            .bp-summary-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; color: #555; }
            .bp-summary-row-total {
                padding-top: 8px;
                border-top: 1px solid #EEF1F6;
                color: #14142B;
                font-weight: 700;
            }

            /* HOST CARD */
            .bp-host-card h3 { margin: 0 0 12px; font-size: 15px; color: #14142B; }
            .bp-host-row { display: flex; gap: 10px; margin-bottom: 10px; }
            .bp-host-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
            .bp-host-name { margin: 0; font-weight: 700; color: #14142B; font-size: 13.5px; display: flex; align-items: center; gap: 6px; }
            .bp-badge-superhost {
                font-size: 9.5px; font-weight: 700;
                background: #E8F0FE; color: #1A56DB;
                padding: 2px 7px; border-radius: 999px;
            }
            .bp-host-since { margin: 2px 0 0; font-size: 11.5px; color: #777; }
            .bp-host-rating { margin: 2px 0 0; font-size: 11.5px; color: #14142B; }
            .bp-host-rating span { color: #777; font-weight: 400; }
            .bp-host-response { margin: 0; font-size: 11.5px; color: #777; }

            /* LOCATION CARD */
            .bp-location-card h3 { margin: 0 0 10px; font-size: 15px; color: #14142B; }
            .bp-location-address { margin: 0 0 10px; font-size: 12.5px; color: #777; }
            .bp-map-thumb {
                position: relative;
                display: block;
                border-radius: 10px;
                overflow: hidden;
                margin-bottom: 10px;
                height: 110px;
                background: #EAF2FB;
            }
            .bp-map-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .bp-map-pin {
                position: absolute; top: 50%; left: 50%;
                transform: translate(-50%, -60%);
                font-size: 22px;
            }

            .bp-btn-outline {
                display: block;
                width: 100%;
                padding: 10px 16px;
                border-radius: 10px;
                border: 1px solid #EEF1F6;
                background: #fff;
                color: #14142B;
                font-size: 13px;
                font-weight: 700;
                text-align: center;
                text-decoration: none;
                box-sizing: border-box;
            }
            .bp-btn-outline:hover { background: #F6F7FB; }

            .bp-listspace-btn {
                background: #FFA726 !important;
                color: #fff !important;
                padding: 8px 16px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 700;
            }
        </style>

        </body>
        </html>