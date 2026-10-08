<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/functions.php — view helpers, security helpers, icon library.
 * ===========================================================================
 */

/* -------------------------------------------------------------------------- */
/* Output escaping                                                            */
/* -------------------------------------------------------------------------- */

/** Escape a value for HTML output. Use this on EVERY piece of dynamic text. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a price using the storefront currency. */
function money($amount): string
{
    return SITE_CURRENCY . number_format((float) $amount, 2);
}

/** Trim a long string to a sensible excerpt without cutting a word in half. */
function excerpt(string $text, int $length = 120): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', $text));

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    $slice = mb_substr($text, 0, $length);
    $space = mb_strrpos($slice, ' ');

    return rtrim($space !== false ? mb_substr($slice, 0, $space) : $slice) . '…';
}

/* -------------------------------------------------------------------------- */
/* URLs                                                                       */
/* -------------------------------------------------------------------------- */

/** Build an application URL from a path relative to the project root. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }

    return BASE_URL . '/' . $path;
}

/** Build an /assets URL. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Redirect and stop. */
function redirect(string $path = ''): void
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)));
    exit;
}

/** Current request path + query, used by the language switch. */
function current_url(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);

    $path = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

    return $query === [] ? $path : $path . '?' . http_build_query($query);
}

/* -------------------------------------------------------------------------- */
/* Flash messages                                                             */
/* -------------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int,array{type:string,message:string}> */
function flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

/** Render queued flash messages as dismissible notices. */
function render_flashes(): string
{
    $html = '';

    foreach (flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info';
        $html .= '<div class="notice notice--' . $type . '" role="status">'
            . '<span>' . e($flash['message']) . '</span>'
            . '<button type="button" class="notice__close" data-dismiss aria-label="Dismiss">&times;</button>'
            . '</div>';
    }

    return $html;
}

/* -------------------------------------------------------------------------- */
/* CSRF                                                                       */
/* -------------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    $expected = (string) ($_SESSION['_csrf'] ?? '');

    return is_string($token) && $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

/** Stop the request unless a valid CSRF token accompanies it. */
function csrf_guard(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(419);
        flash('error', 'Your session expired. Please try that again.');
        redirect(basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
    }
}

/* -------------------------------------------------------------------------- */
/* Authentication (session based)                                             */
/* -------------------------------------------------------------------------- */

/** @return array<string,mixed>|null */
function current_user(): ?array
{
    static $cache = null;
    static $loaded = false;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!$loaded) {
        $loaded = true;
        $cache  = db_one(
            'SELECT id, username, email, role, created_at FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $_SESSION['user_id']]
        );

        if ($cache === null) {
            unset($_SESSION['user_id'], $_SESSION['user_role']);
        }
    }

    return $cache;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();

    return $user !== null && (string) $user['role'] === 'admin';
}

function user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

/** Log a user in: store the minimum and rotate the session id. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_role'] = (string) $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

/** Require a signed-in customer, remembering where they were headed. */
function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $_SESSION['_intended'] = current_url();
    flash('info', 'Please sign in to continue.');
    redirect('login.php');
}

/** Require the administrator role. Called before any admin markup is printed. */
function require_admin(): void
{
    if (!is_logged_in()) {
        $_SESSION['_intended'] = current_url();
        flash('info', 'Administrator sign-in required.');
        redirect(BASE_URL . '/login.php');
    }

    if (!is_admin()) {
        http_response_code(403);
        flash('error', 'That area is restricted to store administrators.');
        redirect(BASE_URL . '/index.php');
    }
}

/** Consume the remembered destination after a successful sign-in. */
function intended_url(string $fallback = 'index.php'): string
{
    $intended = (string) ($_SESSION['_intended'] ?? '');
    unset($_SESSION['_intended']);

    if ($intended === '' || strpos($intended, '//') !== false || strpos($intended, '\\') !== false) {
        return $fallback;
    }

    return ltrim(str_replace(BASE_URL, '', parse_url($intended, PHP_URL_PATH) ?: $fallback), '/') ?: $fallback;
}

/* -------------------------------------------------------------------------- */
/* Request helpers                                                            */
/* -------------------------------------------------------------------------- */

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Trimmed string from $_POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Trimmed string from $_GET. */
function get_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Safe 1-based page number. */
function page_no(): int
{
    $page = (int) get_param('page', '1');

    return $page < 1 ? 1 : $page;
}

