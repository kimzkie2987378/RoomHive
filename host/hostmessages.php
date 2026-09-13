<?php
/* =========================================================
   ROOMHIVE — HOST MESSAGES
   hostmessages.php

   FIXED vs previous version:
   - Send form now has CSRF protection (it had none before —
     added the same guarded pattern usermessages.php uses).
   - Insert explicitly checks rowCount() before touching
     conversations.last_message_at, and sets the timestamp
     itself instead of relying on a DB default.
   - DB errors are caught and logged instead of being silently
     swallowed by PDO's default error mode, and surfaced to the
     host via the existing flash banner.
========================================================= */

require_once __DIR__ . '/host_init.php';

$me = (int) $_SESSION['user_id'];

/* ---------- Guarded CSRF helpers (host side) ---------- */
if (!function_exists('hp_csrf_token')) {
    function hp_csrf_token() {
        if (empty($_SESSION['hp_csrf'])) $_SESSION['hp_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['hp_csrf'];
    }
    function hp_csrf_field() {
        return '<input type="hidden" name="hp_csrf" value="' . hp_csrf_token() . '">';
    }
    function hp_csrf_ok($token) {
        return is_string($token) && isset($_SESSION['hp_csrf']) && hash_equals($_SESSION['hp_csrf'], $token);
    }
}

/* -----------------------------------------------------
   MESSAGES SCHEMA AUTO-DETECT
----------------------------------------------------- */
$_msgCols     = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
$MSG_TIME     = in_array('sent_at', $_msgCols, true) ? 'sent_at' : 'created_at';
$HAS_IS_READ  = in_array('is_read', $_msgCols, true);
$HAS_READ_AT  = in_array('read_at', $_msgCols, true);
$HAS_READ_FLAG = $HAS_IS_READ || $HAS_READ_AT;

$MSG_UNREAD_SQL = $HAS_READ_FLAG
    ? ($HAS_IS_READ ? 'm.is_read = 0' : 'm.read_at IS NULL')
    : '1 = 0';

if ($HAS_IS_READ) {
    $markReadStmt = $pdo->prepare(
        "UPDATE messages SET is_read = 1
         WHERE conversation_id = :c AND sender_id != :me AND is_read = 0"
    );
} elseif ($HAS_READ_AT) {
    $markReadStmt = $pdo->prepare(
        "UPDATE messages SET read_at = NOW()
         WHERE conversation_id = :c AND sender_id != :me AND read_at IS NULL"
    );
} else {
    $markReadStmt = null;
}

/* ---- Send a message (PRG) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'], $_POST['conversation_id'])) {
    $convId = (int) $_POST['conversation_id'];
    $body   = trim($_POST['body'] ?? '');

    if (!hp_csrf_ok($_POST['hp_csrf'] ?? '')) {

        hp_flash_set('error', 'Your session expired — please try sending that again.');

    } elseif ($body === '') {

        hp_flash_set('error', 'Message cannot be empty.');

    } else {

        /* Ownership: conversation must belong to THIS host */
        $own = $pdo->prepare("SELECT id FROM conversations WHERE id = :id AND host_id = :h LIMIT 1");
        $own->execute(['id' => $convId, 'h' => $me]);

        if (!$own->fetch()) {

            hp_flash_set('error', 'That conversation does not exist.');

        } else {

            try {
                $ins = $pdo->prepare(
                    "INSERT INTO messages (conversation_id, sender_id, body, $MSG_TIME)
                     VALUES (:c, :s, :b, NOW())"
                );
                $ins->execute(['c' => $convId, 's' => $me, 'b' => $body]);

                if ($ins->rowCount() === 1) {
                    $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :id")
                        ->execute(['id' => $convId]);
                } else {
                    hp_flash_set('error', 'Your message could not be saved. Please try again.');
                    error_log('hostmessages.php: insert reported rowCount=0 for conversation ' . $convId);
                }
            } catch (PDOException $e) {
                hp_flash_set('error', 'Something went wrong sending your message. Please try again.');
                error_log('hostmessages.php send failed: ' . $e->getMessage());
            }
        }
    }

    header('Location: /webprogg/host/hostmessages.php?c=' . $convId);
    exit;
}

/* ---- Conversation list ---- */
$convStmt = $pdo->prepare(
    "SELECT c.id, c.listing_id, c.last_message_at, c.created_at,
            u.name AS tenant_name, u.avatar_path AS tenant_avatar,
            l.title AS listing_title,
            (SELECT body FROM messages m
              WHERE m.conversation_id = c.id
              ORDER BY m.$MSG_TIME DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM messages m
              WHERE m.conversation_id = c.id
                AND m.sender_id != :h2
                AND $MSG_UNREAD_SQL) AS unread_count
     FROM conversations c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN listings l ON l.id = c.listing_id
     WHERE c.host_id = :h
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC"
);
$convStmt->execute(['h' => $me, 'h2' => $me]);

