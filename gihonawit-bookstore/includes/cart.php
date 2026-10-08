<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/cart.php — session-backed cart and wishlist.
 * ===========================================================================
 *  Both live in the PHP session, so a guest can fill a cart before signing
 *  in. Only the book id => quantity map is stored; prices, titles and stock
 *  are always re-read from MySQL, which means a visitor cannot influence
 *  what they are charged by editing anything in the browser.
 * ===========================================================================
 */
final class Cart
{
    private const KEY = 'cart';

    /** @return array<int,int> book id => quantity */
    public static function raw(): array
    {
        $cart = $_SESSION[self::KEY] ?? [];

        return is_array($cart) ? array_map('intval', $cart) : [];
    }

    private static function persist(array $cart): void
    {
        $clean = [];

        foreach ($cart as $id => $qty) {
            $id  = (int) $id;
            $qty = (int) $qty;

            if ($id > 0 && $qty > 0) {
                $clean[$id] = min($qty, MAX_QTY_PER_LINE);
            }
        }

        if ($clean === []) {
            unset($_SESSION[self::KEY]);
        } else {
            $_SESSION[self::KEY] = $clean;
        }
    }

    /**
     * Cart lines joined with live catalogue data.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function items(): array
    {
        $raw = self::raw();

        if ($raw === []) {
            return [];
        }

        $ids          = array_keys($raw);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows         = db_all("SELECT * FROM books WHERE id IN ($placeholders)", $ids);

        $items = [];
        foreach ($rows as $book) {
            $id       = (int) $book['id'];
            $quantity = (int) ($raw[$id] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $price = (float) $book['price'];
            $stock = (int) $book['stock_quantity'];

            $items[] = [
                'book_id'        => $id,
                'title'          => (string) $book['title'],
                'author'         => (string) $book['author'],
                'genre'          => (string) $book['genre'],
                'price'          => $price,
                'price_fmt'      => money($price),
                'quantity'       => $quantity,
                'line_total'     => round($price * $quantity, 2),
                'line_total_fmt' => money(round($price * $quantity, 2)),
                'stock_quantity' => $stock,
                'stock_ok'       => $stock >= $quantity,
                'cover_image'    => cover_image($book),
                'cover_tone'     => cover_tone($book),
                'url'            => url('book-details.php?id=' . $id),
            ];
        }

        return $items;
    }

    /** @return array{ok:bool,message:string} */
    public static function add(int $bookId, int $quantity = 1): array
    {
        $book = db_one('SELECT id, title, stock_quantity FROM books WHERE id = :id LIMIT 1', [':id' => $bookId]);

        if ($book === null) {
            return ['ok' => false, 'message' => 'That title is no longer in the catalogue.'];
        }

        $quantity = max(1, $quantity);
        $cart     = self::raw();
        $current  = (int) ($cart[$bookId] ?? 0);
        $stock    = (int) $book['stock_quantity'];

        if ($stock <= 0) {
            return ['ok' => false, 'message' => '“' . $book['title'] . '” is out of stock.'];
        }

        if ($current + $quantity > $stock) {
            return [
                'ok'      => false,
                'message' => 'Only ' . $stock . ' copies of “' . $book['title'] . '” are in stock.',
            ];
        }

        if ($current + $quantity > MAX_QTY_PER_LINE) {
            $cart[$bookId] = MAX_QTY_PER_LINE;
            self::persist($cart);

            return ['ok' => true, 'message' => 'Limited to ' . MAX_QTY_PER_LINE . ' copies per order.'];
        }

        $cart[$bookId] = $current + $quantity;
        self::persist($cart);

        return ['ok' => true, 'message' => '“' . $book['title'] . '” added to your cart.'];
    }

    /** @return array{ok:bool,message:string} */
    public static function setQuantity(int $bookId, int $quantity): array
    {
        if ($quantity < 1) {
            self::remove($bookId);

            return ['ok' => true, 'message' => t('cart.updated')];
        }

        $book = db_one('SELECT title, stock_quantity FROM books WHERE id = :id LIMIT 1', [':id' => $bookId]);

        if ($book === null) {
            self::remove($bookId);

            return ['ok' => false, 'message' => 'That title is no longer in the catalogue.'];
        }

        $stock = (int) $book['stock_quantity'];
        $note  = t('cart.updated');

        if ($quantity > $stock) {
            $quantity = $stock;
            $note     = 'Only ' . $stock . ' copies of “' . $book['title'] . '” are in stock — quantity adjusted.';
        }

        if ($quantity < 1) {
            self::remove($bookId);

            return ['ok' => false, 'message' => '“' . $book['title'] . '” is out of stock.'];
        }

        $cart          = self::raw();
        $cart[$bookId] = min($quantity, MAX_QTY_PER_LINE);
        self::persist($cart);

        return ['ok' => true, 'message' => $note];
    }