/** Emit JSON and stop. */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Decoded JSON request body (used by the Fetch API client). */
function json_body(): array
{
    $raw = file_get_contents('php://input');

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/* -------------------------------------------------------------------------- */
/* Catalogue presentation helpers                                             */
/* -------------------------------------------------------------------------- */

/** Is a real cover image configured for this book? */
function has_cover_image(array $book): bool
{
    return trim((string) ($book['cover_image_url'] ?? '')) !== '';
}

/** URL of a configured cover image, or null when the CSS cover is used. */
function cover_image(array $book): ?string
{
    $path = trim((string) ($book['cover_image_url'] ?? ''));

    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path) || strpos($path, '//') === 0) {
        return $path;
    }

    return strpos($path, '/') === 0 ? $path : asset('img/' . ltrim($path, '/'));
}

/**
 * Deterministic cover tone (1-6). The generated cover template picks its
 * palette from the title, so the same book always looks the same while a
 * grid of covers still shows variety.
 */
function cover_tone(array $book): int
{
    $seed = (string) ($book['title'] ?? '') . (string) ($book['author'] ?? '');

    return (abs(crc32($seed)) % 6) + 1;
}

/** "In stock" / "Only 3 left" / "Out of stock". */
function stock_label(int $quantity): string
{
    if ($quantity <= 0) {
        return t('card.out_of_stock');
    }

    if ($quantity <= 5) {
        return tf('card.low_stock', $quantity);
    }

    return t('book.in_stock');
}

/** Badge class for a stock level. */
function stock_class(int $quantity): string
{
    if ($quantity <= 0) {
        return 'is-out';
    }

    return $quantity <= 5 ? 'is-low' : 'is-in';
}

/** Badge for one of the three order statuses. */
function status_badge(string $status): string
{
    $tone = match ($status) {
        'Shipped'   => 'is-shipped',
        'Cancelled' => 'is-cancelled',
        default     => 'is-pending',
    };

    return '<span class="status ' . $tone . '">' . e($status) . '</span>';
}

/* -------------------------------------------------------------------------- */
/* Icon library — inline SVG so the storefront needs no icon font or CDN       */
/* -------------------------------------------------------------------------- */

/** Render a 24×24 line icon. */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'home'      => '<path d="M3 11.2 12 4l9 7.2V20a1 1 0 0 1-1 1h-5v-6.5H9V21H4a1 1 0 0 1-1-1z"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
        'cart'      => '<path d="M3 4h2.2l2.3 10.6A2 2 0 0 0 9.5 16h7.9a2 2 0 0 0 2-1.6L21 8H6.2"/><circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
        'heart'     => '<path d="M12 20.3S4.5 15.6 4.5 10.3A4.2 4.2 0 0 1 12 7.6a4.2 4.2 0 0 1 7.5 2.7c0 5.3-7.5 10-7.5 10z"/>',
        'user'      => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.8 20.5a7.2 7.2 0 0 1 14.4 0"/>',
        'book'      => '<path d="M6 3.5h11.5a1.5 1.5 0 0 1 1.5 1.5v15.5H7.5A1.5 1.5 0 0 1 6 19z"/><path d="M6 3.5A1.5 1.5 0 0 0 4.5 5v13.5"/><path d="M9.5 8.5h6"/>',
        'open-book' => '<path d="M12 6.6C9.8 5 7.2 4.4 4.5 4.5v13.9c2.7-.1 5.3.5 7.5 2.1 2.2-1.6 4.8-2.2 7.5-2.1V4.5c-2.7-.1-5.3.5-7.5 2.1z"/><path d="M12 6.6v13.9"/>',
        'globe'     => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.2 2.3 3.4 5.3 3.4 8.5S14.2 18.2 12 20.5c-2.2-2.3-3.4-5.3-3.4-8.5S9.8 5.8 12 3.5z"/>',
        'cap'       => '<path d="M2.5 9 12 4.2 21.5 9 12 13.8z"/><path d="M6.4 11.2v4.9c0 1.6 2.5 2.9 5.6 2.9s5.6-1.3 5.6-2.9v-4.9"/>',
        'sprout'    => '<path d="M12 20.5V13"/><path d="M12 13C12 9.9 9.4 7.5 6 7.5c0 3.1 2.6 5.5 6 5.5z"/><path d="M12 13c0-3.1 2.6-5.5 6-5.5 0 3.1-2.6 5.5-6 5.5z"/>',
        'cog'       => '<circle cx="12" cy="12" r="2.7"/><path d="M19.3 13.2a7.6 7.6 0 0 0 0-2.4l2-1.5-1.9-3.3-2.3.9a7.4 7.4 0 0 0-1.7-1l-.4-2.4H9l-.4 2.4a7.4 7.4 0 0 0-1.7 1l-2.3-.9-1.9 3.3 2 1.5a7.6 7.6 0 0 0 0 2.4l-2 1.5 1.9 3.3 2.3-.9a7.4 7.4 0 0 0 1.7 1l.4 2.4h4l.4-2.4a7.4 7.4 0 0 0 1.7-1l2.3.9 1.9-3.3z"/>',
        'palette'   => '<path d="M12 3.5a8.5 8.5 0 0 0 0 17c1.2 0 1.9-1 1.5-2-.4-1.1.4-2.2 1.6-2.2h1.2a4.2 4.2 0 0 0 4.2-4.2c0-4.7-3.8-8.6-8.5-8.6z"/><circle cx="8" cy="10" r="1.1"/><circle cx="12" cy="8" r="1.1"/><circle cx="15.8" cy="10.4" r="1.1"/>',
        'dots'      => '<circle cx="6" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="18" cy="12" r="1.7"/>',
        'truck'     => '<path d="M3 6.5h10.5V16H3z"/><path d="M13.5 9.5H17l3 3V16h-6.5z"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        'shield'    => '<path d="M12 3.2 19 6v5.8c0 4.1-2.9 7.6-7 8.9-4.1-1.3-7-4.8-7-8.9V6z"/><path d="m9 12.2 2.1 2.1 4-4.2"/>',
        'books'     => '<path d="M4 6.5h4.5V20H4z"/><path d="M9.8 4.5h4.5V20H9.8z"/><path d="M15.6 7.5H20V20h-4.4z"/>',
        'check'     => '<path d="m5 12.8 4.3 4.3L19 7.4"/>',
        'arrow'     => '<path d="M5 12h13"/><path d="m12.5 6 6 6-6 6"/>',
        'chevron'   => '<path d="m6 9.5 6 6 6-6"/>',
        'trash'     => '<path d="M4.5 7h15"/><path d="M9.5 7V4.8h5V7"/><path d="M6.5 7l1 13h9l1-13"/>',
        'sparkle'   => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18.2l-1.8-5.6L4.5 10.8 10.2 9z"/>',
    ];

    $path = $paths[$name] ?? $paths['book'];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $path . '</svg>';
}

