<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  checkout.php — secure invoice builder.
 * ===========================================================================
 *  Requires a signed-in customer. On submit the whole order is written inside
 *  ONE database transaction:
 *
 *    1. SELECT … FOR UPDATE locks the cart rows
 *    2. price and stock are re-read from MySQL (the browser is never trusted)
 *    3. any shortage aborts the entire order — nothing is half-written
 *    4. orders + order_items are inserted, with price_at_purchase freezing
 *       the price actually paid
 *    5. stock is decremented behind a "stock_quantity >= qty" guard, so two
 *       simultaneous checkouts can never oversell a title
 *
 *  Payment is simulated: card numbers are validated with the Luhn checksum
 *  and are never stored. This code must not be pointed at a real gateway.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$summary = Cart::summary();
$items   = $summary['items'];

if ($items === []) {
    flash('info', 'Your cart is empty — add a book before checking out.');
    redirect('cart.php');
}

$user   = current_user();
$errors = [];

$values = [
    'shipping_name'    => (string) ($user['username'] ?? ''),
    'shipping_email'   => (string) ($user['email'] ?? ''),
    'shipping_phone'   => '',
    'shipping_address' => '',
    'shipping_city'    => '',
    'notes'            => '',
    'payment_method'   => 'card',
];

/**
 * Place the order. Returns the new order number on success.
 *
 * @param  array<int,int>      $lines    book id => quantity (straight from the session)
 * @param  array<string,mixed> $shipping validated delivery details
 * @return array{ok:bool,message:string,order_number?:string,order_id?:int}
 */
function place_order(int $userId, array $lines, array $shipping, string $paymentMethod): array
{
    $lines = array_filter(array_map('intval', $lines), static fn (int $qty): bool => $qty > 0);

    if ($lines === []) {
        return ['ok' => false, 'message' => 'Your cart is empty.'];
    }

    $ids          = array_keys($lines);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    db()->beginTransaction();

    try {
        /* 1 · lock the rows */
        $statement = db()->prepare(
            "SELECT id, title, price, stock_quantity FROM books WHERE id IN ($placeholders) FOR UPDATE"
        );
        $statement->execute($ids);

        $locked = [];
        foreach ($statement->fetchAll() as $row) {
            $locked[(int) $row['id']] = $row;
        }

        /* 2 · re-price and validate against live stock */
        $problems = [];
        $subtotal = 0.0;
        $prepared = [];

        foreach ($lines as $bookId => $quantity) {
            if (!isset($locked[$bookId])) {
                $problems[] = 'A title in your cart is no longer available.';
                continue;
            }

            $book  = $locked[$bookId];
            $stock = (int) $book['stock_quantity'];

            if ($stock < $quantity) {
                $problems[] = $stock <= 0
                    ? '“' . $book['title'] . '” is out of stock.'
                    : 'Only ' . $stock . ' copies of “' . $book['title'] . '” remain.';
                continue;
            }

            $price      = (float) $book['price'];
            $subtotal  += round($price * $quantity, 2);
            $prepared[] = [
                'book_id'  => (int) $book['id'],
                'quantity' => $quantity,
                'price'    => $price,
            ];
        }

        if ($problems !== []) {
            db()->rollBack();

            return ['ok' => false, 'message' => implode(' ', array_unique($problems))];
        }

        $subtotal = round($subtotal, 2);
        $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.00 : FLAT_SHIPPING_RATE;
        $total    = round($subtotal + $shipping, 2);

        /* 3 · order number, checked for collisions */
        $orderNumber = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf('GWB-%s-%04d', gmdate('ymd'), random_int(1000, 9999));

            if ((int) db_value('SELECT COUNT(*) FROM orders WHERE order_number = :n', [':n' => $candidate]) === 0) {
                $orderNumber = $candidate;
                break;
            }
        }

        if ($orderNumber === '') {
            throw new RuntimeException('Could not allocate an order number.');
        }

        /* 4 · order header */
        db_exec(
            'INSERT INTO orders
                (order_number, user_id, subtotal, shipping_amount, total_amount, status,
                 payment_method, shipping_name, shipping_email, shipping_phone,
                 shipping_address, shipping_city, notes, created_at, updated_at)
             VALUES
                (:number, :user_id, :subtotal, :shipping, :total, :status,
                 :method, :name, :email, :phone,
                 :address, :city, :notes, NOW(), NOW())',
            [
                ':number'   => $orderNumber,
                ':user_id'  => $userId,
                ':subtotal' => $subtotal,
                ':shipping' => $shipping,
                ':total'    => $total,
                ':status'   => 'Pending',
                ':method'   => $paymentMethod === 'card' ? 'card' : 'cod',
                ':name'     => (string) $shipping['shipping_name'],
                ':email'    => (string) $shipping['shipping_email'],
                ':phone'    => (string) $shipping['shipping_phone'],
                ':address'  => (string) $shipping['shipping_address'],
                ':city'     => (string) $shipping['shipping_city'],
                ':notes'    => (string) $shipping['notes'],
            ]
        );

        $orderId = db_last_id();

        /* 5 · lines + stock */
        $insertLine = db()->prepare(
            'INSERT INTO order_items (order_id, book_id, quantity, price_at_purchase)
             VALUES (:order_id, :book_id, :quantity, :price)'
        );
        $decrement = db()->prepare(
            'UPDATE books SET stock_quantity = stock_quantity - :qty
              WHERE id = :id AND stock_quantity >= :guard'
        );

        foreach ($prepared as $line) {
            $insertLine->execute([
                ':order_id' => $orderId,
                ':book_id'  => $line['book_id'],
                ':quantity' => $line['quantity'],
                ':price'    => $line['price'],
            ]);

            $decrement->execute([
                ':qty'   => $line['quantity'],
                ':id'    => $line['book_id'],
                ':guard' => $line['quantity'],
            ]);

            if ($decrement->rowCount() < 1) {
                /* Somebody took the last copy between our lock and this update. */
                throw new RuntimeException('Insufficient stock while reserving “' . $line['book_id'] . '”.');
            }
        }

        db()->commit();

        return [
            'ok'           => true,
            'message'      => 'Order placed.',
            'order_number' => $orderNumber,
            'order_id'     => $orderId,
        ];
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log('[checkout] ' . $error->getMessage());

        return ['ok' => false, 'message' => 'We could not complete your order: ' . $error->getMessage()];
    }
}

