-- ===========================================================================
--  GIHONAWIT ONLINE BOOKSTORE  —  database schema + sample catalogue
--  "Books for a Brighter Tomorrow"
-- ===========================================================================
--  Import directly into phpMyAdmin:   http://localhost/phpmyadmin
--      Import  ->  Choose file: online_bookstore.sql  ->  Go
--  or from a terminal:
--      mysql -u root < database/online_bookstore.sql
--
--  Engine  : InnoDB   (required for FOREIGN KEYs and checkout transactions)
--  Charset : utf8mb4  (full Unicode — required for Amharic / Ethiopic script)
--
--  Tables  : users · books · orders · order_items
--  Accounts: seeded passwords are bcrypt hashes produced by PHP's
--            password_hash($pw, PASSWORD_DEFAULT)  ->  $2y$10$...
--            setup.php re-hashes them locally, so they always work.
-- ===========================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `online_bookstore`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `online_bookstore`;

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------------------
-- users — customers and administrators
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(60)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- books — the catalogue
-- ---------------------------------------------------------------------------
CREATE TABLE `books` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `title`           VARCHAR(255)  NOT NULL,
  `author`          VARCHAR(160)  NOT NULL,
  `genre`           VARCHAR(80)   NOT NULL,
  `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity`  INT           NOT NULL DEFAULT 0,
  `description`     TEXT          NULL,
  `cover_image_url` VARCHAR(500)  NULL,
  `is_featured`     TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_books_title`    (`title`),
  KEY `idx_books_author`   (`author`),
  KEY `idx_books_genre`    (`genre`),
  KEY `idx_books_price`    (`price`),
  KEY `idx_books_featured` (`is_featured`),
  CONSTRAINT `chk_books_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_books_stock` CHECK (`stock_quantity` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orders — one row per placed order (status: Pending / Shipped / Cancelled)
-- ---------------------------------------------------------------------------
CREATE TABLE `orders` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `order_number`      VARCHAR(32)   NOT NULL,
  `user_id`           INT           NULL,
  `total_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`            ENUM('Pending','Shipped','Cancelled') NOT NULL DEFAULT 'Pending',
  `subtotal`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`    ENUM('cod','card') NOT NULL DEFAULT 'cod',
  `shipping_name`     VARCHAR(120)  NOT NULL,
  `shipping_email`    VARCHAR(190)  NOT NULL,
  `shipping_phone`    VARCHAR(40)   NULL,
  `shipping_address`  VARCHAR(255)  NOT NULL,
  `shipping_city`     VARCHAR(80)   NOT NULL,
  `notes`             TEXT          NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_user`   (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_date`   (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_items — order lines. price_at_purchase freezes the price paid, so a
-- later price change never rewrites historical invoices.
-- ---------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `order_id`          INT           NOT NULL,
  `book_id`           INT           NULL,
  `quantity`          INT           NOT NULL DEFAULT 1,
  `price_at_purchase` DECIMAL(10,2) NOT NULL,
  KEY `idx_items_order` (`order_id`),
  KEY `idx_items_book`  (`book_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_book` FOREIGN KEY (`book_id`)
      REFERENCES `books` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_items_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
--  SEED DATA
-- ===========================================================================
--  DEMO ACCOUNTS  —  change these before the app leaves localhost
--    admin@gihonawit.test   / Admin@123      role: admin
--    selam@gihonawit.test   / Customer@123   role: customer
--    dawit@gihonawit.test   / Customer@123   role: customer
--
--  NOTE ON THE CATALOGUE: this is demo content for a UI prototype. Titles
--  marked with a bullet are well-known published works and are paired with
--  their real authors; the remaining Ethiopian entries are illustrative
--  placeholder records created for this storefront design and do not refer
--  to actual publications. Swap in your real inventory before going live.
-- ===========================================================================

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@gihonawit.test', '$2y$10$.04K.wf6EpmDNR.daeaW0ejse4Kcvz1SqNSSFOLHfEiEKa/irm5bC', 'admin',    '2026-01-04 08:30:00'),
(2, 'selam', 'selam@gihonawit.test', '$2y$10$o8CiMGZrbIqASVxKsBwuDuJwczgO4HqedA5p7s5KX5kEGUhNBXSSO', 'customer', '2026-02-08 11:15:00'),
(3, 'dawit', 'dawit@gihonawit.test', '$2y$10$7tKXWTPxNkjhqnX87Z0w1u.L70ImpPRNvjnfx.P4mZbgcE9qzBtoe', 'customer', '2026-03-19 16:40:00');

-- ---------------------------------------------------------------------------
-- Catalogue · genre values map onto the seven category ribbons of the UI
--   Ethiopian History & Culture        -> Ethiopian History · Ethiopian Culture
--   Amharic Literature                 -> Amharic Literature
--   International Fiction & Classics   -> Fiction · Classics
--   Education & Academic               -> Education · Academic
--   Self-Help & Personal Development   -> Self-Help · Personal Development
--   Science & Technology               -> Science · Technology
--   Arts & Lifestyle                   -> Arts · Lifestyle
-- ---------------------------------------------------------------------------
INSERT INTO `books`
(`id`, `title`, `author`, `genre`, `price`, `stock_quantity`, `description`, `cover_image_url`, `is_featured`, `created_at`) VALUES
( 1, 'የኢትዮጵያ ታሪክ (The History of Ethiopia)', 'Getachew Haile', 'Ethiopian History', 12.99, 24, 'A sweeping account of Ethiopia''s highland kingdoms, from the Aksumite empire and the Zagwe dynasty to the Solomonic line and the modern state.', NULL, 1, '2026-01-06 09:00:00'),
( 2, 'የኢትዮጵያ ተራሮች (The Ethiopian Highlands)', 'Mengesha Belay', 'Ethiopian Culture', 14.99, 15, 'Portrait of the highland communities, terraced farmland and seasonal festivals that shape life above two thousand metres.', NULL, 1, '2026-01-06 09:10:00'),
( 3, 'Ethiopia: Land of Origins', 'Hana Girma', 'Ethiopian History', 18.75, 11, 'From the earliest hominin fossils in the Afar depression to the rock-hewn churches of Lalibela — an illustrated journey through the places that shaped the region.', NULL, 0, '2026-01-06 09:20:00'),
( 4, 'The Great Rift Valley', 'Tewodros Kebede', 'Ethiopian History', 16.50, 8, 'A traveller''s history of the valley that splits the highlands in two, following the lakes, salt flats and people along its length.', NULL, 0, '2026-01-07 10:00:00'),
( 5, 'Lalibela: The Rock-Hewn Churches', 'Selam Bekele', 'Ethiopian Culture', 19.99, 6, 'A photographic study of the eleven monolithic churches of Lalibela, with notes on the masons, the liturgy and the annual pilgrimages.', NULL, 1, '2026-01-07 10:15:00'),
( 6, 'The Aksumite Civilisation', 'Alemayehu Tesfaye', 'Ethiopian History', 15.25, 13, 'Obelisks, coinage and trade routes: how Aksum became one of the great commercial powers of the ancient world.', NULL, 0, '2026-01-08 08:30:00'),
( 7, 'Ethiopian Coffee: Bean to Ceremony', 'Marta Assefa', 'Lifestyle', 13.99, 15, 'The story of coffee in the land where it was first cultivated, plus a practical guide to roasting, brewing and the jebena ceremony.', NULL, 0, '2026-01-08 08:45:00'),
( 8, 'ፍቅር እስከ መቃብር (Love unto the Grave)', 'Haddis Alemayehu', 'Amharic Literature', 13.50, 12, 'One of the landmark Amharic novels of the twentieth century: love, obligation and the pull of tradition in the Ethiopian countryside.', NULL, 1, '2026-01-09 09:00:00'),
( 9, 'ኦሮማይ (Oromay)', 'Bealu Girma', 'Amharic Literature', 14.25, 9, 'A novel of journalism, war and conscience, written with an unflinching eye on the machinery of power.', NULL, 0, '2026-01-09 09:15:00'),
(10, 'Half of a Yellow Sun', 'Chimamanda Ngozi Adichie', 'Fiction', 11.99, 18, 'Love and loyalty tested by war in 1960s Nigeria, told through the lives of a houseboy, a professor and his revolutionary partner.', NULL, 1, '2026-01-09 09:30:00'),
(11, 'Things Fall Apart', 'Chinua Achebe', 'Classics', 11.50, 22, 'The classic account of an Igbo village elder whose world is unmade by the arrival of missionaries and colonial administration.', NULL, 0, '2026-01-10 11:00:00'),
(12, '1984', 'George Orwell', 'Classics', 10.99, 40, 'Winston Smith edits history for a living while the Party watches every thought. The definitive novel of total surveillance.', NULL, 1, '2026-01-10 11:10:00'),
(13, 'Pride and Prejudice', 'Jane Austen', 'Classics', 9.99, 26, 'Elizabeth Bennet must navigate family expectation, pride and a most inconvenient attraction in Austen''s sharpest social comedy.', NULL, 0, '2026-01-10 11:20:00'),
(14, 'To Kill a Mockingbird', 'Harper Lee', 'Classics', 12.25, 17, 'Scout Finch watches her father defend an innocent man in a small Southern town, and learns what courage actually costs.', NULL, 0, '2026-01-11 09:00:00'),
(15, 'The Shadow King', 'Maaza Mengiste', 'Fiction', 15.99, 14, 'Ethiopian women take up arms against the Italian invasion of 1935, in a novel about war, photography and remembrance.', NULL, 1, '2026-01-11 09:15:00'),
(16, 'Beneath the Lion''s Gaze', 'Maaza Mengiste', 'Fiction', 14.50, 10, 'A family caught between revolution and the Derg, set in Addis Ababa during the last days of Haile Selassie''s reign.', NULL, 0, '2026-01-11 09:30:00'),
(17, 'Cutting for Stone', 'Abraham Verghese', 'Fiction', 16.25, 13, 'Twin brothers, a mission hospital in Addis Ababa and a lifetime of medicine, distance and family secrets.', NULL, 0, '2026-01-12 10:00:00'),
(18, 'The Beautiful Things That Heaven Bears', 'Dinaw Mengestu', 'Fiction', 13.75, 9, 'An Ethiopian shopkeeper in Washington DC, holding on to a country he left behind seventeen years earlier.', NULL, 0, '2026-01-12 10:20:00'),
(19, 'Dune', 'Frank Herbert', 'Fiction', 17.99, 16, 'Politics, ecology, prophecy and giant sandworms collide on the desert planet Arrakis, the only source of the spice.', NULL, 0, '2026-01-12 10:40:00'),
(20, 'The Alchemist', 'Paulo Coelho', 'Fiction', 10.99, 30, 'A shepherd boy travels from Andalusia to the Egyptian pyramids chasing a recurring dream and a fable about following your personal legend.', NULL, 0, '2026-01-13 08:00:00'),
(21, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Academic', 19.99, 12, 'How an unremarkable ape came to dominate the planet through shared fictions: money, nations, gods and corporations.', NULL, 0, '2026-01-13 08:20:00'),
(22, 'The Fate of Africa', 'Martin Meredith', 'Academic', 21.00, 5, 'A history of the continent since independence: the hopes of 1960 and the decades of coups, debt and fragile recovery that followed.', NULL, 0, '2026-01-13 08:40:00'),
(23, 'Long Walk to Freedom', 'Nelson Mandela', 'Education', 17.50, 7, 'The autobiography of a life given to the struggle against apartheid, written with extraordinary restraint and clarity.', NULL, 0, '2026-01-14 09:00:00'),
(24, 'Atomic Habits', 'James Clear', 'Self-Help', 13.99, 32, 'A practical framework for building good habits and dismantling bad ones through tiny, compounding changes.', NULL, 1, '2026-01-14 09:20:00'),
(25, 'Deep Work', 'Cal Newport', 'Personal Development', 13.25, 20, 'Rules for focused success in a distracted world — why concentration is becoming rare and therefore valuable.', NULL, 0, '2026-01-14 09:40:00'),
(26, 'The 7 Habits of Highly Effective People', 'Stephen R. Covey', 'Self-Help', 12.75, 18, 'A principle-centred approach to personal and professional effectiveness, built on character rather than technique.', NULL, 0, '2026-01-15 10:00:00'),
(27, 'Thinking, Fast and Slow', 'Daniel Kahneman', 'Science', 16.99, 11, 'The Nobel laureate''s tour of the two systems that drive how we think — and the biases baked into both.', NULL, 0, '2026-01-15 10:20:00'),
(28, 'Guns, Germs, and Steel', 'Jared Diamond', 'Science', 18.25, 8, 'Why history unfolded so differently on different continents: geography, crops, animals and the accidents of diffusion.', NULL, 0, '2026-01-15 10:40:00'),
(29, 'Clean Code', 'Robert C. Martin', 'Technology', 24.99, 9, 'A handbook of software craftsmanship: naming, functions, formatting and the discipline of writing code humans can read.', NULL, 0, '2026-01-16 11:00:00'),
(30, 'The Pragmatic Programmer', 'Andrew Hunt and David Thomas', 'Technology', 27.50, 6, 'Timeless advice for developers: DRY, orthogonality, tracer bullets and taking responsibility for your craft.', NULL, 0, '2026-01-16 11:20:00'),
(31, 'The Design of Everyday Things', 'Don Norman', 'Arts', 17.75, 7, 'Why some doors, kettles and apps are maddening — and how good design makes itself invisible.', NULL, 0, '2026-01-17 09:00:00'),
(32, 'Ways of Seeing', 'John Berger', 'Arts', 12.50, 10, 'Seven essays on how reproduction, advertising and tradition change what we actually see when we look at an image.', NULL, 0, '2026-01-17 09:20:00'),
(33, 'The Story of Art', 'E. H. Gombrich', 'Arts', 29.99, 4, 'The classic one-volume introduction to the history of art, told as a continuous story from cave painting to modernism.', NULL, 0, '2026-01-17 09:40:00'),
(34, 'Ethiopian Textiles and Craft', 'Genet Wolde', 'Lifestyle', 22.50, 0, 'A guide to the weaving, basketry and leatherwork traditions of the Ethiopian highlands, with photographs of working studios.', NULL, 0, '2026-01-18 10:00:00');

-- ---------------------------------------------------------------------------
-- Sample orders. created_at is relative to import time so the admin dashboard
-- always has recent activity to display.
--   shipping = 0.00 when subtotal >= 35.00, otherwise 4.99
-- ---------------------------------------------------------------------------
INSERT INTO `orders`
(`id`, `order_number`, `user_id`, `subtotal`, `shipping_amount`, `total_amount`, `status`,
 `payment_method`, `shipping_name`, `shipping_email`, `shipping_phone`,
 `shipping_address`, `shipping_city`, `notes`, `created_at`) VALUES
(1, 'GWB-000101', 2, 24.98, 4.99, 29.97, 'Pending',   'card', 'Selam Tesfaye', 'selam@gihonawit.test', '+251 91 155 0134',
    'Bole Road, Friendship Building 4th floor', 'Addis Ababa', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 'GWB-000102', 2, 38.97, 0.00, 38.97, 'Shipped',   'cod',  'Selam Tesfaye', 'selam@gihonawit.test', '+251 91 155 0134',
    'Bole Road, Friendship Building 4th floor', 'Addis Ababa', 'Please call on arrival.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 'GWB-000103', 3, 52.49, 0.00, 52.49, 'Shipped',   'card', 'Dawit Bekele',  'dawit@gihonawit.test', '+251 92 233 0198',
    'Kazanchis, Lion Insurance Building', 'Addis Ababa', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'GWB-000104', 3, 15.99, 4.99, 20.98, 'Pending',   'cod',  'Dawit Bekele',  'dawit@gihonawit.test', '+251 92 233 0198',
    'Kazanchis, Lion Insurance Building', 'Addis Ababa', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'GWB-000105', 2, 33.49, 4.99, 38.48, 'Cancelled', 'card', 'Selam Tesfaye', 'selam@gihonawit.test', '+251 91 155 0134',
    'Bole Road, Friendship Building 4th floor', 'Addis Ababa', 'Customer cancelled before dispatch.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 'GWB-000106', 3, 45.97, 0.00, 45.97, 'Pending',   'card', 'Dawit Bekele',  'dawit@gihonawit.test', '+251 92 233 0198',
    'Kazanchis, Lion Insurance Building', 'Addis Ababa', 'Gift wrap if possible.', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `order_items` (`order_id`, `book_id`, `quantity`, `price_at_purchase`) VALUES
(1, 24, 1, 13.99),
(1, 12, 1, 10.99),
(2, 10, 2, 11.99),
(2,  2, 1, 14.99),
(3, 30, 1, 27.50),
(3, 29, 1, 24.99),
(4, 15, 1, 15.99),
(5,  8, 1, 13.50),
(5, 21, 1, 19.99),
(6,  1, 2, 12.99),
(6,  5, 1, 19.99);

-- ===========================================================================
--  Done.
--    Storefront : http://localhost/gihonawit-bookstore/
--    Admin      : http://localhost/gihonawit-bookstore/admin/dashboard.php
--    Sign in    : admin@gihonawit.test / Admin@123
-- ===========================================================================
