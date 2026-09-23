<?php
/* =========================================================
   ROOMHIVE — 24-HOUR RESERVE AUTO-REJECT (sweep)
   config/reserve_expiry.php

   Pending bookings (reserves) that the host has NOT accepted
   within 24 hours are automatically:
     1. set to status = 'rejected'
     2. refunded: refunded_amount / refunded_at recorded,
        amount_paid credited back to the tenant's
        users.wallet_balance (same model as reject-booking.php)
     3. BOTH parties notified (tenant + host)

   CALL IT at the top of pages (it's cheap when there's
   nothing to do — one indexed SELECT):

     require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/reserve_expiry.php';
     roomhive_auto_reject_expired_reserves($pdo);

   Recommended call sites: pendingtenants.php, listing.php,
   hostbookings.php, userbookings.php, booking-details.php.

   Never throws — a failed sweep never breaks the page.
========================================================= */

if (!function_exists('roomhive_auto_reject_expired_reserves')) {

    function roomhive_auto_reject_expired_reserves($pdo, $hours = 24) {

        $hours    = max(1, (int) $hours);
        $rejected = 0;

        try {
            /* Candidates: pending, older than the window, that
               actually have money on them (unpaid holds are the
               existing stale-booking sweep's job). */
            $find = $pdo->prepare(
                "SELECT b.id, b.amount_paid,
                        b.user_id AS tenant_id,
                        l.user_id AS host_id,
                        l.title   AS listing_title,
                        u.name    AS guest_name
                 FROM bookings b
                 JOIN listings l ON l.id = b.listing_id
                 JOIN users u    ON u.id = b.user_id
                 WHERE b.status = 'pending'
                   AND b.booked_at <= (NOW() - INTERVAL {$hours} HOUR)
                 ORDER BY b.booked_at ASC
                 LIMIT 50"
            );
            $find->execute();
            $candidates = $find->fetchAll();

            foreach ($candidates as $row) {

                if ((float) ($row['amount_paid'] ?? 0) <= 0.005) {
                    continue;
                }

                /* Per-row transaction with FOR UPDATE so a host
                   accepting at the same moment can't race us. */
                $pdo->beginTransaction();
                try {
                    $lock = $pdo->prepare(
                        "SELECT id, status, amount_paid, user_id AS tenant_id
                         FROM bookings
                         WHERE id = :id
                         FOR UPDATE"
                    );
                    $lock->execute(['id' => $row['id']]);
                    $b = $lock->fetch();

                    if (!$b || $b['status'] !== 'pending') {
                        $pdo->rollBack();
                        continue; /* host accepted meanwhile */
                    }

                    $paid = (float) $b['amount_paid'];

                    $upd = $pdo->prepare(
                        "UPDATE bookings
                         SET status = 'rejected',
                             refunded_amount = :r,
                             refunded_at = NOW()
                         WHERE id = :id AND status = 'pending'"
                    );
                    $upd->execute(['r' => $paid, 'id' => $row['id']]);

                    if ($upd->rowCount() === 0) {
                        $pdo->rollBack();
                        continue;
                    }

                    /* Refund to the tenant's wallet */
                    if ($paid > 0) {
                        $pdo->prepare(
                            "UPDATE users SET wallet_balance = wallet_balance + :a WHERE id = :t"
                        )->execute([
                            'a' => $paid,
                            't' => $b['tenant_id'],
                        ]);
                    }

                    $pdo->commit();
                    $rejected++;

                    /* ---------- Notifications (best effort) ---------- */
                    try {
                        $tenantMsg = 'Your reserve for "' . $row['listing_title']
                                   . '" was auto-declined — the host did not respond within '
                                   . $hours . ' hours. ₱' . number_format($paid, 2)
                                   . ' has been refunded to your RoomHive wallet.';
                        $hostMsg   = 'You did not respond to ' . $row['guest_name']
                                   . '\'s reserve for "' . $row['listing_title']
                                   . '" within ' . $hours . ' hours. It was auto-declined and ₱'
                                   . number_format($paid, 2) . ' was refunded to the guest.';

                        $ins = $pdo->prepare(
                            "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                             VALUES (:u, :m, :l, 0, NOW())"
                        );
                        $ins->execute(['u' => (int) $row['tenant_id'],
                                       'm' => mb_substr($tenantMsg, 0, 240),
                                       'l' => '/webprogg/booking/booking-details.php?id=' . $row['id']]);
                        $ins->execute(['u' => (int) $row['host_id'],
                                       'm' => mb_substr($hostMsg, 0, 240),
                                       'l' => '/webprogg/host/hostbookings.php']);
                    } catch (PDOException $e) {
                        error_log('reserve_expiry notify: ' . $e->getMessage());
                    }

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    continue;
                }
            }

        } catch (PDOException $e) {
            error_log('reserve_expiry sweep failed: ' . $e->getMessage());
        }

        return $rejected;
    }
}