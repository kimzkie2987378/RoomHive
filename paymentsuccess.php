<?php

session_start();

require_once 'db_connect.php';


// =====================================================
// ROOMHIVE - PAYMENT SUCCESS
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


// Support another common session name

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
// ONLY ACCEPT POST
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: membership.php"
    );

    exit;

}


// =====================================================
// VALIDATE PLAN
// =====================================================

$plans = [

    "gold" => [

        "label" => "Gold",

        "points" => 5000,

        "amount" => 399.00

    ],

    "platinum" => [

        "label" => "Platinum",

        "points" => 25000,

        "amount" => 799.00

    ]

];


$planKey = $_POST["plan"] ?? "";


if (!array_key_exists(
    $planKey,
    $plans
)) {

    header(
        "Location: membership.php"
    );

    exit;

}


$plan = $plans[$planKey];


// =====================================================
// VALIDATE PAYMENT METHOD
// =====================================================

$allowedPaymentMethods = [

    "card",
    "grabpay",
    "gcash",
    "paymaya"

];


$paymentMethod =
    $_POST["payment_method"] ?? "";


if (!in_array(
    $paymentMethod,
    $allowedPaymentMethods,
    true
)) {

    header(
        "Location: completepurchase.php?plan=" .
        urlencode($planKey)
    );

    exit;

}


// =====================================================
// PROCESS DATABASE UPDATE
// =====================================================

try {


    // -------------------------------------------------
    // START TRANSACTION
    // -------------------------------------------------

    $pdo->beginTransaction();


    // -------------------------------------------------
    // FIND HIVE CLUB MEMBER
    // -------------------------------------------------

    $memberQuery = $pdo->prepare("
        SELECT *
        FROM hive_members
        WHERE user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $memberQuery->execute([
        $userId
    ]);

    $member = $memberQuery->fetch();


    // -------------------------------------------------
    // CREATE MEMBER IF IT DOES NOT EXIST
    // -------------------------------------------------

    if (!$member) {


        $memberId =
            "RH " .
            date("Y") .
            " " .
            str_pad(
                $userId,
                4,
                "0",
                STR_PAD_LEFT
            );


        $insertMember = $pdo->prepare("
            INSERT INTO hive_members
            (
                user_id,
                member_id,
                tier,
                points,
                membership_status
            )
            VALUES
            (
                ?,
                ?,
                'Bronze',
                0,
                'active'
            )
        ");


        $insertMember->execute([

            $userId,

            $memberId

        ]);


        // Get newly created member

        $memberQuery->execute([
            $userId
        ]);

        $member = $memberQuery->fetch();

    }


    // -------------------------------------------------
    // GET DATABASE MEMBER ID
    // -------------------------------------------------

    $hiveMemberDatabaseId =
        (int)$member["id"];


    // -------------------------------------------------
    // UPDATE MEMBERSHIP
    // -------------------------------------------------

    $updateMember = $pdo->prepare("
        UPDATE hive_members

        SET
            tier = ?,
            points = ?,
            membership_status = 'active'

        WHERE user_id = ?
    ");


    $updateMember->execute([

        $plan["label"],

        $plan["points"],

        $userId

    ]);


    // -------------------------------------------------
    // RECORD TRANSACTION
    // -------------------------------------------------

    $insertTransaction = $pdo->prepare("
        INSERT INTO hiveclub_transactions
        (
            user_id,
            hive_member_id,
            plan,
            amount,
            payment_method,
            payment_status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            'paid'
        )
    ");


    $insertTransaction->execute([

        $userId,

        $hiveMemberDatabaseId,

        $planKey,

        $plan["amount"],

        $paymentMethod

    ]);


    // -------------------------------------------------
    // COMMIT
    // -------------------------------------------------

    $pdo->commit();


    // -------------------------------------------------
    // UPDATE SESSION
    // -------------------------------------------------

    $_SESSION["member_tier"] =
        $plan["label"] . " Member";


    $_SESSION["member_points"] =
        $plan["points"];


    $_SESSION["hive_club_member"] =
        true;


    // -------------------------------------------------
    // SUCCESS
    // -------------------------------------------------

    $paymentSuccessful = true;


} catch (PDOException $e) {


    // -------------------------------------------------
    // ROLLBACK
    // -------------------------------------------------

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    error_log(
        "Hive Club payment error: " .
        $e->getMessage()
    );


    $paymentSuccessful = false;


    $errorMessage =
        "We couldn't complete your membership purchase. Please try again.";

}


// =====================================================
// DISPLAY SUCCESS
// =====================================================

if (!$paymentSuccessful) {

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
        RoomHive - Payment Error
    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="hiveclub-section.css"
    >

</head>


<body>


<div class="success-page-wrapper">

    <div class="success-card">

        <a
            href="membership.php"
            class="success-close"
            aria-label="Close"
        >
            &times;
        </a>


        <div class="success-icon-wrap">

            <div
                style="
                    font-size:70px;
                    color:#d9534f;
                "
            >
                !
            </div>

        </div>


        <h1 class="success-title">
            Payment Failed
        </h1>


        <p class="success-subtitle">

            <?php echo htmlspecialchars(
                $errorMessage
            ); ?>

        </p>


        <div class="success-actions">

            <a
                href="membership.php"
                class="success-btn primary"
            >
                TRY AGAIN
            </a>


            <a
                href="hiveclub.php"
                class="success-btn secondary"
            >
                GO BACK TO HIVE CLUB
            </a>

        </div>

    </div>

</div>


</body>
</html>

<?php

exit;

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


    <title>
        RoomHive - Payment Successful
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


<!-- =====================================================
     PAYMENT SUCCESS
===================================================== -->

<div class="success-page-wrapper">


    <div class="success-card">


        <!-- CLOSE -->

        <a
            href="hiveclub.php"
            class="success-close"
            aria-label="Close"
        >

            &times;

        </a>


        <!-- SUCCESS ICON -->

        <div class="success-icon-wrap">

            <img
                class="success-icon"
                src="images/SuccessPaymentCrown.png"
                alt="<?php echo htmlspecialchars(
                    $plan["label"]
                ); ?> Membership"
            >

        </div>


        <!-- TITLE -->

        <h1 class="success-title">

            Welcome to
            <?php echo htmlspecialchars(
                $plan["label"]
            ); ?>!

        </h1>


        <!-- MESSAGE -->

        <p class="success-subtitle">

            Your membership is now active.

            Start enjoying exclusive perks and
            earning more points on every stay.

        </p>


        <!-- MEMBERSHIP INFO -->

        <div
            style="
                margin: 20px auto;
                padding: 15px 20px;
                background: #f8f8f8;
                border-radius: 12px;
                max-width: 400px;
            "
        >

            <strong>
                Membership:
            </strong>

            <?php echo htmlspecialchars(
                $plan["label"]
            ); ?>

            <br>


            <strong>
                Points:
            </strong>

            <?php echo number_format(
                $plan["points"]
            ); ?>


            <br>


            <strong>
                Amount:
            </strong>

            &#8369;

            <?php echo number_format(
                $plan["amount"],
                2
            ); ?>


            <br>


            <strong>
                Payment:
            </strong>

            <?php echo htmlspecialchars(
                strtoupper(
                    $paymentMethod
                )
            ); ?>

        </div>


        <!-- ACTIONS -->

        <div class="success-actions">


            <a
                href="hiveclub.php"
                class="success-btn primary"
            >

                GO BACK TO HIVE CLUB

            </a>


            <a
                href="hiveclub.php#benefits"
                class="success-btn secondary"
            >

                EXPLORE BENEFITS

            </a>


        </div>

    </div>

</div>


</body>
</html>