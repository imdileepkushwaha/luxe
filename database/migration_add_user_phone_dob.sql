-- Run once on existing luxe_shop DB (phpMyAdmin or mysql CLI)
USE luxe_shop;

ALTER TABLE users
  ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT '' AFTER last_name,
  ADD COLUMN dob DATE NULL AFTER phone;
