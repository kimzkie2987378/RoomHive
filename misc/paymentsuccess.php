<?php
/* =========================================================
   ROOMHIVE - PAYMENT SUCCESS (Hive Club purchase)

   Phase 6 — LIFECYCLE:
   1. membership_expires_at stamped = +1 year. Renewing an
      active membership EXTENDS from the current expiry
      (never loses paid days); a new/lapsed member starts
      from now.
   2. hive_grant_purchase() awards the plan's bonus points
      to BOTH buckets via the ledger (idempotent per
      transaction id — double-submit can't double-grant).
   3. Welcome notification.

   KEPT: plan/purchased_at writes (schema migration),
   GREATEST() points floor, absolute "Explore Benefits" link.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

if (!$isLoggedIn) {
    header("Location: /webprogg/auth/loginform.php?redirect=" .
        urlencode("/webprogg/user/membership.php"));
    exit;
}

 $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;

if (!$userId) {
    header("Location: /webprogg/auth/loginform.php?redirect=" .
        urlencode("/webprogg/user/membership.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /webprogg/user/membership.php");
    exit;
}

 $plans = [
    "gold"     => ["label" => "Gold",     "points" => 5000,  "amount" => 399.00],
    "platinum" => ["label" => "Platinum", "points" => 25000, "amount" => 799.00],
];

 $planKey = $_POST["plan"] ?? "";

if (!array_key_exists($planKey, $plans)) {
    header("Location: /webprogg/user/membership.php");
    exit;
}

 $plan = $plans[$planKey];

 $allowedPaymentMethods = ["card", "grabpay", "gcash", "paymaya"];
 $paymentMethod = $_POST["payment_method"] ?? "";

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    header("Location: /webprogg/misc/completepurchase.php?plan=" . urlencode($planKey));
    exit;
}

 $expiryInfo = null;

try {
    $pdo->beginTransaction();

    $memberQuery = $pdo->prepare(
        "SELECT * FROM hive_members WHERE user_id = ? LIMIT 1 FOR UPDATE"
    );
    $memberQuery->execute([$userId]);
    $member = $memberQuery->fetch();

    /* ---------------------------------------------
       EXPIRY MATH
       Active & unexpired  -> extend from current expiry
       New / lapsed / none -> start from now
    --------------------------------------------- */
    if ($member
        && $member['membership_status'] === 'active'
        && $member['membership_expires_at'] !== null
        && strtotime($member['membership_expires_at']) > time()
    ) {
        $baseExpiry = $member['membership_expires_at'];
    } else {
        $baseExpiry = date('Y-m-d H:i:s');
    }

    $newExpiry = date('Y-m-d H:i:s', strtotime($baseExpiry . ' +365 days'));
    $expiryInfo = $newExpiry;

    if (!$member) {
        $memberId = "RH " . date("Y") . " " . str_pad((string) $userId, 4, "0", STR_PAD_LEFT);
        $pdo->prepare(
            "INSERT INTO hive_members
                (user_id, member_id, tier, points,
                 lifetime_points, redeemable_points,
                 membership_status, membership_expires_at)
             VALUES (?, ?, 'Bronze', 0, 0, 0, 'active', ?)"
        )->execute([$userId, $memberId, $newExpiry]);

        $memberQuery->execute([$userId]);
        $member = $memberQuery->fetch();
    } else {
        $pdo->prepare(
            "UPDATE hive_members
             SET tier = ?,
                 points = GREATEST(points, ?),
                 membership_status = 'active',
                 membership_expires_at = ?
             WHERE user_id = ?"
        )->execute([
            $plan["label"],
            $plan["points"],
            $newExpiry,
            $userId,
        ]);
    }

    $hiveMemberDatabaseId = (int) $member["id"];

    $pdo->prepare(
        "INSERT INTO hiveclub_transactions
            (user_id, hive_member_id, plan, amount, payment_method, payment_status)
         VALUES (?, ?, ?, ?, ?, 'paid')"
    )->execute([
        $userId,
        $hiveMemberDatabaseId,
        $planKey,
        $plan["amount"],
        $paymentMethod,
    ]);

    $transactionId = (int) $pdo->lastInsertId();

    $pdo->commit();

    /* =============================================
       POST-COMMIT (each failure never breaks the purchase):

       1. Purchase bonus via the ledger — points land in
          BOTH buckets; unique key per transaction id makes
          double-grant impossible.
    ============================================= */
    try {
        hive_grant_purchase($pdo, $userId, $planKey, $transactionId);
    } catch (Exception $e) {
        error_log('hive grant_purchase failed: ' . $e->getMessage());
    }

    /* 2. Welcome notification */
    try {
        $welcomeMsg = '👑 Welcome to Hive Club ' . $plan["label"] . '! '
            . $plan["points"] . ' bonus points have been added to your account'
            . ($plan["label"] === "Gold" ? ' — 10% off every stay.' : ' — 15% off every stay.')
            . ' Membership active until ' . date('M j, Y', strtotime($newExpiry)) . '.';

        $nh = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
        if (file_exists($nh)) { require_once $nh; }

        if (function_exists('roomhive_notify')) {
            roomhive_notify($pdo, (int) $userId, $welcomeMsg, '/webprogg/hiveclub.php');
        } else {
            $n = $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                 VALUES (:u, :m, :l, 0, NOW())"
            );
            $n->execute([
                'u' => (int) $userId,
                'm' => mb_substr($welcomeMsg, 0, 240),
                'l' => '/webprogg/hiveclub.php',
            ]);
        }
    } catch (Exception $e) {
        error_log('hive welcome notify failed: ' . $e->getMessage());
    }

    $_SESSION["member_tier"]      = $plan["label"] . " Member";
    $_SESSION["member_points"]    = max((int) ($_SESSION["member_points"] ?? 0), (int) $plan["points"]);
    $_SESSION["hive_club_member"] = true;

    $paymentSuccessful = true;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Hive Club payment error: " . $e->getMessage());
    $paymentSuccessful = false;
    $errorMessage = "We couldn't complete your membership purchase. Please try again.";
}

