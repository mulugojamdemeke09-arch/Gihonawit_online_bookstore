<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  books.php — the catalogue: search, category, author, price and stock
 *  filters, sorting and pagination.
 * ===========================================================================
 *  Two modes, identical output:
 *    · plain GET  — the filter form posts back and PHP renders the grid
 *    · AJAX       — js/main.js calls api/search.php and re-renders in place
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/catalogue.php';

$filters = catalogue_filters($_GET);
$result  = catalogue_query($filters);

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

$authors      = catalogue_authors(300);
$activeCat    = $filters['category'] !== '' ? category_by_slug((string) $filters['category']) : null;

$page_title = $activeCat !== null
    ? category_label($activeCat) . ' — ' . SITE_NAME
    : ($filters['q'] !== '' ? 'Search: ' . $filters['q'] : t('catalogue.all') . ' — ' . SITE_NAME);
$page_desc  = 'Browse ' . $result['total'] . ' titles. Filter by category, author, price and availability.';
$active_nav = 'books';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= $activeCat !== null ? e(category_label($activeCat)) : e(t('catalogue.all')) ?></span>
</nav>

<div class="section-head">
    <h2><?= $activeCat !== null ? e(category_label($activeCat)) : e(t('catalogue.all')) ?></h2>
</div>

<div class="catalogue" data-catalogue>
    <!-- ---------------------------------------------------------------- -->
    <!-- Filters                                                            -->
    <!-- ---------------------------------------------------------------- -->
    <form class="filters" id="catalogue-form" method="get" action="<?= e(url('books.php')) ?>" data-filters>
        <div class="field" style="margin-bottom:2px">
            <label for="filter-q"><?= e(t('header.search_short')) ?></label>
            <input class="input" type="search" id="filter-q" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(t('header.search')) ?>" autocomplete="off" data-cat-input>
        </div>

        <fieldset>
            <legend><?= e(t('catalogue.category')) ?></legend>
            <div class="filters__list">
                <label>
                    <span><input type="radio" name="category" value="" <?= $filters['category'] === '' ? 'checked' : '' ?>> <?= e(t('catalogue.all')) ?></span>
                    <span class="filters__count"><?= array_sum($genreCounts) ?></span>
                </label>
                <?php foreach ($ribbon as $category): ?>
                    <label>
                        <span>
                            <input type="radio" name="category" value="<?= e($category['slug']) ?>"
                                <?= $filters['category'] === $category['slug'] ? 'checked' : '' ?>>
                            <span <?= is_amharic() ? 'lang="am"' : '' ?>><?= e(category_label($category)) ?></span>
                        </span>
                        <span class="filters__count"><?= (int) $category['count'] ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend><?= e(t('catalogue.author')) ?></legend>
            <label class="visually-hidden" for="filter-author"><?= e(t('catalogue.author')) ?></label>
            <select class="select" id="filter-author" name="author">
                <option value="">All authors</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?= e((string) $author['author']) ?>"
                        <?= $filters['author'] === (string) $author['author'] ? 'selected' : '' ?>>
                        <?= e((string) $author['author']) ?> (<?= (int) $author['titles'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </fieldset>

        <fieldset>
            <legend><?= e(t('catalogue.price')) ?></legend>
            <div class="row" style="gap:8px">
                <input class="input" type="number" name="min_price" min="0" step="0.01"
                       value="<?= e($filters['min_price']) ?>" placeholder="<?= e(t('catalogue.min')) ?>"
                       aria-label="<?= e(t('catalogue.min')) ?>">
                <input class="input" type="number" name="max_price" min="0" step="0.01"
                       value="<?= e($filters['max_price']) ?>" placeholder="<?= e(t('catalogue.max')) ?>"
                       aria-label="<?= e(t('catalogue.max')) ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend><?= e(t('catalogue.stock')) ?></legend>
            <label class="checkbox">
                <input type="checkbox" name="in_stock" value="1" <?= $filters['in_stock'] ? 'checked' : '' ?>>
                <span><?= e(t('catalogue.in_stock')) ?></span>
            </label>
        </fieldset>

        <div class="row" style="gap:8px">
            <button type="submit" class="btn btn--primary" style="flex:1"><?= e(t('catalogue.apply')) ?></button>
            <a class="btn btn--outline" href="<?= e(url('books.php')) ?>"><?= e(t('catalogue.clear')) ?></a>
        </div>
    </form>

    <!-- ---------------------------------------------------------------- -->
    <!-- Results                                                            -->
    <!-- ---------------------------------------------------------------- -->
    <div>
        <div class="toolbar">
            <p class="toolbar__count" data-count aria-live="polite">
                <strong><?= (int) $result['total'] ?></strong> books found
            </p>

            <div class="row" style="gap:10px">
                <label class="small muted nowrap" for="sort"><?= e(t('catalogue.sort')) ?></label>
                <select class="select" id="sort" name="sort" form="catalogue-form" data-sort style="min-width:196px">
                    <?php foreach (sort_options() as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="chips" data-chips></div>

        <div class="book-grid" data-results>
            <?php if ($result['items'] === []): ?>
                <div class="empty" style="grid-column:1/-1">
                    <h3><?= e(t('catalogue.no_results')) ?></h3>
                    <p>Try a different search term, or clear the filters to see everything.</p>
                    <a class="btn btn--outline" href="<?= e(url('books.php')) ?>"><?= e(t('catalogue.clear')) ?></a>
                </div>
            <?php else: ?>
                <?php foreach ($result['items'] as $book) { include GWB_ROOT . '/includes/book-card.php'; } ?>
            <?php endif; ?>
        </div>

        <nav class="pager" aria-label="Catalogue pages" data-pager>
            <?php if ($result['pages'] > 1): ?>
                <?php
                $current = (int) $result['page'];
                $last    = (int) $result['pages'];
                $from    = max(1, $current - 2);
                $to      = min($last, $from + 4);
                $from    = max(1, $to - 4);
                ?>
                <a href="<?= e(catalogue_link($filters, ['page' => max(1, $current - 1)])) ?>"
                   class="<?= $current <= 1 ? 'is-disabled' : '' ?>" rel="prev">&larr; Prev</a>
                <?php for ($page = $from; $page <= $to; $page++): ?>
                    <?php if ($page === $current): ?>
                        <span class="is-current" aria-current="page"><?= $page ?></span>
                    <?php else: ?>
                        <a href="<?= e(catalogue_link($filters, ['page' => $page])) ?>" data-page="<?= $page ?>"><?= $page ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="<?= e(catalogue_link($filters, ['page' => min($last, $current + 1)])) ?>"
                   class="<?= $current >= $last ? 'is-disabled' : '' ?>" rel="next">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
