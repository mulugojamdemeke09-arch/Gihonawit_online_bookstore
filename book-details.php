<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  book-details.php — individual product presentation.
 * ===========================================================================
 *  Cover, stock status, description, specification list, delivery note, the
 *  purchase box and related titles from the same genre.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

$bookId = (int) get_param('id');
$book   = $bookId > 0
    ? db_one('SELECT * FROM books WHERE id = :id LIMIT 1', [':id' => $bookId])
    : null;

if ($book === null) {
    http_response_code(404);
    $page_title = t('common.not_found');

    require GWB_ROOT . '/includes/header.php';
    ?>
    <div class="empty">
        <h3><?= e(t('common.not_found')) ?></h3>
        <p>That book is not in our catalogue — it may have been removed.</p>
        <a class="btn btn--primary" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
    </div>
    <?php
    require GWB_ROOT . '/includes/footer.php';
    exit;
}

$id          = (int) $book['id'];
$stock       = (int) $book['stock_quantity'];
$inStock     = $stock > 0;
$maxQty      = max(1, min(MAX_QTY_PER_LINE, $inStock ? $stock : 1));
$cover       = cover_image($book);
$saved       = Wishlist::has($id);
$unitsSold   = (int) db_value('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE book_id = :id', [':id' => $id]);

$related = db_all(
    'SELECT * FROM books WHERE genre = :genre AND id <> :id AND stock_quantity > 0
      ORDER BY created_at DESC LIMIT 4',
    [':genre' => (string) $book['genre'], ':id' => $id]
);

$page_title = (string) $book['title'] . ' — ' . SITE_NAME;
$page_desc  = excerpt((string) $book['description'], 155);
$active_nav = 'books';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <a href="<?= e(url('books.php')) ?>"><?= e(t('nav.books')) ?></a>
    <span aria-hidden="true">/</span>
    <a href="<?= e(url('books.php?q=' . urlencode((string) $book['genre']))) ?>"><?= e((string) $book['genre']) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e((string) $book['title']) ?></span>
</nav>

