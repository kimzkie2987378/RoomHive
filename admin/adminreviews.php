<?php
require_once __DIR__ . '/admin_init.php';

/* ---- Delete review ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'], $_POST['csrf_token'])) {
    if (hash_equals($csrfToken, $_POST['csrf_token'])) {
        $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute(['id' => (int) $_POST['delete_review']]);
        admin_flash_set('success', 'Review deleted.');
    }
    header('Location: /webprogg/admin/adminreviews.php?rating=' . urlencode($_GET['rating'] ?? 'all'));
    exit();
}

 $rating = $_GET['rating'] ?? 'all';
 $valid = ['all', '5', '4', '3', '2', '1'];
if (!in_array($rating, $valid, true)) $rating = 'all';

 $where = []; $params = [];
if ($rating !== 'all') { $where[] = 'ROUND(r.rating) = :rt'; $params['rt'] = (int) $rating; }
 $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

 $reviews = $pdo->prepare(
    "SELECT r.id, r.rating, r.comment, r.created_at,
            u.name AS reviewer_name, u.avatar_path,
            l.title AS listing_title, l.id AS listing_id
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     JOIN listings l ON l.id = r.listing_id
     $whereSql
     ORDER BY r.created_at DESC
     LIMIT 100"
);
 $reviews->execute($params);
 $reviews = $reviews->fetchAll();

 $dist = $pdo->query("SELECT ROUND(rating) star, COUNT(*) c FROM reviews GROUP BY star")->fetchAll(PDO::FETCH_KEY_PAIR);
 $avgRow = $pdo->query("SELECT AVG(rating), COUNT(*) FROM reviews")->fetch(PDO::FETCH_NUM);
 $avgRating = $avgRow[0] !== null ? round((float)$avgRow[0], 1) : null;
 $totalReviews = (int) $avgRow[1];

 $flash = admin_flash_take();
?>
<?php admin_page_start('RoomHive Admin — Reviews', 'Reviews'); ?>

<style>
    .review-stars { color: #F5B301; letter-spacing: 2px; font-size: 14px; }
    .review-comment-cell { max-width: 380px; }
    .review-comment-cell p { margin: 0; font-size: 13px; color: #4b5568; line-height: 1.5; }
</style>

<div class="page-heading">
    <h1>Reviews</h1>
    <p>Moderate guest reviews. Deleting one is permanent.</p>
</div>

<?php if ($flash): ?>
    <div class="flash-banner <?= h($flash['type']) ?>"><?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle') ?><span><?= h($flash['text']) ?></span></div>
<?php endif; ?>

<div class="panel" style="margin-bottom:18px;">
    <div class="stat-value-row" style="margin-bottom:14px;">
        <span class="stat-value" style="font-size:30px;"><?= $avgRating !== null ? h(number_format($avgRating, 1)) : '—' ?></span>
        <span class="review-stars">★</span>
        <span class="stat-caption"><?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?> platform-wide</span>
    </div>
    <div class="summary-grid" style="grid-template-columns: repeat(5, 1fr);">
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <div class="summary-item">
                <div class="summary-icon"><?= icon('star') ?></div>
                <div>
                    <span class="summary-label"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></span>
                    <span class="summary-value"><?= (int) ($dist[$i] ?? 0) ?></span>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<div class="panel">
    <div class="filter-tabs">
        <a class="filter-tab <?= $rating === 'all' ? 'active' : '' ?>" href="?rating=all">All</a>
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <a class="filter-tab <?= $rating === (string)$i ? 'active' : '' ?>" href="?rating=<?= $i ?>"><?= $i ?> ★</a>
        <?php endfor; ?>
    </div>

    <?php if (empty($reviews)): ?>
        <?php emptyState('No reviews in this view.'); ?>
    <?php else: ?>
        <div class="table-scroll">
            <table class="app-table">
                <thead><tr><th>Reviewer</th><th>Listing</th><th>Rating</th><th>Comment</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><span class="people-name"><?= h($r['reviewer_name']) ?></span></td>
                        <td><?= h($r['listing_title']) ?></td>
                        <td><span class="review-stars"><?= str_repeat('★', (int) round((float)$r['rating'])) . str_repeat('☆', 5 - (int) round((float)$r['rating'])) ?></span> <?= h(number_format((float)$r['rating'], 1)) ?></td>
                        <td class="review-comment-cell"><p><?= h(mb_strimwidth($r['comment'] ?? '', 0, 140, '…')) ?></p></td>
                        <td><?= h(date('M j, Y', strtotime($r['created_at']))) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this review permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="delete_review" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="view-all" style="border-color:var(--red);color:var(--red);"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php admin_page_end(); ?>