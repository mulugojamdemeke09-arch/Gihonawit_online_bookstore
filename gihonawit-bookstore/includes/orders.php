<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/orders.php — order reads shared by the receipt page, the customer
 *  account area and the admin dashboard.
 * ===========================================================================
 *  Writes live in checkout.php (creation, inside a transaction) and
 *  admin/dashboard.php (status changes) so each mutation has one owner.
 * ===========================================================================
 */

/** Order statuses, mirroring the ENUM in the schema. */
function order_statuses(): array
{
    return ['Pending', 'Shipped', 'Cancelled'];
}

/** @return array<string,mixed>|null */
function order_find(int $id): ?array
{
    return db_one(
        'SELECT o.*, u.username
           FROM orders o
           LEFT JOIN users u ON u.id = o.user_id
          WHERE o.id = :id LIMIT 1',
        [':id' => $id]
    );
}

/** @return array<string,mixed>|null Order row with an `items` key attached. */
function order_find_by_number(string $number): ?array
{
    $order = db_one(
        'SELECT o.*, u.username
           FROM orders o
           LEFT JOIN users u ON u.id = o.user_id
          WHERE o.order_number = :number LIMIT 1',
        [':number' => $number]
    );

    if ($order === null) {
        return null;
    }

    $order['items'] = order_items((int) $order['id']);

    return $order;
}

/**
 * Order lines, with the book's current title and cover attached. A book that
 * was deleted keeps its historical line (book_id becomes NULL) and is shown
 * with the title recorded at the time of purchase.
 *
 * @return array<int,array<string,mixed>>
 */
function order_items(int $orderId): array
{
    $rows = db_all(
        'SELECT oi.id, oi.book_id, oi.quantity, oi.price_at_purchase,
                b.title, b.author, b.genre, b.cover_image_url, b.price AS current_price
           FROM order_items oi
           LEFT JOIN books b ON b.id = oi.book_id
          WHERE oi.order_id = :order_id
          ORDER BY oi.id ASC',
        [':order_id' => $orderId]
    );

    return array_map(static function (array $row): array {
        $row['line_total']     = round((float) $row['price_at_purchase'] * (int) $row['quantity'], 2);
        $row['line_total_fmt'] = money((float) $row['line_total']);
        $row['price_fmt']      = money((float) $row['price_at_purchase']);
        $row['title']          = (string) ($row['title'] ?? 'Title removed from the catalogue');
        $row['author']         = (string) ($row['author'] ?? '');
        $row['cover_image']    = isset($row['cover_image_url']) ? cover_image($row) : null;

        return $row;
    }, $rows);
}

/** A customer's order history, newest first. */
function orders_for_user(int $userId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));

    return db_all(
        'SELECT o.*,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
           FROM orders o
          WHERE o.user_id = :user_id
          ORDER BY o.created_at DESC, o.id DESC
          LIMIT ' . $limit,
        [':user_id' => $userId]
    );
}

/** Money summary for one order row. */
function order_totals(array $order): array
{
    $subtotal = (float) $order['subtotal'];
    $shipping = (float) $order['shipping_amount'];

    return [
        'subtotal'     => $subtotal,
        'subtotal_fmt' => money($subtotal),
        'shipping'     => $shipping,
        'shipping_fmt' => $shipping > 0 ? money($shipping) : 'Free',
        'total'        => (float) $order['total_amount'],
        'total_fmt'    => money((float) $order['total_amount']),
    ];
}

/** Counts used by the admin dashboard tiles. */
function order_stats(): array
{
    $row = db_one(
        "SELECT COUNT(*)                                                                  AS total,
                COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total_amount END), 0)   AS revenue,
                SUM(CASE WHEN status = 'Pending'   THEN 1 ELSE 0 END)                     AS pending,
                SUM(CASE WHEN status = 'Shipped'   THEN 1 ELSE 0 END)                     AS shipped,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END)                     AS cancelled,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END)             AS today
           FROM orders"
    ) ?? [];

    return [
        'total'     => (int) ($row['total'] ?? 0),
        'revenue'   => (float) ($row['revenue'] ?? 0),
        'pending'   => (int) ($row['pending'] ?? 0),
        'shipped'   => (int) ($row['shipped'] ?? 0),
        'cancelled' => (int) ($row['cancelled'] ?? 0),
        'today'     => (int) ($row['today'] ?? 0),
    ];
}

/**
 * Revenue per day for the dashboard chart, zero-filled.
 *
 * @return array<int,array{date:string,label:string,total:float,orders:int}>
 */
function order_sales_by_day(int $days = 7): array
{
    $days = max(1, min(60, $days));

    $rows = db_all(
        "SELECT DATE(created_at) AS day,
                COUNT(*)          AS orders,
                COALESCE(SUM(total_amount), 0) AS total
           FROM orders
          WHERE status <> 'Cancelled'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
          GROUP BY DATE(created_at)",
        [':days' => $days - 1]
    );

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(string) $row['day']] = $row;
    }

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = gmdate('Y-m-d', strtotime('-' . $i . ' day'));
        $row  = $indexed[$date] ?? null;

        $series[] = [
            'date'   => $date,
            'label'  => gmdate('D j M', strtotime($date)),
            'orders' => (int) ($row['orders'] ?? 0),
            'total'  => (float) ($row['total'] ?? 0),
        ];
    }

    return $series;
}