    public static function remove(int $bookId): void
    {
        $cart = self::raw();
        unset($cart[$bookId]);
        self::persist($cart);
    }

    public static function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }

    /** Total copies in the cart — drives the header badge. */
    public static function count(): int
    {
        return array_sum(self::raw());
    }

    public static function lineCount(): int
    {
        return count(self::raw());
    }

    public static function subtotal(): float
    {
        $subtotal = 0.0;

        foreach (self::items() as $item) {
            $subtotal += (float) $item['line_total'];
        }

        return round($subtotal, 2);
    }

    /** Delivery is free above the threshold. */
    public static function shipping(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        return $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.0 : FLAT_SHIPPING_RATE;
    }

    /**
     * Everything the cart page and the JSON API need.
     *
     * @return array<string,mixed>
     */
    public static function summary(): array
    {
        $items    = self::items();
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item['line_total'];
        }

        $subtotal = round($subtotal, 2);
        $shipping = self::shipping($subtotal);

        return [
            'items'          => $items,
            'count'          => array_sum(array_column($items, 'quantity')) ?: 0,
            'line_count'     => count($items),
            'subtotal'       => $subtotal,
            'subtotal_fmt'   => money($subtotal),
            'shipping'       => $shipping,
            'shipping_fmt'   => $shipping > 0 ? money($shipping) : 'Free',
            'total'          => round($subtotal + $shipping, 2),
            'total_fmt'      => money($subtotal + $shipping),
            'threshold'      => FREE_SHIPPING_THRESHOLD,
            'threshold_fmt'  => money(FREE_SHIPPING_THRESHOLD),
            'gap'            => max(0, round(FREE_SHIPPING_THRESHOLD - $subtotal, 2)),
            'problems'       => self::validateStock(),
        ];
    }

    /**
     * Re-check every line against current stock.
     *
     * @return array<int,string>
     */
    public static function validateStock(): array
    {
        $problems = [];

        foreach (self::items() as $item) {
            $stock  = (int) $item['stock_quantity'];
            $wanted = (int) $item['quantity'];

            if ($stock <= 0) {
                $problems[] = '“' . $item['title'] . '” has just gone out of stock.';
            } elseif ($wanted > $stock) {
                $problems[] = 'Only ' . $stock . ' copies of “' . $item['title'] . '” remain — please reduce the quantity.';
            }
        }

        return $problems;
    }
}

/**
 * Wishlist — available to guests too, kept in the session next to the cart.
 */
final class Wishlist
{
    private const KEY = 'wishlist';

    /** @return array<int,int> */
    public static function ids(): array
    {
        $ids = $_SESSION[self::KEY] ?? [];

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    private static function persist(array $ids): void
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));

        if ($ids === []) {
            unset($_SESSION[self::KEY]);
        } else {
            $_SESSION[self::KEY] = $ids;
        }
    }

    public static function has(int $bookId): bool
    {
        return in_array($bookId, self::ids(), true);
    }

    /** @return array{ok:bool,saved:bool,message:string} */
    public static function toggle(int $bookId): array
    {
        if (db_value('SELECT COUNT(*) FROM books WHERE id = :id', [':id' => $bookId]) < 1) {
            return ['ok' => false, 'saved' => false, 'message' => 'That title is no longer in the catalogue.'];
        }

        $ids = self::ids();

        if (in_array($bookId, $ids, true)) {
            self::persist(array_diff($ids, [$bookId]));

            return ['ok' => true, 'saved' => false, 'message' => 'Removed from your wishlist.'];
        }

        $ids[] = $bookId;
        self::persist($ids);

        return ['ok' => true, 'saved' => true, 'message' => 'Saved to your wishlist.'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        $ids = self::ids();

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows         = db_all("SELECT * FROM books WHERE id IN ($placeholders) ORDER BY title ASC", $ids);

        return $rows;
    }

    public static function count(): int
    {
        return count(self::ids());
    }
}
