<?php

session_start();

require_once 'db_connect.php';


// =====================================================
// ROOMHIVE - HIVE CLUB
// STOP SUBSCRIBE (cancel Hive Club membership)
// =====================================================
//
// Called by the "STOP SUBSCRIBE" button on hiveclub.php.
// It does NOT delete the member's row or their points
// history — it just flips membership_status to "inactive"
// so $isHiveMember becomes false again on hiveclub.php,
// which brings back the JOIN HIVE CLUB / HOW IT WORKS
// buttons. If they rejoin later, membership.php can flip
// membership_status back to "active" (and update the tier)
// on the same row instead of creating a new one.
// =====================================================

$isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

if (!$isLoggedIn) {
    header("Location: loginform.php");
    exit;
}


// Only accept this as a real form submission, not a
// plain link click / GET request.

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: hiveclub.php");
    exit;
}


$userId = $_SESSION["user_id"] ?? null;

if (!$userId && isset($_SESSION["id"])) {
    $userId = $_SESSION["id"];
}


if ($userId) {

    try {

        $cancel = $pdo->prepare("
            UPDATE hive_members
            SET membership_status = 'inactive'
            WHERE user_id = ?
            AND membership_status = 'active'
        ");

        $cancel->execute([$userId]);

    } catch (PDOException $e) {

        error_log(
            "Hive Club cancel error: " .
            $e->getMessage()
        );

    }

}


header("Location: hiveclub.php?cancelled=1");
exit;
