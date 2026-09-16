<?php
/* =========================================================
   test-notification.php — ONE-TIME TEST TOOL
   Creates 3 sample notifications for the logged-in user so
   you can verify the bell dropdown works. DELETE THIS FILE
   after testing.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    die('Log in first.');
}

notify_user($pdo, $_SESSION['user_id'], '/webprogg/booking/userbookings.php');
notify_user($pdo, $_SESSION['user_id'], '/webprogg/user/usermessages.php');
notify_user($pdo, $_SESSION['user_id'], '/webprogg/host/hostreviews.php');

echo '3 test notifications created for user #' . (int) $_SESSION['user_id']
   . '. Now DELETE this file and check the bell dropdown.';