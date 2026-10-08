<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/catalogue.php — one search implementation, shared by the
 *  catalogue page (books.php) and the JSON endpoint (api/search.php).
 * ===========================================================================
 *  Keeping the query in one place means the server-rendered grid and the
 *  AJAX-refreshed grid can never drift apart.
 * ===========================================================================
 */

/** Sort keys the interface offers. Anything else falls back to "newest". */
function catalogue_sorts(): array
{
    return ['newest', 'title', 'price_asc', 'price_desc', 'bestselling'];
}

/**
 * Normalise raw request input into a validated filter set.
 *
 * @param  array<string,mixed> $input  usually $_GET
 * @return array<string,mixed>
 */
function catalogue_filters(array $input = []): array
{
    $pick = static function (string $key, string $default = '') use ($input): string {
        $value = $input[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    };

    $sort = $pick('sort', 'newest');

    return [
        'q'         => $pick('q'),
        'category'  => $pick('category'),
        'author'    => $pick('author'),
        'min_price' => $pick('min_price'),
        'max_price' => $pick('max_price'),
        'in_stock'  => $pick('in_stock') === '1',
        'sort'      => in_array($sort, catalogue_sorts(), true) ? $sort : 'newest',
        'page'      => max(1, (int) ($input['page'] ?? 1)),
        'per_page'  => min(48, max(1, (int) ($input['per_page'] ?? 12))),
    ];
}

/**
 * Run the catalogue query.
 *
 * @param  array<string,mixed> $filters as produced by catalogue_filters()
 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int,filters:array<string,mixed>}
 */
function catalogue_query(array $filters): array
{
    $where  = [];
    $params = [];

    /* --- free text across title, author, genre and description --- */
    if ($filters['q'] !== '') {
        /* Distinct placeholders — with emulation off PDO cannot reuse one name. */
        $where[]       = '(b.title LIKE :q1 OR b.author LIKE :q2 OR b.genre LIKE :q3 OR b.description LIKE :q4)';
        $like          = '%' . $filters['q'] . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
        $params[':q4'] = $like;
    }

    /* --- category tile expands into the genre values it covers --- */
    $category = $filters['category'] !== '' ? category_by_slug((string) $filters['category']) : null;

    if ($category !== null) {
        $slots = [];
        foreach ($category['genres'] as $index => $genre) {
            $key          = ':cg' . $index;
            $slots[]      = $key;
            $params[$key] = $genre;
        }
        $where[] = 'b.genre IN (' . implode(', ', $slots) . ')';
    }

    if ($filters['author'] !== '') {
        $where[]           = 'b.author = :author';
        $params[':author'] = $filters['author'];
    }

    if ($filters['min_price'] !== '') {
        $where[]               = 'b.price >= :min_price';
        $params[':min_price']  = (float) $filters['min_price'];
    }

    if ($filters['max_price'] !== '') {
        $where[]               = 'b.price <= :max_price';
        $params[':max_price']  = (float) $filters['max_price'];
    }

    if ($filters['in_stock'] === true) {
        $where[] = 'b.stock_quantity > 0';
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    /* --- ordering, from a whitelist --- */
    $orderSql = match ($filters['sort']) {
        'title'       => ' ORDER BY b.title ASC',
        'price_asc'   => ' ORDER BY b.price ASC, b.title ASC',
        'price_desc'  => ' ORDER BY b.price DESC, b.title ASC',
        'bestselling' => ' ORDER BY units_sold DESC, b.title ASC',
        default       => ' ORDER BY b.created_at DESC, b.id DESC',
    };

    /* The sales aggregate is only joined when it is actually needed. */
    $join = $filters['sort'] === 'bestselling'
        ? ' LEFT JOIN (SELECT book_id, SUM(quantity) AS units_sold FROM order_items GROUP BY book_id) s ON s.book_id = b.id'
        : '';

    /* Count first, using ONLY the WHERE parameters: a native prepared statement
       rejects bound values it does not reference. */
    $total = (int) db_value('SELECT COUNT(*) FROM books b' . $whereSql, $params);

    $perPage = (int) $filters['per_page'];
    $page    = (int) $filters['page'];
    $offset  = ($page - 1) * $perPage;

    /* LIMIT/OFFSET are cast to int — the only safe form for a native prepare. */
    $items = db_all(
        'SELECT b.*' . ($join === '' ? '' : ', COALESCE(s.units_sold, 0) AS units_sold')
        . ' FROM books b' . $join . $whereSql . $orderSql
        . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );

    return [
        'items'    => $items,
        'total'    => $total,
        'page'     => $page,
        'pages'    => max(1, (int) ceil($total / $perPage)),
        'per_page' => $perPage,
        'filters'  => $filters,
    ];
}

/**
 * Shape one book row for the JavaScript client. Mirrors the fields used by
 * GB.bookCard() in js/main.js.
 *
 * @param  array<string,mixed> $book
 * @return array<string,mixed>
 */
function catalogue_payload(array $book, array $wishlistIds = []): array
{
    $id = (int) $book['id'];

    return [
        'id'             => $id,
        'title'          => (string) $book['title'],
        'author'         => (string) $book['author'],
        'genre'          => (string) $book['genre'],
        'price'          => (float) $book['price'],
        'price_fmt'      => money((float) $book['price']),
        'stock_quantity' => (int) $book['stock_quantity'],
        'is_featured'    => (int) $book['is_featured'],
        'cover_image'    => cover_image($book),
        'cover_tone'     => cover_tone($book),
        'url'            => url('book-details.php?id=' . $id),
        'wishlisted'     => in_array($id, $wishlistIds, true),
        'excerpt'        => excerpt((string) ($book['description'] ?? ''), 110),
    ];
}

/** Authors with at least one title, newest first. */
function catalogue_authors(int $limit = 200): array
{
    $limit = max(1, min(500, $limit));

    return db_all(
        'SELECT author, COUNT(*) AS titles, MIN(price) AS min_price
           FROM books
          WHERE author <> ""
          GROUP BY author
          ORDER BY (COUNT(*) > 1) DESC, author ASC
          LIMIT ' . $limit
    );
}

/** Rebuild the current query string with overrides, for links. */
function catalogue_link(array $filters, array $overrides = []): string
{
    $query = array_merge([
        'q'         => $filters['q'],
        'category'  => $filters['category'],
        'author'    => $filters['author'],
        'min_price' => $filters['min_price'],
        'max_price' => $filters['max_price'],
        'in_stock'  => $filters['in_stock'] ? '1' : '',
        'sort'      => $filters['sort'] === 'newest' ? '' : $filters['sort'],
        'page'      => $filters['page'] > 1 ? $filters['page'] : '',
    ], $overrides);

    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);

    return url('books.php' . ($query === [] ? '' : '?' . http_build_query($query)));
}
