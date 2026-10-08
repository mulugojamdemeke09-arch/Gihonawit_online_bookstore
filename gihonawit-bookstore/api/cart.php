<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  api/cart.php — session cart endpoint (JSON).
 * ===========================================================================
 *  Always POST, always CSRF protected. The response carries the freshly
 *  priced cart so the browser never has to do the arithmetic itself.
 *
 *  Request body (JSON, or form-encoded):
 *    { "action": "add",     "book_id": 12, "quantity": 2 }
 *    { "action": "update",  "book_id": 12, "quantity": 3 }
 *    { "action": "remove",  "book_id": 12 }
 *    { "action": "clear" }
 *    { "action": "summary" }
 *
 *  Response:
 *    { ok, action, message, summary: { items:[…], count, subtotal, total, … } }
 * ===========================================================================
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'message' => 'Cart updates must be sent as POST.'], 405);
}

$payload = json_body();

if ($payload === []) {
    $payload = $_POST;
}

/* CSRF: the token arrives in the X-CSRF-Token header from js/main.js, or as a
   hidden field when a no-JavaScript form posts here. */
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$token       = is_string($headerToken) && $headerToken !== ''
    ? $headerToken
    : (string) ($payload['csrf_token'] ?? '');

if (!csrf_verify($token)) {
    json_out([
        'ok'      => false,
        'message' => 'Your session expired. Please reload the page and try again.',
    ], 419);
}

$action   = (string) ($payload['action'] ?? 'summary');
$bookId   = (int) ($payload['book_id'] ?? 0);
$quantity = (int) ($payload['quantity'] ?? 1);
$result   = ['ok' => true, 'message' => ''];

switch ($action) {
    case 'add':
        $result = $bookId > 0
            ? Cart::add($bookId, max(1, $quantity))
            : ['ok' => false, 'message' => 'Which book would you like to add?'];
        break;

    case 'update':
        $result = $bookId > 0
            ? Cart::setQuantity($bookId, $quantity)
            : ['ok' => false, 'message' => 'Which book would you like to update?'];
        break;

    case 'remove':
        if ($bookId > 0) {
            Cart::remove($bookId);
            $result = ['ok' => true, 'message' => 'Removed from your cart.'];
        } else {
            $result = ['ok' => false, 'message' => 'Which book would you like to remove?'];
        }
        break;

    case 'clear':
        Cart::clear();
        $result = ['ok' => true, 'message' => 'Your cart is now empty.'];
        break;

    case 'summary':
        break;

    default:
        $result = ['ok' => false, 'message' => 'Unknown cart action.'];
}

$summary = Cart::summary();

json_out([
    'ok'      => $result['ok'],
    'action'  => $action,
    'message' => $result['message'],
    'summary' => [
        'count'        => (int) $summary['count'],
        'line_count'   => (int) $summary['line_count'],
        'subtotal'     => (float) $summary['subtotal'],
        'subtotal_fmt' => (string) $summary['subtotal_fmt'],
        'shipping'     => (float) $summary['shipping'],
        'shipping_fmt' => (string) $summary['shipping_fmt'],
        'total'        => (float) $summary['total'],
        'total_fmt'    => (string) $summary['total_fmt'],
        'gap'          => (float) $summary['gap'],
        'threshold_fmt' => (string) $summary['threshold_fmt'],
        'problems'     => $summary['problems'],
        'items'        => $summary['items'],
    ],
], $result['ok'] ? 200 : 409);
