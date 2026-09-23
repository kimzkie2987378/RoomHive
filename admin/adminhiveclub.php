<?php
/* =========================================================
   ROOMHIVE ADMIN — HIVE CLUB
   adminhiveclub.php

   Console for the Hive Club program (Phases 1-6):

   1. STATS        — members, active plans, points in
                     circulation, wallet credited via redemptions.
   2. MEMBERS      — searchable table (tier, lifetime,
                     spendable, status, expiry) + ADJUST POINTS:
                     ledger-backed, per-bucket, signed. Positive
                     credits, negative claws back (never below
                     zero). Lifetime changes recompute the tier.
                     The member is notified with the reason.
                     Every adjustment lands in
                     hive_points_ledger (reference_type 'admin',
                     reference_id NULL) -> fully audited.
   3. TRANSACTIONS — recent membership purchases.
   4. REWARDS      — catalog CRUD. Deleting a reward that has
                     already been claimed is BLOCKED (history
                     integrity) — deactivate it instead.

   Requires: admin_init.php shell + config/hiveclub.php engine
   + Phase 1 schema.
========================================================= */

require_once __DIR__ . '/admin_init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/hiveclub.php';

/* ---- Lazy expiry sweep so statuses are current ---- */
hive_expiry_sweep($pdo);

/* =========================================================
   POST ACTIONS (CSRF-gated)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['csrf_token'])) {

    if (!hash_equals($csrfToken, $_POST['csrf_token'])) {
        admin_flash_set('error', 'That request could not be verified.');
        header('Location: /webprogg/admin/adminhiveclub.php');
        exit();
    }

    $action = $_POST['action'];

    /* ---------------------------------------------
       ADJUST POINTS (ledger-backed)
    --------------------------------------------- */
    if ($action === 'adjust_points') {

        $uid    = (int) ($_POST['user_id'] ?? 0);
        $bucket = ($_POST['bucket'] ?? '') === 'lifetime' ? 'lifetime' : 'redeemable';
        $points = (int) ($_POST['points'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($uid <= 0 || $points === 0 || $reason === '') {
            admin_flash_set('error', 'Points amount and a reason are required.');
        } elseif (mb_strlen($reason) > 200) {
            admin_flash_set('error', 'Reason must be 200 characters or fewer.');
        } else {

            /* Ensure the member row exists before locking */
            $member = hive_member($pdo, $uid);
            $ok = false;

            if (!$member) {
                admin_flash_set('error', 'That user has no Hive Club membership.');
            } else {
                $pdo->beginTransaction();
                try {
                    $lock = $pdo->prepare(
                        "SELECT * FROM hive_members WHERE id = :id LIMIT 1 FOR UPDATE"
                    );
                    $lock->execute(['id' => (int) $member['id']]);
                    $m = $lock->fetch();

                    if (!$m) {
                        $pdo->rollBack();
                        admin_flash_set('error', 'Membership row disappeared — try again.');
                    } else {

                        $current = (int) ($bucket === 'redeemable'
                            ? $m['redeemable_points']
                            : $m['lifetime_points']);

                        if ($points < 0 && ($current + $points) < 0) {
                            $pdo->rollBack();
                            admin_flash_set('error', 'Adjustment would take the '
                                . ($bucket === 'redeemable' ? 'spendable' : 'lifetime')
                                . ' balance below zero. Current: ' . number_format($current) . ' pts.');
                        } else {

                            /* Ledger row — the audit trail */
                            $led = $pdo->prepare(
                                "INSERT INTO hive_points_ledger
                                    (user_id, hive_member_id, bucket, points,
                                     description, reference_type, reference_id)
                                 VALUES
                                    (:u, :m, :b, :p, :d, 'admin', NULL)"
                            );
                            $led->execute([
                                'u' => (int) $m['user_id'],
                                'm' => (int) $m['id'],
                                'b' => $bucket,
                                'p' => $points,
                                'd' => 'Admin adjustment: ' . $reason,
                            ]);

                            /* Apply to the chosen bucket (lifetime also
                               updates the legacy mirror + recomputes tier) */
                            if ($bucket === 'redeemable') {
                                $pdo->prepare(
                                    "UPDATE hive_members
                                     SET redeemable_points = redeemable_points + :p
                                     WHERE id = :id"
                                )->execute(['p' => $points, 'id' => (int) $m['id']]);
                            } else {
                                $newLifetime = (int) $m['lifetime_points'] + $points;
                                $pdo->prepare(
                                    "UPDATE hive_members
                                     SET lifetime_points = :lp,
                                         points = :lp2,
                                         tier = :tier
                                     WHERE id = :id"
                                )->execute([
                                    'lp'   => $newLifetime,
                                    'lp2'  => $newLifetime,
                                    'tier' => hive_tier_for_points($newLifetime),
                                    'id'   => (int) $m['id'],
                                ]);
                            }

                            $pdo->commit();
                            $ok = true;

                            $bucketLabel = $bucket === 'redeemable'
                                ? 'spendable' : 'lifetime';

                            admin_flash_set('success',
                                ($points > 0 ? 'Credited ' : 'Debited ')
                                . number_format(abs($points)) . ' '
                                . $bucketLabel . ' points '
                                . ($points > 0 ? 'to' : 'from') . ' member #'
                                . $uid . '.');

                            /* Notify the member with the reason */
                            hive_notify(
                                $pdo,
                                (int) $m['user_id'],
                                'An administrator adjusted your Hive Club points: '
                                    . ($points > 0 ? '+' : '')
                                    . number_format($points) . ' ' . $bucketLabel
                                    . ' points. Reason: ' . $reason,
                                '/webprogg/hiveclub.php'
                            );
                        }
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    error_log('adminhiveclub adjust failed: ' . $e->getMessage());
                    admin_flash_set('error', 'Could not apply the adjustment.');
                }
            }
        }

        header('Location: /webprogg/admin/adminhiveclub.php?q=' . urlencode($_POST['ret_q'] ?? ''));
        exit();
    }

    /* ---------------------------------------------
       ADD REWARD
    --------------------------------------------- */
    if ($action === 'add_reward') {

        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $cost   = (int) ($_POST['cost_points'] ?? 0);
        $value  = (float) ($_POST['reward_value'] ?? 0);

        if ($name === '' || $cost <= 0 || $value < 0) {
            admin_flash_set('error', 'Reward name, a positive point cost, and a valid credit value are required.');
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO hive_rewards (name, description, cost_points, reward_value, is_active)
                     VALUES (:n, :d, :c, :v, 1)"
                )->execute([
                    'n' => mb_substr($name, 0, 120),
                    'd' => mb_substr($desc, 0, 255),
                    'c' => $cost,
                    'v' => $value,
                ]);
                admin_flash_set('success', 'Reward "' . $name . '" added to the catalog.');
            } catch (PDOException $e) {
                error_log('adminhiveclub add_reward failed: ' . $e->getMessage());
                admin_flash_set('error', 'Could not add the reward.');
            }
        }

        header('Location: /webprogg/admin/adminhiveclub.php#rewards');
        exit();
    }

    /* ---------------------------------------------
       TOGGLE REWARD
    --------------------------------------------- */
    if ($action === 'toggle_reward') {
        $rid = (int) ($_POST['reward_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE hive_rewards SET is_active = 1 - is_active WHERE id = :id")
                ->execute(['id' => $rid]);
            admin_flash_set('success', 'Reward availability toggled.');
        } catch (PDOException $e) {
            admin_flash_set('error', 'Could not update the reward.');
        }
        header('Location: /webprogg/admin/adminhiveclub.php#rewards');
        exit();
    }

    /* ---------------------------------------------
       DELETE REWARD (blocked once claimed)
    --------------------------------------------- */
    if ($action === 'delete_reward') {
        $rid = (int) ($_POST['reward_id'] ?? 0);

        $claimedStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM hive_redemptions WHERE reward_id = :r"
        );
        $claimedStmt->execute(['r' => $rid]);
        $claimCount = (int) $claimedStmt->fetchColumn();

        if ($claimCount > 0) {
            admin_flash_set('error',
                'This reward has ' . $claimCount . ' redemption'
                . ($claimCount === 1 ? '' : 's') . ' on record — deactivate it instead of deleting.');
        } else {
            try {
                $pdo->prepare("DELETE FROM hive_rewards WHERE id = :id")->execute(['id' => $rid]);
                admin_flash_set('success', 'Reward deleted.');
            } catch (PDOException $e) {
                admin_flash_set('error', 'Could not delete the reward.');
            }
        }

        header('Location: /webprogg/admin/adminhiveclub.php#rewards');
        exit();
    }
}

