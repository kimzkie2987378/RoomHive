<?php
/* =========================================================
   ROOMHIVE — MY MESSAGES (TENANT INBOX)
   usermessages.php
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

 $functionsFile = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';
if (is_file($functionsFile)) {
    require_once $functionsFile;
}

/* Auth */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('um_resolve_photo')) {
    function um_resolve_photo($path, $fallback) {
        if (empty($path)) return $fallback;
        if (preg_match('#^https?://#i', $path)) return $path;
        $n = ltrim($path, '/');
        if (stripos($n, 'webprogg/') === 0) $n = substr($n, strlen('webprogg/'));
        return '/webprogg/' . $n;
    }
}
if (!function_exists('um_csrf_token')) {
    function um_csrf_token() {
        if (empty($_SESSION['um_csrf'])) $_SESSION['um_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['um_csrf'];
    }
    function um_csrf_field() {
        return '<input type="hidden" name="um_csrf" value="' . um_csrf_token() . '">';
    }
    function um_csrf_ok($token) {
        return is_string($token) && isset($_SESSION['um_csrf']) && hash_equals($_SESSION['um_csrf'], $token);
    }
}
if (!function_exists('um_flash_set')) {
    function um_flash_set($type, $message) {
        $_SESSION['um_flash'] = ['type' => $type, 'message' => $message];
    }
    function um_flash_take() {
        if (empty($_SESSION['um_flash'])) return null;
        $f = $_SESSION['um_flash'];
        unset($_SESSION['um_flash']);
        return $f;
    }
}

/* User */
 $stmt = $pdo->prepare("SELECT id, name, email, avatar_path, is_host FROM users WHERE id = :id LIMIT 1");
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostmessages.php");
    exit;
}

 $_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?: '/webprogg/images/default-avatar.png';
 $activeSidebar = 'messages';
 $me = (int) $_SESSION['user_id'];

/* Schema auto-detect */
 $_msgCols      = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
 $MSG_TIME      = in_array('sent_at', $_msgCols, true) ? 'sent_at' : 'created_at';
 $HAS_IS_READ   = in_array('is_read', $_msgCols, true);
 $HAS_READ_AT   = in_array('read_at', $_msgCols, true);
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

/* SEND — self-posting with PRG redirect */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'], $_POST['conversation_id'])) {
    $convId = (int) $_POST['conversation_id'];
    $body   = trim($_POST['body']);

    if (!um_csrf_ok($_POST['um_csrf'] ?? '')) {
        um_flash_set('error', 'Your session expired — please try sending that again.');
    } elseif ($body === '') {
        um_flash_set('error', 'Message cannot be empty.');
    } else {
        $own = $pdo->prepare("SELECT id FROM conversations WHERE id = :id AND user_id = :u LIMIT 1");
        $own->execute(['id' => $convId, 'u' => $me]);

        if (!$own->fetch()) {
            um_flash_set('error', 'That conversation could not be found.');
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

                    /* NEW — notify the other party */
                    $whoStmt = $pdo->prepare(
                        "SELECT user_id, host_id FROM conversations WHERE id = :id LIMIT 1"
                    );
                    $whoStmt->execute(['id' => $convId]);
                    $conv = $whoStmt->fetch();

                    if ($conv) {
                        $recipient = ((int) $conv['user_id'] === $me)
                            ? (int) $conv['host_id']
                            : (int) $conv['user_id'];

                        $recipientLink = ((int) $conv['user_id'] === $me)
                            ? '/webprogg/host/hostmessages.php?conversation=' . $convId
                            : '/webprogg/user/usermessages.php?conversation=' . $convId;

                        notify_user($pdo, $recipient, $recipientLink);
                    }
                } else {
                    um_flash_set('error', 'Your message could not be saved. Please try again.');
                    error_log('usermessages.php: insert reported rowCount=0 for conversation ' . $convId);
                }
            } catch (PDOException $e) {
                um_flash_set('error', 'Something went wrong sending your message. Please try again.');
                error_log('usermessages.php send failed: ' . $e->getMessage());
            }
        }
    }

    header('Location: /webprogg/user/usermessages.php?conversation=' . $convId);
    exit;
}

 $flash = um_flash_take();

