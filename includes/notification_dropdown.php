<?php
/* =========================================================
   ROOMHIVE — NOTIFICATION BELL DROPDOWN (drop-in)
   includes/notification_dropdown.php

   Include ONCE per page, right AFTER the navbar include:

       <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

   Works for BOTH hosts and users (notifications are rows in
   `notifications` keyed by user_id — whoever is logged in
   sees their own). Self-contained: styles, markup, script.

   ROLE-AWARE LINKS (this fix):
     - Hosts   -> "View all notifications" opens
                  /webprogg/host/hostnotifications.php
     - Renters -> "View all notifications" opens
                  /webprogg/user/notifications.php

   FEATURES:
     - Red unread-count BADGE on the bell (real count, styled,
       decrements live, disappears at 0, caps at "99+").
     - Real message text from notifications.message (with a
       derived label fallback for old/empty rows).
     - Activity-type icon chips (message / accepted / declined /
       application / payment / review / booking / listing / etc).
     - Real listing cover photo thumbnails for booking/listing
       links, with an emoji fallback if the photo is missing.
     - Mark one read on click; "Mark all as read".

   NOTE: this file uses htmlspecialchars() directly (NOT a
   page-level h() helper) because it's included by pages that
   may not define h(). Keep it that way.

   Requires: session started + db_connect.php included.
========================================================= */

if (empty($_SESSION['user_id'])) {
    return; // guests: nothing to show
}

 $ndd_unread = 0;
 $ndd_items  = [];
 $ndd_ready  = true;

/* =========================================================
   ROLE-AWARE "VIEW ALL" DESTINATION
   Hosts land in their dashboard shell, renters in theirs.
========================================================= */
 $ndd_isHost    = !empty($_SESSION['is_host']);
 $ndd_viewAllUrl = $ndd_isHost
    ? '/webprogg/host/hostnotifications.php'
    : '/webprogg/user/notifications.php';

/* -----------------------------------------------------
   HELPERS (guarded so including pages never clash)
----------------------------------------------------- */

if (!function_exists('ndd_time_ago')) {
    function ndd_time_ago($ts) {
        $diff = time() - strtotime($ts);
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', strtotime($ts));
    }
}

if (!function_exists('ndd_label')) {
    function ndd_label($link) {
        $l = strtolower((string) $link);

        if (strpos($l, 'message') !== false)        return 'New message';
        if (strpos($l, 'accept') !== false)         return 'Booking accepted';
        if (strpos($l, 'reject') !== false)         return 'Booking declined';
        if (strpos($l, 'pendingtenants') !== false) return 'New tenant application';
        if (strpos($l, 'payment') !== false)        return 'Payment received';
        if (strpos($l, 'review') !== false
            || strpos($l, 'rating') !== false)      return 'New review';
        if (strpos($l, 'booking') !== false)        return 'Booking update';
        if (strpos($l, 'listing') !== false)        return 'Listing update';
        if (strpos($l, 'hostprofile') !== false)    return 'Host application update';
        if (strpos($l, 'membership') !== false)     return 'Hive Club update';

        return 'New notification';
    }
}

if (!function_exists('ndd_type')) {
    function ndd_type($link, $message) {
        $l = strtolower((string) $link);
        $m = strtolower((string) $message);

        if (strpos($l, 'message') !== false || strpos($l, 'chat') !== false) return 'message';

        if (strpos($l, 'accept') !== false
            || strpos($m, 'accepted') !== false
            || strpos($m, 'approved') !== false)                            return 'accepted';

        if (strpos($l, 'reject') !== false
            || strpos($m, 'declined') !== false
            || strpos($m, 'rejected') !== false)                            return 'declined';

        if (strpos($l, 'pendingtenants') !== false
            || strpos($m, 'application') !== false
            || strpos($m, 'applied') !== false
            || strpos($m, 'new tenant') !== false)                          return 'application';

        if (strpos($l, 'payment') !== false
            || strpos($m, 'payment') !== false
            || strpos($m, 'paid') !== false)                                return 'payment';

        if (strpos($l, 'review') !== false
            || strpos($l, 'rating') !== false
            || strpos($m, 'review') !== false)                              return 'review';

        if (strpos($l, 'booking') !== false)                                return 'booking';
        if (strpos($l, 'listing') !== false
            || strpos($m, 'listing') !== false)                             return 'listing';
        if (strpos($l, 'hostprofile') !== false)                            return 'hostapp';

        return 'system';
    }
}