/* =========================================================
   DATA
========================================================= */

/* ---- Stats ---- */
 $totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM hive_members")->fetchColumn();
 $activeMembers = (int) $pdo->query(
    "SELECT COUNT(*) FROM hive_members
     WHERE membership_status = 'active'
       AND (membership_expires_at IS NULL OR membership_expires_at > NOW())"
)->fetchColumn();
 $pointsInCirculation = (int) $pdo->query(
    "SELECT COALESCE(SUM(redeemable_points),0) FROM hive_members"
)->fetchColumn();
 $walletCredited = (float) $pdo->query(
    "SELECT COALESCE(SUM(wallet_credited),0) FROM hive_redemptions"
)->fetchColumn();

/* ---- Members (searchable) ---- */
 $q = trim($_GET['q'] ?? '');
 $memberParams = [];
 $memberWhere = '';
if ($q !== '') {
    $memberWhere = "AND (u.name LIKE :q OR u.email LIKE :q OR hm.member_id LIKE :q)";
    $memberParams['q'] = '%' . $q . '%';
}

 $membersStmt = $pdo->prepare(
    "SELECT hm.id, hm.user_id, hm.member_id, hm.tier,
            hm.lifetime_points, hm.redeemable_points,
            hm.membership_status, hm.membership_expires_at,
            u.name, u.email
     FROM hive_members hm
     JOIN users u ON u.id = hm.user_id
     WHERE 1 = 1 $memberWhere
     ORDER BY hm.lifetime_points DESC, u.name ASC
     LIMIT 50"
);
 $membersStmt->execute($memberParams);
 $members = $membersStmt->fetchAll();

