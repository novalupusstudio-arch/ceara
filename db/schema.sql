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
    cui VARCHAR(40) NOT NULL DEFAULT '',
    representative VARCHAR(160) NOT NULL DEFAULT '',
    known_customer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    supplier_type ENUM('PF', 'Producator agricol', 'PFA/SRL') NOT NULL,
    cui VARCHAR(40) NOT NULL DEFAULT '',
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
    supplier_type ENUM('PF', 'Producator agricol', 'PFA/SRL') NOT NULL,
    status ENUM('Achizitie', 'Predat Procesator', 'Receptionat Faguri', 'Inchis') NOT NULL,
    gross_g INT NOT NULL,
    shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    foundation_g INT NOT NULL,
    store_id INT NOT NULL,
    processor_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (processor_id) REFERENCES processors(id),
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
