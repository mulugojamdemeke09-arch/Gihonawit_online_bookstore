<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  wishlist.php — saved titles.
 * ===========================================================================
 *  The saved list lives in the session (so guests can use it) and is rendered
 *  with the same card component as the catalogue; the heart on each card
 *  removes an item without a page reload.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

$saved = Wishlist::items();
$total = 0.0;

foreach ($saved as $row) {
    $total += (float) $row['price'];
}

$page_title = t('wish.title') . ' — ' . SITE_NAME;
$page_desc  = 'Books you have saved for later at ' . SITE_NAME . '.';
$active_nav = '';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('wish.title')) ?></span>
</nav>

<div class="section-head">
    <h2><?= e(t('wish.title')) ?></h2>
    <?php if ($saved !== []): ?>
        <span class="muted small"><?= e(tf('wish.count', count($saved))) ?> · <?= e(money($total)) ?> total</span>
    <?php endif; ?>
</div>

<?php if ($saved === []): ?>
    <div class="empty">
        <h3><?= e(t('wish.empty')) ?></h3>
        <p><?= e(t('wish.empty_body')) ?></p>
        <a class="btn btn--primary" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php foreach ($saved as $book) { include GWB_ROOT . '/includes/book-card.php'; } ?>
    </div>

    <p class="muted small" style="margin-top:22px">
        Press the heart on a card to remove a title. Your wishlist is kept in this browser session —
        <a href="<?= e(url('register.php')) ?>">create an account</a> and it will be waiting for you next time.
    </p>
<?php endif; ?>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
