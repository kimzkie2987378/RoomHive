<?php
/* =========================================================
   ROOMHIVE — START / RESUME CONVERSATION
   start-conversation.php

   Entry point for every "Message Host" button on the site.
   Finds the existing conversation between the logged-in
   renter and the given host, or creates one, then redirects
   straight into usermessages.php with that thread open — so
   "Message Host" always lands the visitor in an actual chat,
   never a dead end.

   Usage: /webprogg/user/start-conversation.php?host_id=5
          (optionally &listing_id=12 when messaging is started
          from a specific listing, so the thread can show what
          it's about)
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/* -----------------------------------------------------
   AUTH GUARD
   Callers should already be hiding "Message Host" from
   guests, but guard here too since this script can be
   reached directly by URL.
----------------------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: /webprogg/auth/loginform.php');
    exit;
}

/* -----------------------------------------------------
   TODO: usermessages.php redirects any is_host=1 account
   straight to hostprofile.php, so a host account that's also
   browsing as a renter and clicks "Message Host" on someone
   else's profile has nowhere to land yet — there's no host
   inbox in what's been built so far. Route them there instead
   once that page exists. For now this assumes the visitor is
   using a renter account.
----------------------------------------------------- */

$userId = (int) $_SESSION['user_id'];

$hostId = isset($_GET['host_id']) && is_numeric($_GET['host_id'])
    ? (int) $_GET['host_id']
    : 0;

$listingId = isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])
    ? (int) $_GET['listing_id']
    : null;

/* Can't message yourself, and the target has to be a real,
   currently-approved host — not just any user id. */
if ($hostId === 0 || $hostId === $userId) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

$hostCheckStmt = $pdo->prepare(
    "SELECT id FROM users WHERE id = :id AND is_host = 1 LIMIT 1"
);
$hostCheckStmt->execute(['id' => $hostId]);

if ($hostCheckStmt->fetchColumn() === false) {
    header('Location: /webprogg/Listings/listing.php');
    exit;
}

/* -----------------------------------------------------
   FIND EXISTING CONVERSATION
   One thread per (renter, host) pair — mirrors how
   usermessages.php lists conversations (grouped by host, not
   by listing), so a renter never ends up with two separate
   threads for the same host just because they messaged from
   two different listings.
----------------------------------------------------- */
$findStmt = $pdo->prepare(
    "SELECT id, listing_id FROM conversations
     WHERE user_id = :uid AND host_id = :hid
     LIMIT 1"
);
$findStmt->execute(['uid' => $userId, 'hid' => $hostId]);
$existing = $findStmt->fetch();

if ($existing) {

    $conversationId = (int) $existing['id'];

    /* If the thread didn't have a listing attached yet and the
       visitor arrived from a specific listing this time, attach
       it now so the thread header can show what it's about.
       Never overwrite a listing that's already set. */
    if ($listingId !== null && $existing['listing_id'] === null) {
        $attachStmt = $pdo->prepare(
            "UPDATE conversations SET listing_id = :lid WHERE id = :id"
        );
        $attachStmt->execute(['lid' => $listingId, 'id' => $conversationId]);
    }

} else {

    $createStmt = $pdo->prepare(
        "INSERT INTO conversations (user_id, host_id, listing_id, last_message_at)
         VALUES (:uid, :hid, :lid, NOW())"
    );
    $createStmt->execute([
        'uid' => $userId,
        'hid' => $hostId,
        'lid' => $listingId,
    ]);

    $conversationId = (int) $pdo->lastInsertId();

}

header('Location: /webprogg/user/usermessages.php?conversation=' . $conversationId);
exit;
