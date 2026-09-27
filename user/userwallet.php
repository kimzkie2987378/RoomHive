<?php
/* =========================================================
   ROOMHIVE — MY WALLET
   userwallet.php

   WALLET SYSTEM:
   - Balance lives on users.wallet_balance (the same column
     Hive Club rewards already credit).
   - Every money movement is logged in wallet_transactions
     (self-healing table): topup / spend / refund / reward,
     with balance_after for a full audit trail.
   - GCASH TOP-UP: amount -> pending transaction -> GCash
     checkout (wallet-gcash.php, sandbox) -> paid -> credited.
     Real GCash later = replace wallet-gcash.php with a
     PayMongo/Xendit source + webhook; everything else stays.
   - Top-up limits: ₱50 min, ₱50,000 max per transaction.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* AUTH GUARD */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* USER DATA */
 $stmt = $pdo->prepare(
    "SELECT id, name, email, phone, avatar_path, is_host, wallet_balance, created_at
     FROM users WHERE id = :id LIMIT 1"
);
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);

 $walletBalance = (float) ($dbUser['wallet_balance'] ?? 0);

/* =========================================================
   SELF-HEAL — wallet ledger table + wallet_balance column
========================================================= */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reference VARCHAR(40) NOT NULL UNIQUE,
            type ENUM('topup','spend','refund','reward') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
            description VARCHAR(240) NOT NULL DEFAULT '',
            payment_method VARCHAR(30) DEFAULT NULL,
            payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'paid',
            gcash_reference VARCHAR(60) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wt_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log('wallet: table self-heal failed: ' . $e->getMessage());
}

try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet_balance'");
    if ($colStmt->fetch() === false) {
        $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    error_log('wallet: column self-heal failed: ' . $e->getMessage());
}

/* =========================================================
   UNREAD BELL COUNT
========================================================= */
 $ncStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
 );
 $ncStmt->execute(['u' => $_SESSION['user_id']]);
 $notification_count = (int) $ncStmt->fetchColumn();

/* =========================================================
   TOP-UP INITIATION
   Creates a PENDING transaction, then redirects to the
   GCash checkout (wallet-gcash.php) which flips it to paid.
========================================================= */
 $topupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup') {

    if (!csrf_verify()) {
        $topupError = 'Your session expired. Please try again.';
    } else {
        $amount = round((float) str_replace(',', '', $_POST['amount'] ?? 0), 2);

        if ($amount < 50) {
            $topupError = 'Minimum top-up is ₱50.00.';
        } elseif ($amount > 50000) {
            $topupError = 'Maximum top-up is ₱50,000.00 per transaction.';
        } else {
            try {
                $reference = 'WH' . date('ymd') . '-'
                    . strtoupper(bin2hex(random_bytes(4)));

                $ins = $pdo->prepare(
                    "INSERT INTO wallet_transactions
                        (user_id, reference, type, amount, balance_after,
                         description, payment_method, payment_status)
                     VALUES
                        (:u, :ref, 'topup', :amt, 0,
                         'Wallet top-up via GCash', 'gcash', 'pending')"
                );
                $ins->execute([
                    ':u'   => $_SESSION['user_id'],
                    ':ref' => $reference,
                    ':amt' => $amount,
                ]);

                header("Location: /webprogg/user/wallet-gcash.php?ref=" . urlencode($reference));
                exit;

            } catch (PDOException $e) {
                error_log('wallet topup create failed: ' . $e->getMessage());
                $topupError = 'Could not start the top-up. Please try again.';
            }
        }
    }
}

/* =========================================================
   WALLET HISTORY
========================================================= */
 $txns = [];
try {
    $tStmt = $pdo->prepare(
        "SELECT id, reference, type, amount, balance_after, description,
                payment_method, payment_status, gcash_reference, created_at
         FROM wallet_transactions
         WHERE user_id = :u
         ORDER BY created_at DESC, id DESC
         LIMIT 50"
    );
    $tStmt->execute(['u' => $_SESSION['user_id']]);
    $txns = $tStmt->fetchAll();
} catch (PDOException $e) {
    $txns = [];
}

