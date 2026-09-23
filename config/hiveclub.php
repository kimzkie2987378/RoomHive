<?php
/* =========================================================
   ROOMHIVE — HIVE CLUB ENGINE (FINAL)
   config/hiveclub.php

   The single source of truth for every Hive Club rule.
   Include once wherever membership logic is needed:

       require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

   LOCKED RULES:
   - Earning: 1 point per P10 of amount_paid, awarded when a
     booking becomes 'completed' (ledger-guarded, idempotent).
   - Tiers (by LIFETIME points, never spent):
       Bronze   0 - 4,999     5% off stays
       Gold     5,000-24,999  10% off stays
       Platinum 25,000+       15% off stays
   - Redemption (v1): wallet credit only, 1 pt = P0.50,
     one claim per reward per account, no active plan
     required (points are earned money).
   - Expiry: paid membership benefits last 1 year, then the
     lazy sweep flips membership_status to 'expired'. POINTS
     NEVER EXPIRE — both buckets persist forever. Reminders
     fire once per cycle, 7 days before expiry.
   - Bronze is auto-provisioned: every user earns points
     from day one; buying Gold/Platinum adds perks + bonus
     points. NULL expiry = never-expiring (Bronze).
   - Dual buckets: lifetime_points (tier) + redeemable_points
     (spendable). Legacy `points` column kept in sync
     (= lifetime) so existing UI keeps rendering.

   CONCURRENCY: all money-ish operations run inside
   transactions with SELECT ... FOR UPDATE on the member row;
   double-awards are blocked by the ledger's
   UNIQUE(reference_type, reference_id, bucket).
========================================================= */

if (!function_exists('hive_config')) {

/* ---------------------------------------------------------
   RULES — single source of truth
--------------------------------------------------------- */
function hive_config() {
    return [
        /* points per peso of amount_paid, awarded on completion */
        'points_per_peso'      => 0.1,          /* 1 pt / P10 */
        'points_min_payout'    => 1,            /* never award fewer than 1 pt */

        /* tier thresholds on lifetime_points (upper bound exclusive) */
        'tiers' => [
            'Bronze'   => ['min' => 0,     'discount' => 5],
            'Gold'     => ['min' => 5000,  'discount' => 10],
            'Platinum' => ['min' => 25000, 'discount' => 15],
        ],

        /* purchase bonuses (applied to BOTH buckets) */
        'purchase_bonus' => [
            'gold'     => 5000,
            'platinum' => 25000,
        ],

        /* paid membership window */
        'membership_days' => 365,

        /* redemption: 1 pt = P0.50 wallet credit */
        'peso_per_point' => 0.5,

        /* expiry reminder window (days before expiry) */
        'expiry_reminder_days' => 7,
    ];
}

/* ---------------------------------------------------------
   CONFIG CONVENIENCE ACCESSORS
--------------------------------------------------------- */
function hive_tier_for_points($lifetimePoints) {
    $tiers = hive_config()['tiers'];
    $tier  = 'Bronze';
    foreach ($tiers as $name => $meta) {
        if ($lifetimePoints >= $meta['min']) {
            $tier = $name;
        }
    }
    return $tier;
}

function hive_tier_discount($tierName) {
    $tiers = hive_config()['tiers'];
    return isset($tiers[$tierName]) ? (int) $tiers[$tierName]['discount'] : 0;
}

/* Next tier + points still needed, for progress bars. */
function hive_next_tier($lifetimePoints) {
    $tiers = hive_config()['tiers'];
    $names = array_keys($tiers);
    $current = hive_tier_for_points($lifetimePoints);
    $idx = array_search($current, $names, true);

    /* already top tier */
    if ($idx === false || $idx >= count($names) - 1) {
        return null;
    }

    $nextName = $names[$idx + 1];
    return [
        'name'   => $nextName,
        'min'    => (int) $tiers[$nextName]['min'],
        'needed' => max(0, (int) $tiers[$nextName]['min'] - (int) $lifetimePoints),
    ];
}

/* ---------------------------------------------------------
   LAZY EXPIRY SWEEP
   Flips ACTIVE memberships past their expires_at to
   'expired'. Cheap when nothing to do; call it at the top
   of any page that reads membership state (same lazy
   pattern as roomhive_expire_stale_bookings).
   Returns the number of memberships expired.
--------------------------------------------------------- */
function hive_expiry_sweep(PDO $pdo) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE hive_members
             SET membership_status = 'expired'
             WHERE membership_status = 'active'
               AND membership_expires_at IS NOT NULL
               AND membership_expires_at < NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('hive_expiry_sweep failed: ' . $e->getMessage());
        return 0;
    }
}

