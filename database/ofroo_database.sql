-- ============================================
-- OFROO Database Schema - MySQL
-- ============================================
-- This script creates the complete database structure for OFROO application
-- Includes: Tables, Indexes, Foreign Keys, Views, and Sample Data
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. ROLES TABLE
-- ============================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) UNIQUE NOT NULL COMMENT 'Role name: admin/merchant/user',
  `name_ar` VARCHAR(50) NULL COMMENT 'اسم الدور بالعربية',
  `name_en` VARCHAR(50) NULL COMMENT 'Role name in English',
  `description` VARCHAR(255) NULL COMMENT 'Role description',
  `description_ar` VARCHAR(255) NULL COMMENT 'وصف الدور بالعربية',
  `description_en` VARCHAR(255) NULL COMMENT 'Role description in English',
  `permissions` JSON NULL COMMENT 'JSON permissions array',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. USERS TABLE
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) UNIQUE,
  `phone` VARCHAR(30) UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `language` ENUM('ar','en') DEFAULT 'ar' COMMENT 'User preferred language',
  `role_id` INT NULL,
  `email_verified_at` DATETIME NULL,
  `otp_code` VARCHAR(10) NULL COMMENT 'OTP code for verification',
  `otp_expires_at` DATETIME NULL COMMENT 'OTP expiration time',
  `last_location_lat` DECIMAL(10,7) NULL COMMENT 'Last known latitude',
  `last_location_lng` DECIMAL(10,7) NULL COMMENT 'Last known longitude',
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL,
  INDEX `users_location_idx` (`last_location_lat`, `last_location_lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. MERCHANTS TABLE
-- ============================================
DROP TABLE IF EXISTS `merchants`;
CREATE TABLE `merchants` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL COMMENT 'Owner user ID',
  `company_name` VARCHAR(255) COMMENT 'Company name',
  `company_name_ar` VARCHAR(255) NULL COMMENT 'اسم الشركة بالعربية',
  `company_name_en` VARCHAR(255) NULL COMMENT 'Company name in English',
  `description` TEXT NULL COMMENT 'Merchant description',
  `description_ar` TEXT NULL COMMENT 'وصف التاجر بالعربية',
  `description_en` TEXT NULL COMMENT 'Merchant description in English',
  `address` VARCHAR(500) NULL COMMENT 'Address',
  `address_ar` VARCHAR(500) NULL COMMENT 'العنوان بالعربية',
  `address_en` VARCHAR(500) NULL COMMENT 'Address in English',
  `phone` VARCHAR(50) NULL COMMENT 'Phone number',
  `whatsapp_link` VARCHAR(255) NULL COMMENT 'WhatsApp link',
  `approved` TINYINT(1) DEFAULT 0 COMMENT 'Admin approval status',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `merchant_user_idx` (`user_id`),
  INDEX `merchant_approved_idx` (`approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. STORE LOCATIONS TABLE
-- ============================================
DROP TABLE IF EXISTS `store_locations`;
CREATE TABLE `store_locations` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `merchant_id` BIGINT NOT NULL COMMENT 'Merchant ID',
  `lat` DECIMAL(10,7) NOT NULL COMMENT 'Latitude',
  `lng` DECIMAL(10,7) NOT NULL COMMENT 'Longitude',
  `address` VARCHAR(500) NULL COMMENT 'Address',
  `address_ar` VARCHAR(500) NULL COMMENT 'العنوان بالعربية',
  `address_en` VARCHAR(500) NULL COMMENT 'Address in English',
  `google_place_id` VARCHAR(255) NULL COMMENT 'Google Place ID',
  `opening_hours` JSON NULL COMMENT 'Opening hours JSON',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
  INDEX `store_locations_merchant_idx` (`merchant_id`),
  INDEX `store_locations_geo_idx` (`lat`, `lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CATEGORIES TABLE
-- ============================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_ar` VARCHAR(150) NOT NULL COMMENT 'اسم الفئة بالعربية',
  `name_en` VARCHAR(150) NULL COMMENT 'Category name in English',
  `order_index` INT DEFAULT 0 COMMENT 'Display order',
  `parent_id` INT NULL COMMENT 'Parent category ID',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  INDEX `categories_parent_idx` (`parent_id`),
  INDEX `categories_order_idx` (`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. OFFERS TABLE
-- ============================================
DROP TABLE IF EXISTS `offers`;
CREATE TABLE `offers` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `merchant_id` BIGINT NOT NULL COMMENT 'Merchant ID',
  `category_id` INT NULL COMMENT 'Category ID',
  `location_id` BIGINT NULL COMMENT 'Store location ID',
  `title_ar` VARCHAR(255) COMMENT 'عنوان العرض بالعربية',
  `title_en` VARCHAR(255) NULL COMMENT 'Offer title in English',
  `description_ar` TEXT NULL COMMENT 'وصف العرض بالعربية',
  `description_en` TEXT NULL COMMENT 'Offer description in English',
  `price` DECIMAL(10,2) COMMENT 'Offer price',
  `original_price` DECIMAL(10,2) NULL COMMENT 'Original price before discount',
  `discount_percent` INT DEFAULT 0 COMMENT 'Discount percentage',
  `images` JSON NULL COMMENT 'Images array JSON',
  `total_coupons` INT DEFAULT 0 COMMENT 'Total available coupons',
  `coupons_remaining` INT DEFAULT 0 COMMENT 'Remaining coupons',
  `start_at` DATETIME NULL COMMENT 'Offer start date',
  `end_at` DATETIME NULL COMMENT 'Offer end date',
  `status` ENUM('draft','pending','active','expired') DEFAULT 'draft' COMMENT 'Offer status',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `store_locations`(`id`) ON DELETE SET NULL,
  INDEX `offers_merchant_idx` (`merchant_id`),
  INDEX `offers_category_idx` (`category_id`),
  INDEX `offers_location_idx` (`location_id`),
  INDEX `offers_status_idx` (`status`),
  INDEX `offers_start_at_idx` (`start_at`),
  INDEX `offers_end_at_idx` (`end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CARTS TABLE
-- ============================================
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL COMMENT 'User ID',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `carts_user_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. CART ITEMS TABLE
-- ============================================
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT NOT NULL COMMENT 'Cart ID',
  `offer_id` BIGINT NOT NULL COMMENT 'Offer ID',
  `quantity` INT DEFAULT 1 COMMENT 'Quantity',
  `price_at_add` DECIMAL(10,2) COMMENT 'Price when added to cart',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
  INDEX `cart_items_cart_idx` (`cart_id`),
  INDEX `cart_items_offer_idx` (`offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. ORDERS TABLE
-- ============================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL COMMENT 'User ID',
  `merchant_id` BIGINT NULL COMMENT 'Merchant ID',
  `total_amount` DECIMAL(12,2) COMMENT 'Total order amount',
  `payment_method` ENUM('cash','card','none') DEFAULT 'cash' COMMENT 'Payment method',
  `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending' COMMENT 'Payment status',
  `notes` TEXT NULL COMMENT 'Order notes',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE SET NULL,
  INDEX `orders_user_idx` (`user_id`),
  INDEX `orders_merchant_idx` (`merchant_id`),
  INDEX `orders_payment_status_idx` (`payment_status`),
  INDEX `orders_created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. ORDER ITEMS TABLE
-- ============================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT NOT NULL COMMENT 'Order ID',
  `offer_id` BIGINT NOT NULL COMMENT 'Offer ID',
  `quantity` INT COMMENT 'Quantity',
  `unit_price` DECIMAL(10,2) COMMENT 'Unit price',
  `total_price` DECIMAL(10,2) COMMENT 'Total price',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
  INDEX `order_items_order_idx` (`order_id`),
  INDEX `order_items_offer_idx` (`offer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. COUPONS TABLE
-- ============================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT NOT NULL COMMENT 'Order ID',
  `offer_id` BIGINT NOT NULL COMMENT 'Offer ID',
  `coupon_code` VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique coupon code',
  `barcode_value` VARCHAR(255) NULL COMMENT 'Barcode value',
  `user_id` BIGINT NULL COMMENT 'User owner ID',
  `status` ENUM('reserved','activated','used','cancelled','expired') DEFAULT 'reserved' COMMENT 'Coupon status',
  `reserved_at` DATETIME NULL COMMENT 'Reservation date',
  `activated_at` DATETIME NULL COMMENT 'Activation date',
  `used_at` DATETIME NULL COMMENT 'Usage date',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `coupons_order_idx` (`order_id`),
  INDEX `coupons_offer_idx` (`offer_id`),
  INDEX `coupons_user_idx` (`user_id`),
  INDEX `coupons_status_idx` (`status`),
  INDEX `coupons_code_idx` (`coupon_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. PAYMENTS TABLE
-- ============================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT NOT NULL COMMENT 'Order ID',
  `transaction_id` VARCHAR(255) NULL COMMENT 'Transaction ID from gateway',
  `amount` DECIMAL(12,2) COMMENT 'Payment amount',
  `gateway` VARCHAR(100) NULL COMMENT 'Payment gateway name',
  `status` ENUM('pending','success','failed') DEFAULT 'pending' COMMENT 'Payment status',
  `response` JSON NULL COMMENT 'Gateway response JSON',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `payments_order_idx` (`order_id`),
  INDEX `payments_transaction_idx` (`transaction_id`),
  INDEX `payments_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. REVIEWS TABLE
-- ============================================
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL COMMENT 'User ID',
  `merchant_id` BIGINT NOT NULL COMMENT 'Merchant ID',
  `order_id` BIGINT NULL COMMENT 'Order ID',
  `rating` TINYINT NOT NULL COMMENT 'Rating 1-5',
  `notes` TEXT NULL COMMENT 'Review notes',
  `notes_ar` TEXT NULL COMMENT 'ملاحظات التقييم بالعربية',
  `notes_en` TEXT NULL COMMENT 'Review notes in English',
  `visible_to_public` TINYINT(1) DEFAULT 0 COMMENT 'Visible to public',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX `reviews_user_idx` (`user_id`),
  INDEX `reviews_merchant_idx` (`merchant_id`),
  INDEX `reviews_order_idx` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. NOTIFICATIONS TABLE
-- ============================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `notifiable_type` VARCHAR(255) NOT NULL COMMENT 'Polymorphic type (User, Merchant, etc)',
  `notifiable_id` BIGINT NOT NULL COMMENT 'Polymorphic ID',
  `type` VARCHAR(255) COMMENT 'Notification type',
  `data` JSON NULL COMMENT 'Notification data JSON',
  `read_at` TIMESTAMP NULL COMMENT 'Read timestamp',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `notifications_notifiable_idx` (`notifiable_type`, `notifiable_id`),
  INDEX `notifications_read_at_idx` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15. SETTINGS TABLE
-- ============================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(255) UNIQUE COMMENT 'Setting key',
  `value` TEXT NULL COMMENT 'Setting value',
  `type` VARCHAR(50) DEFAULT 'string' COMMENT 'Value type: string, json, boolean, integer',
  `description` TEXT NULL COMMENT 'Setting description',
  `description_ar` TEXT NULL COMMENT 'وصف الإعداد بالعربية',
  `description_en` TEXT NULL COMMENT 'Setting description in English',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `settings_key_idx` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16. LOGIN ATTEMPTS TABLE
-- ============================================
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NULL COMMENT 'User ID (null if email/phone not found)',
  `email` VARCHAR(150) NULL COMMENT 'Attempted email',
  `phone` VARCHAR(30) NULL COMMENT 'Attempted phone',
  `ip_address` VARCHAR(45) COMMENT 'IP address',
  `user_agent` TEXT NULL COMMENT 'User agent',
  `success` TINYINT(1) DEFAULT 0 COMMENT 'Login success',
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Attempt timestamp',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `login_attempts_user_idx` (`user_id`),
  INDEX `login_attempts_email_idx` (`email`),
  INDEX `login_attempts_phone_idx` (`phone`),
  INDEX `login_attempts_ip_idx` (`ip_address`),
  INDEX `login_attempts_attempted_at_idx` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 17. SUBSCRIPTIONS TABLE
-- ============================================
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `subscribable_type` VARCHAR(255) NOT NULL COMMENT 'Polymorphic type (Merchant, User)',
  `subscribable_id` BIGINT NOT NULL COMMENT 'Polymorphic ID',
  `package_name` VARCHAR(100) COMMENT 'Package name',
  `package_name_ar` VARCHAR(100) NULL COMMENT 'اسم الباقة بالعربية',
  `package_name_en` VARCHAR(100) NULL COMMENT 'Package name in English',
  `starts_at` DATETIME COMMENT 'Subscription start date',
  `ends_at` DATETIME COMMENT 'Subscription end date',
  `price` DECIMAL(10,2) COMMENT 'Subscription price',
  `status` ENUM('active','expired','cancelled') DEFAULT 'active' COMMENT 'Subscription status',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `subscriptions_subscribable_idx` (`subscribable_type`, `subscribable_id`),
  INDEX `subscriptions_status_idx` (`status`),
  INDEX `subscriptions_ends_at_idx` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VIEWS
-- ============================================

-- View: Sales Summary Per Merchant
DROP VIEW IF EXISTS `view_sales_summary_per_merchant`;
CREATE VIEW `view_sales_summary_per_merchant` AS
SELECT 
    m.id AS merchant_id,
    m.company_name,
    m.company_name_ar,
    m.company_name_en,
    COUNT(DISTINCT o.id) AS total_orders,
    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS total_revenue,
    COUNT(DISTINCT CASE WHEN c.status = 'activated' THEN c.id END) AS coupons_activated_count,
    COUNT(DISTINCT CASE WHEN c.status = 'used' THEN c.id END) AS coupons_used_count,
    COUNT(DISTINCT CASE WHEN c.status = 'reserved' THEN c.id END) AS coupons_reserved_count
FROM merchants m
LEFT JOIN orders o ON o.merchant_id = m.id
LEFT JOIN coupons c ON c.order_id = o.id
GROUP BY m.id, m.company_name, m.company_name_ar, m.company_name_en;

-- ============================================
-- SAMPLE DATA (SEEDERS)
-- ============================================

-- Insert Roles
INSERT INTO `roles` (`name`, `name_ar`, `name_en`, `description`, `description_ar`, `description_en`, `permissions`) VALUES
('admin', 'مدير', 'Admin', 'System administrator', 'مدير النظام', 'System administrator', '["*"]'),
('merchant', 'تاجر', 'Merchant', 'Merchant account', 'حساب تاجر', 'Merchant account', '["manage_offers", "view_orders", "activate_coupons"]'),
('user', 'مستخدم', 'User', 'Regular user', 'مستخدم عادي', 'Regular user', '["view_offers", "purchase_coupons", "view_wallet"]');

-- Insert Admin User
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `language`, `role_id`, `email_verified_at`) VALUES
('Admin User', 'admin@ofroo.com', '+201234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', 1, NOW());

-- Insert Regular Users
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `language`, `role_id`, `email_verified_at`, `last_location_lat`, `last_location_lng`) VALUES
('Ahmed Ali', 'ahmed@example.com', '+201234567891', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', 3, NOW(), 30.0626, 31.3219),
('Mohammed Hassan', 'mohammed@example.com', '+201234567892', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', 3, NOW(), 30.0444, 31.2357);

-- Insert Categories
INSERT INTO `categories` (`name_ar`, `name_en`, `order_index`, `parent_id`) VALUES
('مولات', 'Malls', 1, NULL),
('مطاعم', 'Restaurants', 2, NULL),
('ترفيه', 'Entertainment', 3, NULL);

-- Insert Merchants
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `language`, `role_id`, `email_verified_at`) VALUES
('Merchant One', 'merchant1@example.com', '+201234567893', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', 2, NOW()),
('Merchant Two', 'merchant2@example.com', '+201234567894', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', 2, NOW());

SET @merchant1_user_id = LAST_INSERT_ID() - 1;
SET @merchant2_user_id = LAST_INSERT_ID();

INSERT INTO `merchants` (`user_id`, `company_name`, `company_name_ar`, `company_name_en`, `description`, `description_ar`, `description_en`, `address`, `address_ar`, `phone`, `approved`) VALUES
(@merchant1_user_id, 'City Stars Mall', 'سيتي ستارز', 'City Stars Mall', 'Large shopping mall in Cairo', 'مجمع تجاري كبير في القاهرة', 'Large shopping mall in Cairo', 'Nasr City, Cairo, Egypt', 'مدينة نصر، القاهرة، مصر', '+201234567893', 1),
(@merchant2_user_id, 'Koshary Abou Tarek', 'كشري أبو طارق', 'Koshary Abou Tarek', 'Traditional Egyptian restaurant', 'مطعم مصري تقليدي', 'Traditional Egyptian restaurant', 'Downtown, Cairo, Egypt', 'وسط البلد، القاهرة، مصر', '+201234567894', 1);

-- Insert Store Locations
SET @merchant1_id = (SELECT id FROM merchants WHERE user_id = @merchant1_user_id);
SET @merchant2_id = (SELECT id FROM merchants WHERE user_id = @merchant2_user_id);

INSERT INTO `store_locations` (`merchant_id`, `lat`, `lng`, `address`, `address_ar`, `address_en`, `google_place_id`, `opening_hours`) VALUES
(@merchant1_id, 30.0626, 31.3219, 'Nasr City, Cairo, Egypt', 'مدينة نصر، القاهرة، مصر', 'Nasr City, Cairo, Egypt', 'ChIJ...', '{"monday": "10:00-22:00", "tuesday": "10:00-22:00", "wednesday": "10:00-22:00", "thursday": "10:00-22:00", "friday": "14:00-22:00", "saturday": "10:00-22:00", "sunday": "10:00-22:00"}'),
(@merchant2_id, 30.0444, 31.2357, 'Downtown, Cairo, Egypt', 'وسط البلد، القاهرة، مصر', 'Downtown, Cairo, Egypt', 'ChIJ...', '{"monday": "12:00-23:00", "tuesday": "12:00-23:00", "wednesday": "12:00-23:00", "thursday": "12:00-23:00", "friday": "12:00-23:00", "saturday": "12:00-23:00", "sunday": "12:00-23:00"}');

-- Insert Offers
SET @category_malls = (SELECT id FROM categories WHERE name_ar = 'مولات');
SET @category_restaurants = (SELECT id FROM categories WHERE name_ar = 'مطاعم');
SET @location1 = (SELECT id FROM store_locations WHERE merchant_id = @merchant1_id LIMIT 1);
SET @location2 = (SELECT id FROM store_locations WHERE merchant_id = @merchant2_id LIMIT 1);

INSERT INTO `offers` (`merchant_id`, `category_id`, `location_id`, `title_ar`, `title_en`, `description_ar`, `description_en`, `price`, `original_price`, `discount_percent`, `images`, `total_coupons`, `coupons_remaining`, `start_at`, `end_at`, `status`) VALUES
(@merchant1_id, @category_malls, @location1, 'خصم 50% على جميع المنتجات', '50% Discount on All Products', 'خصم كبير على جميع المنتجات في المجمع', 'Big discount on all products in the mall', 25.00, 50.00, 50, '["image1.jpg", "image2.jpg"]', 100, 95, DATE_ADD(NOW(), INTERVAL -5 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
(@merchant2_id, @category_restaurants, @location2, 'وجبة عائلية بخصم 30%', 'Family Meal 30% Off', 'وجبة عائلية لشخصين بخصم 30%', 'Family meal for two with 30% discount', 35.00, 50.00, 30, '["meal1.jpg"]', 50, 48, DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), 'active');

-- Insert Sample Order
SET @user1_id = (SELECT id FROM users WHERE email = 'ahmed@example.com');
SET @offer1_id = (SELECT id FROM offers WHERE merchant_id = @merchant1_id LIMIT 1);

INSERT INTO `orders` (`user_id`, `merchant_id`, `total_amount`, `payment_method`, `payment_status`, `notes`) VALUES
(@user1_id, @merchant1_id, 25.00, 'cash', 'paid', 'Order placed via mobile app');

SET @order1_id = LAST_INSERT_ID();

INSERT INTO `order_items` (`order_id`, `offer_id`, `quantity`, `unit_price`, `total_price`) VALUES
(@order1_id, @offer1_id, 1, 25.00, 25.00);

-- Insert Generated Coupons
INSERT INTO `coupons` (`order_id`, `offer_id`, `coupon_code`, `barcode_value`, `user_id`, `status`, `reserved_at`) VALUES
(@order1_id, @offer1_id, 'OFR-ABC1234', '1234567890123', @user1_id, 'reserved', NOW()),
(@order1_id, @offer1_id, 'OFR-XYZ5678', '1234567890124', @user1_id, 'reserved', NOW());

-- Insert Settings
INSERT INTO `settings` (`key`, `value`, `type`, `description`, `description_ar`, `description_en`) VALUES
('app_name', 'OFROO', 'string', 'Application name', 'اسم التطبيق', 'Application name'),
('app_logo', '/images/logo.png', 'string', 'Application logo URL', 'رابط شعار التطبيق', 'Application logo URL'),
('primary_color', '#FF6B35', 'string', 'Primary theme color', 'اللون الأساسي', 'Primary theme color'),
('secondary_color', '#004E89', 'string', 'Secondary theme color', 'اللون الثانوي', 'Secondary theme color'),
('commission_rate', '0.15', 'string', 'Commission rate (15%)', 'نسبة العمولة (15%)', 'Commission rate (15%)'),
('enable_gps', 'true', 'boolean', 'Enable GPS location', 'تفعيل الموقع الجغرافي', 'Enable GPS location'),
('enable_electronic_payments', 'true', 'boolean', 'Enable electronic payments', 'تفعيل الدفع الإلكتروني', 'Enable electronic payments'),
('google_maps_api_key', '', 'string', 'Google Maps API Key', 'مفتاح Google Maps API', 'Google Maps API Key');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================
-- END OF SCRIPT
-- ============================================

