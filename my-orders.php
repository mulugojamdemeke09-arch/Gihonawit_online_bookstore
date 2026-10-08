<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  my-orders.php — the customer's order history.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/orders.php';

require_login();

$account = current_user();
$orders  = orders_for_user(user_id());
$spent   = 0.0;
$open    = 0;

foreach ($orders as $row) {
    if ($row['status'] !== 'Cancelled') {
        $spent += (float) $row['total_amount'];
    }
    if ($row['status'] === 'Pending' || $row['status'] === 'Shipped') {
        $open++;
    }
}

$page_title = t('order.my') . ' — ' . SITE_NAME;
$page_desc  = 'Your order history at ' . SITE_NAME . '.';
$active_nav = '';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('order.my')) ?></span>
</nav>

<div class="section-head">
    <div>
        <h2><?= e(t('order.my')) ?></h2>
        <p class="muted small"><?= e((string) $account['username']) ?> · <?= e((string) $account['email']) ?></p>
    </div>
    <a class="btn btn--outline btn--sm" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
</div>

<div class="stat-strip">
    <div class="stat stat--forest">
        <span>Orders placed</span>
        <strong><?= count($orders) ?></strong>
        <small>Lifetime with this shop</small>
    </div>
    <div class="stat stat--gold">
        <span>Total spent</span>
        <strong><?= e(money($spent)) ?></strong>
        <small>Excludes cancelled orders</small>
    </div>
    <div class="stat">
        <span>Open orders</span>
        <strong><?= $open ?></strong>
        <small>Pending or shipped</small>
    </div>
</div>

<?php if ($orders === []): ?>
    <div class="empty">
        <h3><?= e(t('order.none')) ?></h3>
        <p>When you place an order it will appear here with its delivery status.</p>
        <a class="btn btn--primary" href="<?= e(url('books.php')) ?>"><?= e(t('cart.browse')) ?></a>
    </div>
<?php else: ?>
    <?php foreach ($orders as $order): ?>
        <?php
        $lines   = order_items((int) $order['id']);
        $totals  = order_totals($order);
        $flow    = ['Pending', 'Shipped'];
        $reached = array_search((string) $order['status'], $flow, true);
        ?>
        <article class="order-card">
            <header class="order-card__head">
                <div>
                    <a class="order-card__ref" href="<?= e(url('order-success.php?order=' . urlencode((string) $order['order_number']))) ?>">
                        <?= e((string) $order['order_number']) ?>
                    </a>
                    <div class="muted small">
                        <?= e(t('order.placed')) ?> <?= e(date('j M Y, H:i', strtotime((string) $order['created_at']))) ?>
                    </div>
                </div>

                <div class="order-card__meta">
                    <span><strong><?= (int) $order['item_count'] ?></strong> <?= e(t('order.items')) ?></span>
                    <span><?= e(t('cart.total')) ?> <strong><?= e($totals['total_fmt']) ?></strong></span>
                    <span><?= e(strtoupper((string) $order['payment_method']) === 'CARD' ? t('checkout.card') : t('checkout.cod')) ?></span>
                </div>

                <div><?= status_badge((string) $order['status']) ?></div>
            </header>

            <div class="order-card__body">
                <?php if ((string) $order['status'] === 'Cancelled'): ?>
                    <p class="muted small" style="padding-top:10px">This order was cancelled. Nothing was dispatched.</p>
                <?php else: ?>
                    <div class="steps" style="margin:12px 0 4px">
                        <?php foreach ($flow as $index => $step): ?>
                            <?php $done = $reached !== false && $index <= $reached; ?>
                            <span class="step <?= $done ? 'is-done' : '' ?>">
                                <b><?= $done ? '&#10003;' : $index + 1 ?></b> <?= e($step) ?>
                            </span>
                            <?php if ($index === 0): ?><span aria-hidden="true">&#8212;</span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($lines as $item): ?>
                    <div class="order-line">
                        <span class="order-line__media">
                            <?php if (!empty($item['cover_image'])): ?>
                                <img src="<?= e((string) $item['cover_image']) ?>" alt="" width="42" height="63" loading="lazy">
                            <?php endif; ?>
                        </span>
                        <span style="flex:1">
                            <strong style="display:block"><?= e((string) $item['title']) ?></strong>
                            <span class="muted small"><?= (int) $item['quantity'] ?> × <?= e((string) $item['price_fmt']) ?></span>
                        </span>
                        <span><?= e((string) $item['line_total_fmt']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="row" style="margin-top:14px">
                    <a class="btn btn--outline btn--sm"
                       href="<?= e(url('order-success.php?order=' . urlencode((string) $order['order_number']))) ?>">
                        <?= e(t('order.receipt')) ?>
                    </a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
