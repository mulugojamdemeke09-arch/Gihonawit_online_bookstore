<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/footer.php — trust strip, footer and the JavaScript bootstrap.
 * ===========================================================================
 *  Pages that already printed their own trust strip can set
 *  $skip_trust_strip = true before including this file.
 * ===========================================================================
 */

$skip_trust_strip = $skip_trust_strip ?? false;
?>

<?php if (!$skip_trust_strip): ?>
<div class="trust">
    <div class="wrap trust__grid">
        <div class="trust__item">
            <span class="trust__icon"><?= icon('truck') ?></span>
            <span>
                <strong><?= e(t('trust.delivery')) ?></strong>
                <span><?= e(t('trust.delivery_sub')) ?></span>
            </span>
        </div>
        <div class="trust__item">
            <span class="trust__icon"><?= icon('shield') ?></span>
            <span>
                <strong><?= e(t('trust.payment')) ?></strong>
                <span><?= e(t('trust.payment_sub')) ?></span>
            </span>
        </div>
        <div class="trust__item">
            <span class="trust__icon"><?= icon('books') ?></span>
            <span>
                <strong><?= e(t('trust.selection')) ?></strong>
                <span><?= e(t('trust.selection_sub')) ?></span>
            </span>
        </div>
        <div class="trust__item">
            <span class="trust__icon"><?= icon('heart') ?></span>
            <span>
                <strong><?= e(t('trust.local')) ?></strong>
                <span><?= e(t('trust.local_sub')) ?></span>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<footer class="footer">
    <div class="wrap footer__grid">
        <div>
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <?= brand_mark() ?>
                <span>
                    <span class="brand__name"><?= e(SITE_NAME) ?></span>
                    <span class="brand__sub"><?= e(SITE_SUFFIX) ?></span>
                    <span class="brand__tag"><?= e(SITE_TAGLINE) ?></span>
                </span>
            </a>
            <p style="margin-top:16px;max-width:34ch"><?= e(t('footer.about_body')) ?></p>
        </div>

        <div>
            <h4><?= e(t('footer.shop')) ?></h4>
            <ul>
                <li><a href="<?= e(url('books.php')) ?>"><?= e(t('catalogue.all')) ?></a></li>
                <li><a href="<?= e(url('books.php?sort=bestselling')) ?>"><?= e(t('section.best_title')) ?></a></li>
                <li><a href="<?= e(url('books.php?sort=newest')) ?>"><?= e(t('section.new')) ?></a></li>
                <li><a href="<?= e(url('authors.php')) ?>"><?= e(t('nav.authors')) ?></a></li>
            </ul>
        </div>

        <div>
            <h4><?= e(t('footer.help')) ?></h4>
            <ul>
                <li><a href="<?= e(url('cart.php')) ?>"><?= e(t('cart.title')) ?></a></li>
                <li><a href="<?= e(url('my-orders.php')) ?>"><?= e(t('order.my')) ?></a></li>
                <li><a href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact')) ?></a></li>
                <li><a href="<?= e(url('about.php')) ?>"><?= e(t('nav.about')) ?></a></li>
            </ul>
        </div>

        <div>
            <h4><?= e(t('nav.contact')) ?></h4>
            <ul>
                <li><?= e(SITE_ADDRESS) ?></li>
                <li><a href="tel:<?= e(str_replace(' ', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a></li>
                <li><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></li>
                <li><?= e(SITE_HOURS) ?></li>
            </ul>
        </div>
    </div>

    <div class="wrap footer__bottom">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> <?= e(SITE_SUFFIX) ?>. <?= e(t('footer.rights')) ?></span>
        <span><?= e(tf('footer.ship_note', money(FREE_SHIPPING_THRESHOLD))) ?></span>
    </div>
</footer>

<script>
window.GB_CONFIG = <?= json_encode($gb_config ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(url('js/main.js')) ?>" defer></script>
</body>
</html>
