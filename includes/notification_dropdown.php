<?php
/* =========================================================
   ROOMHIVE — NOTIFICATION BELL DROPDOWN (drop-in)
   includes/notification_dropdown.php

   Include ONCE per page, right AFTER the navbar include:

       <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>

   Works for BOTH hosts and users (notifications are rows in
   `notifications` keyed by user_id — whoever is logged in
   sees their own). Self-contained: styles, markup, script.
   Auto-attaches to whichever .nav-bell the page's navbar
   rendered, updates the badge with the real unread count,
   marks notifications read on click, and has
   "Mark all as read".

   Requires: session started + db_connect.php included
   (both are always true before the navbar on every page).
========================================================= */

if (empty($_SESSION['user_id'])) {
    return; // guests: nothing to show
}

 $ndd_unread = 0;
 $ndd_items  = [];
 $ndd_ready  = true;

try {
    /* Unread count (authoritative for the badge) */
    $nddCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
    );
    $nddCountStmt->execute(['u' => $_SESSION['user_id']]);
    $ndd_unread = (int) $nddCountStmt->fetchColumn();

    /* Latest 8 notifications */
    $nddStmt = $pdo->prepare(
        "SELECT id, link, is_read, created_at
         FROM notifications
         WHERE user_id = :u
         ORDER BY created_at DESC
         LIMIT 8"
    );
    $nddStmt->execute(['u' => $_SESSION['user_id']]);

    /* Time-ago helper */
    if (!function_exists('nt_time_ago')) {
        function nt_time_ago($ts) {
            $diff = time() - strtotime($ts);
            if ($diff < 60)     return 'Just now';
            if ($diff < 3600)   return floor($diff / 60) . 'm ago';
            if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
            if ($diff < 604800) return floor($diff / 86400) . 'd ago';
            return date('M j, Y', strtotime($ts));
        }
    }

    /* Human label derived from the stored link (the
       notifications table stores user_id + link + is_read +
       created_at — the link tells us what happened). */
    if (!function_exists('nt_label')) {
        function nt_label($link) {
            $l = strtolower($link);

            if (strpos($l, 'message') !== false)          return 'New message';
            if (strpos($l, 'accept') !== false)           return 'Booking accepted';
            if (strpos($l, 'reject') !== false)           return 'Booking declined';
            if (strpos($l, 'booking-detail') !== false)   return 'Booking update';
            if (strpos($l, 'pendingtenants') !== false)   return 'New tenant application';
            if (strpos($l, 'hostbookings') !== false)     return 'New booking';
            if (strpos($l, 'hostreview') !== false
                || strpos($l, 'userreview') !== false)    return 'New review';
            if (strpos($l, 'hostprofile') !== false)      return 'Host application update';
            if (strpos($l, 'listingpayment') !== false)   return 'Payment received';
            if (strpos($l, 'membership') !== false)       return 'Hive Club update';

            return 'New notification';
        }
    }

    foreach ($nddStmt->fetchAll() as $row) {
        $ndd_items[] = [
            'id'       => (int) $row['id'],
            'link'     => $row['link'] ?: '/webprogg/user/notifications.php',
            'is_read'  => (int) $row['is_read'],
            'ago'      => nt_time_ago($row['created_at']),
            'label'    => nt_label($row['link']),
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

        width: 330px;
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
        max-height: 340px;
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

    .rh-ndd-item.unread {
        background: #fff8ec;
    }

    .rh-ndd-item.unread:hover { background: #fdf1dc; }

    .rh-ndd-dot {
        width: 8px;
        height: 8px;
        flex-shrink: 0;

        margin-top: 5px;

        background: #eda423;
        border-radius: 50%;
    }

    .rh-ndd-item:not(.unread) .rh-ndd-dot {
        background: transparent;
        border: 1px solid rgba(28, 42, 56, 0.15);
    }

    .rh-ndd-item-body { flex: 1; min-width: 0; }

    .rh-ndd-item-label {
        margin: 0;

        color: #1c2a38;

        font-size: 13px;
        font-weight: 600;

        line-height: 1.4;
    }

    .rh-ndd-item.unread .rh-ndd-item-label { font-weight: 700; }

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
</style>

<!-- Panel markup — JS moves it inside the bell and wires it -->
<div class="rh-ndd-panel" id="rhNddPanel" data-unread="<?php echo (int) $ndd_unread; ?>">

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

                    <span class="rh-ndd-dot"></span>

                    <span class="rh-ndd-item-body">

                        <span class="rh-ndd-item-label">
                            <?php echo htmlspecialchars($n['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                        <span class="rh-ndd-item-time">
                            <?php echo htmlspecialchars($n['ago'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                    </span>

                </button>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <a class="rh-ndd-foot" href="/webprogg/user/notifications.php">
        View all notifications
    </a>

</div>

<script>
(function () {
    "use strict";

    var panel = document.getElementById("rhNddPanel");
    if (!panel) { return; }

    /* ---- Find the bell and attach the panel to it ---- */
    var bell = document.querySelector(".nav-bell");

    if (bell) {
        /* Panel must anchor to the bell */
        if (window.getComputedStyle(bell).position === "static") {
            bell.style.position = "relative";
        }

        bell.appendChild(panel);

        /* Bell click toggles the panel instead of navigating.
           "View all" inside the panel is the full-page link. */
        bell.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();

            var isOpen = panel.classList.toggle("open");

            /* keep aria state on the bell for accessibility */
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

    /* ---- Badge: real unread count, create/update it ---- */
    var unread = parseInt(panel.getAttribute("data-unread"), 10) || 0;

    if (bell) {
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

    /* ---- Mark helpers ---- */
    function markRead(id, markAll) {
        var body = markAll
            ? "all=1"
            : "id=" + encodeURIComponent(id);

        return fetch("/webprogg/notifications/mark-notifications-read.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body,
            credentials: "same-origin"
        }).catch(function () {});
    }

    function refreshBadge() {
        var badge = bell ? bell.querySelector(".nav-bell-badge") : null;
        if (badge) { badge.remove(); }
    }

    /* ---- Clicking a notification: mark read, then navigate ---- */
    panel.querySelectorAll(".rh-ndd-item").forEach(function (item) {
        item.addEventListener("click", function () {

            var id  = item.getAttribute("data-id");
            var link = item.getAttribute("data-link") || "/webprogg/user/notifications.php";

            var wasUnread = item.classList.contains("unread");

            if (wasUnread) {
                item.classList.remove("unread");
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

            refreshBadge();
            markAllBtn.remove();

            markRead(0, true);
        });
    }
})();
</script>

<?php endif; ?>