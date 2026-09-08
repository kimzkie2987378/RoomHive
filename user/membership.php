<?php

session_start();


// =====================================================
// ROOMHIVE - MEMBERSHIP SELECTION
// =====================================================


// =====================================================
// LOGIN STATUS
// =====================================================

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);


// =====================================================
// CURRENT YEAR
// =====================================================

$currentYear = date("Y");


// =====================================================
// NAVIGATION
// =====================================================

$navigation = [

    "HOME" => $isLoggedIn
        ? "/webprogg/user/usershome.php"
        : "/webprogg/index.php",

    "LISTINGS" => "/webprogg/Listings/listing.php",

    "HOW IT WORKS" => "/webprogg/host/howitworks.php",

    "BECOME A HOST" => $isLoggedIn
        ? "/webprogg/host/becomeahost.php"
        : "/webprogg/auth/loginform.php",

    "HIVE CLUB" => "/webprogg/hiveclub.php",

    "CONTACTS" => "/webprogg/misc/contacts.php"

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        RoomHive - Join Hive Club
    </title>


    <!-- Poppins -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- CSS -->

    <link
        rel="stylesheet"
        href="/webprogg/assets/hiveclub-section.css"
    >

</head>


<body>


<div class="membership-page-wrapper">


    <!-- BACK -->

    <a
        href="/webprogg/hiveclub.php"
        class="back-to-hive"
    >

        &larr;
        Back to Hive Club

    </a>


    <div class="membership-modal membership-page-card">


        <!-- HEADER -->

        <div class="membership-modal-header">

            <h2>
                Choose Your Membership
            </h2>

            <p>
                Select the plan that's right for you.
            </p>

        </div>


        <!-- PLANS -->

        <div class="membership-plans">


            <!-- =================================================
                 BRONZE
            ================================================== -->

            <div class="membership-plan">


                <div class="plan-icon">

                    <img
                        src="/webprogg/images/BronzeIcon-HiveClub.png"
                        alt="Bronze"
                    >

                </div>


                <h3>
                    Bronze
                </h3>


                <span class="plan-points">
                    0 - 4,999 pts
                </span>


                <ul class="plan-features">

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        5% off on stays
                    </li>

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        Member-only offers
                    </li>

                </ul>


                <div class="plan-price">
                    FREE
                </div>


                <?php if ($isLoggedIn): ?>

                    <a
                        href="/webprogg/hiveclub.php"
                        class="plan-select-button"
                    >
                        CURRENT / FREE
                    </a>

                <?php else: ?>

                    <a
                        href="/webprogg/auth/loginform.php?redirect=<?php
                            echo urlencode(
                                '/webprogg/user/membership.php'
                            );
                        ?>"
                        class="plan-select-button"
                    >
                        GET STARTED
                    </a>

                <?php endif; ?>


            </div>


            <!-- =================================================
                 GOLD
            ================================================== -->

            <div class="membership-plan popular">


                <span class="popular-badge">
                    MOST POPULAR
                </span>


                <div class="plan-icon">

                    <img
                        src="/webprogg/images/GoldIcon-HiveClub.png"
                        alt="Gold"
                    >

                </div>


                <h3 class="gold-text">
                    Gold
                </h3>


                <span class="plan-points">
                    5,000 - 24,999 pts
                </span>


                <ul class="plan-features">

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        10% off on stays
                    </li>

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        Priority customer support
                    </li>

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        Early access to promos
                    </li>

                </ul>


                <div class="plan-price">

                    <span class="peso">
                        &#8369;
                    </span>

                    399

                    <span class="per">
                        / year
                    </span>

                </div>


                <?php if ($isLoggedIn): ?>

                    <a
                        href="/webprogg/misc/completepurchase.php?plan=gold"
                        class="plan-select-button primary"
                    >
                        GET STARTED
                    </a>

                <?php else: ?>

                    <a
                        href="/webprogg/auth/loginform.php?redirect=<?php
                            echo urlencode(
                                '/webprogg/user/membership.php'
                            );
                        ?>"
                        class="plan-select-button primary"
                    >
                        GET STARTED
                    </a>

                <?php endif; ?>


            </div>


            <!-- =================================================
                 PLATINUM
            ================================================== -->

            <div class="membership-plan">


                <div class="plan-icon">

                    <img
                        src="/webprogg/images/PlatinumIcon-HiveClub.png"
                        alt="Platinum"
                    >

                </div>


                <h3>
                    Platinum
                </h3>


                <span class="plan-points">
                    25,000+ pts
                </span>


                <ul class="plan-features">

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        15% off on stays
                    </li>

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        Free upgrades
                        <small>
                            (subject to availability)
                        </small>
                    </li>

                    <li>
                        <span class="check">
                            &#10003;
                        </span>

                        VIP deals &amp; exclusive perks
                    </li>

                </ul>


                <div class="plan-price">

                    <span class="peso">
                        &#8369;
                    </span>

                    799

                    <span class="per">
                        / year
                    </span>

                </div>


                <?php if ($isLoggedIn): ?>

                    <a
                        href="/webprogg/misc/completepurchase.php?plan=platinum"
                        class="plan-select-button"
                    >
                        GET STARTED
                    </a>

                <?php else: ?>

                    <a
                        href="/webprogg/auth/loginform.php?redirect=<?php
                            echo urlencode(
                                '/webprogg/user/membership.php'
                            );
                        ?>"
                        class="plan-select-button"
                    >
                        GET STARTED
                    </a>

                <?php endif; ?>


            </div>

        </div>


        <!-- FOOTER -->

        <div class="membership-modal-footer">

            <span class="footer-crown">
                &#9819;
            </span>


            <div>

                <strong>
                    The more you book, the more you earn.
                </strong>

                <p>
                    You can upgrade or cancel anytime.
                </p>

            </div>

        </div>

    </div>

</div>


</body>
</html>