/* -------------------------------------------------------------------------- */
/* Submission                                                                 */
/* -------------------------------------------------------------------------- */
if (is_post()) {
    csrf_guard();

    foreach (array_keys($values) as $field) {
        $values[$field] = post($field, $values[$field]);
    }

    /* --- delivery details --- */
    if (mb_strlen($values['shipping_name']) < 2) {
        $errors['shipping_name'] = 'Enter the full name for the delivery.';
    }
    if (!filter_var($values['shipping_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['shipping_email'] = 'Enter a valid email address for the receipt.';
    }
    if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $values['shipping_phone'])) {
        $errors['shipping_phone'] = 'Enter a reachable phone number (7–20 digits).';
    }
    if (mb_strlen($values['shipping_address']) < 5) {
        $errors['shipping_address'] = 'Enter the street address, building and apartment number.';
    }
    if (mb_strlen($values['shipping_city']) < 2) {
        $errors['shipping_city'] = 'Enter the city or town.';
    }
    if (mb_strlen($values['notes']) > 500) {
        $errors['notes'] = 'Order notes must be 500 characters or fewer.';
    }

    /* --- simulated card payment --- */
    $method = $values['payment_method'] === 'card' ? 'card' : 'cod';

    if ($method === 'card') {
        $number = preg_replace('/\D/', '', post('card_number')) ?? '';
        $expiry = post('card_expiry');
        $cvv    = preg_replace('/\D/', '', post('card_cvv')) ?? '';

        if (strlen($number) < 12 || strlen($number) > 19 || !luhn_valid($number)) {
            $errors['card_number'] = 'That card number failed the checksum test. Use 4242 4242 4242 4242.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $matches)) {
            $errors['card_expiry'] = 'Use the MM/YY format, for example 04/29.';
        } elseif (strtotime('20' . $matches[2] . '-' . $matches[1] . '-01 23:59:59') < time()) {
            $errors['card_expiry'] = 'That expiry date is in the past.';
        }
        if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
            $errors['card_cvv'] = 'The security code is the 3 or 4 digits on the back of the card.';
        }
        if (!isset($errors['card_number']) && substr($number, -4) === '0000') {
            $errors['card_number'] = 'Card declined by the issuing bank (simulated).';
        }
    }

    /* --- last stock gate before writing anything --- */
    if ($errors === []) {
        $stockProblems = Cart::validateStock();

        if ($stockProblems !== []) {
            $errors['cart'] = implode(' ', $stockProblems);
        }
    }

    if ($errors === []) {
        $placed = place_order((int) $user['id'], Cart::raw(), $values, $method);

        if ($placed['ok']) {
            Cart::clear();
            flash('success', 'Order ' . $placed['order_number'] . ' confirmed. Thank you!');
            redirect('order-success.php?order=' . urlencode((string) $placed['order_number']));
        }

        $errors['cart'] = $placed['message'];
    }
}

