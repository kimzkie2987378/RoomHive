<?php
/* =========================================================
   ROOMHIVE — STAY COMPLETION SWEEP + POINT AWARDS
   config/booking_autocomplete.php

   Two stages, both idempotent and self-healing:

   STAGE A (flip): confirmed bookings whose checkout_date has
   passed are flipped to 'completed'. Long-term stays
   (checkout_date IS NULL) are NEVER auto-completed — hosts
   end those manually via booking/complete-booking.php.

   STAGE B (award): completed bookings that have amount_paid
   but NO ledger row yet get Hive Club points awarded to the
   tenant (1 pt per P10). The NOT EXISTS check means a crash
   between stages self-heals on the next run, and the ledger's
   UNIQUE key makes double-awards impossible regardless of how
   many code paths call this.

   WIRING (one line per page, after db_connect.php is loaded):
     require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/booking_autocomplete.php';
   The sweep runs automatically ONCE per request on include —
   cheap (one indexed SELECT) when there is nothing to do.

   Recommended call sites: listing.php, pendingtenants.php,
   hostbookings.php, userbookings.php, booking-details.php,
   hiveclub.php.
========================================================= */

if (!function_exists('hive_autocomplete_stays')) {

function hive_autocomplete_stays(PDO $pdo): int
{
    $completed = 0;

    /* ================= STAGE A — flip stale confirmed stays ================= */
    try {
        $find = $pdo->prepare(
            "SELECT b.id,
                    l.user_id AS host_id,
                    l.title   AS listing_title
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE b.status = 'confirmed'
               AND b.checkin_date IS NOT NULL
               AND b.checkout_date IS NOT NULL
               AND b.checkout_date < CURDATE()
             ORDER BY b.checkout_date ASC
             LIMIT 50"
        );
        $find->execute();
        $candidates = $find->fetchAll();

        foreach ($candidates as $row) {

            /* Per-row transaction with FOR UPDATE so a host
               cancelling at the same moment can't race us. */
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare(
                    "SELECT id, status FROM bookings WHERE id = :id FOR UPDATE"
                );
                $lock->execute(['id' => $row['id']]);
                $b = $lock->fetch();

                if (!$b || $b['status'] !== 'confirmed') {
                    $pdo->rollBack();
                    continue;
                }

                $upd = $pdo->prepare(
                    "UPDATE bookings SET status = 'completed'
                     WHERE id = :id AND status = 'confirmed'"
                );
                $upd->execute(['id' => $row['id']]);

                if ($upd->rowCount() === 0) {
                    $pdo->rollBack();
                    continue;
                }

                $pdo->commit();
                $completed++;

                /* Brief host notification (tenant gets their
                   points notification in Stage B below). */
                try {
                    $msg = 'The stay at "' . $row['listing_title']
                         . '" has ended and was marked completed.';
                    $nh = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
                    if (file_exists($nh)) { require_once $nh; }

                    if (function_exists('roomhive_notify')) {
                        roomhive_notify($pdo, (int) $row['host_id'], $msg,
                            '/webprogg/host/hostbookings.php');
                    } else {
                        $n = $pdo->prepare(
                            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                             VALUES (:u, :m, :l, 0, NOW())"
                        );
                        $n->execute([
                            'u' => (int) $row['host_id'],
                            'm' => mb_substr($msg, 0, 240),
                            'l' => '/webprogg/host/hostbookings.php',
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log('autocomplete host notify: ' . $e->getMessage());
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                continue;
            }
        }

    } catch (PDOException $e) {
        error_log('autocomplete stage A failed: ' . $e->getMessage());
    }

    /* ================= STAGE B — award points for completed stays =================
       Finds completed bookings with money paid but no ledger row yet
       (crash-recovery + backfill for pre-engine completions), and awards
       once via the ledger's UNIQUE key. */
    try {
        $awardFind = $pdo->prepare(
            "SELECT b.id,
                    b.user_id AS tenant_id,
                    b.amount_paid,
                    l.title   AS listing_title
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE b.status = 'completed'
               AND b.amount_paid > 0
               AND NOT EXISTS (
                   SELECT 1 FROM hive_points_ledger hpl
                   WHERE hpl.reference_type = 'booking'
                     AND hpl.reference_id  = b.id
                     AND hpl.bucket        = 'redeemable'
               )
             ORDER BY b.id ASC
             LIMIT 50"
        );
        $awardFind->execute();
        $awardCandidates = $awardFind->fetchAll();

        foreach ($awardCandidates as $row) {

            $points = hive_points_for_peso((float) $row['amount_paid']);
            if ($points < 1) { $points = 1; } /* never award fewer than 1 pt */

            $result = hive_award_points(
                $pdo,
                (int) $row['tenant_id'],
                $points,
                'Stay completed: ' . $row['listing_title'],
                'booking',
                (int) $row['id']
            );

            if (!empty($result['ok']) && empty($result['already'])) {
                try {
                    $msg = '🍯 Your stay at "' . $row['listing_title']
                         . '" is complete — you earned ' . number_format($points)
                         . ' Hive Club points!';
                    $nh = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
                    if (file_exists($nh)) { require_once $nh; }

                    if (function_exists('roomhive_notify')) {
                        roomhive_notify($pdo, (int) $row['tenant_id'], $msg,
                            '/webprogg/booking/userbookings.php');
                    } else {
                        $n = $pdo->prepare(
                            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                             VALUES (:u, :m, :l, 0, NOW())"
                        );
                        $n->execute([
                            'u' => (int) $row['tenant_id'],
                            'm' => mb_substr($msg, 0, 240),
                            'l' => '/webprogg/booking/userbookings.php',
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log('autocomplete tenant notify: ' . $e->getMessage());
                }
            }
        }

    } catch (PDOException $e) {
        error_log('autocomplete stage B failed: ' . $e->getMessage());
    }

    return $completed;
}

} /* !function_exists */

/* =========================================================
   AUTO-RUN once per request when included after
   db_connect.php. Cheap no-op when there's nothing to do.
========================================================= */
if (isset($pdo) && $pdo instanceof PDO && empty($GLOBALS['hive_autocomplete_done'])) {
    $GLOBALS['hive_autocomplete_done'] = true;
    try {
        hive_autocomplete_stays($pdo);
    } catch (Throwable $e) {
        error_log('autocomplete auto-run failed: ' . $e->getMessage());
    }
}