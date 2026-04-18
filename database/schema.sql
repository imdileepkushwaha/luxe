-- LUXE shop — MySQL 5.7+ / 8.0
CREATE DATABASE IF NOT EXISTS luxe_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE luxe_shop;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  dob DATE NULL,
  gender VARCHAR(16) NULL,
  loyalty_points_redeemed INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT 'Administrator',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE site_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES ('platform_fee_rupees', '3');

CREATE TABLE seller_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT 'Seller',
  allowed_categories VARCHAR(255) NOT NULL DEFAULT 'fashion,electronics,beauty,home',
  business_name VARCHAR(150) NOT NULL DEFAULT '',
  gst_number VARCHAR(20) NOT NULL DEFAULT '',
  pan_number VARCHAR(20) NOT NULL DEFAULT '',
  aadhaar_number VARCHAR(20) NOT NULL DEFAULT '',
  bank_name VARCHAR(120) NOT NULL DEFAULT '',
  gst_doc_path VARCHAR(255) NOT NULL DEFAULT '',
  pan_doc_path VARCHAR(255) NOT NULL DEFAULT '',
  aadhaar_doc_path VARCHAR(255) NOT NULL DEFAULT '',
  bank_account_name VARCHAR(120) NOT NULL DEFAULT '',
  bank_account_number VARCHAR(40) NOT NULL DEFAULT '',
  bank_ifsc VARCHAR(20) NOT NULL DEFAULT '',
  address_line1 VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  state VARCHAR(100) NOT NULL DEFAULT '',
  pin_code VARCHAR(20) NOT NULL DEFAULT '',
  id_proof_type VARCHAR(40) NOT NULL DEFAULT '',
  id_proof_number VARCHAR(80) NOT NULL DEFAULT '',
  phone_number VARCHAR(40) NOT NULL DEFAULT '',
  business_address VARCHAR(255) NOT NULL DEFAULT '',
  logo_path VARCHAR(255) NOT NULL DEFAULT '',
  banner_path VARCHAR(255) NOT NULL DEFAULT '',
  kyc_completed TINYINT(1) NOT NULL DEFAULT 0,
  kyc_updated_at DATETIME NULL,
  kyc_final_approved TINYINT(1) NOT NULL DEFAULT 0,
  kyc_final_reviewed_by INT UNSIGNED NULL,
  kyc_final_reviewed_at DATETIME NULL,
  kyc_rejection_reason VARCHAR(255) NOT NULL DEFAULT '',
  kyc_edit_request_status VARCHAR(16) NOT NULL DEFAULT 'none',
  kyc_edit_requested_at DATETIME NULL,
  kyc_edit_reviewed_by INT UNSIGNED NULL,
  kyc_edit_reviewed_at DATETIME NULL,
  kyc_edit_rejection_reason VARCHAR(255) NOT NULL DEFAULT '',
  kyc_edit_unlocked TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE seller_create_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  requested_password_hash VARCHAR(255) NOT NULL DEFAULT '',
  requested_categories VARCHAR(255) NOT NULL DEFAULT '',
  note VARCHAR(500) NOT NULL DEFAULT '',
  business_name VARCHAR(150) NOT NULL DEFAULT '',
  gst_number VARCHAR(20) NOT NULL DEFAULT '',
  pan_number VARCHAR(20) NOT NULL DEFAULT '',
  aadhaar_number VARCHAR(20) NOT NULL DEFAULT '',
  bank_account_name VARCHAR(120) NOT NULL DEFAULT '',
  bank_account_number VARCHAR(40) NOT NULL DEFAULT '',
  bank_ifsc VARCHAR(20) NOT NULL DEFAULT '',
  address_line1 VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  state VARCHAR(100) NOT NULL DEFAULT '',
  pin_code VARCHAR(20) NOT NULL DEFAULT '',
  id_proof_type VARCHAR(40) NOT NULL DEFAULT '',
  id_proof_number VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  seller_id INT UNSIGNED NULL,
  rejection_reason VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_seller_req_status (status, created_at),
  KEY idx_seller_req_email (email),
  CONSTRAINT fk_seller_req_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_seller_req_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  sku VARCHAR(80) NULL,
  category VARCHAR(64) NOT NULL,
  price INT UNSIGNED NOT NULL,
  original_price INT UNSIGNED NOT NULL,
  emoji VARCHAR(16) NOT NULL DEFAULT '📦',
  badge VARCHAR(64) NOT NULL DEFAULT '',
  rating DECIMAL(2,1) NOT NULL DEFAULT 4.5,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  brand VARCHAR(255) NOT NULL DEFAULT 'LUXE',
  image_bg VARCHAR(32) NOT NULL DEFAULT '#1a0a2e',
  image_path VARCHAR(255) NULL,
  size_options VARCHAR(255) NOT NULL DEFAULT '',
  color_options VARCHAR(255) NOT NULL DEFAULT '',
  stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
  description TEXT NULL,
  offer_flash_text VARCHAR(150) NOT NULL DEFAULT '',
  offer_countdown_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  offer_bank_text VARCHAR(150) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  approval_status VARCHAR(20) NOT NULL DEFAULT 'approved',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_products_seller_id (seller_id),
  KEY idx_products_approval_status (approval_status),
  UNIQUE KEY uq_products_sku (sku),
  CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product_images_product (product_id, sort_order, id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_variant_inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  size_label VARCHAR(64) NOT NULL DEFAULT '',
  color_label VARCHAR(64) NOT NULL DEFAULT '',
  stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_variant_inventory (product_id, size_label, color_label),
  KEY idx_product_variant_inventory_product (product_id, active),
  CONSTRAINT fk_product_variant_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_withdraw_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  amount INT UNSIGNED NOT NULL,
  method VARCHAR(32) NOT NULL DEFAULT 'bank',
  account_ref VARCHAR(255) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  rejection_reason VARCHAR(255) NOT NULL DEFAULT '',
  KEY idx_seller_withdraw_seller (seller_id, status, requested_at),
  KEY idx_seller_withdraw_status (status, requested_at),
  CONSTRAINT fk_seller_withdraw_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_seller_withdraw_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE seller_shipping_settings (
  seller_id INT UNSIGNED PRIMARY KEY,
  handling_time_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
  default_shipping_fee INT UNSIGNED NOT NULL DEFAULT 0,
  free_shipping_min_order INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_regions VARCHAR(255) NOT NULL DEFAULT 'All India',
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  shipping_policy TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_seller_shipping_settings_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_delivery_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  option_code VARCHAR(32) NOT NULL,
  option_label VARCHAR(80) NOT NULL,
  eta_min_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
  eta_max_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
  fee_amount INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_delivery_option (seller_id, option_code),
  KEY idx_seller_delivery_options_seller (seller_id, is_active, sort_order),
  CONSTRAINT fk_seller_delivery_options_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_return_settings (
  seller_id INT UNSIGNED PRIMARY KEY,
  return_window_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
  return_conditions TEXT NULL,
  refund_method VARCHAR(40) NOT NULL DEFAULT 'original_payment',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_seller_return_settings_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL DEFAULT 'Customer',
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  review_text VARCHAR(1000) NOT NULL DEFAULT '',
  review_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  seller_response VARCHAR(1000) NOT NULL DEFAULT '',
  seller_reviewed_at DATETIME NULL,
  seller_responded_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_product_reviews_product (product_id, review_status, created_at),
  KEY idx_product_reviews_user (user_id),
  CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  order_ref VARCHAR(32) NOT NULL UNIQUE,
  status VARCHAR(32) NOT NULL DEFAULT 'processing',
  total_amount INT UNSIGNED NOT NULL,
  platform_fee_rupees INT UNSIGNED NOT NULL DEFAULT 0,
  payment_method VARCHAR(128) NOT NULL DEFAULT '',
  shipping_address VARCHAR(512) NOT NULL DEFAULT '',
  delivered_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  emoji VARCHAR(16) NOT NULL DEFAULT '📦',
  variant_text VARCHAR(255) NOT NULL DEFAULT '',
  price INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE user_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type_label VARCHAR(32) NOT NULL DEFAULT 'Home',
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  line1 VARCHAR(255) NOT NULL,
  line2 VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  pin VARCHAR(20) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_account_deletion_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  process_after DATETIME NOT NULL,
  completed_at DATETIME NULL,
  KEY idx_user_status (user_id, status),
  KEY idx_status_process (status, process_after)
) ENGINE=InnoDB;

CREATE TABLE user_return_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  order_ref VARCHAR(32) NOT NULL,
  product_name VARCHAR(255) NOT NULL DEFAULT '',
  reason VARCHAR(120) NOT NULL DEFAULT '',
  details VARCHAR(1000) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  KEY idx_user_return_user (user_id, requested_at),
  KEY idx_user_return_status (status, requested_at),
  CONSTRAINT fk_user_return_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_order_cancel_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  order_ref VARCHAR(32) NOT NULL,
  reason VARCHAR(120) NOT NULL DEFAULT '',
  details VARCHAR(1000) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  KEY idx_user_cancel_user (user_id, requested_at),
  KEY idx_user_cancel_order (order_id, seller_id, status),
  KEY idx_user_cancel_seller (seller_id, status, requested_at),
  CONSTRAINT fk_user_cancel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_cancel_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_cancel_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE seller_account_deletion_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  rejection_reason VARCHAR(255) NOT NULL DEFAULT '',
  KEY idx_seller_del_status (status, requested_at),
  KEY idx_seller_del_seller (seller_id, status),
  CONSTRAINT fk_seller_del_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Demo: demo@luxe.com / password  (bcrypt below is PHP default test hash for "password")
INSERT INTO users (email, password_hash, first_name, last_name, phone, dob, gender) VALUES
('demo@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rahul', 'Sharma', '+91 98765 43210', '1995-03-15', 'male');

INSERT INTO products (id, name, slug, category, price, original_price, emoji, badge, rating, review_count, brand, image_bg, description) VALUES
(1, 'AirMax Pro 2026', 'airmax-pro-2026', 'fashion', 8999, 14500, '👟', 'Best Seller', 4.8, 2140, 'Nike × LUXE', '#1a0a2e', 'Premium sneakers'),
(2, 'Sony WH-1000XM5', 'sony-wh1000xm5', 'electronics', 18999, 34990, '🎧', 'Sale', 4.9, 5621, 'Sony', '#0a1a2e', NULL),
(3, 'Retinol Serum Kit', 'retinol-serum-kit', 'beauty', 1899, 3500, '🧴', 'New', 4.6, 890, 'LUXE Beauty', '#1a2e0a', NULL),
(4, 'Smart Coffee Maker', 'smart-coffee-maker', 'home', 5499, 8999, '☕', 'Sale', 4.7, 432, 'LUXE Home', '#2e1a0a', NULL),
(5, 'Apple Watch SE', 'apple-watch-se', 'electronics', 19500, 29900, '⌚', 'Hot', 4.8, 3210, 'Apple', '#1a0a2e', NULL),
(6, 'Linen Co-ord Set', 'linen-coord-set', 'fashion', 3299, 5500, '👗', 'New', 4.5, 678, 'LUXE', '#0a2e1a', NULL),
(7, 'Vitamin C Gummies', 'vitamin-c-gummies', 'beauty', 699, 1200, '🍊', 'Sale', 4.4, 1230, 'LUXE', '#2e0a1a', NULL),
(8, 'LED Desk Lamp', 'led-desk-lamp', 'home', 1599, 2800, '💡', 'Best Seller', 4.7, 980, 'LUXE', '#1a1a2e', NULL),
(10, 'Nike React Infinity', 'nike-react-infinity', 'fashion', 10999, 16999, '👟', 'New', 4.6, 400, 'Nike', '#1a0a2e', NULL),
(11, 'Adidas UltraBoost', 'adidas-ultraboost', 'fashion', 12499, 19999, '👟', 'Hot', 4.7, 300, 'Adidas', '#0a1a2e', NULL);

INSERT INTO orders (user_id, order_ref, status, total_amount, payment_method, shipping_address, created_at) VALUES
(1, 'LUXE83920741', 'delivered', 27998, 'HDFC Credit Card', 'Sector 15, Noida, UP 201301', '2026-04-10 12:00:00');

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 1, 'AirMax Pro 2026', '👟', 'UK 8 · Purple', 8999, 1 FROM orders WHERE order_ref = 'LUXE83920741' LIMIT 1;

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 2, 'Sony WH-1000XM5', '🎧', 'Black', 18999, 1 FROM orders WHERE order_ref = 'LUXE83920741' LIMIT 1;

INSERT INTO orders (user_id, order_ref, status, total_amount, payment_method, shipping_address, created_at) VALUES
(1, 'LUXE92810465', 'shipped', 19500, 'UPI - rahul@ok', 'Sector 15, Noida, UP 201301', '2026-04-06 10:00:00');

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 5, 'Apple Watch SE 44mm', '⌚', 'Midnight · GPS', 19500, 1 FROM orders WHERE order_ref = 'LUXE92810465' LIMIT 1;

INSERT INTO orders (user_id, order_ref, status, total_amount, payment_method, shipping_address, created_at) VALUES
(1, 'LUXE77541238', 'delivered', 6797, 'Amazon Pay', 'Sector 15, Noida, UP 201301', '2026-03-28 15:00:00');

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 6, 'Linen Co-ord Set', '👗', 'S · Beige', 3299, 1 FROM orders WHERE order_ref = 'LUXE77541238' LIMIT 1;

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 3, 'Retinol Serum Kit', '🧴', '30ml', 1899, 1 FROM orders WHERE order_ref = 'LUXE77541238' LIMIT 1;

INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty)
SELECT id, 8, 'LED Desk Lamp', '💡', 'White', 1599, 1 FROM orders WHERE order_ref = 'LUXE77541238' LIMIT 1;

INSERT INTO user_addresses (user_id, type_label, full_name, phone, line1, line2, city, state, pin, is_default) VALUES
(1, 'Home', 'Rahul Sharma', '+91 98765 43210', 'Flat 402, Emerald Heights', 'Sector 15, Near Metro Station', 'Noida', 'Uttar Pradesh', '201301', 1),
(1, 'Work', 'Rahul Sharma', '+91 91234 56789', 'LUXE Corp, Tower B, 5th Floor', 'Cyber City, DLF Phase 2', 'Gurugram', 'Haryana', '122002', 0);