$page_title = t('checkout.title') . ' — ' . SITE_NAME;
$page_desc  = 'Complete your order at ' . SITE_NAME . '.';
$active_nav = '';

require GWB_ROOT . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>"><?= e(t('nav.home')) ?></a>
    <span aria-hidden="true">/</span>
    <a href="<?= e(url('cart.php')) ?>"><?= e(t('cart.title')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= e(t('checkout.title')) ?></span>
</nav>

<div class="section-head">
    <h2><?= e(t('checkout.title')) ?></h2>
    <span class="muted small"><?= e((string) $user['username']) ?> · <?= e((string) $user['email']) ?></span>
</div>

<div class="steps">
    <span class="step is-done"><b>&#10003;</b> <?= e(t('cart.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step is-active"><b>2</b> <?= e(t('checkout.title')) ?></span>
    <span aria-hidden="true">&#8212;</span>
    <span class="step"><b>3</b> <?= e(t('order.confirmed')) ?></span>
</div>

<?php if (!empty($errors['cart'])): ?>
    <div class="notice notice--error" role="alert"><span><?= e($errors['cart']) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('checkout.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="cart-layout">
        <!-- ------------------------------------------------------------ -->
        <!-- Delivery + payment                                            -->
        <!-- ------------------------------------------------------------ -->
        <div class="stack">
            <section class="panel">
                <div class="panel__head">
                    <h3><?= e(t('checkout.shipping_info')) ?></h3>
                    <span class="muted small"><?= e(t('common.required')) ?> *</span>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="shipping_name"><?= e(t('checkout.full_name')) ?> *</label>
                        <input class="input" type="text" id="shipping_name" name="shipping_name" required minlength="2"
                               maxlength="120" value="<?= e($values['shipping_name']) ?>"
                               aria-invalid="<?= isset($errors['shipping_name']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['shipping_name'])): ?><span class="field__error"><?= e($errors['shipping_name']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="shipping_email"><?= e(t('checkout.email')) ?> *</label>
                        <input class="input" type="email" id="shipping_email" name="shipping_email" required
                               maxlength="190" value="<?= e($values['shipping_email']) ?>"
                               aria-invalid="<?= isset($errors['shipping_email']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['shipping_email'])): ?><span class="field__error"><?= e($errors['shipping_email']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="shipping_phone"><?= e(t('checkout.phone')) ?> *</label>
                    <input class="input" type="tel" id="shipping_phone" name="shipping_phone" required maxlength="20"
                           value="<?= e($values['shipping_phone']) ?>" placeholder="+251 91 155 0134"
                           aria-invalid="<?= isset($errors['shipping_phone']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['shipping_phone'])): ?><span class="field__error"><?= e($errors['shipping_phone']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="shipping_address"><?= e(t('checkout.address')) ?> *</label>
                    <input class="input" type="text" id="shipping_address" name="shipping_address" required
                           minlength="5" maxlength="255" value="<?= e($values['shipping_address']) ?>"
                           aria-invalid="<?= isset($errors['shipping_address']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['shipping_address'])): ?><span class="field__error"><?= e($errors['shipping_address']) ?></span><?php endif; ?>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="shipping_city"><?= e(t('checkout.city')) ?> *</label>
                        <input class="input" type="text" id="shipping_city" name="shipping_city" required maxlength="80"
                               value="<?= e($values['shipping_city']) ?>"
                               aria-invalid="<?= isset($errors['shipping_city']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['shipping_city'])): ?><span class="field__error"><?= e($errors['shipping_city']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="notes"><?= e(t('checkout.notes')) ?></label>
                        <input class="input" type="text" id="notes" name="notes" maxlength="500" value="<?= e($values['notes']) ?>">
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__head">
                    <h3><?= e(t('checkout.payment')) ?></h3>
                    <span class="tag tag--gold">Simulated</span>
                </div>

                <label class="pay-method">
                    <input type="radio" name="payment_method" value="card" <?= $values['payment_method'] === 'card' ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e(t('checkout.card')) ?></strong>
                        <span><?= e(t('checkout.card_sub')) ?></span>
                    </span>
                </label>

                <label class="pay-method">
                    <input type="radio" name="payment_method" value="cod" <?= $values['payment_method'] === 'cod' ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e(t('checkout.cod')) ?></strong>
                        <span><?= e(t('checkout.cod_sub')) ?></span>
                    </span>
                </label>

                <div class="field-row field-row--3" style="margin-top:16px">
                    <div class="field">
                        <label for="card_number">Card number</label>
                        <input class="input" type="text" id="card_number" name="card_number" inputmode="numeric"
                               autocomplete="cc-number" maxlength="23" placeholder="4242 4242 4242 4242"
                               aria-invalid="<?= isset($errors['card_number']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['card_number'])): ?><span class="field__error"><?= e($errors['card_number']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="card_expiry">Expiry (MM/YY)</label>
                        <input class="input" type="text" id="card_expiry" name="card_expiry" inputmode="numeric"
                               autocomplete="cc-exp" maxlength="5" placeholder="04/29"
                               aria-invalid="<?= isset($errors['card_expiry']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['card_expiry'])): ?><span class="field__error"><?= e($errors['card_expiry']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="card_cvv">CVV</label>
                        <input class="input" type="text" id="card_cvv" name="card_cvv" inputmode="numeric"
                               autocomplete="cc-csc" maxlength="4" placeholder="123"
                               aria-invalid="<?= isset($errors['card_cvv']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['card_cvv'])): ?><span class="field__error"><?= e($errors['card_cvv']) ?></span><?php endif; ?>
                    </div>
                </div>

                <p class="field__hint">
                    Demo gateway — cards ending in <code>0000</code> are declined so you can test the failure path.
                    No card data is stored anywhere.
                </p>
            </section>
        </div>

        <!-- ------------------------------------------------------------ -->
        <!-- Order review                                                  -->
        <!-- ------------------------------------------------------------ -->
        <aside class="panel summary">
            <div class="panel__head">
                <h3><?= e(t('cart.summary')) ?></h3>
                <a class="small" href="<?= e(url('cart.php')) ?>">Edit</a>
            </div>

            <div style="margin-bottom:16px">
                <?php foreach ($items as $item): ?>
                    <div class="order-line">
                        <span class="order-line__media">
                            <?php if ($item['cover_image'] !== null): ?>
                                <img src="<?= e((string) $item['cover_image']) ?>" alt="" width="42" height="63" loading="lazy">
                            <?php else: ?>
                                <span class="cover cover--t<?= (int) $item['cover_tone'] ?>" style="width:42px">
                                    <span class="cover__top"><span class="cover__title" style="font-size:.55rem"><?= e(excerpt((string) $item['title'], 20)) ?></span></span>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span style="flex:1">
                            <strong style="display:block;font-size:.9rem"><?= e((string) $item['title']) ?></strong>
                            <span class="muted small"><?= (int) $item['quantity'] ?> × <?= e((string) $item['price_fmt']) ?></span>
                        </span>
                        <span><?= e((string) $item['line_total_fmt']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary__row">
                <span><?= e(t('cart.subtotal')) ?></span>
                <span><?= e((string) $summary['subtotal_fmt']) ?></span>
            </div>
            <div class="summary__row">
                <span><?= e(t('cart.shipping')) ?></span>
                <span><?= e((string) $summary['shipping_fmt']) ?></span>
            </div>
            <div class="summary__row summary__row--total">
                <span><?= e(t('cart.total')) ?></span>
                <span><?= e((string) $summary['total_fmt']) ?></span>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:18px">
                <?= e(t('checkout.place')) ?> · <?= e((string) $summary['total_fmt']) ?>
            </button>

            <p class="summary__note"><?= e(t('checkout.secure')) ?></p>
        </aside>
    </div>
</form>

<script>
/* Progressive enhancement for the demo payment step: format the card fields as
   they are typed and grey out the card block when Cash on Delivery is chosen.
   The real validation happens server-side in checkout.php. */
(function () {
  var methods = document.querySelectorAll('[name="payment_method"]');
  var cardIds = ['card_number', 'card_expiry', 'card_cvv'];

  function toggle() {
    var chosen = document.querySelector('[name="payment_method"]:checked');
    var isCard = !!chosen && chosen.value === 'card';

    cardIds.forEach(function (id) {
      var field = document.getElementById(id);
      if (field) {
        field.disabled = !isCard;
        field.closest('.field').style.opacity = isCard ? '1' : '.45';
      }
    });
  }

  methods.forEach(function (radio) { radio.addEventListener('change', toggle); });
  toggle();

  var number = document.getElementById('card_number');
  if (number) {
    number.addEventListener('input', function () {
      var digits = number.value.replace(/\D/g, '').slice(0, 19);
      number.value = digits.replace(/(.{4})/g, '$1 ').trim();
    });
  }

  var expiry = document.getElementById('card_expiry');
  if (expiry) {
    expiry.addEventListener('input', function () {
      var digits = expiry.value.replace(/\D/g, '').slice(0, 4);
      expiry.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
    });
  }

  var cvv = document.getElementById('card_cvv');
  if (cvv) {
    cvv.addEventListener('input', function () {
      cvv.value = cvv.value.replace(/\D/g, '').slice(0, 4);
    });
  }
}());
</script>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
