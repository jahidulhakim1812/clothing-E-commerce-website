-- ============================================================
-- Stitch & Souls — Handmade With Heart
-- Database Schema
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `stitchsouls_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stitchsouls_db`;

-- ------------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `status` ENUM('active','blocked') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `employees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','employee') NOT NULL DEFAULT 'employee',
  `photo` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `cost_price` DECIMAL(10,2) DEFAULT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `image` VARCHAR(255) DEFAULT NULL,
  `gallery` TEXT DEFAULT NULL,
  `size_options` VARCHAR(255) DEFAULT NULL,
  `size_chart` TEXT DEFAULT NULL COMMENT 'JSON map of size -> {chest,waist,hip} measurements, set per-product from the admin form',
  `color_options` VARCHAR(255) DEFAULT NULL,
  `featured` TINYINT(1) DEFAULT 0,
  `is_bestseller` TINYINT(1) DEFAULT 0,
  `is_flash_sale` TINYINT(1) DEFAULT 0,
  `flash_sale_end` DATETIME DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_by` INT(11) DEFAULT NULL COMMENT 'Employee who added this product',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_employee` FOREIGN KEY (`created_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT(11) DEFAULT NULL,
  `guest_name` VARCHAR(150) DEFAULT NULL,
  `guest_email` VARCHAR(150) DEFAULT NULL,
  `guest_phone` VARCHAR(30) DEFAULT NULL,
  `shipping_address` TEXT NOT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `shipping_zone` ENUM('inside_dhaka','outside_dhaka') NOT NULL DEFAULT 'inside_dhaka',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cod','card','mobile_banking') DEFAULT 'cod',
  `payment_status` ENUM('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `order_status` ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `stitch_request` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Customer asked us to stitch the dress for them',
  `handled_by` INT(11) DEFAULT NULL COMMENT 'Employee who last processed/updated this order',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `handled_by` (`handled_by`),
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_employee` FOREIGN KEY (`handled_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `size` VARCHAR(30) DEFAULT NULL,
  `color` VARCHAR(30) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `inventory_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `change_qty` INT(11) NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `employee_id` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_log_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Shop By Video Categories — a fully independent section (not part of the
-- shop's product categories). Only the Shop By Video area can create these
-- and only the Shop By Video area can upload the videos inside them.
CREATE TABLE `video_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `thumbnail` VARCHAR(255) DEFAULT NULL COMMENT 'Circle thumbnail shown in the Shop By Video category row',
  `sort_order` INT(11) DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_videocat_employee` FOREIGN KEY (`created_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Shop By Video — the actual vertical video clips. Uploaded ONLY from this
-- section's admin page (never from the Products page). Each video may
-- optionally link to a product for the "Shop Now" button inside the story
-- player, or to a custom link, or to nothing at all.
CREATE TABLE `shop_videos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(200) DEFAULT NULL COMMENT 'Caption shown over the video in the story player',
  `video` VARCHAR(255) NOT NULL COMMENT 'Vertical (portrait, 9:16) showcase video file',
  `cover_image` VARCHAR(255) DEFAULT NULL COMMENT 'Optional custom cover shown before playback; falls back to the linked product image',
  `product_id` INT(11) DEFAULT NULL COMMENT 'Optional linked product for the Shop Now button',
  `shop_link` VARCHAR(255) DEFAULT NULL COMMENT 'Optional custom URL for the Shop Now button when no product is linked',
  `sort_order` INT(11) DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_shopvideo_category` FOREIGN KEY (`category_id`) REFERENCES `video_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shopvideo_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shopvideo_employee` FOREIGN KEY (`created_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `hero_slides` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `button_text` VARCHAR(60) DEFAULT 'Shop Now',
  `button_link` VARCHAR(255) DEFAULT '#',
  `sort_order` INT(11) DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `customer_id` INT(11) DEFAULT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(150) DEFAULT NULL,
  `rating` TINYINT(1) NOT NULL DEFAULT 5,
  `comment` TEXT DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT(11) DEFAULT NULL COMMENT 'Employee/admin who approved or rejected this review',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_employee` FOREIGN KEY (`reviewed_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` ENUM('order','customer','low_stock','system') NOT NULL DEFAULT 'system',
  `title` VARCHAR(200) NOT NULL,
  `message` VARCHAR(255) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
CREATE TABLE `site_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Employees (password = admin123)
INSERT INTO `employees` (`name`,`email`,`password`,`role`,`status`) VALUES
('Store Owner','superadmin@stitchandsouls.com','$2y$10$Yn7YEBemxS1AhFAELatJ9uhnzxFATjFsaaCr6RRGuqPGuGtcNPEOW','super_admin','active'),
('Studio Assistant','staff@stitchandsouls.com','$2y$10$Yn7YEBemxS1AhFAELatJ9uhnzxFATjFsaaCr6RRGuqPGuGtcNPEOW','employee','active');

-- Categories
INSERT INTO `categories` (`name`,`description`,`image`,`status`) VALUES
('Kameez Sets','Hand-embroidered three-piece kameez sets','category-kameez.jpg','active'),
('Two-Piece','Comfortable hand-stitched two-piece sets','category-twopiece.jpg','active'),
('Sharee','Handwoven and hand-printed sharees','category-sharee.jpg','active'),
('Gowns','Flowing hand-finished evening gowns','category-gowns.jpg','active'),
('Kaftan','Relaxed hand-embroidered kaftans','category-kaftan.jpg','active'),
('Cord-Sets','Matching hand-stitched co-ord sets','category-cordset.jpg','active'),
('Kids Wear','Soft handmade essentials for little ones','category-kids.jpg','active'),
('Purse & Bags','Hand-embroidered purses and tote bags','category-purse.jpg','active');

-- Products (cost_price included for profit/loss reporting)
INSERT INTO `products` (`category_id`,`name`,`description`,`price`,`discount_price`,`cost_price`,`sku`,`stock`,`image`,`featured`,`is_bestseller`,`is_flash_sale`,`flash_sale_end`,`status`) VALUES
(1,'Rosewood Kameez Set','Hand-embroidered three-piece kameez set in soft rosewood tones.',2650.00,1950.00,1450.00,'KAM-01',22,'product-01.jpg',1,1,1,DATE_ADD(NOW(), INTERVAL 3 DAY),'active'),
(1,'Meher Kameez Set','Delicately hand-stitched kameez set with thread detailing.',2850.00,2100.00,1550.00,'KAM-02',14,'product-02.jpg',0,0,0,NULL,'active'),
(2,'Blush Comfort Two-Piece','Soft, breathable hand-stitched two-piece in blush pink.',1990.00,1490.00,1050.00,'TWP-01',30,'product-03.jpg',1,1,1,DATE_ADD(NOW(), INTERVAL 2 DAY),'active'),
(2,'Ivory Everyday Two-Piece','Everyday hand-finished two-piece with hand-block print.',1750.00,NULL,950.00,'TWP-02',18,'product-04.jpg',0,1,0,NULL,'active'),
(3,'Golden Thread Sharee','Handwoven sharee with fine golden thread border.',3250.00,2450.00,1750.00,'SHR-01',25,'product-05.jpg',1,0,1,DATE_ADD(NOW(), INTERVAL 4 DAY),'active'),
(3,'Midnight Bloom Sharee','Hand-printed floral sharee in deep midnight tones.',2890.00,2150.00,1550.00,'SHR-02',40,'product-06.jpg',0,0,0,NULL,'active'),
(4,'Moonlight Evening Gown','Hand-finished flowing gown for special occasions.',3450.00,NULL,1850.00,'GWN-01',20,'product-07.jpg',1,0,0,NULL,'active'),
(4,'Aurora Party Gown','Hand-embellished party gown with soft draping.',3750.00,2890.00,2050.00,'GWN-02',16,'product-08.jpg',0,1,0,NULL,'active'),
(5,'Desert Rose Kaftan','Relaxed hand-embroidered kaftan, perfect for summer.',2250.00,1650.00,1200.00,'KAF-01',35,'product-09.jpg',1,1,1,DATE_ADD(NOW(), INTERVAL 5 DAY),'active'),
(5,'Coastal Breeze Kaftan','Lightweight hand-stitched kaftan in coastal prints.',2150.00,NULL,1150.00,'KAF-02',12,'product-10.jpg',0,0,0,NULL,'active'),
(6,'Terracotta Cord-Set','Matching hand-stitched co-ord set in warm terracotta.',2450.00,1850.00,1300.00,'COR-01',28,'product-11.jpg',1,1,0,NULL,'active'),
(6,'Sage Garden Cord-Set','Hand-finished co-ord set with sage embroidery accents.',2650.00,NULL,1400.00,'COR-02',9,'product-12.jpg',0,0,0,NULL,'active'),
(7,'Little Blossom Frock','Hand-stitched party frock for little ones.',1450.00,1050.00,750.00,'KID-01',10,'product-13.jpg',1,1,1,DATE_ADD(NOW(), INTERVAL 3 DAY),'active'),
(7,'Sunshine Kids Set','Soft hand-finished two-piece set for kids.',1250.00,950.00,650.00,'KID-02',15,'product-14.jpg',0,0,0,NULL,'active'),
(8,'Embroidered Boho Tote','Hand-embroidered canvas tote for everyday carry.',1350.00,NULL,700.00,'BAG-01',18,'product-15.jpg',1,0,0,NULL,'active'),
(8,'Petal Stitched Purse','Hand-stitched purse with floral thread detailing.',950.00,690.00,480.00,'BAG-02',45,'product-16.jpg',0,1,1,DATE_ADD(NOW(), INTERVAL 6 DAY),'active');

-- Hero Slides
INSERT INTO `hero_slides` (`title`,`subtitle`,`image`,`button_text`,`button_link`,`sort_order`,`status`) VALUES
('New Season, New Stitches','Hand-stitched kameez sets & two-pieces, freshly dropped','slide-1.jpg','Shop Now','products.php',1,'active'),
('Evening Elegance','Hand-finished gowns and kaftans for special occasions','slide-2.jpg','Explore Collection','products.php?category=4',2,'active'),
('Little Ones, Big Style','Soft handmade essentials for kids','slide-3.jpg','Shop Kids Wear','products.php?category=7',3,'active');

-- Shop By Video Categories (fully independent section — videos are uploaded only from the Shop By Video admin page)
INSERT INTO `video_categories` (`name`, `thumbnail`, `sort_order`, `status`) VALUES
('Wedding Edit', NULL, 1, 'active'),
('Everyday Wear', NULL, 2, 'active'),
('Festive Picks', NULL, 3, 'active');

-- No sample rows for `shop_videos` — add real vertical clips from Admin → Shop By Video.

-- Site Settings
INSERT INTO `site_settings` (`setting_key`,`setting_value`) VALUES
('site_name','Stitch & Souls'),
('site_tagline','Handmade With Heart'),
('site_email','hello@stitchandsouls.com'),
('site_phone','+880 1700-000000'),
('site_address','Dhaka, Bangladesh'),
('currency_symbol','৳'),
('shipping_fee_inside_dhaka','70'),
('shipping_fee_outside_dhaka','130'),
('facebook_link','https://facebook.com'),
('instagram_link','https://instagram.com'),
('announcement_text','New handmade drops every week — Free shipping over ৳1500');

-- Sample Customers (password for all: customer123)
INSERT INTO `customers` (`name`,`email`,`phone`,`password`,`address`,`status`,`created_at`) VALUES
('Ayesha Islam','ayesha@example.com','01711000000','$2y$10$RUfFIAm7TcNFIvUUTAwmAuRUWMdOd2siBbdbCcIwmGa5EuX7SRIn6','House 7, Road 2, Bashundhara, Dhaka','active', DATE_SUB(NOW(), INTERVAL 40 DAY)),
('Nabila Chowdhury','nabila@example.com','01822000000','$2y$10$pvFFp4t0q4cNAHrbT6Se2.6gtAZCK16MDh6pYUTsVuyVKloTTrSkC','Flat 3B, Zindabazar, Sylhet','active', DATE_SUB(NOW(), INTERVAL 25 DAY)),
('Rezaul Karim','rezaul@example.com','01933000000','$2y$10$pvFFp4t0q4cNAHrbT6Se2.6gtAZCK16MDh6pYUTsVuyVKloTTrSkC','45 GEC Circle, Chattogram','active', DATE_SUB(NOW(), INTERVAL 12 DAY)),
('Sabrina Yasmin','sabrina@example.com','01644000000','$2y$10$pvFFp4t0q4cNAHrbT6Se2.6gtAZCK16MDh6pYUTsVuyVKloTTrSkC','Sector 11, Uttara, Dhaka','active', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ============================================================
-- Sample Orders + Order Items + Inventory Logs
-- ============================================================

-- Order 1 — Guest, Dhaka, Delivered & Paid
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260625A1B2C3', NULL, 'Farhana Kabir', 'farhana.k@example.com', '01755000111', 'House 22, Road 9, Dhanmondi, Dhaka', 'Dhaka', 'inside_dhaka', 2640.00, 70.00, 2710.00, 'cod', 'paid', NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 28 DAY));
SET @order1 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order1, 1, 'Rosewood Kameez Set', 'product-01.jpg', 'M', NULL, 1950.00, 1, 1950.00),
(@order1, 16, 'Petal Stitched Purse', 'product-16.jpg', NULL, NULL, 690.00, 1, 690.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(1, -1, 'Order #ORD20260625A1B2C3', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(16, -1, 'Order #ORD20260625A1B2C3', DATE_SUB(NOW(), INTERVAL 28 DAY));

-- Order 2 — Ayesha Islam (registered), Dhaka, Shipped & Paid
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260709D4E5F6', 1, 'Ayesha Islam', 'ayesha@example.com', '01711000000', 'House 7, Road 2, Bashundhara, Dhaka', 'Dhaka', 'inside_dhaka', 2980.00, 70.00, 3050.00, 'mobile_banking', 'paid', 'BKASH8827441', 'shipped', DATE_SUB(NOW(), INTERVAL 14 DAY));
SET @order2 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order2, 3, 'Blush Comfort Two-Piece', 'product-03.jpg', 'L', NULL, 1490.00, 2, 2980.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(3, -2, 'Order #ORD20260709D4E5F6', DATE_SUB(NOW(), INTERVAL 14 DAY));

-- Order 3 — Guest, outside Dhaka (Chattogram), Processing & Unpaid
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260716G7H8I9', NULL, 'Tanvir Ahmed', 'tanvir.a@example.com', '01611000222', '45 GEC Circle, Chattogram', 'Chattogram', 'outside_dhaka', 2700.00, 130.00, 2830.00, 'cod', 'unpaid', NULL, 'processing', DATE_SUB(NOW(), INTERVAL 7 DAY));
SET @order3 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order3, 9, 'Desert Rose Kaftan', 'product-09.jpg', 'M', 'Coral', 1650.00, 1, 1650.00),
(@order3, 13, 'Little Blossom Frock', 'product-13.jpg', '4-5Y', 'Pink', 1050.00, 1, 1050.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(9, -1, 'Order #ORD20260716G7H8I9', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(13, -1, 'Order #ORD20260716G7H8I9', DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Order 4 — Guest, Dhaka, Pending & Unpaid
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260728J1K2L3', NULL, 'Nusrat Jahan', 'nusrat.j@example.com', '01522000333', '12 Kakrail, Dhaka', 'Dhaka', 'inside_dhaka', 2450.00, 70.00, 2520.00, 'cod', 'unpaid', NULL, 'pending', DATE_SUB(NOW(), INTERVAL 2 DAY));
SET @order4 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order4, 5, 'Golden Thread Sharee', 'product-05.jpg', NULL, NULL, 2450.00, 1, 2450.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(5, -1, 'Order #ORD20260728J1K2L3', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Order 5 — Nabila Chowdhury (registered), outside Dhaka (Sylhet), Delivered & Paid by card
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260701M4N5O6', 2, 'Nabila Chowdhury', 'nabila@example.com', '01822000000', 'Flat 3B, Zindabazar, Sylhet', 'Sylhet', 'outside_dhaka', 4800.00, 130.00, 4930.00, 'card', 'paid', 'TXN-CARD-99213', 'delivered', DATE_SUB(NOW(), INTERVAL 22 DAY));
SET @order5 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order5, 7, 'Moonlight Evening Gown', 'product-07.jpg', 'M', NULL, 3450.00, 1, 3450.00),
(@order5, 15, 'Embroidered Boho Tote', 'product-15.jpg', NULL, NULL, 1350.00, 1, 1350.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(7, -1, 'Order #ORD20260701M4N5O6', DATE_SUB(NOW(), INTERVAL 22 DAY)),
(15, -1, 'Order #ORD20260701M4N5O6', DATE_SUB(NOW(), INTERVAL 22 DAY));

-- Order 6 — Guest, Dhaka, Cancelled & Refunded
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`notes`,`created_at`) VALUES
('ORD20260718P7Q8R9', NULL, 'Imran Hossain', 'imran.h@example.com', '01911000444', '9 Elephant Road, Dhaka', 'Dhaka', 'inside_dhaka', 3600.00, 70.00, 3670.00, 'cod', 'refunded', NULL, 'cancelled', 'Customer requested a size change, refunded and cancelled.', DATE_SUB(NOW(), INTERVAL 6 DAY));
SET @order6 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order6, 11, 'Terracotta Cord-Set', 'product-11.jpg', 'L', NULL, 1850.00, 1, 1850.00),
(@order6, 4, 'Ivory Everyday Two-Piece', 'product-04.jpg', 'M', NULL, 1750.00, 1, 1750.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(11, 1, 'Restock - Order #ORD20260718P7Q8R9 cancelled', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 1, 'Restock - Order #ORD20260718P7Q8R9 cancelled', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Order 7 — Rezaul Karim (registered), Dhaka, Processing & Paid
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260730S1T2U3', 3, 'Rezaul Karim', 'rezaul@example.com', '01933000000', '45 GEC Circle, Chattogram', 'Chattogram', 'outside_dhaka', 4790.00, 130.00, 4920.00, 'mobile_banking', 'paid', 'NAGAD5567123', 'processing', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @order7 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order7, 8, 'Aurora Party Gown', 'product-08.jpg', 'S', NULL, 2890.00, 1, 2890.00),
(@order7, 14, 'Sunshine Kids Set', 'product-14.jpg', '5-6Y', 'Yellow', 950.00, 2, 1900.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(8, -1, 'Order #ORD20260730S1T2U3', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(14, -2, 'Order #ORD20260730S1T2U3', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Order 8 — Guest, Dhaka, Delivered & Paid (last month, for month-over-month comparison)
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260628V4W5X6', NULL, 'Kamrul Hasan', 'kamrul.h@example.com', '01611222333', 'House 3, Road 4, Mirpur, Dhaka', 'Dhaka', 'inside_dhaka', 4800.00, 70.00, 4870.00, 'cod', 'paid', NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 38 DAY));
SET @order8 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order8, 6, 'Midnight Bloom Sharee', 'product-06.jpg', NULL, NULL, 2150.00, 1, 2150.00),
(@order8, 12, 'Sage Garden Cord-Set', 'product-12.jpg', 'M', NULL, 2650.00, 1, 2650.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(6, -1, 'Order #ORD20260628V4W5X6', DATE_SUB(NOW(), INTERVAL 38 DAY)),
(12, -1, 'Order #ORD20260628V4W5X6', DATE_SUB(NOW(), INTERVAL 38 DAY));

-- Order 9 — Sabrina Yasmin (registered), Dhaka, Delivered & Paid (last month)
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260616Y7Z8A9', 4, 'Sabrina Yasmin', 'sabrina@example.com', '01644000000', 'Sector 11, Uttara, Dhaka', 'Dhaka', 'inside_dhaka', 4250.00, 70.00, 4320.00, 'mobile_banking', 'paid', 'BKASH2210987', 'delivered', DATE_SUB(NOW(), INTERVAL 50 DAY));
SET @order9 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order9, 2, 'Meher Kameez Set', 'product-02.jpg', 'L', NULL, 2100.00, 1, 2100.00),
(@order9, 10, 'Coastal Breeze Kaftan', 'product-10.jpg', NULL, NULL, 2150.00, 1, 2150.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(2, -1, 'Order #ORD20260616Y7Z8A9', DATE_SUB(NOW(), INTERVAL 50 DAY)),
(10, -1, 'Order #ORD20260616Y7Z8A9', DATE_SUB(NOW(), INTERVAL 50 DAY));

-- Order 10 — Guest, outside Dhaka, Delivered & Paid (last month)
INSERT INTO `orders` (`order_number`,`customer_id`,`guest_name`,`guest_email`,`guest_phone`,`shipping_address`,`city`,`shipping_zone`,`subtotal`,`shipping_fee`,`total_amount`,`payment_method`,`payment_status`,`transaction_id`,`order_status`,`created_at`) VALUES
('ORD20260611B1C2D3', NULL, 'Shirin Akter', 'shirin.a@example.com', '01522333444', 'Court Road, Barishal', 'Barishal', 'outside_dhaka', 1380.00, 130.00, 1510.00, 'cod', 'paid', NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 55 DAY));
SET @order10 = LAST_INSERT_ID();
INSERT INTO `order_items` (`order_id`,`product_id`,`product_name`,`product_image`,`size`,`color`,`price`,`quantity`,`line_total`) VALUES
(@order10, 16, 'Petal Stitched Purse', 'product-16.jpg', NULL, NULL, 690.00, 2, 1380.00);
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(16, -2, 'Order #ORD20260611B1C2D3', DATE_SUB(NOW(), INTERVAL 55 DAY));

-- Initial stock inventory logs for all products
INSERT INTO `inventory_logs` (`product_id`,`change_qty`,`reason`,`created_at`) VALUES
(1, 23, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(2, 15, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(3, 32, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(4, 18, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(5, 26, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(6, 41, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(7, 21, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(8, 17, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(9, 36, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(10, 13, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(11, 28, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(12, 10, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(13, 11, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(14, 17, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(15, 19, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(16, 48, 'Initial stock', DATE_SUB(NOW(), INTERVAL 65 DAY));

-- Sample Reviews
INSERT INTO `reviews` (`product_id`,`customer_id`,`customer_name`,`customer_email`,`rating`,`comment`,`status`,`reviewed_by`,`created_at`) VALUES
(1, 1, 'Ayesha Islam', 'ayesha@example.com', 5, 'The embroidery on this kameez set is stunning — you can feel the care in every stitch!', 'approved', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(3, NULL, 'Farhana Kabir', 'farhana.k@example.com', 4, 'Very comfortable for everyday wear, fits true to size.', 'approved', 2, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(9, 2, 'Nabila Chowdhury', 'nabila@example.com', 5, 'Perfect for summer, the fabric breathes really well.', 'approved', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(5, NULL, 'Tanvir Ahmed', 'tanvir.a@example.com', 5, 'Beautiful golden border, looks even better in person.', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(13, 4, 'Sabrina Yasmin', 'sabrina@example.com', 4, 'My daughter loves this frock, great stitching quality.', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Attribute existing sample orders to employees (so employee dashboards have data)
UPDATE `orders` SET `handled_by` = 2 WHERE `order_number` IN ('ORD20260625A1B2C3','ORD20260716G7H8I9','ORD20260628V4W5X6');
UPDATE `orders` SET `handled_by` = 1 WHERE `order_number` IN ('ORD20260709D4E5F6','ORD20260701M4N5O6','ORD20260730S1T2U3','ORD20260616Y7Z8A9','ORD20260611B1C2D3');

-- Attribute existing sample products to employees
UPDATE `products` SET `created_by` = 2 WHERE `sku` IN ('KAM-01','TWP-01','SHR-01','GWN-01','KAF-01','COR-01','KID-01','BAG-01');
UPDATE `products` SET `created_by` = 1 WHERE `sku` IN ('KAM-02','TWP-02','SHR-02','GWN-02','KAF-02','COR-02','KID-02','BAG-02');

-- Sample Notifications
INSERT INTO `notifications` (`type`,`title`,`message`,`link`,`is_read`,`created_at`) VALUES
('order','New order received','ORD20260730S1T2U3 — ৳4,920.00 from Rezaul Karim','order-view.php?id=7',0,DATE_SUB(NOW(), INTERVAL 1 DAY)),
('low_stock','Low stock alert','Sage Garden Cord-Set has only 9 units left','inventory.php',0,DATE_SUB(NOW(), INTERVAL 2 DAY)),
('order','New order received','ORD20260728J1K2L3 — ৳2,520.00 from Nusrat Jahan','order-view.php?id=4',1,DATE_SUB(NOW(), INTERVAL 2 DAY)),
('customer','New customer registered','Sabrina Yasmin created an account','customers.php',1,DATE_SUB(NOW(), INTERVAL 5 DAY)),
('low_stock','Low stock alert','Little Blossom Frock has only 10 units left','inventory.php',1,DATE_SUB(NOW(), INTERVAL 6 DAY)),
('order','Order cancelled','ORD20260718P7Q8R9 was cancelled and refunded','order-view.php?id=6',1,DATE_SUB(NOW(), INTERVAL 6 DAY)),
('system','Welcome to Stitch & Souls Admin','Your storefront and admin panel are ready to go.','dashboard.php',1,DATE_SUB(NOW(), INTERVAL 65 DAY));

-- ------------------------------------------------------------
-- MIGRATION (safe to run on an existing database that was created
-- before the `stitch_request` column existed). If you already ran
-- the CREATE TABLE `orders` above from this file, skip this block.
-- ------------------------------------------------------------
-- ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `stitch_request` TINYINT(1) NOT NULL DEFAULT 0
--   COMMENT 'Customer asked us to stitch the dress for them' AFTER `notes`;
