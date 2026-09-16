<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   usermessages.php

   Chat-app style layout (see usermessages.css): a "Chats"
   pane on the left with search + All/Unread filtering, and
   the open thread on the right. Assumes a `conversations`
   table (id, user_id, host_id, listing_id, last_message_at)
   and a `messages` table (id, conversation_id, sender_id,
   body, created_at, read_at).
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   USER DATA
----------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host FROM users WHERE id = :id LIMIT 1"
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

$notification_count = 0;
$activeSidebar = 'messages';

/* -----------------------------------------------------
   CONVERSATION LIST
   Includes h.id (host_id) alongside the host's display info
   so the thread header can link out to the host's public
   profile page (hostpublicprofile.php?id=<host_id>).
----------------------------------------------------- */
$conversationsStmt = $pdo->prepare(
    "SELECT c.id, c.listing_id, c.last_message_at,
            h.id AS host_id, h.name AS host_name, h.avatar_path AS host_avatar,
            l.title AS listing_title,
            (SELECT body FROM messages m
              WHERE m.conversation_id = c.id
              ORDER BY m.created_at DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM messages m
              WHERE m.conversation_id = c.id
                AND m.sender_id != :uid2
                AND m.read_at IS NULL) AS unread_count
     FROM conversations c
     JOIN users h ON h.id = c.host_id
     LEFT JOIN listings l ON l.id = c.listing_id
     WHERE c.user_id = :uid
     ORDER BY c.last_message_at DESC"
);
$conversationsStmt->execute(['uid' => $_SESSION['user_id'], 'uid2' => $_SESSION['user_id']]);

$conversations = array_map(function ($row) {
    return [
        'id'            => (int) $row['id'],
        'listing_id'    => $row['listing_id'] ? (int) $row['listing_id'] : null,
        'listing_title' => $row['listing_title'] ?? '',
        'host_id'       => (int) $row['host_id'],
        'host_name'     => $row['host_name'],
        'host_avatar'   => resolve_photo($row['host_avatar'], '/webprogg/images/default-avatar.png'),
        'last_body'     => $row['last_body'] ?? '',
        'last_at'       => $row['last_message_at'],
        'unread'        => (int) $row['unread_count'],
    ];
}, $conversationsStmt->fetchAll());

/* -----------------------------------------------------
   OPEN THREAD
   ?conversation=<id> selects which thread to show. On
   larger screens we default to the most recent conversation
   so the page never opens empty. On mobile, only an explicit
   ?conversation param opens the thread pane (see
   $isExplicitThread) so the visitor lands on the list first,
   same as a normal chat app.
----------------------------------------------------- */
$isExplicitThread = isset($_GET['conversation']);
$activeConversationId = $isExplicitThread
    ? (int) $_GET['conversation']
    : ($conversations[0]['id'] ?? null);

$activeConversation = null;
$threadMessages = [];

if ($activeConversationId !== null) {
    foreach ($conversations as $c) {
        if ($c['id'] === $activeConversationId) {
            $activeConversation = $c;
            break;
        }
    }

    if ($activeConversation) {
        $threadStmt = $pdo->prepare(
            "SELECT sender_id, body, created_at
             FROM messages
             WHERE conversation_id = :cid
             ORDER BY created_at ASC"
        );
        $threadStmt->execute(['cid' => $activeConversationId]);
        $threadMessages = $threadStmt->fetchAll();
    }
}

/**
 * Human day-divider label for a message timestamp, the way
 * most chat apps group messages ("Today", "Yesterday", then
 * a full date).
 */
