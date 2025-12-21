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

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(50) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    sub_branch_id INT UNSIGNED NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_system TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    code_active VARCHAR(50)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN code ELSE NULL END) STORED,
    UNIQUE KEY uk_customers_code_active (code_active),
    KEY idx_customers_sub_branch (sub_branch_id),
    CONSTRAINT fk_customers_sub_branch
        FOREIGN KEY (sub_branch_id) REFERENCES branches(id)
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

CREATE TABLE IF NOT EXISTS shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(80) NOT NULL,
    origin_country_id INT UNSIGNED NOT NULL,
    status ENUM('active','departed','airport','arrived','distributed') NOT NULL DEFAULT 'active',
    shipping_type ENUM('air','sea','land') NOT NULL,
    shipper VARCHAR(160) NULL,
    consignee VARCHAR(160) NULL,
    shipment_date DATE NULL,
    way_of_shipment VARCHAR(120) NULL,
    type_of_goods VARCHAR(160) NULL,
    vessel_or_flight_name VARCHAR(160) NULL,
    departure_date DATE NULL,
    arrival_date DATE NULL,
    size DECIMAL(12,3) NULL,
    weight DECIMAL(12,3) NULL,
    gross_weight DECIMAL(12,3) NULL,
    default_rate DECIMAL(12,2) NULL,
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
    CONSTRAINT fk_shipments_origin_country
        FOREIGN KEY (origin_country_id) REFERENCES countries(id)
) ENGINE=InnoDB;

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
    sub_branch_id INT UNSIGNED NOT NULL,
    collection_id INT UNSIGNED NULL,
    tracking_number VARCHAR(80) NOT NULL,
    delivery_type ENUM('pickup','delivery') NOT NULL,
    unit_type ENUM('kg','cbm') NOT NULL,
    qty DECIMAL(12,3) NOT NULL DEFAULT 0,
    weight_type ENUM('actual','volumetric') NOT NULL,
    actual_weight DECIMAL(12,3) NULL,
    w DECIMAL(12,3) NULL,
    d DECIMAL(12,3) NULL,
    h DECIMAL(12,3) NULL,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    adjustments_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    fulfillment_status ENUM(
        'in_shipment','main_branch','pending_receipt','received_subbranch','closed','returned','canceled'
    ) NOT NULL DEFAULT 'in_shipment',
    notification_status ENUM('pending','notified') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    tracking_number_active VARCHAR(80)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN tracking_number ELSE NULL END) STORED,
    UNIQUE KEY uk_orders_tracking_active (shipment_id, tracking_number_active),
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
          AND COLUMN_NAME = 'note'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD COLUMN note TEXT NULL'
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
    total DECIMAL(12,2) NOT NULL,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_total DECIMAL(12,2) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by_user_id INT UNSIGNED NULL,
    note TEXT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
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
    type ENUM('payment','deposit','refund','adjustment','admin_settlement') NOT NULL DEFAULT 'payment',
    payment_method_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NULL,
    whish_phone VARCHAR(40) NULL,
    note TEXT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    KEY idx_transactions_branch (branch_id),
    KEY idx_transactions_customer (customer_id),
    KEY idx_transactions_method (payment_method_id),
    CONSTRAINT fk_transactions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_transactions_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_transactions_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
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

CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('shipment','order','shopping_order','invoice') NOT NULL,
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

INSERT IGNORE INTO roles (name) VALUES
    ('Admin'),
    ('Owner'),
    ('Main Branch'),
    ('Sub Branch'),
    ('Warehouse');

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
