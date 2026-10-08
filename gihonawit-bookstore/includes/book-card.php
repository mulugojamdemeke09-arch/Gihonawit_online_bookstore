<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/book-card.php — the catalogue card used across the storefront.
 * ===========================================================================
 *  Include inside a loop, passing one row from the books table:
 *
 *      foreach ($books as $book) { include GWB_ROOT . '/includes/book-card.php'; }
 *
 *  The card renders a real cover photo when books.cover_image_url is set, and
 *  otherwise builds a styled cover template from the title, author and genre
 *  (see the .cover rules in css/style.css) — so the grid always looks complete.
 * ===========================================================================
 */

if (!isset($book) || !is_array($book)) {
    return;
}

$book_id     = (int) $book['id'];
$in_stock    = (int) $book['stock_quantity'] > 0;
$is_featured = (int) ($book['is_featured'] ?? 0) === 1;
$saved       = Wishlist::has($book_id);
$cover       = cover_image($book);
?>
<article class="book-card" data-book-id="<?= $book_id ?>">
    <div class="book-card__media">
        <?php if ($cover !== null): ?>
            <img class="book-card__photo" src="<?= e($cover) ?>"
                 alt="Cover of <?= e((string) $book['title']) ?>" loading="lazy" width="400" height="600">
        <?php else: ?>
            <div class="cover cover--t<?= cover_tone($book) ?>">
                <span class="cover__top">
                    <span class="cover__genre"><?= e((string) $book['genre']) ?></span>
                    <span class="cover__title"><?= e((string) $book['title']) ?></span>
                </span>
                <span class="cover__bottom">
                    <span class="cover__rule"></span>
                    <span class="cover__author"><?= e((string) $book['author']) ?></span>
                </span>
            </div>
        <?php endif; ?>

        <div class="book-card__flags">
            <?php if ($is_featured): ?>
                <span class="tag tag--gold"><?= e(t('card.featured_badge')) ?></span>
            <?php endif; ?>
            <?php if (!$in_stock): ?>
                <span class="tag tag--out"><?= e(t('card.out_of_stock')) ?></span>
            <?php elseif ((int) $book['stock_quantity'] <= 5): ?>
                <span class="tag tag--low"><?= e(tf('card.low_stock', (int) $book['stock_quantity'])) ?></span>
            <?php endif; ?>
        </div>

        <button type="button"
                class="book-card__wish <?= $saved ? 'is-saved' : '' ?>"
                data-wish="<?= $book_id ?>"
                aria-pressed="<?= $saved ? 'true' : 'false' ?>"
                aria-label="<?= e(t('card.wish')) ?>">
            <?= icon('heart') ?>
        </button>
    </div>

    <div class="book-card__body">
        <h3 class="book-card__title">
            <a href="<?= e(url('book-details.php?id=' . $book_id)) ?>"><?= e((string) $book['title']) ?></a>
        </h3>

        <p class="book-card__author"><?= e((string) $book['author']) ?></p>

        <p class="book-card__tags">
            <span class="tag"><?= e((string) $book['genre']) ?></span>
        </p>

        <div class="book-card__foot">
            <span class="book-card__price"><?= e(money((float) $book['price'])) ?></span>
            <span class="book-card__cta">
                <button type="button"
                        class="btn btn--primary btn--sm"
                        data-add-to-cart="<?= $book_id ?>"
                        <?= $in_stock ? '' : 'disabled' ?>>
                    <?= icon('cart') ?>
                    <?= $in_stock ? e(t('card.add_to_cart')) : e(t('card.out_of_stock')) ?>
                </button>
            </span>
        </div>
    </div>
</article>
