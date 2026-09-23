<?php
/* =========================================================
   ROOMHIVE ADMIN — MESSAGES
   adminmessages.php

   TAB 1 "Contact Questions" — contact_messages from the
        Contacts page, with an "Open Chat" button that opens
        a messenger-style thread (adminchat.php). Replies
        appear in the user's Messages inbox as a chat from
        RoomHive Admin. Guests: email reply only.
   TAB 2 "Support Chats"    — ongoing admin ↔ user chats.
   TAB 3 "Conversations"    — original tenant ↔ host reader.
========================================================= */

require_once __DIR__ . '/admin_init.php';

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

/* ---- System support user (same helper as adminchat.php) ---- */
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
            error_log('adminmessages: could not create support user: ' . $e->getMessage());
            return 0;
        }
    }
}

 $supportId = adm_support_user_id($pdo);

/* ---- Schema auto-detect ---- */
 $_msgCols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
 $MSG_TIME = in_array('sent_at', $_msgCols, true) ? 'sent_at' : 'created_at';
 $HAS_IS_READ   = in_array('is_read', $_msgCols, true);
 $HAS_READ_AT   = in_array('read_at', $_msgCols, true);
 $MSG_UNREAD_SQL = $HAS_IS_READ ? 'm.is_read = 0'
                 : ($HAS_READ_AT ? 'm.read_at IS NULL' : '1 = 0');

/* ---- Tab switcher ---- */
 $tabParam = $_GET['tab'] ?? 'inquiries';
 $tab = in_array($tabParam, ['inquiries', 'chats', 'conversations'], true) ? $tabParam : 'inquiries';

/* ---- Status update (Contact Questions tab) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'inquiries') {
    $updId     = (int) ($_POST['ticket_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');

    if ($updId > 0 && in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $u = $pdo->prepare("UPDATE contact_messages SET status = :s WHERE id = :id");
        $u->execute([':s' => $newStatus, ':id' => $updId]);
    }
    header('Location: ?tab=inquiries&v=' . $updId);
    exit;
}

/* =========================================================
   TAB 1 — CONTACT QUESTIONS
========================================================= */
 $inquiries = [];
 $inqCounts = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];

try {
    $inquiries = $pdo->query(
        "SELECT cm.*, u.name AS account_name
         FROM contact_messages cm
         LEFT JOIN users u ON u.id = cm.user_id
         ORDER BY cm.created_at DESC
         LIMIT 200"
    )->fetchAll();

    foreach ($inquiries as $row) {
        $s = $row['status'] ?? 'open';
        if (isset($inqCounts[$s])) { $inqCounts[$s]++; }
    }
} catch (PDOException $e) { $inquiries = []; }

 $selectedV       = (int) ($_GET['v'] ?? 0);
 $selectedInquiry = null;
foreach ($inquiries as $row) {
    if ((int) $row['id'] === $selectedV) { $selectedInquiry = $row; break; }
}

/* =========================================================
   TAB 2 — SUPPORT CHATS (admin ↔ user)
========================================================= */
 $chats = [];
if ($tab === 'chats' && $supportId > 0) {
    try {
        $st = $pdo->prepare(
            "SELECT c.id, c.user_id, u.name AS user_name, u.email,
                    c.last_message_at, c.created_at,
                    (SELECT body FROM messages m
                      WHERE m.conversation_id = c.id
                      ORDER BY m.$MSG_TIME DESC LIMIT 1) AS last_body,
                    (SELECT COUNT(*) FROM messages m
                      WHERE m.conversation_id = c.id
                        AND m.sender_id != :sid
                        AND $MSG_UNREAD_SQL) AS unread_count
             FROM conversations c
             JOIN users u ON u.id = c.user_id
             WHERE c.host_id = :sid2
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
             LIMIT 100"
        );
        $st->execute([':sid' => $supportId, ':sid2' => $supportId]);
        $chats = $st->fetchAll();
    } catch (PDOException $e) { $chats = []; }
}

/* =========================================================
   TAB 3 — CONVERSATIONS (original tenant ↔ host reader)
========================================================= */
 $conversations = [];
 $thread        = [];
 $selected      = null;
 $selectedId    = (int) ($_GET['c'] ?? 0);

if ($tab === 'conversations') {
    $conversations = $pdo->query(
        "SELECT c.id, c.created_at, c.last_message_at,
                t.name AS tenant_name, h.name AS host_name,
                COALESCE(l.title, 'No listing attached') AS listing_title,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS msg_count
         FROM conversations c
         JOIN users t ON t.id = c.user_id
         JOIN users h ON h.id = c.host_id
         LEFT JOIN listings l ON l.id = c.listing_id
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
         LIMIT 100"
    )->fetchAll();

    foreach ($conversations as $c) {
        if ((int) $c['id'] === $selectedId) { $selected = $c; break; }
    }

    if ($selected) {
        $m = $pdo->prepare(
            "SELECT m.body, m.sender_id, m.$MSG_TIME AS msg_time, u.name AS sender_name
             FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = :c
             ORDER BY m.$MSG_TIME ASC"
        );
        $m->execute(['c' => $selectedId]);
        $thread = $m->fetchAll();
    }
}

