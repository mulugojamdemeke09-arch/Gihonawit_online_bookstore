<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  cart.php — the shopping cart.
 * ===========================================================================
 *  The cart lives in the PHP session; this page renders it server-side and
 *  js/main.js keeps the totals in step afterwards through api/cart.php.
 *
 *  The POST branch below is the no-JavaScript path — for example the plain
 *  "Add to cart" form on book-details.php, which posts straight here.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

/* -------------------------------------------------------------------------- */
/* Mutations (no-JS fallback)                                                 */
/* -------------------------------------------------------------------------- */
if (is_post()) {
    csrf_guard();

    $action = post('_action', post('action'));
    $bookId = (int) post('book_id');
    $result = ['ok' => true, 'message' => t('cart.updated')];

    switch ($action) {
        case 'add':
            $result = Cart::add($bookId, max(1, (int) post('quantity', '1')));
            break;

        case 'update':
            $result = Cart::setQuantity($bookId, (int) post('quantity'));
            break;

        case 'remove':
            Cart::remove($bookId);
            $result = ['ok' => true, 'message' => 'Removed from your cart.'];
            break;

        case 'clear':
            Cart::clear();
            $result = ['ok' => true, 'message' => 'Your cart is now empty.'];
            break;

        default:
            $result = ['ok' => false, 'message' => 'Unrecognised cart action.'];
    }

    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('cart.php');
}

$summary  = Cart::summary();
$items    = $summary['items'];
$problems = $summary['problems'];
$hasItems = $items !== [];

$progress = (int) round(
    (($summary['threshold'] - $summary['gap']) / $summary['threshold']) * 100
);
$progress = max(0, min(100, $progress));

$page_title = t('cart.title') . ' — ' . SITE_NAME;
$page_desc  = 'Review the books in your cart and continue to checkout.';
$active_nav = '';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('cart.title')) ?></span>
</nav>

<div class="section-head">
    <h2><?= e(t('cart.title')) ?></h2>
    <?php if ($hasItems): ?>
        <span class="muted small"><?= e(tf('cart.items', (int) $summary['count'])) ?></span>
    <?php endif; ?>
</div>

