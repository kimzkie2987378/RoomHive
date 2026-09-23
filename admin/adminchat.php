<?php
/* =========================================================
   ROOMHIVE ADMIN — SUPPORT CHAT
   adminchat.php

   Messenger-style reply screen for the admin panel.
   - Reached from Admin > Messages > Contact Questions
     ("Open Chat") or the "Support Chats" tab.
   - Conversations are stored in the SAME conversations/messages
     tables the tenant inbox (usermessages.php) reads, with the
     system "RoomHive Admin" user as the other party (host_id).
   - So when the admin sends a reply here, it appears instantly
     in the user's Messages inbox as a chat from RoomHive Admin,
     and the user's own replies appear back here.
   - Guests (contact questions with no account) can't be
     chatted with — only emailed.

   Self-heals: creates the support user on first use.

   FIX: thread query now aliases u.email AS user_email —
   the header read $conv['user_email'] which didn't exist,
   causing "Undefined array key" on line 284.
========================================================= */

require_once __DIR__ . '/admin_init.php';

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

/* -----------------------------------------------------
   SYSTEM SUPPORT USER (the "Admin" chat partner)
   Lives in the users table so the tenant inbox JOIN works.
   Password is a random hash — nobody can log into it.
----------------------------------------------------- */
if (!function_exists('adm_support_user_id')) {
    function adm_support_user_id($pdo) {
        $st = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => 'support@roomhive.local']);
        $id = $st->fetchColumn();
        if ($id) { return (int) $id; }

        try {
            $ins = $pdo->prepare(
                "INSERT INTO users (name, email, password, is_host, status)
                 VALUES (:n, :e, :p, 0, 'active')"
            );
            $ins->execute([
                ':n' => 'RoomHive Admin',
                ':e' => 'support@roomhive.local',
                ':p' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('adminchat: could not create support user: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('adm_csrf_token')) {
    function adm_csrf_token() {
        if (empty($_SESSION['adm_csrf'])) {
            $_SESSION['adm_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['adm_csrf'];
    }
    function adm_csrf_ok($t) {
        return is_string($t) && isset($_SESSION['adm_csrf']) && hash_equals($_SESSION['adm_csrf'], $t);
    }
}

/* ---- Schema auto-detect (same as tenant inbox) ---- */
 $_msgCols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
 $MSG_TIME = in_array('sent_at', $_msgCols, true) ? 'sent_at' : 'created_at';

 $supportId = adm_support_user_id($pdo);

/* -----------------------------------------------------
   SEND (admin reply) — POST + PRG
----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c'], $_POST['body'])) {

    $convId = (int) $_POST['c'];
    $body   = trim($_POST['body']);

    if (!adm_csrf_ok($_POST['adm_csrf'] ?? '')) {
        header('Location: ?c=' . $convId . '&err=csrf'); exit;
    }
    if ($body === '') {
        header('Location: ?c=' . $convId . '&err=empty'); exit;
    }

    $own = $pdo->prepare(
        "SELECT id, user_id FROM conversations
         WHERE id = :id AND host_id = :h LIMIT 1"
    );
    $own->execute([':id' => $convId, ':h' => $supportId]);
    $conv = $own->fetch();

    if (!$conv) {
        header('Location: ?err=notfound'); exit;
    }

    try {
        $ins = $pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_id, body, $MSG_TIME)
             VALUES (:c, :s, :b, NOW())"
        );
        $ins->execute([':c' => $convId, ':s' => $supportId, ':b' => $body]);

        $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :id")
            ->execute([':id' => $convId]);

        /* Notify the user so their navbar bell updates */
        try {
            $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read)
                 VALUES (:u, :m, :l, 0)"
            )->execute([
                ':u' => (int) $conv['user_id'],
                ':m' => 'RoomHive Admin replied to your message',
                ':l' => '/webprogg/user/usermessages.php?conversation=' . $convId,
            ]);
        } catch (PDOException $e) { /* notification is best-effort */ }

    } catch (PDOException $e) {
        error_log('adminchat send failed: ' . $e->getMessage());
        header('Location: ?c=' . $convId . '&err=save'); exit;
    }

    header('Location: ?c=' . $convId . '&sent=1');
    exit;
}

/* -----------------------------------------------------
   ENTRY FROM A CONTACT QUESTION — get or create the chat
----------------------------------------------------- */
 $fromInquiry = (int) ($_GET['from_inquiry'] ?? 0);
 $entryError  = '';

if ($fromInquiry > 0) {
    $q = $pdo->prepare("SELECT id, user_id FROM contact_messages WHERE id = :id LIMIT 1");
    $q->execute([':id' => $fromInquiry]);
    $inq = $q->fetch();

    if (!$inq) {
        $entryError = 'That contact question no longer exists.';
    } elseif (empty($inq['user_id'])) {
        $entryError = 'This was a guest submission (no account), so chat is unavailable. Use "Reply by email" instead.';
    } elseif ($supportId === 0) {
        $entryError = 'Could not prepare the support account. Check the database error log.';
    } else {
        $find = $pdo->prepare(
            "SELECT id FROM conversations
             WHERE user_id = :u AND host_id = :h AND listing_id IS NULL
             ORDER BY id LIMIT 1"
        );
        $find->execute([':u' => (int) $inq['user_id'], ':h' => $supportId]);
        $convId = $find->fetchColumn();

        if (!$convId) {
            $pdo->prepare(
                "INSERT INTO conversations (user_id, host_id, listing_id, created_at, last_message_at)
                 VALUES (:u, :h, NULL, NOW(), NOW())"
            )->execute([':u' => (int) $inq['user_id'], ':h' => $supportId]);
            $convId = (int) $pdo->lastInsertId();
        }

        header('Location: ?c=' . (int) $convId);
        exit;
    }
}

/* -----------------------------------------------------
   LOAD THREAD
----------------------------------------------------- */
 $conv = null; $thread = [];
 $openId = (int) ($_GET['c'] ?? 0);

if ($openId > 0 && $supportId > 0) {
    $st = $pdo->prepare(
        /* FIX — u.email aliased AS user_email (was the
           "Undefined array key" warning on line 284) */
        "SELECT c.id, c.user_id, c.created_at, u.name AS user_name, u.email AS user_email
         FROM conversations c
         JOIN users u ON u.id = c.user_id
         WHERE c.id = :id AND c.host_id = :h LIMIT 1"
    );
    $st->execute([':id' => $openId, ':h' => $supportId]);
    $conv = $st->fetch();

    if ($conv) {
        $t = $pdo->prepare(
            "SELECT sender_id, body, $MSG_TIME AS msg_time
             FROM messages WHERE conversation_id = :c ORDER BY $MSG_TIME ASC"
        );
        $t->execute([':c' => $openId]);
        $thread = $t->fetchAll();

        /* messages from the user are now read */
        try {
            if (in_array('is_read', $_msgCols, true)) {
                $pdo->prepare(
                    "UPDATE messages SET is_read = 1
                     WHERE conversation_id = :c AND sender_id != :me AND is_read = 0"
                )->execute([':c' => $openId, ':me' => $supportId]);
            } elseif (in_array('read_at', $_msgCols, true)) {
                $pdo->prepare(
                    "UPDATE messages SET read_at = NOW()
                     WHERE conversation_id = :c AND sender_id != :me AND read_at IS NULL"
                )->execute([':c' => $openId, ':me' => $supportId]);
            }
        } catch (PDOException $e) { /* optional */ }
    }
}

 $err  = $_GET['err'] ?? '';
 $sent = isset($_GET['sent']);
?>
<?php admin_page_start('RoomHive Admin — Support Chat', 'Messages', <<<CSS
<style>
    .ac-wrap { max-width: 760px; margin: 0 auto; }
    .ac-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
    .ac-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px;
        border-bottom: 1px solid var(--border); background: #fff; }
    .ac-head img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #F0EEE6; }
    .ac-head strong { display: block; font-size: 14px; color: var(--text-dark); }
    .ac-head span { display: block; font-size: 11.5px; color: var(--text-muted); }
    .ac-back { font-size: 12px; font-weight: 700; color: #b07708; text-decoration: none; }
    .ac-back:hover { text-decoration: underline; }
    .ac-body { height: 420px; overflow-y: auto; padding: 18px; display: flex;
        flex-direction: column; gap: 10px; background: #FAFAF7; }
    .ac-row { display: flex; }
    .ac-row.me { justify-content: flex-end; }
    .ac-bub { max-width: 72%; padding: 10px 14px; border-radius: 14px;
        font-size: 13px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ac-bub b { display: block; font-size: 10.5px; opacity: .7; margin-bottom: 3px; }
    .ac-row.them .ac-bub { background: #F3F4F6; color: var(--text-dark); border-bottom-left-radius: 4px; }
    .ac-row.me .ac-bub { background: linear-gradient(135deg, #f6b93b, #eda423); color: #1c2a38;
        border-bottom-right-radius: 4px; }
    .ac-time { display: block; margin-top: 3px; font-size: 10px; opacity: .65; }
    .ac-composer { display: flex; gap: 10px; padding: 12px; border-top: 1px solid var(--border); background: #fff; }
    .ac-input { flex: 1; padding: 11px 14px; border: 1.5px solid #e3e7ec; border-radius: 10px;
        font-family: inherit; font-size: 13.5px; outline: none; }
    .ac-input:focus { border-color: #eda423; box-shadow: 0 0 0 3px rgba(237,164,35,.15); }
    .ac-send { padding: 0 22px; border: none; border-radius: 10px; cursor: pointer;
        background: linear-gradient(135deg, #f6b93b, #eda423); color: #1c2a38;
        font-family: inherit; font-size: 13px; font-weight: 800; }
    .ac-send:hover { transform: translateY(-1px); }
    .ac-flash { padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 12px; }
    .ac-ok  { background: #E8F8F1; color: #178A50; border: 1px solid rgba(23,138,80,.3); }
    .ac-err { background: #FDECEC; color: #A4302F; border: 1px solid #F3B9B9; }
    .ac-empty { text-align: center; padding: 70px 20px; color: var(--text-muted); font-size: 13.5px; }
    .ac-refresh { font-size: 11.5px; color: var(--text-muted); text-decoration: none; margin-left: auto; }
    .ac-refresh:hover { color: #b07708; }
</style>
CSS); ?>

<div class="page-heading">
    <h1>Support Chat</h1>
    <p>Your replies appear in the user's Messages inbox as a chat from <strong>RoomHive Admin</strong>.</p>
</div>

<div class="ac-wrap">

    <?php if ($entryError !== ''): ?>
        <div class="ac-flash ac-err"><?= h($entryError) ?></div>
        <a class="ac-back" href="adminmessages.php?tab=inquiries">&larr; Back to Messages</a>
    <?php elseif ($err === 'csrf' || $err === 'empty' || $err === 'save' || $err === 'notfound'): ?>
        <div class="ac-flash ac-err">
            <?= $err === 'empty' ? 'Message cannot be empty.' :
                ($err === 'csrf' ? 'Session expired — please send again.' :
                ($err === 'notfound' ? 'Conversation not found.' : 'Could not save the message. Please try again.')) ?>
        </div>
    <?php endif; ?>

    <?php if ($conv): ?>

        <?php if ($sent): ?>
            <div class="ac-flash ac-ok">Reply sent — the user has been notified.</div>
        <?php endif; ?>

        <div class="ac-card">
            <div class="ac-head">
                <img src="/webprogg/images/default-avatar.png" alt="">
                <div>
                    <strong><?= h($conv['user_name']) ?></strong>
                    <span><?= h($conv['user_email']) ?></span>
                </div>
                <a class="ac-refresh" href="?c=<?= (int) $conv['id'] ?>" title="Check for new replies">&#8635; Refresh</a>
            </div>

            <div class="ac-body" id="acBody">
                <?php if (empty($thread)): ?>
                    <div class="ac-empty">No messages yet — say hello. The user will see this in their inbox.</div>
                <?php endif; ?>
                <?php foreach ($thread as $m): ?>
                    <?php $mine = (int) $m['sender_id'] === $supportId; ?>
                    <div class="ac-row <?= $mine ? 'me' : 'them' ?>">
                        <div class="ac-bub">
                            <b><?= $mine ? 'You (RoomHive Admin)' : h($conv['user_name']) ?>
                               &middot; <?= h(date('M j, g:i A', strtotime($m['msg_time']))) ?></b>
                            <?= h($m['body']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="?c=<?= (int) $conv['id'] ?>" class="ac-composer">
                <input type="hidden" name="adm_csrf" value="<?= h(adm_csrf_token()) ?>">
                <input type="hidden" name="c" value="<?= (int) $conv['id'] ?>">
                <input type="text" name="body" class="ac-input" placeholder="Write a reply..." autocomplete="off" required>
                <button type="submit" class="ac-send">Send</button>
            </form>
        </div>

        <p style="margin-top:10px;">
            <a class="ac-back" href="adminmessages.php?tab=chats">&larr; All support chats</a>
        </p>

        <script>
            var b = document.getElementById('acBody');
            if (b) { b.scrollTop = b.scrollHeight; }
        </script>

    <?php elseif ($entryError === '' && $openId > 0): ?>

        <div class="ac-card"><div class="ac-empty">Conversation not found.</div></div>

    <?php elseif ($entryError === '' && $err === '' && $fromInquiry === 0): ?>

        <div class="ac-card"><div class="ac-empty">
            Open a chat from <strong>Messages &rarr; Contact Questions &rarr; Open Chat</strong>,
            or pick one under the Support Chats tab.
        </div></div>

    <?php endif; ?>

</div>

<?php admin_page_end(); ?>