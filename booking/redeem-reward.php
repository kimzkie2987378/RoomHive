<?php
/* =========================================================
   ROOMHIVE — REDEEM REWARD (endpoint)
   booking/redeem-reward.php

   Called via fetch() from hiveclub.php's rewards section.
   Wraps hive_redeem() (config/hiveclub.php), which runs the
   full transactional flow:
     lock reward -> lock member -> balance check ->
     ledger spend row -> deduct redeemable_points ->
     wallet credit -> hive_redemptions row -> notification

   Friendly pre-checks (for good UX before hitting the engine):
     - reward must exist and be active
     - one claim per reward per account (matches the ledger's
       unique-key semantics) -> clear "already claimed" message

   Guests: 401. Points NEVER require an active paid plan —
   an expired member can still spend what they earned.

   Expects POST: reward_id
   Responds JSON: { success, message?, reward?, credited?,
                    balance?, points_spent? }
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to redeem rewards.']);
    exit;
}

 $rewardId = isset($_POST['reward_id']) && is_numeric($_POST['reward_id'])
    ? (int) $_POST['reward_id']
    : 0;

if ($rewardId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid reward.']);
    exit;
}

 $userId = (int) $_SESSION['user_id'];

/* ---- Pre-check 1: reward exists and is active ---- */
 $rwStmt = $pdo->prepare(
    "SELECT id, name FROM hive_rewards WHERE id = :id AND is_active = 1 LIMIT 1"
);
 $rwStmt->execute(['id' => $rewardId]);
 $rewardRow = $rwStmt->fetch();

if (!$rewardRow) {
    echo json_encode(['success' => false, 'message' => 'That reward is no longer available.']);
    exit;
}

/* ---- Pre-check 2: one claim per reward per account ---- */
 $claimedStmt = $pdo->prepare(
    "SELECT id FROM hive_redemptions WHERE user_id = :u AND reward_id = :r LIMIT 1"
);
 $claimedStmt->execute(['u' => $userId, 'r' => $rewardId]);

if ($claimedStmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'You have already claimed "' . $rewardRow['name'] . '". '
                   . 'Each reward can be claimed once per account.',
    ]);
    exit;
}

/* ---- Redeem (engine handles the transaction + notification) ---- */
 $result = hive_redeem($pdo, $userId, $rewardId);

if (empty($result['ok'])) {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Could not complete the redemption. Please try again.',
    ]);
    exit;
}

echo json_encode([
    'success'     => true,
    'reward'      => $result['reward'],
    'credited'    => $result['credited'],
    'balance'     => $result['balance'],
    'points_spent'=> $result['points_spent'],
]);