if (!function_exists('ndd_icon')) {
    function ndd_icon($type) {
        switch ($type) {
            case 'message':     return ['&#128172;', 'rh-ndd-ic-blue'];    /* 💬 */
            case 'accepted':    return ['&#9989;',   'rh-ndd-ic-green'];   /* ✅ */
            case 'declined':    return ['&#10060;',  'rh-ndd-ic-red'];     /* ❌ */
            case 'application': return ['&#128221;', 'rh-ndd-ic-yellow'];  /* 📝 */
            case 'payment':     return ['&#128176;', 'rh-ndd-ic-green'];   /* 💰 */
            case 'review':      return ['&#11088;',  'rh-ndd-ic-yellow'];  /* ⭐ */
            case 'booking':     return ['&#128197;', 'rh-ndd-ic-blue'];    /* 📅 */
            case 'listing':     return ['&#127968;', 'rh-ndd-ic-honey'];   /* 🏠 */
            case 'hostapp':     return ['&#128100;', 'rh-ndd-ic-purple'];  /* 👤 */
            default:            return ['&#128276;', 'rh-ndd-ic-gray'];    /* 🔔 */
        }
    }
}

/* Resolve a stored photo path to an absolute web path.
   Handles: absolute URLs, "/webprogg/..." paths, "webprogg/..."
   prefixes, bare filenames (listing cover convention), and
   generic relative paths. Returns '' when there's nothing. */
if (!function_exists('ndd_resolve_photo')) {
    function ndd_resolve_photo($path) {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $p = ltrim($path, '/');
        if (stripos($p, 'webprogg/') === 0) {
            return '/' . $p;
        }
        /* Bare filename → listing cover convention (same as listing.php) */
        if (strpos($p, '/') === false) {
            return '/webprogg/uploads/listing_photos/cover/' . $p;
        }
        return '/webprogg/' . $p;
    }
}

