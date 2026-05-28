-- Run once on existing luxe_shop DB (phpMyAdmin or mysql CLI)
USE luxe_shop;

ALTER TABLE seller_create_requests
  ADD COLUMN password_confirmed_at DATETIME NULL DEFAULT NULL AFTER requested_password_hash;
