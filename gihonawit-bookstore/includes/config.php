<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/config.php — static site configuration.
 * ===========================================================================
 *  Site identity, the category ribbon map, delivery rules and the store
 *  contact block. Everything here is data, not behaviour.
 * ===========================================================================
 */

/* -------------------------------------------------------------------------- */
/* Identity                                                                   */
/* -------------------------------------------------------------------------- */
define('SITE_NAME', 'Gihonawit');
define('SITE_SUFFIX', 'Online Bookstore');
define('SITE_TAGLINE', 'Books for a Brighter Tomorrow');
define('SITE_CURRENCY', '$');
define('SITE_EMAIL', 'hello@gihonawit.test');
define('SITE_PHONE', '+251 11 555 0199');
define('SITE_ADDRESS', 'Bole Road, Friendship Building, 4th floor, Addis Ababa, Ethiopia');
define('SITE_HOURS', 'Monday to Saturday, 8:30 – 18:30 (EAT)');

/* -------------------------------------------------------------------------- */
/* Delivery rules (mirrored by includes/cart.php and checkout.php)            */
/* -------------------------------------------------------------------------- */
define('FREE_SHIPPING_THRESHOLD', 35.00);
define('FLAT_SHIPPING_RATE', 4.99);
define('MAX_QTY_PER_LINE', 10);

/* -------------------------------------------------------------------------- */
/* Category ribbon                                                            */
/* -------------------------------------------------------------------------- */
/* The eight tiles under the hero. Each tile maps to one or more values of the
   books.genre column, so a tile filters the catalogue without needing a
   second table. Adding a tile is a one-line change here. */
function categories(): array
{
    return [
        [
            'slug'   => 'ethiopian-history-culture',
            'en'     => 'Ethiopian History & Culture',
            'am'     => 'የኢትዮጵያ ታሪክና ባህል',
            'icon'   => 'book',
            'genres' => ['Ethiopian History', 'Ethiopian Culture'],
        ],
        [
            'slug'   => 'amharic-literature',
            'en'     => 'Amharic Literature',
            'am'     => 'የአማርኛ ሥነ ጽሑፍ',
            'icon'   => 'open-book',
            'genres' => ['Amharic Literature'],
        ],
        [
            'slug'   => 'international-fiction-classics',
            'en'     => 'International Fiction & Classics',
            'am'     => 'ዓለም አቀፍ ልቦለድና ጥንታዊ መጻሕፍት',
            'icon'   => 'globe',
            'genres' => ['Fiction', 'Classics'],
        ],
        [
            'slug'   => 'education-academic',
            'en'     => 'Education & Academic',
            'am'     => 'ትምህርትና አካዳሚ',
            'icon'   => 'cap',
            'genres' => ['Education', 'Academic'],
        ],
        [
            'slug'   => 'self-help',
            'en'     => 'Self-Help & Personal Development',
            'am'     => 'ራስን ማሻሻል',
            'icon'   => 'sprout',
            'genres' => ['Self-Help', 'Personal Development'],
        ],
        [
            'slug'   => 'science-technology',
            'en'     => 'Science & Technology',
            'am'     => 'ሳይንስና ቴክኖሎጂ',
            'icon'   => 'cog',
            'genres' => ['Science', 'Technology'],
        ],
        [
            'slug'   => 'arts-lifestyle',
            'en'     => 'Arts & Lifestyle',
            'am'     => 'ሥነ ጥበብና የኑሮ ዘይቤ',
            'icon'   => 'palette',
            'genres' => ['Arts', 'Lifestyle'],
        ],
    ];
}

/** Look up one category by its slug. */
function category_by_slug(string $slug): ?array
{
    foreach (categories() as $category) {
        if ($category['slug'] === $slug) {
            return $category;
        }
    }

    return null;
}