/** The Ethiopian flag mark used by the language switch. */
function flag_icon(string $class = 'flag'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 24 16" aria-hidden="true" focusable="false">'
        . '<rect width="24" height="5.34" y="0" fill="#078930"/>'
        . '<rect width="24" height="5.34" y="5.33" fill="#fcdd09"/>'
        . '<rect width="24" height="5.34" y="10.66" fill="#da121a"/>'
        . '<circle cx="12" cy="8" r="4.1" fill="#0f47af"/>'
        . '<polygon fill="#fcdd09" points="12,4.7 12.79,6.91 15.14,6.98 13.28,8.42 13.94,10.67 12,9.35 10.06,10.67 10.72,8.42 8.86,6.98 11.21,6.91"/>'
        . '</svg>';
}

/** The Gihonawit mark: an open book with a tree growing from its spine. */
function brand_mark(string $class = 'brand__mark'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 48 48" fill="none" aria-hidden="true" focusable="false">'
        . '<path d="M24 15.5c-3.6-2.7-8-4-12.6-4v21.6c4.6 0 9 1.3 12.6 4 3.6-2.7 8-4 12.6-4V11.5c-4.6 0-9 1.3-12.6 4z"'
        . ' stroke="currentColor" stroke-width="2.3" stroke-linejoin="round"/>'
        . '<path d="M24 15.5v21.6" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>'
        . '<path d="M24 14.5V9.5" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>'
        . '<path d="M24 12.4c.4-3.6 3.6-6.4 7.6-6.1.3 3.6-2.9 6.5-6.9 6.2z" fill="currentColor" opacity=".92"/>'
        . '<path d="M24 14.2c-.5-3.5-3.7-6.1-7.7-5.7-.2 3.6 3 6.3 7 5.8z" fill="currentColor" opacity=".72"/>'
        . '</svg>';
}

/**
 * Luhn checksum for the simulated card gateway in checkout.php.
 * Card numbers are validated only — never stored, never transmitted.
 */
function luhn_valid(string $number): bool
{
    $digits = preg_replace('/\D/', '', $number) ?? '';

    if (strlen($digits) < 12) {
        return false;
    }

    $sum       = 0;
    $alternate = false;

    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $digit = (int) $digits[$i];

        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }

        $sum      += $digit;
        $alternate = !$alternate;
    }

    return $sum % 10 === 0;
}
