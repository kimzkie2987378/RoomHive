<?php
/* =========================================================
   ROOMHIVE — BOOKING HELPERS
   booking_helpers.php
   Location: /webprogg/config/booking_helpers.php

   Expires stale, UNPAID inquiry holds so a listing doesn't
   stay hidden from listing.php forever just because someone
   clicked "Send Inquiry" and never followed through with
   payment.

   RULE:
     - status = 'pending' AND paid_at IS NULL AND older than
       $holdMinutes  -> stale hold, flip to 'cancelled'.
       (listing.php's NOT EXISTS check only looks at
       'pending'/'confirmed', so once this flips to
       'cancelled' the listing naturally reappears — no other
       change needed.)

     - status = 'pending' AND paid_at IS SET -> a real hold
       created by a completed payment (see process-payment.php).
       Left alone indefinitely; only accept-booking.php (or an
       explicit cancel) should change it from here.

     - status = 'confirmed' -> real occupied dates, never
       touched by this function.

   There is no cron/queue in this stack, so this runs "lazily":
   call it right before any query that decides which listings
   currently count as taken (listing.php's SELECT, and book.php's
   FOR UPDATE check). That keeps expiry accurate to "whenever
   someone next loads a relevant page," which is good enough for
   a 10-minute hold window — it is not second-accurate, and a
   listing will not un-hide itself while nobody is browsing.
   ========================================================= */

function roomhive_expire_stale_bookings(PDO $pdo, int $holdMinutes = 10): void
{
    $stmt = $pdo->prepare(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'pending'
           AND paid_at IS NULL
           AND booked_at < (NOW() - INTERVAL :minutes MINUTE)"
    );
    $stmt->bindValue(':minutes', $holdMinutes, PDO::PARAM_INT);
    $stmt->execute();
}