/** Every genre value referenced by the ribbon (used for the "all" view). */
function ribbon_genres(): array
{
    $genres = [];

    foreach (categories() as $category) {
        $genres = array_merge($genres, $category['genres']);
    }

    return $genres;
}

/* -------------------------------------------------------------------------- */
/* Primary navigation                                                         */
/* -------------------------------------------------------------------------- */
function main_menu(): array
{
    return [
        ['label' => 'nav.home',       'url' => 'index.php',   'icon' => 'home'],
        ['label' => 'nav.books',      'url' => 'books.php',   'icon' => null],
        ['label' => 'nav.authors',    'url' => 'authors.php', 'icon' => null],
        ['label' => 'nav.categories', 'url' => 'books.php#categories', 'icon' => null],
        ['label' => 'nav.about',      'url' => 'about.php',   'icon' => null],
        ['label' => 'nav.contact',    'url' => 'contact.php', 'icon' => null],
    ];
}

/** Sort options offered on the catalogue page. */
function sort_options(): array
{
    return [
        'newest'      => 'Newest first',
        'title'       => 'Title A–Z',
        'price_asc'   => 'Price: low to high',
        'price_desc'  => 'Price: high to low',
        'bestselling' => 'Best selling',
    ];
}

/** Order statuses — mirrors the orders.status ENUM exactly. */
function order_statuses(): array
{
    return ['Pending', 'Shipped', 'Cancelled'];
}

/* -------------------------------------------------------------------------- */
/* Hero messages                                                              */
/* -------------------------------------------------------------------------- */
/* The three dots beneath the hero rotate through these messages. Keeping the
   copy here means marketing can rewrite the hero without touching any PHP. */
function hero_slides(): array
{
    return [
        [
            'en_tag'  => 'Discover Stories. Explore Knowledge. Celebrate Ethiopia.',
            'am_tag'  => 'ታሪኮችን ያግኙ። እውቀትን ይዳስሱ። ኢትዮጵያን ያክብሩ።',
            'en_body' => 'From rich Ethiopian heritage to global bestsellers, Gihonawit brings books closer to you — wherever you are.',
            'am_body' => 'ከኢትዮጵያ የበለጸገ ቅርስ ጀምሮ እስከ ዓለም አቀፍ ተወዳጅ መጻሕፍት፣ ጊሆናዊት መጻሕፍትን በሚገኙበት ቦታ ሁሉ ያቀርባል።',
        ],
        [
            'en_tag'  => 'Ethiopian Voices. Global Perspectives.',
            'am_tag'  => 'የኢትዮጵያ ድምጾች። ዓለም አቀፍ አመለካከቶች።',
            'en_body' => 'Amharic literature and Ethiopian history sit beside the world classics — one shelf, every reader.',
            'am_body' => 'የአማርኛ ሥነ ጽሑፍና የኢትዮጵያ ታሪክ ከዓለም ጥንታዊ መጻሕፍት ጎን — አንድ መደርደሪያ፣ ለሁሉም አንባቢ።',
        ],
        [
            'en_tag'  => 'Every Order Supports Local Authors.',
            'am_tag'  => 'ሁሉም ግዢ የአገር ውስጥ ደራሲዎችን ይደግፋል።',
            'en_body' => 'Free delivery across Ethiopia and beyond on orders over ' . money(FREE_SHIPPING_THRESHOLD) . '.',
            'am_body' => 'ከ' . money(FREE_SHIPPING_THRESHOLD) . ' በላይ ግዢ ላይ በኢትዮጵያ ውስጥና ከውጭ ነጻ ማድረስ።',
        ],
    ];
}

/** Hero slide copy resolved for the active language. */
function hero_slide(int $index): array
{
    $slides = hero_slides();
    $slide  = $slides[$index] ?? $slides[0];
    $am     = is_amharic();

    return [
        'tag'  => $am ? $slide['am_tag'] : $slide['en_tag'],
        'body' => $am ? $slide['am_body'] : $slide['en_body'],
    ];
}