if (!function_exists('adm_status_badge')) {
    function adm_status_badge($status) {
        $map = [
            'open'        => ['Open',        'st-open'],
            'in_progress' => ['In Progress', 'st-progress'],
            'resolved'    => ['Resolved',    'st-resolved'],
            'closed'      => ['Closed',      'st-closed'],
        ];
        $s = $map[$status] ?? $map['open'];
        return '<span class="st-badge ' . $s[1] . '">' . $s[0] . '</span>';
    }
}
?>
<?php admin_page_start('RoomHive Admin — Messages', 'Messages', <<<CSS
<style>
    .adm-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .adm-tab { padding: 9px 18px; border-radius: 999px; border: 1px solid var(--border);
        background: #fff; color: var(--text-dark); font-size: 12.5px; font-weight: 700;
        text-decoration: none; transition: .15s ease; }
    .adm-tab:hover { border-color: #eda423; color: #b07708; }
    .adm-tab.active { background: var(--orange-light); border-color: #eda423; color: #b07708; }

    .msg-layout { display: grid; grid-template-columns: 1fr 1.4fr; gap: 18px; align-items: start; }
    @media (max-width: 1000px) { .msg-layout { grid-template-columns: 1fr; } }

    .convo-item, .inq-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px;
        border-bottom: 1px solid var(--border); }
    .convo-item:hover, .convo-item.active, .inq-item:hover, .inq-item.active { background: var(--orange-light); }
    .convo-item strong, .inq-item strong { display: block; font-size: 13px; color: var(--text-dark); }
    .convo-item span, .inq-item span { display: block; font-size: 11.5px; color: var(--text-muted); }

    .st-badge { display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 10.5px; font-weight: 800; letter-spacing: .03em; white-space: nowrap; }
    .st-open     { background: #FFF1DC; color: #B07708; border: 1px solid rgba(237,164,35,.5); }
    .st-progress { background: #E7F0FF; color: #2563EB; border: 1px solid rgba(37,99,235,.35); }
    .st-resolved { background: #E8F8F1; color: #178A50; border: 1px solid rgba(23,138,80,.35); }
    .st-closed   { background: #F0F1F6; color: #6B7684; border: 1px solid var(--border); }

    .thread-box { max-height: 480px; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
    .inq-detail { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
    .inq-subject { margin: 0; font-size: 17px; font-weight: 800; color: var(--text-dark); }
    .inq-meta { display: grid; gap: 6px; padding: 12px 14px; border: 1px solid var(--border);
        border-radius: 10px; background: #FAFAFA; }
    .inq-meta div { display: flex; gap: 8px; font-size: 12.5px; }
    .inq-meta b { min-width: 80px; color: var(--text-muted); font-weight: 700; }
    .inq-meta a { color: #b07708; text-decoration: none; font-weight: 600; }
    .inq-meta a:hover { text-decoration: underline; }
    .inq-body { padding: 14px 16px; border: 1px solid var(--border); border-left: 4px solid #eda423;
        border-radius: 10px; font-size: 13.5px; line-height: 1.65; color: var(--text-dark);
        white-space: pre-wrap; overflow-wrap: anywhere; }
    .inq-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .st-btn { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border);
        background: #fff; color: var(--text-dark); font-family: inherit; font-size: 11.5px;
        font-weight: 700; cursor: pointer; transition: .15s ease; }
    .st-btn:hover { border-color: #eda423; color: #b07708; background: #FFF8EC; }
    .reply-btn, .chat-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px;
        border-radius: 999px; font-size: 12px; font-weight: 800; text-decoration: none; }
    .reply-btn { background: linear-gradient(135deg, #f6b93b, #eda423); color: #1c2a38;
        box-shadow: 0 6px 14px rgba(237,164,35,.3); }
    .reply-btn:hover { transform: translateY(-1px); }
    .chat-btn { background: #1c2a38; color: #fff; }
    .chat-btn:hover { background: #eda423; color: #1c2a38; }
    .guest-note { font-size: 11.5px; color: var(--text-muted); font-style: italic; }

    .cnt-chip { display: inline-flex; align-items: center; gap: 5px; margin-right: 8px;
        font-size: 11.5px; color: var(--text-muted); font-weight: 600; }
    .unread-badge { background: #eda423; color: #fff; font-size: 10px; font-weight: 800;
        border-radius: 999px; padding: 2px 8px; }

    .bubble { max-width: 72%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; }
    .bubble b { display: block; font-size: 11px; opacity: .75; margin-bottom: 3px; }
    .bubble.host-b { align-self: flex-start; background: #F3F4F6; color: var(--text-dark); border-bottom-left-radius: 4px; }
    .bubble.tenant-b { align-self: flex-end; background: var(--orange-light); color: var(--text-dark); border-bottom-right-radius: 4px; }
</style>
CSS); ?>

<div class="page-heading">
    <h1>Messages</h1>
    <p>Contact questions from the Contacts page, support chats, and tenant &harr; host oversight.</p>
</div>

<!-- TABS -->
<div class="adm-tabs">
    <a class="adm-tab <?= $tab === 'inquiries' ? 'active' : '' ?>" href="?tab=inquiries">
        Contact Questions (<?= count($inquiries) ?>)
    </a>
    <a class="adm-tab <?= $tab === 'chats' ? 'active' : '' ?>" href="?tab=chats">
        Support Chats (<?= count($chats) ?>)
    </a>
    <a class="adm-tab <?= $tab === 'conversations' ? 'active' : '' ?>" href="?tab=conversations">
        User Conversations
    </a>
</div>

<?php if ($tab === 'inquiries'): ?>

    <div class="msg-layout">

        <div class="panel" style="padding:0; overflow:hidden;">
            <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
                <h2>Inquiries</h2>
                <div>
                    <span class="cnt-chip"><span class="st-badge st-open">Open</span> <?= (int) $inqCounts['open'] ?></span>
                    <span class="cnt-chip"><span class="st-badge st-progress">In Prog.</span> <?= (int) $inqCounts['in_progress'] ?></span>
                    <span class="cnt-chip"><span class="st-badge st-resolved">Done</span> <?= (int) $inqCounts['resolved'] ?></span>
                </div>
            </div>

            <?php if (empty($inquiries)): ?>
                <?php emptyState('No contact questions yet. Submissions from the Contacts page will appear here.'); ?>
            <?php else: ?>
                <?php foreach ($inquiries as $q): ?>
                    <a class="inq-item <?= $selectedV === (int) $q['id'] ? 'active' : '' ?>"
                       href="?tab=inquiries&v=<?= (int) $q['id'] ?>" style="text-decoration:none;">
                        <div style="flex:1; min-width:0;">
                            <strong><?= h(trim($q['first_name'] . ' ' . $q['last_name'])) ?></strong>
                            <span><?= h($q['subject']) ?></span>
                            <span><?= h(date('M j, g:i A', strtotime($q['created_at']))) ?> &middot; <?= h($q['email']) ?></span>
                        </div>
                        <?= adm_status_badge($q['status']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="panel" style="padding:0;">
            <?php if ($selectedInquiry): ?>
                <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
                    <h2>Contact Question #<?= (int) $selectedInquiry['id'] ?></h2>
                    <?= adm_status_badge($selectedInquiry['status']) ?>
                </div>

                <div class="inq-detail">

                    <h3 class="inq-subject"><?= h($selectedInquiry['subject']) ?></h3>

                    <div class="inq-meta">
                        <div><b>From</b> <span><?= h(trim($selectedInquiry['first_name'] . ' ' . $selectedInquiry['last_name'])) ?></span></div>
                        <div><b>Email</b> <a href="mailto:<?= h($selectedInquiry['email']) ?>"><?= h($selectedInquiry['email']) ?></a></div>
                        <div><b>Account</b> <span><?= $selectedInquiry['account_name'] ? h($selectedInquiry['account_name']) . ' (user #' . (int) $selectedInquiry['user_id'] . ')' : 'Guest (not logged in)' ?></span></div>
                        <div><b>Received</b> <span><?= h(date('M j, Y — g:i A', strtotime($selectedInquiry['created_at']))) ?></span></div>
                    </div>

                    <div class="inq-body"><?= h($selectedInquiry['message']) ?></div>

                    <!-- CHAT + STATUS -->
                    <div class="inq-actions">
                        <?php if (!empty($selectedInquiry['user_id']) && $supportId > 0): ?>
                            <a class="chat-btn"
                               href="adminchat.php?from_inquiry=<?= (int) $selectedInquiry['id'] ?>">
                                &#128172; Open Chat
                            </a>
                        <?php else: ?>
                            <span class="guest-note">Guest submission — chat unavailable, reply by email.</span>
                        <?php endif; ?>

                        <a class="reply-btn"
                           href="mailto:<?= h($selectedInquiry['email']) ?>?subject=<?= rawurlencode('Re: ' . $selectedInquiry['subject']) ?>">
                            &#9993; Reply by email
                        </a>
                    </div>

                    <div class="inq-actions">
                        <form method="post" action="?tab=inquiries&v=<?= (int) $selectedInquiry['id'] ?>" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="hidden" name="ticket_id" value="<?= (int) $selectedInquiry['id'] ?>">
                            <button class="st-btn" name="status" value="open">Mark Open</button>
                            <button class="st-btn" name="status" value="in_progress">Mark In&nbsp;Progress</button>
                            <button class="st-btn" name="status" value="resolved">Mark Resolved</button>
                            <button class="st-btn" name="status" value="closed">Mark Closed</button>
                        </form>
                    </div>

                </div>
            <?php else: ?>
                <div class="detail-empty" style="padding:80px 20px;">
                    <div class="empty-icon"><?= icon('message') ?></div>
                    <p>Select a contact question to read it. Submissions arrive from the Contacts page.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

<?php elseif ($tab === 'chats'): ?>

    <div class="panel" style="padding:0; overflow:hidden;">
        <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
            <h2>Support Chats (<?= count($chats) ?>)</h2>
        </div>

        <?php if ($supportId === 0): ?>
            <?php emptyState('The support account could not be prepared — check the PHP error log.'); ?>
        <?php elseif (empty($chats)): ?>
            <?php emptyState('No support chats yet. Open one from a Contact Question.'); ?>
        <?php else: ?>
            <?php foreach ($chats as $c): ?>
                <a class="inq-item" href="adminchat.php?c=<?= (int) $c['id'] ?>" style="text-decoration:none;">
                    <div style="flex:1; min-width:0;">
                        <strong><?= h($c['user_name']) ?></strong>
                        <span><?= $c['last_body'] !== null ? h(mb_strimwidth((string) $c['last_body'], 0, 70, '…')) : 'No messages yet' ?></span>
                        <span><?= $c['last_message_at'] ? h(date('M j, g:i A', strtotime($c['last_message_at']))) : h(date('M j, Y', strtotime($c['created_at']))) ?> &middot; <?= h($c['email']) ?></span>
                    </div>
                    <?php if ((int) $c['unread_count'] > 0): ?>
                        <span class="unread-badge"><?= (int) $c['unread_count'] ?></span>
                    <?php endif; ?>
                    <?= icon('chevron-right') ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="msg-layout">

        <div class="panel" style="padding:0; overflow:hidden;">
            <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
                <h2>Conversations (<?= count($conversations) ?>)</h2>
            </div>
            <?php if (empty($conversations)): ?>
                <?php emptyState('No conversations yet.'); ?>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <a class="convo-item <?= $selectedId === (int) $c['id'] ? 'active' : '' ?>" href="?tab=conversations&c=<?= (int) $c['id'] ?>" style="text-decoration:none;">
                        <div style="flex:1; min-width:0;">
                            <strong><?= h($c['tenant_name']) ?> &harr; <?= h($c['host_name']) ?></strong>
                            <span><?= h($c['listing_title']) ?></span>
                            <span><?= (int) $c['msg_count'] ?> message(s) &middot; <?= $c['last_message_at'] ? h(date('M j, g:i A', strtotime($c['last_message_at']))) : 'no messages yet' ?></span>
                        </div>
                        <?= icon('chevron-right') ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="panel" style="padding:0;">
            <?php if ($selected): ?>
                <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
                    <h2><?= h($selected['tenant_name']) ?> &harr; <?= h($selected['host_name']) ?></h2>
                    <span class="stat-caption"><?= h($selected['listing_title']) ?></span>
                </div>
                <div class="thread-box">
                    <?php if (empty($thread)): ?>
                        <p class="stat-caption" style="margin:auto;">No messages in this conversation yet.</p>
                    <?php else: ?>
                        <?php
                        $hostIdStmt = $pdo->prepare("SELECT host_id FROM conversations WHERE id = :id");
                        $hostIdStmt->execute(['id' => $selectedId]);
                        $threadHostId = (int) $hostIdStmt->fetchColumn();
                        ?>
                        <?php foreach ($thread as $m): ?>
                            <div class="bubble <?= (int) $m['sender_id'] === $threadHostId ? 'host-b' : 'tenant-b' ?>">
                                <b><?= h($m['sender_name']) ?> &middot; <?= h(date('M j, g:i A', strtotime($m['msg_time']))) ?></b>
                                <?= h($m['body']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="detail-empty" style="padding:80px 20px;">
                    <div class="empty-icon"><?= icon('message') ?></div>
                    <p>Select a conversation to read it. Admins can read but not send messages.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

<?php endif; ?>

<?php admin_page_end(); ?>