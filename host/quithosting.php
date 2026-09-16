<?php
/* =========================================================
   ROOMHIVE — HOST
   quithosting.php

   Lets a host stop hosting: is_host flips back to 0, all
   their listings are unlisted, and they land on the guest
   dashboard. Account, bookings history, and reviews are kept.

   Loads ONLY host_init.php (same as every other host page),
   so it never collides with functions.php.
========================================================= */

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_init.php';

/* host_init.php now provides: session, $pdo, auth guard,
   $host (including 'id'), $navAvatar, $notification_count,
   $pending_tenants_count, and h(). */

/* ---- CSRF (guarded, prefixed — safe from collisions) ---- */
if (!function_exists('qh_csrf_token')) {
    function qh_csrf_token() {
        if (empty($_SESSION['qh_csrf'])) {
            $_SESSION['qh_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['qh_csrf'];
    }
    function qh_csrf_field() {
        return '<input type="hidden" name="qh_csrf" value="' . qh_csrf_token() . '">';
    }
    function qh_csrf_ok($token) {
        return is_string($token)
            && isset($_SESSION['qh_csrf'])
            && hash_equals($_SESSION['qh_csrf'], $token);
    }
}

 $hostId     = (int) ($host['id'] ?? $_SESSION['user_id']);
 $activePage = 'quithosting';

 $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!qh_csrf_ok($_POST['qh_csrf'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $password    = $_POST['quit_password'] ?? '';
    $confirmWord = strtoupper(trim($_POST['confirm_word'] ?? ''));

    /* Verify password against the users table */
    if (empty($errors)) {
        $pwStmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
        $pwStmt->execute(['id' => $hostId]);
        $hash = $pwStmt->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            $errors[] = 'Password is incorrect.';
        }
    }

    if ($confirmWord !== 'QUIT') {
        $errors[] = 'Type QUIT to confirm.';
    }

    if (empty($errors)) {
        try {

            /* 1. Flip the account back to a regular user */
            $pdo->prepare("UPDATE users SET is_host = 0 WHERE id = :id")
                ->execute(['id' => $hostId]);

            /* 2. Unlist all their listings so tenants can't book them.
                  Delete this block to leave listings untouched. */
            $pdo->prepare(
                "UPDATE listings SET status = 'unlisted' WHERE host_id = :id"
            )->execute(['id' => $hostId]);

            /* 3. Clear host session flag, then send them to the
                  guest dashboard — still logged in. */
            unset($_SESSION['is_host']);

            header("Location: /webprogg/user/userprofile.php?quit_hosting=1");
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Something went wrong. Please try again.';
            error_log('quithosting failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quit Hosting — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<!-- If your host pages use an extra stylesheet, add it here,
     e.g. <link rel="stylesheet" href="/webprogg/assets/host.css"> -->
<style>
    /* Self-contained so this page renders regardless of host CSS */
    .qh-layout {
        display: flex;
        align-items: flex-start;
        gap: 24px;
        max-width: 1200px;
        margin: 130px auto 60px;
        padding: 0 24px;
    }
    .qh-main { flex: 1; min-width: 0; }
    .qh-card {
        background: #ffffff;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 16px;
        box-shadow: 0 14px 30px rgba(28, 42, 56, 0.10);
        padding: 28px;
        max-width: 640px;
    }
    .qh-card h1 {
        margin: 0 0 10px;
        color: #a1332e;
        font-size: 22px;
        font-weight: 800;
    }
    .qh-card > p {
        margin: 0 0 12px;
        font-size: 13.5px;
        line-height: 1.65;
        color: #5d6875;
    }
    .qh-list {
        margin: 0 0 18px;
        padding-left: 18px;
        font-size: 13.5px;
        line-height: 1.8;
        color: #5d6875;
    }
    .qh-field { margin-bottom: 14px; }
    .qh-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 12.5px;
        font-weight: 700;
        color: #1c2a38;
    }
    .qh-field input {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid #d9dee4;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        box-sizing: border-box;
    }
    .qh-field input:focus {
        outline: none;
        border-color: #b07708;
        box-shadow: 0 0 0 3px rgba(176, 119, 8, 0.15);
    }
    .qh-error {
        background: #fdecea;
        border: 1px solid #f5c6c2;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13.5px;
    }
    .qh-error p { margin: 0 0 4px; color: #b3261e; }
    .qh-btn {
        width: 100%;
        background: #e0524d;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 12px;
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .qh-btn:hover { background: #c84642; }
    .qh-cancel {
        display: block;
        text-align: center;
        margin-top: 14px;
        font-size: 13px;
        font-weight: 600;
        color: #1c2a38;
        text-decoration: none;
    }
    .qh-cancel:hover { color: #b07708; }

    @media (max-width: 900px) {
        .qh-layout { flex-direction: column; margin-top: 115px; }
    }
</style>
</head>
<body>

<!-- ANIMATED SHARED HOST NAVBAR -->
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_navbar.php'; ?>

<div class="qh-layout">

    <!-- SHARED HOST SIDEBAR (with the Settings group + Quit Hosting link) -->
    <?php
    $sidebarFile = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/hostsidebar.php';
    if (is_file($sidebarFile)) {
        require $sidebarFile;
    }
    ?>

    <main class="qh-main">

        <div class="qh-card">

            <h1>Quit Hosting</h1>

            <p>
                This turns your RoomHive account back into a regular guest account.
                <strong>You keep your account, bookings history, and reviews</strong> —
                you just stop being a host.
            </p>

            <ul class="qh-list">
                <li>All of your listings will be unlisted immediately.</li>
                <li>Existing bookings stay on record for you and your guests.</li>
                <li>You can become a host again anytime from the Become a Host page.</li>
            </ul>

            <?php if (!empty($errors)): ?>
                <div class="qh-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/webprogg/host/quithosting.php"
                  onsubmit="return confirm('This will end your hosting on RoomHive and unlist all your listings. Continue?');">

                <?php echo qh_csrf_field(); ?>

                <div class="qh-field">
                    <label for="qhPw">Password</label>
                    <input type="password" id="qhPw" name="quit_password" required>
                </div>

                <div class="qh-field">
                    <label for="qhWord">Type QUIT to confirm</label>
                    <input type="text" id="qhWord" name="confirm_word" required>
                </div>

                <button type="submit" class="qh-btn">QUIT HOSTING</button>
            </form>

            <a href="/webprogg/host/hostprofile.php" class="qh-cancel">Cancel — keep hosting</a>

        </div>

    </main>

</div>

</body>
</html>