/* ---------------------------------------------------------
   MEMBER FETCH / AUTO-PROVISION
   Every user gets a Bronze row lazily — earning starts at
   their first completed booking even if they never paid
   for a plan. $autoProvision=false returns null instead of
   creating (for read-only contexts where a write would be
   surprising).
--------------------------------------------------------- */
function hive_member(PDO $pdo, $userId, $autoProvision = true) {
    $userId = (int) $userId;
    if ($userId <= 0) { return null; }

    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM hive_members WHERE user_id = :u LIMIT 1"
        );
        $stmt->execute(['u' => $userId]);
        $member = $stmt->fetch();

        if ($member || !$autoProvision) {
            return $member ?: null;
        }

        /* Provision Bronze. member_id format matches the
           existing convention: "RH YYYY 0001". */
        $memberCode = 'RH ' . date('Y') . ' '
            . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);

        try {
            $ins = $pdo->prepare(
                "INSERT INTO hive_members
                    (user_id, member_id, tier, points,
                     lifetime_points, redeemable_points,
                     membership_status, membership_expires_at)
                 VALUES
                    (:u, :code, 'Bronze', 0, 0, 0, 'active', NULL)"
            );
            $ins->execute(['u' => $userId, 'code' => $memberCode]);

            /* Bronze = free: benefits active immediately, but
               expiry stays NULL so the sweep never touches it.
               hive_active() treats NULL expiry as never-expiring
               for Bronze specifically (see below). */

            $stmt->execute(['u' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            /* race: another request provisioned first — refetch */
            $stmt->execute(['u' => $userId]);
            return $stmt->fetch() ?: null;
        }

    } catch (PDOException $e) {
        error_log('hive_member failed: ' . $e->getMessage());
        return null;
    }
}

/* ---------------------------------------------------------
   ACTIVITY / DISCOUNT HELPERS
   hive_active(): membership perks usable right now.
     - status 'active' AND (expiry NULL = never-expiring
       [Bronze] OR expiry in the future)
     - 'expired' members keep their points but lose perks
       until they renew.
--------------------------------------------------------- */
function hive_active($member) {
    if (!$member) { return false; }
    if (($member['membership_status'] ?? '') !== 'active') { return false; }

    $exp = $member['membership_expires_at'] ?? null;
    if ($exp === null) {
        /* NULL expiry: never-expiring — but only Bronze should
           look like this; be permissive anyway. */
        return true;
    }
    return strtotime($exp) > time();
}

function hive_discount_pct($member) {
    if (!hive_active($member)) { return 0; }
    return hive_tier_discount($member['tier'] ?? 'Bronze');
}

/* Days until expiry (null = never expires; 0/neg = past). */
function hive_days_until_expiry($member) {
    $exp = $member['membership_expires_at'] ?? null;
    if ($exp === null) { return null; }
    return (int) ceil((strtotime($exp) - time()) / 86400);
}

/* ---------------------------------------------------------
   NOTIFIER (self-contained, message-carrying)
--------------------------------------------------------- */
if (!function_exists('hive_notify')) {
    function hive_notify(PDO $pdo, $userId, $message, $link) {
        try {
            $userId = (int) $userId;
            if ($userId <= 0) { return false; }

            $nh = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notify.php';
            if (file_exists($nh)) { require_once $nh; }

            if (function_exists('roomhive_notify')) {
                if (roomhive_notify($pdo, $userId, $message, $link)) { return true; }
            }

            $n = $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read, created_at)
                 VALUES (:u, :m, :l, 0, NOW())"
            );
            $n->execute([
                'u' => $userId,
                'm' => mb_substr($message, 0, 240),
                'l' => $link,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('hive_notify failed: ' . $e->getMessage());
            return false;
        }
    }
}

