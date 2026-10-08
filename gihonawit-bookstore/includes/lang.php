<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  includes/lang.php — bilingual storefront chrome (English / አማርኛ).
 * ===========================================================================
 *  The language switch in the top navigator stores the choice in the session
 *  and every label in the shared chrome is rendered through t('key').
 *
 *  Scope: all navigation, buttons, section headings, labels and footer copy.
 *  Catalogue content itself comes from the database, so book titles and
 *  descriptions are shown exactly as they were authored.
 *
 *  NOTE FOR THE CLIENT: the Amharic column is a first-pass translation of the
 *  interface. Have a native speaker review it before launch — that is a
 *  five-minute job and it removes any awkward phrasing.
 * ===========================================================================
 */

/**
 * Translation table. Keys are shared; add a language by adding one block.
 *
 * @return array<string,array<string,string>>
 */
function translations(): array
{
    static $table = null;

    if ($table !== null) {
        return $table;
    }

    $table = [
        'en' => [
            /* Navigation */
            'nav.home'            => 'Home',
            'nav.books'           => 'Books',
            'nav.authors'         => 'Authors',
            'nav.categories'      => 'Categories',
            'nav.about'           => 'About Us',
            'nav.contact'         => 'Contact',

            /* Header utilities */
            'header.search'       => 'Search for books, authors, or topics…',
            'header.search_short' => 'Search books…',
            'header.login'        => 'Login / Sign Up',
            'header.wishlist'     => 'Wishlist',
            'header.cart'         => 'Cart',
            'header.menu'         => 'Menu',

            /* Hero */
            'hero.title'          => 'Gihonawit',
            'hero.subtitle'       => 'Online Bookstore',
            'hero.line1'          => 'Discover Stories.',
            'hero.line2'          => 'Explore Knowledge.',
            'hero.line3'          => 'Celebrate Ethiopia.',
            'hero.body'           => 'From rich Ethiopian heritage to global bestsellers, Gihonawit brings books closer to you — wherever you are.',
            'hero.cta'            => 'Shop Now',

            /* Category ribbon */
            'ribbon.more'         => 'More Categories',

            /* Sections */
            'section.featured'    => 'Featured Books',
            'section.view_all'    => 'View All',
            'section.voices'      => 'Ethiopian Voices, Global Perspectives',
            'section.voices_body' => 'Explore a diverse collection of Ethiopian and international books.',
            'section.voices_cta'  => 'Explore Ethiopian Books',
            'section.best_title'  => 'Bestsellers Worldwide',
            'section.best_body'   => 'Read the books everyone is talking about.',
            'section.best_cta'    => 'View Bestsellers',
            'section.new'         => 'New Arrivals',
            'section.categories'  => 'Browse by Category',
            'section.by_author'   => 'Featured Authors',

            /* Trust strip */
            'trust.delivery'      => 'Fast & Reliable Delivery',
            'trust.delivery_sub'  => 'Across Ethiopia and beyond',
            'trust.payment'       => 'Secure Payment',
            'trust.payment_sub'   => 'Your information is safe with us',
            'trust.selection'     => 'Wide Selection',
            'trust.selection_sub' => 'Ethiopian & International Books',
            'trust.local'         => 'Support Local',
            'trust.local_sub'     => 'Empowering Ethiopian Authors',

            /* Cards & catalogue */
            'card.add_to_cart'    => 'Add to Cart',
            'card.added'          => 'Added',
            'card.adding'         => 'Adding…',
            'card.out_of_stock'   => 'Out of Stock',
            'card.low_stock'      => 'Only %d left',
            'card.wish'           => 'Save to wishlist',
            'card.unwish'         => 'Remove from wishlist',
            'card.details'        => 'View Details',
            'card.featured_badge' => 'Featured',

            /* Catalogue page */
            'catalogue.all'       => 'All Books',
            'catalogue.results'   => '%d books found',
            'catalogue.no_results'=> 'No books matched your search.',
            'catalogue.sort'      => 'Sort by',
            'catalogue.filters'   => 'Filters',
            'catalogue.category'  => 'Category',
            'catalogue.author'    => 'Author',
            'catalogue.price'     => 'Price range',
            'catalogue.min'       => 'Min',
            'catalogue.max'       => 'Max',
            'catalogue.stock'     => 'Availability',
            'catalogue.in_stock'  => 'In stock only',
            'catalogue.apply'     => 'Apply filters',
            'catalogue.clear'     => 'Clear all',
            'catalogue.load_more' => 'Load more',
            'catalogue.loading'   => 'Loading books…',
            'catalogue.error'     => 'We could not load the catalogue. Please try again.',

            /* Book detail */
            'book.by'             => 'by',
            'book.description'    => 'Description',
            'book.specs'          => 'Book details',
            'book.related'        => 'You may also like',
            'book.genre'          => 'Genre',
            'book.price'          => 'Price',
            'book.availability'   => 'Availability',
            'book.sold'           => 'copies sold',
            'book.quantity'       => 'Quantity',
            'book.in_stock'       => 'In stock — dispatched within 24 hours.',
            'book.unavailable'    => 'Currently unavailable.',
            'book.free_ship'      => 'Free delivery on orders over %s.',

            /* Cart & checkout */
            'cart.title'          => 'Shopping Cart',
            'cart.empty'          => 'Your cart is empty',
            'cart.empty_body'     => 'Browse the catalogue and add a few titles to get started.',
            'cart.continue'       => 'Continue Shopping',
            'cart.browse'         => 'Browse Books',
            'cart.summary'        => 'Order Summary',
            'cart.subtotal'       => 'Subtotal',
            'cart.shipping'       => 'Delivery',
            'cart.free'           => 'Free',
            'cart.total'          => 'Total',
            'cart.checkout'       => 'Proceed to Checkout',
            'cart.remove'         => 'Remove',
            'cart.clear'          => 'Empty cart',
            'cart.items'          => '%d items',
            'cart.free_gap'       => 'Add %s more for free delivery.',
            'cart.free_ok'        => 'Free delivery unlocked.',
            'cart.updated'        => 'Cart updated.',
            'cart.line_title'     => 'Title',
            'cart.line_price'     => 'Price',
            'cart.line_qty'       => 'Qty',
            'cart.line_total'     => 'Total',

            'checkout.title'      => 'Checkout',
            'checkout.shipping_info' => 'Delivery details',
            'checkout.payment'    => 'Payment method',
            'checkout.cod'        => 'Cash on Delivery',
            'checkout.cod_sub'    => 'Pay the courier when your books arrive.',
            'checkout.card'       => 'Card (simulated)',
            'checkout.card_sub'   => 'Demo gateway — use 4242 4242 4242 4242. No card data is stored.',
            'checkout.place'      => 'Place Order',
            'checkout.secure'     => 'Your order is checked against live stock before it is accepted.',
            'checkout.full_name'  => 'Full name',
            'checkout.email'      => 'Email address',
            'checkout.phone'      => 'Phone number',
            'checkout.address'    => 'Delivery address',
            'checkout.city'       => 'City',
            'checkout.notes'      => 'Order notes (optional)',

            /* Orders */
            'order.confirmed'     => 'Thank you — your order is confirmed',
            'order.number'        => 'Order number',
            'order.placed'        => 'Placed on',
            'order.status'        => 'Status',
            'order.my'            => 'My Orders',
            'order.none'          => 'You have not placed any orders yet.',
            'order.items'         => 'Items',
            'order.deliver_to'    => 'Deliver to',
            'order.receipt'       => 'View receipt',
            'order.keep_shopping' => 'Continue shopping',

            /* Auth */
            'auth.sign_in'        => 'Sign In',
            'auth.sign_up'        => 'Create Account',
            'auth.sign_out'       => 'Sign Out',
            'auth.username'       => 'Username',
            'auth.email'          => 'Email address',
            'auth.password'       => 'Password',
            'auth.confirm'        => 'Confirm password',
            'auth.have_account'   => 'Already registered?',
            'auth.no_account'     => 'New to Gihonawit?',
            'auth.demo'           => 'Demo accounts',

            /* Wishlist */
            'wish.title'          => 'My Wishlist',
            'wish.empty'          => 'Your wishlist is empty',
            'wish.empty_body'     => 'Tap the heart on any book to save it here.',
            'wish.count'          => '%d saved',

            /* Generic */
            'common.from'         => 'from',
            'common.more'         => 'More',
            'common.language'     => 'Language',
            'common.back_home'    => 'Back to home',
            'common.not_found'    => 'We could not find that page',
            'common.required'     => 'required',

            /* Footer */
            'footer.about_title'  => 'About Gihonawit',
            'footer.about_body'   => 'An Ethiopian bookshop bringing local voices and world literature to readers everywhere.',
            'footer.shop'         => 'Shop',
            'footer.help'         => 'Customer Care',
            'footer.rights'       => 'All rights reserved.',
            'footer.ship_note'    => 'Free delivery on orders over %s',
        ],

        'am' => [
            'nav.home'            => 'መነሻ',
            'nav.books'           => 'መጻሕፍት',
            'nav.authors'         => 'ደራሲዎች',
            'nav.categories'      => 'ምድቦች',
            'nav.about'           => 'ስለ እኛ',
            'nav.contact'         => 'አግኙን',

            'header.search'       => 'መጻሕፍት፣ ደራሲዎች ወይም ርዕሶች ይፈልጉ…',
            'header.search_short' => 'መጻሕፍት ይፈልጉ…',
            'header.login'        => 'ግባ / ተመዝገብ',
            'header.wishlist'     => 'የምኞት ዝርዝር',
            'header.cart'         => 'ጋሪ',
            'header.menu'         => 'ዝርዝር',

            'hero.title'          => 'ጊሆናዊት',
            'hero.subtitle'       => 'የመጻሕፍት መደብር',
            'hero.line1'          => 'ታሪኮችን ያግኙ።',
            'hero.line2'          => 'እውቀትን ይዳስሱ።',
            'hero.line3'          => 'ኢትዮጵያን ያክብሩ።',
            'hero.body'           => 'ከኢትዮጵያ የበለጸገ ቅርስ ጀምሮ እስከ ዓለም አቀፍ ተወዳጅ መጻሕፍት፣ ጊሆናዊት መጻሕፍትን በሚገኙበት ቦታ ሁሉ ያቀርባል።',
            'hero.cta'            => 'አሁን ግዛ',

            'ribbon.more'         => 'ተጨማሪ ምድቦች',

            'section.featured'    => 'ተመራጭ መጻሕፍት',
            'section.view_all'    => 'ሁሉንም ይመልከቱ',
            'section.voices'      => 'የኢትዮጵያ ድምጾች፣ ዓለም አቀፍ አመለካከቶች',
            'section.voices_body' => 'የኢትዮጵያና ዓለም አቀፍ መጻሕፍትን የተለያየ ስብስብ ይዳስሱ።',
            'section.voices_cta'  => 'የኢትዮጵያ መጻሕፍትን ይዳስሱ',
            'section.best_title'  => 'ዓለም አቀፍ ተወዳጅ መጻሕፍት',
            'section.best_body'   => 'ሁሉም ሰው የሚናገርባቸውን መጻሕፍት ያንብቡ።',
            'section.best_cta'    => 'ተወዳጅ መጻሕፍትን ይመልከቱ',
            'section.new'         => 'አዲስ የገቡ',
            'section.categories'  => 'በምድብ ያስሱ',
            'section.by_author'   => 'ተመራጭ ደራሲዎች',

            'trust.delivery'      => 'ፈጣንና አስተማማኝ ማድረስ',
            'trust.delivery_sub'  => 'በኢትዮጵያ ውስጥና ከውጭ',
            'trust.payment'       => 'አስተማማኝ ክፍያ',
            'trust.payment_sub'   => 'የእርስዎ መረጃ ደህንነቱ የተጠበቀ ነው',
            'trust.selection'     => 'ሰፊ ምርጫ',
            'trust.selection_sub' => 'የኢትዮጵያና ዓለም አቀፍ መጻሕፍት',
            'trust.local'         => 'የአገር ውስጥ ድጋፍ',
            'trust.local_sub'     => 'ኢትዮጵያዊ ደራሲዎችን ማብቃት',

            'card.add_to_cart'    => 'ወደ ጋሪ ጨምር',
            'card.added'          => 'ተጨምሯል',
            'card.adding'         => 'በመጨመር ላይ…',
            'card.out_of_stock'   => 'ከክምችት ውጭ',
            'card.low_stock'      => '%d ብቻ ቀርቷል',
            'card.wish'           => 'በምኞት ዝርዝር ውስጥ አስቀምጥ',
            'card.unwish'         => 'ከምኞት ዝርዝር አስወግድ',
            'card.details'        => 'ዝርዝር ይመልከቱ',
            'card.featured_badge' => 'ተመራጭ',

            'catalogue.all'       => 'ሁሉም መጻሕፍት',
            'catalogue.results'   => '%d መጻሕፍት ተገኙ',
            'catalogue.no_results'=> 'ከፍለጋዎ ጋር የሚመሳሰል መጽሐፍ አልተገኘም።',
            'catalogue.sort'      => 'ደርድር በ',
            'catalogue.filters'   => 'ማጣሪያዎች',
            'catalogue.category'  => 'ምድብ',
            'catalogue.author'    => 'ደራሲ',
            'catalogue.price'     => 'የዋጋ ክልል',
            'catalogue.min'       => 'ዝቅተኛ',
            'catalogue.max'       => 'ከፍተኛ',
            'catalogue.stock'     => 'በክምችት መኖር',
            'catalogue.in_stock'  => 'በክምችት ያሉ ብቻ',
            'catalogue.apply'     => 'ማጣሪያ ተግብር',
            'catalogue.clear'     => 'ሁሉንም አጥፋ',
            'catalogue.load_more' => 'ተጨማሪ አሳይ',
            'catalogue.loading'   => 'መጻሕፍት በመጫን ላይ…',
            'catalogue.error'     => 'ካታሎጉን መጫን አልቻልንም። እባክዎ እንደገና ይሞክሩ።',

            'book.by'             => 'የ',
            'book.description'    => 'መግለጫ',
            'book.specs'          => 'የመጽሐፉ ዝርዝሮች',
            'book.related'        => 'እነዚህንም ይወዱ ይሆናል',
            'book.genre'          => 'ዘውግ',
            'book.price'          => 'ዋጋ',
            'book.availability'   => 'በክምችት መኖር',
            'book.sold'           => 'ተሽጧል',
            'book.quantity'       => 'ብዛት',
            'book.in_stock'       => 'በክምችት አለ — በ24 ሰዓት ውስጥ ይላካል።',
            'book.unavailable'    => 'በአሁኑ ጊዜ አይገኝም።',
            'book.free_ship'      => 'ከ%s በላይ ግዢ ላይ ነጻ ማድረስ።',

            'cart.title'          => 'የግዢ ጋሪ',
            'cart.empty'          => 'ጋሪዎ ባዶ ነው',
            'cart.empty_body'     => 'ካታሎጉን ይመልከቱና ጥቂት መጻሕፍት ይጨምሩ።',
            'cart.continue'       => 'ግዢውን ይቀጥሉ',
            'cart.browse'         => 'መጻሕፍትን ያስሱ',
            'cart.summary'        => 'የትዕዛዝ ማጠቃለያ',
            'cart.subtotal'       => 'ንዑስ ድምር',
            'cart.shipping'       => 'ማድረስ',
            'cart.free'           => 'ነጻ',
            'cart.total'          => 'ጠቅላላ',
            'cart.checkout'       => 'ወደ ክፍያ ቀጥል',
            'cart.remove'         => 'አስወግድ',
            'cart.clear'          => 'ጋሪውን አጥፋ',
            'cart.items'          => '%d ዕቃዎች',
            'cart.free_gap'       => 'ነጻ ማድረስ ለማግኘት %s ይጨምሩ።',
            'cart.free_ok'        => 'ነጻ ማድረስ ተከፍቷል።',
            'cart.updated'        => 'ጋሪው ተሻሽሏል።',
            'cart.line_title'     => 'ርዕስ',
            'cart.line_price'     => 'ዋጋ',
            'cart.line_qty'       => 'ብዛት',
            'cart.line_total'     => 'ጠቅላላ',

            'checkout.title'      => 'ክፍያ',
            'checkout.shipping_info' => 'የማድረሻ ዝርዝሮች',
            'checkout.payment'    => 'የክፍያ ዘዴ',
            'checkout.cod'        => 'ገንዘብ በማድረሻ ጊዜ',
            'checkout.cod_sub'    => 'መጻሕፍቱ ሲደርሱ ለማድረሻው ሰው ይክፈሉ።',
            'checkout.card'       => 'ካርድ (ምሳሌ)',
            'checkout.card_sub'   => 'የሙከራ ክፍያ — 4242 4242 4242 4242 ይጠቀሙ። የካርድ መረጃ አይቀመጥም።',
            'checkout.place'      => 'ትዕዛዝ አስገባ',
            'checkout.secure'     => 'ትዕዛዝዎ ከመቀበሉ በፊት ከክምችት ጋር ይረጋገጣል።',
            'checkout.full_name'  => 'ሙሉ ስም',
            'checkout.email'      => 'የኢሜይል አድራሻ',
            'checkout.phone'      => 'የስልክ ቁጥር',
            'checkout.address'    => 'የማድረሻ አድራሻ',
            'checkout.city'       => 'ከተማ',
            'checkout.notes'      => 'ተጨማሪ ማስታወሻ (አማራጭ)',

            'order.confirmed'     => 'እናመሰግናለን — ትዕዛዝዎ ተረጋግጧል',
            'order.number'        => 'የትዕዛዝ ቁጥር',
            'order.placed'        => 'የተሰጠበት ቀን',
            'order.status'        => 'ሁኔታ',
            'order.my'            => 'የእኔ ትዕዛዞች',
            'order.none'          => 'እስካሁን ትዕዛዝ አልሰጡም።',
            'order.items'         => 'ዕቃዎች',
            'order.deliver_to'    => 'የሚደርስበት',
            'order.receipt'       => 'ደረሰኝ ይመልከቱ',
            'order.keep_shopping' => 'ግዢውን ይቀጥሉ',

            'auth.sign_in'        => 'ይግቡ',
            'auth.sign_up'        => 'መዝገብ ይክፈቱ',
            'auth.sign_out'       => 'ይውጡ',
            'auth.username'       => 'የተጠቃሚ ስም',
            'auth.email'          => 'የኢሜይል አድራሻ',
            'auth.password'       => 'የይለፍ ቃል',
            'auth.confirm'        => 'የይለፍ ቃሉን ያረጋግጡ',
            'auth.have_account'   => 'ቀድመው ተመዝግበዋል?',
            'auth.no_account'     => 'ለጊሆናዊት አዲስ ነዎት?',
            'auth.demo'           => 'የሙከራ መዝገቦች',

            'wish.title'          => 'የምኞት ዝርዝሬ',
            'wish.empty'          => 'የምኞት ዝርዝርዎ ባዶ ነው',
            'wish.empty_body'     => 'ማንኛውንም መጽሐፍ ለማስቀመጥ የልብ ምልክቱን ይጫኑ።',
            'wish.count'          => '%d ተቀምጧል',

            'common.from'         => 'ከ',
            'common.more'         => 'ተጨማሪ',
            'common.language'     => 'ቋንቋ',
            'common.back_home'    => 'ወደ መነሻ ተመለስ',
            'common.not_found'    => 'ያንን ገጽ ማግኘት አልቻልንም',
            'common.required'     => 'አስፈላጊ',

            'footer.about_title'  => 'ስለ ጊሆናዊት',
            'footer.about_body'   => 'የአገር ውስጥ ድምጾችንና የዓለም ሥነ ጽሑፍን ለአንባቢዎች የሚያቀርብ የኢትዮጵያ የመጻሕፍት መደብር።',
            'footer.shop'         => 'ግዢ',
            'footer.help'         => 'የደንበኛ አገልግሎት',
            'footer.rights'       => 'መብቱ በህግ የተጠበቀ ነው።',
            'footer.ship_note'    => 'ከ%s በላይ ግዢ ላይ ነጻ ማድረስ',
        ],
    ];

    return $table;
}

/** Languages the storefront can render, as code => label. */
function languages(): array
{
    return ['en' => 'English', 'am' => 'አማርኛ'];
}

/** Active language code for this session. */
function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? 'en';

    return isset(languages()[$lang]) ? (string) $lang : 'en';
}

/** True when the storefront is being rendered in Amharic. */
function is_amharic(): bool
{
    return current_lang() === 'am';
}

/**
 * Translate a key into the active language.
 * Falls back to English, then to the key itself, so a missing string never
 * leaves a blank space in the interface.
 */
function t(string $key): string
{
    $table = translations();
    $lang  = current_lang();

    return $table[$lang][$key] ?? $table['en'][$key] ?? $key;
}

/** t() with sprintf() applied — for strings containing placeholders. */
function tf(string $key, ...$args): string
{
    return sprintf(t($key), ...$args);
}

/** Label for a category tile in the active language. */
function category_label(array $category): string
{
    return is_amharic() ? (string) $category['am'] : (string) $category['en'];
}
