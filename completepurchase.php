<?php

session_start();


// =====================================================
// ROOMHIVE - COMPLETE YOUR PURCHASE
// =====================================================


// =====================================================
// REQUIRE LOGIN
// =====================================================

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);


if (!$isLoggedIn) {

    header(
        "Location: loginform.php?redirect=" .
        urlencode("membership.php")
    );

    exit;

}


// =====================================================
// GET USER ID
// =====================================================

$userId = $_SESSION["user_id"] ?? null;

if (!$userId && isset($_SESSION["id"])) {
    $userId = $_SESSION["id"];
}


if (!$userId) {

    header(
        "Location: loginform.php?redirect=" .
        urlencode("membership.php")
    );

    exit;

}


// =====================================================
// MEMBERSHIP PLANS
// =====================================================

$plans = [

    "gold" => [

        "label" => "Gold Membership",

        "colorClass" => "gold",

        "icon" =>
            "images/GoldIcon-HiveClub.png",

        "price" => 399,

        "period" => "year",

        "features" => [

            "10% off on stays",

            "Priority customer support",

            "Early access to promos"

        ]

    ],


    "platinum" => [

        "label" => "Platinum Membership",

        "colorClass" => "platinum",

        "icon" =>
            "images/PlatinumIcon-HiveClub.png",

        "price" => 799,

        "period" => "year",

        "features" => [

            "15% off on stays",

            "Free upgrades (subject to availability)",

            "VIP deals & exclusive perks"

        ]

    ]

];


// =====================================================
// SELECT PLAN
// =====================================================

$planKey = (

    isset($_GET["plan"]) &&

    array_key_exists(
        $_GET["plan"],
        $plans
    )

)

    ? $_GET["plan"]

    : "gold";


$plan = $plans[$planKey];

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
        RoomHive - Complete Your Purchase
    </title>


    <!-- Poppins -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- CSS -->

    <link
        rel="stylesheet"
        href="hiveclub-section.css"
    >

</head>


<body>


