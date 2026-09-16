<?php
/* =========================================================
   ROOMHIVE — MARK NOTIFICATIONS READ (endpoint)
   notifications/mark-notifications-read.php

   POST:  id=123   → mark one notification read
   POST:  all=1    → mark ALL of this user's notifications read

   Session-guarded; only ever touches the logged-in user's
   own rows (user_id is always bound to the session).
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

try {

    if (isset($_POST['all'])) {

        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0"
        );
        $stmt->execute(['u' => $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'marked' => 'all']);
        exit;
    }

    $id = isset($_POST['id']) && is_numeric($_POST['id'])
        ? (int) $_POST['id']
        : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No notification id.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE id = :id AND user_id = :u"
    );
    $stmt->execute(['id' => $id, 'u' => $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'marked' => $id]);
    exit;

} catch (PDOException $e) {
    error_log('mark-notifications-read failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}