/* Conversation list */
 $conversationsStmt = $pdo->prepare(
    "SELECT c.id, c.listing_id, c.last_message_at,
            h.id AS host_id, h.name AS host_name, h.avatar_path AS host_avatar,
            l.title AS listing_title,
            (SELECT body FROM messages m
              WHERE m.conversation_id = c.id
              ORDER BY m.$MSG_TIME DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM messages m
              WHERE m.conversation_id = c.id
                AND m.sender_id != :uid2
                AND $MSG_UNREAD_SQL) AS unread_count
     FROM conversations c
     JOIN users h ON h.id = c.host_id
     LEFT JOIN listings l ON l.id = c.listing_id
     WHERE c.user_id = :uid
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC"
);
 $conversationsStmt->execute(['uid' => $me, 'uid2' => $me]);

 $conversations = array_map(function ($row) {
    return [
        'id'            => (int) $row['id'],
        'listing_id'    => $row['listing_id'] ? (int) $row['listing_id'] : null,
        'listing_title' => $row['listing_title'] ?? '',
        'host_id'       => (int) $row['host_id'],
        'host_name'     => $row['host_name'],
        'host_avatar'   => um_resolve_photo($row['host_avatar'], '/webprogg/images/default-avatar.png'),
        'last_body'     => $row['last_body'] ?? '',
        'unread'        => (int) $row['unread_count'],
    ];
}, $conversationsStmt->fetchAll());

/* Open thread */
 $isExplicitThread = isset($_GET['conversation']);
 $activeConversationId = $isExplicitThread
    ? (int) $_GET['conversation']
    : ($conversations[0]['id'] ?? null);

 $activeConversation = null;
 $threadMessages = [];

if ($activeConversationId !== null) {
    foreach ($conversations as $c) {
        if ($c['id'] === $activeConversationId) { $activeConversation = $c; break; }
    }

    if ($activeConversation) {
        $threadStmt = $pdo->prepare(
            "SELECT sender_id, body, m.$MSG_TIME AS msg_time
             FROM messages m
             WHERE m.conversation_id = :cid
             ORDER BY m.$MSG_TIME ASC"
        );
        $threadStmt->execute(['cid' => $activeConversationId]);
        $threadMessages = $threadStmt->fetchAll();

        if ($markReadStmt) {
            $markReadStmt->execute(['c' => $activeConversationId, 'me' => $me]);
        }
    }
}

/* Bell = real unread total */
 $bellStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM messages m
     JOIN conversations c ON c.id = m.conversation_id
     WHERE c.user_id = :u AND m.sender_id != :u2 AND $MSG_UNREAD_SQL"
);
 $bellStmt->execute(['u' => $me, 'u2' => $me]);
 $notification_count = (int) $bellStmt->fetchColumn();

