-- Seller-generated discount coupons (cart / checkout / place-order)
CREATE TABLE IF NOT EXISTS seller_coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  discount_type ENUM('percent','flat') NOT NULL,
  discount_value INT UNSIGNED NOT NULL,
  max_discount_rupees INT UNSIGNED NULL,
  min_order_rupees INT UNSIGNED NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  valid_from DATE NULL,
  valid_until DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seller_coupons_code (code),
  KEY idx_seller_coupons_seller (seller_id, is_active),
  CONSTRAINT fk_seller_coupons_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
