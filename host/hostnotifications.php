<?php
/* =========================================================
   ROOMHIVE — HOST NOTIFICATIONS (full page)
   host/hostnotifications.php

   The host-side notifications page (host dashboard shell).
   The renter equivalent lives at /webprogg/user/notifications.php.

   FEATURES:
     - All / Unread tabs (filter preserved across pagination)
     - Real message text from notifications.message
     - Activity icons + listing cover photos for
       booking/listing links
     - Click a row = mark read + navigate
     - "Mark all as read"
     - Pagination (20/page)
     - Sidebar badge reuses this page's unread count (no
       duplicate query) — see $hostNotifUnread below.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

 $stmt = $pdo->prepare(
    "SELECT id, name, email, avatar_path, is_host, created_at FROM users WHERE id = :id LIMIT 1"
);
 $stmt->execute(['id' => $_SESSION['user_id']]);
 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/user/notifications.php");
    exit;
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

 $_SESSION['avatar_path'] = $dbUser['avatar_path'] ?? null;
 $navAvatar = $_SESSION['avatar_path'] ?? '/webprogg/images/default-avatar.png';

 $notification_count = 0;

/* -----------------------------------------------------
   HELPERS (np_ prefix — cannot clash with the dropdown)
----------------------------------------------------- */

if (!function_exists('np_time_ago')) {
    function np_time_ago($ts) {
        $diff = time() - strtotime($ts);
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', strtotime($ts));
    }
}

if (!function_exists('np_type')) {
    function np_type($link, $message) {
        $l = strtolower((string) $link);
        $m = strtolower((string) $message);

        if (strpos($l, 'message') !== false || strpos($l, 'chat') !== false) return 'message';
        if (strpos($l, 'accept') !== false || strpos($m, 'accepted') !== false
            || strpos($m, 'approved') !== false)                             return 'accepted';
        if (strpos($l, 'reject') !== false || strpos($m, 'declined') !== false
            || strpos($m, 'rejected') !== false)                             return 'declined';
        if (strpos($l, 'pendingtenants') !== false || strpos($m, 'application') !== false
            || strpos($m, 'applied') !== false)                              return 'application';
        if (strpos($l, 'payment') !== false || strpos($m, 'payment') !== false
            || strpos($m, 'paid') !== false || strpos($m, 'refunded') !== false) return 'payment';
        if (strpos($l, 'review') !== false || strpos($l, 'rating') !== false
            || strpos($m, 'review') !== false)                               return 'review';
        if (strpos($l, 'booking') !== false)                                 return 'booking';
        if (strpos($l, 'listing') !== false || strpos($m, 'listing') !== false) return 'listing';

        return 'system';
    }
}

if (!function_exists('np_icon')) {
    function np_icon($type) {
        switch ($type) {
            case 'message':     return ['&#128172;', 'np-ic-blue'];
            case 'accepted':    return ['&#9989;',   'np-ic-green'];
            case 'declined':    return ['&#10060;',  'np-ic-red'];
            case 'application': return ['&#128221;', 'np-ic-yellow'];
            case 'payment':     return ['&#128176;', 'np-ic-green'];
            case 'review':      return ['&#11088;',  'np-ic-yellow'];
            case 'booking':     return ['&#128197;', 'np-ic-blue'];
            case 'listing':     return ['&#127968;', 'np-ic-honey'];
            default:            return ['&#128276;', 'np-ic-gray'];
        }
    }
}

if (!function_exists('np_resolve_photo')) {
    function np_resolve_photo($path) {
        $path = trim((string) $path);
        if ($path === '') { return ''; }
        if (preg_match('#^https?://#i', $path)) { return $path; }
        $p = ltrim($path, '/');
        if (stripos($p, 'webprogg/') === 0) { return '/' . $p; }
        if (strpos($p, '/') === false) {
            return '/webprogg/uploads/listing_photos/cover/' . $p;
        }
        return '/webprogg/' . $p;
    }
}

/* -----------------------------------------------------
   FILTER (all | unread)
----------------------------------------------------- */
 $filter = (isset($_GET['filter']) && $_GET['filter'] === 'unread') ? 'unread' : 'all';

