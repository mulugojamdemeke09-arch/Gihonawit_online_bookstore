<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  order-success.php — the invoice / receipt.
 * ===========================================================================
 *  Reachable by the customer who placed the order, or by an administrator.
 *  A customer can never read somebody else's invoice by editing the URL.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once GWB_ROOT . '/includes/orders.php';

require_login();

$number = get_param('order');
$order  = $number !== '' ? order_find_by_number($number) : null;

$isOwner = $order !== null && (int) $order['user_id'] === user_id();

if ($order === null || (!$isOwner && !is_admin())) {
    http_response_code(404);
    $page_title = t('common.not_found');

    require GWB_ROOT . '/includes/header.php';
    ?>
    <div class="empty">
        <h3><?= e(t('common.not_found')) ?></h3>
        <p>Check the link in your confirmation email, or open your order history.</p>
        <a class="btn btn--primary" href="<?= e(url('my-orders.php')) ?>"><?= e(t('order.my')) ?></a>
    </div>
    <?php
    require GWB_ROOT . '/includes/footer.php';
    exit;
}

$items  = $order['items'] ?? [];
$totals = order_totals($order);

$page_title = t('order.confirmed') . ' — ' . SITE_NAME;
$active_nav = '';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <a href="<?= e(url('my-orders.php')) ?>"><?= e(t('order.my')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e((string) $order['order_number']) ?></span>
</nav>

<div class="section-head">
    <div>
        <p class="eyebrow">Order received</p>
        <h2><?= e(t('order.confirmed')) ?></h2>
        <p class="muted small">
            <?= e(t('order.number')) ?> <strong><?= e((string) $order['order_number']) ?></strong> ·
            <?= e(t('order.placed')) ?> <?= e(date('j F Y, H:i', strtotime((string) $order['created_at']))) ?>
        </p>
    </div>
    <?= status_badge((string) $order['status']) ?>
</div>

<div class="steps">
    <span class="step is-done"><b>&#10003;</b> <?= e(t('cart.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step is-done"><b>&#10003;</b> <?= e(t('checkout.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step is-active"><b>3</b> <?= e(t('order.confirmed')) ?></span>
</div>

<div class="cart-layout">
    <div class="stack">
        <section class="panel panel--flush">
            <div class="order-card__head">
                <span class="order-card__ref"><?= e((string) $order['order_number']) ?></span>
                <span class="order-card__meta">
                    <span><strong><?= count($items) ?></strong> <?= e(t('order.items')) ?></span>
                    <span><?= e(strtoupper((string) $order['payment_method']) === 'CARD' ? t('checkout.card') : t('checkout.cod')) ?></span>
                    <span><?= e((string) $order['shipping_city']) ?></span>
                </span>
            </div>

            <div class="order-card__body">
                <?php foreach ($items as $item): ?>
                    <div class="order-line">
                        <span class="order-line__media">
                            <?php if (!empty($item['cover_image'])): ?>
                                <img src="<?= e((string) $item['cover_image']) ?>" alt="" width="42" height="63" loading="lazy">
                            <?php endif; ?>
                        </span>
                        <span style="flex:1">
                            <?php if (!empty($item['book_id'])): ?>
                                <a href="<?= e(url('book-details.php?id=' . (int) $item['book_id'])) ?>">
                                    <strong><?= e((string) $item['title']) ?></strong>
                                </a>
                            <?php else: ?>
                                <strong><?= e((string) $item['title']) ?></strong>
                            <?php endif; ?>
                            <span class="muted small" style="display:block">
                                <?= (int) $item['quantity'] ?> × <?= e((string) $item['price_fmt']) ?>
                            </span>
                        </span>
                        <span><strong><?= e((string) $item['line_total_fmt']) ?></strong></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head"><h3><?= e(t('order.deliver_to')) ?></h3></div>

            <div class="field-row">
                <div>
                    <p class="label"><?= e(t('checkout.address')) ?></p>
                    <p class="small">
                        <?= e((string) $order['shipping_name']) ?><br>
                        <?= e((string) $order['shipping_address']) ?><br>
                        <?= e((string) $order['shipping_city']) ?><br>
                        <?= e((string) $order['shipping_email']) ?><br>
                        <?= e((string) ($order['shipping_phone'] ?? '')) ?>
                    </p>
                </div>
                <div>
                    <p class="label"><?= e(t('order.status')) ?></p>
                    <p class="small">
                        <?= status_badge((string) $order['status']) ?><br>
                        <span class="muted"><?= e(t('order.placed')) ?> <?= e(date('j M Y', strtotime((string) $order['created_at']))) ?></span>
                    </p>
                    <?php if (!empty($order['notes'])): ?>
                        <p class="label" style="margin-top:12px"><?= e(t('checkout.notes')) ?></p>
                        <p class="small"><?= nl2br(e((string) $order['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <aside class="panel summary">
        <div class="panel__head"><h3><?= e(t('cart.summary')) ?></h3></div>

        <div class="summary__row"><span><?= e(t('cart.subtotal')) ?></span><span><?= e($totals['subtotal_fmt']) ?></span></div>
        <div class="summary__row"><span><?= e(t('cart.shipping')) ?></span><span><?= e($totals['shipping_fmt']) ?></span></div>
        <div class="summary__row summary__row--total"><span><?= e(t('cart.total')) ?></span><span><?= e($totals['total_fmt']) ?></span></div>

        <div class="stack" style="margin-top:20px">
            <a class="btn btn--outline btn--block" href="<?= e(url('my-orders.php')) ?>"><?= e(t('order.my')) ?></a>
            <a class="btn btn--primary btn--block" href="<?= e(url('books.php')) ?>"><?= e(t('order.keep_shopping')) ?></a>
        </div>

        <p class="summary__note">
            Need to change something? Orders can be amended while the status still reads
            <strong>Pending</strong> — quote <?= e((string) $order['order_number']) ?>.
        </p>
    </aside>
</div>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