/* ---- Transactions ---- */
 $transactions = $pdo->query(
    "SELECT ht.id, ht.plan, ht.amount, ht.payment_method, ht.payment_status,
            ht.purchased_at, ht.expires_at,
            u.name, u.email
     FROM hiveclub_transactions ht
     JOIN users u ON u.id = ht.user_id
     ORDER BY ht.purchased_at DESC
     LIMIT 25"
)->fetchAll();

/* ---- Rewards (with claim counts) ---- */
 $rewards = $pdo->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM hive_redemptions hr WHERE hr.reward_id = r.id) AS claim_count
     FROM hive_rewards r
     ORDER BY r.is_active DESC, r.sort_order ASC, r.cost_points ASC"
)->fetchAll();

 $flash = admin_flash_take();
?>
<?php admin_page_start('RoomHive Admin — Hive Club', 'Hive Club', <<<CSS
<style>
    .hc-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
    @media (max-width: 900px) { .hc-stats { grid-template-columns: 1fr 1fr; } }
    .hc-stat {
        background: #fff; border: 1px solid var(--border); border-radius: 14px;
        padding: 16px 18px;
    }
    .hc-stat span { display: block; color: #8B93A6; font-size: 11px; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase; }
    .hc-stat strong { display: block; margin-top: 5px; font-size: 24px; font-weight: 800; color: #14142B; }
    .hc-stat small { display: block; margin-top: 2px; color: #8B93A6; font-size: 11.5px; }

    .hc-adjust-btn {
        border: none; border-radius: 8px; padding: 7px 12px;
        background: #FFF6E9; color: #C77800;
        font-size: 12px; font-weight: 700; cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    .hc-adjust-btn:hover { background: #eda423; color: #fff; }

    .hc-tier-pill {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 800;
    }
    .hc-tier-Bronze   { background: #F0F0F0; color: #666; }
    .hc-tier-Gold     { background: #FFF0C9; color: #B07708; }
    .hc-tier-Platinum { background: #E8F0F5; color: #1A56DB; }

    .hc-rw-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 12px 0; border-bottom: 1px solid #F6F7FB; }
    .hc-rw-row:last-child { border-bottom: none; }
    .hc-rw-name { flex: 1; min-width: 180px; }
    .hc-rw-name strong { display: block; color: #14142B; font-size: 13.5px; }
    .hc-rw-name span { color: #8B93A6; font-size: 12px; }
    .hc-rw-figures { text-align: right; }
    .hc-rw-figures strong { color: #C77800; }
    .hc-rw-figures div { color: #8B93A6; font-size: 11.5px; }

    .hc-mini-btn {
        border: 1px solid #EEF1F6; background: #fff; border-radius: 8px;
        padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
        color: #14142B; text-decoration: none; display: inline-block;
    }
    .hc-mini-btn:hover { background: #F6F7FB; }
    .hc-mini-btn.danger { color: #E14B4B; border-color: #F5B5B5; }
    .hc-mini-btn.danger:hover { background: #FCEAEA; }
    .hc-mini-btn.warn { color: #C77800; border-color: #F5C77E; }
    .hc-mini-btn.warn:hover { background: #FFF6E9; }
    .hc-inactive-tag { font-size: 10px; font-weight: 800; color: #E14B4B;
        background: #FCEAEA; padding: 2px 8px; border-radius: 999px; }

    /* ---- Adjust points floating modal (hca-) ---- */
    .hca-backdrop {
        position: fixed; inset: 0; z-index: 200;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        background: rgba(20, 20, 43, 0.45);
        opacity: 0; visibility: hidden; pointer-events: none;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .hca-backdrop.open { opacity: 1; visibility: visible; pointer-events: auto; }
    .hca-card {
        background: #fff; border-radius: 16px; max-width: 400px; width: 100%;
        padding: 24px; box-shadow: 0 20px 50px rgba(20,20,43,0.25);
        transform: translateY(18px); transition: transform 0.25s ease;
    }
    .hca-backdrop.open .hca-card { transform: translateY(0); }
    .hca-card h3 { margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #14142B; }
    .hca-member { margin: 0 0 16px; font-size: 12.5px; color: #8B93A6; }
    .hca-field { margin-bottom: 12px; }
    .hca-field label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color: #14142B; }
    .hca-field input, .hca-field select {
        width: 100%; box-sizing: border-box; padding: 10px 12px;
        border: 1px solid #DADEE6; border-radius: 9px;
        font-size: 13px; font-family: inherit; outline: none;
    }
    .hca-field input:focus, .hca-field select:focus { border-color: #eda423; }
    .hca-hint { font-size: 11px; color: #8B93A6; margin: 4px 0 0; }
    .hca-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
    .hca-btn { border: none; border-radius: 9px; padding: 9px 16px;
        font-size: 13px; font-weight: 600; cursor: pointer; }
    .hca-cancel { background: #F6F7FB; color: #14142B; }
    .hca-cancel:hover { background: #EEF1F6; }
    .hca-confirm { background: #eda423; color: #fff; }
    .hca-confirm:hover { background: #d99218; }
</style>
CSS
); ?>

<div class="page-heading">
    <h1>Hive Club</h1>
    <p>Members, point adjustments (fully audited), membership purchases, and the rewards catalog.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<!-- ============ STATS ============ -->
<div class="hc-stats">
    <div class="hc-stat">
        <span>Total Members</span>
        <strong><?= number_format($totalMembers) ?></strong>
        <small>everyone auto-provisioned</small>
    </div>
    <div class="hc-stat">
        <span>Active Plans</span>
        <strong><?= number_format($activeMembers) ?></strong>
        <small>discounts currently live</small>
    </div>
    <div class="hc-stat">
        <span>Points in Circulation</span>
        <strong><?= number_format($pointsInCirculation) ?></strong>
        <small>spendable balances, all members</small>
    </div>
    <div class="hc-stat">
        <span>Wallet Credited</span>
        <strong>&#8369;<?= number_format($walletCredited, 2) ?></strong>
        <small>via reward redemptions</small>
    </div>
</div>

<!-- ============ MEMBERS ============ -->
<div class="panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <h2>Members (<?= count($members) ?> shown)</h2>
        <form method="get" style="display:flex; gap:8px; align-items:center;">
            <div class="search-box" style="max-width:280px;">
                <?= icon('search') ?>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name, email, member ID...">
            </div>
            <button class="view-all" type="submit">Search</button>
        </form>
    </div>

    <?php if (empty($members)): ?>
        <?php emptyState($q !== '' ? 'No members match your search.' : 'No Hive Club members yet — rows appear automatically as users interact.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Member ID</th>
                        <th>Tier</th>
                        <th>Lifetime</th>
                        <th>Spendable</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m):
                    $exp = $m['membership_expires_at'];
                    $isActive = $m['membership_status'] === 'active'
                        && ($exp === null || strtotime($exp) > time());
                ?>
                    <tr>
                        <td>
                            <div class="people-info">
                                <span class="people-name"><?= h($m['name']) ?></span>
                                <span class="people-sub"><?= h($m['email']) ?></span>
                            </div>
                        </td>
                        <td><span class="people-sub"><?= h($m['member_id']) ?></span></td>
                        <td><span class="hc-tier-pill hc-tier-<?= h($m['tier']) ?>"><?= h($m['tier']) ?></span></td>
                        <td><strong><?= number_format((int) $m['lifetime_points']) ?></strong></td>
                        <td><strong><?= number_format((int) $m['redeemable_points']) ?></strong></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge badge-approved">Active</span>
                            <?php elseif ($m['membership_status'] === 'expired'): ?>
                                <span class="badge badge-rejected">Expired</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $exp !== null
                                ? h(date('M j, Y', strtotime($exp)))
                                : '<span class="people-sub">Never</span>' ?>
                        </td>
                        <td>
                            <button
                                type="button"
                                class="hc-adjust-btn js-adjust-btn"
                                data-user-id="<?= (int) $m['user_id'] ?>"
                                data-user-name="<?= h($m['name'], ENT_QUOTES) ?>"
                                data-lifetime="<?= (int) $m['lifetime_points'] ?>"
                                data-redeemable="<?= (int) $m['redeemable_points'] ?>"
                            >Adjust Points</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============ TRANSACTIONS ============ -->
<div class="panel" style="margin-bottom:18px;">
    <div class="panel-header"><h2>Membership Purchases (recent 25)</h2></div>

    <?php if (empty($transactions)): ?>
        <?php emptyState('No membership purchases yet.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead>
                    <tr><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Paid On</th><th>Valid Until</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>
                            <div class="people-info">
                                <span class="people-name"><?= h($t['name']) ?></span>
                                <span class="people-sub"><?= h($t['email']) ?></span>
                            </div>
                        </td>
                        <td style="text-transform:capitalize; font-weight:600;"><?= h($t['plan'] ?: '—') ?></td>
                        <td><strong>&#8369;<?= h(number_format((float) $t['amount'], 2)) ?></strong></td>
                        <td><?= h(strtoupper((string) $t['payment_method'])) ?></td>
                        <td><?= h(date('M j, Y', strtotime($t['purchased_at']))) ?></td>
                        <td><?= $t['expires_at'] ? h(date('M j, Y', strtotime($t['expires_at']))) : '—' ?></td>
                        <td><span class="badge <?= statusBadgeClass(ucfirst((string) $t['payment_status'])) ?>"><?= h(ucfirst((string) $t['payment_status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============ REWARDS ============ -->
<div class="panel" id="rewards">
    <div class="panel-header"><h2>Rewards Catalog</h2></div>

    <?php foreach ($rewards as $rw): ?>
        <div class="hc-rw-row">
            <div class="hc-rw-name">
                <strong>
                    <?= h($rw['name']) ?>
                    <?php if (!$rw['is_active']): ?><span class="hc-inactive-tag">INACTIVE</span><?php endif; ?>
                </strong>
                <span><?= h($rw['description']) ?></span>
            </div>

            <div class="hc-rw-figures">
                <strong><?= number_format((int) $rw['cost_points']) ?> pts</strong>
                <div>&#8369;<?= number_format((float) $rw['reward_value'], 2) ?> credit</div>
                <div><?= (int) $rw['claim_count'] ?> claim<?= (int) $rw['claim_count'] === 1 ? '' : 's' ?></div>
            </div>

            <div style="display:flex; gap:8px;">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="toggle_reward">
                    <input type="hidden" name="reward_id" value="<?= (int) $rw['id'] ?>">
                    <button type="submit" class="hc-mini-btn <?= $rw['is_active'] ? 'warn' : '' ?>">
                        <?= $rw['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('Delete this reward? Rewards with claims cannot be deleted.');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="delete_reward">
                    <input type="hidden" name="reward_id" value="<?= (int) $rw['id'] ?>">
                    <button type="submit" class="hc-mini-btn danger">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Add reward -->
    <div class="detail-section" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
        <h3 style="margin:0 0 12px; font-size:14px; font-weight:700; color:#14142B;">Add a Reward</h3>
        <form method="post" style="display:grid; grid-template-columns:1.4fr 1fr 1fr 1fr; gap:12px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="add_reward">
            <div>
                <label style="display:block; font-size:11px; font-weight:700; color:#8B93A6; margin-bottom:4px;">NAME</label>
                <input type="text" name="name" required maxlength="120" placeholder="P100 Wallet Credit"
                       style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #DADEE6; border-radius:9px; font-size:13px;">
            </div>
            <div>
                <label style="display:block; font-size:11px; font-weight:700; color:#8B93A6; margin-bottom:4px;">COST (POINTS)</label>
                <input type="number" name="cost_points" required min="1" placeholder="200"
                       style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #DADEE6; border-radius:9px; font-size:13px;">
            </div>
            <div>
                <label style="display:block; font-size:11px; font-weight:700; color:#8B93A6; margin-bottom:4px;">CREDIT VALUE (&#8369;)</label>
                <input type="number" name="reward_value" required min="0" step="0.01" placeholder="100.00"
                       style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #DADEE6; border-radius:9px; font-size:13px;">
            </div>
            <button type="submit" class="hc-mini-btn" style="background:#eda423; color:#fff; border-color:#eda423; height:38px;">
                Add Reward
            </button>
            <div style="grid-column:1 / -1;">
                <label style="display:block; font-size:11px; font-weight:700; color:#8B93A6; margin-bottom:4px;">DESCRIPTION</label>
                <input type="text" name="description" maxlength="255" placeholder="Credited instantly to the member's RoomHive wallet."
                       style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #DADEE6; border-radius:9px; font-size:13px;">
            </div>
        </form>
    </div>
</div>

<!-- ============ ADJUST POINTS MODAL ============ -->
<div class="hca-backdrop" id="hcaBackdrop" aria-hidden="true">
    <div class="hca-card" role="dialog" aria-modal="true">
        <h3>Adjust Points</h3>
        <p class="hca-member" id="hcaMember"></p>

        <form method="post" id="hcaForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="adjust_points">
            <input type="hidden" name="user_id" id="hcaUserId" value="">
            <input type="hidden" name="ret_q" value="<?= h($q) ?>">

            <div class="hca-field">
                <label for="hcaBucket">Bucket</label>
                <select name="bucket" id="hcaBucket" required>
                    <option value="redeemable">Spendable (redeemable_points)</option>
                    <option value="lifetime">Lifetime (tier progress)</option>
                </select>
            </div>

            <div class="hca-field">
                <label for="hcaPoints">Points (use a minus sign to deduct)</label>
                <input type="number" name="points" id="hcaPoints" required placeholder="e.g. 500 or -200">
                <p class="hca-hint" id="hcaHint"></p>
            </div>

            <div class="hca-field">
                <label for="hcaReason">Reason (shown to the member)</label>
                <input type="text" name="reason" id="hcaReason" required maxlength="200" placeholder="e.g. Goodwill credit for booking issue #123">
            </div>

            <div class="hca-actions">
                <button type="button" class="hca-btn hca-cancel" id="hcaCancel">Cancel</button>
                <button type="submit" class="hca-btn hca-confirm">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    "use strict";

    var backdrop = document.getElementById('hcaBackdrop');
    if (!backdrop) return;

    var memberEl = document.getElementById('hcaMember');
    var userIdEl = document.getElementById('hcaUserId');
    var bucketEl = document.getElementById('hcaBucket');
    var pointsEl = document.getElementById('hcaPoints');
    var hintEl   = document.getElementById('hcaHint');
    var cancelEl = document.getElementById('hcaCancel');

    var currentLifetime = 0;
    var currentRedeemable = 0;

    document.querySelectorAll('.js-adjust-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.getAttribute('data-user-name');
            currentLifetime = parseInt(btn.getAttribute('data-lifetime'), 10) || 0;
            currentRedeemable = parseInt(btn.getAttribute('data-redeemable'), 10) || 0;

            userIdEl.value = btn.getAttribute('data-user-id');
            memberEl.textContent = name + ' — lifetime '
                + currentLifetime.toLocaleString() + ' pts, spendable '
                + currentRedeemable.toLocaleString() + ' pts';

            hintEl.textContent = '';
            backdrop.classList.add('open');
        });
    });

    function updateHint() {
        var v = parseInt(pointsEl.value, 10) || 0;
        var bucket = bucketEl.value;
        var current = bucket === 'lifetime' ? currentLifetime : currentRedeemable;
        var projected = current + v;

        if (v < 0 && projected < 0) {
            hintEl.textContent = 'Blocked server-side: would go below zero (current ' + current.toLocaleString() + ').';
            hintEl.style.color = '#E14B4B';
        } else {
            hintEl.textContent = 'Projected new balance: ' + Math.max(0, projected).toLocaleString() + ' pts'
                + (bucket === 'lifetime' ? ' — tier recalculates automatically.' : '');
            hintEl.style.color = '#8B93A6';
        }
    }
    pointsEl.addEventListener('input', updateHint);
    bucketEl.addEventListener('change', updateHint);

    function close() { backdrop.classList.remove('open'); }
    cancelEl.addEventListener('click', close);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
</script>

<?php admin_page_end(); ?>