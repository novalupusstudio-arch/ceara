<?php

declare(strict_types=1);

namespace Ceara;

use PDO;

final class DatabaseMigrator
{
    public function __construct(private PDO $pdo)
    {
    }

    public function run(): void
    {
        $column = $this->pdo->query("SHOW COLUMNS FROM processors LIKE 'address'")->fetch();
        if (!$column) {
            $this->pdo->exec("ALTER TABLE processors ADD address VARCHAR(255) NOT NULL DEFAULT '' AFTER cui");
        }

        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'fgo_series'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD fgo_series VARCHAR(80) NOT NULL DEFAULT '' AFTER address");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'processing_shrinkage_pct'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD processing_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER fgo_series");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'processing_price_cents'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD processing_price_cents INT NOT NULL DEFAULT 0 AFTER processing_shrinkage_pct");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'purchase_shrinkage_pct'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD purchase_shrinkage_pct DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER processing_price_cents");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'purchase_price_cents_per_kg'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD purchase_price_cents_per_kg INT NOT NULL DEFAULT 0 AFTER purchase_shrinkage_pct");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM stores LIKE 'processor_id'")->fetch()) {
            $this->pdo->exec("ALTER TABLE stores ADD processor_id INT NULL AFTER purchase_price_cents_per_kg");
        }

        $customerColumns = [
            'customer_type' => "ALTER TABLE customers ADD customer_type ENUM('PF', 'PJ') NOT NULL DEFAULT 'PF' AFTER id",
            'address' => "ALTER TABLE customers ADD address VARCHAR(255) NOT NULL DEFAULT '' AFTER phone",
            'identifier' => "ALTER TABLE customers ADD identifier VARCHAR(40) NOT NULL DEFAULT '' AFTER address",
            'cui' => "ALTER TABLE customers ADD cui VARCHAR(40) NOT NULL DEFAULT '' AFTER address",
            'representative' => "ALTER TABLE customers ADD representative VARCHAR(160) NOT NULL DEFAULT '' AFTER cui",
            'county_code' => "ALTER TABLE customers ADD county_code VARCHAR(10) NOT NULL DEFAULT '' AFTER representative",
            'county_name' => "ALTER TABLE customers ADD county_name VARCHAR(80) NOT NULL DEFAULT '' AFTER county_code",
            'locality_siruta' => "ALTER TABLE customers ADD locality_siruta INT NULL AFTER county_name",
            'locality_name' => "ALTER TABLE customers ADD locality_name VARCHAR(160) NOT NULL DEFAULT '' AFTER locality_siruta",
            'postal_code' => "ALTER TABLE customers ADD postal_code VARCHAR(20) NOT NULL DEFAULT '' AFTER locality_name",
            'registry_number' => "ALTER TABLE customers ADD registry_number VARCHAR(80) NOT NULL DEFAULT '' AFTER postal_code",
            'legal_form' => "ALTER TABLE customers ADD legal_form VARCHAR(40) NOT NULL DEFAULT '' AFTER registry_number",
            'vat_status' => "ALTER TABLE customers ADD vat_status VARCHAR(80) NOT NULL DEFAULT '' AFTER legal_form",
            'external_source' => "ALTER TABLE customers ADD external_source VARCHAR(40) NOT NULL DEFAULT '' AFTER vat_status",
            'external_checked_at' => "ALTER TABLE customers ADD external_checked_at TIMESTAMP NULL AFTER external_source",
        ];
        foreach ($customerColumns as $name => $sql) {
            if (!$this->pdo->query("SHOW COLUMNS FROM customers LIKE '$name'")->fetch()) {
                $this->pdo->exec($sql);
            }
        }
        if ($this->pdo->query("SHOW COLUMNS FROM customers LIKE 'identifier'")->fetch()
            && $this->pdo->query("SHOW COLUMNS FROM customers LIKE 'cui'")->fetch()) {
            $this->pdo->exec("UPDATE customers SET identifier = cui WHERE identifier = '' AND cui <> ''");
        }

        if (!$this->pdo->query("SHOW COLUMNS FROM processing_lots LIKE 'processing_price_cents'")->fetch()) {
            $this->pdo->exec("ALTER TABLE processing_lots ADD processing_price_cents INT NOT NULL DEFAULT 0 AFTER gross_g");
        }
        if (!$this->pdo->query("SHOW COLUMNS FROM processing_lots LIKE 'factory_sent_g'")->fetch()) {
            $this->pdo->exec("ALTER TABLE processing_lots ADD factory_sent_g INT NOT NULL DEFAULT 0 AFTER gross_g");
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'processing_lot_status_events'")->fetchColumn();
        if (!$table) {
            $this->pdo->exec(
                "CREATE TABLE processing_lot_status_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    lot_id INT NOT NULL,
                    status ENUM('In Validare', 'Acceptat', 'Predat Fabricii', 'Respins', 'Returnat') NOT NULL,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (lot_id) REFERENCES processing_lots(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'processing_lot_movements'")->fetchColumn();
        if (!$table) {
            $this->pdo->exec(
                "CREATE TABLE processing_lot_movements (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'factory_batches'")->fetchColumn();
        if (!$table) {
            $this->pdo->exec(
                "CREATE TABLE factory_batches (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    batch_number VARCHAR(40) NOT NULL UNIQUE,
                    processor_id INT NOT NULL,
                    store_id INT NOT NULL,
                    aviz_number VARCHAR(80) NOT NULL DEFAULT '',
                    aviz_date DATE NULL,
                    wax_g INT NOT NULL,
                    foundation_g INT NOT NULL,
                    processing_cost_cents INT NOT NULL DEFAULT 0,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (processor_id) REFERENCES processors(id),
                    FOREIGN KEY (store_id) REFERENCES stores(id),
                    FOREIGN KEY (created_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            if (!$this->pdo->query("SHOW COLUMNS FROM factory_batches LIKE 'aviz_number'")->fetch()) {
                $this->pdo->exec("ALTER TABLE factory_batches ADD aviz_number VARCHAR(80) NOT NULL DEFAULT '' AFTER store_id");
            }
            if (!$this->pdo->query("SHOW COLUMNS FROM factory_batches LIKE 'aviz_date'")->fetch()) {
                $this->pdo->exec("ALTER TABLE factory_batches ADD aviz_date DATE NULL AFTER aviz_number");
            }
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'factory_batch_items'")->fetchColumn();
        if (!$table) {
            $this->pdo->exec(
                "CREATE TABLE factory_batch_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    batch_id INT NOT NULL,
                    processing_lot_id INT NOT NULL,
                    wax_g INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (batch_id) REFERENCES factory_batches(id) ON DELETE CASCADE,
                    FOREIGN KEY (processing_lot_id) REFERENCES processing_lots(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'factory_buffer_adjustments'")->fetchColumn();
        if (!$table) {
            $this->pdo->exec(
                "CREATE TABLE factory_buffer_adjustments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    adjustment_type ENUM('plus', 'minus') NOT NULL,
                    aviz_number VARCHAR(80) NOT NULL,
                    aviz_date DATE NOT NULL,
                    reception_date DATE NOT NULL,
                    qty_g INT NOT NULL,
                    store_id INT NOT NULL,
                    notes TEXT NULL,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (store_id) REFERENCES stores(id),
                    FOREIGN KEY (created_by) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            if (!$this->pdo->query("SHOW COLUMNS FROM factory_buffer_adjustments LIKE 'aviz_date'")->fetch()) {
                $this->pdo->exec("ALTER TABLE factory_buffer_adjustments ADD aviz_date DATE NOT NULL AFTER aviz_number");
                $this->pdo->exec("UPDATE factory_buffer_adjustments SET aviz_date = DATE(created_at) WHERE aviz_date = '0000-00-00' OR aviz_date IS NULL");
            }
            if (!$this->pdo->query("SHOW COLUMNS FROM factory_buffer_adjustments LIKE 'reception_date'")->fetch()) {
                $this->pdo->exec("ALTER TABLE factory_buffer_adjustments ADD reception_date DATE NOT NULL AFTER aviz_date");
                $this->pdo->exec("UPDATE factory_buffer_adjustments SET reception_date = DATE(created_at) WHERE reception_date = '0000-00-00' OR reception_date IS NULL");
            }
        }

        $documentColumns = [
            'lot_id' => 'ALTER TABLE documents ADD lot_id INT NULL AFTER store_id',
            'movement_id' => 'ALTER TABLE documents ADD movement_id INT NULL AFTER lot_id',
            'factory_batch_id' => 'ALTER TABLE documents ADD factory_batch_id INT NULL AFTER movement_id',
            'file_path' => 'ALTER TABLE documents ADD file_path VARCHAR(255) NULL AFTER status',
            'external_url' => 'ALTER TABLE documents ADD external_url VARCHAR(500) NULL AFTER file_path',
            'created_by' => 'ALTER TABLE documents ADD created_by INT NULL AFTER notes',
            'printed_at' => 'ALTER TABLE documents ADD printed_at TIMESTAMP NULL AFTER created_by',
        ];
        foreach ($documentColumns as $name => $sql) {
            if (!$this->pdo->query("SHOW COLUMNS FROM documents LIKE '$name'")->fetch()) {
                $this->pdo->exec($sql);
            }
        }

        if (!$this->pdo->query("SHOW TABLES LIKE 'document_templates'")->fetchColumn()) {
            $this->pdo->exec(
                "CREATE TABLE document_templates (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->pdo->query("SHOW TABLES LIKE 'company_settings'")->fetchColumn()) {
            $this->pdo->exec(
                "CREATE TABLE company_settings (
                    id TINYINT PRIMARY KEY,
                    company_name VARCHAR(160) NOT NULL DEFAULT '',
                    vat_number VARCHAR(40) NOT NULL DEFAULT '',
                    registry_number VARCHAR(80) NOT NULL DEFAULT '',
                    address VARCHAR(255) NOT NULL DEFAULT '',
                    phone VARCHAR(80) NOT NULL DEFAULT '',
                    email VARCHAR(120) NOT NULL DEFAULT '',
                    fgo_url VARCHAR(255) NOT NULL DEFAULT '',
                    fgo_token VARCHAR(255) NOT NULL DEFAULT '',
                    updated_by INT NULL,
                    updated_at TIMESTAMP NULL,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $companyColumns = [
            'phone' => "ALTER TABLE company_settings ADD phone VARCHAR(80) NOT NULL DEFAULT '' AFTER address",
            'email' => "ALTER TABLE company_settings ADD email VARCHAR(120) NOT NULL DEFAULT '' AFTER phone",
            'fgo_url' => "ALTER TABLE company_settings ADD fgo_url VARCHAR(255) NOT NULL DEFAULT '' AFTER email",
            'fgo_token' => "ALTER TABLE company_settings ADD fgo_token VARCHAR(255) NOT NULL DEFAULT '' AFTER fgo_url",
        ];
        foreach ($companyColumns as $name => $sql) {
            if (!$this->pdo->query("SHOW COLUMNS FROM company_settings LIKE '$name'")->fetch()) {
                $this->pdo->exec($sql);
            }
        }

        if ($this->pdo->query("SHOW TABLES LIKE 'suppliers'")->fetchColumn()) {
            $this->pdo->exec("UPDATE suppliers SET supplier_type = 'PJ/PFA' WHERE supplier_type = 'PFA/SRL'");
            $this->pdo->exec("ALTER TABLE suppliers MODIFY supplier_type ENUM('PF', 'Producator agricol', 'PJ/PFA') NOT NULL");
            $supplierColumns = [
                'phone' => "ALTER TABLE suppliers ADD phone VARCHAR(80) NOT NULL DEFAULT '' AFTER supplier_type",
                'identifier' => "ALTER TABLE suppliers ADD identifier VARCHAR(40) NOT NULL DEFAULT '' AFTER phone",
                'address' => "ALTER TABLE suppliers ADD address VARCHAR(255) NOT NULL DEFAULT '' AFTER cui",
                'county_code' => "ALTER TABLE suppliers ADD county_code VARCHAR(10) NOT NULL DEFAULT '' AFTER address",
                'county_name' => "ALTER TABLE suppliers ADD county_name VARCHAR(80) NOT NULL DEFAULT '' AFTER county_code",
                'locality_siruta' => "ALTER TABLE suppliers ADD locality_siruta INT NULL AFTER county_name",
                'locality_name' => "ALTER TABLE suppliers ADD locality_name VARCHAR(160) NOT NULL DEFAULT '' AFTER locality_siruta",
                'postal_code' => "ALTER TABLE suppliers ADD postal_code VARCHAR(20) NOT NULL DEFAULT '' AFTER locality_name",
            ];
            foreach ($supplierColumns as $name => $sql) {
                if (!$this->pdo->query("SHOW COLUMNS FROM suppliers LIKE '$name'")->fetch()) {
                    $this->pdo->exec($sql);
                }
            }
        }

        if ($this->pdo->query("SHOW TABLES LIKE 'purchase_lots'")->fetchColumn()) {
            $this->pdo->exec("UPDATE purchase_lots SET supplier_type = 'PJ/PFA' WHERE supplier_type = 'PFA/SRL'");
            $purchaseColumns = [
                'purchase_date' => "ALTER TABLE purchase_lots ADD purchase_date DATE NULL AFTER status",
                'external_document_type' => "ALTER TABLE purchase_lots ADD external_document_type ENUM('borderou', 'carnet', 'factura') NOT NULL DEFAULT 'borderou' AFTER purchase_date",
                'external_document_series' => "ALTER TABLE purchase_lots ADD external_document_series VARCHAR(80) NOT NULL DEFAULT '' AFTER external_document_type",
                'external_document_number' => "ALTER TABLE purchase_lots ADD external_document_number VARCHAR(80) NOT NULL DEFAULT '' AFTER external_document_series",
                'external_document_date' => "ALTER TABLE purchase_lots ADD external_document_date DATE NULL AFTER external_document_number",
                'borderou_position' => "ALTER TABLE purchase_lots ADD borderou_position VARCHAR(40) NOT NULL DEFAULT '' AFTER external_document_date",
                'net_g' => "ALTER TABLE purchase_lots ADD net_g INT NOT NULL DEFAULT 0 AFTER shrinkage_pct",
                'purchase_price_cents_per_kg' => "ALTER TABLE purchase_lots ADD purchase_price_cents_per_kg INT NOT NULL DEFAULT 0 AFTER net_g",
                'total_amount_cents' => "ALTER TABLE purchase_lots ADD total_amount_cents INT NOT NULL DEFAULT 0 AFTER purchase_price_cents_per_kg",
            ];
            foreach ($purchaseColumns as $name => $sql) {
                if (!$this->pdo->query("SHOW COLUMNS FROM purchase_lots LIKE '$name'")->fetch()) {
                    $this->pdo->exec($sql);
                }
            }
            if ($this->pdo->query("SHOW COLUMNS FROM purchase_lots LIKE 'processor_id'")->fetch()) {
                $stmt = $this->pdo->prepare(
                    "SELECT CONSTRAINT_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'purchase_lots'
                       AND COLUMN_NAME = 'processor_id'
                       AND REFERENCED_TABLE_NAME IS NOT NULL
                     LIMIT 1"
                );
                $stmt->execute();
                $constraint = (string) $stmt->fetchColumn();
                if ($constraint !== '') {
                    $this->pdo->exec('ALTER TABLE purchase_lots DROP FOREIGN KEY `' . str_replace('`', '``', $constraint) . '`');
                }
                $this->pdo->exec("ALTER TABLE purchase_lots DROP COLUMN processor_id");
            }
            $this->pdo->exec("UPDATE purchase_lots SET purchase_date = DATE(created_at) WHERE purchase_date IS NULL");
            $this->pdo->exec("UPDATE purchase_lots SET net_g = gross_g WHERE net_g = 0");
            $this->pdo->exec("UPDATE purchase_lots SET external_document_type = CASE WHEN supplier_type = 'PF' THEN 'borderou' WHEN supplier_type = 'Producator agricol' THEN 'carnet' ELSE 'factura' END WHERE external_document_number = ''");
            $this->pdo->exec("UPDATE purchase_lots SET status = 'In stoc' WHERE status IN ('Achizitie', 'Predat Procesator', 'Receptionat Faguri', 'Inchis')");
            $this->pdo->exec("ALTER TABLE purchase_lots MODIFY supplier_type ENUM('PF', 'Producator agricol', 'PJ/PFA') NOT NULL");
            $this->pdo->exec("ALTER TABLE purchase_lots MODIFY status ENUM('In stoc', 'Partial vandut', 'Vandut') NOT NULL DEFAULT 'In stoc'");
            if (!$this->pdo->query("SHOW INDEX FROM purchase_lots WHERE Key_name = 'unique_purchase_external_position'")->fetch()) {
                $this->pdo->exec("ALTER TABLE purchase_lots ADD UNIQUE KEY unique_purchase_external_position (external_document_type, external_document_series, external_document_number, borderou_position)");
            }
        }

        if (!$this->pdo->query("SHOW TABLES LIKE 'purchase_wax_exits'")->fetchColumn()) {
            $this->pdo->exec(
                "CREATE TABLE purchase_wax_exits (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->pdo->query("SHOW TABLES LIKE 'siruta_counties'")->fetchColumn()) {
            $this->pdo->exec(
                "CREATE TABLE siruta_counties (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    county_code VARCHAR(10) NOT NULL UNIQUE,
                    siruta_code INT NOT NULL,
                    name VARCHAR(80) NOT NULL,
                    normalized_name VARCHAR(80) NOT NULL,
                    INDEX idx_county_name (normalized_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!$this->pdo->query("SHOW TABLES LIKE 'siruta_localities'")->fetchColumn()) {
            $this->pdo->exec(
                "CREATE TABLE siruta_localities (
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
                    INDEX idx_locality_parent (parent_siruta)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }
}
