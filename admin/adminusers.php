<?php
/**adminusers.php
 * RoomHive Admin — Users
 *
 * === ACCURATE DETAIL VIEW (this version) ===
 * The detail modal now shows EXACTLY what the user's data has:
 *   - VERIFICATION BREAKDOWN: shows ID status (uploaded/pending/
 *     none) and exactly WHICH profile fields are missing, instead
 *     of a vague yes/no.
 *   - PER-TABLE COUNTS: each activity count (wishlist, saved
 *     searches, notifications, tickets, conversations, messages)
 *     is queried SEPARATELY and guarded — one missing/mismatched
 *     table can no longer zero out all the others; a missing
 *     table shows "—" instead of a false 0.
 *   - REAL TOTALS: section headers show "5 of 12" (shown of total)
 *     for bookings, listings and reviews.
 *   - RESILIENT QUERIES: reviews and bookings use LEFT JOIN so
 *     rows whose listing was deleted still count and display.
 * - KEPT: single profile-pic avatar in the list, Personal Photo
 *   section in the modal, cascading delete, filters, nav, topbar.
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

/* -------------------------------------------------------
   Self-heal: personal_photo + user_id_documents must exist
   BEFORE any prepare() referencing them.
-------------------------------------------------------- */
try {
    $ppCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'personal_photo'")->fetch();
    if (!$ppCol) {
        $pdo->exec("ALTER TABLE users ADD COLUMN personal_photo VARCHAR(255) NULL");
    }
} catch (PDOException $e) {
    error_log('adminusers: personal_photo ensure failed: ' . $e->getMessage());
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_id_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            id_type VARCHAR(60) NOT NULL,
            id_number VARCHAR(80) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            INDEX idx_uid (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log('adminusers: user_id_documents ensure failed: ' . $e->getMessage());
}

/* ---------- CSRF token (per-session) ---------- */
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['admin_csrf'];

/*
 * =========================================================
 * DELETE USER (full cascading delete) — unchanged
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

                /* 1. LISTINGS OWNED BY THE USER */
                $stmt = $pdo->prepare("SELECT id FROM listings WHERE user_id = :uid");
                $stmt->execute(['uid' => $targetUserId]);
                $ownedListingIds = array_column($stmt->fetchAll(), 'id');

                if ($ownedListingIds) {
                    $placeholders = implode(',', array_fill(0, count($ownedListingIds), '?'));

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

                /* 2. CONVERSATIONS BOTH DIRECTIONS */
                try {
                    $convStmt = $pdo->prepare(
                        "SELECT id FROM conversations WHERE user_id = :uid OR host_id = :uid2"
                    );
                    $convStmt->execute(['uid' => $targetUserId, 'uid2' => $targetUserId]);
                    $convIds = array_column($convStmt->fetchAll(), 'id');

                    if ($convIds) {
                        $convPh = implode(',', array_fill(0, count($convIds), '?'));
                        $pdo->prepare("DELETE FROM messages WHERE conversation_id IN ($convPh)")
                            ->execute($convIds);
                        $pdo->prepare("DELETE FROM conversations WHERE id IN ($convPh)")
                            ->execute($convIds);
                    }

                    $pdo->prepare("DELETE FROM messages WHERE sender_id = :uid")
                        ->execute(['uid' => $targetUserId]);

                } catch (PDOException $e) {
                    error_log('adminusers cascade: conversations/messages: ' . $e->getMessage());
                }

                /* 3-7. OWN ROWS + ACCOUNT */
                $pdo->prepare("DELETE FROM bookings WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM reviews WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM wishlist WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM saved_searches WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM support_tickets WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM notification_settings WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM notification_preferences WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM payouts WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM payout_methods WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM user_id_documents WHERE user_id = :uid")->execute(['uid' => $targetUserId]);

                $stmt = $pdo->prepare("SELECT id FROM hive_members WHERE user_id = :uid");
                $stmt->execute(['uid' => $targetUserId]);
                $hiveMemberIds = array_column($stmt->fetchAll(), 'id');

                if ($hiveMemberIds) {
                    $placeholders = implode(',', array_fill(0, count($hiveMemberIds), '?'));
                    $pdo->prepare("DELETE FROM hiveclub_transactions WHERE hive_member_id IN ($placeholders)")
                        ->execute($hiveMemberIds);
                }
                $pdo->prepare("DELETE FROM hiveclub_transactions WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM hive_redemptions WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM hive_points_ledger WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM hive_members WHERE user_id = :uid")->execute(['uid' => $targetUserId]);

                $pdo->prepare("DELETE FROM host_applications WHERE user_id = :uid")->execute(['uid' => $targetUserId]);
                $pdo->prepare("DELETE FROM users WHERE id = :uid")->execute(['uid' => $targetUserId]);

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

/* ---------- Sidebar navigation ---------- */
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

/* =========================================================
   FILTERS: search + role
 * ========================================================= */
 $search = trim($_GET['q'] ?? '');
 $role   = $_GET['role'] ?? 'all';

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

/* =========================================================
   USERS LIST
 * ========================================================= */
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

/* =========================================================
   USER DETAIL — ?user=ID
   ACCURATE VIEW: every stat reflects the user's real data.
========================================================= */
 $detailUser  = null;
 $detail      = [];
 $detailId    = (int) ($_GET['user'] ?? 0);

if ($detailId > 0) {
    $st = $pdo->prepare(
        "SELECT id, name, email, phone, age, location, avatar_path, is_host, created_at, personal_photo
         FROM users WHERE id = :id LIMIT 1"
    );
    $st->execute(['id' => $detailId]);
    $detailUser = $st->fetch();

    if ($detailUser) {

        /* =============================================
           VERIFICATION BREAKDOWN — accurate to what exists:
           which parts are done, which are missing.
        ============================================== */
        $verHasId    = false;
        $verIdStatus = null;

        try {
            $v = $pdo->prepare(
                "SELECT status FROM user_id_documents
                 WHERE user_id = :u AND status != 'rejected'
                 ORDER BY uploaded_at DESC
                 LIMIT 1"
            );
            $v->execute([':u' => $detailId]);
            $verIdStatus = $v->fetchColumn();
            $verHasId    = (bool) $verIdStatus;
        } catch (PDOException $e) { /* table guarded */ }

        $verMissing = [];
        if (trim((string) ($detailUser['name'] ?? '')) === '')                       { $verMissing[] = 'Name'; }
        if (trim((string) ($detailUser['phone'] ?? '')) === '')                      { $verMissing[] = 'Phone'; }
        if (!isset($detailUser['age']) || $detailUser['age'] === null || $detailUser['age'] === '') { $verMissing[] = 'Age'; }
        if (trim((string) ($detailUser['location'] ?? '')) === '')                   { $verMissing[] = 'Location'; }

        $detailVerified = $verHasId && empty($verMissing);

        /* =============================================
           ID DOCUMENTS
        ============================================== */
        $detail['docs'] = [];
        try {
            $d = $pdo->prepare(
                "SELECT id_type, id_number, file_path, status, admin_note, uploaded_at
                 FROM user_id_documents
                 WHERE user_id = :u
                 ORDER BY uploaded_at DESC
                 LIMIT 5"
            );
            $d->execute([':u' => $detailId]);
            $detail['docs'] = $d->fetchAll();
        } catch (PDOException $e) { $detail['docs'] = []; }

        /* =============================================
           BOOKINGS — real total + recent (LEFT JOIN so
           bookings whose listing was deleted still show)
        ============================================== */
        $detail['totals']['bookings'] = 0;
        try {
            $t = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :u");
            $t->execute([':u' => $detailId]);
            $detail['totals']['bookings'] = (int) $t->fetchColumn();
        } catch (PDOException $e) {}

        $detail['bookings'] = [];
        try {
            $b = $pdo->prepare(
                "SELECT b.id, b.total, b.status, b.booked_at,
                        COALESCE(l.title, 'Listing removed') AS title
                 FROM bookings b
                 LEFT JOIN listings l ON l.id = b.listing_id
                 WHERE b.user_id = :u
                 ORDER BY b.booked_at DESC
                 LIMIT 5"
            );
            $b->execute([':u' => $detailId]);
            $detail['bookings'] = $b->fetchAll();
        } catch (PDOException $e) { $detail['bookings'] = []; }

        /* =============================================
           LISTINGS — real total + recent
        ============================================== */
        $detail['totals']['listings'] = 0;
        try {
            $t = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE user_id = :u");
            $t->execute([':u' => $detailId]);
            $detail['totals']['listings'] = (int) $t->fetchColumn();
        } catch (PDOException $e) {}

        $detail['listings'] = [];
        try {
            $l = $pdo->prepare(
                "SELECT l.id, l.title, l.price, l.status, l.created_at,
                        p.photo_path AS cover_photo
                 FROM listings l
                 LEFT JOIN listing_photos p
                        ON p.listing_id = l.id AND p.photo_type = 'cover'
                 WHERE l.user_id = :u
                 ORDER BY l.created_at DESC
                 LIMIT 5"
            );
            $l->execute([':u' => $detailId]);
            $detail['listings'] = $l->fetchAll();
        } catch (PDOException $e) { $detail['listings'] = []; }

        /* =============================================
           REVIEWS — real total + recent (LEFT JOIN so
           reviews on deleted listings still count)
        ============================================== */
        $detail['totals']['reviews'] = 0;
        try {
            $t = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = :u");
            $t->execute([':u' => $detailId]);
            $detail['totals']['reviews'] = (int) $t->fetchColumn();
        } catch (PDOException $e) {}

        $detail['reviews'] = [];
        try {
            $r = $pdo->prepare(
                "SELECT r.rating, r.created_at,
                        COALESCE(l.title, 'Listing removed') AS title
                 FROM reviews r
                 LEFT JOIN listings l ON l.id = r.listing_id
                 WHERE r.user_id = :u
                 ORDER BY r.created_at DESC
                 LIMIT 5"
            );
            $r->execute([':u' => $detailId]);
            $detail['reviews'] = $r->fetchAll();
        } catch (PDOException $e) { $detail['reviews'] = []; }

        /* =============================================
           ACTIVITY COUNTS — each table counted SEPARATELY,
           each in its own guard. A missing table shows "—"
           instead of falsely zeroing everything.
        ============================================== */
        $detail['counts'] = [
            'wishlist' => null, 'saved'  => null, 'notifs' => null,
            'tickets'  => null, 'convos' => null, 'msgs'   => null,
            'hive'     => false, 'hive_tier' => null,
        ];

        $countTables = [
            'wishlist' => "SELECT COUNT(*) FROM wishlist WHERE user_id = :u",
            'saved'    => "SELECT COUNT(*) FROM saved_searches WHERE user_id = :u",
            'notifs'   => "SELECT COUNT(*) FROM notifications WHERE user_id = :u",
            'tickets'  => "SELECT COUNT(*) FROM support_tickets WHERE user_id = :u",
        ];

        foreach ($countTables as $key => $cntSql) {
            try {
                $c = $pdo->prepare($cntSql);
                $c->execute([':u' => $detailId]);
                $detail['counts'][$key] = (int) $c->fetchColumn();
            } catch (PDOException $e) {
                $detail['counts'][$key] = null; /* table missing */
            }
        }

        /* Conversations (both directions) */
        try {
            $c = $pdo->prepare(
                "SELECT COUNT(*) FROM conversations WHERE user_id = :u1 OR host_id = :u2"
            );
            $c->execute([':u1' => $detailId, ':u2' => $detailId]);
            $detail['counts']['convos'] = (int) $c->fetchColumn();
        } catch (PDOException $e) {
            $detail['counts']['convos'] = null;
        }

        /* Messages sent */
        try {
            $c = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = :u");
            $c->execute([':u' => $detailId]);
            $detail['counts']['msgs'] = (int) $c->fetchColumn();
        } catch (PDOException $e) {
            $detail['counts']['msgs'] = null;
        }

        /* Hive Club tier */
        try {
            $h = $pdo->prepare(
                "SELECT tier FROM hive_members
                 WHERE user_id = :u ORDER BY id DESC LIMIT 1"
            );
            $h->execute([':u' => $detailId]);
            $tier = $h->fetchColumn();
            if ($tier) {
                $detail['counts']['hive']      = true;
                $detail['counts']['hive_tier'] = (string) $tier;
            }
        } catch (PDOException $e) { /* table missing — stays "—" */ }

    } else {
        header('Location: /webprogg/admin/adminusers.php');
        exit();
    }
}

/* ---------- Display helper: null = "—" (data unavailable) ---------- */
if (!function_exists('dm_count')) {
    function dm_count($v) {
        return ($v === null) ? '—' : (string) (int) $v;
    }
}

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
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'shield' => '<path d="M12 3l7 3v6c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'id' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="8" cy="11" r="2"/><path d="M5.5 16a3.5 3.5 0 0 1 5 0"/><path d="M14 9h5M14 12.5h5M14 16h3"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
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

    a.user-cell-link { text-decoration: none; color: inherit; display: block; }
    a.user-cell-link:hover .user-name { color: #2F7DE1; }
    .user-cell { display: flex; align-items: center; gap: 10px; min-width: 200px; }
    .user-cell img, .user-cell .avatar-fallback {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    }
    .avatar-fallback {
        display: flex; align-items: center; justify-content: center;
        background: #EDA423; color: #fff; font-weight: 700; font-size: 14px;
    }
    .user-name { font-weight: 600; color: #14142B; display: block; transition: color .15s ease; }
    .user-loc { font-size: 12px; color: #8B93A6; }
    .view-details-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #EEF1F6;
        background: #F6F7FB;
        color: #14142B;
        font-size: 12px;
        font-weight: 600;
        padding: 7px 12px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
    }
    .view-details-btn:hover { border-color: #2F7DE1; color: #2F7DE1; background: #EAF2FE; }
    .view-details-btn .icon { width: 14px; height: 14px; }

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

    /* =====================================================
       USER DETAIL MODAL
    ====================================================== */
    .detail-modal-card {
        background: #fff;
        border-radius: 16px;
        max-width: 640px;
        width: 100%;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(20,20,43,0.25);
    }
    .dm-close {
        position: sticky;
        top: 0;
        float: right;
        margin: 14px 14px 0 0;
        width: 34px; height: 34px;
        border: none;
        border-radius: 50%;
        background: #F6F7FB;
        color: #5B6172;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        z-index: 2;
    }
    .dm-close:hover { background: #FCEAEA; color: #E14B4B; }
    .dm-close .icon { width: 16px; height: 16px; }

    .dm-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px 22px 16px;
        border-bottom: 1px solid #F6F7FB;
    }
    .dm-head img, .dm-head .avatar-fallback {
        width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    }
    .dm-head-info h2 { margin: 0; font-size: 17px; color: #14142B; }
    .dm-head-info p { margin: 3px 0 0; font-size: 12.5px; color: #8B93A6; }

    .dm-section { padding: 16px 22px; border-bottom: 1px solid #F6F7FB; }
    .dm-section:last-child { border-bottom: none; }
    .dm-section h4 {
        margin: 0 0 10px;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8B93A6;
    }
    .dm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
    .dm-field span { display: block; font-size: 11px; color: #8B93A6; }
    .dm-field strong { display: block; font-size: 13px; color: #14142B; font-weight: 600; }
    .dm-field a { color: #2F7DE1; text-decoration: none; font-weight: 600; }
    .dm-field a:hover { text-decoration: underline; }

    /* Verification breakdown */
    .dm-verbreak { display: grid; gap: 6px; margin-top: 10px; }
    .dm-verbreak-row {
        display: flex; align-items: center; gap: 8px;
        font-size: 12.5px; color: #14142B; font-weight: 600;
    }
    .dm-vercheck {
        width: 18px; height: 18px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; flex-shrink: 0; font-weight: 800;
    }
    .dm-vercheck.yes { background: #E7F7EC; color: #2FA84F; }
    .dm-vercheck.no  { background: #FFF4E0; color: #C77A00; }

    /* Personal photo display in the modal */
    .pp-photo-link { display: block; }
    .pp-photo-img {
        width: 100%;
        max-width: 420px;
        height: 200px;
        object-fit: cover;
        object-position: center;
        border-radius: 10px;
        border: 1px solid #EEF1F6;
        display: block;
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .pp-photo-link:hover .pp-photo-img {
        transform: scale(1.02);
        box-shadow: 0 10px 24px rgba(20, 20, 43, 0.15);
    }
    .pp-photo-unavailable { display: none; }

    .dm-counts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .dm-count-box {
        background: #F6F7FB;
        border-radius: 10px;
        padding: 10px;
        text-align: center;
    }
    .dm-count-box strong { display: block; font-size: 17px; color: #14142B; }
    .dm-count-box span { display: block; font-size: 10.5px; color: #8B93A6; margin-top: 2px; }

    .dm-verify {
        padding: 12px 14px; border-radius: 10px; font-size: 13px; font-weight: 600;
    }
    .dm-verify.ok  { background: #E7F7EC; color: #2FA84F; }
    .dm-verify.no  { background: #FFF4E0; color: #C77A00; }

    .dm-doc {
        display: flex; align-items: center; gap: 10px;
        border: 1px solid #EEF1F6; border-radius: 10px; padding: 8px 10px; margin-bottom: 8px;
    }
    .dm-doc img, .dm-doc .doc-fallback {
        width: 42px; height: 42px; border-radius: 8px; object-fit: cover; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 18px; background: #F0EEE6;
    }
    .dm-doc-body { flex: 1; min-width: 0; }
    .dm-doc-body strong { display: block; font-size: 12.5px; color: #14142B; }
    .dm-doc-body span { display: block; font-size: 11px; color: #8B93A6; }

    .dm-item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 0; border-bottom: 1px solid #F6F7FB; font-size: 12.5px;
    }
    .dm-item:last-child { border-bottom: none; }
    .dm-item img { width: 34px; height: 34px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #F0EEE6; }
    .dm-item-body { flex: 1; min-width: 0; }
    .dm-item-body strong { display: block; color: #14142B; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dm-item-body span { display: block; font-size: 11px; color: #8B93A6; }
    .dm-item-side { text-align: right; flex-shrink: 0; }
    .dm-item-side strong { display: block; color: #14142B; }
    .dm-mini-pill {
        font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; display: inline-block;
    }
    .dm-mini-pill.approved { background: #E7F7EC; color: #2FA84F; }
    .dm-mini-pill.pending  { background: #FFF4E0; color: #C77A00; }
    .dm-mini-pill.rejected { background: #FCEAEA; color: #E14B4B; }
    .dm-mini-pill.draft    { background: #F6F7FB; color: #8B93A6; }
    .dm-star { color: #EDA423; font-weight: 700; }
    .dm-empty { font-size: 12.5px; color: #8B93A6; font-style: italic; }

    .dm-footer {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 22px 18px;
    }
    .dm-footer .btn-delete-user { pointer-events: auto; }
</style>
</head>
<body>

<div class="layout" id="adminLayout">

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
                <p>Every registered account on the platform. Click a user to see their full details; deleting is permanent and cascades.</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash-banner <?= $flash['type'] ?>">
                    <?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?>
                    <span><?= $flash['text'] ?></span>
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
                                    <?php
                                        $detailHref = '?user=' . (int) $u['id']
                                            . ($search !== '' ? '&q=' . urlencode($search) : '')
                                            . ($role !== 'all' ? '&role=' . urlencode($role) : '');
                                    ?>
                                    <tr>
                                        <td>
                                            <a class="user-cell-link" href="<?= $detailHref ?>" title="View user details">
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
                                            </a>
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
                                        <td style="white-space:nowrap;">
                                            <a class="view-details-btn" href="<?= $detailHref ?>" title="View details">
                                                <?= icon('eye') ?> View
                                            </a>
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

<!-- ============ USER DETAIL MODAL ============ -->
<?php if ($detailUser): ?>
<div class="modal-overlay open" id="userDetailModal">

    <div class="detail-modal-card">

        <button type="button" class="dm-close js-close-detail" aria-label="Close"><?= icon('x') ?></button>

        <!-- Header -->
        <div class="dm-head">
            <?php if (!empty($detailUser['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($detailUser['avatar_path']) ?>" alt="">
            <?php else: ?>
                <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr($detailUser['name'], 0, 1))) ?></div>
            <?php endif; ?>
            <div class="dm-head-info">
                <h2>
                    <?= htmlspecialchars($detailUser['name']) ?>
                    <span class="role-pill <?= $detailUser['is_host'] ? 'host' : 'guest' ?>">
                        <?= $detailUser['is_host'] ? 'Host' : 'Guest' ?>
                    </span>
                </h2>
                <p>
                    User #<?= (int) $detailUser['id'] ?>
                    &middot; joined <?= htmlspecialchars(date('M j, Y', strtotime($detailUser['created_at']))) ?>
                </p>
            </div>
        </div>

        <!-- Verification — ACCURATE BREAKDOWN -->
        <div class="dm-section">
            <h4>Verification</h4>

            <div class="dm-verify <?= $detailVerified ? 'ok' : 'no' ?>">
                <span>
                    <?= $detailVerified
                        ? 'Verified — all requirements met.'
                        : 'Not verified yet — see what\u2019s missing below.' ?>
                </span>
            </div>

            <div class="dm-verbreak">
                <div class="dm-verbreak-row">
                    <span class="dm-vercheck <?= $verHasId ? 'yes' : 'no' ?>"><?= $verHasId ? '&#10003;' : '!' ?></span>
                    <span>
                        Government ID:
                        <?php if ($verHasId): ?>
                            uploaded
                            <?php if ($verIdStatus === 'verified'): ?>
                                <strong style="color:#2FA84F;">(verified)</strong>
                            <?php elseif ($verIdStatus === 'pending'): ?>
                                <strong style="color:#C77A00;">(pending review)</strong>
                            <?php else: ?>
                                (<?= htmlspecialchars(ucfirst((string) $verIdStatus)) ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            <strong style="color:#C77A00;">not uploaded</strong>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="dm-verbreak-row">
                    <span class="dm-vercheck <?= empty($verMissing) ? 'yes' : 'no' ?>"><?= empty($verMissing) ? '&#10003;' : '!' ?></span>
                    <span>
                        Profile complete:
                        <?php if (empty($verMissing)): ?>
                            <strong style="color:#2FA84F;">yes</strong>
                        <?php else: ?>
                            <strong style="color:#C77A00;">missing <?= htmlspecialchars(implode(', ', $verMissing)) ?></strong>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Profile details -->
        <div class="dm-section">
            <h4>Profile Details</h4>
            <div class="dm-grid">
                <div class="dm-field">
                    <span>Email</span>
                    <strong><a href="mailto:<?= htmlspecialchars($detailUser['email']) ?>"><?= htmlspecialchars($detailUser['email']) ?></a></strong>
                </div>
                <div class="dm-field">
                    <span>Phone</span>
                    <strong><?= ($detailUser['phone'] !== '' && $detailUser['phone'] !== null) ? htmlspecialchars($detailUser['phone']) : '—' ?></strong>
                </div>
                <div class="dm-field">
                    <span>Age</span>
                    <strong><?= ($detailUser['age'] !== null && $detailUser['age'] !== '') ? htmlspecialchars($detailUser['age']) : '—' ?></strong>
                </div>
                <div class="dm-field">
                    <span>Location</span>
                    <strong><?= $detailUser['location'] ? htmlspecialchars($detailUser['location']) : '—' ?></strong>
                </div>
            </div>
        </div>

        <!-- Personal Photo (separate from profile picture) -->
        <div class="dm-section">
            <h4>Personal Photo (in-person recognition)</h4>
            <?php $pp = trim((string) ($detailUser['personal_photo'] ?? '')); ?>
            <?php if ($pp !== ''): ?>
                <a class="pp-photo-link" href="<?= htmlspecialchars($pp) ?>" target="_blank" rel="noopener" title="Open full size">
                    <img
                        class="pp-photo-img"
                        src="<?= htmlspecialchars($pp) ?>"
                        alt="Personal photo"
                        onerror="this.style.display='none'; this.parentElement.nextElementSibling.style.display='block';"
                    >
                </a>
                <p class="dm-empty pp-photo-unavailable">Photo file unavailable.</p>
            <?php else: ?>
                <p class="dm-empty">No personal photo uploaded yet.</p>
            <?php endif; ?>
        </div>

        <!-- ID documents -->
        <div class="dm-section">
            <h4>ID Documents (<?= count($detail['docs']) ?>)</h4>
            <?php if (empty($detail['docs'])): ?>
                <p class="dm-empty">No IDs uploaded yet.</p>
            <?php else: ?>
                <?php foreach ($detail['docs'] as $doc): ?>
                    <div class="dm-doc">
                        <?php
                            $dExt   = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            $dIsPdf = $dExt === 'pdf';
                        ?>
                        <?php if ($dIsPdf): ?>
                            <a class="doc-fallback" href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" rel="noopener" title="Open PDF">&#128196;</a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" rel="noopener" title="Open full image">
                                <img src="<?= htmlspecialchars($doc['file_path']) ?>" alt="ID">
                            </a>
                        <?php endif; ?>
                        <div class="dm-doc-body">
                            <strong><?= htmlspecialchars(ucwords(str_replace('-', ' ', $doc['id_type']))) ?></strong>
                            <span>&bull;&bull;&bull;&bull;<?= htmlspecialchars(substr($doc['id_number'], -4)) ?>
                                &middot; <?= htmlspecialchars(date('M j, Y', strtotime($doc['uploaded_at']))) ?>
                                <?php if (!empty($doc['admin_note'])): ?> &middot; <?= htmlspecialchars($doc['admin_note']) ?><?php endif; ?>
                            </span>
                        </div>
                        <span class="dm-mini-pill <?= htmlspecialchars($doc['status']) ?>">
                            <?= htmlspecialchars(ucfirst($doc['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Activity counts — accurate; "—" when a table is unavailable -->
        <div class="dm-section">
            <h4>Activity</h4>
            <div class="dm-counts">
                <div class="dm-count-box"><strong><?= dm_count($detail['counts']['wishlist']) ?></strong><span>Wishlist</span></div>
                <div class="dm-count-box"><strong><?= dm_count($detail['counts']['saved']) ?></strong><span>Saved searches</span></div>
                <div class="dm-count-box"><strong><?= dm_count($detail['counts']['convos']) ?></strong><span>Conversations</span></div>
                <div class="dm-count-box"><strong><?= dm_count($detail['counts']['msgs']) ?></strong><span>Messages sent</span></div>
                <div class="dm-count-box"><strong><?= dm_count($detail['counts']['notifs']) ?></strong><span>Notifications</span></div>
                <div class="dm-count-box">
                    <strong><?= $detail['counts']['hive'] ? htmlspecialchars($detail['counts']['hive_tier']) : '—' ?></strong>
                    <span>Hive Club</span>
                </div>
            </div>
        </div>

        <!-- Recent bookings — real totals -->
        <div class="dm-section">
            <h4>Recent Bookings (<?= min(5, (int) $detail['totals']['bookings']) ?> of <?= (int) $detail['totals']['bookings'] ?>)</h4>
            <?php if (empty($detail['bookings'])): ?>
                <p class="dm-empty">No bookings yet.</p>
            <?php else: ?>
                <?php foreach ($detail['bookings'] as $b): ?>
                    <div class="dm-item">
                        <div class="dm-item-body">
                            <strong><?= htmlspecialchars($b['title']) ?></strong>
                            <span><?= htmlspecialchars(date('M j, Y', strtotime($b['booked_at']))) ?></span>
                        </div>
                        <div class="dm-item-side">
                            <strong>&#8369; <?= htmlspecialchars(number_format((float) $b['total'], 2)) ?></strong>
                            <span class="dm-mini-pill <?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Listings — real totals -->
        <div class="dm-section">
            <h4>Listings (<?= min(5, (int) $detail['totals']['listings']) ?> of <?= (int) $detail['totals']['listings'] ?>)</h4>
            <?php if (empty($detail['listings'])): ?>
                <p class="dm-empty">No listings yet.</p>
            <?php else: ?>
                <?php foreach ($detail['listings'] as $l): ?>
                    <div class="dm-item">
                        <img src="<?= htmlspecialchars(!empty($l['cover_photo']) ? $l['cover_photo'] : '/webprogg/images/ListingPlaceholder.png') ?>" alt="">
                        <div class="dm-item-body">
                            <strong><?= htmlspecialchars($l['title']) ?></strong>
                            <span>Added <?= htmlspecialchars(date('M j, Y', strtotime($l['created_at']))) ?></span>
                        </div>
                        <div class="dm-item-side">
                            <strong>&#8369; <?= htmlspecialchars(number_format((float) $l['price'])) ?></strong>
                            <span class="dm-mini-pill <?= htmlspecialchars($l['status']) ?>"><?= htmlspecialchars(ucfirst($l['status'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Reviews — real totals -->
        <div class="dm-section">
            <h4>Reviews Written (<?= min(5, (int) $detail['totals']['reviews']) ?> of <?= (int) $detail['totals']['reviews'] ?>)</h4>
            <?php if (empty($detail['reviews'])): ?>
                <p class="dm-empty">No reviews written yet.</p>
            <?php else: ?>
                <?php foreach ($detail['reviews'] as $r): ?>
                    <div class="dm-item">
                        <div class="dm-item-body">
                            <strong><?= htmlspecialchars($r['title']) ?></strong>
                            <span><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></span>
                        </div>
                        <div class="dm-item-side">
                            <span class="dm-star">&#9733; <?= htmlspecialchars(number_format((float) $r['rating'], 1)) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="dm-footer">
            <a href="/webprogg/admin/adminusers.php<?= ($search !== '' || $role !== 'all') ? '?' . http_build_query(array_filter(['q' => $search, 'role' => $role])) : '' ?>"
               class="modal-btn modal-btn-cancel js-close-detail-link" style="text-decoration:none;">Back to list</a>
            <button
                type="button"
                class="btn-delete-user js-delete-user"
                data-user-id="<?= (int) $detailUser['id'] ?>"
                data-user-name="<?= htmlspecialchars($detailUser['name'], ENT_QUOTES) ?>"
                data-listing-count="<?= (int) $detail['totals']['listings'] ?>"
                data-booking-count="<?= (int) $detail['totals']['bookings'] ?>"
            >
                <?= icon('trash') ?> Delete User
            </button>
        </div>

    </div>
</div>
<?php endif; ?>

<script>
/* =====================================================
   Canonical script — nav + delete modal + detail modal
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

    /* ---- Mobile sidebar toggle ---- */
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

    /* ---- Delete confirmation modal ---- */
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
                '</strong> and all of their data — bookings, reviews, wishlist items, listings and photos, messages and conversations, notifications, saved searches, support tickets, payout records, Hive Club membership, ID documents and host application history.' +
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

    /* ---- User detail modal close handlers ---- */
    var detailModal = document.getElementById('userDetailModal');

    if (detailModal) {
        detailModal.querySelectorAll('.js-close-detail').forEach(function (el) {
            el.addEventListener('click', function () {
                var url = new URL(window.location.href);
                url.searchParams.delete('user');
                window.location.href = url.toString();
            });
        });

        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal) {
                var url = new URL(window.location.href);
                url.searchParams.delete('user');
                window.location.href = url.toString();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var url = new URL(window.location.href);
                url.searchParams.delete('user');
                window.location.href = url.toString();
            }
        });
    }
})();
</script>
</body>
</html>