<div class="detail">
    <!-- ---------------------------------------------------------------- -->
    <!-- Media + actions                                                    -->
    <!-- ---------------------------------------------------------------- -->
    <div class="detail__media">
        <?php if ($cover !== null): ?>
            <img src="<?= e($cover) ?>" alt="Cover of <?= e((string) $book['title']) ?>" width="400" height="600">
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

        <div class="detail__actions">
            <button type="button"
                    class="btn btn--outline btn--block <?= $saved ? 'is-saved' : '' ?>"
                    data-wish="<?= $id ?>"
                    aria-pressed="<?= $saved ? 'true' : 'false' ?>">
                <?= icon('heart') ?>
                <span data-wish-label><?= $saved ? e(t('card.unwish')) : e(t('card.wish')) ?></span>
            </button>
        </div>
    </div>

    <!-- ---------------------------------------------------------------- -->
    <!-- Information                                                        -->
    <!-- ---------------------------------------------------------------- -->
    <div>
        <p class="eyebrow" <?= is_amharic() ? 'lang="am"' : '' ?>><?= e((string) $book['genre']) ?></p>
        <h1 class="detail__title"><?= e((string) $book['title']) ?></h1>
        <p class="detail__author">
            <?= e(t('book.by')) ?> <strong><?= e((string) $book['author']) ?></strong>
        </p>

        <div class="detail__meta">
            <span class="tag tag--forest"><?= e((string) $book['genre']) ?></span>
            <span class="tag <?= $inStock ? 'tag--forest' : 'tag--out' ?>"><?= e(stock_label($stock)) ?></span>
            <span class="tag"><?= (int) $unitsSold ?> <?= e(t('book.sold')) ?></span>
            <?php if ((int) $book['is_featured'] === 1): ?>
                <span class="tag tag--gold"><?= e(t('card.featured_badge')) ?></span>
            <?php endif; ?>
        </div>

        <div class="detail__price">
            <b><?= e(money((float) $book['price'])) ?></b>
            <span><?= e(tf('book.free_ship', money(FREE_SHIPPING_THRESHOLD))) ?></span>
        </div>

        <!-- Purchase box -->
        <?php if ($inStock): ?>
            <form class="buy-box" data-purchase method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="book_id" value="<?= $id ?>">

                <div class="qty" data-qty>
                    <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                    <label class="visually-hidden" for="quantity"><?= e(t('book.quantity')) ?></label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= $maxQty ?>">
                    <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                </div>

                <button type="button" class="btn btn--primary btn--lg" data-add-to-cart="<?= $id ?>">
                    <?= icon('cart') ?>
                    <?= e(t('card.add_to_cart')) ?>
                </button>

                <noscript>
                    <button type="submit" class="btn btn--primary btn--lg"><?= e(t('card.add_to_cart')) ?></button>
                </noscript>

                <a class="btn btn--outline btn--lg" href="<?= e(url('cart.php')) ?>"><?= e(t('header.cart')) ?></a>
            </form>
        <?php else: ?>
            <div class="buy-box">
                <span class="tag tag--out"><?= e(t('card.out_of_stock')) ?></span>
                <span class="muted small"><?= e(t('book.unavailable')) ?></span>
                <button type="button" class="btn btn--outline" data-wish="<?= $id ?>"><?= icon('heart') ?> <?= e(t('card.wish')) ?></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs" data-tabs role="tablist">
            <button class="tab is-active" data-tab="desc" role="tab" aria-selected="true"><?= e(t('book.description')) ?></button>
            <button class="tab" data-tab="specs" role="tab" aria-selected="false"><?= e(t('book.specs')) ?></button>
            <button class="tab" data-tab="delivery" role="tab" aria-selected="false"><?= e(t('cart.shipping')) ?></button>
        </div>

        <section data-tab-panel="desc" role="tabpanel">
            <div class="prose">
                <p><?= nl2br(e((string) ($book['description'] ?: 'No description has been added for this title yet.'))) ?></p>
            </div>
        </section>

        <section data-tab-panel="specs" role="tabpanel" hidden>
            <dl class="spec-list">
                <div class="spec"><dt><?= e(t('book.genre')) ?></dt><dd><?= e((string) $book['genre']) ?></dd></div>
                <div class="spec"><dt><?= e(t('catalogue.author')) ?></dt><dd><?= e((string) $book['author']) ?></dd></div>
                <div class="spec"><dt><?= e(t('book.price')) ?></dt><dd><?= e(money((float) $book['price'])) ?></dd></div>
                <div class="spec"><dt><?= e(t('book.availability')) ?></dt><dd><?= e(stock_label($stock)) ?></dd></div>
                <div class="spec"><dt><?= e(t('book.sold')) ?></dt><dd><?= (int) $unitsSold ?></dd></div>
                <div class="spec"><dt>Added</dt><dd><?= e(date('j M Y', strtotime((string) $book['created_at']))) ?></dd></div>
            </dl>
        </section>

        <section data-tab-panel="delivery" role="tabpanel" hidden>
            <div class="prose">
                <p><strong>Standard delivery.</strong> Dispatched within one working day, 2–4 working days in
                    transit. Free on orders over <?= e(money(FREE_SHIPPING_THRESHOLD)) ?>,
                    otherwise <?= e(money(FLAT_SHIPPING_RATE)) ?>.</p>
                <p><strong>Cash on delivery.</strong> Pay the courier when the parcel arrives; a signature is
                    required for every delivery.</p>
                <p><strong>Returns.</strong> Send any book back within 30 days in resalable condition for a full
                    refund. Faulty copies are replaced free of charge.</p>
            </div>
        </section>
    </div>
</div>

<?php if ($related !== []): ?>
<section class="section">
    <div class="section-head">
        <h2><?= e(t('book.related')) ?></h2>
        <a class="link-more" href="<?= e(url('books.php?q=' . urlencode((string) $book['genre']))) ?>">
            <?= e(t('section.view_all')) ?> <?= icon('arrow') ?>
        </a>
    </div>

    <div class="book-grid">
        <?php foreach ($related as $book) { include GWB_ROOT . '/includes/book-card.php'; } ?>
    </div>
</section>
<?php endif; ?>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
