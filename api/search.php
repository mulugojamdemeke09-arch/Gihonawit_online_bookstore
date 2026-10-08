<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  api/search.php — catalogue search endpoint (JSON).
 * ===========================================================================
 *  Called by js/main.js with the Fetch API: the live search suggestions in
 *  the masthead and the filtering/sorting/paging on books.php both use it.
 *
 *  GET parameters (all optional)
 *    q          free text — matched against title, author, genre, description
 *    category   category-ribbon slug (see categories() in includes/config.php)
 *    author     exact author name
 *    min_price  decimal
 *    max_price  decimal
 *    in_stock   1 to hide out-of-stock titles
 *    sort       newest | title | price_asc | price_desc | bestselling
 *    page       1-based page number
 *    per_page   1-48 (default 12)
 *
 *  Example
 *    api/search.php?q=dune&category=international-fiction-classics&sort=price_asc
 *
 *  Read-only, so no CSRF token is required.
 * ===========================================================================
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/catalogue.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_out(['ok' => false, 'message' => 'Use GET for catalogue searches.'], 405);
}

$filters = catalogue_filters($_GET);
$result  = catalogue_query($filters);

/* Only the fields the grid actually renders are exposed. */
$wishlistIds = Wishlist::ids();
$items       = array_map(
    static fn (array $book): array => catalogue_payload($book, $wishlistIds),
    $result['items']
);

json_out([
    'ok'       => true,
    'query'    => $filters['q'],
    'total'    => $result['total'],
    'page'     => $result['page'],
    'pages'    => $result['pages'],
    'per_page' => $result['per_page'],
    'sort'     => $filters['sort'],
    'items'    => $items,
]);
