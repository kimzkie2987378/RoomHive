<?php
/* =========================================================
   ROOMHIVE — NOTIFY HELPER
   includes/notify.php

   One function any page can call to create a notification:

       require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
       roomhive_notify($pdo, $userId, 'Your booking was accepted!', '/webprogg/booking/booking-details.php?id=29');

   - Writes to notifications (user_id, message, link, is_read, created_at)
   - Truncates message to 240 chars (column is varchar(255))
   - Never throws — a failed notification must never break
     the action that caused it (accept, pay, etc.)
========================================================= */

if (!function_exists('roomhive_notify')) {
    function roomhive_notify($pdo, $userId, $message, $link) {
        try {
            $userId  = (int) $userId;
            $message = mb_substr(trim((string) $message), 0, 240);
            $link    = trim((string) $link);
            if ($link === '') {
                $link = '/webprogg/user/notifications.php';
            }

            if ($userId <= 0 || $message === '') {
                return false;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                 VALUES (:u, :m, :l, 0, NOW())"
            );
            $stmt->execute(['u' => $userId, 'm' => $message, 'l' => $link]);
            return true;

        } catch (PDOException $e) {
            error_log('roomhive_notify failed: ' . $e->getMessage());
            return false;
        }
    }
}