if (!$paymentSuccessful) {
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoomHive - Payment Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/webprogg/assets/hiveclub-section.css">
</head>
<body>

<div class="success-page-wrapper">
    <div class="success-card">
        <a href="/webprogg/user/membership.php" class="success-close" aria-label="Close">
            &times;
        </a>

        <div class="success-icon-wrap">
            <div style="font-size:70px; color:#d9534f;">
                !
            </div>
        </div>

        <h1 class="success-title">
            Payment Failed
        </h1>

        <p class="success-subtitle">
            <?php echo htmlspecialchars($errorMessage); ?>
        </p>

        <div class="success-actions">
            <a href="/webprogg/user/membership.php" class="success-btn primary">
                TRY AGAIN
            </a>
            <a href="/webprogg/hiveclub.php" class="success-btn secondary">
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoomHive - Payment Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/webprogg/assets/hiveclub-section.css">
</head>
<body>

<div class="success-page-wrapper">
    <div class="success-card">

        <a href="/webprogg/hiveclub.php" class="success-close" aria-label="Close">
            &times;
        </a>

        <div class="success-icon-wrap">
            <img
                class="success-icon"
                src="/webprogg/images/SuccessPaymentCrown.png"
                alt="<?php echo htmlspecialchars($plan["label"]); ?> Membership"
            >
        </div>

        <h1 class="success-title">
            Welcome to <?php echo htmlspecialchars($plan["label"]); ?>!
        </h1>

        <p class="success-subtitle">
            Your membership is now active.
            Start enjoying exclusive perks and
            earning more points on every stay.
        </p>

        <div style="margin: 20px auto; padding: 15px 20px; background: #f8f8f8; border-radius: 12px; max-width: 400px;">
            <strong>Membership:</strong>
            <?php echo htmlspecialchars($plan["label"]); ?><br>

            <strong>Bonus Points:</strong>
            <?php echo number_format($plan["points"]); ?><br>

            <strong>Amount:</strong>
            &#8369; <?php echo number_format($plan["amount"], 2); ?><br>

            <strong>Payment:</strong>
            <?php echo htmlspecialchars(strtoupper($paymentMethod)); ?><br>

            <strong>Active Until:</strong>
            <?php echo htmlspecialchars(date('M j, Y', strtotime($expiryInfo))); ?>
        </div>

        <div class="success-actions">
            <a href="/webprogg/hiveclub.php" class="success-btn primary">
                GO BACK TO HIVE CLUB
            </a>

            <a href="/webprogg/hiveclub.php#benefits" class="success-btn secondary">
                EXPLORE BENEFITS
            </a>
        </div>

    </div>
</div>

</body>
</html>