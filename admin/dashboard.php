<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  admin/dashboard.php — the administrator area.
 * ===========================================================================
 *  Access control: require_admin() runs before a single byte of HTML is
 *  produced, so an unauthenticated visitor or a customer never receives the
 *  dashboard, and never reaches the code below it. Both the page and every
 *  POST handler are gated, so the CRUD endpoints cannot be driven directly.
 *
 *  Tabs (all in this one file, as specified)
 *    overview   key figures, a 7-day revenue chart, stock alerts
 *    books      inventory CRUD — create, read, update, delete, feature toggle
 *    orders     incoming orders and their status (Pending / Shipped / Cancelled)
 *    customers  registered accounts
 * ===========================================================================
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/orders.php';
require_once GWB_ROOT . '/includes/catalogue.php';

require_admin();

$tab = get_param('tab', 'overview');

/* -------------------------------------------------------------------------- */
/* Write operations                                                           */
/* -------------------------------------------------------------------------- */
if (is_post()) {
    csrf_guard();

    $action  = post('_action');
    $bookId  = (int) post('book_id');
    $backTo  = post('return_tab', 'books');

    if ($action === 'save_book') {
        $data = [
            'title'           => post('title'),
            'author'          => post('author'),
            'genre'           => post('genre'),
            'price'           => post('price'),
            'stock_quantity'  => post('stock_quantity'),
            'description'     => post('description'),
            'cover_image_url' => post('cover_image_url'),
            'is_featured'     => isset($_POST['is_featured']) ? 1 : 0,
        ];

        $problems = [];

        if (mb_strlen($data['title']) < 2 || mb_strlen($data['title']) > 255) {
            $problems[] = 'Titles must be between 2 and 255 characters.';
        }
        if (mb_strlen($data['author']) < 2 || mb_strlen($data['author']) > 160) {
            $problems[] = 'Enter the author name (2–160 characters).';
        }
        if (trim($data['genre']) === '' || mb_strlen($data['genre']) > 80) {
            $problems[] = 'Choose an existing genre or type a new one (max 80 characters).';
        }
        if (!is_numeric(str_replace(',', '', $data['price'])) || (float) $data['price'] < 0) {
            $problems[] = 'Enter a price of 0.00 or more.';
        }
        if (filter_var($data['stock_quantity'], FILTER_VALIDATE_INT) === false || (int) $data['stock_quantity'] < 0) {
            $problems[] = 'Stock must be a whole number of 0 or more.';
        }
        if (mb_strlen((string) $data['description']) > 5000) {
            $problems[] = 'Keep the description under 5,000 characters.';
        }

        $cover = trim((string) $data['cover_image_url']);
        if ($cover !== '' && !preg_match('#^(https?://|//)#i', $cover) && !preg_match('/^[A-Za-z0-9._\-\/]+$/', $cover)) {
            $problems[] = 'Cover image must be an https:// URL or a filename inside assets/img/.';
        }

        if ($problems !== []) {
            foreach ($problems as $problem) {
                flash('error', $problem);
            }
            redirect('admin/dashboard.php?tab=books' . ($bookId > 0 ? '&edit=' . $bookId : '&edit=new'));
        }

        $payload = [
            ':title'       => $data['title'],
            ':author'      => $data['author'],
            ':genre'       => $data['genre'],
            ':price'       => (float) str_replace(',', '', $data['price']),
            ':stock'       => (int) $data['stock_quantity'],
            ':description' => (string) $data['description'],
            ':cover'       => $cover,
            ':featured'    => (int) $data['is_featured'],
        ];

        if ($bookId > 0) {
            db_exec(
                'UPDATE books
                    SET title = :title, author = :author, genre = :genre, price = :price,
                        stock_quantity = :stock, description = :description,
                        cover_image_url = :cover, is_featured = :featured
                  WHERE id = :id',
                $payload + [':id' => $bookId]
            );
            flash('success', '“' . $data['title'] . '” was updated.');
        } else {
            db_exec(
                'INSERT INTO books
                    (title, author, genre, price, stock_quantity, description, cover_image_url, is_featured, created_at)
                 VALUES
                    (:title, :author, :genre, :price, :stock, :description, :cover, :featured, NOW())',
                $payload
            );
            flash('success', '“' . $data['title'] . '” was added to the catalogue.');
        }

        redirect('admin/dashboard.php?tab=books');
    }

    if ($action === 'delete_book') {
        $book = db_one('SELECT title FROM books WHERE id = :id LIMIT 1', [':id' => $bookId]);

        if ($book === null) {
            flash('error', 'That book no longer exists.');
        } elseif ((int) db_value('SELECT COUNT(*) FROM order_items WHERE book_id = :id', [':id' => $bookId]) > 0) {
            /* Past invoices must keep their line — retire the title instead. */
            flash('error', '“' . $book['title'] . '” appears on existing orders and cannot be deleted. Set its stock to 0 to retire it.');
        } else {
            db_exec('DELETE FROM books WHERE id = :id', [':id' => $bookId]);
            flash('success', '“' . $book['title'] . '” was removed from the catalogue.');
        }

        redirect('admin/dashboard.php?tab=' . urlencode($backTo));
    }

    if ($action === 'toggle_featured') {
        $book = db_one('SELECT title, is_featured FROM books WHERE id = :id LIMIT 1', [':id' => $bookId]);

        if ($book !== null) {
            $now = (int) $book['is_featured'] === 1 ? 0 : 1;
            db_exec('UPDATE books SET is_featured = :flag WHERE id = :id', [':flag' => $now, ':id' => $bookId]);
            flash('success', '“' . $book['title'] . '” is ' . ($now === 1 ? 'now featured on the homepage.' : 'no longer featured.'));
        }

        redirect('admin/dashboard.php?tab=' . urlencode($backTo));
    }

    if ($action === 'update_order_status') {
        $orderId = (int) post('order_id');
        $status  = post('status');

        $order = order_find($orderId);

        if ($order === null) {
            flash('error', 'That order no longer exists.');
        } elseif (!in_array($status, order_statuses(), true)) {
            flash('error', 'That is not a valid order status.');
        } elseif ((string) $order['status'] === $status) {
            flash('info', 'Order ' . $order['order_number'] . ' is already ' . $status . '.');
        } else {
            db_exec(
                'UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id',
                [':status' => $status, ':id' => $orderId]
            );
            flash('success', 'Order ' . $order['order_number'] . ' moved from ' . $order['status'] . ' to ' . $status . '.');
        }

        redirect('admin/dashboard.php?tab=orders');
    }

    redirect('admin/dashboard.php');
}

