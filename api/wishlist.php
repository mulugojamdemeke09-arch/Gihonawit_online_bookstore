<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  api/wishlist.php — toggle a title on the wishlist (JSON).
 * ===========================================================================
 *  POST { "book_id": 12 }   with the CSRF token in the X-CSRF-Token header.
 *  Response: { ok, saved, count, message }
 *
 *  The wishlist lives in the session, so it works for guests and survives the
 *  sign-in step: sign in and the items you saved are still there.
 * ===========================================================================
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'message' => 'Wishlist changes must be sent as POST.'], 405);
}

$payload = json_body();

if ($payload === []) {
    $payload = $_POST;
}

$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$token       = is_string($headerToken) && $headerToken !== ''
    ? $headerToken
    : (string) ($payload['csrf_token'] ?? '');

if (!csrf_verify($token)) {
    json_out(['ok' => false, 'message' => 'Your session expired. Please reload the page.'], 419);
}

$bookId = (int) ($payload['book_id'] ?? 0);

if ($bookId <= 0) {
    json_out(['ok' => false, 'message' => 'Which book would you like to save?'], 422);
}

$result = Wishlist::toggle($bookId);

json_out([
    'ok'      => $result['ok'],
    'saved'   => $result['saved'],
    'count'   => Wishlist::count(),
    'message' => $result['message'],
], $result['ok'] ? 200 : 422);
