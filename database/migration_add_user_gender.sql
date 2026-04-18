-- Run once if users table already exists without gender
USE luxe_shop;

ALTER TABLE users
  ADD COLUMN gender VARCHAR(16) NULL AFTER dob;
