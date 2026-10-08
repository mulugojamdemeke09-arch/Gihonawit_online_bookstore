# Gihonawit Online Bookstore

**Books for a Brighter Tomorrow** — a complete, production-ready Ethiopian bookshop built on a classic
web stack and designed to run locally inside XAMPP.

| Layer      | Technology                                                                 |
|------------|----------------------------------------------------------------------------|
| Markup     | Semantic HTML5                                                              |
| Styling    | A single hand-written `css/style.css` (flexbox + grid, no framework, no CDN) |
| Behaviour  | Vanilla JavaScript using the native **Fetch API** (`js/main.js`)             |
| Backend    | PHP 8 — modular, session-driven, PDO prepared statements                    |
| Database   | MySQL / MariaDB named `online_bookstore`, managed in phpMyAdmin             |
| Target     | `xampp/htdocs/gihonawit-bookstore/`                                          |

Brand palette: deep forest green `#0f3d2a`, ivory `#fcfbf7`, warm gold `#d4a35c`, near-black ink `#14181f`.

---

## 1. What you get

**Storefront**
- A masthead with the Gihonawit identity, a wide search bar with live suggestions, and utility hooks for
  Login / Sign Up, Wishlist and a cart badge that updates without a reload.
- A deep-green sub-navigation with Home, Books, Authors, Categories, About and Contact, plus an
  **English / አማርኛ language switch** carrying the Ethiopian flag.
- An immersive split hero: headline copy on the left with a gold "Shop Now" call to action, and a stack of
  genre spines (Ethiopian History · Amharic Literature · Global Classics · Modern World) on the right.
  The three dots rotate through three messages.
- The seven-tile **category ribbon** (plus *More Categories*), each tile filtering the catalogue.
- **Featured Books** in a five-across grid of product cards — cover block, title, author, category tag,
  price and a green "Add to Cart" footer — with the *Ethiopian Voices, Global Perspectives* and
  *Bestsellers Worldwide* promotional cards alongside.
- Bestseller ranking, new arrivals, author index and a trust strip.

**Commerce**
- Session cart available to guests, with prices and stock always re-read from MySQL.
- Quantity editing, removal and totals refreshed through the API without a page reload.
- Wishlist (also session-based) toggled by the heart on any card.
- Checkout with full server-side validation and a simulated card / cash-on-delivery payment step.
- Order confirmation invoice and a personal order history.

**Administration**
- `admin/dashboard.php`, gated by `require_admin()` before any HTML is emitted.
- Overview KPIs, a 7-day revenue chart, low-stock alerts and a bestseller table.
- Full inventory **CRUD** — create, read, update, delete and a homepage "featured" toggle.
- Order management with the three statuses **Pending → Shipped → Cancelled**.
- Customer list with order counts and lifetime value.

---

## 2. Requirements

* **XAMPP** with PHP **8.0+** (8.1 / 8.2 recommended) and MySQL / MariaDB **5.7+** / **10.4+**
* PHP extensions: `pdo_mysql`, `mbstring`, `json`, `session` (all on by default in XAMPP)
* Any modern browser

---

## 3. Installation

### Option A — the installer (recommended)

1. Copy the folder into your web root so the path matches exactly:

   ```
   Windows :  C:\xampp\htdocs\gihonawit-bookstore\
   macOS   :  /Applications/XAMPP/htdocs/gihonawit-bookstore/
   Linux   :  /opt/lampp/htdocs/gihonawit-bookstore/
   ```

2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

3. Visit <http://localhost/gihonawit-bookstore/setup.php> and press **Install the database**.

   The installer creates `online_bookstore`, imports `database/online_bookstore.sql`, and re-hashes the
   demo passwords with *your* PHP build so the logins below are guaranteed to work.

4. **Delete `setup.php`** when it reports success — it can drop the database.

### Option B — import by hand in phpMyAdmin

1. Start Apache and MySQL.
2. Open <http://localhost/phpmyadmin>.
3. **Import** → choose `database/online_bookstore.sql` → **Go**.
4. If your MySQL user or password differs from the XAMPP default, edit `config/db.php`.

### 3.1 Demo accounts

