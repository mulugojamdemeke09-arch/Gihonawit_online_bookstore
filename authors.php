<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  authors.php — browse the catalogue by author.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/catalogue.php';

$authors = catalogue_authors(300);
$letters = [];

foreach ($authors as $author) {
    $initial = strtoupper(mb_substr(trim((string) $author['author']), 0, 1));
    $letters[$initial][] = $author;
}
ksort($letters);

/* One query for every cover, then pick a representative title per author —
   cheaper than one query per author tile. */
$samples = [];
foreach (db_all('SELECT * FROM books ORDER BY is_featured DESC, created_at DESC') as $row) {
    $name = (string) $row['author'];

    if (!isset($samples[$name])) {
        $samples[$name] = $row;
    }
}

$page_title = t('nav.authors') . ' — ' . SITE_NAME;
$page_desc  = 'Browse ' . count($authors) . ' authors stocked by ' . SITE_NAME . '.';
$active_nav = 'authors';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('nav.authors')) ?></span>
</nav>

<div class="section-head">
    <h2><?= e(t('nav.authors')) ?></h2>
    <span class="muted small"><?= count($authors) ?> authors on our shelves</span>
</div>

<?php if ($authors === []): ?>
    <div class="empty">
        <h3>No authors yet</h3>
        <p>Add titles in the admin dashboard and their authors will appear here.</p>
        <a class="btn btn--primary" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
    </div>
<?php else: ?>
    <?php foreach ($letters as $letter => $group): ?>
        <section class="section section--tight">
            <div class="section-head">
                <h2 style="font-size:1.3rem"><?= e($letter) ?></h2>
            </div>

            <div class="book-grid" style="grid-template-columns:repeat(auto-fill,minmax(228px,1fr))">
                <?php foreach ($group as $author): ?>
                <?php
                /* A representative cover for the author tile. */
                $sample = $samples[(string) $author['author']] ?? null;
                ?>
                    <a class="panel" style="display:flex;gap:14px;align-items:center;padding:14px"
                       href="<?= e(url('books.php?author=' . urlencode((string) $author['author']))) ?>">
                        <?php if ($sample !== null): ?>
                            <span style="width:46px;flex:0 0 auto">
                                <?php if (cover_image($sample) !== null): ?>
                                    <img src="<?= e((string) cover_image($sample)) ?>" alt="" width="46" height="69" loading="lazy">
                                <?php else: ?>
                                    <span class="cover cover--t<?= cover_tone($sample) ?>" style="width:46px">
                                        <span class="cover__top"><span class="cover__title" style="font-size:.6rem"><?= e(excerpt((string) $sample['title'], 22)) ?></span></span>
                                    </span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <span>
                            <strong style="display:block;color:var(--ink)"><?= e((string) $author['author']) ?></strong>
                            <span class="muted small">
                                <?= (int) $author['titles'] ?> <?= (int) $author['titles'] === 1 ? 'title' : 'titles' ?>
                                · <?= e(t('common.from')) ?> <?= e(money((float) $author['min_price'])) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
