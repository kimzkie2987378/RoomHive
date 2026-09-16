<?php
require_once __DIR__ . '/host_init.php';

 $activePage = 'notificationsettings';

/* The 6 REAL columns in your notification_preferences table */
 $prefKeys = [
    'email_booking_updates' => ['Email · Booking updates', 'Requests, confirmations, and changes for your listings.'],
    'email_messages'        => ['Email · New messages', 'When a tenant sends you a message.'],
    'email_promos'          => ['Email · Promotions', 'Platform-wide promos and offers.'],
    'email_reviews'         => ['Email · Reviews', 'When a tenant leaves a review on your listing.'],
    'push_booking_updates'  => ['Push · Booking updates', 'Real-time booking alerts on this browser.'],
    'push_messages'         => ['Push · New messages', 'Real-time message alerts on this browser.'],
];

 $userId = (int) $_SESSION['user_id'];

/* ---- Save ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $values = [];
    foreach ($prefKeys as $key => $meta) {
        $values[$key] = isset($_POST[$key]) ? 1 : 0;
    }

    try {
        $check = $pdo->prepare("SELECT user_id FROM notification_preferences WHERE user_id = :u LIMIT 1");
        $check->execute(['u' => $userId]);

        if ($check->fetch()) {
            $up = $pdo->prepare(
                "UPDATE notification_preferences
                 SET email_booking_updates = :a, email_messages = :b, email_promos = :c,
                     email_reviews = :d, push_booking_updates = :e, push_messages = :f,
                     updated_at = NOW()
                 WHERE user_id = :u"
            );
        } else {
            $up = $pdo->prepare(
                "INSERT INTO notification_preferences
                    (user_id, email_booking_updates, email_messages, email_promos,
                     email_reviews, push_booking_updates, push_messages)
                 VALUES (:u, :a, :b, :c, :d, :e, :f)"
            );
        }
        $up->execute([
            'u' => $userId,
            'a' => $values['email_booking_updates'],
            'b' => $values['email_messages'],
            'c' => $values['email_promos'],
            'd' => $values['email_reviews'],
            'e' => $values['push_booking_updates'],
            'f' => $values['push_messages'],
        ]);
        hp_flash_set('success', 'Notification preferences saved.');
    } catch (PDOException $e) {
        hp_flash_set('error', 'Could not save your preferences.');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ---- Load current values (default ON if no row yet) ---- */
 $prefs = array_fill_keys(array_keys($prefKeys), 1);
try {
    $row = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = :u LIMIT 1");
    $row->execute(['u' => $userId]);
    if ($dbRow = $row->fetch()) {
        foreach ($prefKeys as $key => $meta) {
            if (array_key_exists($key, $dbRow)) {
                $prefs[$key] = (int) $dbRow[$key];
            }
        }
    }
} catch (PDOException $e) { /* defaults apply */ }

 $flash = hp_flash_take();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notification Settings — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>
 <?php include $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/notification_dropdown.php'; ?>
<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Notification Settings</h1>
                <p class="hp-page-subtitle">Saved to your RoomHive account.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="hp-card">
            <form method="POST">
                <?php foreach ($prefKeys as $key => [$title, $desc]): ?>
                <div class="hp-setting-row">
                    <div>
                        <strong><?php echo h($title); ?></strong>
                        <p><?php echo h($desc); ?></p>
                    </div>
                    <label class="hp-switch">
                        <input type="checkbox" name="<?php echo h($key); ?>" <?php echo $prefs[$key] ? 'checked' : ''; ?>>
                        <span class="hp-slider"></span>
                    </label>
                </div>
                <?php endforeach; ?>

                <div class="hp-form-actions">
                    <button type="submit" name="save_prefs" value="1" class="hp-btn-primary">SAVE PREFERENCES</button>
                </div>
            </form>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>