/* -----------------------------------------------------
   LOAD DATA
----------------------------------------------------- */
try {
    /* Unread count (authoritative for the badge) */
    $nddCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
    );
    $nddCountStmt->execute(['u' => $_SESSION['user_id']]);
    $ndd_unread = (int) $nddCountStmt->fetchColumn();

    /* Latest 8 notifications — message column INCLUDED */
    $nddStmt = $pdo->prepare(
        "SELECT id, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = :u
         ORDER BY created_at DESC
         LIMIT 8"
    );
    $nddStmt->execute(['u' => $_SESSION['user_id']]);
    $ndd_rows = $nddStmt->fetchAll();

    /* -----------------------------------------------------
       COLLECT booking/listing ids from the links so we can
       batch-fetch their cover photos (2 queries max).
    ----------------------------------------------------- */
    $ndd_bookingIds = [];
    $ndd_listingIds = [];
    $ndd_parsed     = [];

    foreach ($ndd_rows as $row) {
        $link  = (string) ($row['link'] ?? '');
        $query = parse_url($link, PHP_URL_QUERY);
        parse_str((string) $query, $qs);
        $path  = strtolower((string) parse_url($link, PHP_URL_PATH));
        $id    = isset($qs['id']) && is_numeric($qs['id']) ? (int) $qs['id'] : 0;

        $ndd_parsed[$row['id']] = ['path' => $path, 'id' => $id];

        if ($id > 0 && strpos($path, 'booking-detail') !== false) {
            $ndd_bookingIds[] = $id;
        }
        if ($id > 0 && strpos($path, 'listing-detail') !== false) {
            $ndd_listingIds[] = $id;
        }
    }

    /* booking links → the booking's listing cover photo + title */
    $ndd_photos   = [];
    $ndd_ctxTitle = [];

    if (!empty($ndd_bookingIds)) {
        $in = implode(',', array_map('intval', array_unique($ndd_bookingIds)));
        $bStmt = $pdo->query(
            "SELECT b.id, l.title AS listing_title, p.photo_path
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN listing_photos p
                    ON p.listing_id = l.id AND p.photo_type = 'cover'
             WHERE b.id IN ($in)"
        );
        foreach ($bStmt->fetchAll() as $r) {
            $ndd_photos['b' . (int) $r['id']] = ndd_resolve_photo($r['photo_path']);
            $ndd_ctxTitle['b' . (int) $r['id']] = (string) $r['listing_title'];
        }
    }

    /* listing links → the listing's cover photo + title */
    if (!empty($ndd_listingIds)) {
        $in = implode(',', array_map('intval', array_unique($ndd_listingIds)));
        $lStmt = $pdo->query(
            "SELECT l.id, l.title, p.photo_path
             FROM listings l
             LEFT JOIN listing_photos p
                    ON p.listing_id = l.id AND p.photo_type = 'cover'
             WHERE l.id IN ($in)"
        );
        foreach ($lStmt->fetchAll() as $r) {
            $ndd_photos['L' . (int) $r['id']] = ndd_resolve_photo($r['photo_path']);
            $ndd_ctxTitle['L' . (int) $r['id']] = (string) $r['title'];
        }
    }

    /* -----------------------------------------------------
       BUILD ITEMS — real message first, derived label as
       fallback; photo when we found one; context title.
    ----------------------------------------------------- */
    foreach ($ndd_rows as $row) {
        $link    = (string) ($row['link'] ?? '');
        $message = trim((string) ($row['message'] ?? ''));
        $id      = (int) $row['id'];

        $type  = ndd_type($link, $message);
        $label = $message !== '' ? $message : ndd_label($link);

        $ctxKey = '';
        $imgKey = '';
        if (isset($ndd_parsed[$id])) {
            $path = $ndd_parsed[$id]['path'];
            $bid  = $ndd_parsed[$id]['id'];
            if ($bid > 0 && strpos($path, 'booking-detail') !== false) {
                $ctxKey = 'b' . $bid;
                $imgKey = 'b' . $bid;
            } elseif ($bid > 0 && strpos($path, 'listing-detail') !== false) {
                $ctxKey = 'L' . $bid;
                $imgKey = 'L' . $bid;
            }
        }

        $img = ($imgKey !== '' && isset($ndd_photos[$imgKey])) ? $ndd_photos[$imgKey] : '';

        list($emoji, $iconClass) = ndd_icon($type);

        $ndd_items[] = [
            'id'      => $id,
            'link'    => $link !== '' ? $link : $ndd_viewAllUrl,
            'is_read' => (int) $row['is_read'],
            'ago'     => ndd_time_ago($row['created_at']),
            'label'   => $label,
            'type'    => $type,
            'emoji'   => $emoji,
            'icls'    => $iconClass,
            'img'     => $img,
            'ctx'     => ($ctxKey !== '' && isset($ndd_ctxTitle[$ctxKey])) ? $ndd_ctxTitle[$ctxKey] : '',
        ];
    }

} catch (PDOException $e) {
    /* notifications table missing → render nothing rather than
       break the page. Fix the table, refresh. */
    $ndd_ready = false;
    error_log('notification_dropdown: ' . $e->getMessage());
}

/* Only render for logged-in users with a working table */
if (!empty($_SESSION['user_id']) && $ndd_ready):
?>