<div class="checkout-page-wrapper">


    <div class="checkout-card">


        <!-- CLOSE -->

        <a
            href="membership.php"
            class="checkout-close"
            aria-label="Close"
        >
            &times;
        </a>


        <!-- HEADER -->

        <div class="checkout-header">

            <h2>
                Complete Your Purchase
            </h2>

            <p>
                You're one step away from amazing perks!
            </p>

        </div>


        <div class="checkout-grid">


            <!-- =================================================
                 SELECTED PLAN
            ================================================== -->

            <div class="checkout-panel you-selected-panel">


                <h3 class="checkout-panel-title">
                    You Selected
                </h3>


                <div class="selected-plan-box">


                    <div
                        class="selected-plan-icon
                        <?php echo $plan["colorClass"]; ?>"
                    >

                        <img
                            src="<?php echo htmlspecialchars(
                                $plan["icon"]
                            ); ?>"
                            alt="<?php echo htmlspecialchars(
                                $plan["label"]
                            ); ?>"
                        >

                    </div>


                    <div class="selected-plan-info">

                        <h4
                            class="<?php echo
                                $plan["colorClass"];
                            ?>-text"
                        >

                            <?php echo htmlspecialchars(
                                $plan["label"]
                            ); ?>

                        </h4>


                        <span
                            class="selected-plan-price"
                        >

                            <span class="peso">
                                &#8369;
                            </span>

                            <?php echo number_format(
                                $plan["price"],
                                2
                            ); ?>

                            /

                            <?php echo $plan["period"]; ?>

                        </span>

                    </div>

                </div>


                <ul class="selected-features">

                    <?php foreach (
                        $plan["features"]
                        as $feature
                    ): ?>

                        <li>

                            <span class="check">
                                &#10003;
                            </span>

                            <?php echo htmlspecialchars(
                                $feature
                            ); ?>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>


            <!-- =================================================
                 PAYMENT METHOD
            ================================================== -->

            <div
                class="checkout-panel payment-panel"
            >

                <h3 class="checkout-panel-title">
                    Payment Method
                </h3>


                <form
                    id="paymentForm"
                    action="paymentsuccess.php"
                    method="POST"
                >


                    <!-- PLAN -->

                    <input
                        type="hidden"
                        name="plan"
                        value="<?php echo htmlspecialchars(
                            $planKey
                        ); ?>"
                    >


                    <!-- CARD -->

                    <label
                        class="payment-option selected"
                    >

                        <span class="payment-radio">

                            <input
                                type="radio"
                                name="payment_method"
                                value="card"
                                checked
                                required
                            >

                            <span class="radio-dot"></span>

                        </span>


                        <span
                            class="payment-option-body"
                        >

                            <span
                                class="payment-option-label"
                            >
                                Credit / Debit Card
                            </span>


                            <span
                                class="payment-logos"
                            >

                                <img
                                    src="images/visalogo.png"
                                    alt="Visa"
                                >

                                <img
                                    src="images/mastercardlogo.png"
                                    alt="Mastercard"
                                >

                                <img
                                    src="images/amexlogo.png"
                                    alt="Amex"
                                >

                            </span>

                        </span>

                    </label>


                    <!-- GRABPAY -->

                    <label
                        class="payment-option"
                    >

                        <span class="payment-radio">

                            <input
                                type="radio"
                                name="payment_method"
                                value="grabpay"
                            >

                            <span class="radio-dot"></span>

                        </span>


                        <span
                            class="payment-option-body"
                        >

                            <img
                                class="payment-wordmark"
                                src="images/wholenamegrabpaylogo.png"
                                alt="GrabPay"
                            >

                        </span>

                    </label>


                    <!-- GCASH -->

                    <label
                        class="payment-option"
                    >

                        <span class="payment-radio">

                            <input
                                type="radio"
                                name="payment_method"
                                value="gcash"
                            >

                            <span class="radio-dot"></span>

                        </span>


                        <span
                            class="payment-option-body"
                        >

                            <img
                                class="payment-icon-sm"
                                src="images/GcashIcon.png"
                                alt="GCash"
                            >

                            <span
                                class="payment-option-label"
                            >
                                GCash
                            </span>

                        </span>

                    </label>


                    <!-- PAYMAYA -->

                    <label
                        class="payment-option"
                    >

                        <span class="payment-radio">

                            <input
                                type="radio"
                                name="payment_method"
                                value="paymaya"
                            >

                            <span class="radio-dot"></span>

                        </span>


                        <span
                            class="payment-option-body"
                        >

                            <img
                                class="payment-icon-sm"
                                src="images/paymayaicon.png"
                                alt="PayMaya"
                            >

                            <span
                                class="payment-option-label"
                            >
                                PayMaya
                            </span>

                        </span>

                    </label>


                </form>

            </div>

        </div>


        <!-- =================================================
             FOOTER
        ================================================== -->

        <div class="checkout-footer">


            <div class="secure-note">

                <img
                    src="images/securepaymenticon.png"
                    alt=""
                >


                <div>

                    <strong>
                        Secure payment
                    </strong>

                    <p>
                        Your payment information is encrypted and secure.
                    </p>

                </div>

            </div>


            <button
                type="submit"
                form="paymentForm"
                class="pay-button"
            >

                PAY

                <span class="peso">
                    &#8369;
                </span>

                <?php echo number_format(
                    $plan["price"],
                    2
                ); ?>

            </button>

        </div>

    </div>

</div>


<script>


// =====================================================
// PAYMENT OPTION HIGHLIGHT
// =====================================================

document
    .querySelectorAll(".payment-option")
    .forEach(function(option) {


        option.addEventListener(
            "click",
            function() {


                document
                    .querySelectorAll(
                        ".payment-option"
                    )
                    .forEach(
                        function(item) {

                            item.classList.remove(
                                "selected"
                            );

                        }
                    );


                option.classList.add(
                    "selected"
                );


                const radio =
                    option.querySelector(
                        "input[type=radio]"
                    );


                if (radio) {

                    radio.checked = true;

                }

            }
        );

    });


</script>


</body>
</html>