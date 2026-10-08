<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  contact.php — store contact details and an enquiry form.
 * ===========================================================================
 *  The form is validated server-side. Messages are written to the PHP error
 *  log so nothing is lost during development; wire it to mail(), a queue or a
 *  `messages` table before going live (see the note in README.md).
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if (is_post()) {
    csrf_guard();

    foreach (array_keys($values) as $field) {
        $values[$field] = post($field, $values[$field]);
    }

    if (mb_strlen($values['name']) < 2) {
        $errors['name'] = 'Please tell us your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address so we can reply.';
    }
    if (mb_strlen($values['subject']) < 3) {
        $errors['subject'] = 'Give your message a short subject.';
    }
    if (mb_strlen($values['message']) < 10) {
        $errors['message'] = 'Please write at least a sentence or two.';
    }

    if ($errors === []) {
        error_log(sprintf(
            '[contact] %s <%s> — %s: %s',
            $values['name'],
            $values['email'],
            $values['subject'],
            $values['message']
        ));

        $values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        flash('success', 'Thank you — your message has reached us. We reply within one working day.');
    }
}

$page_title = t('nav.contact') . ' — ' . SITE_NAME;
$page_desc  = 'Contact Gihonawit Online Bookstore — ' . SITE_PHONE . ', ' . SITE_EMAIL . '.';
$active_nav = 'contact';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('nav.contact')) ?></span>
</nav>

<div class="section-head">
    <h2><?= e(t('nav.contact')) ?></h2>
</div>

<div class="split">
    <section class="panel">
        <div class="panel__head">
            <h3>Send us a message</h3>
            <span class="muted small">All fields required</span>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('contact.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field-row">
                <div class="field">
                    <label for="name">Your name</label>
                    <input class="input" type="text" id="name" name="name" required minlength="2" maxlength="120"
                           value="<?= e($values['name']) ?>">
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" required maxlength="190"
                           value="<?= e($values['email']) ?>">
                </div>
            </div>

            <div class="field">
                <label for="subject">Subject</label>
                <input class="input" type="text" id="subject" name="subject" required maxlength="160"
                       value="<?= e($values['subject']) ?>" placeholder="Order enquiry, wholesale, book request…">
            </div>

            <div class="field">
                <label for="message">Message</label>
                <textarea class="textarea" id="message" name="message" required minlength="10"
                          maxlength="2000"><?= e($values['message']) ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--lg">Send message</button>
        </form>
    </section>

    <aside class="stack">
        <div class="panel">
            <h3>Visit the shop</h3>
            <p class="small" style="margin-top:12px">
                <?= e(SITE_ADDRESS) ?>
            </p>
            <p class="small" style="margin-top:10px"><?= e(SITE_HOURS) ?></p>
        </div>

        <div class="panel">
            <h3>Customer care</h3>
            <p class="small" style="margin-top:12px">
                <a href="tel:<?= e(str_replace(' ', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a><br>
                <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>
            </p>
            <p class="muted small" style="margin-top:12px">
                Order questions? Quote your order number (for example <code>GWB-260101-4821</code>) and we can
                look it up straight away.
            </p>
        </div>

        <div class="panel">
            <h3>Delivery &amp; returns</h3>
            <ul class="small" style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
                <li>Free delivery on orders over <?= e(money(FREE_SHIPPING_THRESHOLD)) ?></li>
                <li>Flat <?= e(money(FLAT_SHIPPING_RATE)) ?> below that</li>
                <li>Dispatched within one working day</li>
                <li>30-day returns on every title</li>
            </ul>
        </div>
    </aside>
</div>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