| Role          | Email                    | Password       |
|---------------|--------------------------|----------------|
| Administrator | `admin@gihonawit.test`   | `Admin@123`    |
| Customer      | `selam@gihonawit.test`   | `Customer@123` |
| Customer      | `dawit@gihonawit.test`   | `Customer@123` |

Both customers ship with order history so the dashboards are not empty. Change or delete them before this
runs anywhere other than `localhost`.

---

## 4. Folder structure

The tree below is exactly as specified, followed by the few additional files a working application needs.

```
xampp/htdocs/gihonawit-bookstore/
├── config/
│   └── db.php                 Secure PDO connectivity + query helpers
├── css/
│   └── style.css              Deep green, gold and off-white master stylesheet
├── js/
│   └── main.js                Fetch API engine: search, filtering, cart, wishlist
├── api/
│   └── search.php             JSON catalogue records for the Fetch API
├── admin/
│   └── dashboard.php          Role-restricted CRUD interface (books, orders, customers)
├── index.php                  Homepage: hero, ribbon, featured grid, promos, bestsellers
├── book-details.php           Individual product presentation
├── cart.php                   Session cart interface
├── checkout.php               Invoice builder — transactional order creation
│
├── api/cart.php               (added) cart mutations for the AJAX layer
├── api/wishlist.php           (added) wishlist toggle
├── includes/
│   ├── bootstrap.php          Single entry point: session, BASE_URL, wiring
│   ├── config.php             Site identity, category map, delivery rules, hero copy
│   ├── functions.php          Escaping, CSRF, auth guards, icons, Luhn
│   ├── lang.php               English / Amharic translation table
│   ├── cart.php               Cart + Wishlist classes (session)
│   ├── catalogue.php          One search implementation shared by page and API
│   ├── orders.php             Order reads, statuses, dashboard aggregates
│   ├── header.php             Masthead, navigation, language switch
│   ├── footer.php             Trust strip, footer, JavaScript bootstrap
│   └── book-card.php          The catalogue card component
├── authors.php                Browse by author
├── wishlist.php               Saved titles
├── my-orders.php              Customer order history
├── order-success.php          Invoice / receipt
├── login.php · register.php · logout.php
├── about.php · contact.php
├── assets/img/                hero.jpg · voices.jpg · bestsellers.jpg · favicon.svg
├── database/online_bookstore.sql
├── setup.php                  One-click installer  ← delete after running
├── .htaccess                  Hardening, caching, compression
└── README.md
```

Every page starts with one line — `require_once __DIR__ . '/includes/bootstrap.php';` — which wires up the
session, the `BASE_URL` constant and the PDO connection.

---

## 5. Database schema

Database **`online_bookstore`** — InnoDB (foreign keys + transactions), utf8mb4 (Amharic script).

| Table | Columns |
|---|---|
| `users` | `id` INT AI PK · `username` VARCHAR(60) · `email` VARCHAR(190) UNIQUE · `password_hash` VARCHAR(255) · `role` ENUM('customer','admin') · `created_at` TIMESTAMP |
| `books` | `id` INT AI PK · `title` VARCHAR(255) · `author` VARCHAR(160) · `genre` VARCHAR(80) · `price` DECIMAL(10,2) · `stock_quantity` INT · `description` TEXT · `cover_image_url` VARCHAR(500) · `is_featured` TINYINT(1) · `created_at` TIMESTAMP |
| `orders` | `id` INT AI PK · `user_id` INT **FK → users(id)** · `total_amount` DECIMAL(10,2) · `status` ENUM('Pending','Shipped','Cancelled') · `created_at` TIMESTAMP — plus `order_number`, `subtotal`, `shipping_amount`, `payment_method`, the `shipping_*` delivery fields and `updated_at` |
| `order_items` | `id` INT AI PK · `order_id` INT **FK → orders(id) ON DELETE CASCADE** · `book_id` INT **FK → books(id)** · `quantity` INT · `price_at_purchase` DECIMAL(10,2) |

> `orders.order_number` and the delivery/payment columns are additions: an invoice needs a human-readable
> reference and somewhere to record who it is going to. `books.created_at` powers "Newest first".

**Seed content:** 34 titles across the seven design categories — including the five on the mock-up
(*The History of Ethiopia* $12.99, *Half of a Yellow Sun* $11.99, *The Ethiopian Highlands* $14.99,
*Atomic Habits* $13.99, *1984* $10.99) — 3 accounts and 6 orders covering all three statuses.