<style>
    /* ---- Notification dropdown (self-contained, rh-ndd-*) ---- */

    .rh-ndd-panel {
        display: none;
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: 340px;
        max-width: calc(100vw - 30px);
        background: #ffffff;
        border: 1px solid rgba(28, 42, 56, 0.08);
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(28, 42, 56, 0.18);
        overflow: hidden;
        z-index: 1200;
        font-family: "Poppins", sans-serif;
    }

    .rh-ndd-panel.open { display: block; animation: rhNddIn 0.2s ease; }

    @keyframes rhNddIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .rh-ndd-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(28, 42, 56, 0.08);
    }

    .rh-ndd-head strong {
        color: #1c2a38;
        font-size: 14px;
        font-weight: 800;
    }

    .rh-ndd-markall {
        background: none;
        border: none;
        padding: 0;
        color: #b07708;
        font-family: inherit;
        font-size: 11.5px;
        font-weight: 700;
        cursor: pointer;
    }

    .rh-ndd-markall:hover { text-decoration: underline; }

    .rh-ndd-list {
        max-height: 360px;
        overflow-y: auto;
    }

    .rh-ndd-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        width: 100%;
        padding: 12px 16px;
        background: none;
        border: none;
        border-bottom: 1px solid rgba(28, 42, 56, 0.06);
        text-align: left;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .rh-ndd-item:last-child { border-bottom: none; }
    .rh-ndd-item:hover { background: #fff8ec; }
    .rh-ndd-item.unread { background: #fff8ec; }
    .rh-ndd-item.unread:hover { background: #fdf1dc; }

    /* Unread dot on the far right edge */
    .rh-ndd-unread-dot {
        width: 8px;
        height: 8px;
        flex-shrink: 0;
        margin-top: 6px;
        background: #eda423;
        border-radius: 50%;
        align-self: center;
    }
    .rh-ndd-item:not(.unread) .rh-ndd-unread-dot {
        background: transparent;
        border: 1px solid rgba(28, 42, 56, 0.12);
    }

    /* ---- Activity icon chip (or real photo) ---- */
    .rh-ndd-ic {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        font-size: 16px;
        line-height: 1;
    }
    .rh-ndd-ic-blue   { background: #E7F0FF; }
    .rh-ndd-ic-green  { background: #E8F8F1; }
    .rh-ndd-ic-red    { background: #FDECEC; }
    .rh-ndd-ic-yellow { background: #FDF1DC; }
    .rh-ndd-ic-honey  { background: #FFF6E9; }
    .rh-ndd-ic-purple { background: #F1EBFF; }
    .rh-ndd-ic-gray   { background: #F0F1F6; }

    .rh-ndd-ic-photo {
        padding: 0;
        overflow: hidden;
        background: #F0EEE6;
    }
    .rh-ndd-ic-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .rh-ndd-ic-fallback {
        display: none;
        width: 100%;
        height: 100%;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: #FDF1DC;
    }

    .rh-ndd-item-body { flex: 1; min-width: 0; }

    .rh-ndd-item-label {
        margin: 0;
        color: #1c2a38;
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .rh-ndd-item.unread .rh-ndd-item-label { font-weight: 700; }

    .rh-ndd-item-ctx {
        display: block;
        margin-top: 1px;
        color: #8d99a5;
        font-size: 11px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rh-ndd-item-time {
        display: block;
        margin-top: 2px;
        color: #8d99a5;
        font-size: 11px;
    }

    .rh-ndd-empty {
        padding: 36px 16px;
        text-align: center;
        color: #8d99a5;
        font-size: 13px;
    }

    .rh-ndd-foot {
        display: block;
        padding: 12px 16px;
        border-top: 1px solid rgba(28, 42, 56, 0.08);
        text-align: center;
        color: #b07708;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .rh-ndd-foot:hover { background: #fff8ec; }

    /* Bell must anchor the panel + show a pointer cursor */
    .nav-bell {
        position: relative !important;
        cursor: pointer;
    }
    .nav-bell img { transition: transform 0.15s ease; }
    .nav-bell:hover img { transform: scale(1.1); }

    /* =====================================================
       UNREAD COUNT BADGE on the bell
       Red circle with the number of unread notifications.
       Position: top-right corner of the bell icon.
       JS creates/updates/removes it live (see script below);
       this CSS is what actually makes it visible.
    ====================================================== */
    .nav-bell-badge {
        position: absolute;
        top: -4px;
        right: -6px;

        min-width: 17px;
        height: 17px;
        padding: 0 4px;

        background: #E14B4B;

        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        line-height: 17px;
        text-align: center;

        border: 2px solid #ffffff;
        border-radius: 999px;

        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);

        z-index: 10;
    }
</style>

<!-- Panel markup — JS moves it inside the bell and wires it -->
<div class="rh-ndd-panel" id="rhNddPanel" data-unread="<?php echo (int) $ndd_unread; ?>" data-viewall="<?php echo htmlspecialchars($ndd_viewAllUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="rh-ndd-head">

        <strong>Notifications</strong>

        <?php if ($ndd_unread > 0): ?>
            <button type="button" class="rh-ndd-markall" id="rhNddMarkAll">
                Mark all as read
            </button>
        <?php endif; ?>

    </div>

    <div class="rh-ndd-list">

        <?php if (empty($ndd_items)): ?>

            <p class="rh-ndd-empty">No notifications yet</p>

        <?php else: ?>

            <?php foreach ($ndd_items as $n): ?>

                <button
                    type="button"
                    class="rh-ndd-item<?php echo $n['is_read'] ? '' : ' unread'; ?>"
                    data-id="<?php echo (int) $n['id']; ?>"
                    data-link="<?php echo htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8'); ?>"
                >

                    <?php if ($n['img'] !== ''): ?>
                        <!-- Real photo (listing cover) with emoji fallback -->
                        <span class="rh-ndd-ic rh-ndd-ic-photo">
                            <img
                                src="<?php echo htmlspecialchars($n['img'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt=""
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                            >
                            <span class="rh-ndd-ic-fallback"><?php echo $n['emoji']; ?></span>
                        </span>
                    <?php else: ?>
                        <!-- Activity-type icon chip -->
                        <span class="rh-ndd-ic <?php echo htmlspecialchars($n['icls'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $n['emoji']; ?></span>
                    <?php endif; ?>

                    <span class="rh-ndd-item-body">

                        <span class="rh-ndd-item-label">
                            <?php echo htmlspecialchars($n['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                        <?php if ($n['ctx'] !== ''): ?>
                            <span class="rh-ndd-item-ctx">
                                <?php echo htmlspecialchars($n['ctx'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>

                        <span class="rh-ndd-item-time">
                            <?php echo htmlspecialchars($n['ago'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                    </span>

                    <span class="rh-ndd-unread-dot"></span>

                </button>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <a class="rh-ndd-foot" href="<?php echo htmlspecialchars($ndd_viewAllUrl, ENT_QUOTES, 'UTF-8'); ?>">
        View all notifications
    </a>

</div>

<script>
(function () {
    "use strict";

    var panel = document.getElementById("rhNddPanel");
    if (!panel) { return; }

    /* Role-aware "view all" destination, read from the panel
       (hosts -> hostnotifications.php, renters -> user page). */
    var VIEW_ALL_URL = panel.getAttribute("data-viewall")
        || "/webprogg/user/notifications.php";

    /* ---- Find the bell and attach the panel to it ---- */
    var bell = document.querySelector(".nav-bell");

    if (bell) {
        if (window.getComputedStyle(bell).position === "static") {
            bell.style.position = "relative";
        }

        bell.appendChild(panel);

        bell.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            var isOpen = panel.classList.toggle("open");
            bell.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });
    }

    /* Stop clicks inside the panel from closing it */
    panel.addEventListener("click", function (event) {
        event.stopPropagation();
    });

    /* Close on outside click */
    document.addEventListener("click", function () {
        panel.classList.remove("open");
    });

    /* Close on Esc */
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            panel.classList.remove("open");
        }
    });

    /* =====================================================
       BADGE — red number on the bell showing unread count.
       - Created on load with the real count from the DB
       - Decrements by 1 when an unread item is clicked
       - Removed entirely when it hits 0 / "Mark all as read"
       - Caps the display at "99+"
    ====================================================== */
    var unread = parseInt(panel.getAttribute("data-unread"), 10) || 0;

    function updateBadge() {
        if (!bell) { return; }
        var badge = bell.querySelector(".nav-bell-badge");

        if (unread > 0) {
            if (!badge) {
                badge = document.createElement("span");
                badge.className = "nav-bell-badge";
                bell.appendChild(badge);
            }
            badge.textContent = unread > 99 ? "99+" : String(unread);
        } else if (badge) {
            badge.remove();
        }
    }

    updateBadge();

    /* ---- Mark helpers ---- */
    function markRead(id, markAll) {
        var body = markAll ? "all=1" : "id=" + encodeURIComponent(id);

        return fetch("/webprogg/notifications/mark-notifications-read.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body,
            credentials: "same-origin"
        }).catch(function () {});
    }

    /* ---- Clicking a notification: mark read, decrement badge, navigate ---- */
    panel.querySelectorAll(".rh-ndd-item").forEach(function (item) {

        item.addEventListener("click", function () {

            var id   = item.getAttribute("data-id");
            var link = item.getAttribute("data-link") || VIEW_ALL_URL;

            if (item.classList.contains("unread")) {
                item.classList.remove("unread");
                unread = Math.max(0, unread - 1);
                updateBadge();
                markRead(id, false);
            }

            window.location.href = link;
        });
    });

    /* ---- Mark all as read ---- */
    var markAllBtn = document.getElementById("rhNddMarkAll");

    if (markAllBtn) {
        markAllBtn.addEventListener("click", function () {

            panel.querySelectorAll(".rh-ndd-item.unread").forEach(function (item) {
                item.classList.remove("unread");
            });

            unread = 0;
            updateBadge();
            markAllBtn.remove();

            markRead(0, true);
        });
    }
})();
</script>

<?php endif; ?>