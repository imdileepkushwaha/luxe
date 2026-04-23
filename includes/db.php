<?php

declare(strict_types=1);

/**
 * Adds profile columns if the DB was created from an older schema (avoids "Unknown column 'phone'" fatals).
 */
function db_ensure_user_profile_columns(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?,?,?)'
        );
        $chk->execute([$dbName, 'users', 'phone', 'dob', 'gender']);
        $have = array_flip($chk->fetchAll(PDO::FETCH_COLUMN));

        if (!isset($have['phone'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT ''");
        }
        if (!isset($have['dob'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN dob DATE NULL');
        }
        if (!isset($have['gender'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN gender VARCHAR(16) NULL');
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Tracks customer email confirmation; login is blocked until set (see actions/login.php).
 */
function db_ensure_user_email_verified_at_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$dbName, 'users', 'email_verified_at']);
        if (!$chk->fetchColumn()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER created_at');
        }
        // Existing accounts (created before this column) can keep signing in.
        $pdo->exec('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_user_phone_verified_at_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$dbName, 'users', 'phone_verified_at']);
        if (!$chk->fetchColumn()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN phone_verified_at DATETIME NULL DEFAULT NULL AFTER email_verified_at');
        }
        $pdo->exec(
            "UPDATE users SET phone_verified_at = COALESCE(phone_verified_at, created_at)
             WHERE phone_verified_at IS NULL AND TRIM(COALESCE(phone, '')) <> ''"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Ensures admin table exists with one default admin account for first-time setup.
 */
function db_ensure_admin_users_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(120) NOT NULL DEFAULT 'Administrator',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );

        $st = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
        $st->execute(['admin@luxe.com']);
        $row = $st->fetch();
        if (!$row) {
            $ins = $pdo->prepare('INSERT INTO admin_users (email, password_hash, full_name, is_active) VALUES (?, ?, ?, 1)');
            $ins->execute([
                'admin@luxe.com',
                password_hash('admin123', PASSWORD_DEFAULT),
                'System Admin',
            ]);
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_account_deletion_requests(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_account_deletion_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions: rely on manual migrations
    }
}

function db_ensure_seller_account_deletion_requests(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_account_deletion_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions: rely on manual migrations
    }
}

/**
 * Ensures seller table exists with one default seller account.
 */
function db_ensure_seller_users_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_users (
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
            ) ENGINE=InnoDB"
        );

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            $colChk = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $colChk->execute([$dbName, 'seller_users']);
            $haveCols = array_flip($colChk->fetchAll(PDO::FETCH_COLUMN));

            $requiredCols = [
                'business_name' => "ALTER TABLE seller_users ADD COLUMN business_name VARCHAR(150) NOT NULL DEFAULT '' AFTER allowed_categories",
                'gst_number' => "ALTER TABLE seller_users ADD COLUMN gst_number VARCHAR(20) NOT NULL DEFAULT '' AFTER business_name",
                'pan_number' => "ALTER TABLE seller_users ADD COLUMN pan_number VARCHAR(20) NOT NULL DEFAULT '' AFTER gst_number",
                'aadhaar_number' => "ALTER TABLE seller_users ADD COLUMN aadhaar_number VARCHAR(20) NOT NULL DEFAULT '' AFTER pan_number",
                'bank_name' => "ALTER TABLE seller_users ADD COLUMN bank_name VARCHAR(120) NOT NULL DEFAULT '' AFTER aadhaar_number",
                'gst_doc_path' => "ALTER TABLE seller_users ADD COLUMN gst_doc_path VARCHAR(255) NOT NULL DEFAULT '' AFTER bank_name",
                'pan_doc_path' => "ALTER TABLE seller_users ADD COLUMN pan_doc_path VARCHAR(255) NOT NULL DEFAULT '' AFTER gst_doc_path",
                'aadhaar_doc_path' => "ALTER TABLE seller_users ADD COLUMN aadhaar_doc_path VARCHAR(255) NOT NULL DEFAULT '' AFTER pan_doc_path",
                'bank_account_name' => "ALTER TABLE seller_users ADD COLUMN bank_account_name VARCHAR(120) NOT NULL DEFAULT '' AFTER aadhaar_doc_path",
                'bank_account_number' => "ALTER TABLE seller_users ADD COLUMN bank_account_number VARCHAR(40) NOT NULL DEFAULT '' AFTER bank_account_name",
                'bank_ifsc' => "ALTER TABLE seller_users ADD COLUMN bank_ifsc VARCHAR(20) NOT NULL DEFAULT '' AFTER bank_account_number",
                'address_line1' => "ALTER TABLE seller_users ADD COLUMN address_line1 VARCHAR(255) NOT NULL DEFAULT '' AFTER bank_ifsc",
                'city' => "ALTER TABLE seller_users ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '' AFTER address_line1",
                'state' => "ALTER TABLE seller_users ADD COLUMN state VARCHAR(100) NOT NULL DEFAULT '' AFTER city",
                'pin_code' => "ALTER TABLE seller_users ADD COLUMN pin_code VARCHAR(20) NOT NULL DEFAULT '' AFTER state",
                'id_proof_type' => "ALTER TABLE seller_users ADD COLUMN id_proof_type VARCHAR(40) NOT NULL DEFAULT '' AFTER pin_code",
                'id_proof_number' => "ALTER TABLE seller_users ADD COLUMN id_proof_number VARCHAR(80) NOT NULL DEFAULT '' AFTER id_proof_type",
                'phone_number' => "ALTER TABLE seller_users ADD COLUMN phone_number VARCHAR(40) NOT NULL DEFAULT '' AFTER id_proof_number",
                'business_address' => "ALTER TABLE seller_users ADD COLUMN business_address VARCHAR(255) NOT NULL DEFAULT '' AFTER phone_number",
                'logo_path' => "ALTER TABLE seller_users ADD COLUMN logo_path VARCHAR(255) NOT NULL DEFAULT '' AFTER business_address",
                'banner_path' => "ALTER TABLE seller_users ADD COLUMN banner_path VARCHAR(255) NOT NULL DEFAULT '' AFTER logo_path",
                'kyc_completed' => 'ALTER TABLE seller_users ADD COLUMN kyc_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER banner_path',
                'kyc_updated_at' => 'ALTER TABLE seller_users ADD COLUMN kyc_updated_at DATETIME NULL AFTER kyc_completed',
                'kyc_final_approved' => 'ALTER TABLE seller_users ADD COLUMN kyc_final_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER kyc_updated_at',
                'kyc_final_reviewed_by' => 'ALTER TABLE seller_users ADD COLUMN kyc_final_reviewed_by INT UNSIGNED NULL AFTER kyc_final_approved',
                'kyc_final_reviewed_at' => 'ALTER TABLE seller_users ADD COLUMN kyc_final_reviewed_at DATETIME NULL AFTER kyc_final_reviewed_by',
                'kyc_rejection_reason' => "ALTER TABLE seller_users ADD COLUMN kyc_rejection_reason VARCHAR(255) NOT NULL DEFAULT '' AFTER kyc_final_reviewed_at",
                'kyc_edit_request_status' => "ALTER TABLE seller_users ADD COLUMN kyc_edit_request_status VARCHAR(16) NOT NULL DEFAULT 'none' AFTER kyc_rejection_reason",
                'kyc_edit_requested_at' => 'ALTER TABLE seller_users ADD COLUMN kyc_edit_requested_at DATETIME NULL AFTER kyc_edit_request_status',
                'kyc_edit_reviewed_by' => 'ALTER TABLE seller_users ADD COLUMN kyc_edit_reviewed_by INT UNSIGNED NULL AFTER kyc_edit_requested_at',
                'kyc_edit_reviewed_at' => 'ALTER TABLE seller_users ADD COLUMN kyc_edit_reviewed_at DATETIME NULL AFTER kyc_edit_reviewed_by',
                'kyc_edit_rejection_reason' => "ALTER TABLE seller_users ADD COLUMN kyc_edit_rejection_reason VARCHAR(255) NOT NULL DEFAULT '' AFTER kyc_edit_reviewed_at",
                'kyc_edit_unlocked' => 'ALTER TABLE seller_users ADD COLUMN kyc_edit_unlocked TINYINT(1) NOT NULL DEFAULT 0 AFTER kyc_edit_rejection_reason',
            ];

            foreach ($requiredCols as $column => $query) {
                if (!isset($haveCols[$column])) {
                    $pdo->exec($query);
                }
            }
        }

        $st = $pdo->prepare('SELECT id FROM seller_users WHERE email = ? LIMIT 1');
        $st->execute(['seller@luxe.com']);
        $row = $st->fetch();
        if (!$row) {
            $delChk = $pdo->prepare(
                "SELECT id
                 FROM seller_account_deletion_requests
                 WHERE email = ? AND status = 'approved'
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $delChk->execute(['seller@luxe.com']);
            $wasDeleted = (bool) $delChk->fetchColumn();
            if (!$wasDeleted) {
                $ins = $pdo->prepare('INSERT INTO seller_users (email, password_hash, full_name, allowed_categories, is_active) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([
                    'seller@luxe.com',
                    password_hash('seller123', PASSWORD_DEFAULT),
                    'Default Seller',
                    'fashion,electronics',
                ]);
            }
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Ensures products table can map product -> seller.
 */
function db_ensure_products_seller_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }

        $colChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $colChk->execute([$dbName, 'products', 'seller_id']);
        $hasSellerId = (bool) $colChk->fetchColumn();
        if (!$hasSellerId) {
            $pdo->exec('ALTER TABLE products ADD COLUMN seller_id INT UNSIGNED NULL AFTER id');
        }

        $imgChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $imgChk->execute([$dbName, 'products', 'image_path']);
        $hasImagePath = (bool) $imgChk->fetchColumn();
        if (!$hasImagePath) {
            $pdo->exec('ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER image_bg');
        }

        $sizeChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $sizeChk->execute([$dbName, 'products', 'size_options']);
        $hasSizeOptions = (bool) $sizeChk->fetchColumn();
        if (!$hasSizeOptions) {
            $pdo->exec("ALTER TABLE products ADD COLUMN size_options VARCHAR(255) NOT NULL DEFAULT '' AFTER image_path");
        }

        $colorChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $colorChk->execute([$dbName, 'products', 'color_options']);
        $hasColorOptions = (bool) $colorChk->fetchColumn();
        if (!$hasColorOptions) {
            $pdo->exec("ALTER TABLE products ADD COLUMN color_options VARCHAR(255) NOT NULL DEFAULT '' AFTER size_options");
        }

        $stockChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stockChk->execute([$dbName, 'products', 'stock_qty']);
        $hasStockQty = (bool) $stockChk->fetchColumn();
        if (!$hasStockQty) {
            $pdo->exec('ALTER TABLE products ADD COLUMN stock_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER color_options');
        }

        $skuChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $skuChk->execute([$dbName, 'products', 'sku']);
        $hasSku = (bool) $skuChk->fetchColumn();
        if (!$hasSku) {
            $pdo->exec('ALTER TABLE products ADD COLUMN sku VARCHAR(80) NULL AFTER slug');
        }

        $productTypeChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $productTypeChk->execute([$dbName, 'products', 'product_type']);
        if (!(bool) $productTypeChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN product_type VARCHAR(64) NOT NULL DEFAULT '' AFTER category");
        }

        $genderChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $genderChk->execute([$dbName, 'products', 'gender']);
        if (!(bool) $genderChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN gender VARCHAR(16) NOT NULL DEFAULT 'unisex' AFTER product_type");
        }

        $offerFlashChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $offerFlashChk->execute([$dbName, 'products', 'offer_flash_text']);
        $hasOfferFlashText = (bool) $offerFlashChk->fetchColumn();
        if (!$hasOfferFlashText) {
            $pdo->exec("ALTER TABLE products ADD COLUMN offer_flash_text VARCHAR(150) NOT NULL DEFAULT '' AFTER description");
        }

        $offerCountdownChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $offerCountdownChk->execute([$dbName, 'products', 'offer_countdown_seconds']);
        $hasOfferCountdown = (bool) $offerCountdownChk->fetchColumn();
        if (!$hasOfferCountdown) {
            $pdo->exec('ALTER TABLE products ADD COLUMN offer_countdown_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER offer_flash_text');
        }

        $offerBankChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $offerBankChk->execute([$dbName, 'products', 'offer_bank_text']);
        $hasOfferBankText = (bool) $offerBankChk->fetchColumn();
        if (!$hasOfferBankText) {
            $pdo->exec("ALTER TABLE products ADD COLUMN offer_bank_text VARCHAR(150) NOT NULL DEFAULT '' AFTER offer_countdown_seconds");
        }

        $shipClassChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $shipClassChk->execute([$dbName, 'products', 'shipping_class']);
        if (!(bool) $shipClassChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN shipping_class VARCHAR(32) NOT NULL DEFAULT 'standard' AFTER offer_bank_text");
        }

        $mfgGenericChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $mfgGenericChk->execute([$dbName, 'products', 'manufacturer_generic_name']);
        if (!(bool) $mfgGenericChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN manufacturer_generic_name VARCHAR(255) NOT NULL DEFAULT '' AFTER shipping_class");
        }

        $mfgCountryChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $mfgCountryChk->execute([$dbName, 'products', 'manufacturer_country']);
        if (!(bool) $mfgCountryChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN manufacturer_country VARCHAR(128) NOT NULL DEFAULT '' AFTER manufacturer_generic_name");
        }

        $mfgAddrChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $mfgAddrChk->execute([$dbName, 'products', 'manufacturer_name_address']);
        if (!(bool) $mfgAddrChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN manufacturer_name_address VARCHAR(2000) NOT NULL DEFAULT '' AFTER manufacturer_country");
        }

        $packerAddrChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $packerAddrChk->execute([$dbName, 'products', 'packer_name_address']);
        if (!(bool) $packerAddrChk->fetchColumn()) {
            $pdo->exec("ALTER TABLE products ADD COLUMN packer_name_address VARCHAR(2000) NOT NULL DEFAULT '' AFTER manufacturer_name_address");
        }

        $approvalChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $approvalChk->execute([$dbName, 'products', 'approval_status']);
        $hasApprovalStatus = (bool) $approvalChk->fetchColumn();
        if (!$hasApprovalStatus) {
            $pdo->exec("ALTER TABLE products ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER active");
            $idxApprovalChk = $pdo->prepare(
                'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $idxApprovalChk->execute([$dbName, 'products', 'idx_products_approval_status']);
            if (!(bool) $idxApprovalChk->fetchColumn()) {
                $pdo->exec('ALTER TABLE products ADD INDEX idx_products_approval_status (approval_status)');
            }
        }

        $idxChk = $pdo->prepare(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $idxChk->execute([$dbName, 'products', 'idx_products_seller_id']);
        $hasSellerIdx = (bool) $idxChk->fetchColumn();
        if (!$hasSellerIdx) {
            $pdo->exec('ALTER TABLE products ADD INDEX idx_products_seller_id (seller_id)');
        }

        $skuIdxChk = $pdo->prepare(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $skuIdxChk->execute([$dbName, 'products', 'uq_products_sku']);
        $hasSkuIdx = (bool) $skuIdxChk->fetchColumn();
        if (!$hasSkuIdx) {
            $pdo->exec('ALTER TABLE products ADD UNIQUE KEY uq_products_sku (sku)');
        }

        $fkChk = $pdo->prepare(
            'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?'
        );
        $fkChk->execute([$dbName, 'products', 'seller_id', 'seller_users']);
        $hasFk = (bool) $fkChk->fetchColumn();
        if (!$hasFk) {
            $pdo->exec('ALTER TABLE products ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE SET NULL');
        }

        $createdAtChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $createdAtChk->execute([$dbName, 'products', 'created_at']);
        if (!(bool) $createdAtChk->fetchColumn()) {
            $pdo->exec(
                "ALTER TABLE products ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER approval_status"
            );
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Ensures seller account creation requests table exists.
 */
function db_ensure_seller_create_requests_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_create_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }

        $colChk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $colChk->execute([$dbName, 'seller_create_requests']);
        $haveCols = array_flip($colChk->fetchAll(PDO::FETCH_COLUMN));

        $requiredCols = [
            'requested_password_hash' => "ALTER TABLE seller_create_requests ADD COLUMN requested_password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER phone",
            'business_name' => "ALTER TABLE seller_create_requests ADD COLUMN business_name VARCHAR(150) NOT NULL DEFAULT '' AFTER note",
            'gst_number' => "ALTER TABLE seller_create_requests ADD COLUMN gst_number VARCHAR(20) NOT NULL DEFAULT '' AFTER business_name",
            'pan_number' => "ALTER TABLE seller_create_requests ADD COLUMN pan_number VARCHAR(20) NOT NULL DEFAULT '' AFTER gst_number",
            'aadhaar_number' => "ALTER TABLE seller_create_requests ADD COLUMN aadhaar_number VARCHAR(20) NOT NULL DEFAULT '' AFTER pan_number",
            'bank_account_name' => "ALTER TABLE seller_create_requests ADD COLUMN bank_account_name VARCHAR(120) NOT NULL DEFAULT '' AFTER aadhaar_number",
            'bank_account_number' => "ALTER TABLE seller_create_requests ADD COLUMN bank_account_number VARCHAR(40) NOT NULL DEFAULT '' AFTER bank_account_name",
            'bank_ifsc' => "ALTER TABLE seller_create_requests ADD COLUMN bank_ifsc VARCHAR(20) NOT NULL DEFAULT '' AFTER bank_account_number",
            'address_line1' => "ALTER TABLE seller_create_requests ADD COLUMN address_line1 VARCHAR(255) NOT NULL DEFAULT '' AFTER bank_ifsc",
            'city' => "ALTER TABLE seller_create_requests ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '' AFTER address_line1",
            'state' => "ALTER TABLE seller_create_requests ADD COLUMN state VARCHAR(100) NOT NULL DEFAULT '' AFTER city",
            'pin_code' => "ALTER TABLE seller_create_requests ADD COLUMN pin_code VARCHAR(20) NOT NULL DEFAULT '' AFTER state",
            'id_proof_type' => "ALTER TABLE seller_create_requests ADD COLUMN id_proof_type VARCHAR(40) NOT NULL DEFAULT '' AFTER pin_code",
            'id_proof_number' => "ALTER TABLE seller_create_requests ADD COLUMN id_proof_number VARCHAR(80) NOT NULL DEFAULT '' AFTER id_proof_type",
        ];

        foreach ($requiredCols as $column => $query) {
            if (!isset($haveCols[$column])) {
                $pdo->exec($query);
            }
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Ensures product images table exists for multi-image support.
 */
function db_ensure_product_images_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_product_images_product (product_id, sort_order, id),
                CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

/**
 * Ensures product variant inventory table exists for size/color wise stock management.
 */
function db_ensure_product_variant_inventory_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_variant_inventory (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_bank_accounts_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_bank_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                seller_id INT UNSIGNED NOT NULL,
                bank_name VARCHAR(120) NOT NULL,
                account_holder_name VARCHAR(120) NOT NULL,
                account_number VARCHAR(40) NOT NULL,
                ifsc VARCHAR(20) NOT NULL,
                upi_id VARCHAR(100) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_seller_bank_account (seller_id, account_number),
                KEY idx_seller_bank_accounts_seller (seller_id, created_at),
                CONSTRAINT fk_seller_bank_accounts_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            $colChk = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $colChk->execute([$dbName, 'seller_bank_accounts', 'upi_id']);
            if ((int) $colChk->fetchColumn() === 0) {
                $pdo->exec(
                    "ALTER TABLE seller_bank_accounts ADD COLUMN upi_id VARCHAR(100) NOT NULL DEFAULT '' AFTER ifsc"
                );
            }
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_payment_gateway_configs_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_payment_gateway_configs (
                seller_id INT UNSIGNED PRIMARY KEY,
                gateway VARCHAR(32) NOT NULL DEFAULT 'none',
                mode VARCHAR(8) NOT NULL DEFAULT 'test',
                public_key VARCHAR(255) NOT NULL DEFAULT '',
                secret_key VARCHAR(255) NOT NULL DEFAULT '',
                merchant_id VARCHAR(120) NOT NULL DEFAULT '',
                webhook_secret VARCHAR(255) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_seller_pgw_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_platform_payment_gateway_config_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS platform_payment_gateway_config (
                id TINYINT UNSIGNED PRIMARY KEY,
                gateway VARCHAR(32) NOT NULL DEFAULT 'none',
                mode VARCHAR(8) NOT NULL DEFAULT 'test',
                public_key VARCHAR(255) NOT NULL DEFAULT '',
                secret_key VARCHAR(255) NOT NULL DEFAULT '',
                merchant_id VARCHAR(120) NOT NULL DEFAULT '',
                webhook_secret VARCHAR(255) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $cnt = (int) $pdo->query('SELECT COUNT(*) FROM platform_payment_gateway_config')->fetchColumn();
        if ($cnt === 0) {
            $pdo->exec("INSERT INTO platform_payment_gateway_config (id) VALUES (1)");
        }
        try {
            $plat = $pdo->query(
                "SELECT gateway, public_key FROM platform_payment_gateway_config WHERE id = 1 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if (
                $plat
                && (string) ($plat['gateway'] ?? '') === 'none'
                && trim((string) ($plat['public_key'] ?? '')) === ''
            ) {
                $leg = $pdo->query(
                    "SELECT gateway, mode, public_key, secret_key, merchant_id, webhook_secret
                     FROM seller_payment_gateway_configs
                     WHERE gateway IS NOT NULL AND gateway <> '' AND gateway <> 'none'
                     ORDER BY updated_at DESC
                     LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($leg) {
                    $u = $pdo->prepare(
                        'UPDATE platform_payment_gateway_config SET
                            gateway = ?, mode = ?, public_key = ?, secret_key = ?,
                            merchant_id = ?, webhook_secret = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = 1'
                    );
                    $u->execute([
                        (string) $leg['gateway'],
                        (string) $leg['mode'],
                        (string) $leg['public_key'],
                        (string) $leg['secret_key'],
                        (string) $leg['merchant_id'],
                        (string) $leg['webhook_secret'],
                    ]);
                }
            }
        } catch (Throwable) {
            /* ignore legacy migration */
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_withdraw_requests_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_withdraw_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_shipping_settings_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_shipping_settings (
                seller_id INT UNSIGNED PRIMARY KEY,
                handling_time_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
                default_shipping_fee INT UNSIGNED NOT NULL DEFAULT 0,
                free_shipping_min_order INT UNSIGNED NOT NULL DEFAULT 0,
                shipping_regions VARCHAR(255) NOT NULL DEFAULT 'All India',
                cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
                shipping_policy TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_seller_shipping_settings_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_delivery_options_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_delivery_options (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_return_settings_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_return_settings (
                seller_id INT UNSIGNED PRIMARY KEY,
                return_window_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
                return_conditions TEXT NULL,
                refund_method VARCHAR(40) NOT NULL DEFAULT 'original_payment',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_seller_return_settings_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_seller_coupons_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS seller_coupons (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_product_reviews_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_reviews (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            $colChk = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $colChk->execute([$dbName, 'product_reviews']);
            $haveCols = array_flip($colChk->fetchAll(PDO::FETCH_COLUMN));

            $requiredCols = [
                'review_status' => "ALTER TABLE product_reviews ADD COLUMN review_status VARCHAR(16) NOT NULL DEFAULT 'pending' AFTER review_text",
                'seller_reviewed_at' => 'ALTER TABLE product_reviews ADD COLUMN seller_reviewed_at DATETIME NULL AFTER seller_response',
            ];
            foreach ($requiredCols as $column => $query) {
                if (!isset($haveCols[$column])) {
                    $pdo->exec($query);
                }
            }
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_user_return_requests_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_return_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_user_order_cancel_requests_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_order_cancel_requests (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_user_order_enquiries_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_order_enquiries (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                seller_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NOT NULL,
                order_item_id INT UNSIGNED NOT NULL,
                order_ref VARCHAR(32) NOT NULL,
                product_id INT UNSIGNED NULL,
                product_name VARCHAR(255) NOT NULL DEFAULT '',
                message VARCHAR(1000) NOT NULL DEFAULT '',
                seller_reply VARCHAR(1000) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                replied_at DATETIME NULL,
                KEY idx_user_order_enquiry_user (user_id, created_at),
                KEY idx_user_order_enquiry_seller (seller_id, created_at),
                KEY idx_user_order_enquiry_order (order_id, order_item_id, seller_id),
                CONSTRAINT fk_user_order_enquiry_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_order_enquiry_seller FOREIGN KEY (seller_id) REFERENCES seller_users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_order_enquiry_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_order_enquiry_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL: rely on manual migrations
    }
}

function db_ensure_orders_platform_fee_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$dbName, 'orders', 'platform_fee_rupees']);
        if ($chk->fetchColumn()) {
            return;
        }
        $pdo->exec(
            'ALTER TABLE orders ADD COLUMN platform_fee_rupees INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_amount'
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL
    }
}

function db_ensure_orders_delivered_at_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$dbName, 'orders', 'delivered_at']);
        if (!$chk->fetchColumn()) {
            $pdo->exec(
                'ALTER TABLE orders ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER shipping_address'
            );
        }
        $pdo->exec(
            "UPDATE orders SET delivered_at = COALESCE(delivered_at, created_at)
             WHERE status = 'delivered' AND delivered_at IS NULL"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL
    }
}

function db_ensure_orders_status_time_columns(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );

        $columns = [
            'confirmed_at' => "ALTER TABLE orders ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL AFTER created_at",
            'shipped_at' => "ALTER TABLE orders ADD COLUMN shipped_at DATETIME NULL DEFAULT NULL AFTER confirmed_at",
            'out_for_delivery_at' => "ALTER TABLE orders ADD COLUMN out_for_delivery_at DATETIME NULL DEFAULT NULL AFTER shipped_at",
        ];

        foreach ($columns as $col => $sql) {
            $chk->execute([$dbName, 'orders', $col]);
            if (!$chk->fetchColumn()) {
                $pdo->exec($sql);
            }
        }
    } catch (Throwable) {
        // Missing permissions or non-MySQL
    }
}

function db_ensure_order_items_status_columns(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );

        $columns = [
            'status' => "ALTER TABLE order_items ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'processing' AFTER qty",
            'confirmed_at' => 'ALTER TABLE order_items ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL AFTER status',
            'shipped_at' => 'ALTER TABLE order_items ADD COLUMN shipped_at DATETIME NULL DEFAULT NULL AFTER confirmed_at',
            'out_for_delivery_at' => 'ALTER TABLE order_items ADD COLUMN out_for_delivery_at DATETIME NULL DEFAULT NULL AFTER shipped_at',
            'delivered_at' => 'ALTER TABLE order_items ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER out_for_delivery_at',
        ];

        foreach ($columns as $col => $sql) {
            $chk->execute([$dbName, 'order_items', $col]);
            if (!$chk->fetchColumn()) {
                $pdo->exec($sql);
            }
        }

        // Backfill old data so line-item progress works immediately.
        $pdo->exec(
            "UPDATE order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             SET
                oi.status = CASE
                    WHEN TRIM(COALESCE(oi.status, '')) = '' THEN LOWER(TRIM(COALESCE(o.status, 'processing')))
                    ELSE LOWER(TRIM(oi.status))
                END,
                oi.confirmed_at = COALESCE(oi.confirmed_at, o.confirmed_at),
                oi.shipped_at = COALESCE(oi.shipped_at, o.shipped_at),
                oi.out_for_delivery_at = COALESCE(oi.out_for_delivery_at, o.out_for_delivery_at),
                oi.delivered_at = COALESCE(oi.delivered_at, o.delivered_at)
             WHERE oi.order_id = o.id"
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL
    }
}

function db_ensure_user_loyalty_redeemed_column(PDO $pdo): void
{
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$dbName, 'users', 'loyalty_points_redeemed']);
        if ($chk->fetchColumn()) {
            return;
        }
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN loyalty_points_redeemed INT UNSIGNED NOT NULL DEFAULT 0'
        );
    } catch (Throwable) {
        // Missing permissions or non-MySQL
    }
}

function db_ensure_site_settings_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL DEFAULT '',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $st = $pdo->prepare('SELECT 1 FROM site_settings WHERE setting_key = ? LIMIT 1');
        $st->execute(['platform_fee_rupees']);
        if (!$st->fetch()) {
            $ins = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
            $ins->execute(['platform_fee_rupees', '3']);
        }
        $st->execute(['cart_free_shipping_min_rupees']);
        if (!$st->fetch()) {
            $ins = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
            $ins->execute(['cart_free_shipping_min_rupees', '1000']);
        }
        $st->execute(['cart_below_min_shipping_fee_rupees']);
        if (!$st->fetch()) {
            $ins = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
            $ins->execute(['cart_below_min_shipping_fee_rupees', '50']);
        }
    } catch (Throwable) {
        // Missing permissions: rely on manual migrations
    }
}

function db_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_readable($path)) {
        throw new RuntimeException('Missing includes/config.php — copy includes/config.example.php');
    }
    $cfg = require $path;
    return $cfg['db'];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['port'],
        $c['name'],
        $c['charset']
    );
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    db_ensure_user_profile_columns($pdo);
    db_ensure_user_email_verified_at_column($pdo);
    db_ensure_user_phone_verified_at_column($pdo);
    db_ensure_admin_users_table($pdo);
    db_ensure_account_deletion_requests($pdo);
    db_ensure_seller_account_deletion_requests($pdo);
    db_ensure_seller_users_table($pdo);
    db_ensure_products_seller_column($pdo);
    db_ensure_seller_create_requests_table($pdo);
    db_ensure_product_images_table($pdo);
    db_ensure_product_variant_inventory_table($pdo);
    db_ensure_seller_withdraw_requests_table($pdo);
    db_ensure_seller_bank_accounts_table($pdo);
    db_ensure_seller_payment_gateway_configs_table($pdo);
    db_ensure_platform_payment_gateway_config_table($pdo);
    db_ensure_seller_shipping_settings_table($pdo);
    db_ensure_seller_delivery_options_table($pdo);
    db_ensure_seller_return_settings_table($pdo);
    db_ensure_seller_coupons_table($pdo);
    db_ensure_product_reviews_table($pdo);
    db_ensure_user_return_requests_table($pdo);
    db_ensure_user_order_cancel_requests_table($pdo);
    db_ensure_user_order_enquiries_table($pdo);
    db_ensure_site_settings_table($pdo);
    db_ensure_orders_platform_fee_column($pdo);
    db_ensure_orders_delivered_at_column($pdo);
    db_ensure_orders_status_time_columns($pdo);
    db_ensure_order_items_status_columns($pdo);
    db_ensure_user_loyalty_redeemed_column($pdo);
    return $pdo;
}