/* Stats */
 $totalTopups = 0.0;
 $totalSpent  = 0.0;
 $totalRefunds = 0.0;
 $totalRewards = 0.0;

foreach ($txns as $t) {
    if ($t['payment_status'] !== 'paid') { continue; }
    $amt = (float) $t['amount'];
    if ($t['type'] === 'topup')       { $totalTopups  += $amt; }
    elseif ($t['type'] === 'spend')   { $totalSpent   += $amt; }
    elseif ($t['type'] === 'refund')  { $totalRefunds += $amt; }
    elseif ($t['type'] === 'reward')  { $totalRewards += $amt; }
}

/* Human labels for each type */
 $typeLabels = [
    'topup'  => ['Wallet Top-Up',   'wt-type-topup'],
    'spend'  => ['Booking Payment', 'wt-type-spend'],
    'refund' => ['Refund',          'wt-type-refund'],
    'reward' => ['Hive Club Reward','wt-type-reward'],
];

 $activeSidebar = 'wallet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Wallet — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>

<style>
    /* =====================================================
       WALLET — self-contained styles
    ====================================================== */
    .up-dashboard {
        margin-top: 110px;
    }

    .wl-balance-card {
        position: relative;
        overflow: hidden;

        padding: 34px 36px;
        margin-bottom: 20px;

        background:
            radial-gradient(600px 260px at 90% -30%, rgba(237, 164, 35, 0.22), transparent 60%),
            linear-gradient(120deg, #1c2a38 0%, #24384d 60%, #1c2a38 100%);

        border-radius: 20px;
        color: #ffffff;

        box-shadow: 0 20px 44px rgba(28, 42, 56, 0.28);
    }

    .wl-balance-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23f6c04e' fill-opacity='0.06' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
        background-size: 28px 49px;
        pointer-events: none;
    }

    .wl-balance-label {
        position: relative;
        z-index: 1;

        color: rgba(255, 255, 255, 0.6);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .wl-balance-amount {
        position: relative;
        z-index: 1;

        margin-top: 8px;

        font-size: clamp(32px, 4.5vw, 46px);
        font-weight: 800;
        letter-spacing: -1px;
        line-height: 1;
    }

    .wl-balance-amount .wl-peso {
        color: #f6c04e;
        margin-right: 6px;
    }

    .wl-balance-meta {
        position: relative;
        z-index: 1;

        margin-top: 12px;

        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;

        color: rgba(255, 255, 255, 0.65);
        font-size: 12px;
    }

    .wl-balance-meta strong {
        color: #ffffff;
    }

    /* Top-up card */
    .wl-topup-presets {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;

        margin-bottom: 14px;
    }

    .wl-preset {
        padding: 8px 14px;

        background: #ffffff;
        border: 1.5px solid #e3e7ec;
        border-radius: 10px;

        color: var(--up-navy, #1c2a38);

        font-family: inherit;
        font-size: 12.5px;
        font-weight: 700;

        cursor: pointer;

        transition:
            border-color 0.15s ease,
            background 0.15s ease,
            color 0.15s ease;
    }

    .wl-preset:hover {
        border-color: #eda423;
        background: #fff8ec;
        color: #b07708;
    }

    .wl-preset.active {
        background: linear-gradient(135deg, #f6b93b, #eda423);
        border-color: transparent;
        color: #1c2a38;
    }

    .wl-amount-input {
        width: 100%;
        padding: 12px 14px;

        background: #fbfcfd;
        border: 1.5px solid #e3e7ec;
        border-radius: 10px;

        outline: none;

        color: var(--up-navy, #1c2a38);

        font-family: "Poppins", sans-serif;
        font-size: 16px;
        font-weight: 700;

        box-sizing: border-box;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .wl-amount-input:focus {
        background: #ffffff;
        border-color: #eda423;
        box-shadow: 0 0 0 4px rgba(237, 164, 35, 0.15);
    }

    .wl-gcash-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;

        width: 100%;
        height: 50px;
        margin-top: 14px;

        background: linear-gradient(135deg, #0075f6, #0057d4);
        border: none;
        border-radius: 12px;

        color: #ffffff;

        font-family: inherit;
        font-size: 13.5px;
        font-weight: 800;
        letter-spacing: 0.5px;

        cursor: pointer;

        box-shadow: 0 8px 20px rgba(0, 87, 212, 0.35);

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .wl-gcash-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(0, 87, 212, 0.45);
    }

    .wl-gcash-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .wl-gcash-logo {
        height: 20px;
        width: auto;
        border-radius: 4px;
        background: #ffffff;
        padding: 1px 5px;
    }

    .wl-limits-note {
        margin: 10px 0 0;

        color: var(--up-text-muted, #6b7684);
        font-size: 11.5px;
        text-align: center;
    }

    /* Stats row */
    .wl-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;

        margin-bottom: 20px;
    }

    @media (max-width: 900px) {
        .wl-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 480px) {
        .wl-stats { grid-template-columns: 1fr; }
    }

    .wl-stat {
        background: #ffffff;
        border: 1px solid var(--up-border, rgba(28, 42, 56, 0.08));
        border-radius: 14px;
        padding: 16px 18px;

        box-shadow: 0 6px 18px rgba(28, 42, 56, 0.05);
    }

    .wl-stat span {
        display: block;

        color: var(--up-text-muted, #6b7684);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
    }

    .wl-stat strong {
        display: block;
        margin-top: 5px;

        color: var(--up-navy, #1c2a38);
        font-size: 19px;
        font-weight: 800;
    }

    /* History */
    .wl-txn {
        display: flex;
        align-items: center;
        gap: 14px;

        padding: 13px 4px;
        border-bottom: 1px solid var(--up-border, rgba(28, 42, 56, 0.08));
    }

    .wl-txn:last-child {
        border-bottom: none;
    }

    .wl-txn-ico {
        width: 40px;
        height: 40px;
        flex-shrink: 0;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 12px;

        font-size: 17px;
    }

    .wt-type-topup  .wl-txn-ico { background: #E8F8F1; color: #1e7a3d; }
    .wt-type-spend  .wl-txn-ico { background: #FFF1DC; color: #b07708; }
    .wt-type-refund .wl-txn-ico { background: #E9F0FA; color: #33517E; }
    .wt-type-reward .wl-txn-ico { background: #F3E8FA; color: #7a4bb0; }

    .wl-txn-info {
        flex: 1;
        min-width: 0;
    }

    .wl-txn-info strong {
        display: block;

        color: var(--up-navy, #1c2a38);
        font-size: 13.5px;
        font-weight: 700;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wl-txn-info span {
        display: block;
        margin-top: 2px;

        color: var(--up-text-muted, #6b7684);
        font-size: 11.5px;
    }

    .wl-txn-amt {
        text-align: right;
        flex-shrink: 0;
    }

    .wl-txn-amt strong {
        display: block;

        font-size: 14.5px;
        font-weight: 800;
    }

    .wl-amt-in  { color: #1e7a3d; }
    .wl-amt-out { color: #a1332e; }

    .wl-txn-amt span {
        display: block;
        margin-top: 2px;

        color: var(--up-text-muted, #6b7684);
        font-size: 10.5px;
    }

    .wl-status-pill {
        display: inline-block;
        margin-top: 3px;

        padding: 2px 8px;

        border-radius: 999px;

        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: 0.4px;
    }

    .wl-st-paid    { background: #E8F8F1; color: #1FA971; }
    .wl-st-pending { background: #FFF1DC; color: #b07708; }
    .wl-st-failed  { background: #FCEAEA; color: #a1332e; }

    .wl-empty {
        text-align: center;
        padding: 30px 12px;
        color: var(--up-text-muted, #6b7684);
    }

    .wl-empty strong {
        display: block;
        margin-bottom: 4px;

        color: var(--up-navy, #1c2a38);
        font-size: 14px;
        font-weight: 700;
    }

    /* Sandbox notice */
    .wl-sandbox-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;

        margin-bottom: 20px;
        padding: 12px 14px;

        background: #F3E8FA;
        border: 1px dashed rgba(122, 75, 176, 0.4);
        border-radius: 12px;

        color: #5c3a85;
        font-size: 12px;
        line-height: 1.55;
    }

    body[data-theme="dark"] .wl-topup-card,
    html[data-theme-preview="1"] .wl-topup-card,
    body[data-theme="dark"] .wl-history-card,
    html[data-theme-preview="1"] .wl-history-card {
        background: #1a222b;
    }

    body[data-theme="dark"] .wl-preset,
    html[data-theme-preview="1"] .wl-preset {
        background: #1a222b;
        border-color: rgba(232, 236, 241, 0.14);
        color: #e8ecf1;
    }

    body[data-theme="dark"] .wl-stat,
    html[data-theme-preview="1"] .wl-stat {
        background: #1a222b;
    }

    body[data-theme="dark"] .wl-txn,
    html[data-theme-preview="1"] .wl-txn {
        border-color: rgba(232, 236, 241, 0.1);
    }
</style>
</head>
<body>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if (isset($_GET['topup']) && $_GET['topup'] === 'success'): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; Top-up successful! Your wallet has been credited.</p>
      </section>
    <?php endif; ?>

    <?php if (isset($_GET['topup']) && $_GET['topup'] === 'cancelled'): ?>
      <section class="up-alert up-alert-error">
        <p>The GCash top-up was not completed. No money was taken.</p>
      </section>
    <?php endif; ?>

    <?php if ($topupError !== ''): ?>
      <section class="up-alert up-alert-error">
        <p><?php echo htmlspecialchars($topupError); ?></p>
      </section>
    <?php endif; ?>

    <!-- BALANCE CARD -->
    <div class="wl-balance-card up-reveal">

        <span class="wl-balance-label">RoomHive Wallet Balance</span>

        <div class="wl-balance-amount">
            <span class="wl-peso">&#8369;</span><?php echo number_format($walletBalance, 2); ?>
        </div>

        <div class="wl-balance-meta">
            <span>Top-ups: <strong>&#8369;<?php echo number_format($totalTopups, 2); ?></strong></span>
            <span>Spent: <strong>&#8369;<?php echo number_format($totalSpent, 2); ?></strong></span>
            <span>Refunds: <strong>&#8369;<?php echo number_format($totalRefunds, 2); ?></strong></span>
            <span>Rewards: <strong>&#8369;<?php echo number_format($totalRewards, 2); ?></strong></span>
        </div>

    </div>

    <div class="up-two-col" style="grid-template-columns: 1fr 1.6fr; align-items: start;">

        <!-- TOP-UP CARD -->
        <div class="up-card up-reveal wl-topup-card" style="--i: 1;">

            <div class="up-card-header">
                <h3>Add Money</h3>
            </div>

            <form method="POST" action="/webprogg/user/userwallet.php" id="wlTopupForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="topup">

                <div class="wl-topup-presets">
                    <?php foreach ([100, 300, 500, 1000, 2000, 5000] as $preset): ?>
                        <button type="button" class="wl-preset" data-amount="<?php echo $preset; ?>">
                            &#8369;<?php echo number_format($preset); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <input
                    type="number"
                    name="amount"
                    id="wlAmount"
                    class="wl-amount-input"
                    min="50"
                    max="50000"
                    step="0.01"
                    placeholder="Enter amount (₱50 – ₱50,000)"
                    required
                >

                <button type="submit" class="wl-gcash-btn">
                    <img
                        src="/webprogg/images/GooglePlay.jpg"
                        alt=""
                        class="wl-gcash-logo"
                        style="display:none;"
                    >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px;">
                        <rect x="5" y="2.5" width="14" height="19" rx="2.5"/>
                        <path d="M9.5 18.5h5"/>
                    </svg>
                    TOP UP WITH GCASH
                </button>

                <p class="wl-limits-note">
                    Minimum &#8369;50 &middot; Maximum &#8369;50,000 per transaction
                </p>

            </form>

        </div>

        <!-- HISTORY CARD -->
        <div class="up-card up-reveal wl-history-card" style="--i: 2;">

            <div class="up-card-header">
                <h3>Transaction History</h3>
            </div>

            <?php if (empty($txns)): ?>

                <div class="wl-empty">
                    <strong>No transactions yet</strong>
                    Top up your wallet to get started.
                </div>

            <?php else: ?>

                <?php foreach ($txns as $t):
                    $label = $typeLabels[$t['type']] ?? [ucfirst($t['type']), ''];
                    $isIn  = in_array($t['type'], ['topup', 'refund', 'reward'], true);
                    $icons = [
                        'topup'  => '&#11014;',
                        'spend'  => '&#127976;',
                        'refund' => '&#8634;',
                        'reward' => '&#127873;',
                    ];
                ?>

                <div class="wl-txn <?php echo htmlspecialchars($label[1]); ?>">

                    <span class="wl-txn-ico"><?php echo $icons[$t['type']] ?? '&#8369;'; ?></span>

                    <div class="wl-txn-info">
                        <strong><?php echo htmlspecialchars($label[0]); ?></strong>
                        <span>
                            <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($t['created_at']))); ?>
                            &middot; Ref <?php echo htmlspecialchars($t['reference']); ?>
                            <?php if (!empty($t['gcash_reference'])): ?>
                                &middot; GCash <?php echo htmlspecialchars($t['gcash_reference']); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($t['payment_status'] !== 'paid'): ?>
                            <span class="wl-status-pill wl-st-<?php echo htmlspecialchars($t['payment_status']); ?>">
                                <?php echo htmlspecialchars(strtoupper($t['payment_status'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="wl-txn-amt">
                        <strong class="<?php echo $isIn ? 'wl-amt-in' : 'wl-amt-out'; ?>">
                            <?php echo $isIn ? '+' : '&minus;'; ?>&#8369;<?php echo number_format((float) $t['amount'], 2); ?>
                        </strong>
                        <span>Bal: &#8369;<?php echo number_format((float) $t['balance_after'], 2); ?></span>
                    </div>

                </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

  </div>
</main>

<script>
(function () {
    "use strict";

    /* ---- Reveal ---- */
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.classList.add("in-view");
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    /* ---- Preset chips fill the amount input ---- */
    var amountInput = document.getElementById("wlAmount");
    document.querySelectorAll(".wl-preset").forEach(function (chip) {
        chip.addEventListener("click", function () {
            document.querySelectorAll(".wl-preset").forEach(function (c) {
                c.classList.remove("active");
            });
            chip.classList.add("active");
            if (amountInput) {
                amountInput.value = chip.getAttribute("data-amount");
                amountInput.focus();
            }
        });
    });

    /* ---- SIDEBAR ACTIVE STATE (fallback — the 'wallet' key
       may not be in sidebar.php's list yet) ---- */
    var here = window.location.pathname.toLowerCase();
    document.querySelectorAll(".up-side-link").forEach(function (link) {
        try {
            if (new URL(link.href, window.location.origin).pathname.toLowerCase() === here) {
                link.classList.add("active");
            }
        } catch (e) { /* ignore malformed hrefs */ }
    });
})();
</script>

</body>
</html>