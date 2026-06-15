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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    cui VARCHAR(40) NOT NULL DEFAULT '',
    contact VARCHAR(160) NOT NULL DEFAULT '',
    processing_price_cents INT NOT NULL DEFAULT 0,
    exchange_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(80) NOT NULL DEFAULT '',
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
    reference_type VARCHAR(60) NOT NULL,
    reference_id INT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'mock',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
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

