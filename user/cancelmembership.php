<?php
/* =========================================================
   ROOMHIVE — CANCEL HIVE CLUB MEMBERSHIP
   user/cancelmembership.php

   Called by "STOP SUBSCRIBE" on hiveclub.php. Flips
   membership_status to 'inactive' so member perks stop.

   POINTS ARE NEVER LOST — both buckets persist forever
   (locked Phase 0 rule). The user keeps earning from
   completed stays and can still redeem; only the tier
   DISCOUNTS pause until they rejoin/renew.

   Wrapped in a transaction + notification. POST-only.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

 $isLoggedIn = (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true
);

if (!$isLoggedIn) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /webprogg/hiveclub.php");
    exit;
}

 $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;

if (!$userId) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

try {
    $pdo->beginTransaction();

    $cancel = $pdo->prepare(
        "UPDATE hive_members
         SET membership_status = 'inactive'
         WHERE user_id = ?
           AND membership_status = 'active'"
    );
    $cancel->execute([$userId]);

    $pdo->commit();

    if ($cancel->rowCount() > 0) {
        hive_notify(
            $pdo,
            (int) $userId,
            'Your Hive Club subscription has been stopped. Your points are safe — '
                . 'you keep every point earned and can still redeem rewards. '
                . 'Rejoin anytime to reactivate your tier discounts.',
            '/webprogg/hiveclub.php'
        );
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Hive Club cancel error: " . $e->getMessage());
}

header("Location: /webprogg/hiveclub.php?cancelled=1");
exit;