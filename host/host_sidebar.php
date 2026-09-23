<?php
/* Shared host sidebar. Set $activePage before including:
   overview | listings | pending | bookings | earnings | payouts |
   reviews | messages | notifications | editprofile | verification |
   payoutmethods | notificationsettings | security | quithosting |
   helpcenter
   Requires host_init.php ($host, $pending_tenants_count). */
 $activePage = $activePage ?? '';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function hp_side_active($activePage, $key) {
    return $activePage === $key ? ' active' : '';
}

/* -----------------------------------------------------
   UNREAD NOTIFICATION COUNT (for the sidebar badge)
   Computed here (guarded) so EVERY host page gets the live
   badge without each page having to query it. Pages like
   hostnotifications.php pre-set $hostNotifUnread, which is
   reused instead of running a second query.
----------------------------------------------------- */
 $hostNotifUnread = $hostNotifUnread ?? null;

if ($hostNotifUnread === null && isset($_SESSION['user_id'])) {
    try {
        $hnuStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0"
        );
        $hnuStmt->execute(['u' => $_SESSION['user_id']]);
        $hostNotifUnread = (int) $hnuStmt->fetchColumn();
    } catch (Exception $e) {
        $hostNotifUnread = 0;
    }
}
?>
<style>
    .hp-side-link-badged { position: relative; display: flex; align-items: center; gap: 10px; }
    .hp-side-badge {
        margin-left: auto;
        background: #E14B4B;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        padding: 3px 7px;
        border-radius: 999px;
    }
    .hp-sidebar-card .hp-profile-photo {
        margin: 0 auto 12px;
    }
    .hp-sidebar-card .hp-sidebar-avatar {
        width: 64px;
        height: 64px;
    }

    .hp-side-heading {
        margin: 20px 10px 6px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #9aa5b1;
    }
    .hp-side-quit {
        color: #b3261e;
    }
    .hp-side-quit:hover {
        background: #fdecea;
        color: #b3261e;
    }
</style>

<aside class="hp-sidebar">

    <div class="hp-sidebar-card">
        <div class="hp-profile-photo">
            <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>" class="hp-sidebar-avatar" id="hostSidebarAvatarImg">
            <button type="button" class="hp-photo-edit" id="hostPhotoButton" aria-label="Change profile photo">
                <img src="/webprogg/images/cameraicon-userprofile.png" alt="">
            </button>
            <input type="file" id="hostAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>
        <h4><?php echo h($host['name']); ?></h4>
        <span class="hp-host-badge">Host</span>
        <p class="hp-member-since">Member since <?php echo h($host['member_since']); ?></p>
    </div>

    <a href="/webprogg/host/hostprofile.php" class="hp-side-link<?php echo hp_side_active($activePage, 'overview'); ?>">Overview</a>
    <a href="/webprogg/user/mylistings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'listings'); ?>">My Listings</a>

    <a href="/webprogg/booking/pendingtenants.php" class="hp-side-link hp-side-link-badged<?php echo hp_side_active($activePage, 'pending'); ?>">
        Pending Tenants
        <?php if ($pending_tenants_count > 0): ?>
            <span class="hp-side-badge"><?php echo h($pending_tenants_count); ?></span>
        <?php endif; ?>
    </a>

    <a href="/webprogg/host/hostbookings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'bookings'); ?>">Bookings</a>

    <a href="/webprogg/host/earning.php" class="hp-side-link<?php echo hp_side_active($activePage, 'earnings'); ?>">Earnings</a>
    <a href="/webprogg/host/payouts.php" class="hp-side-link<?php echo hp_side_active($activePage, 'payouts'); ?>">Payouts</a>
    <a href="/webprogg/host/hostreviews.php" class="hp-side-link<?php echo hp_side_active($activePage, 'reviews'); ?>">Reviews</a>
    <a href="/webprogg/host/hostmessages.php" class="hp-side-link<?php echo hp_side_active($activePage, 'messages'); ?>">Messages</a>

    <!-- Notifications — links to the HOST notifications page -->
    <a href="/webprogg/host/hostnotifications.php" class="hp-side-link hp-side-link-badged<?php echo hp_side_active($activePage, 'notifications'); ?>">
        Notifications
        <?php if ($hostNotifUnread > 0): ?>
            <span class="hp-side-badge"><?php echo h($hostNotifUnread); ?></span>
        <?php endif; ?>
    </a>

    <!-- SETTINGS GROUP -->
    <span class="hp-side-heading">Settings</span>
    <a href="/webprogg/host/hosteditprofile.php" class="hp-side-link<?php echo hp_side_active($activePage, 'editprofile'); ?>">Profile &amp; Account</a>
    <a href="/webprogg/host/payoutmethods.php" class="hp-side-link<?php echo hp_side_active($activePage, 'payoutmethods'); ?>">Payout Methods</a>
    <a href="/webprogg/host/hostnotificationsettings.php" class="hp-side-link<?php echo hp_side_active($activePage, 'notificationsettings'); ?>">Notification Settings</a>
    <a href="/webprogg/host/hostsecurity.php" class="hp-side-link<?php echo hp_side_active($activePage, 'security'); ?>">Security</a>
    <a href="/webprogg/host/quithosting.php" class="hp-side-link hp-side-quit<?php echo hp_side_active($activePage, 'quithosting'); ?>">Quit Hosting</a>

    <a href="/webprogg/host/helpcenter.php" class="hp-side-link<?php echo hp_side_active($activePage, 'helpcenter'); ?>">Help Center</a>

    <a href="/webprogg/auth/logout.php" class="hp-side-link hp-side-logout">Log Out</a>

</aside>

<script>
/* Sidebar avatar upload — lives here so every host page gets it. */
(function () {
    const photoButton  = document.getElementById('hostPhotoButton');
    const fileInput    = document.getElementById('hostAvatarFileInput');
    const sidebarImg   = document.getElementById('hostSidebarAvatarImg');
    const navAvatarImg = document.getElementById('navAccountAvatarImg');

    if (!photoButton || !fileInput || !sidebarImg) return;

    photoButton.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please choose a JPG, PNG, or WEBP image.');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('That image is too large. Please choose one under 5MB.');
            fileInput.value = '';
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        const previousSrc = sidebarImg.src;
        sidebarImg.src = previewUrl;
        if (navAvatarImg) navAvatarImg.src = previewUrl;
        photoButton.disabled = true;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('/webprogg/user/uploadavatar.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                sidebarImg.src = data.avatar_url;
                if (navAvatarImg) navAvatarImg.src = data.avatar_url;
            } else {
                sidebarImg.src = previousSrc;
                if (navAvatarImg) navAvatarImg.src = previousSrc;
                alert(data.error || 'Could not update your profile photo.');
            }
        })
        .catch(function () {
            sidebarImg.src = previousSrc;
            if (navAvatarImg) navAvatarImg.src = previousSrc;
            alert('Something went wrong uploading your photo. Please try again.');
        })
        .finally(function () {
            URL.revokeObjectURL(previewUrl);
            photoButton.disabled = false;
            fileInput.value = '';
        });
    });
})();
</script>