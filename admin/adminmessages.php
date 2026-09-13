<?php
require_once __DIR__ . '/admin_init.php';

/* NOTE: uses messages.sent_at per your schema. If you get
   "Unknown column 'sent_at'", swap every sent_at below for created_at. */

 $conversations = $pdo->query(
    "SELECT c.id, c.created_at, c.last_message_at,
            t.name AS tenant_name, h.name AS host_name,
            l.title AS listing_title,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS msg_count
     FROM conversations c
     JOIN users t ON t.id = c.user_id
     JOIN users h ON h.id = c.host_id
     JOIN listings l ON l.id = c.listing_id
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
     LIMIT 100"
)->fetchAll();

 $selectedId = (int) ($_GET['c'] ?? 0);
 $selected = null;
 $thread = [];
foreach ($conversations as $c) {
    if ((int)$c['id'] === $selectedId) { $selected = $c; break; }
}
if ($selected) {
    $m = $pdo->prepare(
        "SELECT m.body, m.sender_id, m.sent_at, u.name AS sender_name
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = :c
         ORDER BY m.sent_at ASC"
    );
    $m->execute(['c' => $selectedId]);
    $thread = $m->fetchAll();
}
?>
<?php admin_page_start('RoomHive Admin — Messages', 'Messages', <<<CSS
<style>
    .msg-layout { display: grid; grid-template-columns: 1fr 1.4fr; gap: 18px; align-items: start; }
    @media (max-width: 1000px) { .msg-layout { grid-template-columns: 1fr; } }
    .convo-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px;
        border-bottom: 1px solid var(--border); cursor: pointer; }
    .convo-item:hover, .convo-item.active { background: var(--orange-light); }
    .convo-item strong { display: block; font-size: 13px; color: var(--text-dark); }
    .convo-item span { display: block; font-size: 11.5px; color: var(--text-muted); }
    .thread-box { max-height: 480px; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
    .bubble { max-width: 72%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; }
    .bubble b { display: block; font-size: 11px; opacity: .75; margin-bottom: 3px; }
    .bubble.host-b { align-self: flex-start; background: #F3F4F6; color: var(--text-dark); border-bottom-left-radius: 4px; }
    .bubble.tenant-b { align-self: flex-end; background: var(--orange-light); color: var(--text-dark); border-bottom-right-radius: 4px; }
</style>
CSS); ?>

<div class="page-heading">
    <h1>Messages</h1>
    <p>Read-only oversight of tenant ↔ host conversations (for moderation and support).</p>
</div>

<div class="msg-layout">
    <div class="panel" style="padding:0; overflow:hidden;">
        <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
            <h2>Conversations (<?= count($conversations) ?>)</h2>
        </div>
        <?php if (empty($conversations)): ?>
            <?php emptyState('No conversations yet.'); ?>
        <?php else: ?>
            <?php foreach ($conversations as $c): ?>
                <a class="convo-item <?= $selectedId === (int)$c['id'] ? 'active' : '' ?>" href="?c=<?= (int)$c['id'] ?>" style="text-decoration:none;">
                    <div style="flex:1; min-width:0;">
                        <strong><?= h($c['tenant_name']) ?> ↔ <?= h($c['host_name']) ?></strong>
                        <span><?= h($c['listing_title']) ?></span>
                        <span><?= (int)$c['msg_count'] ?> message(s) · <?= $c['last_message_at'] ? h(date('M j, g:i A', strtotime($c['last_message_at']))) : 'no messages yet' ?></span>
                    </div>
                    <?= icon('chevron-right') ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel" style="padding:0;">
        <?php if ($selected): ?>
            <div class="panel-header" style="padding:16px 18px; margin:0; border-bottom:1px solid var(--border);">
                <h2><?= h($selected['tenant_name']) ?> ↔ <?= h($selected['host_name']) ?></h2>
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
                        <div class="bubble <?= (int)$m['sender_id'] === $threadHostId ? 'host-b' : 'tenant-b' ?>">
                            <b><?= h($m['sender_name']) ?> · <?= h(date('M j, g:i A', strtotime($m['sent_at']))) ?></b>
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

<?php admin_page_end(); ?>