function message_day_label($timestamp) {
    $day   = date('Y-m-d', $timestamp);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($day === $today) {
        return 'Today';
    }
    if ($day === $yesterday) {
        return 'Yesterday';
    }
    return date('F j, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — RoomHive</title>

<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<link rel="stylesheet" href="/webprogg/assets/usermessages.css">
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<section class="up-welcome">
  <div class="up-welcome-text">
    <p class="up-welcome-eyebrow">Messages</p>
    <h1>Your conversations</h1>
    <span class="up-welcome-underline"></span>
    <p class="up-welcome-sub">Talk with hosts about your bookings and questions.</p>
  </div>
  <div class="up-welcome-image">
    <img src="/webprogg/images/messagesicon-userprofile.png" alt="">
  </div>
</section>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if (empty($conversations)): ?>

      <!-- EMPTY STATE: no conversations at all yet -->
      <section class="up-card up-msg-card-empty">
        <div class="up-msg-list-header">
          <h2>Chats</h2>
        </div>
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

      <section class="up-card up-msg-card<?php echo $isExplicitThread ? ' up-msg-mobile-open' : ''; ?>">

        <!-- LIST PANE -->
        <div class="up-msg-list-pane">

          <div class="up-msg-list-header">
            <h2>Chats</h2>
            <a href="/webprogg/Listings/listing.php" class="up-msg-compose" title="Start a new conversation" aria-label="Start a new conversation">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14"/>
              </svg>
            </a>
          </div>

          <div class="up-msg-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
            </svg>
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
              <a href="usermessages.php?conversation=<?php echo h($c['id']); ?>"
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
                  <span class="up-msg-preview"><?php echo h($c['last_body']); ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>

        </div>

        <!-- OPEN THREAD -->
        <div class="up-msg-thread">
          <?php if ($activeConversation): ?>

            <div class="up-msg-thread-header">
              <a href="usermessages.php" class="up-msg-back" aria-label="Back to messages">&#8249;</a>

              <!-- Avatar + name link out to the host's public
                   profile page, so tenants can see who they're
                   chatting with beyond just the thread. -->
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
                <a href="/webprogg/host/hostpublicprofile.php?id=<?php echo h($activeConversation['host_id']); ?>"
                   class="up-msg-icon-btn" title="View host profile" aria-label="View host profile">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
                  </svg>
                </a>
                <?php if ($activeConversation['listing_id']): ?>
                  <a href="/webprogg/Listings/listingdetails.php?id=<?php echo h($activeConversation['listing_id']); ?>"
                     class="up-msg-icon-btn" title="View listing" aria-label="View listing">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                    </svg>
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
                  $ts = strtotime($m['created_at']);
                  $dayKey = date('Y-m-d', $ts);
                  $isMine = (int) $m['sender_id'] === (int) $_SESSION['user_id'];
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

            <form action="sendmessage.php" method="POST" class="up-msg-composer">
              <?php echo csrf_field(); ?>
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
            <p class="footer-tagline">
                Find, stay, relax, at home. RoomHive helps you discover
                comfortable stays across Negros Oriental.
            </p>
            <div class="footer-contact-line">
                <img src="/webprogg/images/PhoneIcon.jpg" alt="">
                <span>0927 569 3574</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/EmailIcon.jpg" alt="">
                <span>kimdivino55@gmail.com</span>
            </div>
            <div class="footer-contact-line">
                <img src="/webprogg/images/GPSIcon.png" alt="">
                <span>Dumaguete City, Negros Oriental, Philippines</span>
            </div>
        </div>

        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studioloft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=sharedbedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entirehouse">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>

        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>

        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>
<script>
  (function () {
    // Open the thread already scrolled to the newest message.
    var body = document.getElementById('msgThreadBody');
    if (body) {
      body.scrollTop = body.scrollHeight;
    }

    // Search + All/Unread filtering over the conversation list.
    // Purely client-side — every conversation is already in the
    // DOM, so no extra request is needed.
    var searchInput = document.getElementById('msgSearchInput');
    var tabsWrap = document.getElementById('msgTabs');
    var list = document.getElementById('msgList');
    if (!searchInput || !tabsWrap || !list) {
      return;
    }

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
        tabsWrap.querySelectorAll('.up-msg-tab').forEach(function (t) {
          t.classList.remove('active');
        });
        tab.classList.add('active');
        activeFilter = tab.getAttribute('data-filter');
        applyFilters();
      });
    });
  })();
</script>
</body>
</html>