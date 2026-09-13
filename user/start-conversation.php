<?php
/* =========================================================
   ROOMHIVE — START / RESUME CONVERSATION
   start-conversation.php?host_id=5[&listing_id=12]

   Finds the existing conversation for this (renter, host)
   pair or creates one, then redirects into the correct
   inbox with the thread open.

   FIXED vs previous version:
   - If host_id is wrong/invalid, the tenant is bounced back
     to the listings page with NO indication anything went
     wrong — that's a very easy way to end up messaging the
     wrong host without noticing. Now it sets a flash error
     the listings page can optionally display.
   - Wrapped the create-conversation insert in try/catch so a
     DB failure doesn't silently 500 or redirect as if it
     worked.
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /webprogg/auth/loginform.php');
    exit;
}

if (!function_exists('sc_flash_set')) {
    function sc_flash_set($type, $message) {
        $_SESSION['sc_flash'] = ['type' => $type, 'message' => $message];
    }
}

$userId    = (int) $_SESSION['user_id'];
$hostId    = (isset($_GET['host_id']) && is_numeric($_GET['host_id'])) ? (int) $_GET['host_id'] : 0;
$listingId = (isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])) ? (int) $_GET['listing_id'] : null;

if ($hostId === 0 || $hostId === $userId) {
    sc_flash_set('error', 'That host link looks invalid. Please try again from the listing page.');
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

/* Target must be a real, approved host.
   IMPORTANT: this must be a users.id, not a host_applications.id
   or any other id — double check whatever page builds the
   "Message host" link (usually the listing detail page) is
   passing listings.user_id, not host_application_id. */
$hostCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND is_host = 1 LIMIT 1");
$hostCheckStmt->execute(['id' => $hostId]);
if ($hostCheckStmt->fetchColumn() === false) {
    sc_flash_set('error', 'That host could not be found. Please try again from the listing page.');
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

/* Is the VIEWER a host? Decides which inbox they land in. */
$viewerStmt = $pdo->prepare("SELECT is_host FROM users WHERE id = :id LIMIT 1");
$viewerStmt->execute(['id' => $userId]);
$isHostViewer = (bool) $viewerStmt->fetchColumn();

try {
    /* Find or create the (user, host) thread — one per pair */
    $findStmt = $pdo->prepare(
        "SELECT id, listing_id FROM conversations
         WHERE user_id = :uid AND host_id = :hid
         LIMIT 1"
    );
    $findStmt->execute(['uid' => $userId, 'hid' => $hostId]);
    $existing = $findStmt->fetch();

    if ($existing) {
        $conversationId = (int) $existing['id'];

        if ($listingId !== null && $existing['listing_id'] === null) {
            $pdo->prepare("UPDATE conversations SET listing_id = :lid WHERE id = :id")
                ->execute(['lid' => $listingId, 'id' => $conversationId]);
        }
    } else {
        $createStmt = $pdo->prepare(
            "INSERT INTO conversations (user_id, host_id, listing_id, last_message_at)
             VALUES (:uid, :hid, :lid, NOW())"
        );
        $createStmt->execute(['uid' => $userId, 'hid' => $hostId, 'lid' => $listingId]);

        if ($createStmt->rowCount() !== 1) {
            throw new RuntimeException('conversation insert reported rowCount=0');
        }

        $conversationId = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    error_log('start-conversation.php failed: ' . $e->getMessage());
    sc_flash_set('error', 'Could not start that conversation. Please try again.');
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

if ($isHostViewer) {
    header('Location: /webprogg/host/hostmessages.php?c=' . $conversationId);
} else {
    header('Location: /webprogg/user/usermessages.php?conversation=' . $conversationId);
}
exit;