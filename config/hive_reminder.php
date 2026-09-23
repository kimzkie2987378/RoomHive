<?php
/* =========================================================
   ROOMHIVE — HIVE CLUB EXPIRY REMINDER (lazy auto-run)
   config/hive_reminder.php

   Wire AFTER db_connect.php on user-facing membership pages:
     require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hive_reminder.php';
   Runs at most once per request; cheap no-op when no member
   is inside the 7-day reminder window.
========================================================= */

 $hiveEnginePath = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';
if (is_file($hiveEnginePath)) {
    require_once $hiveEnginePath;
}

if (isset($pdo) && $pdo instanceof PDO
    && function_exists('hive_expiry_reminders')
    && empty($GLOBALS['hive_reminder_done'])) {

    $GLOBALS['hive_reminder_done'] = true;
    try {
        hive_expiry_reminders($pdo);
    } catch (Throwable $e) {
        error_log('hive reminder auto-run failed: ' . $e->getMessage());
    }
}