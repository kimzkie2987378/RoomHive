<?php
require_once __DIR__ . '/host_init.php';
 $activePage = 'helpcenter';

 $faqs = [
    ['How do payouts work?', 'Go to Payouts, request any amount from your available balance (min ₱500), and it will be sent to your default payout method within 1–3 business days. Set a default method under Payout Methods first.'],
    ['How do I accept or reject a tenant?', 'Open Pending Tenants (or the 3-dot menu on a listing in Overview) and choose Accept or Reject. Only one active application per listing is shown at a time.'],
    ['When do my listings show as "Active"?', 'New listings start as Pending until the RoomHive team approves them. Once approved, they appear in public search results immediately.'],
    ['How is my occupancy rate calculated?', "It's the number of nights booked in the last 30 days divided by the total available nights across all your listings."],
    ['How do I delete a listing?', 'Overview → My Listings card → 3-dot menu on the listing → Delete Listing. This is permanent and also removes its photos.'],
    ['How do I get the "Verified Host" badge?', 'Complete the items on the Verification page. Your government ID status comes from your Become a Host application.'],
    ['What is Hive Club?', "RoomHive's rewards program. Hosts and tenants earn points on activity which unlock Bronze, Gold, and Platinum perks. See the Hive Club page for tiers."],
    ['How do I change my password?', 'Go to Security in the sidebar. You will need your current password, and the new one must be at least 8 characters.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help Center — RoomHive</title>
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
                <h1 class="hp-page-title">Help Center</h1>
                <p class="hp-page-subtitle">Answers to the questions hosts ask most.</p>
            </div>
        </div>

        <!-- Search -->
        <section class="hp-card">
            <div class="hp-search-wrap" style="max-width:420px;">
                <img src="/webprogg/images/searchicon-userprofile.png" alt="" onerror="this.style.display='none'">
                <input type="text" id="faqSearch" placeholder="Search help articles..." autocomplete="off">
            </div>
        </section>

        <!-- Popular guides -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Popular Guides</h3></div>
            <div class="hp-article-grid">
                <a href="/webprogg/host/howitworks.php" class="hp-article-card">
                    <strong>Hosting 101</strong>
                    <span>How hosting works on RoomHive</span>
                </a>
                <a href="/webprogg/host/becomeahost.php" class="hp-article-card">
                    <strong>Create a Listing</strong>
                    <span>Step-by-step listing guide</span>
                </a>
                <a href="/webprogg/host/payouts.php" class="hp-article-card">
                    <strong>Getting Paid</strong>
                    <span>Payouts and payout methods</span>
                </a>
            </div>
        </section>

        <!-- FAQs -->
        <section class="hp-card">
            <div class="hp-card-header"><h3>Frequently Asked Questions</h3></div>

            <?php foreach ($faqs as $faq): ?>
            <details class="hp-faq" data-faq>
                <summary><?php echo h($faq[0]); ?></summary>
                <p class="hp-faq-body"><?php echo h($faq[1]); ?></p>
            </details>
            <?php endforeach; ?>

            <p class="hp-muted" id="faqNoResults" style="display:none;margin-top:14px;">No articles matched your search.</p>
        </section>

        <!-- Contact support -->
        <section class="hp-grow-banner">
            <div class="hp-grow-text">
                <div>
                    <h3>Still need help?</h3>
                    <p>Our support team replies within 24 hours.</p>
                </div>
            </div>
            <div class="hp-grow-links">
                <a href="/webprogg/misc/contacts.php" class="hp-btn-primary" style="text-decoration:none;">CONTACT SUPPORT</a>
            </div>
        </section>

    </div>
</main>

<?php include __DIR__ . '/host_footer.php'; ?>

<script>
(function () {
    var input = document.getElementById('faqSearch');
    var empty = document.getElementById('faqNoResults');
    if (!input) return;

    var items = Array.prototype.slice.call(document.querySelectorAll('details[data-faq]'));

    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var visible = 0;

        items.forEach(function (d) {
            var text = d.textContent.toLowerCase();
            var match = q === '' || text.indexOf(q) !== -1;
            d.style.display = match ? '' : 'none';
            if (match) visible++;
            if (q !== '' && match) d.open = true;
        });

        empty.style.display = visible === 0 ? '' : 'none';
    });
})();
</script>
</body>
</html>