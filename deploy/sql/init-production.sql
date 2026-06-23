-- Ceara production reset + init
--
-- Run this script on the selected production database when starting from zero.
-- It drops all application tables, including operational data and logs, then
-- recreates the schema and seeds only the initial admin user, permissions and
-- role defaults. It does not create stores, processors, lots, documents,
-- inventory movements or audit entries.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS company_settings;
DROP TABLE IF EXISTS document_templates;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS document_series;
DROP TABLE IF EXISTS purchase_wax_exits;
DROP TABLE IF EXISTS purchase_lots;
DROP TABLE IF EXISTS factory_buffer_adjustments;
DROP TABLE IF EXISTS factory_batch_items;
DROP TABLE IF EXISTS factory_batches;
DROP TABLE IF EXISTS processing_lot_movements;
DROP TABLE IF EXISTS processing_lot_status_events;
DROP TABLE IF EXISTS processing_lots;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS siruta_localities;
DROP TABLE IF EXISTS siruta_counties;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS processors;
DROP TABLE IF EXISTS user_stores;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    fgo_series VARCHAR(80) NOT NULL DEFAULT '',
    processing_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    processing_price_cents INT NOT NULL DEFAULT 0,
    purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_price_cents_per_kg INT NOT NULL DEFAULT 0,
    processor_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    code VARCHAR(80) PRIMARY KEY,
    label VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_name ENUM('admin', 'operator') NOT NULL,
    permission_code VARCHAR(80) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (role_name, permission_code),
    FOREIGN KEY (permission_code) REFERENCES permissions(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_stores (
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    PRIMARY KEY (user_id, store_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    cui VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    contact VARCHAR(160) NOT NULL DEFAULT '',
    processing_price_cents INT NOT NULL DEFAULT 0,
    exchange_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_type ENUM('PF', 'PJ') NOT NULL DEFAULT 'PF',
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(80) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    identifier VARCHAR(40) NOT NULL DEFAULT '',
    cui VARCHAR(40) NOT NULL DEFAULT '',
    representative VARCHAR(160) NOT NULL DEFAULT '',
    county_code VARCHAR(10) NOT NULL DEFAULT '',
    county_name VARCHAR(80) NOT NULL DEFAULT '',
    locality_siruta INT NULL,
    locality_name VARCHAR(160) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    registry_number VARCHAR(80) NOT NULL DEFAULT '',
    legal_form VARCHAR(40) NOT NULL DEFAULT '',
    vat_status VARCHAR(80) NOT NULL DEFAULT '',
    external_source VARCHAR(40) NOT NULL DEFAULT '',
    external_checked_at TIMESTAMP NULL,
    known_customer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siruta_counties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    county_code VARCHAR(10) NOT NULL UNIQUE,
    siruta_code INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    normalized_name VARCHAR(80) NOT NULL,
    INDEX idx_county_name (normalized_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siruta_localities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siruta_code INT NOT NULL UNIQUE,
    county_code VARCHAR(10) NOT NULL,
    name VARCHAR(160) NOT NULL,
    normalized_name VARCHAR(160) NOT NULL,
    display_name VARCHAR(220) NOT NULL,
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    parent_siruta INT NULL,
    parent_name VARCHAR(160) NOT NULL DEFAULT '',
    parent_type VARCHAR(40) NOT NULL DEFAULT '',
    type_code INT NOT NULL DEFAULT 0,
    level_no INT NOT NULL DEFAULT 0,
    duplicate_name_in_county TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_locality_county_name (county_code, normalized_name),
    INDEX idx_locality_parent (parent_siruta),
    FOREIGN KEY (county_code) REFERENCES siruta_counties(county_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    supplier_type ENUM('PF', 'Producator agricol', 'PJ/PFA') NOT NULL,
    phone VARCHAR(80) NOT NULL DEFAULT '',
    identifier VARCHAR(40) NOT NULL DEFAULT '',
    cui VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    county_code VARCHAR(10) NOT NULL DEFAULT '',
    county_name VARCHAR(80) NOT NULL DEFAULT '',
    locality_siruta INT NULL,
    locality_name VARCHAR(160) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    status ENUM('In Validare', 'Acceptat', 'Predat Fabricii', 'Respins', 'Returnat') NOT NULL,
    gross_g INT NOT NULL,
    factory_sent_g INT NOT NULL DEFAULT 0,
    processing_price_cents INT NOT NULL DEFAULT 0,
    shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL,
    store_id INT NOT NULL,
    processor_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (processor_id) REFERENCES processors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lot_status_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    status ENUM('In Validare', 'Acceptat', 'Predat Fabricii', 'Respins', 'Returnat') NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_lot_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    movement_type ENUM(
        'RECEIVE_WAX_FROM_CLIENT',
        'EXCHANGE_WAX_WITH_CLIENT',
        'RETURN_WAX_TO_CLIENT',
        'SEND_WAX_TO_FACTORY',
        'RECEIVE_FOUNDATION_FROM_FACTORY',
        'FACTORY_REJECT_WAX',
        'RECORD_LOSS',
        'RECOVER_FOUNDATION_FROM_CLIENT'
    ) NOT NULL,
    wax_g INT NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL DEFAULT 0,
    service_value_cents INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(40) NOT NULL UNIQUE,
    processor_id INT NOT NULL,
    store_id INT NOT NULL,
    wax_g INT NOT NULL,
    foundation_g INT NOT NULL,
    processing_cost_cents INT NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processor_id) REFERENCES processors(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_batch_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    processing_lot_id INT NOT NULL,
    wax_g INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES factory_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (processing_lot_id) REFERENCES processing_lots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS factory_buffer_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_type ENUM('plus', 'minus') NOT NULL,
    aviz_number VARCHAR(80) NOT NULL,
    qty_g INT NOT NULL,
    store_id INT NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_number VARCHAR(40) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    supplier_type ENUM('PF', 'Producator agricol', 'PJ/PFA') NOT NULL,
    status ENUM('In stoc', 'Partial vandut', 'Vandut') NOT NULL DEFAULT 'In stoc',
    purchase_date DATE NOT NULL,
    external_document_type ENUM('borderou', 'carnet', 'factura') NOT NULL,
    external_document_series VARCHAR(80) NOT NULL DEFAULT '',
    external_document_number VARCHAR(80) NOT NULL DEFAULT '',
    external_document_date DATE NULL,
    borderou_position VARCHAR(40) NOT NULL DEFAULT '',
    gross_g INT NOT NULL,
    shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    net_g INT NOT NULL DEFAULT 0,
    purchase_price_cents_per_kg INT NOT NULL DEFAULT 0,
    total_amount_cents INT NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL DEFAULT 0,
    store_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_purchase_external_position (external_document_type, external_document_series, external_document_number, borderou_position),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_wax_exits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exit_number VARCHAR(40) NOT NULL UNIQUE,
    partner_name VARCHAR(160) NOT NULL,
    partner_identifier VARCHAR(80) NOT NULL DEFAULT '',
    document_type VARCHAR(40) NOT NULL DEFAULT '',
    document_series VARCHAR(80) NOT NULL DEFAULT '',
    document_number VARCHAR(80) NOT NULL DEFAULT '',
    document_date DATE NULL,
    qty_g INT NOT NULL,
    store_id INT NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    series VARCHAR(80) NOT NULL,
    next_number INT NOT NULL DEFAULT 1,
    UNIQUE KEY unique_store_doc (store_id, document_type),
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(40) NOT NULL,
    series VARCHAR(80) NOT NULL,
    number INT NOT NULL,
    store_id INT NOT NULL,
    lot_id INT NULL,
    movement_id INT NULL,
    factory_batch_id INT NULL,
    reference_type VARCHAR(60) NOT NULL,
    reference_id INT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    file_path VARCHAR(255) NULL,
    external_url VARCHAR(500) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    printed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE SET NULL,
    FOREIGN KEY (movement_id) REFERENCES processing_lot_movements(id) ON DELETE SET NULL,
    FOREIGN KEY (factory_batch_id) REFERENCES factory_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL,
    variables_json TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_settings (
    id TINYINT PRIMARY KEY,
    company_name VARCHAR(160) NOT NULL DEFAULT '',
    vat_number VARCHAR(40) NOT NULL DEFAULT '',
    registry_number VARCHAR(80) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    fgo_private_key VARCHAR(255) NOT NULL DEFAULT '',
    purchase_default_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_default_price_cents_per_kg INT NOT NULL DEFAULT 0,
    purchase_factory_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_factory_price_cents_per_kg INT NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movement_type VARCHAR(80) NOT NULL,
    qty_g INT NOT NULL,
    store_id INT NOT NULL,
    reference_type VARCHAR(60) NOT NULL,
    reference_id INT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    operation VARCHAR(120) NOT NULL,
    entity VARCHAR(120) NOT NULL,
    entity_id INT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Production seed: admin user, standard permissions and role defaults only.
START TRANSACTION;

INSERT INTO users (username, password_hash, full_name, role, active)
VALUES ('admin', '$2y$10$IS58HCK3SGmnI0qOkG/uVefZ2Y7GjtoivzJ9lEU.AB2kdgwl8GTAe', 'Administrator', 'admin', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO permissions (code, label) VALUES
('USER_CREATE', 'Creare utilizatori'),
('USER_EDIT', 'Editare utilizatori'),
('USER_RESET_PASSWORD', 'Resetare parole'),
('STORE_MANAGE', 'Administrare gestiuni'),
('PROCESSOR_MANAGE', 'Administrare procesatori'),
('DOCUMENT_TEMPLATE_MANAGE', 'Administrare template documente'),
('PROCESSING_CREATE', 'Creare procesare'),
('PROCESSING_ACCEPT', 'Acceptare procesare'),
('PROCESSING_REJECT', 'Respingere procesare'),
('PURCHASE_CREATE', 'Creare achizitii'),
('REPORT_VIEW', 'Vizualizare rapoarte'),
('AUDIT_VIEW', 'Vizualizare audit')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO role_permissions (role_name, permission_code, allowed) VALUES
('admin', 'USER_CREATE', 1),
('admin', 'USER_EDIT', 1),
('admin', 'USER_RESET_PASSWORD', 1),
('admin', 'STORE_MANAGE', 1),
('admin', 'PROCESSOR_MANAGE', 1),
('admin', 'DOCUMENT_TEMPLATE_MANAGE', 1),
('admin', 'PROCESSING_CREATE', 1),
('admin', 'PROCESSING_ACCEPT', 1),
('admin', 'PROCESSING_REJECT', 1),
('admin', 'PURCHASE_CREATE', 1),
('admin', 'REPORT_VIEW', 1),
('admin', 'AUDIT_VIEW', 1),
('operator', 'USER_CREATE', 0),
('operator', 'USER_EDIT', 0),
('operator', 'USER_RESET_PASSWORD', 0),
('operator', 'STORE_MANAGE', 0),
('operator', 'PROCESSOR_MANAGE', 0),
('operator', 'DOCUMENT_TEMPLATE_MANAGE', 0),
('operator', 'PROCESSING_CREATE', 1),
('operator', 'PROCESSING_ACCEPT', 0),
('operator', 'PROCESSING_REJECT', 0),
('operator', 'PURCHASE_CREATE', 1),
('operator', 'REPORT_VIEW', 1),
('operator', 'AUDIT_VIEW', 0)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

COMMIT;