/* -------------------------------------------------------------------------- */
/* Read operations                                                            */
/* -------------------------------------------------------------------------- */
$adminUser = current_user();

$stats      = order_stats();
$bookTotals = db_one(
    'SELECT COUNT(*) AS titles,
            COALESCE(SUM(stock_quantity), 0) AS units,
            COALESCE(SUM(price * stock_quantity), 0) AS value,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
       FROM books'
) ?? ['titles' => 0, 'units' => 0, 'value' => 0, 'out_of_stock' => 0];

$customerCount = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'customer'");

/* --- overview data --- */
$sales        = order_sales_by_day(7);
$peak         = max(1.0, max(array_map(static fn (array $day): float => $day['total'], $sales) ?: [1.0]));
$recentOrders = db_all(
    'SELECT o.*, u.username FROM orders o
       LEFT JOIN users u ON u.id = o.user_id
      ORDER BY o.created_at DESC, o.id DESC LIMIT 8'
);
$lowStock = db_all('SELECT * FROM books WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC, title ASC LIMIT 8');
$topBooks = db_all(
    "SELECT b.id, b.title, b.author, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.price_at_purchase) AS revenue
       FROM order_items oi
       INNER JOIN orders o ON o.id = oi.order_id AND o.status <> 'Cancelled'
       INNER JOIN books  b ON b.id = oi.book_id
      GROUP BY b.id, b.title, b.author
      ORDER BY revenue DESC
      LIMIT 6"
);

/* --- books tab --- */
$bookFilters = catalogue_filters([
    'q'        => get_param('q'),
    'sort'     => get_param('sort', 'newest'),
    'page'     => page_no(),
    'per_page' => 15,
]);
$bookList = $tab === 'books' && get_param('edit') === '' ? catalogue_query($bookFilters) : null;

$editing  = null;
$editKey  = get_param('edit');