$conversations = array_map(function ($row) {
    return [
        'id'            => (int) $row['id'],
        'listing_id'    => $row['listing_id'] ? (int) $row['listing_id'] : null,
        'listing_title' => $row['listing_title'] ?? '',
        'tenant_name'   => $row['tenant_name'],
        'tenant_avatar' => resolve_photo($row['tenant_avatar'], '/webprogg/images/default-avatar.png'),
        'last_body'     => $row['last_body'] ?? '',
        'unread'        => (int) $row['unread_count'],
    ];
}, $convStmt->fetchAll());

/* ---- Selected thread ---- */
$isExplicitThread = isset($_GET['c']);
$selectedId = $isExplicitThread
    ? (int) $_GET['c']
    : ($conversations[0]['id'] ?? 0);

$selected = null;
$thread = [];

if ($selectedId) {
    foreach ($conversations as $c) {
        if ($c['id'] === $selectedId) { $selected = $c; break; }
    }
    if ($selected) {
        $msgStmt = $pdo->prepare(
            "SELECT body, sender_id, m.$MSG_TIME AS msg_time
             FROM messages m
             WHERE m.conversation_id = :c
             ORDER BY m.$MSG_TIME ASC"
        );
        $msgStmt->execute(['c' => $selectedId]);
        $thread = $msgStmt->fetchAll();

        if ($markReadStmt) {
            $markReadStmt->execute(['c' => $selectedId, 'me' => $me]);
        }
    }
}

/* Bell badge = real unread total (overrides host_init's 0) */
$bellStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM messages m
     JOIN conversations c ON c.id = m.conversation_id
     WHERE c.host_id = :h AND m.sender_id != :h2 AND $MSG_UNREAD_SQL"
);
$bellStmt->execute(['h' => $me, 'h2' => $me]);
$notification_count = (int) $bellStmt->fetchColumn();

function hp_message_day_label($timestamp) {
    $day = date('Y-m-d', $timestamp);
    if ($day === date('Y-m-d')) return 'Today';
    if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date('F j, Y', $timestamp);
}

