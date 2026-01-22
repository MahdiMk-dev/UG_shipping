CREATE DATABASE IF NOT EXISTS ug_shipping
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ug_shipping;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS countries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    iso2 CHAR(2) NOT NULL,
    iso3 CHAR(3) NULL,
    phone_code VARCHAR(10) NULL,
    UNIQUE KEY uk_countries_name (name),
    UNIQUE KEY uk_countries_iso2 (iso2)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_type ENUM('admin','branch','staff','supplier') NOT NULL,
    owner_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    account_type VARCHAR(80) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    payment_method_id INT UNSIGNED NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_accounts_owner (owner_type, owner_id),
    KEY idx_accounts_active (is_active),
    KEY idx_accounts_method (payment_method_id),
    CONSTRAINT fk_accounts_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT UNSIGNED NULL,
    to_account_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    entry_type ENUM(
        'customer_payment',
        'branch_transfer',
        'supplier_transaction',
        'staff_expense',
        'general_expense',
        'shipment_expense',
        'adjustment',
        'other'
    ) NOT NULL,
    transfer_date DATE NULL,
    note TEXT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    status ENUM('active','canceled') NOT NULL DEFAULT 'active',
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    KEY idx_account_transfers_from (from_account_id),
    KEY idx_account_transfers_to (to_account_id),
    KEY idx_account_transfers_type (entry_type),
    KEY idx_account_transfers_ref (reference_type, reference_id),
    CONSTRAINT fk_account_transfers_from
        FOREIGN KEY (from_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_account_transfers_to
        FOREIGN KEY (to_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    transfer_id INT UNSIGNED NULL,
    entry_type ENUM(
        'customer_payment',
        'branch_transfer',
        'supplier_transaction',
        'staff_expense',
        'general_expense',
        'shipment_expense',
        'adjustment',
        'other'
    ) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    entry_date DATE NULL,
    status ENUM('active','canceled') NOT NULL DEFAULT 'active',
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    KEY idx_account_entries_account (account_id),
    KEY idx_account_entries_transfer (transfer_id),
    KEY idx_account_entries_type (entry_type),
    CONSTRAINT fk_account_entries_account
        FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_account_entries_transfer
        FOREIGN KEY (transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    type ENUM('deposit','withdrawal') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    title VARCHAR(160) NOT NULL,
    note TEXT NULL,
    adjustment_date DATE NULL,
    account_transfer_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    KEY idx_account_adjustments_account (account_id),
    KEY idx_account_adjustments_transfer (account_transfer_id),
    CONSTRAINT fk_account_adjustments_account
        FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_account_adjustments_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

UPDATE accounts
SET owner_type = 'supplier'
WHERE owner_type NOT IN ('admin', 'branch', 'staff', 'supplier');

UPDATE account_transfers
SET entry_type = 'supplier_transaction'
WHERE entry_type NOT IN (
    'customer_payment',
    'branch_transfer',
    'supplier_transaction',
    'staff_expense',
    'general_expense',
    'shipment_expense',
    'adjustment',
    'other'
);

UPDATE account_entries
SET entry_type = 'supplier_transaction'
WHERE entry_type NOT IN (
    'customer_payment',
    'branch_transfer',
    'supplier_transaction',
    'staff_expense',
    'general_expense',
    'shipment_expense',
    'adjustment',
    'other'
);

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'accounts'
          AND COLUMN_NAME = 'payment_method_id'
    ),
    'SELECT 1',
    'ALTER TABLE accounts ADD COLUMN payment_method_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'accounts'
          AND COLUMN_NAME = 'currency'
    ),
    'SELECT 1',
    'ALTER TABLE accounts ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''USD'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'accounts'
          AND INDEX_NAME = 'idx_accounts_method'
    ),
    'SELECT 1',
    'ALTER TABLE accounts ADD KEY idx_accounts_method (payment_method_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'accounts'
          AND CONSTRAINT_NAME = 'fk_accounts_payment_method'
    ),
    'SELECT 1',
    'ALTER TABLE accounts ADD CONSTRAINT fk_accounts_payment_method '
        'FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS goods_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    name_active VARCHAR(160)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN name ELSE NULL END) STORED,
    UNIQUE KEY uk_goods_types_name_active (name_active),
    KEY idx_goods_types_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type ENUM('head','main','sub','warehouse') NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    parent_branch_id INT UNSIGNED NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_branches_country (country_id),
    KEY idx_branches_parent (parent_branch_id),
    CONSTRAINT fk_branches_country
        FOREIGN KEY (country_id) REFERENCES countries(id),
    CONSTRAINT fk_branches_parent
        FOREIGN KEY (parent_branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

ALTER TABLE branches MODIFY type ENUM('head','main','sub','warehouse') NOT NULL;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_users_username (username),
    KEY idx_users_role (role_id),
    KEY idx_users_branch (branch_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    meta_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_user (user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action (action),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(120) NULL,
    website VARCHAR(120) NULL,
    logo_url VARCHAR(255) NULL,
    points_price DECIMAL(12,2) NULL,
    points_value DECIMAL(12,2) NULL,
    usd_to_lbp DECIMAL(12,2) NULL,
    domain_expiry DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'company_settings'
          AND COLUMN_NAME = 'points_price'
    ),
    'SELECT 1',
    'ALTER TABLE company_settings ADD COLUMN points_price DECIMAL(12,2) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'company_settings'
          AND COLUMN_NAME = 'points_value'
    ),
    'SELECT 1',
    'ALTER TABLE company_settings ADD COLUMN points_value DECIMAL(12,2) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'company_settings'
          AND COLUMN_NAME = 'usd_to_lbp'
    ),
    'SELECT 1',
    'ALTER TABLE company_settings ADD COLUMN usd_to_lbp DECIMAL(12,2) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'company_settings'
          AND COLUMN_NAME = 'domain_expiry'
    ),
    'SELECT 1',
    'ALTER TABLE company_settings ADD COLUMN domain_expiry DATE NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS customer_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(40) NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    sub_branch_id INT UNSIGNED NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_customer_accounts_username (username),
    UNIQUE KEY uk_customer_accounts_phone (phone),
    KEY idx_customer_accounts_branch (sub_branch_id),
    CONSTRAINT fk_customer_accounts_branch
        FOREIGN KEY (sub_branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(50) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    note TEXT NULL,
    sub_branch_id INT UNSIGNED NULL,
    profile_country_id INT UNSIGNED NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    points_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_system TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    code_active VARCHAR(50)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN code ELSE NULL END) STORED,
    profile_country_active VARCHAR(80)
        GENERATED ALWAYS AS (
            CASE WHEN deleted_at IS NULL THEN CONCAT(account_id, ':', profile_country_id) ELSE NULL END
        ) STORED,
    UNIQUE KEY uk_customers_code_active (code_active),
    UNIQUE KEY uk_customers_account_country_active (profile_country_active),
    KEY idx_customers_account (account_id),
    KEY idx_customers_sub_branch (sub_branch_id),
    KEY idx_customers_profile_country (profile_country_id),
    CONSTRAINT fk_customers_account
        FOREIGN KEY (account_id) REFERENCES customer_accounts(id),
    CONSTRAINT fk_customers_sub_branch
        FOREIGN KEY (sub_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_customers_profile_country
        FOREIGN KEY (profile_country_id) REFERENCES countries(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_auth (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_customer_auth_customer (customer_id),
    UNIQUE KEY uk_customer_auth_username (username),
    CONSTRAINT fk_customer_auth_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supplier_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('shipper','consignee') NOT NULL,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    note TEXT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_supplier_profiles_type (type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supplier_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    invoice_no VARCHAR(80) NOT NULL,
    status ENUM('open','partially_paid','paid','void') NOT NULL DEFAULT 'open',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    rate_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
    rate_cbm DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_weight DECIMAL(12,3) NOT NULL DEFAULT 0,
    total_volume DECIMAL(12,3) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_total DECIMAL(12,2) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by_user_id INT UNSIGNED NULL,
    note TEXT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    invoice_no_active VARCHAR(80)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN invoice_no ELSE NULL END) STORED,
    supplier_shipment_active VARCHAR(80)
        GENERATED ALWAYS AS (
            CASE
                WHEN deleted_at IS NULL AND shipment_id IS NOT NULL THEN CONCAT(supplier_id, ':', shipment_id)
                ELSE NULL
            END
        ) STORED,
    UNIQUE KEY uk_supplier_invoices_no_active (invoice_no_active),
    UNIQUE KEY uk_supplier_invoices_supplier_shipment_active (supplier_shipment_active),
    KEY idx_supplier_invoices_supplier (supplier_id),
    KEY idx_supplier_invoices_shipment (shipment_id),
    CONSTRAINT fk_supplier_invoices_supplier
        FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'currency'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''USD'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'rate_kg'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN rate_kg DECIMAL(12,2) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'rate_cbm'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN rate_cbm DECIMAL(12,2) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'total_weight'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN total_weight DECIMAL(12,3) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'total_volume'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN total_volume DECIMAL(12,3) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS supplier_invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_supplier_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_supplier_invoice_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES supplier_invoices(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supplier_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NULL,
    branch_id INT UNSIGNED NULL,
    type ENUM('invoice_create','invoice_regenerate','payment','refund','adjustment','charge','discount') NOT NULL DEFAULT 'payment',
    status ENUM('active','canceled') NOT NULL DEFAULT 'active',
    payment_method_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NULL,
    reason VARCHAR(80) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    account_transfer_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_supplier_transactions_supplier (supplier_id),
    KEY idx_supplier_transactions_invoice (invoice_id),
    KEY idx_supplier_transactions_branch (branch_id),
    KEY idx_supplier_transactions_method (payment_method_id),
    KEY idx_supplier_transactions_account_transfer (account_transfer_id),
    CONSTRAINT fk_supplier_transactions_supplier
        FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id),
    CONSTRAINT fk_supplier_transactions_invoice
        FOREIGN KEY (invoice_id) REFERENCES supplier_invoices(id),
    CONSTRAINT fk_supplier_transactions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_supplier_transactions_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    CONSTRAINT fk_supplier_transactions_account_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

UPDATE supplier_transactions
SET type = 'payment'
WHERE type NOT IN ('invoice_create','invoice_regenerate','payment','refund','adjustment','charge','discount');

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'type'
          AND COLUMN_TYPE LIKE '%receipt%'
    ),
    'ALTER TABLE supplier_transactions MODIFY type '
        'ENUM('
            '\'invoice_create\','
            '\'invoice_regenerate\','
            '\'payment\','
            '\'refund\','
            '\'adjustment\','
            '\'charge\','
            '\'discount\''
        ') NOT NULL DEFAULT \'payment\'',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'payment_method_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE supplier_transactions MODIFY payment_method_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS supplier_transaction_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_supplier_transaction_items_tx (transaction_id),
    CONSTRAINT fk_supplier_transaction_items_tx
        FOREIGN KEY (transaction_id) REFERENCES supplier_transactions(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_profiles'
          AND COLUMN_NAME = 'note'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_profiles ADD COLUMN note TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_profiles'
          AND CONSTRAINT_NAME = 'fk_supplier_profiles_country'
    ),
    'ALTER TABLE supplier_profiles DROP FOREIGN KEY fk_supplier_profiles_country',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_profiles'
          AND INDEX_NAME = 'idx_supplier_profiles_country'
    ),
    'ALTER TABLE supplier_profiles DROP INDEX idx_supplier_profiles_country',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_profiles'
          AND COLUMN_NAME = 'country_id'
    ),
    'ALTER TABLE supplier_profiles DROP COLUMN country_id',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'supplier_shipment_active'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN supplier_shipment_active VARCHAR(80) GENERATED ALWAYS AS ('
        'CASE '
            'WHEN deleted_at IS NULL AND shipment_id IS NOT NULL THEN CONCAT(supplier_id, \':\', shipment_id) '
            'ELSE NULL '
        'END'
        ') STORED'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND INDEX_NAME = 'uk_supplier_invoices_supplier_shipment_active'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD UNIQUE KEY uk_supplier_invoices_supplier_shipment_active (supplier_shipment_active)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'branch_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE supplier_transactions MODIFY branch_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'reason'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN reason VARCHAR(80) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'canceled_at'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN canceled_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'canceled_reason'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN canceled_reason TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'canceled_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN canceled_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'status'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN status ENUM(\'active\',\'canceled\') NOT NULL DEFAULT \'active\''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'canceled_at'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN canceled_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'canceled_reason'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN canceled_reason TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'canceled_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN canceled_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'note'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN note TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'account_id'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN account_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'profile_country_id'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN profile_country_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'points_balance'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN points_balance DECIMAL(12,2) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'profile_country_active'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN profile_country_active VARCHAR(80) GENERATED ALWAYS AS ('
        'CASE WHEN deleted_at IS NULL THEN CONCAT(account_id, \':\', profile_country_id) ELSE NULL END'
        ') STORED'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND INDEX_NAME = 'uk_customers_account_country_active'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD UNIQUE KEY uk_customers_account_country_active (profile_country_active)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND INDEX_NAME = 'idx_customers_account'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD KEY idx_customers_account (account_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customers'
          AND INDEX_NAME = 'idx_customers_profile_country'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD KEY idx_customers_profile_country (profile_country_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_auth'
    ),
    'INSERT INTO customer_accounts (phone, username, password_hash, sub_branch_id, last_login_at, created_at, '
        'created_by_user_id, updated_at, updated_by_user_id, deleted_at) '
        'SELECT c.phone, ca.username, ca.password_hash, c.sub_branch_id, ca.last_login_at, ca.created_at, '
        'ca.created_by_user_id, ca.updated_at, ca.updated_by_user_id, ca.deleted_at '
        'FROM customer_auth ca '
        'JOIN customers c ON c.id = ca.customer_id '
        'WHERE ca.deleted_at IS NULL '
        'ON DUPLICATE KEY UPDATE '
        'phone = VALUES(phone), '
        'password_hash = VALUES(password_hash), '
        'sub_branch_id = VALUES(sub_branch_id), '
        'last_login_at = VALUES(last_login_at), '
        'updated_at = VALUES(updated_at), '
        'updated_by_user_id = VALUES(updated_by_user_id)',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE customers c
JOIN customer_auth ca ON ca.customer_id = c.id
JOIN customer_accounts a ON a.username = ca.username
SET c.account_id = a.id
WHERE c.account_id IS NULL;


CREATE TABLE IF NOT EXISTS shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(80) NOT NULL,
    origin_country_id INT UNSIGNED NOT NULL,
    status ENUM('active','departed','airport','arrived','partially_distributed','distributed') NOT NULL DEFAULT 'active',
    shipping_type ENUM('air','sea','land') NOT NULL,
    shipper VARCHAR(160) NULL,
    consignee VARCHAR(160) NULL,
    shipper_profile_id INT UNSIGNED NULL,
    consignee_profile_id INT UNSIGNED NULL,
    shipment_date DATE NULL,
    way_of_shipment VARCHAR(120) NULL,
    type_of_goods VARCHAR(160) NULL,
    vessel_or_flight_name VARCHAR(160) NULL,
    departure_date DATE NULL,
    arrival_date DATE NULL,
    actual_departure_date DATE NULL,
    actual_arrival_date DATE NULL,
    size DECIMAL(12,3) NULL,
    weight DECIMAL(12,3) NULL,
    gross_weight DECIMAL(12,3) NULL,
    default_rate DECIMAL(12,2) NULL,
    default_rate_kg DECIMAL(12,2) NULL,
    default_rate_cbm DECIMAL(12,2) NULL,
    default_rate_unit ENUM('kg','cbm') NULL,
    cost_per_unit DECIMAL(12,2) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    shipment_number_active VARCHAR(80)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN shipment_number ELSE NULL END) STORED,
    UNIQUE KEY uk_shipments_number_active (shipment_number_active),
    KEY idx_shipments_origin (origin_country_id),
    KEY idx_shipments_shipper_profile (shipper_profile_id),
    KEY idx_shipments_consignee_profile (consignee_profile_id),
    CONSTRAINT fk_shipments_origin_country
        FOREIGN KEY (origin_country_id) REFERENCES countries(id),
    CONSTRAINT fk_shipments_shipper_profile
        FOREIGN KEY (shipper_profile_id) REFERENCES supplier_profiles(id),
    CONSTRAINT fk_shipments_consignee_profile
        FOREIGN KEY (consignee_profile_id) REFERENCES supplier_profiles(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'status'
          AND COLUMN_TYPE LIKE '%partially_distributed%'
    ),
    'SELECT 1',
    'ALTER TABLE shipments MODIFY status '
        'ENUM('
            '\'active\','
            '\'departed\','
            '\'airport\','
            '\'arrived\','
            '\'partially_distributed\','
            '\'distributed\''
        ') NOT NULL DEFAULT \'active\''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'shipper_profile_id'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN shipper_profile_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'consignee_profile_id'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN consignee_profile_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'actual_departure_date'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN actual_departure_date DATE NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'actual_arrival_date'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN actual_arrival_date DATE NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'default_rate_kg'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN default_rate_kg DECIMAL(12,2) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND COLUMN_NAME = 'default_rate_cbm'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD COLUMN default_rate_cbm DECIMAL(12,2) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE shipments
SET default_rate_kg = COALESCE(default_rate_kg, default_rate, 0),
    default_rate_cbm = COALESCE(default_rate_cbm, default_rate, 0)
WHERE default_rate_kg IS NULL OR default_rate_cbm IS NULL;

UPDATE supplier_invoices i
LEFT JOIN shipments s ON s.id = i.shipment_id
SET i.total_weight = COALESCE(NULLIF(i.total_weight, 0), s.weight, 0),
    i.total_volume = COALESCE(NULLIF(i.total_volume, 0), s.size, 0)
WHERE i.total_weight = 0 OR i.total_volume = 0;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND INDEX_NAME = 'idx_shipments_shipper_profile'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD KEY idx_shipments_shipper_profile (shipper_profile_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND INDEX_NAME = 'idx_shipments_consignee_profile'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD KEY idx_shipments_consignee_profile (consignee_profile_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND CONSTRAINT_NAME = 'fk_shipments_shipper_profile'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD CONSTRAINT fk_shipments_shipper_profile '
        'FOREIGN KEY (shipper_profile_id) REFERENCES supplier_profiles(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'shipments'
          AND CONSTRAINT_NAME = 'fk_shipments_consignee_profile'
    ),
    'SELECT 1',
    'ALTER TABLE shipments ADD CONSTRAINT fk_shipments_consignee_profile '
        'FOREIGN KEY (consignee_profile_id) REFERENCES supplier_profiles(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND COLUMN_NAME = 'shipment_id'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD COLUMN shipment_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND INDEX_NAME = 'idx_supplier_invoices_shipment'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD KEY idx_supplier_invoices_shipment (shipment_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_invoices'
          AND CONSTRAINT_NAME = 'fk_supplier_invoices_shipment'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_invoices ADD CONSTRAINT fk_supplier_invoices_shipment '
        'FOREIGN KEY (shipment_id) REFERENCES shipments(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    KEY idx_collections_shipment (shipment_id),
    CONSTRAINT fk_collections_shipment
        FOREIGN KEY (shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'collections'
          AND COLUMN_NAME = 'created_at'
    ),
    'SELECT 1',
    'ALTER TABLE collections ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'collections'
          AND COLUMN_NAME = 'created_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE collections ADD COLUMN created_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'collections'
          AND COLUMN_NAME = 'updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE collections ADD COLUMN updated_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'collections'
          AND COLUMN_NAME = 'updated_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE collections ADD COLUMN updated_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    sub_branch_id INT UNSIGNED NULL,
    collection_id INT UNSIGNED NULL,
    tracking_number VARCHAR(80) NOT NULL,
    delivery_type ENUM('pickup','delivery') NOT NULL,
    package_type ENUM('bag','box') NOT NULL DEFAULT 'bag',
    unit_type ENUM('kg','cbm') NOT NULL,
    qty DECIMAL(12,3) NOT NULL DEFAULT 0,
    weight_type ENUM('actual','volumetric') NOT NULL,
    actual_weight DECIMAL(12,3) NULL,
    w DECIMAL(12,3) NULL,
    d DECIMAL(12,3) NULL,
    h DECIMAL(12,3) NULL,
    rate_kg DECIMAL(12,2) NULL,
    rate_cbm DECIMAL(12,2) NULL,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    adjustments_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    fulfillment_status ENUM(
        'in_shipment','main_branch','pending_receipt','received_subbranch','with_delivery','picked_up',
        'closed','returned','canceled'
    ) NOT NULL DEFAULT 'in_shipment',
    notification_status ENUM('pending','notified') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    tracking_number_active VARCHAR(80)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN tracking_number ELSE NULL END) STORED,
    UNIQUE KEY uk_orders_tracking_active (tracking_number_active),
    KEY idx_orders_shipment (shipment_id),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_sub_branch (sub_branch_id),
    KEY idx_orders_collection (collection_id),
    CONSTRAINT fk_orders_shipment
        FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_orders_sub_branch
        FOREIGN KEY (sub_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_orders_collection
        FOREIGN KEY (collection_id) REFERENCES collections(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'fulfillment_status'
          AND COLUMN_TYPE LIKE '%with_delivery%'
    ),
    'SELECT 1',
    'ALTER TABLE orders MODIFY fulfillment_status '
        'ENUM('
            '\'in_shipment\','
            '\'main_branch\','
            '\'pending_receipt\','
            '\'received_subbranch\','
            '\'with_delivery\','
            '\'picked_up\','
            '\'closed\','
            '\'returned\','
            '\'canceled\''
        ') NOT NULL DEFAULT \'in_shipment\''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'package_type'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD COLUMN package_type ENUM(''bag'',''box'') NOT NULL DEFAULT ''bag'' AFTER delivery_type'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'sub_branch_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE orders MODIFY sub_branch_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'note'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD COLUMN note TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'rate_kg'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD COLUMN rate_kg DECIMAL(12,2) NULL AFTER h'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'rate_cbm'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD COLUMN rate_cbm DECIMAL(12,2) NULL AFTER rate_kg'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE orders
SET rate_kg = COALESCE(rate_kg, rate, 0),
    rate_cbm = COALESCE(rate_cbm, rate, 0)
WHERE rate_kg IS NULL OR rate_cbm IS NULL;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND INDEX_NAME = 'uk_orders_tracking_active'
    ),
    'ALTER TABLE orders DROP INDEX uk_orders_tracking_active',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND INDEX_NAME = 'uk_orders_tracking_active'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD UNIQUE KEY uk_orders_tracking_active (tracking_number_active)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS order_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    kind ENUM('cost','discount') NOT NULL,
    calc_type ENUM('amount','percentage') NOT NULL,
    value DECIMAL(12,2) NOT NULL DEFAULT 0,
    computed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_order_adjustments_order (order_id),
    CONSTRAINT fk_order_adjustments_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    invoice_no VARCHAR(80) NOT NULL,
    status ENUM('open','partially_paid','paid','void') NOT NULL DEFAULT 'open',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    total DECIMAL(12,2) NOT NULL,
    points_used INT UNSIGNED NOT NULL DEFAULT 0,
    points_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_total DECIMAL(12,2) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by_user_id INT UNSIGNED NULL,
    note TEXT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    invoice_no_active VARCHAR(80)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN invoice_no ELSE NULL END) STORED,
    UNIQUE KEY uk_invoices_no_active (invoice_no_active),
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_branch (branch_id),
    CONSTRAINT fk_invoices_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_invoices_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
  ) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'currency'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''USD'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'points_used'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN points_used INT UNSIGNED NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'points_discount'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN points_discount DECIMAL(12,2) NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    order_snapshot_json JSON NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    KEY idx_invoice_items_invoice (invoice_id),
    KEY idx_invoice_items_order (order_id),
    CONSTRAINT fk_invoice_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_invoice_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    type ENUM('payment','deposit','refund','adjustment','admin_settlement','charge','discount') NOT NULL DEFAULT 'payment',
    status ENUM('active','canceled') NOT NULL DEFAULT 'active',
    payment_method_id INT UNSIGNED NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NULL,
    whish_phone VARCHAR(40) NULL,
    reason VARCHAR(80) NULL,
    note TEXT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    canceled_at DATETIME NULL DEFAULT NULL,
    canceled_reason TEXT NULL,
    canceled_by_user_id INT UNSIGNED NULL,
    account_transfer_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_transactions_branch (branch_id),
    KEY idx_transactions_customer (customer_id),
    KEY idx_transactions_method (payment_method_id),
    KEY idx_transactions_account_transfer (account_transfer_id),
    CONSTRAINT fk_transactions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_transactions_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_transactions_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    CONSTRAINT fk_transactions_account_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN updated_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'updated_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN updated_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'canceled_at'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN canceled_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'canceled_reason'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN canceled_reason TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'invoices'
          AND COLUMN_NAME = 'canceled_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE invoices ADD COLUMN canceled_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'type'
          AND COLUMN_TYPE LIKE '%discount%'
    ),
    'SELECT 1',
    'ALTER TABLE transactions MODIFY type '
        'ENUM('
            '\'payment\','
            '\'deposit\','
            '\'refund\','
            '\'adjustment\','
            '\'admin_settlement\','
            '\'charge\','
            '\'discount\''
        ') NOT NULL DEFAULT \'payment\''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'payment_method_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE transactions MODIFY payment_method_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'reason'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN reason VARCHAR(80) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'currency'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''USD'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN updated_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'updated_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN updated_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'status'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN status ENUM(\'active\',\'canceled\') NOT NULL DEFAULT \'active\''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'canceled_at'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN canceled_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'canceled_reason'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN canceled_reason TEXT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'canceled_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN canceled_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS transaction_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    amount_allocated DECIMAL(12,2) NOT NULL,
    KEY idx_tx_allocations_transaction (transaction_id),
    KEY idx_tx_allocations_invoice (invoice_id),
    CONSTRAINT fk_tx_allocations_transaction
        FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_tx_allocations_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branch_receiving_scans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NOT NULL,
    tracking_number VARCHAR(80) NOT NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanned_by_user_id INT UNSIGNED NULL,
    match_status ENUM('matched','unmatched') NOT NULL,
    matched_order_id INT UNSIGNED NULL,
    reported_weight DECIMAL(12,3) NULL,
    reported_at DATETIME NULL DEFAULT NULL,
    reported_by_user_id INT UNSIGNED NULL,
    note TEXT NULL,
    KEY idx_receiving_scans_branch (branch_id),
    KEY idx_receiving_scans_shipment (shipment_id),
    KEY idx_receiving_scans_tracking (tracking_number),
    KEY idx_receiving_scans_order (matched_order_id),
    CONSTRAINT fk_receiving_scans_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_receiving_scans_shipment
        FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    CONSTRAINT fk_receiving_scans_order
        FOREIGN KEY (matched_order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_receiving_scans'
          AND COLUMN_NAME = 'reported_weight'
    ),
    'SELECT 1',
    'ALTER TABLE branch_receiving_scans ADD COLUMN reported_weight DECIMAL(12,3) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_receiving_scans'
          AND COLUMN_NAME = 'reported_at'
    ),
    'SELECT 1',
    'ALTER TABLE branch_receiving_scans ADD COLUMN reported_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_receiving_scans'
          AND COLUMN_NAME = 'reported_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE branch_receiving_scans ADD COLUMN reported_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('shipment','order','shopping_order','invoice','collection') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_attachments_entity (entity_type, entity_id),
    KEY idx_attachments_uploaded_by (uploaded_by_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shopping_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    sub_branch_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    image_url VARCHAR(255) NULL,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    fees_type ENUM('amount','percentage') NULL,
    fees_amount DECIMAL(12,2) NULL,
    fees_percentage DECIMAL(12,2) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_type ENUM('pickup','delivery') NOT NULL,
    status ENUM('pending','distributed','received_subbranch','closed','canceled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_shopping_orders_customer (customer_id),
    KEY idx_shopping_orders_sub_branch (sub_branch_id),
    CONSTRAINT fk_shopping_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_shopping_orders_sub_branch
        FOREIGN KEY (sub_branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'attachments'
          AND COLUMN_NAME = 'entity_type'
          AND COLUMN_TYPE LIKE '%collection%'
    ),
    'SELECT 1',
    'ALTER TABLE attachments MODIFY entity_type ENUM(''shipment'',''order'',''shopping_order'',''invoice'',''collection'') NOT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS staff_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    position VARCHAR(120) NULL,
    branch_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    hired_at DATE NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_staff_branch (branch_id),
    KEY idx_staff_user (user_id),
    CONSTRAINT fk_staff_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_staff_user
        FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_members'
          AND COLUMN_NAME = 'user_id'
    ),
    'SELECT 1',
    'ALTER TABLE staff_members ADD COLUMN user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_members'
          AND INDEX_NAME = 'idx_staff_user'
    ),
    'SELECT 1',
    'ALTER TABLE staff_members ADD KEY idx_staff_user (user_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_members'
          AND CONSTRAINT_NAME = 'fk_staff_user'
    ),
    'SELECT 1',
    'ALTER TABLE staff_members ADD CONSTRAINT fk_staff_user '
        'FOREIGN KEY (user_id) REFERENCES users(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS staff_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    type ENUM('salary_adjustment','advance','bonus','salary_payment') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    salary_before DECIMAL(12,2) NULL,
    salary_after DECIMAL(12,2) NULL,
    expense_date DATE NULL,
    salary_month DATE NULL,
    note TEXT NULL,
    account_transfer_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    salary_month_active VARCHAR(80)
        GENERATED ALWAYS AS (
            CASE
                WHEN deleted_at IS NULL AND type = 'salary_payment' AND salary_month IS NOT NULL
                    THEN CONCAT(staff_id, ':', salary_month)
                ELSE NULL
            END
        ) STORED,
    KEY idx_staff_expenses_staff (staff_id),
    KEY idx_staff_expenses_branch (branch_id),
    KEY idx_staff_expenses_type (type),
    KEY idx_staff_expenses_account_transfer (account_transfer_id),
    UNIQUE KEY uk_staff_salary_month_active (salary_month_active),
    CONSTRAINT fk_staff_expenses_staff
        FOREIGN KEY (staff_id) REFERENCES staff_members(id),
    CONSTRAINT fk_staff_expenses_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_staff_expenses_account_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND COLUMN_NAME = 'type'
          AND COLUMN_TYPE LIKE '%salary_payment%'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses MODIFY type '
        'ENUM('
            '\'salary_adjustment\','
            '\'advance\','
            '\'bonus\','
            '\'salary_payment\''
        ') NOT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND COLUMN_NAME = 'salary_month'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD COLUMN salary_month DATE NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND COLUMN_NAME = 'salary_month_active'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD COLUMN salary_month_active VARCHAR(80) GENERATED ALWAYS AS ('
        'CASE '
            'WHEN deleted_at IS NULL AND type = \'salary_payment\' AND salary_month IS NOT NULL '
                'THEN CONCAT(staff_id, \':\', salary_month) '
            'ELSE NULL '
        'END'
        ') STORED'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND INDEX_NAME = 'uk_staff_salary_month_active'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD UNIQUE KEY uk_staff_salary_month_active (salary_month_active)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS general_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NULL,
    note TEXT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    account_transfer_id INT UNSIGNED NULL,
    is_paid TINYINT NOT NULL DEFAULT 0,
    paid_at DATETIME NULL DEFAULT NULL,
    paid_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_general_expenses_branch (branch_id),
    KEY idx_general_expenses_shipment (shipment_id),
    KEY idx_general_expenses_date (expense_date),
    KEY idx_general_expenses_ref (reference_type, reference_id),
    KEY idx_general_expenses_account_transfer (account_transfer_id),
    KEY idx_general_expenses_paid (is_paid),
    CONSTRAINT fk_general_expenses_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_general_expenses_shipment
        FOREIGN KEY (shipment_id) REFERENCES shipments(id),
    CONSTRAINT fk_general_expenses_account_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branch_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_branch_id INT UNSIGNED NOT NULL,
    to_branch_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    transfer_date DATE NULL,
    note TEXT NULL,
    account_transfer_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_branch_transfers_from (from_branch_id),
    KEY idx_branch_transfers_to (to_branch_id),
    KEY idx_branch_transfers_date (transfer_date),
    KEY idx_branch_transfers_account_transfer (account_transfer_id),
    CONSTRAINT fk_branch_transfers_from
        FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_branch_transfers_to
        FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_branch_transfers_account_transfer
        FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branch_balance_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    entry_type ENUM('order_received','order_reversal','transfer_out','transfer_in','adjustment','customer_payment') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    KEY idx_branch_balance_branch (branch_id),
    KEY idx_branch_balance_type (entry_type),
    KEY idx_branch_balance_ref (reference_type, reference_id),
    CONSTRAINT fk_branch_balance_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_balance_entries'
          AND COLUMN_NAME = 'entry_type'
          AND COLUMN_TYPE LIKE '%customer_payment%'
    ),
    'SELECT 1',
    'ALTER TABLE branch_balance_entries MODIFY entry_type '
        'ENUM('
            '\'order_received\','
            '\'order_reversal\','
            '\'transfer_out\','
            '\'transfer_in\','
            '\'adjustment\','
            '\'customer_payment\''
        ') NOT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS customer_balance_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    entry_type ENUM(
        'order_charge',
        'order_reversal',
        'payment',
        'deposit',
        'refund',
        'adjustment',
        'admin_settlement',
        'charge',
        'discount'
    ) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    KEY idx_customer_balance_customer (customer_id),
    KEY idx_customer_balance_branch (branch_id),
    KEY idx_customer_balance_type (entry_type),
    KEY idx_customer_balance_ref (reference_type, reference_id),
    CONSTRAINT fk_customer_balance_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_customer_balance_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer_balance_entries'
          AND COLUMN_NAME = 'entry_type'
          AND COLUMN_TYPE LIKE '%discount%'
    ),
    'SELECT 1',
    'ALTER TABLE customer_balance_entries MODIFY entry_type '
        'ENUM('
            '\'order_charge\','
            '\'order_reversal\','
            '\'payment\','
            '\'deposit\','
            '\'refund\','
            '\'adjustment\','
            '\'admin_settlement\','
            '\'charge\','
            '\'discount\''
        ') NOT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'branch_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE general_expenses MODIFY branch_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'shipment_id'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN shipment_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND INDEX_NAME = 'idx_general_expenses_shipment'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD KEY idx_general_expenses_shipment (shipment_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND CONSTRAINT_NAME = 'fk_general_expenses_shipment'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD CONSTRAINT fk_general_expenses_shipment '
        'FOREIGN KEY (shipment_id) REFERENCES shipments(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'reference_type'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN reference_type VARCHAR(40) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'reference_id'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN reference_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND INDEX_NAME = 'idx_general_expenses_ref'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD KEY idx_general_expenses_ref (reference_type, reference_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND COLUMN_NAME = 'account_transfer_id'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD COLUMN account_transfer_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND INDEX_NAME = 'idx_transactions_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD KEY idx_transactions_account_transfer (account_transfer_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transactions'
          AND CONSTRAINT_NAME = 'fk_transactions_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_account_transfer '
        'FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND COLUMN_NAME = 'account_transfer_id'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD COLUMN account_transfer_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND INDEX_NAME = 'idx_supplier_transactions_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD KEY idx_supplier_transactions_account_transfer (account_transfer_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'supplier_transactions'
          AND CONSTRAINT_NAME = 'fk_supplier_transactions_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE supplier_transactions ADD CONSTRAINT fk_supplier_transactions_account_transfer '
        'FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND COLUMN_NAME = 'account_transfer_id'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD COLUMN account_transfer_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND INDEX_NAME = 'idx_staff_expenses_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD KEY idx_staff_expenses_account_transfer (account_transfer_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'staff_expenses'
          AND CONSTRAINT_NAME = 'fk_staff_expenses_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE staff_expenses ADD CONSTRAINT fk_staff_expenses_account_transfer '
        'FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'account_transfer_id'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN account_transfer_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'is_paid'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN is_paid TINYINT NOT NULL DEFAULT 0'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'paid_at'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN paid_at DATETIME NULL DEFAULT NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND COLUMN_NAME = 'paid_by_user_id'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD COLUMN paid_by_user_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE general_expenses
SET is_paid = CASE WHEN account_transfer_id IS NOT NULL THEN 1 ELSE 0 END
WHERE is_paid = 0 OR is_paid IS NULL;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND INDEX_NAME = 'idx_general_expenses_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD KEY idx_general_expenses_account_transfer (account_transfer_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND INDEX_NAME = 'idx_general_expenses_paid'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD KEY idx_general_expenses_paid (is_paid)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'general_expenses'
          AND CONSTRAINT_NAME = 'fk_general_expenses_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE general_expenses ADD CONSTRAINT fk_general_expenses_account_transfer '
        'FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_transfers'
          AND COLUMN_NAME = 'account_transfer_id'
    ),
    'SELECT 1',
    'ALTER TABLE branch_transfers ADD COLUMN account_transfer_id INT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_transfers'
          AND COLUMN_NAME = 'to_branch_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE branch_transfers MODIFY to_branch_id INT UNSIGNED NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_transfers'
          AND INDEX_NAME = 'idx_branch_transfers_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE branch_transfers ADD KEY idx_branch_transfers_account_transfer (account_transfer_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'branch_transfers'
          AND CONSTRAINT_NAME = 'fk_branch_transfers_account_transfer'
    ),
    'SELECT 1',
    'ALTER TABLE branch_transfers ADD CONSTRAINT fk_branch_transfers_account_transfer '
        'FOREIGN KEY (account_transfer_id) REFERENCES account_transfers(id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE customers c
JOIN (
    SELECT o.customer_id, s.origin_country_id
    FROM orders o
    JOIN shipments s ON s.id = o.shipment_id
    JOIN (
        SELECT customer_id, MAX(id) AS last_order_id
        FROM orders
        WHERE deleted_at IS NULL
        GROUP BY customer_id
    ) lo ON lo.customer_id = o.customer_id AND lo.last_order_id = o.id
) recent ON recent.customer_id = c.id
SET c.profile_country_id = recent.origin_country_id
WHERE c.profile_country_id IS NULL;

UPDATE customers c
JOIN branches b ON b.id = c.sub_branch_id
SET c.profile_country_id = b.country_id
WHERE c.profile_country_id IS NULL;

INSERT IGNORE INTO roles (name) VALUES
    ('Admin'),
    ('Owner'),
    ('Main Branch'),
    ('Sub Branch'),
    ('Warehouse');

INSERT IGNORE INTO company_settings (id, name, address, phone, logo_url)
VALUES (1, 'United Group', 'Beirut, Tayyouneh', '71277723', 'assets/img/ug-logo.jpg');

INSERT IGNORE INTO countries (name, iso2, iso3, phone_code) VALUES
    ('Lebanon', 'LB', 'LBN', '961'),
    ('Syria', 'SY', 'SYR', '963'),
    ('Jordan', 'JO', 'JOR', '962'),
    ('Turkey', 'TR', 'TUR', '90'),
    ('United Arab Emirates', 'AE', 'ARE', '971'),
    ('Saudi Arabia', 'SA', 'SAU', '966'),
    ('Egypt', 'EG', 'EGY', '20'),
    ('Iraq', 'IQ', 'IRQ', '964'),
    ('Qatar', 'QA', 'QAT', '974'),
    ('Kuwait', 'KW', 'KWT', '965'),
    ('Oman', 'OM', 'OMN', '968'),
    ('Bahrain', 'BH', 'BHR', '973'),
    ('Cyprus', 'CY', 'CYP', '357'),
    ('Greece', 'GR', 'GRC', '30'),
    ('France', 'FR', 'FRA', '33'),
    ('Germany', 'DE', 'DEU', '49'),
    ('United Kingdom', 'GB', 'GBR', '44'),
    ('United States', 'US', 'USA', '1'),
    ('China', 'CN', 'CHN', '86');

INSERT INTO branches (name, type, country_id, parent_branch_id)
SELECT 'Beirut', 'sub', c.id, NULL
FROM countries c
WHERE c.iso2 = 'LB'
  AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.name = 'Beirut');

UPDATE branches SET type = 'sub' WHERE name = 'Beirut';

INSERT INTO branches (name, type, country_id, parent_branch_id)
SELECT 'Riyak', 'sub', c.id, b.id
FROM countries c
JOIN branches b ON b.name = 'Beirut'
WHERE c.iso2 = 'LB'
  AND NOT EXISTS (SELECT 1 FROM branches x WHERE x.name = 'Riyak');

INSERT INTO branches (name, type, country_id, parent_branch_id)
SELECT 'Nabatieh', 'sub', c.id, b.id
FROM countries c
JOIN branches b ON b.name = 'Beirut'
WHERE c.iso2 = 'LB'
  AND NOT EXISTS (SELECT 1 FROM branches x WHERE x.name = 'Nabatieh');

INSERT INTO branches (name, type, country_id, parent_branch_id)
SELECT 'Saida', 'sub', c.id, b.id
FROM countries c
JOIN branches b ON b.name = 'Beirut'
WHERE c.iso2 = 'LB'
  AND NOT EXISTS (SELECT 1 FROM branches x WHERE x.name = 'Saida');

INSERT INTO branches (name, type, country_id, parent_branch_id)
SELECT 'Tyre', 'sub', c.id, b.id
FROM countries c
JOIN branches b ON b.name = 'Beirut'
WHERE c.iso2 = 'LB'
  AND NOT EXISTS (SELECT 1 FROM branches x WHERE x.name = 'Tyre');

INSERT IGNORE INTO payment_methods (name) VALUES
    ('Cash'),
    ('Credit'),
    ('Whish');





INSERT IGNORE INTO goods_types (name) VALUES
    ('General cargo'),
    ('Electronics'),
    ('Furniture'),
    ('Clothing'),
    ('Auto parts'),
    ('Home appliances'),
    ('Cosmetics'),
    ('Food');

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT 'Admin User', 'admin', '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM roles r
JOIN branches b ON b.name = 'Beirut'
WHERE r.name = 'Admin';

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT 'Owner User', 'owner', '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM roles r
JOIN branches b ON b.name = 'Beirut'
WHERE r.name = 'Owner';

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT 'Main Branch User', 'mainbranch', '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM roles r
JOIN branches b ON b.name = 'Beirut'
WHERE r.name = 'Main Branch';

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT 'Sub Branch User', 'subbranch', '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM roles r
JOIN branches b ON b.name = 'Riyak'
WHERE r.name = 'Sub Branch';

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT 'Warehouse User', 'warehouse', '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM roles r
JOIN branches b ON b.name = 'Tyre'
WHERE r.name = 'Warehouse';

INSERT IGNORE INTO users (name, username, password_hash, role_id, branch_id)
SELECT b.name, b.name, '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK', r.id, b.id
FROM branches b
JOIN roles r ON r.name = CASE b.type
    WHEN 'warehouse' THEN 'Warehouse'
    WHEN 'sub' THEN 'Sub Branch'
    WHEN 'main' THEN 'Main Branch'
    WHEN 'head' THEN 'Main Branch'
    ELSE 'Main Branch'
END;

UPDATE users
SET password_hash = '$2y$10$TtfGMaNFphvf1FoDr6EfFOsgNz.gFX7ijWGe.2P6rtNFcr/Fpv6SK';

INSERT IGNORE INTO customers (name, code, is_system)
VALUES ('Unknown', 'UNKNOWN', 1);