if ($tab === 'books' && $editKey !== '') {
    if ($editKey !== 'new') {
        $editing = db_one('SELECT * FROM books WHERE id = :id LIMIT 1', [':id' => (int) $editKey]);
    }
    if ($editing === null) {
        $editing = [
            'id' => 0, 'title' => '', 'author' => '', 'genre' => '', 'price' => '',
            'stock_quantity' => 0, 'description' => '', 'cover_image_url' => '', 'is_featured' => 0,
        ];
    }
}

/* --- orders tab --- */
$orderStatusFilter = get_param('status');
$orderQuery        = get_param('q');

$orderWhere  = [];
$orderParams = [];

if (in_array($orderStatusFilter, order_statuses(), true)) {
    $orderWhere[]         = 'o.status = :status';
    $orderParams[':status'] = $orderStatusFilter;
}
if ($orderQuery !== '') {
    $orderWhere[]      = '(o.order_number LIKE :q1 OR o.shipping_name LIKE :q2 OR o.shipping_email LIKE :q3)';
    $like             = '%' . $orderQuery . '%';
    $orderParams[':q1'] = $like;
    $orderParams[':q2'] = $like;
    $orderParams[':q3'] = $like;
}

$orderRows = $tab === 'orders'
    ? db_all(
        'SELECT o.*, u.username,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
           FROM orders o
           LEFT JOIN users u ON u.id = o.user_id'
        . ($orderWhere === [] ? '' : ' WHERE ' . implode(' AND ', $orderWhere))
        . ' ORDER BY o.created_at DESC, o.id DESC LIMIT 60',
        $orderParams
    )
    : [];

/* --- customers tab --- */
$customers = $tab === 'customers'
    ? db_all(
        'SELECT u.id, u.username, u.email, u.role, u.created_at,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(CASE WHEN o.status <> \'Cancelled\' THEN o.total_amount END), 0) AS lifetime
           FROM users u
           LEFT JOIN orders o ON o.user_id = u.id
          GROUP BY u.id, u.username, u.email, u.role, u.created_at
          ORDER BY u.created_at DESC'
    )
    : [];

$genres = db_all('SELECT DISTINCT genre FROM books ORDER BY genre ASC');

$gb_config = [
    'baseUrl'   => BASE_URL,
    'csrf'      => csrf_token(),
    'currency'  => SITE_CURRENCY,
    'cartCount' => Cart::count(),
    'wishCount' => Wishlist::count(),
    'lang'      => current_lang(),
    'endpoints' => [],
    'strings'   => [],
];