<div class="steps">
    <span class="step is-active"><b>1</b> <?= e(t('cart.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step"><b>2</b> <?= e(t('checkout.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step"><b>3</b> <?= e(t('order.confirmed')) ?></span>
</div>

<?php if ($problems !== []): ?>
    <div class="notice notice--error" role="alert">
        <span>
            <strong>Some items need attention before you can check out:</strong>
            <span style="display:block;margin-top:6px"><?= e(implode(' ', $problems)) ?></span>
        </span>
    </div>
<?php endif; ?>

<div data-cart-page>
    <?php if (!$hasItems): ?>
        <div class="empty">
            <h3><?= e(t('cart.empty')) ?></h3>
            <p><?= e(t('cart.empty_body')) ?></p>
            <a class="btn btn--primary" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- -------------------------------------------------------- -->
            <!-- Lines                                                     -->
            <!-- -------------------------------------------------------- -->
            <div class="panel panel--flush">
                <table class="cart-table">
                    <caption class="visually-hidden"><?= e(t('cart.title')) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('cart.line_title')) ?></th>
                            <th scope="col"><?= e(t('cart.line_price')) ?></th>
                            <th scope="col"><?= e(t('cart.line_qty')) ?></th>
                            <th scope="col"><?= e(t('cart.line_total')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td data-label="<?= e(t('cart.line_title')) ?>">
                                    <div class="cart-line">
                                        <span class="cart-line__media">
                                            <?php if ($item['cover_image'] !== null): ?>
                                                <img src="<?= e((string) $item['cover_image']) ?>" alt="" width="58" height="87" loading="lazy">
                                            <?php else: ?>
                                                <span class="cover cover--t<?= (int) $item['cover_tone'] ?>" style="width:58px">
                                                    <span class="cover__top"><span class="cover__title" style="font-size:.62rem"><?= e(excerpt((string) $item['title'], 24)) ?></span></span>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            <a class="cart-line__title" href="<?= e((string) $item['url']) ?>"><?= e((string) $item['title']) ?></a>
                                            <span class="cart-line__meta"><?= e((string) $item['author']) ?> · <?= e((string) $item['genre']) ?></span>
                                            <?php if (!$item['stock_ok']): ?>
                                                <span class="tag tag--low" style="margin-top:5px">
                                                    <?= e(tf('card.low_stock', (int) $item['stock_quantity'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span style="display:block;margin-top:6px">
                                                <button type="button" class="cart-line__remove" data-cart-remove="<?= (int) $item['book_id'] ?>">
                                                    <?= e(t('cart.remove')) ?>
                                                </button>
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td data-label="<?= e(t('cart.line_price')) ?>"><?= e((string) $item['price_fmt']) ?></td>

                                <td data-label="<?= e(t('cart.line_qty')) ?>">
                                    <span class="qty" data-qty data-ready="1">
                                        <button type="button" data-step="-1" aria-label="Decrease">&minus;</button>
                                        <label class="visually-hidden" for="qty-<?= (int) $item['book_id'] ?>"><?= e(t('book.quantity')) ?></label>
                                        <input type="number" id="qty-<?= (int) $item['book_id'] ?>"
                                               value="<?= (int) $item['quantity'] ?>" min="1"
                                               max="<?= max(1, min(MAX_QTY_PER_LINE, (int) $item['stock_quantity'])) ?>"
                                               data-cart-qty="<?= (int) $item['book_id'] ?>">
                                        <button type="button" data-step="1" aria-label="Increase">+</button>
                                    </span>
                                </td>

                                <td data-label="<?= e(t('cart.line_total')) ?>">
                                    <strong data-row-total="<?= (int) $item['book_id'] ?>"><?= e((string) $item['line_total_fmt']) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- -------------------------------------------------------- -->
            <!-- Summary                                                   -->
            <!-- -------------------------------------------------------- -->
            <aside class="panel summary">
                <div class="panel__head">
                    <h3><?= e(t('cart.summary')) ?></h3>
                </div>

                <div class="summary__row">
                    <span><?= e(t('cart.subtotal')) ?> (<span data-sum-count><?= (int) $summary['count'] ?></span>)</span>
                    <span data-sum-subtotal><?= e((string) $summary['subtotal_fmt']) ?></span>
                </div>

                <div class="summary__row">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span data-sum-shipping><?= e((string) $summary['shipping_fmt']) ?></span>
                </div>

                <div class="progress-track" role="presentation"><i style="width:<?= $progress ?>%"></i></div>
                <p class="small muted" data-sum-gap>
                    <?= $summary['gap'] > 0
                        ? e(tf('cart.free_gap', money((float) $summary['gap'])))
                        : e(t('cart.free_ok')) ?>
                </p>

                <div class="summary__row summary__row--total">
                    <span><?= e(t('cart.total')) ?></span>
                    <span data-sum-total><?= e((string) $summary['total_fmt']) ?></span>
                </div>

                <a class="btn btn--primary btn--block btn--lg" style="margin-top:16px"
                   href="<?= e(url('checkout.php')) ?>"
                   <?= $problems !== [] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <?= e(t('cart.checkout')) ?>
                </a>

                <div class="row row--between" style="margin-top:12px">
                    <a class="btn btn--outline btn--sm" href="<?= e(url('books.php')) ?>"><?= e(t('cart.continue')) ?></a>
                    <form method="post" action="<?= e(url('cart.php')) ?>" onsubmit="return confirm('Empty your cart?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="clear">
                        <button type="submit" class="btn btn--ghost btn--sm"><?= e(t('cart.clear')) ?></button>
                    </form>
                </div>

                <p class="summary__note"><?= e(tf('footer.ship_note', money(FREE_SHIPPING_THRESHOLD))) ?>.</p>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
