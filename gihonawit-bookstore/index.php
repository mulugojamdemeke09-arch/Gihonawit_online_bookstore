<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  index.php — Gihonawit homepage.
 * ===========================================================================
 *  Layout: hero showcase, category ribbon, Featured Books with the
 *  promotional sidebar, then bestsellers and new arrivals.
 *  Every block reads live data from MySQL.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

/* Titles per category tile — one grouped query, mapped in PHP. */
$genreCounts = [];
foreach (db_all('SELECT genre, COUNT(*) AS total FROM books GROUP BY genre') as $row) {
    $genreCounts[(string) $row['genre']] = (int) $row['total'];
}

$ribbon = [];
foreach (categories() as $category) {
    $count = 0;
    foreach ($category['genres'] as $genre) {
        $count += $genreCounts[$genre] ?? 0;
    }
    $category['count'] = $count;
    $ribbon[] = $category;
}

/* Featured titles — administrator-flagged, newest first. */
$featured = db_all(
    'SELECT * FROM books WHERE is_featured = 1 ORDER BY created_at DESC, id DESC LIMIT 5'
);

/* Never leave the homepage grid empty on a fresh install. */
if ($featured === []) {
    $featured = db_all('SELECT * FROM books ORDER BY created_at DESC, id DESC LIMIT 5');
}

/* Bestsellers — ranked by copies actually sold. */
$bestsellers = db_all(
    'SELECT b.*, COALESCE(s.units_sold, 0) AS units_sold
       FROM books b
       LEFT JOIN (SELECT book_id, SUM(quantity) AS units_sold FROM order_items GROUP BY book_id) s
              ON s.book_id = b.id
      WHERE b.stock_quantity > 0
      ORDER BY units_sold DESC, b.title ASC
      LIMIT 5'
);

/* New arrivals. */
$arrivals = db_all('SELECT * FROM books ORDER BY created_at DESC, id DESC LIMIT 5');

/* Store totals for the hero counter strip. */
$totals = db_one(
    'SELECT COUNT(*) AS books,
            COUNT(DISTINCT author) AS authors,
            COALESCE(SUM(stock_quantity), 0) AS copies
       FROM books'
) ?? ['books' => 0, 'authors' => 0, 'copies' => 0];

$slide = hero_slide(0);

$page_title = SITE_NAME . ' ' . SITE_SUFFIX . ' — ' . SITE_TAGLINE;
$page_desc  = 'Buy Ethiopian history, Amharic literature, international fiction and academic books. Free delivery on orders over ' . money(FREE_SHIPPING_THRESHOLD) . '.';
$active_nav = 'home';

require GWB_ROOT . '/includes/header.php';
?>

<!-- ===================================================================== -->
<!-- Hero showcase                                                          -->
<!-- ===================================================================== -->
<section class="hero" data-hero>
    <div class="wrap hero__inner">
        <div class="hero__copy">
            <h1 class="hero__title" <?= is_amharic() ? 'lang="am"' : '' ?>><?= e(t('hero.title')) ?></h1>
            <p class="hero__subtitle" <?= is_amharic() ? 'lang="am"' : '' ?>><?= e(t('hero.subtitle')) ?></p>

            <p class="hero__tag" data-hero-tag><?= e($slide['tag']) ?></p>
            <p class="hero__body" data-hero-body><?= e($slide['body']) ?></p>

            <div class="hero__cta">
                <a class="btn btn--gold btn--lg" href="<?= e(url('books.php')) ?>">
                    <?= e(t('hero.cta')) ?>
                    <?= icon('arrow') ?>
                </a>
                <a class="btn btn--outline btn--lg" href="<?= e(url('books.php?sort=bestselling')) ?>">
                    <?= e(t('section.best_cta')) ?>
                </a>
            </div>

            <p class="small" style="color:rgba(255,255,255,.72);margin-top:20px">
                <?= (int) $totals['books'] ?> titles ·
                <?= (int) $totals['authors'] ?> authors ·
                <?= (int) $totals['copies'] ?> copies in stock
            </p>
        </div>

        <div class="hero__stack" aria-hidden="true">
            <span class="spine spine--1">Ethiopia <em>Land of Origins</em></span>
            <span class="spine spine--2">The Great Rift Valley</span>
            <span class="spine spine--3">Amharic Literature <em lang="am">አማርኛ ሥነ ጽሑፍ</em></span>
            <span class="spine spine--4">Global Classics</span>
            <span class="spine spine--5">Modern World</span>
        </div>
    </div>

    <div class="hero__dots" role="tablist" aria-label="Hero highlights">
        <?php foreach (hero_slides() as $index => $ignored): ?>
            <?php $copy = hero_slide($index); ?>
            <button type="button"
                    class="hero__dot <?= $index === 0 ? 'is-active' : '' ?>"
                    data-slide="<?= $index ?>"
                    data-tag="<?= e($copy['tag']) ?>"
                    data-body="<?= e($copy['body']) ?>"
                    role="tab"
                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                    aria-label="Highlight <?= $index + 1 ?>"></button>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===================================================================== -->