/* Sidebar navigation. */
$navItems = [
    'overview'  => ['label' => 'Overview',  'icon' => 'cog'],
    'books'     => ['label' => 'Inventory', 'icon' => 'books'],
    'orders'    => ['label' => 'Orders',    'icon' => 'truck'],
    'customers' => ['label' => 'Customers', 'icon' => 'user'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin · <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="admin">

<a class="skip-link" href="#admin-content">Skip to content</a>

<div class="admin-shell">
    <!-- ---------------------------------------------------------------- -->
    <!-- Sidebar                                                            -->
    <!-- ---------------------------------------------------------------- -->
    <aside class="admin-side">
        <div class="admin-side__brand">
            <?= brand_mark() ?>
            <span>
                <span class="brand__name"><?= e(SITE_NAME) ?></span>
                <span class="brand__sub">Admin</span>
            </span>
        </div>

        <p class="admin-side__label">Manage</p>
        <nav aria-label="Admin sections">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= e(url('admin/dashboard.php?tab=' . $key)) ?>" class="<?= $tab === $key ? 'is-active' : '' ?>">
                    <?= icon((string) $item['icon']) ?>
                    <?= e((string) $item['label']) ?>
                    <?php if ($key === 'orders' && $stats['pending'] > 0): ?>
                        <span class="tag tag--gold" style="margin-left:auto"><?= (int) $stats['pending'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-side__foot">
            <a href="<?= e(url('index.php')) ?>">&larr; View storefront</a>
            <a href="<?= e(url('logout.php')) ?>"><?= e(t('auth.sign_out')) ?></a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-top">
            <div>
                <h1><?= e((string) $navItems[$tab]['label'] ?? 'Dashboard') ?></h1>
                <p class="muted small">Signed in as <?= e((string) $adminUser['username']) ?> · administrator</p>
            </div>
            <span class="spacer"></span>
            <a class="btn btn--outline btn--sm" href="<?= e(url('books.php')) ?>" target="_blank" rel="noopener">Open shop</a>
        </header>

        <div class="admin-body" id="admin-content">
            <?= render_flashes() ?>

<?php /* ======================== OVERVIEW TAB ======================== */ ?>
<?php if ($tab === 'overview'): ?>
    <section class="stat-strip" style="margin-bottom:0">
        <div class="stat stat--forest">
            <span>Revenue</span>
            <strong><?= e(money($stats['revenue'])) ?></strong>
            <small>All orders except cancelled</small>
        </div>
        <div class="stat">
            <span>Orders</span>
            <strong><?= (int) $stats['total'] ?></strong>
            <small><?= (int) $stats['today'] ?> placed today</small>
        </div>
        <div class="stat stat--gold">
            <span>Pending</span>
            <strong><?= (int) $stats['pending'] ?></strong>
            <small><?= (int) $stats['shipped'] ?> shipped · <?= (int) $stats['cancelled'] ?> cancelled</small>
        </div>
        <div class="stat">
            <span>Titles</span>
            <strong><?= (int) $bookTotals['titles'] ?></strong>
            <small><?= (int) $bookTotals['units'] ?> copies in stock</small>
        </div>
        <div class="stat">
            <span>Inventory value</span>
            <strong><?= e(money((float) $bookTotals['value'])) ?></strong>
            <small><?= (int) $bookTotals['out_of_stock'] ?> titles out of stock</small>
        </div>
        <div class="stat">
            <span>Customers</span>
            <strong><?= $customerCount ?></strong>
            <small>Registered accounts</small>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h3>Revenue · last 7 days</h3>
            <span class="muted small">Peak day <?= e(money($peak)) ?></span>
        </div>
        <div class="chart" role="img" aria-label="Revenue for the last seven days">
            <?php foreach ($sales as $day): ?>
                <?php $height = max(3, (int) round(((float) $day['total'] / $peak) * 100)); ?>
                <div class="chart__col" title="<?= e($day['label']) ?>: <?= e(money((float) $day['total'])) ?>">
                    <span class="chart__value"><?= (float) $day['total'] > 0 ? e(money((float) $day['total'])) : '—' ?></span>
                    <span class="chart__bar" style="height:<?= $height ?>%"></span>
                    <span class="chart__label"><?= e($day['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="split">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Placed</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $row): ?>
                        <tr>
                            <td><a href="<?= e(url('order-success.php?order=' . urlencode((string) $row['order_number']))) ?>"><?= e((string) $row['order_number']) ?></a></td>
                            <td>
                                <?= e((string) $row['shipping_name']) ?>
                                <span class="muted small" style="display:block"><?= e((string) $row['shipping_email']) ?></span>
                            </td>
                            <td><?= e(money((float) $row['total_amount'])) ?></td>
                            <td><?= status_badge((string) $row['status']) ?></td>
                            <td class="muted small nowrap"><?= e(date('j M, H:i', strtotime((string) $row['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentOrders === []): ?>
                        <tr><td colspan="5" class="center muted">No orders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Low stock</th><th>Left</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($lowStock as $row): ?>
                        <tr>
                            <td>
                                <?= e((string) $row['title']) ?>
                                <span class="muted small" style="display:block"><?= e((string) $row['author']) ?></span>
                            </td>
                            <td>
                                <?php if ((int) $row['stock_quantity'] === 0): ?>
                                    <span class="tag tag--out">Out</span>
                                <?php else: ?>
                                    <span class="tag tag--low"><?= (int) $row['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="table__actions">
                                <a class="btn btn--outline btn--sm" href="<?= e(url('admin/dashboard.php?tab=books&edit=' . (int) $row['id'])) ?>">Restock</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($lowStock === []): ?>
                        <tr><td colspan="3" class="center muted">Every title has healthy stock.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <section class="table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Best seller</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($topBooks as $index => $row): ?>
                    <tr>
                        <td class="muted"><?= $index + 1 ?></td>
                        <td>
                            <a href="<?= e(url('admin/dashboard.php?tab=books&edit=' . (int) $row['id'])) ?>"><?= e((string) $row['title']) ?></a>
                            <span class="muted small" style="display:block"><?= e((string) $row['author']) ?></span>
                        </td>
                        <td><?= (int) $row['units'] ?></td>
                        <td><strong><?= e(money((float) $row['revenue'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($topBooks === []): ?>
                    <tr><td colspan="4" class="center muted">No sales recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php /* ========================= BOOKS TAB ========================= */ ?>
<?php if ($tab === 'books'): ?>
    <?php if ($editing !== null): ?>
        <?php /* ---------- create / edit form ---------- */ ?>
        <section class="panel">
            <div class="panel__head">
                <h3><?= (int) $editing['id'] > 0 ? 'Edit: ' . e((string) $editing['title']) : 'Add a new book' ?></h3>
                <a class="btn btn--outline btn--sm" href="<?= e(url('admin/dashboard.php?tab=books')) ?>">Back to inventory</a>
            </div>

            <form method="post" action="<?= e(url('admin/dashboard.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="save_book">
                <input type="hidden" name="book_id" value="<?= (int) $editing['id'] ?>">

                <div class="field-row">
                    <div class="field">
                        <label for="title">Title *</label>
                        <input class="input" type="text" id="title" name="title" required maxlength="255" value="<?= e((string) $editing['title']) ?>">
                    </div>
                    <div class="field">
                        <label for="author">Author *</label>
                        <input class="input" type="text" id="author" name="author" required maxlength="160" value="<?= e((string) $editing['author']) ?>">
                    </div>
                </div>

                <div class="field-row field-row--3">
                    <div class="field">
                        <label for="genre">Genre *</label>
                        <input class="input" type="text" id="genre" name="genre" required maxlength="80" list="genre-options"
                               value="<?= e((string) $editing['genre']) ?>">
                        <datalist id="genre-options">
                            <?php foreach ($genres as $row): ?>
                                <option value="<?= e((string) $row['genre']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="price">Price (<?= e(SITE_CURRENCY) ?>) *</label>
                        <input class="input" type="number" id="price" name="price" required min="0" step="0.01"
                               value="<?= e((string) $editing['price']) ?>">
                    </div>
                    <div class="field">
                        <label for="stock_quantity">Stock *</label>
                        <input class="input" type="number" id="stock_quantity" name="stock_quantity" required min="0" step="1"
                               value="<?= (int) $editing['stock_quantity'] ?>">
                        <span class="field__hint">0 retires a title without deleting it.</span>
                    </div>
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description" maxlength="5000"><?= e((string) $editing['description']) ?></textarea>
                </div>

                <div class="field">
                    <label for="cover_image_url">Cover image URL</label>
                    <input class="input" type="text" id="cover_image_url" name="cover_image_url" maxlength="500"
                           value="<?= e((string) $editing['cover_image_url']) ?>"
                           placeholder="https://… — leave blank to use the generated cover template">
                    <span class="field__hint">Blank is fine: the storefront renders a styled cover from the title, author and genre.</span>
                </div>

                <label class="checkbox" style="margin-bottom:18px">
                    <input type="checkbox" name="is_featured" value="1" <?= (int) $editing['is_featured'] === 1 ? 'checked' : '' ?>>
                    <span>Feature this title on the homepage</span>
                </label>

                <div class="row">
                    <button type="submit" class="btn btn--primary">
                        <?= (int) $editing['id'] > 0 ? 'Save changes' : 'Add book' ?>
                    </button>
                    <a class="btn btn--outline" href="<?= e(url('admin/dashboard.php?tab=books')) ?>">Cancel</a>
                </div>
            </form>
        </section>
    <?php else: ?>
        <?php /* ---------- inventory table ---------- */ ?>
        <section class="panel">
            <div class="panel__head">
                <h3>Inventory (<?= (int) ($bookList['total'] ?? 0) ?> titles)</h3>
                <a class="btn btn--primary btn--sm" href="<?= e(url('admin/dashboard.php?tab=books&edit=new')) ?>">+ Add a book</a>
            </div>

            <form class="row" method="get" action="<?= e(url('admin/dashboard.php')) ?>" style="gap:10px">
                <input type="hidden" name="tab" value="books">
                <input class="input" type="search" name="q" value="<?= e((string) $bookFilters['q']) ?>"
                       placeholder="Search title, author or genre…" style="max-width:320px">
                <button type="submit" class="btn btn--outline btn--sm">Search</button>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/dashboard.php?tab=books')) ?>">Reset</a>
            </form>
        </section>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Book</th><th>Genre</th><th>Price</th><th>Stock</th><th>Featured</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($bookList['items'] ?? []) as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $row['title']) ?></strong>
                                <span class="muted small" style="display:block"><?= e((string) $row['author']) ?> · #<?= (int) $row['id'] ?></span>
                            </td>
                            <td><span class="tag"><?= e((string) $row['genre']) ?></span></td>
                            <td><?= e(money((float) $row['price'])) ?></td>
                            <td>
                                <?php if ((int) $row['stock_quantity'] <= 0): ?>
                                    <span class="tag tag--out">Out of stock</span>
                                <?php elseif ((int) $row['stock_quantity'] <= 5): ?>
                                    <span class="tag tag--low"><?= (int) $row['stock_quantity'] ?> left</span>
                                <?php else: ?>
                                    <span class="tag tag--forest"><?= (int) $row['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $row['is_featured'] === 1 ? '<span class="tag tag--gold">Featured</span>' : '<span class="muted small">—</span>' ?></td>
                            <td>
                                <div class="table__actions">
                                    <a class="btn btn--outline btn--sm" href="<?= e(url('book-details.php?id=' . (int) $row['id'])) ?>" target="_blank" rel="noopener">View</a>
                                    <a class="btn btn--outline btn--sm" href="<?= e(url('admin/dashboard.php?tab=books&edit=' . (int) $row['id'])) ?>">Edit</a>

                                    <form method="post" action="<?= e(url('admin/dashboard.php')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="toggle_featured">
                                        <input type="hidden" name="book_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm">
                                            <?= (int) $row['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(url('admin/dashboard.php')) ?>" style="display:inline"
                                          onsubmit="return confirm('Delete &quot;<?= e((string) $row['title']) ?>&quot; permanently?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete_book">
                                        <input type="hidden" name="book_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (($bookList['items'] ?? []) === []): ?>
                        <tr><td colspan="6" class="center muted" style="padding:30px">No titles match that search.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (($bookList['pages'] ?? 1) > 1): ?>
            <nav class="pager">
                <?php for ($page = 1; $page <= (int) $bookList['pages']; $page++): ?>
                    <?php if ($page === (int) $bookList['page']): ?>
                        <span class="is-current"><?= $page ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('admin/dashboard.php?' . http_build_query(array_filter([
                            'tab' => 'books',
                            'q'   => (string) $bookFilters['q'],
                            'page' => $page,
                        ])))) ?>"><?= $page ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php /* ========================= ORDERS TAB ======================== */ ?>
<?php if ($tab === 'orders'): ?>
    <section class="panel">
        <div class="panel__head">
            <h3>Order queue</h3>
            <span class="muted small"><?= (int) $stats['pending'] ?> pending · <?= (int) $stats['shipped'] ?> shipped · <?= (int) $stats['cancelled'] ?> cancelled</span>
        </div>

        <form class="row" method="get" action="<?= e(url('admin/dashboard.php')) ?>" style="gap:10px">
            <input type="hidden" name="tab" value="orders">
            <input class="input" type="search" name="q" value="<?= e($orderQuery) ?>"
                   placeholder="Order number, name or email…" style="max-width:320px">
            <select class="select" name="status" style="max-width:190px">
                <option value="">All statuses</option>
                <?php foreach (order_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $orderStatusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn--outline btn--sm">Filter</button>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/dashboard.php?tab=orders')) ?>">Reset</a>
        </form>
    </section>

    <?php if ($orderRows === []): ?>
        <div class="empty"><h3>No orders match those filters</h3><p>Try clearing the search box.</p></div>
    <?php endif; ?>

    <?php foreach ($orderRows as $row): ?>
        <?php $lines = order_items((int) $row['id']); ?>
        <article class="order-card">
            <header class="order-card__head">
                <div>
                    <a class="order-card__ref" href="<?= e(url('order-success.php?order=' . urlencode((string) $row['order_number']))) ?>">
                        <?= e((string) $row['order_number']) ?>
                    </a>
                    <div class="muted small">
                        <?= e(date('j M Y, H:i', strtotime((string) $row['created_at']))) ?> ·
                        user #<?= (int) $row['user_id'] ?> (<?= e((string) ($row['username'] ?? 'deleted')) ?>)
                    </div>
                </div>

                <div class="order-card__meta">
                    <span><strong><?= (int) $row['item_count'] ?></strong> items</span>
                    <span>Total <strong><?= e(money((float) $row['total_amount'])) ?></strong></span>
                    <span><?= e(strtoupper((string) $row['payment_method']) === 'CARD' ? t('checkout.card') : t('checkout.cod')) ?></span>
                </div>

                <div><?= status_badge((string) $row['status']) ?></div>
            </header>

            <div class="order-card__body">
                <details>
                    <summary class="small" style="cursor:pointer;color:var(--forest);font-weight:600">
                        Items, delivery details and status control
                    </summary>

                    <div style="margin-top:14px">
                        <?php foreach ($lines as $line): ?>
                            <div class="order-line">
                                <span class="order-line__media">
                                    <?php if (!empty($line['cover_image'])): ?>
                                        <img src="<?= e((string) $line['cover_image']) ?>" alt="" width="42" height="63" loading="lazy">
                                    <?php endif; ?>
                                </span>
                                <span style="flex:1">
                                    <strong style="display:block"><?= e((string) $line['title']) ?></strong>
                                    <span class="muted small"><?= (int) $line['quantity'] ?> × <?= e((string) $line['price_fmt']) ?></span>
                                </span>
                                <span><?= e((string) $line['line_total_fmt']) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="field-row" style="margin-top:16px">
                            <div>
                                <p class="label">Deliver to</p>
                                <p class="small">
                                    <?= e((string) $row['shipping_name']) ?><br>
                                    <?= e((string) $row['shipping_address']) ?><br>
                                    <?= e((string) $row['shipping_city']) ?><br>
                                    <?= e((string) $row['shipping_email']) ?><br>
                                    <?= e((string) ($row['shipping_phone'] ?? '')) ?>
                                </p>
                            </div>
                            <div>
                                <p class="label">Payment</p>
                                <p class="small">
                                    Method: <?= e(strtoupper((string) $row['payment_method']) === 'CARD' ? t('checkout.card') : t('checkout.cod')) ?><br>
                                    Subtotal: <?= e(money((float) $row['subtotal'])) ?><br>
                                    Delivery: <?= (float) $row['shipping_amount'] > 0 ? e(money((float) $row['shipping_amount'])) : 'Free' ?><br>
                                    Total: <strong><?= e(money((float) $row['total_amount'])) ?></strong>
                                </p>
                                <?php if (!empty($row['notes'])): ?>
                                    <p class="small muted" style="margin-top:8px"><em><?= nl2br(e((string) $row['notes'])) ?></em></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="post" action="<?= e(url('admin/dashboard.php')) ?>" class="row" style="gap:10px;margin-top:16px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="update_order_status">
                            <input type="hidden" name="order_id" value="<?= (int) $row['id'] ?>">
                            <label class="visually-hidden" for="status-<?= (int) $row['id'] ?>">Order status</label>
                            <select class="select" name="status" id="status-<?= (int) $row['id'] ?>" style="max-width:200px">
                                <?php foreach (order_statuses() as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn--primary btn--sm">Update status</button>
                        </form>
                    </div>
                </details>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php /* ======================= CUSTOMERS TAB ======================= */ ?>
<?php if ($tab === 'customers'): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Role</th><th>Orders</th><th>Lifetime value</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach ($customers as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $row['username']) ?></strong>
                            <span class="muted small" style="display:block"><?= e((string) $row['email']) ?></span>
                        </td>
                        <td>
                            <span class="tag <?= $row['role'] === 'admin' ? 'tag--gold' : 'tag--forest' ?>">
                                <?= e(ucfirst((string) $row['role'])) ?>
                            </span>
                        </td>
                        <td><?= (int) $row['order_count'] ?></td>
                        <td><strong><?= e(money((float) $row['lifetime'])) ?></strong></td>
                        <td class="muted small nowrap"><?= e(date('j M Y', strtotime((string) $row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($customers === []): ?>
                    <tr><td colspan="5" class="center muted">No accounts yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

        </div><!-- /.admin-body -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-shell -->

<div class="toasts" role="status" aria-live="polite"></div>

<script>
window.GB_CONFIG = <?= json_encode($gb_config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(url('js/main.js')) ?>" defer></script>
</body>
</html>