/* ---------------------------------------------------------
   AWARD POINTS (the earn path)
   Awards to BOTH buckets inside one transaction:
     - ledger row per bucket (UNIQUE key blocks double
       awards for the same reference)
     - member balances updated
     - tier re-checked; tier-up fires ONE notification
   Idempotent per (reference_type, reference_id): calling
   twice for the same booking returns ['already' => true].

   $points is a positive integer. reference_type must be
   'booking' | 'purchase' | 'admin'.
--------------------------------------------------------- */
function hive_award_points(PDO $pdo, $userId, $points, $description, $referenceType, $referenceId = null) {
    $userId = (int) $userId;
    $points = (int) $points;

    if ($userId <= 0 || $points <= 0) {
        return ['ok' => false, 'reason' => 'invalid_input'];
    }
    if (!in_array($referenceType, ['booking', 'purchase', 'admin'], true)) {
        return ['ok' => false, 'reason' => 'invalid_reference_type'];
    }

    try {
        $pdo->beginTransaction();

        /* Provision-on-award: guarantee the member row exists
           (locked) before writing balances. */
        $stmt = $pdo->prepare(
            "SELECT * FROM hive_members WHERE user_id = :u LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['u' => $userId]);
        $member = $stmt->fetch();

        if (!$member) {
            $memberCode = 'RH ' . date('Y') . ' '
                . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
            try {
                $pdo->prepare(
                    "INSERT INTO hive_members
                        (user_id, member_id, tier, points,
                         lifetime_points, redeemable_points,
                         membership_status, membership_expires_at)
                     VALUES (:u, :code, 'Bronze', 0, 0, 0, 'active', NULL)"
                )->execute(['u' => $userId, 'code' => $memberCode]);
            } catch (PDOException $e) {
                /* already provisioned concurrently */
            }
            $stmt->execute(['u' => $userId]);
            $member = $stmt->fetch();

            if (!$member) {
                $pdo->rollBack();
                return ['ok' => false, 'reason' => 'member_missing'];
            }
        }

        /* Double-award guard: try to claim the ledger rows
           FIRST. If either unique key collides, this award
           already happened — roll back and report. */
        $already = false;
        try {
            $led = $pdo->prepare(
                "INSERT INTO hive_points_ledger
                    (user_id, hive_member_id, bucket, points,
                     description, reference_type, reference_id)
                 VALUES
                    (:u, :m, 'redeemable', :p1, :d, :rt, :rid),
                    (:u2, :m2, 'lifetime', :p2, :d2, :rt2, :rid2)"
            );
            $led->execute([
                'u'    => $userId, 'm'  => (int) $member['id'],
                'p1'   => $points, 'd'  => mb_substr($description, 0, 240),
                'rt'   => $referenceType, 'rid' => $referenceId,
                'u2'   => $userId, 'm2' => (int) $member['id'],
                'p2'   => $points, 'd2' => mb_substr($description, 0, 240),
                'rt2'  => $referenceType, 'rid2' => $referenceId,
            ]);
        } catch (PDOException $e) {
            /* 23000 = integrity constraint violation (duplicate) */
            if ($e->getCode() === '23000') {
                $already = true;
            } else {
                throw $e;
            }
        }

        if ($already) {
            $pdo->rollBack();
            return ['ok' => true, 'already' => true, 'points' => 0];
        }

        $oldTier = $member['tier'];

        $upd = $pdo->prepare(
            "UPDATE hive_members
             SET redeemable_points = redeemable_points + :p1,
                 lifetime_points   = lifetime_points + :p2,
                 points            = points + :p3,
                 tier              = :tier
             WHERE id = :id"
        );
        $upd->execute([
            'p1' => $points,
            'p2' => $points,
            'p3' => $points, /* legacy mirror */
            'tier' => hive_tier_for_points((int) $member['lifetime_points'] + $points),
            'id'   => (int) $member['id'],
        ]);

        $newTier = hive_tier_for_points((int) $member['lifetime_points'] + $points);

        $pdo->commit();

        /* Tier-up notification (outside the transaction) */
        if ($newTier !== $oldTier) {
            hive_notify(
                $pdo, $userId,
                '🎉 You leveled up to ' . $newTier . ' in Hive Club! '
                    . ($newTier === 'Gold'
                        ? 'You now get 10% off every stay.'
                        : ($newTier === 'Platinum'
                            ? 'You now get 15% off every stay.'
                            : '')),
                '/webprogg/hiveclub.php'
            );
        }

        return [
            'ok' => true, 'already' => false, 'points' => $points,
            'tier' => $newTier, 'tier_up' => ($newTier !== $oldTier),
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('hive_award_points failed: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'exception'];
    }
}

/* Points earned for a peso amount, per the rules. */
function hive_points_for_peso($peso) {
    $cfg = hive_config();
    $pts = (int) floor(((float) $peso) * $cfg['points_per_peso']);
    return max(0, $pts);
}

/* ---------------------------------------------------------
   REDEEM (the spend path)
   Transactional: lock member -> re-check balance -> deduct
   via ledger -> credit wallet -> redemption row -> notify.
   Membership activity NOT required for redemption (points
   are earned money) — only PERKS require an active plan.
--------------------------------------------------------- */
function hive_redeem(PDO $pdo, $userId, $rewardId) {
    $userId   = (int) $userId;
    $rewardId = (int) $rewardId;

    if ($userId <= 0 || $rewardId <= 0) {
        return ['ok' => false, 'message' => 'Invalid request.'];
    }

    try {
        $pdo->beginTransaction();

        /* Lock reward row */
        $rw = $pdo->prepare(
            "SELECT * FROM hive_rewards WHERE id = :id AND is_active = 1 LIMIT 1 FOR UPDATE"
        );
        $rw->execute(['id' => $rewardId]);
        $reward = $rw->fetch();

        if (!$reward) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That reward is no longer available.'];
        }

        /* Lock member row */
        $mStmt = $pdo->prepare(
            "SELECT * FROM hive_members WHERE user_id = :u LIMIT 1 FOR UPDATE"
        );
        $mStmt->execute(['u' => $userId]);
        $member = $mStmt->fetch();

        if (!$member) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'You are not a Hive Club member yet.'];
        }

        $cost    = (int) $reward['cost_points'];
        $balance = (int) $member['redeemable_points'];

        if ($balance < $cost) {
            $pdo->rollBack();
            return ['ok' => false, 'message' =>
                'You need ' . number_format($cost) . ' points but only have '
                . number_format($balance) . '.'];
        }

        /* Ledger spend row (reference_id = reward id) */
        $led = $pdo->prepare(
            "INSERT INTO hive_points_ledger
                (user_id, hive_member_id, bucket, points,
                 description, reference_type, reference_id)
             VALUES
                (:u, :m, 'redeemable', :p, :d, 'redemption', :rid)"
        );
        $led->execute([
            'u'  => $userId,
            'm'  => (int) $member['id'],
            'p'  => -$cost,
            'd'  => 'Redeemed: ' . $reward['name'],
            'rid' => $rewardId,
        ]);

        /* NOTE on the unique key: reference_type='redemption'
           rows are NOT covered by the double-award guard the
           way bookings are (each redemption is its own event),
           but reference_id = reward id means re-redeeming the
           SAME reward twice would collide. That is intentional:
           one claim per reward per account, which also caps
           abuse. If you want unlimited re-claims later, switch
           reference_type to 'admin' with reference_id NULL, or
           relax the unique key. */

        $upd = $pdo->prepare(
            "UPDATE hive_members
             SET redeemable_points = redeemable_points - :c
             WHERE id = :id"
        );
        $upd->execute(['c' => $cost, 'id' => (int) $member['id']]);

        /* Credit the wallet (users.wallet_balance exists) */
        $credit = (float) $reward['reward_value'];
        $pdo->prepare(
            "UPDATE users SET wallet_balance = wallet_balance + :v WHERE id = :u"
        )->execute(['v' => $credit, 'u' => $userId]);

        /* Redemption history row */
        $pdo->prepare(
            "INSERT INTO hive_redemptions
                (user_id, hive_member_id, reward_id,
                 points_spent, wallet_credited)
             VALUES (:u, :m, :r, :p, :v)"
        )->execute([
            'u' => $userId,
            'm' => (int) $member['id'],
            'r' => $rewardId,
            'p' => $cost,
            'v' => $credit,
        ]);

        $newBalance = $balance - $cost;

        $pdo->commit();

        hive_notify(
            $pdo, $userId,
            '✅ Redeemed "' . $reward['name'] . '" for ' . number_format($cost)
                . ' points. ₱' . number_format($credit, 2)
                . ' has been credited to your wallet. '
                . 'New balance: ' . number_format($newBalance) . ' pts.',
            '/webprogg/hiveclub.php#rewards'
        );

        return [
            'ok' => true,
            'reward' => $reward['name'],
            'points_spent' => $cost,
            'credited' => $credit,
            'balance' => $newBalance,
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('hive_redeem failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not complete the redemption. Please try again.'];
    }
}

/* ---------------------------------------------------------
   GRANT PURCHASE BONUS (called by paymentsuccess.php)
   Awards the plan's bonus points to BOTH buckets via the
   ledger (reference_type 'purchase' — idempotent per
   transaction id). Also stamps the membership expiry.
--------------------------------------------------------- */
function hive_grant_purchase(PDO $pdo, $userId, $planKey, $transactionId) {
    $cfg = hive_config();

    $bonus = isset($cfg['purchase_bonus'][$planKey])
        ? (int) $cfg['purchase_bonus'][$planKey]
        : 0;

    if ($bonus <= 0) {
        return ['ok' => false, 'reason' => 'no_bonus_for_plan'];
    }

    return hive_award_points(
        $pdo,
        $userId,
        $bonus,
        'Hive Club ' . ucfirst($planKey) . ' membership bonus',
        'purchase',
        $transactionId
    );
}

/* ---------------------------------------------------------
   PHASE 8 — EXPIRY REMINDERS
   Notifies active members 7 days before their paid plan
   expires. ONCE per cycle: the marker (expiry_notified_at)
   only blocks a re-send while it's newer than
   (expiry - 8 days) — the moment a renewal pushes
   membership_expires_at forward by a year, the old marker
   falls outside that window and the reminder re-arms
   automatically. Bronze (NULL expiry) is never touched.
   Returns the number of reminders sent.
--------------------------------------------------------- */
if (!function_exists('hive_expiry_reminders')) {
    function hive_expiry_reminders(PDO $pdo) {
        $cfg = hive_config();
        $window = (int) $cfg['expiry_reminder_days']; /* 7 */

        try {
            $find = $pdo->prepare(
                "SELECT id, user_id, tier, membership_expires_at
                 FROM hive_members
                 WHERE membership_status = 'active'
                   AND membership_expires_at IS NOT NULL
                   AND membership_expires_at > NOW()
                   AND membership_expires_at <= (NOW() + INTERVAL {$window} DAY)
                   AND (expiry_notified_at IS NULL
                        OR expiry_notified_at < (membership_expires_at - INTERVAL " . ($window + 1) . " DAY))
                 LIMIT 50"
            );
            $find->execute();
            $candidates = $find->fetchAll();

            $sent = 0;
            foreach ($candidates as $row) {

                $daysLeft = max(1, (int) ceil(
                    (strtotime($row['membership_expires_at']) - time()) / 86400
                ));

                $discount = hive_tier_discount($row['tier']);

                $msg = '⏳ Your Hive Club ' . $row['tier'] . ' membership expires in '
                     . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' ('
                     . date('M j, Y', strtotime($row['membership_expires_at'])) . '). '
                     . 'Renew to keep your ' . $discount . '% discount — your points never expire.';

                if (hive_notify($pdo, (int) $row['user_id'], $msg,
                        '/webprogg/user/membership.php')) {
                    try {
                        $pdo->prepare(
                            "UPDATE hive_members SET expiry_notified_at = NOW() WHERE id = :id"
                        )->execute(['id' => (int) $row['id']]);
                        $sent++;
                    } catch (PDOException $e) {
                        error_log('hive reminder marker failed: ' . $e->getMessage());
                    }
                }
            }
            return $sent;

        } catch (PDOException $e) {
            error_log('hive_expiry_reminders failed: ' . $e->getMessage());
            return 0;
        }
    }
}

} /* !function_exists hive_config */