<!-- Category ribbon                                                        -->
<!-- ===================================================================== -->
<section class="ribbon">
    <div class="wrap ribbon__grid">
        <?php foreach ($ribbon as $category): ?>
            <a class="ribbon__item" href="<?= e(url('books.php?category=' . urlencode($category['slug']))) ?>">
                <span class="ribbon__icon"><?= icon((string) $category['icon']) ?></span>
                <span class="ribbon__label" <?= is_amharic() ? 'lang="am"' : '' ?>><?= e(category_label($category)) ?></span>
                <span class="ribbon__count"><?= (int) $category['count'] ?> <?= (int) $category['count'] === 1 ? 'title' : 'titles' ?></span>
            </a>
        <?php endforeach; ?>

        <a class="ribbon__item" href="<?= e(url('books.php')) ?>">
            <span class="ribbon__icon"><?= icon('dots') ?></span>
            <span class="ribbon__label"><?= e(t('ribbon.more')) ?></span>
            <span class="ribbon__count"><?= (int) $totals['books'] ?> titles</span>
        </a>
    </div>
</section>

<!-- ===================================================================== -->
<!-- Featured books + promotional sidebar                                   -->
<!-- ===================================================================== -->
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <h2><?= e(t('section.featured')) ?></h2>
            <a class="link-more" href="<?= e(url('books.php')) ?>">
                <?= e(t('section.view_all')) ?> <?= icon('arrow') ?>
            </a>
        </div>

        <div class="featured-layout">
            <div class="featured-books">
                <div class="featured-books__grid">
                    <?php foreach ($featured as $book) { include GWB_ROOT . '/includes/book-card.php'; } ?>
                </div>
            </div>

            <aside class="promos">
                <div class="promo promo--voices">
                    <h3><?= e(t('section.voices')) ?></h3>
                    <p><?= e(t('section.voices_body')) ?></p>
                    <a class="btn btn--primary btn--sm" href="<?= e(url('books.php?category=ethiopian-history-culture')) ?>">
                        <?= e(t('section.voices_cta')) ?> <?= icon('arrow') ?>
                    </a>
                </div>

                <div class="promo promo--bestsellers">
                    <h3><?= e(t('section.best_title')) ?></h3>
                    <p><?= e(t('section.best_body')) ?></p>
                    <a class="btn btn--gold btn--sm" href="<?= e(url('books.php?sort=bestselling')) ?>">
                        <?= e(t('section.best_cta')) ?> <?= icon('arrow') ?>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- ===================================================================== -->
<!-- Bestsellers + new arrivals                                             -->
<!-- ===================================================================== -->
<section class="section section--cream">
    <div class="wrap split">
        <div>
            <div class="section-head">
                <h2><?= e(t('section.best_title')) ?></h2>
                <a class="link-more" href="<?= e(url('books.php?sort=bestselling')) ?>">
                    <?= e(t('section.view_all')) ?> <?= icon('arrow') ?>
                </a>
            </div>

            <div class="panel">
                <?php if ($bestsellers === []): ?>
                    <p class="muted">No sales recorded yet — place an order and the ranking will fill up.</p>
                <?php else: ?>
                    <div class="rank-list">
                        <?php foreach ($bestsellers as $index => $book): ?>
                            <article class="rank">
                                <span class="rank__no"><?= $index + 1 ?></span>
                                <span class="rank__cover">
                                    <?php if (cover_image($book) !== null): ?>
                                        <img src="<?= e((string) cover_image($book)) ?>" alt="" width="52" height="78" loading="lazy">
                                    <?php else: ?>
                                        <div class="cover cover--t<?= cover_tone($book) ?>" style="width:52px">
                                            <span class="cover__top"><span class="cover__title"><?= e(excerpt((string) $book['title'], 34)) ?></span></span>
                                        </div>
                                    <?php endif; ?>
                                </span>
                                <span>
                                    <a class="rank__title" href="<?= e(url('book-details.php?id=' . (int) $book['id'])) ?>">
                                        <?= e((string) $book['title']) ?>
                                    </a>
                                    <span class="rank__meta"><?= e((string) $book['author']) ?> · <?= (int) $book['units_sold'] ?> copies sold</span>
                                </span>
                                <span class="rank__price"><?= e(money((float) $book['price'])) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside>
            <div class="section-head">
                <h2><?= e(t('section.new')) ?></h2>
            </div>

            <div class="panel">
                <div class="stack" style="gap:12px">
                    <?php foreach ($arrivals as $book): ?>
                        <article class="book-card book-card--compact">
                            <div class="book-card__media">
                                <?php if (cover_image($book) !== null): ?>
                                    <img class="book-card__photo" src="<?= e((string) cover_image($book)) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="cover cover--t<?= cover_tone($book) ?>">
                                        <span class="cover__top"><span class="cover__title"><?= e(excerpt((string) $book['title'], 26)) ?></span></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="book-card__body">
                                <h3 class="book-card__title">
                                    <a href="<?= e(url('book-details.php?id=' . (int) $book['id'])) ?>"><?= e((string) $book['title']) ?></a>
                                </h3>
                                <p class="book-card__author"><?= e((string) $book['author']) ?></p>
                                <div class="book-card__foot">
                                    <span class="book-card__price"><?= e(money((float) $book['price'])) ?></span>
                                    <span class="book-card__cta">
                                        <button type="button" class="btn btn--outline btn--sm" data-add-to-cart="<?= (int) $book['id'] ?>"
                                            <?= (int) $book['stock_quantity'] > 0 ? '' : 'disabled' ?>>
                                            <?= icon('cart') ?>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</section>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
