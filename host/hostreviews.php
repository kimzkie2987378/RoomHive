<?php
require_once __DIR__ . '/host_init.php';

/* Uses the real `reviews` table (rating decimal(2,1), comment,
   user_id, listing_id). */
 $st = $pdo->prepare(
    "SELECT r.rating, r.comment, r.created_at,
            u.name AS reviewer_name, u.avatar_path AS reviewer_avatar,
            l.title AS listing_title
     FROM reviews r
     JOIN listings l ON l.id = r.listing_id
     JOIN users u ON u.id = r.user_id
     WHERE l.user_id = :id
     ORDER BY r.created_at DESC"
);
 $st->execute(['id' => $_SESSION['user_id']]);
 $reviews = $st->fetchAll();

 $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
 $sum = 0;
foreach ($reviews as $r) {
    $star = (int) round((float) $r['rating']);
    $star = max(1, min(5, $star));
    $dist[$star]++;
    $sum += (float) $r['rating'];
}
 $totalReviews = count($reviews);
 $avgRating = $totalReviews > 0 ? $sum / $totalReviews : 0;

function hp_stars($rating) {
    $full = (int) floor((float) $rating);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

 $activePage = 'reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviews — RoomHive</title>
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
                <h1 class="hp-page-title">Reviews</h1>
                <p class="hp-page-subtitle">What tenants say about your listings.</p>
            </div>
        </div>

        <!-- Rating summary -->
        <section class="hp-card">
            <div class="hp-rating-hero">
                <div style="text-align:center;">
                    <div class="hp-rating-big"><?php echo h(number_format($avgRating, 1)); ?></div>
                    <div class="hp-stars"><?php echo hp_stars($avgRating); ?></div>
                    <div class="hp-rating-count"><?php echo h($totalReviews); ?> review<?php echo $totalReviews === 1 ? '' : 's'; ?></div>
                </div>
                <div class="hp-distribution">
                    <?php for ($i = 5; $i >= 1; $i--):
                        $pct = $totalReviews > 0 ? round(($dist[$i] / $totalReviews) * 100) : 0;
                    ?>
                    <div class="hp-dist-row">
                        <span style="width:34px;"><?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?></span>
                        <div class="hp-dist-track"><div class="hp-dist-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                        <span style="width:30px;text-align:right;"><?php echo $dist[$i]; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- Review list -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>All Reviews</h3></div>

            <?php if (empty($reviews)): ?>
                <div class="hp-empty-state">
                    <p class="hp-empty-state-title">No reviews yet</p>
                    <p>After tenants review your listings, their feedback will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                <div class="hp-review-item">
                    <img class="hp-review-avatar" src="<?php echo h(resolve_photo($r['reviewer_avatar'], '/webprogg/images/default-avatar.png')); ?>" alt="">
                    <div style="flex:1;min-width:0;">
                        <div class="hp-review-head">
                            <strong><?php echo h($r['reviewer_name']); ?></strong>
                            <span class="hp-stars" style="font-size:13px;"><?php echo hp_stars((float)$r['rating']); ?></span>
                            <span style="color:var(--hp-text-muted);font-size:12px;"><?php echo h(number_format((float)$r['rating'], 1)); ?>/5</span>
                            <span class="hp-review-date"><?php echo h(date('M j, Y', strtotime($r['created_at']))); ?></span>
                        </div>
                        <span class="hp-review-listing">on <?php echo h($r['listing_title']); ?></span>
                        <p class="hp-review-text"><?php echo h($r['comment'] ?? ''); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>
</body>
</html>