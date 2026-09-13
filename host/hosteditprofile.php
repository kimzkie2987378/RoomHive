<?php
require_once __DIR__ . '/host_init.php';

/* NOTE: `users` has no `about` (bio) column in your schema.
   The save below tries WITH bio first; if that fails it saves
   everything else and tells you. To enable bio permanently run:
     ALTER TABLE users ADD COLUMN about TEXT NULL;
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $age      = trim($_POST['age'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $about    = trim($_POST['about'] ?? '');

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if ($age !== '' && (!ctype_digit($age) || (int)$age < 18 || (int)$age > 120)) $errors[] = 'Age must be between 18 and 120.';

    /* Email must stay unique */
    if (!$errors) {
        $dupe = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1");
        $dupe->execute(['e' => $email, 'id' => $_SESSION['user_id']]);
        if ($dupe->fetch()) $errors[] = 'That email is already in use by another account.';
    }

    if ($errors) {
        hp_flash_set('error', implode(' ', $errors));
    } else {
        try {
            $up = $pdo->prepare(
                "UPDATE users SET name=:n, email=:e, phone=:p, age=:a, location=:l, about=:ab WHERE id=:id"
            );
            $up->execute(['n'=>$name,'e'=>$email,'p'=>$phone,'a'=>$age !== '' ? (int)$age : null,'l'=>$location,'ab'=>$about,'id'=>$_SESSION['user_id']]);
            hp_flash_set('success', 'Profile updated successfully.');
        } catch (PDOException $e) {
            try {
                $up = $pdo->prepare(
                    "UPDATE users SET name=:n, email=:e, phone=:p, age=:a, location=:l WHERE id=:id"
                );
                $up->execute(['n'=>$name,'e'=>$email,'p'=>$phone,'a'=>$age !== '' ? (int)$age : null,'l'=>$location,'id'=>$_SESSION['user_id']]);
                hp_flash_set('success', 'Profile updated. (Bio not saved — run ALTER TABLE users ADD COLUMN about TEXT NULL to enable it.)');
            } catch (PDOException $e2) {
                hp_flash_set('error', 'Could not save your profile. Please try again.');
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

 $flash = hp_flash_take();
 $activePage = 'editprofile';

/* Re-fetch after potential update (only columns that exist) */
 $re = $pdo->prepare("SELECT name, email, phone, age, location FROM users WHERE id = :id LIMIT 1");
 $re->execute(['id' => $_SESSION['user_id']]);
 $cur = $re->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile &amp; Account — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css">
</head>
<body>

<?php include __DIR__ . '/host_navbar.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

    <?php include __DIR__ . '/host_sidebar.php'; ?>

    <div class="hp-content">

        <div class="hp-page-header">
            <div>
                <h1 class="hp-page-title">Profile &amp; Account</h1>
                <p class="hp-page-subtitle">Update your personal details. Changes appear on your public host profile.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="hp-flash <?php echo $flash['type'] === 'success' ? 'hp-flash-success' : 'hp-flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="hp-card">
            <div class="hp-profile-body" style="margin-bottom:24px;">
                <div class="hp-profile-photo">
                    <img src="<?php echo h($host['avatar']); ?>" alt="<?php echo h($host['name']); ?>">
                </div>
                <div>
                    <span class="hp-field-label">Member since</span>
                    <p class="hp-field-value"><?php echo h($host['member_since']); ?></p>
                    <span class="hp-field-label" style="margin-top:14px;">Account type</span>
                    <p class="hp-field-value">Host</p>
                </div>
                <div></div>
            </div>

            <form method="POST">
                <div class="hp-form-grid">
                    <div class="hp-field">
                        <label for="name">Full Name</label>
                        <input class="hp-input" type="text" id="name" name="name" value="<?php echo h($cur['name']); ?>" required>
                    </div>
                    <div class="hp-field">
                        <label for="email">Email Address</label>
                        <input class="hp-input" type="email" id="email" name="email" value="<?php echo h($cur['email']); ?>" required>
                    </div>
                    <div class="hp-field">
                        <label for="phone">Phone Number</label>
                        <input class="hp-input" type="text" id="phone" name="phone" value="<?php echo h($cur['phone'] ?? ''); ?>" placeholder="09XX XXX XXXX">
                    </div>
                    <div class="hp-field">
                        <label for="age">Age</label>
                        <input class="hp-input" type="number" id="age" name="age" min="18" max="120" value="<?php echo h($cur['age'] ?? ''); ?>">
                    </div>
                    <div class="hp-field">
                        <label for="location">Location</label>
                        <input class="hp-input" type="text" id="location" name="location" value="<?php echo h($cur['location'] ?? ''); ?>" placeholder="City, Province">
                    </div>
                </div>

                <div class="hp-field" style="margin-top:16px;">
                    <label for="about">Bio</label>
                    <textarea class="hp-textarea" id="about" name="about" placeholder="Tell tenants a little about yourself..."></textarea>
                </div>

                <div class="hp-form-actions">
                    <button type="submit" class="hp-btn-primary">SAVE CHANGES</button>
                </div>
            </form>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>