if (!function_exists('message_day_label')) {
    function message_day_label($timestamp) {
        $day = date('Y-m-d', $timestamp);
        if ($day === date('Y-m-d')) return 'Today';
        if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
        return date('F j, Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<link rel="stylesheet" href="/webprogg/assets/usermessages.css">
<script>document.documentElement.classList.add("js");</script>
<style>
.um-flash { padding: 10px 14px; margin: 0 0 12px; border-radius: 8px; font-size: 14px; }
.um-flash-error { background: #fdecea; color: #b3261e; border: 1px solid #f5c6c2; }
.um-flash-success { background: #e9f7ef; color: #1e7e42; border: 1px solid #bfe8cf; }
</style>
</head>
<body>

<header class="navbar">
    <a href="/webprogg/user/usershome.php" class="logo">
        <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo">
    </a>
    <nav class="nav-links">
        <a href="/webprogg/user/usershome.php">HOME</a>
        <a href="/webprogg/Listings/listing.php">LISTINGS</a>
        <a href="/webprogg/host/howitworks.php">HOW IT WORKS</a>
        <a href="/webprogg/host/becomeahost.php">BECOME A HOST</a>
        <a href="/webprogg/hiveclub.php">HIVE CLUB</a>
        <a href="/webprogg/misc/contacts.php">CONTACTS</a>
        <a href="/webprogg/user/notifications.php" class="nav-bell">
            <img src="/webprogg/images/bellicon.png" alt="Notifications">
            <?php if ($notification_count > 0): ?>
                <span class="nav-bell-badge"><?php echo h($notification_count); ?></span>
            <?php endif; ?>
        </a>
        <div class="account-dropdown js-account-dropdown">
            <button type="button" class="my-account js-account-toggle" id="accountDropdownToggle" aria-haspopup="true" aria-expanded="false">
                <span class="account-circle"><img src="<?php echo h($navAvatar); ?>" alt="My Account" id="navAccountAvatarImg"></span>
                <span>MY PROFILE</span>
                <span class="dropdown-caret">&#9662;</span>
            </button>
            <div class="account-dropdown-menu" id="accountDropdownMenu">
                <a href="/webprogg/user/userprofile.php">My Profile</a>
                <a href="/webprogg/auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
</header>

<!-- PAGE HEADER — PLAIN -->
<header class="ub-page-head">
    <span class="ub-eyebrow">Tenant Inbox</span>
    <h1>Your conversations</h1>
    <p class="ub-lead">Talk with hosts about your bookings and questions.</p>
</header>

<main class="up-dashboard">

  <?php
  $sidebarFile = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';
  if (is_file($sidebarFile)) { require $sidebarFile; }
  ?>

  <div class="up-content">

    <?php if ($flash): ?>
      <div class="um-flash <?php echo $flash['type'] === 'success' ? 'um-flash-success' : 'um-flash-error'; ?>">
        <?php echo h($flash['message']); ?>
      </div>
    <?php endif; ?>

    <?php if (empty($conversations)): ?>

      <section class="up-card up-msg-card-empty up-reveal">
        <div class="up-msg-list-header"><h2>Chats</h2></div>
        <div class="up-msg-welcome">
          <span class="up-msg-welcome-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
          </span>
          <h3>No conversations yet</h3>
          <p>Once you message a host about a stay, your conversation will show up here.</p>
          <a href="/webprogg/Listings/listing.php" class="up-btn-outline">BROWSE LISTINGS</a>
        </div>
      </section>

    <?php else: ?>

      <section class="up-card up-msg-card up-reveal<?php echo $isExplicitThread ? ' up-msg-mobile-open' : ''; ?>">

        <!-- LIST PANE -->
        <div class="up-msg-list-pane">
          <div class="up-msg-list-header">
            <h2>Chats</h2>
            <a href="/webprogg/Listings/listing.php" class="up-msg-compose" title="Start a new conversation" aria-label="Start a new conversation">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </a>
          </div>

          <div class="up-msg-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="msgSearchInput" class="up-msg-search" placeholder="Search messages" autocomplete="off">
          </div>

          <div class="up-msg-tabs" id="msgTabs">
            <button type="button" class="up-msg-tab active" data-filter="all">All</button>
            <button type="button" class="up-msg-tab" data-filter="unread">Unread</button>
          </div>

          <div class="up-msg-list" id="msgList">
            <?php foreach ($conversations as $c): ?>
              <?php
                $isActive = $activeConversation && $c['id'] === $activeConversation['id'];
                $isUnread = $c['unread'] > 0;
              ?>
              <a href="/webprogg/user/usermessages.php?conversation=<?php echo h($c['id']); ?>"
                 class="up-msg-list-item<?php echo $isActive ? ' active' : ''; ?><?php echo $isUnread ? ' unread' : ''; ?>">
                <span class="up-msg-avatar">
                  <img src="<?php echo h($c['host_avatar']); ?>" alt="<?php echo h($c['host_name']); ?>">
                </span>
                <span class="up-msg-item-body">
                  <span class="up-msg-item-top">
                    <span class="up-msg-name"><?php echo h($c['host_name']); ?></span>
                    <?php if ($isUnread): ?>
                      <span class="up-msg-badge"><?php echo h($c['unread']); ?></span>
                    <?php endif; ?>
                  </span>
                  <?php if ($c['listing_title'] !== ''): ?>
                    <span class="up-msg-listing-tag"><?php echo h($c['listing_title']); ?></span>
                  <?php endif; ?>
                  <span class="up-msg-preview"><?php echo $c['last_body'] !== '' ? h($c['last_body']) : 'No messages yet'; ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- THREAD PANE -->
        <div class="up-msg-thread">
          <?php if ($activeConversation): ?>

            <div class="up-msg-thread-header">
              <a href="/webprogg/user/usermessages.php" class="up-msg-back" aria-label="Back to messages">&#8249;</a>
              <a href="/webprogg/host/hostpublicprofile.php?id=<?php echo h($activeConversation['host_id']); ?>"
                 class="up-msg-thread-identity" title="View <?php echo h($activeConversation['host_name']); ?>'s profile">
                <img class="up-msg-thread-avatar" src="<?php echo h($activeConversation['host_avatar']); ?>" alt="">
                <div class="up-msg-thread-info">
                  <div class="up-msg-thread-name"><?php echo h($activeConversation['host_name']); ?></div>
                  <?php if ($activeConversation['listing_title'] !== ''): ?>
                    <p class="up-msg-thread-listing"><?php echo h($activeConversation['listing_title']); ?></p>
                  <?php endif; ?>
                </div>
              </a>
              <div class="up-msg-thread-actions">
                <?php if ($activeConversation['listing_id']): ?>
                  <a href="/webprogg/Listings/listingdetails.php?id=<?php echo h($activeConversation['listing_id']); ?>"
                     class="up-msg-icon-btn" title="View listing" aria-label="View listing">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <div class="up-msg-thread-body" id="msgThreadBody">
              <?php if (empty($threadMessages)): ?>
                <div class="up-msg-welcome">
                  <span class="up-msg-welcome-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                    </svg>
                  </span>
                  <h3>Say hello</h3>
                  <p>No messages in this conversation yet.</p>
                </div>
              <?php endif; ?>

              <?php $lastDay = null; ?>
              <?php foreach ($threadMessages as $m): ?>
                <?php
                  $ts     = strtotime($m['msg_time']);
                  $dayKey = date('Y-m-d', $ts);
                  $isMine = (int) $m['sender_id'] === $me;
                ?>
                <?php if ($dayKey !== $lastDay): ?>
                  <span class="up-msg-day-divider"><?php echo h(message_day_label($ts)); ?></span>
                  <?php $lastDay = $dayKey; ?>
                <?php endif; ?>
                <div class="up-msg-row <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                  <div class="up-msg-bubble-wrap">
                    <div class="up-msg-bubble"><?php echo nl2br(h($m['body'])); ?></div>
                    <span class="up-msg-time"><?php echo h(date('g:i A', $ts)); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <form action="/webprogg/user/usermessages.php" method="POST" class="up-msg-composer">
              <?php echo um_csrf_field(); ?>
              <input type="hidden" name="conversation_id" value="<?php echo h($activeConversation['id']); ?>">
              <input type="text" name="body" class="up-msg-input" placeholder="Write a message..." autocomplete="off" required>
              <button type="submit" class="up-msg-send-btn" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.75 2.75a.75.75 0 0 1 .93-.73l17.5 5a.75.75 0 0 1 0 1.44l-6.36 1.82-1.82 6.36a.75.75 0 0 1-1.44 0l-5-17.5a.77.77 0 0 1-.01-.39Z"/></svg>
              </button>
            </form>

          <?php else: ?>
            <div class="up-msg-welcome">
              <span class="up-msg-welcome-icon">
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

<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>
            <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
            <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>hello@roomhive.ph</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
        </div>
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>
        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>
    <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p></div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>

<script>
  (function () {
    var body = document.getElementById('msgThreadBody');
    if (body) { body.scrollTop = body.scrollHeight; }

    var searchInput = document.getElementById('msgSearchInput');
    var tabsWrap = document.getElementById('msgTabs');
    var list = document.getElementById('msgList');
    if (!searchInput || !tabsWrap || !list) { return; }

    var items = Array.prototype.slice.call(list.querySelectorAll('.up-msg-list-item'));
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

    tabsWrap.querySelectorAll('.up-msg-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabsWrap.querySelectorAll('.up-msg-tab').forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        activeFilter = tab.getAttribute('data-filter');
        applyFilters();
      });
    });
  })();
</script>

<!-- Reveal (self-contained) -->
<script>
(function () {
    "use strict";
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
                window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }
})();
</script>

</body>
</html>