<?php
/**adminusers.php
 * RoomHive Admin — Users
 * Lists every registered user (all logged-in accounts live in the
 * `users` table) with a Delete action. Deleting a user is destructive
 * and cascades across every table that references that user.
 *
 * CASCADE (kept): covers every user-referencing table — listings
 * (+ their bookings/reviews/wishlist/photos), conversations in BOTH
 * directions (+ their messages), messages sent in other parties'
 * threads, own bookings/reviews/wishlist, notifications,
 * saved_searches, support_tickets, notification_settings,
 * notification_preferences, payouts, payout_methods, Hive Club,
 * host_applications, and the account itself. Conversations attached
 * to the user's LISTINGS (owned by other parties) are DETACHED.
 *
 * NEW (this version):
 *   - NAV ALIGNMENT: working ☰ menu button (off-canvas sidebar +
 *     overlay under 1000px), topbar shadow on scroll.
 *   - "Hive Club" nav entry added (adminhiveclub.php).
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/*
 * =========================================================
 * ADMIN AUTH GUARD
 * =========================================================
 */
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: /webprogg/auth/adminlogin.php');
    exit();
}

 $adminName  = $_SESSION['admin_name']  ?? 'Admin User';
 $adminEmail = $_SESSION['admin_email'] ?? '';

/* ---------- CSRF token (per-session) ---------- */
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['admin_csrf'];

/*
 * =========================================================
 * DELETE USER (full cascading delete)
 * =========================================================
 */
 $flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $targetUserId = (int) $_POST['delete_user_id'];
    $tokenOk      = isset($_POST['csrf_token']) && hash_equals($csrfToken, $_POST['csrf_token']);

    if ($tokenOk && $targetUserId > 0) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $targetUserId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $pdo->beginTransaction();
            try {

                /* =============================================
                   1. LISTINGS OWNED BY THE USER
                   ============================================= */
                $stmt = $pdo->prepare("SELECT id FROM listings WHERE user_id = :uid");
                $stmt->execute(['uid' => $targetUserId]);
                $ownedListingIds = array_column($stmt->fetchAll(), 'id');

                if ($ownedListingIds) {
                    $placeholders = implode(',', array_fill(0, count($ownedListingIds), '?'));

                    /* Detach conversations pointing at these listings
                       (other parties' chat history is kept). listing_id
                       is nullable after the schema migration. */
                    try {
                        $convCols = $pdo->query("SHOW COLUMNS FROM conversations")
                                        ->fetchAll(PDO::FETCH_COLUMN);
                        if (in_array('listing_id', $convCols, true)) {
                            $pdo->prepare("UPDATE conversations SET listing_id = NULL WHERE listing_id IN ($placeholders)")
                                ->execute($ownedListingIds);
                        }
                    } catch (PDOException $e) {
                        error_log('adminusers cascade: conversations detach skipped: ' . $e->getMessage());
                    }

                    $pdo->prepare("DELETE FROM bookings WHERE listing_id IN ($placeholders)")
                        ->execute($ownedListingIds);

                    $pdo->prepare("DELETE FROM reviews WHERE listing_id IN ($placeholders)")
                        ->execute($ownedListingIds);

                    $pdo->prepare("DELETE FROM wishlist WHERE listing_id IN ($placeholders)")
                        ->execute($ownedListingIds);

                    $pdo->prepare("DELETE FROM listing_photos WHERE listing_id IN ($placeholders)")
                        ->execute($ownedListingIds);

                    $pdo->prepare("DELETE FROM listings WHERE id IN ($placeholders)")
                        ->execute($ownedListingIds);
                }

                /* =============================================
                   2. CONVERSATIONS WHERE THEY'RE TENANT OR HOST
                   (+ every message inside them)
                   ============================================= */
                try {
                    $convStmt = $pdo->prepare(
                        "SELECT id FROM conversations WHERE user_id = :uid OR host_id = :uid2"
                    );
                    $convStmt->execute([
                        'uid'  => $targetUserId,
                        'uid2' => $targetUserId,
                    ]);
                    $convIds = array_column($convStmt->fetchAll(), 'id');

                    if ($convIds) {
                        $convPh = implode(',', array_fill(0, count($convIds), '?'));
                        $pdo->prepare("DELETE FROM messages WHERE conversation_id IN ($convPh)")
                            ->execute($convIds);
                        $pdo->prepare("DELETE FROM conversations WHERE id IN ($convPh)")
                            ->execute($convIds);
                    }

                    /* Messages they sent inside OTHER parties' threads. */
                    $pdo->prepare("DELETE FROM messages WHERE sender_id = :uid")
                        ->execute(['uid' => $targetUserId]);

                } catch (PDOException $e) {
                    error_log('adminusers cascade: conversations/messages: ' . $e->getMessage());
                }

                /* =============================================
                   3. THEIR OWN ROWS
                   ============================================= */
                $pdo->prepare("DELETE FROM bookings WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM reviews WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM wishlist WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                /* =============================================
                   4. NOTIFICATIONS
                   ============================================= */
                $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                /* =============================================
                   5. OTHER USER-KEYED TABLES
                   ============================================= */
                $pdo->prepare("DELETE FROM saved_searches WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM support_tickets WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM notification_settings WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM notification_preferences WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM payouts WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM payout_methods WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                /* =============================================
                   6. HIVE CLUB (ledger cleaned via user_id)
                   ============================================= */
                $stmt = $pdo->prepare("SELECT id FROM hive_members WHERE user_id = :uid");
                $stmt->execute(['uid' => $targetUserId]);
                $hiveMemberIds = array_column($stmt->fetchAll(), 'id');

                if ($hiveMemberIds) {
                    $placeholders = implode(',', array_fill(0, count($hiveMemberIds), '?'));
                    $pdo->prepare("DELETE FROM hiveclub_transactions WHERE hive_member_id IN ($placeholders)")
                        ->execute($hiveMemberIds);
                }
                $pdo->prepare("DELETE FROM hiveclub_transactions WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM hive_redemptions WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM hive_points_ledger WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM hive_members WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                /* =============================================
                   7. HOST APPLICATION + THE ACCOUNT ITSELF
                   ============================================= */
                $pdo->prepare("DELETE FROM host_applications WHERE user_id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM users WHERE id = :uid")
                    ->execute(['uid' => $targetUserId]);

                $pdo->commit();

                header('Location: /webprogg/admin/adminusers.php?deleted=' . urlencode($targetUser['name']));
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('adminusers delete failed: ' . $e->getMessage());
                header('Location: /webprogg/admin/adminusers.php?error=delete_failed');
                exit();
            }
        } else {
            header('Location: /webprogg/admin/adminusers.php?error=not_found');
            exit();
        }
    } else {
        header('Location: /webprogg/admin/adminusers.php?error=bad_request');
        exit();
    }
}