/* -----------------------------------------------------
   PAGINATION + DATA
----------------------------------------------------- */
 $perPage = 20;
 $page    = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

 $countWhere = ($filter === 'unread') ? " AND is_read = 0" : "";

 $totalStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = :u" . $countWhere
);
 $totalStmt->execute(['u' => $_SESSION['user_id']]);
 $totalItems  = (int) $totalStmt->fetchColumn();
 $totalPages  = max(1, (int) ceil($totalItems / $perPage));
 $page        = min($page, $totalPages);
 $offset      = ($page - 1) * $perPage;

 $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0");
 $unreadStmt->execute(['u' => $_SESSION['user_id']]);
 $unreadCount = (int) $unreadStmt->fetchColumn();

/* Reuse the count for the sidebar badge — no duplicate query */
 $hostNotifUnread = $unreadCount;

 $rowsStmt = $pdo->prepare(
    "SELECT id, message, link, is_read, created_at
     FROM notifications
     WHERE user_id = :u" . $countWhere . "
     ORDER BY created_at DESC
     LIMIT :lim OFFSET :off"
);
 $rowsStmt->bindValue(':u', $_SESSION['user_id'], PDO::PARAM_INT);
 $rowsStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
 $rowsStmt->bindValue(':off', $offset, PDO::PARAM_INT);
 $rowsStmt->execute();
 $rows = $rowsStmt->fetchAll();

/* Batch-fetch cover photos + titles for booking/listing links */
 $bookingIds = [];
 $listingIds = [];
 $parsed     = [];

foreach ($rows as $row) {
    $link = (string) ($row['link'] ?? '');
    parse_str((string) parse_url($link, PHP_URL_QUERY), $qs);
    $path = strtolower((string) parse_url($link, PHP_URL_PATH));
    $id   = isset($qs['id']) && is_numeric($qs['id']) ? (int) $qs['id'] : 0;

    $parsed[$row['id']] = ['path' => $path, 'id' => $id];

    if ($id > 0 && strpos($path, 'booking-detail') !== false) { $bookingIds[] = $id; }
    if ($id > 0 && strpos($path, 'listing-detail') !== false) { $listingIds[] = $id; }
}

 $photos = [];
 $titles = [];

if (!empty($bookingIds)) {
    $in = implode(',', array_map('intval', array_unique($bookingIds)));
    foreach ($pdo->query(
        "SELECT b.id, l.title, p.photo_path
         FROM bookings b
         JOIN listings l ON l.id = b.listing_id
         LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
         WHERE b.id IN ($in)"
    )->fetchAll() as $r) {
        $photos['b' . (int) $r['id']] = np_resolve_photo($r['photo_path']);
        $titles['b' . (int) $r['id']] = (string) $r['title'];
    }
}

if (!empty($listingIds)) {
    $in = implode(',', array_map('intval', array_unique($listingIds)));
    foreach ($pdo->query(
        "SELECT l.id, l.title, p.photo_path
         FROM listings l
         LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.photo_type = 'cover'
         WHERE l.id IN ($in)"
    )->fetchAll() as $r) {
        $photos['L' . (int) $r['id']] = np_resolve_photo($r['photo_path']);
        $titles['L' . (int) $r['id']] = (string) $r['title'];
    }
}

/* -----------------------------------------------------
   BUILD ITEM MARKUP
----------------------------------------------------- */
 $itemsHtml = '';

foreach ($rows as $row) {
    $id      = (int) $row['id'];
    $link    = (string) ($row['link'] ?? '');
    $message = trim((string) ($row['message'] ?? ''));
    $isRead  = (int) $row['is_read'];

    $type  = np_type($link, $message);
    list($emoji, $icls) = np_icon($type);

    $ctxKey = '';
    $imgKey = '';
    if (isset($parsed[$id])) {
        $path = $parsed[$id]['path'];
        $bid  = $parsed[$id]['id'];
        if ($bid > 0 && strpos($path, 'booking-detail') !== false)      { $ctxKey = $imgKey = 'b' . $bid; }
        elseif ($bid > 0 && strpos($path, 'listing-detail') !== false) { $ctxKey = $imgKey = 'L' . $bid; }
    }

    $img = ($imgKey !== '' && isset($photos[$imgKey])) ? $photos[$imgKey] : '';
    $ctx = ($ctxKey !== '' && isset($titles[$ctxKey])) ? $titles[$ctxKey] : '';

    ob_start();
    ?>
    <button
        type="button"
        class="np-item<?php echo $isRead ? '' : ' unread'; ?>"
        data-id="<?php echo $id; ?>"
        data-link="<?php echo h($link !== '' ? $link : '/webprogg/host/hostnotifications.php'); ?>"
    >
        <?php if ($img !== ''): ?>
            <span class="np-ic np-ic-photo">
                <img src="<?php echo h($img); ?>" alt=""
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <span class="np-ic-fallback"><?php echo $emoji; ?></span>
            </span>
        <?php else: ?>
            <span class="np-ic <?php echo h($icls); ?>"><?php echo $emoji; ?></span>
        <?php endif; ?>

        <span class="np-body">
            <span class="np-label"><?php echo h($message !== '' ? $message : 'New notification'); ?></span>
            <?php if ($ctx !== ''): ?>
                <span class="np-ctx"><?php echo h($ctx); ?></span>
            <?php endif; ?>
            <span class="np-time"><?php echo h(np_time_ago($row['created_at'])); ?></span>
        </span>

        <span class="np-dot"></span>
    </button>
    <?php
    $itemsHtml .= ob_get_clean();
}

