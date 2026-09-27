<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   sidebar.php

   Single source of truth for the account sidebar.
   USAGE: set $activeSidebar before including:
       $activeSidebar = 'profile';
       require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php';

   === BUGFIX (this version) ===
   h() is now DEFINED here (guarded with function_exists(),
   implementation identical to the host sidebar's h()). The
   previous version called h() without defining it — on user
   pages that never load the host sidebar, the include fataled
   with "Call to undefined function h()".
========================================================= */

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

 $sidebarLinks = [
    'overview'      => ['/webprogg/user/userprofile.php',             'overviewicon-userprofile.png',            'Overview'],
    'bookings'      => ['/webprogg/booking/userbookings.php',         'bookingsicon-userprofile.png',            'My Bookings'],
    'wishlist'      => ['/webprogg/user/userwishlist.php',            'wihlistedicon-userprofile.png',           'Wishlist'],
    'payments'      => ['/webprogg/user/userpayments.php',            'paymentsicon-userprofile.png',            'Payments'],
    'reviews'       => ['/webprogg/user/userreviews.php',             'averageratinsicon-userprofile.png',       'Reviews'],
    'messages'      => ['/webprogg/user/usermessages.php',            'messagesicon-userprofile.png',            'Messages'],
    'notifcenter'   => ['/webprogg/user/notifications.php',           'bell.png',                                'Notifications'],
    'profile'       => ['/webprogg/user/editprofile.php',             'profile&accounticon-userprofile.png',     'Profile &amp; Account'],
    'security'      => ['/webprogg/user/security.php',                'lockicon-userprofile.png',                'Settings'],
    'notifications' => ['/webprogg/user/usernotificationsettings.php','notificationsettings-userprofile.png',    'Notification Settings'],
    'savedsearches' => ['/webprogg/user/savedsearches.php',           'savedsearchesicon-userprofile.png',       'Saved Searches'],
    'help'          => ['/webprogg/user/helpcenter.php',              'needhelpicon-userprofile.png',            'Help Center'],
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