if (isset($_GET['deleted'])) {
    $flash = ['type' => 'success', 'text' => htmlspecialchars($_GET['deleted']) . ' was deleted, along with all of their data.'];
} elseif (isset($_GET['error'])) {
    $messages = [
        'delete_failed' => 'Something went wrong while deleting that user. Nothing was changed.',
        'not_found'     => 'That user no longer exists.',
        'bad_request'   => 'That request could not be verified. Please try again.',
    ];
    $flash = ['type' => 'error', 'text' => $messages[$_GET['error']] ?? 'Something went wrong.'];
}

/* ---------- Sidebar navigation (Hive Club added) ---------- */
 $navItems = [
    ['label' => 'Dashboard',            'icon' => 'home',       'href' => '/webprogg/admin/admin.php'],
    ['label' => 'Users',                'icon' => 'users',      'active' => true, 'href' => '/webprogg/admin/adminusers.php'],
    ['label' => 'Bookings',             'icon' => 'calendar',   'href' => '/webprogg/admin/adminbookings.php'],
    ['label' => 'Listings',             'icon' => 'listing',    'href' => '/webprogg/admin/adminlistings.php'],
    ['label' => 'Listings Application', 'icon' => 'clipboard',  'href' => '/webprogg/admin/listingapplication.php'],
    ['label' => 'Host Applications',    'icon' => 'user-check', 'href' => '/webprogg/admin/hostapplication.php'],
    ['label' => 'Payouts',              'icon' => 'wallet',     'href' => '/webprogg/admin/adminpayouts.php'],
    ['label' => 'Hive Club',            'icon' => 'tag',        'href' => '/webprogg/admin/adminhiveclub.php'],
    ['label' => 'Reviews',              'icon' => 'star',       'href' => '/webprogg/admin/adminreviews.php'],
    ['label' => 'Messages',             'icon' => 'message',    'href' => '/webprogg/admin/adminmessages.php'],
    ['label' => 'Reports',              'icon' => 'bar-chart',  'href' => '/webprogg/admin/adminreports.php'],
    ['label' => 'Settings',             'icon' => 'settings',   'href' => '/webprogg/admin/adminsettings.php'],
];