$flash = hp_flash_take();
$activePage = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
<link rel="stylesheet" href="/webprogg/assets/hostmessages.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Messages</h1>
                <p class="hp-page-subtitle">Chat with tenants about their bookings.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($conversations)): ?>

            <section class="hp-msg-card-empty">
                <div class="hp-msg-list-header"><h2>Chats</h2></div>
                <div class="hp-msg-welcome">
                    <span class="hp-msg-welcome-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                        </svg>
                    </span>
                    <h3>No conversations yet</h3>
                    <p>When a tenant starts a chat on one of your listings, it will appear here.</p>
                </div>
            </section>

        <?php else: ?>

            <section class="hp-msg-card<?php echo $isExplicitThread ? ' hp-msg-mobile-open' : ''; ?>">

                <!-- LIST PANE -->
                <div class="hp-msg-list-pane">

                    <div class="hp-msg-list-header"><h2>Chats</h2></div>

                    <div class="hp-msg-search-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" id="hpMsgSearchInput" class="hp-msg-search" placeholder="Search messages" autocomplete="off">
                    </div>

                    <div class="hp-msg-tabs" id="hpMsgTabs">
                        <button type="button" class="hp-msg-tab active" data-filter="all">All</button>
                        <button type="button" class="hp-msg-tab" data-filter="unread">Unread</button>
                    </div>

                    <div class="hp-msg-list" id="hpMsgList">
                        <?php foreach ($conversations as $c): ?>
                            <?php
                                $isActive = $selected && $c['id'] === $selected['id'];
                                $isUnread = $c['unread'] > 0;
                            ?>
                            <a href="/webprogg/host/hostmessages.php?c=<?php echo (int) $c['id']; ?>"
                               class="hp-msg-list-item<?php echo $isActive ? ' active' : ''; ?><?php echo $isUnread ? ' unread' : ''; ?>">
                                <span class="hp-msg-avatar">
                                    <img src="<?php echo h($c['tenant_avatar']); ?>" alt="<?php echo h($c['tenant_name']); ?>">
                                </span>
                                <span class="hp-msg-item-body">
                                    <span class="hp-msg-item-top">
                                        <span class="hp-msg-name"><?php echo h($c['tenant_name']); ?></span>
                                        <?php if ($isUnread): ?>
                                            <span class="hp-msg-badge"><?php echo h($c['unread']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($c['listing_title'] !== ''): ?>
                                        <span class="hp-msg-listing-tag"><?php echo h($c['listing_title']); ?></span>
                                    <?php endif; ?>
                                    <span class="hp-msg-preview"><?php echo $c['last_body'] !== '' ? h($c['last_body']) : 'No messages yet'; ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                </div>

                <!-- THREAD PANE -->
                <div class="hp-msg-thread">
                    <?php if ($selected): ?>

                        <div class="hp-msg-thread-header">
                            <a href="/webprogg/host/hostmessages.php" class="hp-msg-back" aria-label="Back to messages">&#8249;</a>
                            <img class="hp-msg-thread-avatar" src="<?php echo h($selected['tenant_avatar']); ?>" alt="">
                            <div class="hp-msg-thread-info">
                                <div class="hp-msg-thread-name"><?php echo h($selected['tenant_name']); ?></div>
                                <?php if ($selected['listing_title'] !== ''): ?>
                                    <p class="hp-msg-thread-listing"><?php echo h($selected['listing_title']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($selected['listing_id']): ?>
                                <div class="hp-msg-thread-actions">
                                    <a href="/webprogg/Listings/listingdetails.php?id=<?php echo h($selected['listing_id']); ?>"
                                       class="hp-msg-icon-btn" title="View listing" aria-label="View listing">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hp-msg-thread-body" id="hpMsgThreadBody">
                            <?php if (empty($thread)): ?>
                                <div class="hp-msg-welcome">
                                    <span class="hp-msg-welcome-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                        </svg>
                                    </span>
                                    <h3>Say hello</h3>
                                    <p>No messages in this conversation yet.</p>
                                </div>
                            <?php endif; ?>

                            <?php $lastDay = null; ?>
                            <?php foreach ($thread as $m): ?>
                                <?php
                                    $ts     = strtotime($m['msg_time']);
                                    $dayKey = date('Y-m-d', $ts);
                                    $isMine = (int) $m['sender_id'] === $me;
                                ?>
                                <?php if ($dayKey !== $lastDay): ?>
                                    <span class="hp-msg-day-divider"><?php echo h(hp_message_day_label($ts)); ?></span>
                                    <?php $lastDay = $dayKey; ?>
                                <?php endif; ?>
                                <div class="hp-msg-row <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                                    <div class="hp-msg-bubble-wrap">
                                        <div class="hp-msg-bubble"><?php echo nl2br(h($m['body'])); ?></div>
                                        <span class="hp-msg-time"><?php echo h(date('g:i A', $ts)); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form method="POST" class="hp-msg-composer">
                            <?php echo hp_csrf_field(); ?>
                            <input type="hidden" name="conversation_id" value="<?php echo (int) $selected['id']; ?>">
                            <input type="text" name="body" class="hp-msg-input" placeholder="Type your message..." autocomplete="off" required>
                            <button type="submit" name="send_message" value="1" class="hp-msg-send-btn" aria-label="Send message">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.75 2.75a.75.75 0 0 1 .93-.73l17.5 5a.75.75 0 0 1 0 1.44l-6.36 1.82-1.82 6.36a.75.75 0 0 1-1.44 0l-5-17.5a.77.77 0 0 1-.01-.39Z"/></svg>
                            </button>
                        </form>

                    <?php else: ?>
                        <div class="hp-msg-welcome">
                            <span class="hp-msg-welcome-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                </svg>
                            </span>
                            <h3>Select a conversation</h3>
                            <p>Pick a chat on the left to see your messages.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </section>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>

<script>
  (function () {
    var body = document.getElementById('hpMsgThreadBody');
    if (body) { body.scrollTop = body.scrollHeight; }

    var searchInput = document.getElementById('hpMsgSearchInput');
    var tabsWrap = document.getElementById('hpMsgTabs');
    var list = document.getElementById('hpMsgList');
    if (!searchInput || !tabsWrap || !list) { return; }

    var items = Array.prototype.slice.call(list.querySelectorAll('.hp-msg-list-item'));
    var activeFilter = 'all';

    function applyFilters() {
      var term = searchInput.value.trim().toLowerCase();
      items.forEach(function (item) {
        var matchesTab = activeFilter === 'all' || item.classList.contains('unread');
        var matchesSearch = !term || item.textContent.toLowerCase().indexOf(term) !== -1;
        item.classList.toggle('js-hidden', !(matchesTab && matchesSearch));
      });
    }

    searchInput.addEventListener('input', applyFilters);

    tabsWrap.querySelectorAll('.hp-msg-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabsWrap.querySelectorAll('.hp-msg-tab').forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        activeFilter = tab.getAttribute('data-filter');
        applyFilters();
      });
    });
  })();
</script>
</body>
</html>