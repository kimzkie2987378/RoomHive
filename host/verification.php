<?php
require_once __DIR__ . '/host_init.php';

/* Real ID verification status from host_applications
   (status enum: pending / approved / rejected) */
 $appStmt = $pdo->prepare(
    "SELECT id_type, id_number, status, updated_at
     FROM host_applications
     WHERE user_id = :id
     ORDER BY id DESC
     LIMIT 1"
);
 $appStmt->execute(['id' => $_SESSION['user_id']]);
 $application = $appStmt->fetch();

 $idDone    = $application && $application['status'] === 'approved';
 $idPending = $application && $application['status'] === 'pending';

 $items = [
    [
        'done'  => true,
        'title' => 'Email Address',
        'desc'  => 'Your email ' . $dbUser['email'] . ' is on file and verified.',
    ],
    [
        'done'  => ($dbUser['phone'] ?? '') !== '',
        'title' => 'Phone Number',
        'desc'  => ($dbUser['phone'] ?? '') !== ''
            ? 'On file: ' . $dbUser['phone']
            : 'Add a phone number so tenants can reach you about bookings.',
    ],
    [
        'done'  => ($dbUser['location'] ?? '') !== '',
        'title' => 'Address / Location',
        'desc'  => ($dbUser['location'] ?? '') !== ''
            ? 'On file: ' . $dbUser['location']
            : 'Add your location to build tenant trust.',
    ],
    [
        'done'  => $idDone,
        'title' => 'Government ID Verification',
        'desc'  => $idDone
            ? 'Your ' . ($application['id_type'] ?? 'ID') . ' was approved.'
            : ($idPending
                ? 'Your ' . ($application['id_type'] ?? 'ID') . ' submission is pending review.'
                : ($application
                    ? 'Your ID submission was rejected. Please re-apply via Become a Host.'
                    : 'No ID submitted yet. Submit one via Become a Host to get verified.')),
    ],
];

 $doneCount = 0;
foreach ($items as $item) { if ($item['done']) $doneCount++; }

 $activePage = 'verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verification — RoomHive</title>
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
                <h1 class="hp-page-title">Verification</h1>
                <p class="hp-page-subtitle">Verified hosts get more bookings. Complete the checklist below.</p>
            </div>
        </div>

        <section class="hp-card">
            <p class="hp-verify-progress"><strong><?php echo $doneCount; ?> of <?php echo count($items); ?></strong> completed</p>

            <?php foreach ($items as $item): ?>
            <div class="hp-verify-item">
                <div class="hp-verify-icon <?php echo $item['done'] ? 'hp-verify-done' : 'hp-verify-todo'; ?>">
                    <?php echo $item['done'] ? '✓' : '!'; ?>
                </div>
                <div>
                    <strong><?php echo h($item['title']); ?></strong>
                    <p><?php echo h($item['desc']); ?></p>
                </div>
                <?php if (!$item['done'] && in_array($item['title'], ['Phone Number', 'Address / Location'], true)): ?>
                    <a href="/webprogg/host/hosteditprofile.php" class="hp-btn-outline">Update</a>
                <?php elseif (!$item['done'] && $item['title'] === 'Government ID Verification' && !$application): ?>
                    <a href="/webprogg/host/becomeahost.php" class="hp-btn-outline">Submit ID</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>