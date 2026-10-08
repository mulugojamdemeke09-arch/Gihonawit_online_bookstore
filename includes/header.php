<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/header.php — masthead, primary navigation and page opening.
 * ===========================================================================
 *  A page may set these before including this file:
 *      $page_title   string   <title> and document heading context
 *      $page_desc    string   meta description
 *      $active_nav   string   home | books | authors | about | contact
 *      $body_class   string   extra <body> classes
 *      $hero_visible bool     true on the homepage (keeps the masthead compact)
 * ===========================================================================
 */

if (!defined('GWB_ROOT')) {
    require_once __DIR__ . '/bootstrap.php';
}

$page_title = $page_title ?? SITE_NAME . ' ' . SITE_SUFFIX;
$page_desc  = $page_desc ?? 'Gihonawit Online Bookstore — Ethiopian history, Amharic literature and international classics, delivered across Ethiopia and beyond.';
$active_nav = $active_nav ?? '';
$body_class = $body_class ?? '';

$user        = current_user();
$cart_count  = Cart::count();
$wish_count  = Wishlist::count();
$search_value = get_param('q');

/* Runtime configuration for js/main.js — includes UI strings so client-side
   messages follow the selected language. */
$gb_config = [
    'baseUrl'   => BASE_URL,
    'csrf'      => csrf_token(),
    'currency'  => SITE_CURRENCY,
    'cartCount' => $cart_count,
    'wishCount' => $wish_count,
    'lang'      => current_lang(),
    'endpoints' => [
        'search'   => url('api/search.php'),
        'cart'     => url('api/cart.php'),
        'wishlist' => url('api/wishlist.php'),
    ],
    'strings'   => [
        'add'          => t('card.add_to_cart'),
        'adding'       => t('card.adding'),
        'added'        => t('card.added'),
        'out'          => t('card.out_of_stock'),
        'low'          => t('card.low_stock'),
        'wish'         => t('card.wish'),
        'featured'     => t('card.featured_badge'),
        'results'      => t('catalogue.results'),
        'foundIn'      => 'books found',
        'none'         => t('catalogue.no_results'),
        'noneHint'     => 'Try a different search term, or clear the filters to see everything.',
        'clear'        => t('catalogue.clear'),
        'error'        => t('catalogue.error'),
        'loading'      => t('catalogue.loading'),
        'searchLabel'  => t('nav.books'),
        'categoryLabel' => t('catalogue.category'),
        'authorLabel'  => t('catalogue.author'),
        'inStockLabel' => t('catalogue.in_stock'),
        'freeGap'      => t('cart.free_gap'),
        'freeOk'       => t('cart.free_ok'),
    ],
];
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($page_desc) ?>">
<meta name="theme-color" content="#0f3d2a">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="<?= e($body_class) ?>">

<a class="skip-link" href="#main">Skip to content</a>
<div class="topline"></div>

