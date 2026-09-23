<?php
/* =========================================================
   ROOMHIVE — BOOKING HELPERS
   Location: /webprogg/config/booking_helpers.php

   FIXED FOR YOUR SCHEMA: bookings has NO paid_at column —
   payment state lives in payment_status
   enum('pending','paid','failed','cancelled'). The old
   "AND paid_at IS NULL" threw "Unknown column" on every call,
   so expired inquiry holds were NEVER cancelled and listings
   stayed hidden from listing.php forever.

   RULE:
     - status='pending' AND payment_status='pending' AND older
       than $holdMinutes -> flip to 'cancelled' (unpaid
       inquiry hold — free the listing)
     - status='pending' AND payment_status='paid' -> a real 50%
       reserve; only accept/reject/24h-expiry may touch it
     - 'confirmed' -> occupied, never touched here
   ========================================================= */

function roomhive_expire_stale_bookings(PDO $pdo, int $holdMinutes = 10): void
{
    $stmt = $pdo->prepare(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'pending'
           AND payment_status = 'pending'
           AND booked_at < (NOW() - INTERVAL :minutes MINUTE)"
    );
    $stmt->bindValue(':minutes', $holdMinutes, PDO::PARAM_INT);
    $stmt->execute();
}