> The catalogue is **demo content**. Titles marked as well-known works are paired with their real authors;
> the remaining Ethiopian entries are illustrative placeholders written for this design. Replace them with
> your real inventory before launch.

---

## 6. The interface, mapped to the specification

| Specification | Where it lives |
|---|---|
| Deep forest header `#0f3d2a`, ivory body `#fcfbf7`, gold accents `#d4a35c` | `css/style.css` → `:root` design tokens |
| Brand lock-up with tree-and-book mark and tagline | `brand_mark()` in `includes/functions.php`; `.brand` in the masthead |
| Wide search bar with matching by name, category or author | `.search` in `includes/header.php`; `api/search.php` matches title, author, genre **and** description |
| Login / Sign Up, Wishlist state, cart badge | `.utilities` in `includes/header.php`; badge counts from `Cart::count()` / `Wishlist::count()` |
| Sub-navbar with category routing and language toggle | `main_menu()` and `categories()` in `includes/config.php`; `includes/lang.php` |
| Split hero with gold "Shop Now" and genre spine stack | `.hero`, `.hero__stack`, `.spine` in `css/style.css` |
| Category ribbon with seven icons + More Categories | `.ribbon__grid` on `index.php`, driven by `categories()` |
| Featured Books cards with green "Add to Cart" footer | `includes/book-card.php` |
| *Ethiopian Voices* and *Bestsellers Worldwide* cards | `.promo--voices` / `.promo--bestsellers` on `index.php` |
| Trust strip (delivery, secure payment, wide selection, support local) | `includes/footer.php` |

---

## 7. JSON API

| Endpoint | Method | Purpose |
|---|---|---|
| `api/search.php?q=&category=&author=&min_price=&max_price=&in_stock=1&sort=&page=&per_page=` | GET | Catalogue search — returns `{ok,total,page,pages,items[]}` |
| `api/cart.php` | POST | `{action:add\|update\|remove\|clear\|summary, book_id, quantity}` → the re-priced cart |
| `api/wishlist.php` | POST | `{book_id}` → `{ok,saved,count,message}` |

POST bodies may be JSON or form-encoded; the CSRF token travels in the `X-CSRF-Token` header
(sent automatically by `GB.request`) or as a `csrf_token` field.

```bash
curl "http://localhost/gihonawit-bookstore/api/search.php?q=dune&sort=price_asc"
curl -X POST "http://localhost/gihonawit-bookstore/api/wishlist.php" \
     -H "Content-Type: application/json" -H "X-CSRF-Token: <token>" \
     -d '{"book_id":8}'
```

---

## 8. Security notes

* **SQL injection** — every query in the project is a PDO prepared statement with
  `ATTR_EMULATE_PREPARES = false`. `ORDER BY`, `LIMIT` and `OFFSET` are built from whitelists and integer
  casts, never from raw input. The only sanctioned way to reach the database is the `db_all() / db_one() /
  db_value() / db_exec()` helpers in `config/db.php`.
* **XSS** — output passes through `e()` (`htmlspecialchars`, `ENT_QUOTES`) and the JavaScript layer escapes
  every interpolated value before it touches `innerHTML`.
* **CSRF** — one token per session, compared with `hash_equals()` on every POST, forms and API calls alike.
* **Passwords** — `password_hash()` (bcrypt) on the way in, `password_verify()` on the way out, re-hashed
  automatically when the cost changes.
* **Sessions** — `HttpOnly`, `SameSite=Lax`, `Secure` under HTTPS, session id regenerated on login.
* **Authorisation** — `require_login()` and `require_admin()` gate the pages *and* every POST handler;
  invoices are ownership-checked so a customer cannot read someone else's order.
* **Overselling** — checkout locks the cart rows (`SELECT … FOR UPDATE`), re-prices from the database, and
  decrements stock behind a `stock_quantity >= qty` guard inside one transaction.
* **Payments** — simulated. Card numbers are Luhn-checked and **never stored or transmitted**; only the
  method (`card` / `cod`) is recorded. Do not point this at a real gateway.

---

## 9. Configuration

`config/db.php` reads environment variables first, then falls back to the XAMPP defaults:

| Variable | Default | Meaning |
|---|---|---|
| `GWB_DB_HOST` | `127.0.0.1` | MySQL host |
| `GWB_DB_PORT` | `3306` | MySQL port |
| `GWB_DB_NAME` | `online_bookstore` | Database name |
| `GWB_DB_USER` | `root` | MySQL user |
| `GWB_DB_PASS` | *(empty)* | MySQL password |
| `GWB_TZ` | `UTC` | PHP timezone |

Everything else is data in `includes/config.php`: the category ribbon (add a tile by adding one array
entry), delivery rules (`FREE_SHIPPING_THRESHOLD`, `FLAT_SHIPPING_RATE`, `MAX_QTY_PER_LINE`), store contact
details and the hero messages.

---

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| "Database connection failed" | Start **MySQL** in the XAMPP Control Panel, then run `setup.php` or import the dump. |
| Blank white page | `display_errors` is already on in `includes/bootstrap.php`; check `xampp/apache/logs/error.log`. |
| "Access denied for user 'root'@'localhost'" | Your MySQL root password is not empty — set `GWB_DB_PASS` or edit `config/db.php`. |
| Port 80 busy | Move Apache to 8080 in XAMPP and browse `http://localhost:8080/gihonawit-bookstore/`. |
| Port 3306 busy | Another MySQL is running; stop it or change the port in both XAMPP and `config/db.php`. |
| Styles or scripts missing | Confirm `css/style.css` and `js/main.js` exist; check `.htaccess` overrides are permitted. |
| Missing hero/promo imagery | The three photographs live in `assets/img/` (`hero.jpg`, `voices.jpg`, `bestsellers.jpg`). |
| Can't sign in | Run `setup.php` — it re-hashes the demo passwords with your local PHP build. |
| Apache error "Invalid command 'Options'" | Delete `.htaccess`; the application runs fine without it. |
| Amharic looks like boxes | Install an Ethiopic font (Windows: *Ebrima*/*Nyala*; macOS: *Kefa*; any: *Noto Sans Ethiopic*). |

### Before this goes further
1. Delete `setup.php`.
2. Change the demo passwords or remove the demo accounts.
3. Turn `display_errors` off in `includes/bootstrap.php`.
4. Replace the demo catalogue with your real inventory.
5. Have a native speaker review the Amharic column in `includes/lang.php` (see §11).

---

## 11. Notes and known limitations

* **Amharic translation** — the language switch is fully wired: every label in the shared chrome, the hero,
  the ribbon, buttons, section headings and the footer comes from `includes/lang.php`, with a complete
  Amharic column (149 keys, both languages in step — verified). It is a first pass, so treat it as a
  starting point for a native speaker's review rather than finished copy. Page-level prose on
  *About* and *Contact* is intentionally left in English; catalogue text is stored per book in the database.
* **Contact form** — validated server-side and written to the PHP error log. Connect it to `mail()`, a queue
  or a `messages` table in `contact.php` when you are ready to receive real mail.
* **Cover images** — no book images ship with the project, by design. When `books.cover_image_url` is empty
  the storefront renders a styled cover template from the title, author and genre (six palettes, chosen
  deterministically), so the grid always looks complete. Paste an `https://` URL into the admin book form to
  use real artwork instead.
* **Search** — uses indexed `LIKE` matching, which is plenty for a single-store catalogue. For tens of
  thousands of titles, switch to a MySQL `FULLTEXT` index; only `includes/catalogue.php` would need changing.

---

## 12. What to try first

1. Open <http://localhost/gihonawit-bookstore/> and watch the hero dots rotate.
2. Type "amharic" into the search bar — suggestions appear as you type, without a reload.
3. Click the **Ethiopian History & Culture** ribbon tile, then sort by price.
4. Add a book to the cart, change the quantity (totals update live), then check out with
   `4242 4242 4242 4242`, any future expiry and any 3-digit code — or a card ending `0000` for the
   declined path.
5. Switch the language to **አማርኛ** and watch the whole interface follow.
6. Sign in as `admin@gihonawit.test` / `Admin@123`, open **Inventory**, edit a title, feature it, and see it
   appear under *Featured Books* on the homepage. Then move an order from **Pending** to **Shipped**.

---

*Built as a self-contained teaching and demonstration project. MIT-style: use it, change it, ship it.*