<!-- ===================================================================== -->
<!-- Masthead: identity, search, utilities                                  -->
<!-- ===================================================================== -->
<header class="masthead">
    <div class="wrap masthead__inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <?= brand_mark() ?>
            <span>
                <span class="brand__name"><?= e(SITE_NAME) ?></span>
                <span class="brand__sub"><?= e(SITE_SUFFIX) ?></span>
                <span class="brand__tag"><?= e(SITE_TAGLINE) ?></span>
            </span>
        </a>

        <div class="search">
            <form class="search__form" method="get" action="<?= e(url('books.php')) ?>" role="search">
                <label class="visually-hidden" for="site-search"><?= e(t('header.search')) ?></label>
                <input type="search" id="site-search" name="q" value="<?= e($search_value) ?>"
                       placeholder="<?= e(t('header.search')) ?>" autocomplete="off"
                       data-search-input>
                <button type="submit" class="search__btn" aria-label="<?= e(t('nav.books')) ?>">
                    <?= icon('search') ?>
                </button>
            </form>
            <div class="suggest" data-suggest hidden></div>
        </div>

        <div class="utilities">
            <?php if ($user !== null): ?>
                <details class="user-menu" style="position:relative;z-index:100">
                    <summary class="utility" style="list-style:none;cursor:pointer">
                        <?= icon('user') ?>
                        <span class="utility__text">
                            <span><?= e((string) $user['username']) ?></span>
                            <small><?= is_admin() ? 'Administrator' : 'Customer' ?></small>
                        </span>
                    </summary>
                    <div class="lang__menu user-menu__dropdown" style="right:auto;left:0;z-index:100">
                        <a href="<?= e(url('my-orders.php')) ?>"><?= e(t('order.my')) ?></a>
                        <a href="<?= e(url('wishlist.php')) ?>"><?= e(t('wish.title')) ?></a>
                        <?php if (is_admin()): ?>
                            <a href="<?= e(url('admin/dashboard.php')) ?>">Admin dashboard</a>
                        <?php endif; ?>
                        <a href="<?= e(url('logout.php')) ?>"><?= e(t('auth.sign_out')) ?></a>
                    </div>
                </details>
            <?php else: ?>
                <a class="utility" href="<?= e(url('login.php')) ?>">
                    <?= icon('user') ?>
                    <span class="utility__text">
                        <span><?= e(t('header.login')) ?></span>
                        <small><?= e(t('auth.sign_up')) ?></small>
                    </span>
                </a>
            <?php endif; ?>

            <a class="utility" href="<?= e(url('wishlist.php')) ?>">
                <?= icon('heart') ?>
                <span class="utility__text">
                    <span><?= e(t('header.wishlist')) ?></span>
                </span>
                <span class="badge-count" data-wish-count <?= $wish_count === 0 ? 'hidden' : '' ?>><?= (int) $wish_count ?></span>
            </a>

            <a class="utility" href="<?= e(url('cart.php')) ?>">
                <?= icon('cart') ?>
                <span class="utility__text">
                    <span><?= e(t('header.cart')) ?></span>
                </span>
                <span class="badge-count" data-cart-count <?= $cart_count === 0 ? 'hidden' : '' ?>><?= (int) $cart_count ?></span>
            </a>
        </div>
    </div>
</header>

<!-- ===================================================================== -->
<!-- Primary navigation + language switch                                   -->
<!-- ===================================================================== -->
<nav class="navbar" aria-label="Primary">
    <div class="wrap navbar__inner">
        <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="primary-links">
            <?= icon('dots') ?>
            <span><?= e(t('header.menu')) ?></span>
        </button>

        <div class="navbar__links" id="primary-links" data-nav>
            <?php foreach (main_menu() as $item): ?>
                <?php
                $is_active = $active_nav === str_replace('nav.', '', $item['label']);
                $href      = strpos($item['url'], 'http') === 0 ? $item['url'] : url($item['url']);
                ?>
                <a class="nav-link <?= $is_active ? 'is-active' : '' ?>" href="<?= e($href) ?>">
                    <?php if (!empty($item['icon'])): ?><?= icon((string) $item['icon']) ?><?php endif; ?>
                    <?= e(t($item['label'])) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <details class="lang">
            <summary>
                <?= flag_icon() ?>
                <span><?= e(languages()[current_lang()]) ?></span>
                <?= icon('chevron') ?>
            </summary>
            <div class="lang__menu">
                <?php foreach (languages() as $code => $label): ?>
                    <a href="<?= e(url(current_url(['lang' => $code]))) ?>"
                       class="<?= $code === current_lang() ? 'is-current' : '' ?>"
                       hreflang="<?= e($code) ?>">
                        <span <?= $code === 'am' ? 'lang="am"' : '' ?>><?= e($label) ?></span>
                        <?php if ($code === current_lang()): ?><?= icon('check') ?><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
</nav>

<div class="toasts" role="status" aria-live="polite"></div>