/* -----------------------------------------------------
   SHELL VARIABLES (host sidebar contract)
----------------------------------------------------- */
 $host = [
    'name'         => $dbUser['name'],
    'avatar'       => $navAvatar,
    'member_since' => date('F Y', strtotime($dbUser['created_at'])),
];

 $pcStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b
     JOIN listings l ON l.id = b.listing_id
     WHERE l.user_id = :h AND b.status = 'pending'"
);
 $pcStmt->execute(['h' => $_SESSION['user_id']]);
 $pending_tenants_count = (int) $pcStmt->fetchColumn();

 $activePage = 'notifications';

 $tabAll    = '/webprogg/host/hostnotifications.php';
 $tabUnread = '/webprogg/host/hostnotifications.php?filter=unread';

function np_page_url($page, $filter) {
    return '/webprogg/host/hostnotifications.php?page=' . $page
         . ($filter === 'unread' ? '&filter=unread' : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — RoomHive</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=6">

<style>
    /* ---- Notifications page (np-*, host shell) ---- */
    .np-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .np-head h2 { margin: 0; font-size: 20px; font-weight: 800; color: #1c2a38; }

    .np-unread-pill {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        background: #FDF1DC;
        border: 1px solid rgba(237, 164, 35, 0.5);
        color: #B07708;
        font-size: 11.5px;
        font-weight: 800;
        margin-left: 8px;
        vertical-align: middle;
    }

    .np-markall {
        background: linear-gradient(135deg, #f6b93b, #eda423);
        border: none;
        color: #1c2a38;
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 800;
        padding: 10px 18px;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(237, 164, 35, 0.35);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .np-markall:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(237, 164, 35, 0.45); }

    .np-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .np-tab {
        padding: 8px 16px;
        background: #ffffff;
        color: #6b7684;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: background .15s ease, color .15s ease, border-color .15s ease;
    }
    .np-tab:hover { border-color: #eda423; color: #b07708; }
    .np-tab.active {
        background: #FDF1DC;
        color: #b07708;
        border-color: #eda423;
    }

    .np-list { display: flex; flex-direction: column; }

    .np-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        padding: 14px 12px;
        background: none;
        border: none;
        border-bottom: 1px solid rgba(28, 42, 56, 0.06);
        text-align: left;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.15s ease;
        border-radius: 10px;
    }
    .np-item:hover { background: #fff8ec; }
    .np-item.unread { background: #fff8ec; }
    .np-item.unread:hover { background: #fdf1dc; }

    .np-dot {
        width: 9px; height: 9px;
        flex-shrink: 0;
        align-self: center;
        background: #eda423;
        border-radius: 50%;
    }
    .np-item:not(.unread) .np-dot {
        background: transparent;
        border: 1px solid rgba(28, 42, 56, 0.12);
    }

    .np-ic {
        width: 42px; height: 42px;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
        font-size: 18px;
        line-height: 1;
    }
    .np-ic-blue   { background: #E7F0FF; }
    .np-ic-green  { background: #E8F8F1; }
    .np-ic-red    { background: #FDECEC; }
    .np-ic-yellow { background: #FDF1DC; }
    .np-ic-honey  { background: #FFF6E9; }
    .np-ic-gray   { background: #F0F1F6; }

    .np-ic-photo { padding: 0; overflow: hidden; background: #F0EEE6; }
    .np-ic-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .np-ic-fallback {
        display: none;
        width: 100%; height: 100%;
        align-items: center; justify-content: center;
        font-size: 18px;
        background: #FDF1DC;
    }

    .np-body { flex: 1; min-width: 0; }

    .np-label {
        display: block;
        margin: 0;
        color: #1c2a38;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .np-item.unread .np-label { font-weight: 700; }

    .np-ctx {
        display: block;
        margin-top: 2px;
        color: #8d99a5;
        font-size: 11.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .np-time {
        display: block;
        margin-top: 3px;
        color: #8d99a5;
        font-size: 11px;
    }

    .np-empty {
        text-align: center;
        padding: 60px 20px;
        color: #8d99a5;
        font-size: 14px;
    }
    .np-empty-icon { font-size: 40px; display: block; margin-bottom: 12px; }

    .np-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding-top: 18px;
    }
    .np-page-btn {
        padding: 8px 16px;
        border-radius: 999px;
        border: 1px solid rgba(28, 42, 56, 0.08);
        background: #fff;
        color: #1c2a38;
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
        transition: border-color .15s ease, color .15s ease;
    }
    .np-page-btn:hover { border-color: #eda423; color: #b07708; }
    .np-page-btn.active {
        background: #FDF1DC;
        border-color: #eda423;
        color: #b07708;
    }
    .np-page-btn[disabled] { opacity: 0.45; cursor: default; }

    @media (prefers-reduced-motion: reduce) {
        .np-item, .np-markall { transition: none !important; }
    }
</style>
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

  <?php include __DIR__ . '/host_sidebar.php'; ?>

  <div class="hp-content">

    <div class="hp-page-header">
      <div>
        <h1 class="hp-page-title">Notifications</h1>
        <p class="hp-page-subtitle">New reserves, payments, cancellations and everything else on your listings.</p>
      </div>
    </div>

    <div class="hp-card">

        <div class="np-head">
            <h2>
                Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="np-unread-pill"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h2>
            <?php if ($unreadCount > 0): ?>
                <button type="button" class="np-markall" id="npMarkAll">Mark all as read</button>
            <?php endif; ?>
        </div>

        <!-- ALL / UNREAD tabs -->
        <div class="np-tabs">
            <a class="np-tab<?php echo $filter === 'all' ? ' active' : ''; ?>" href="<?php echo h($tabAll); ?>">
                All
            </a>
            <a class="np-tab<?php echo $filter === 'unread' ? ' active' : ''; ?>" href="<?php echo h($tabUnread); ?>">
                Unread<?php echo $unreadCount > 0 ? ' (' . $unreadCount . ')' : ''; ?>
            </a>
        </div>

        <div class="np-list" id="npList">
            <?php echo $itemsHtml ?: (
                $filter === 'unread'
                    ? '<div class="np-empty"><span class="np-empty-icon">&#10003;</span>You\'re all caught up — no unread notifications.</div>'
                    : '<div class="np-empty"><span class="np-empty-icon">&#128276;</span>No notifications yet. Reserves, payments and cancellations on your listings will show up here.</div>'
            ); ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="np-pagination">
            <?php if ($page > 1): ?>
                <a class="np-page-btn" href="<?php echo h(np_page_url($page - 1, $filter)); ?>">&larr; Newer</a>
            <?php else: ?>
                <span class="np-page-btn" disabled>&larr; Newer</span>
            <?php endif; ?>

            <span class="np-page-btn active"><?php echo $page; ?> / <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a class="np-page-btn" href="<?php echo h(np_page_url($page + 1, $filter)); ?>">Older &rarr;</a>
            <?php else: ?>
                <span class="np-page-btn" disabled>Older &rarr;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

  </div>
</main>

<script src="/webprogg/assets/javaScript.js"></script>

<script>
(function () {
    "use strict";

    /* ---- Click a notification: mark read, then navigate ---- */
    document.querySelectorAll('.np-item').forEach(function (item) {
        item.addEventListener('click', function () {
            var id   = item.getAttribute('data-id');
            var link = item.getAttribute('data-link') || '/webprogg/host/hostnotifications.php';

            if (item.classList.contains('unread')) {
                item.classList.remove('unread');

                fetch('/webprogg/notifications/mark-notifications-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id),
                    credentials: 'same-origin'
                }).catch(function () {});
            }

            window.location.href = link;
        });
    });

    /* ---- Mark all as read ---- */
    var markAllBtn = document.getElementById('npMarkAll');

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.np-item.unread').forEach(function (item) {
                item.classList.remove('unread');
            });

            var pill = document.querySelector('.np-unread-pill');
            if (pill) { pill.remove(); }

            markAllBtn.remove();

            fetch('/webprogg/notifications/mark-notifications-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'all=1',
                credentials: 'same-origin'
            }).catch(function () {});
        });
    }
})();
</script>

</body>
</html>