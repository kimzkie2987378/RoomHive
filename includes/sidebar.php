<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   sidebar.php

   Single source of truth for the account sidebar. Every
   /my-account page previously hand-copied this block and
   drifted out of sync:
     - userprofile.php:   Reviews -> "userreview  s.php" (typo,
                           404), Messages -> "messages.php" (404),
                           Notification Settings ->
                           "notificationsettings.php" (404),
                           Payments -> "payments.php" (404, x2)
     - userbookings.php:  Payments/Reviews/Messages/Notification
                           Settings all pointed at filenames
                           without the "user" prefix (404, x4)
     - userwishlist.php:  Profile & Account, Saved Searches, and
                           Help Center pointed at filenames that
                           don't exist anywhere on disk (404, x3)

   All links below are absolute (site-root) paths so they work
   correctly regardless of which folder the current page lives
   in — userbookings.php is served from /webprogg/booking/ while
   the rest live in /webprogg/user/, so relative links between
   them only ever worked by accident.

   USAGE: set $activeSidebar to one of the keys below before
   including this file, e.g.:
       $activeSidebar = 'profile';
       require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';
========================================================= */

$sidebarLinks = [
    'overview'      => ['/webprogg/user/userprofile.php',            'overviewicon-userprofile.png',            'Overview'],
    'bookings'      => ['/webprogg/booking/userbookings.php',        'bookingsicon-userprofile.png',            'My Bookings'],
    'wishlist'      => ['/webprogg/user/userwishlist.php',           'wihlistedicon-userprofile.png',           'Wishlist'],
    'payments'      => ['/webprogg/user/userpayments.php',           'paymentsicon-userprofile.png',            'Payments'],
    'reviews'       => ['/webprogg/user/userreviews.php',            'averageratinsicon-userprofile.png',       'Reviews'],
    'messages'      => ['/webprogg/user/usermessages.php',           'messagesicon-userprofile.png',            'Messages'],
    'profile'       => ['/webprogg/user/editprofile.php',            'profile&accounticon-userprofile.png',     'Profile &amp; Account'],
    'security'      => ['/webprogg/user/security.php',               'lockicon-userprofile.png',                'Settings'],
    'notifications' => ['/webprogg/user/usernotificationsettings.php','notificationsettings-userprofile.png',   'Notification Settings'],
    'savedsearches' => ['/webprogg/user/savedsearches.php',          'savedsearchesicon-userprofile.png',       'Saved Searches'],
    'help'          => ['/webprogg/user/helpcenter.php',             'needhelpicon-userprofile.png',            'Help Center'],
];
?>
<aside class="up-sidebar">
  <?php foreach ($sidebarLinks as $key => $link): ?>
    <a href="<?php echo h($link[0]); ?>" class="up-side-link<?php echo ($activeSidebar ?? '') === $key ? ' active' : ''; ?>">
      <img src="/webprogg/images/<?php echo h($link[1]); ?>" alt="">
      <?php echo $link[2]; /* pre-escaped literal label above, safe to echo raw */ ?>
    </a>
  <?php endforeach; ?>
  <a href="/webprogg/auth/logout.php" class="up-side-link up-side-logout">
    <img src="/webprogg/images/logouticon-userprofile.png" alt="">
    Log Out
  </a>
</aside>
