<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  about.php — the shop's story.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

$totals = db_one(
    'SELECT COUNT(*) AS books, COUNT(DISTINCT author) AS authors, COUNT(DISTINCT genre) AS genres
       FROM books'
) ?? ['books' => 0, 'authors' => 0, 'genres' => 0];

$page_title = t('nav.about') . ' — ' . SITE_NAME;
$page_desc  = 'About Gihonawit Online Bookstore — Ethiopian voices and world literature, delivered.';
$active_nav = 'about';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('nav.about')) ?></span>
</nav>

<section class="hero" style="min-height:300px;background-position:center 30%">
    <div class="wrap hero__inner" style="padding-block:44px 54px;grid-template-columns:minmax(0,1fr)">
        <div class="hero__copy">
            <h1 class="hero__title" style="font-size:clamp(2rem,3.4vw,2.7rem)"><?= e(SITE_NAME) ?> <?= e(SITE_SUFFIX) ?></h1>
            <p class="hero__tag" style="margin-top:12px"><?= e(SITE_TAGLINE) ?></p>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div class="prose">
            <p class="eyebrow">Our story</p>
            <h2 style="margin-bottom:14px">Books for a Brighter Tomorrow</h2>

            <p>Gihonawit began with a simple frustration: the books that shaped us — Ethiopian history,
               Amharic literature, the classics we studied and the new titles everyone was talking about —
               were scattered across different shops, different cities and different websites.</p>

            <p>So we built one shelves-are-open-all-night bookshop. Ethiopian voices sit beside global
               bestsellers; a history of the highlands sits beside a guide to writing better code. Every
               title is stocked, priced and dispatched from Addis Ababa, and every order supports the
               authors and translators who make the work possible.</p>

            <p>We ship across Ethiopia and beyond, we take payment on delivery for customers who prefer it,
               and if a book is not right for you, send it back within thirty days.</p>

            <h3 style="margin:26px 0 12px">What we stock</h3>
            <div class="spec-list">
                <?php
                $pillars = [
                    ['Ethiopian History & Culture', 'From Aksum and Lalibela to the modern state.'],
                    ['Amharic Literature', 'Fiction and poetry from Ethiopia\'s finest writers.'],
                    ['International Fiction & Classics', 'The novels everyone should read once.'],
                    ['Education & Academic', 'Reference and coursework for students and teachers.'],
                    ['Self-Help & Personal Development', 'Practical books that change how you work.'],
                    ['Science & Technology', 'Science writing and software craft.'],
                    ['Arts & Lifestyle', 'Design, art history, food and craft.'],
                ];
                foreach ($pillars as [$title, $blurb]): ?>
                    <div class="spec">
                        <dt><?= e($title) ?></dt>
                        <dd style="font-weight:400;color:var(--body);font-size:.88rem"><?= e($blurb) ?></dd>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="stack">
            <div class="panel">
                <h3>The shop in numbers</h3>
                <div class="stat-strip" style="grid-template-columns:1fr;margin:16px 0 0">
                    <div class="stat stat--forest">
                        <span>Titles in stock</span>
                        <strong><?= (int) $totals['books'] ?></strong>
                        <small>Across every category</small>
                    </div>
                    <div class="stat stat--gold">
                        <span>Authors represented</span>
                        <strong><?= (int) $totals['authors'] ?></strong>
                        <small>Ethiopian and international</small>
                    </div>
                    <div class="stat">
                        <span>Categories</span>
                        <strong><?= (int) $totals['genres'] ?></strong>
                        <small>Plus new shelves every season</small>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3>Visit or contact us</h3>
                <p class="small" style="margin-top:12px">
                    <?= e(SITE_ADDRESS) ?><br><br>
                    <a href="tel:<?= e(str_replace(' ', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a><br>
                    <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a><br><br>
                    <?= e(SITE_HOURS) ?>
                </p>
                <a class="btn btn--primary btn--block" style="margin-top:16px" href="<?= e(url('contact.php')) ?>">
                    <?= e(t('nav.contact')) ?>
                </a>
            </div>
        </aside>
    </div>
</section>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