/* ---------- Notifications badge ---------- */
 $pendingHostApps   = (int) $pdo->query("SELECT COUNT(*) FROM host_applications WHERE status = 'pending'")->fetchColumn();
 $pendingListings   = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
 $notificationCount = $pendingHostApps + $pendingListings;

/*
 * =========================================================
 * FILTERS: search + role
 * =========================================================
 */
 $search = trim($_GET['q'] ?? '');
 $role   = $_GET['role'] ?? 'all'; // all | host | guest

 $where  = [];
 $params = [];

if ($search !== '') {
    $where[]          = '(u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)';
    $params['search']  = '%' . $search . '%';
}
if ($role === 'host') {
    $where[] = 'u.is_host = 1';
} elseif ($role === 'guest') {
    $where[] = 'u.is_host = 0';
}
 $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/*
 * =========================================================
 * USERS LIST — with booking/listing/review counts
 * =========================================================
 */
 $sql = "
    SELECT
        u.id, u.name, u.email, u.phone, u.age, u.avatar_path, u.location,
        u.created_at, u.is_host,
        (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count,
        (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS listing_count,
        (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS review_count
    FROM users u
    $whereSql
    ORDER BY u.created_at DESC
";
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $users = $stmt->fetchAll();

 $totalUsersCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
 $totalHostsCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_host = 1")->fetchColumn();

/* ---------- Inline icon helper ---------- */
function icon($name, $class = '') {
    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 8.5a3 3 0 1 1 0-6"/><path d="M15 14.5c3 0 6.5 1.4 6.5 5.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'listing' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9 20v-5h6v5"/>',
        'clipboard' => '<rect x="5" y="4.5" width="14" height="17" rx="2"/><path d="M9 4.5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8v.7"/><path d="M9 11.5h6M9 15.5h6M9 7.5h1"/>',
        'user-check' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="m16 11 2 2 3.5-3.5"/>',
        'wallet' => '<rect x="2.5" y="6.5" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2"/>',
        'star' => '<path d="M12 3.5l2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.9l-5.2 2.6 1-5.75-4.2-4.1 5.8-.85z"/>',
        'message' => '<path d="M3.5 12a8.2 8.2 0 1 1 3.3 6.5L3 20l1.3-3.8A8.1 8.1 0 0 1 3.5 12Z"/>',
        'bar-chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.8 1.8 0 0 0 .36 2l.04.04a2.2 2.2 0 1 1-3.1 3.1l-.04-.04a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.1 1.65V20a2.2 2.2 0 1 1-4.4 0v-.06a1.8 1.8 0 0 0-1.18-1.65 1.8 1.8 0 0 0-2 .36l-.04.04a2.2 2.2 0 1 1-3.1-3.1l.04-.04a1.8 1.8 0 0 0 .36-2 1.8 1.8 0 0 0-1.65-1.1H4a2.2 2.2 0 1 1 0-4.4h.06a1.8 1.8 0 0 0 1.65-1.18 1.8 1.8 0 0 0-.36-2l-.04-.04a2.2 2.2 0 1 1 3.1-3.1l.04.04a1.8 1.8 0 0 0 2 .36H10.5a1.8 1.8 0 0 0 1.1-1.65V4a2.2 2.2 0 1 1 4.4 0v.06a1.8 1.8 0 0 0 1.1 1.65 1.8 1.8 0 0 0 2-.36l-.04-.04a2.2 2.2 0 1 1 3.1 3.1l-.04.04a1.8 1.8 0 0 0-.36 2v.09a1.8 1.8 0 0 0 1.65 1.1H20a2.2 2.2 0 1 1 0 4.4h-.06a1.8 1.8 0 0 0-1.65 1.1Z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 6.5-2.5 8-2.5 8h17S18 14.5 18 8Z"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'headphones' => '<path d="M3 13.5v-1.7a9 9 0 0 1 18 0v1.7"/><rect x="3" y="13.5" width="5" height="6.5" rx="1.6"/><rect x="16" y="13.5" width="5" height="6.5" rx="1.6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/><path d="M10 11v6M14 11v6"/>',
        'alert-triangle' => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m22 4-10 10.01-3-3"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg class="icon '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}

function emptyState($text) {
    echo '<div class="empty-state">';
    echo '<div class="empty-icon">'.icon('search').'</div>';
    echo '<p>'.htmlspecialchars($text).'</p>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RoomHive Admin — Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/webprogg/assets/admin.css">
<style>
    .sidebar .nav { padding-top: 10px; }
    .admin-chip { position: relative; cursor: pointer; }
    .admin-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 200px;
        background: #fff;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(20, 20, 43, 0.12);
        padding: 8px;
        z-index: 50;
    }
    .admin-chip.open .admin-menu { display: block; }
    .admin-menu-header {
        display: flex;
        flex-direction: column;
        padding: 8px 10px 10px;
        border-bottom: 1px solid #EEF1F6;
        margin-bottom: 6px;
    }
    .admin-menu-name { font-weight: 600; font-size: 13px; color: #14142B; }
    .admin-menu-email { font-size: 12px; color: #8B93A6; margin-top: 2px; }
    .admin-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13px;
        color: #14142B;
        text-decoration: none;
    }
    .admin-menu-item:hover { background: #F6F7FB; }
    .admin-menu-item .icon { width: 16px; height: 16px; }
    .admin-logout { color: #E14B4B; }

    /* ---- NAV ALIGNMENT: mobile sidebar toggle + topbar shadow ---- */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(20, 20, 43, 0.45);
        z-index: 90;
    }
    @media (max-width: 1000px) {
        .layout.sidebar-open .sidebar-overlay { display: block; }
        .layout.sidebar-open .sidebar {
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
    }
    .topbar.topbar-scrolled { box-shadow: 0 6px 18px rgba(20, 20, 43, 0.08); }

    /* ---------- Users page specifics ---------- */
    .users-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .users-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .users-search-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #F6F7FB;
        border: 1px solid #EEF1F6;
        border-radius: 10px;
        padding: 8px 12px;
        min-width: 240px;
    }
    .users-search-box .icon { width: 16px; height: 16px; color: #8B93A6; flex-shrink: 0; }
    .users-search-box input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 13px;
        width: 100%;
        font-family: inherit;
    }
    .role-tabs { display: flex; gap: 6px; background: #F6F7FB; border-radius: 10px; padding: 4px; }
    .role-tab {
        border: none;
        background: transparent;
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #8B93A6;
        cursor: pointer;
        text-decoration: none;
    }
    .role-tab.active { background: #fff; color: #14142B; box-shadow: 0 1px 4px rgba(20,20,43,0.1); }

    .users-count-pill {
        font-size: 12px;
        font-weight: 600;
        color: #8B93A6;
        background: #F6F7FB;
        border-radius: 999px;
        padding: 6px 12px;
    }

    .users-table-wrap { overflow-x: auto; }
    table.users-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    table.users-table th {
        text-align: left;
        color: #8B93A6;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 10px 12px;
        border-bottom: 1px solid #EEF1F6;
        white-space: nowrap;
    }
    table.users-table td {
        padding: 12px;
        border-bottom: 1px solid #F6F7FB;
        vertical-align: middle;
    }
    table.users-table tr:last-child td { border-bottom: none; }
    .user-cell { display: flex; align-items: center; gap: 10px; min-width: 200px; }
    .user-cell img, .user-cell .avatar-fallback {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    }
    .avatar-fallback {
        display: flex; align-items: center; justify-content: center;
        background: #EDA423; color: #fff; font-weight: 700; font-size: 14px;
    }
    .user-name { font-weight: 600; color: #14142B; display: block; }
    .user-loc { font-size: 12px; color: #8B93A6; }
    .role-pill {
        font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 999px;
        display: inline-block;
    }
    .role-pill.host { background: #EAF2FE; color: #2F7DE1; }
    .role-pill.guest { background: #F6F7FB; color: #8B93A6; }
    .stat-inline { color: #14142B; font-weight: 600; }
    .stat-inline-sub { color: #8B93A6; font-size: 12px; }

    .btn-delete-user {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        background: #FCEAEA;
        color: #E14B4B;
        font-size: 12px;
        font-weight: 600;
        padding: 7px 12px;
        border-radius: 8px;
        cursor: pointer;
    }
    .btn-delete-user:hover { background: #E14B4B; color: #fff; }
    .btn-delete-user .icon { width: 14px; height: 14px; }

    .flash-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 16px;
    }
    .flash-banner.success { background: #E7F7EC; color: #2FA84F; }
    .flash-banner.error { background: #FCEAEA; color: #E14B4B; }
    .flash-banner .icon { width: 18px; height: 18px; flex-shrink: 0; }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(20, 20, 43, 0.45);
        align-items: center;
        justify-content: center;
        z-index: 100;
        padding: 16px;
    }
    .modal-overlay.open { display: flex; }
    .modal-card {
        background: #fff;
        border-radius: 14px;
        max-width: 380px;
        width: 100%;
        padding: 22px;
        box-shadow: 0 20px 50px rgba(20,20,43,0.25);
    }
    .modal-icon-wrap {
        width: 44px; height: 44px; border-radius: 50%;
        background: #FCEAEA; color: #E14B4B;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 14px;
    }
    .modal-icon-wrap .icon { width: 22px; height: 22px; }
    .modal-title { font-size: 16px; font-weight: 700; color: #14142B; margin: 0 0 8px; }
    .modal-text { font-size: 13px; color: #5B6172; line-height: 1.5; margin: 0 0 20px; }
    .modal-text strong { color: #14142B; }
    .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
    .modal-btn {
        border: none;
        border-radius: 9px;
        padding: 9px 16px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .modal-btn-cancel { background: #F6F7FB; color: #14142B; }
    .modal-btn-cancel:hover { background: #EEF1F6; }
    .modal-btn-confirm { background: #E14B4B; color: #fff; }
    .modal-btn-confirm:hover { background: #c93f3f; }
</style>
</head>
<body>

<div class="layout" id="adminLayout">

    <!-- Mobile overlay (NAV ALIGNMENT) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>" class="nav-item <?= !empty($item['active']) ? 'active' : '' ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- ============ MAIN ============ -->
    <div class="main">

        <header class="topbar" id="adminTopbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="Toggle menu"><?= icon('menu') ?></button>

            <div class="search-box">
                <?= icon('search') ?>
                <input type="text" placeholder="Search users, bookings, properties...">
            </div>

            <div class="topbar-right">
                <button class="icon-btn bell-btn" aria-label="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($notificationCount > 0): ?>
                        <span class="bell-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="admin-chip" id="adminChip">
                    <div class="admin-avatar admin-avatar-fallback">
                        <?= htmlspecialchars(strtoupper(substr($adminName, 0, 1))) ?>
                    </div>
                    <div class="admin-info">
                        <span class="admin-name"><?= htmlspecialchars($adminName) ?></span>
                        <span class="admin-role">Administrator</span>
                    </div>
                    <?= icon('chevron-down', 'chevron') ?>

                    <div class="admin-menu" id="adminMenu">
                        <div class="admin-menu-header">
                            <span class="admin-menu-name"><?= htmlspecialchars($adminName) ?></span>
                            <?php if ($adminEmail): ?>
                                <span class="admin-menu-email"><?= htmlspecialchars($adminEmail) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="/webprogg/auth/logout.php" class="admin-menu-item admin-logout">
                            <?= icon('lock') ?>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-heading">
                <h1>Users</h1>
                <p>Every registered account on the platform. Deleting a user permanently removes their account and all associated data.</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash-banner <?= $flash['type'] ?>">
                    <?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?>
                    <span><?= $flash['text'] /* already escaped above */ ?></span>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="users-toolbar">
                    <form class="users-filters" method="get" action="/webprogg/admin/adminusers.php">
                        <div class="users-search-box">
                            <?= icon('search') ?>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, phone...">
                        </div>
                        <?php if ($role !== 'all'): ?>
                            <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                        <?php endif; ?>
                    </form>

                    <div class="role-tabs">
                        <a class="role-tab <?= $role === 'all' ? 'active' : '' ?>" href="?role=all<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">All (<?= $totalUsersCount ?>)</a>
                        <a class="role-tab <?= $role === 'host' ? 'active' : '' ?>" href="?role=host<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Hosts (<?= $totalHostsCount ?>)</a>
                        <a class="role-tab <?= $role === 'guest' ? 'active' : '' ?>" href="?role=guest<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Guests (<?= $totalUsersCount - $totalHostsCount ?>)</a>
                    </div>

                    <span class="users-count-pill"><?= count($users) ?> shown</span>
                </div>

                <?php if (empty($users)): ?>
                    <?php emptyState($search !== '' ? 'No users match your search.' : 'No registered users yet.'); ?>
                <?php else: ?>
                    <div class="users-table-wrap">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Bookings</th>
                                    <th>Listings</th>
                                    <th>Joined</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <?php if (!empty($u['avatar_path'])): ?>
                                                    <img src="<?= htmlspecialchars($u['avatar_path']) ?>" alt="<?= htmlspecialchars($u['name']) ?>">
                                                <?php else: ?>
                                                    <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr($u['name'], 0, 1))) ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="user-name"><?= htmlspecialchars($u['name']) ?></span>
                                                    <span class="user-loc"><?= htmlspecialchars($u['location'] ?: '—') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="user-name" style="font-weight:500;"><?= htmlspecialchars($u['email']) ?></span><br>
                                            <span class="user-loc"><?= htmlspecialchars($u['phone'] ?: '—') ?></span>
                                        </td>
                                        <td>
                                            <span class="role-pill <?= $u['is_host'] ? 'host' : 'guest' ?>">
                                                <?= $u['is_host'] ? 'Host' : 'Guest' ?>
                                            </span>
                                        </td>
                                        <td><span class="stat-inline"><?= (int) $u['booking_count'] ?></span></td>
                                        <td>
                                            <span class="stat-inline"><?= (int) $u['listing_count'] ?></span>
                                            <?php if ((int) $u['review_count'] > 0): ?>
                                                <div class="stat-inline-sub"><?= (int) $u['review_count'] ?> reviews</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($u['created_at']))) ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn-delete-user js-delete-user"
                                                data-user-id="<?= (int) $u['id'] ?>"
                                                data-user-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                                                data-listing-count="<?= (int) $u['listing_count'] ?>"
                                                data-booking-count="<?= (int) $u['booking_count'] ?>"
                                            >
                                                <?= icon('trash') ?> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============ DELETE CONFIRMATION MODAL ============ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-card">
        <div class="modal-icon-wrap"><?= icon('alert-triangle') ?></div>
        <h3 class="modal-title">Delete this user?</h3>
        <p class="modal-text" id="deleteModalText"></p>
        <form method="post" action="/webprogg/admin/adminusers.php" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="delete_user_id" id="deleteUserId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" id="cancelDelete">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-confirm">Yes, delete user</button>
            </div>
        </form>
    </div>
</div>

<script>
/* =====================================================
   CANONICAL SCRIPT — nav alignment (chip, sidebar toggle,
   topbar shadow) + the delete-confirmation modal
====================================================== */
(function () {
    "use strict";

    /* ---- Admin chip dropdown ---- */
    var chip = document.getElementById('adminChip');
    if (chip) {
        chip.addEventListener('click', function (e) {
            chip.classList.toggle('open');
            e.stopPropagation();
        });
        document.addEventListener('click', function () {
            chip.classList.remove('open');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { chip.classList.remove('open'); }
        });
    }

    /* ---- Mobile sidebar toggle (menu button now works) ---- */
    var layout  = document.getElementById('adminLayout');
    var menuBtn = document.getElementById('menuBtn');
    var overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() { if (layout) { layout.classList.remove('sidebar-open'); } }

    if (menuBtn && layout) {
        menuBtn.addEventListener('click', function (e) {
            layout.classList.toggle('sidebar-open');
            e.stopPropagation();
        });
    }
    if (overlay) { overlay.addEventListener('click', closeSidebar); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeSidebar(); }
    });

    /* ---- Topbar shadow on scroll ---- */
    var topbar = document.getElementById('adminTopbar');
    if (topbar) {
        var onScroll = function () {
            topbar.classList.toggle('topbar-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---- Delete confirmation modal (kept) ---- */
    var modal      = document.getElementById('deleteModal');
    var modalText  = document.getElementById('deleteModalText');
    var idField    = document.getElementById('deleteUserId');
    var cancelBtn  = document.getElementById('cancelDelete');

    document.querySelectorAll('.js-delete-user').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name     = btn.dataset.userName;
            const listings = parseInt(btn.dataset.listingCount || '0', 10);
            const bookings = parseInt(btn.dataset.bookingCount || '0', 10);

            let extra = '';
            if (listings > 0 || bookings > 0) {
                const parts = [];
                if (listings > 0) parts.push(listings + ' listing' + (listings === 1 ? '' : 's'));
                if (bookings > 0) parts.push(bookings + ' booking' + (bookings === 1 ? '' : 's'));
                extra = ' This account also has ' + parts.join(' and ') + ', which will be removed too.';
            }

            modalText.innerHTML = 'This will permanently delete <strong>' + name +
                '</strong> and all of their data — bookings, reviews, wishlist items, listings and photos, messages and conversations, notifications, saved searches, support tickets, payout records, Hive Club membership and host application history.' +
                extra + ' This action cannot be undone.';
            idField.value = btn.dataset.userId;
            modal.classList.add('open');
        });
    });

    function closeModal() { modal.classList.remove('open'); }
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
</body>
</html>