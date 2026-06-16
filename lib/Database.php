<?php

final class Database
{
    private array $config;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $db = $this->config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }

    public function ensureDatabase(): void
    {
        $db = $this->config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['charset']
        );

        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $name = str_replace('`', '``', $db['name']);
        $charset = $db['charset'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
    }

    public function migrateAndSeed(): void
    {
        $pdo = $this->pdo();
        $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Schema file missing.');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }

        $this->migrateExistingTables($pdo);
        $this->seed($pdo);
    }

    private function migrateExistingTables(PDO $pdo): void
    {
        $column = $pdo->query("SHOW COLUMNS FROM processors LIKE 'address'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE processors ADD address VARCHAR(255) NOT NULL DEFAULT '' AFTER cui");
        }

        if (!$pdo->query("SHOW COLUMNS FROM stores LIKE 'processor_id'")->fetch()) {
            $pdo->exec("ALTER TABLE stores ADD processor_id INT NULL AFTER address");
        }

        $customerColumns = [
            'customer_type' => "ALTER TABLE customers ADD customer_type ENUM('PF', 'PJ') NOT NULL DEFAULT 'PF' AFTER id",
            'address' => "ALTER TABLE customers ADD address VARCHAR(255) NOT NULL DEFAULT '' AFTER phone",
            'cui' => "ALTER TABLE customers ADD cui VARCHAR(40) NOT NULL DEFAULT '' AFTER address",
            'representative' => "ALTER TABLE customers ADD representative VARCHAR(160) NOT NULL DEFAULT '' AFTER cui",
        ];
        foreach ($customerColumns as $name => $sql) {
            if (!$pdo->query("SHOW COLUMNS FROM customers LIKE '$name'")->fetch()) {
                $pdo->exec($sql);
            }
        }

        if (!$pdo->query("SHOW COLUMNS FROM processing_lots LIKE 'processing_price_cents'")->fetch()) {
            $pdo->exec("ALTER TABLE processing_lots ADD processing_price_cents INT NOT NULL DEFAULT 0 AFTER gross_g");
        }
        if (!$pdo->query("SHOW COLUMNS FROM processing_lots LIKE 'factory_sent_g'")->fetch()) {
            $pdo->exec("ALTER TABLE processing_lots ADD factory_sent_g INT NOT NULL DEFAULT 0 AFTER gross_g");
        }

        $table = $pdo->query("SHOW TABLES LIKE 'processing_lot_status_events'")->fetchColumn();
        if (!$table) {
            $pdo->exec(
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

        $table = $pdo->query("SHOW TABLES LIKE 'processing_lot_movements'")->fetchColumn();
        if (!$table) {
            $pdo->exec(
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

        $table = $pdo->query("SHOW TABLES LIKE 'factory_batches'")->fetchColumn();
        if (!$table) {
            $pdo->exec(
                "CREATE TABLE factory_batches (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $table = $pdo->query("SHOW TABLES LIKE 'factory_batch_items'")->fetchColumn();
        if (!$table) {
            $pdo->exec(
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

        $table = $pdo->query("SHOW TABLES LIKE 'factory_buffer_adjustments'")->fetchColumn();
        if (!$table) {
            $pdo->exec(
                "CREATE TABLE factory_buffer_adjustments (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $documentColumns = [
            'lot_id' => 'ALTER TABLE documents ADD lot_id INT NULL AFTER store_id',
            'movement_id' => 'ALTER TABLE documents ADD movement_id INT NULL AFTER lot_id',
            'factory_batch_id' => 'ALTER TABLE documents ADD factory_batch_id INT NULL AFTER movement_id',
            'created_by' => 'ALTER TABLE documents ADD created_by INT NULL AFTER notes',
            'printed_at' => 'ALTER TABLE documents ADD printed_at TIMESTAMP NULL AFTER created_by',
        ];
        foreach ($documentColumns as $name => $sql) {
            if (!$pdo->query("SHOW COLUMNS FROM documents LIKE '$name'")->fetch()) {
                $pdo->exec($sql);
            }
        }
    }

    private function seed(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'Administrator', 'admin']);
        }

        $storeCount = (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn();
        if ($storeCount === 0) {
            $pdo->prepare('INSERT INTO stores (code, name, address) VALUES (?, ?, ?)')
                ->execute(['GEST1', 'Gestiune principala', '']);
        }

        $adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        $firstStoreId = (int) $pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn();
        if ($adminId > 0 && $firstStoreId > 0) {
            $pdo->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)')
                ->execute([$adminId, $firstStoreId]);
        }

        $processorCount = (int) $pdo->query('SELECT COUNT(*) FROM processors')->fetchColumn();
        if ($processorCount === 0) {
            $pdo->prepare(
                'INSERT INTO processors (name, cui, address, contact, processing_price_cents, exchange_shrinkage_pct, purchase_shrinkage_pct) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute(['Procesator implicit', '', '', '', 0, 0, 0]);
        }

        $firstProcessorId = (int) $pdo->query('SELECT id FROM processors ORDER BY id LIMIT 1')->fetchColumn();
        if ($firstProcessorId > 0 && $pdo->query("SHOW COLUMNS FROM stores LIKE 'processor_id'")->fetch()) {
            $pdo->prepare('UPDATE stores SET processor_id = ? WHERE processor_id IS NULL')
                ->execute([$firstProcessorId]);
        }

        $permissions = [
            'USER_CREATE' => 'Creare utilizatori',
            'USER_EDIT' => 'Editare utilizatori',
            'USER_RESET_PASSWORD' => 'Resetare parole',
            'STORE_MANAGE' => 'Administrare gestiuni',
            'PROCESSOR_MANAGE' => 'Administrare procesatori',
            'PROCESSING_CREATE' => 'Creare procesare',
            'PROCESSING_ACCEPT' => 'Acceptare procesare',
            'PROCESSING_REJECT' => 'Respingere procesare',
            'PURCHASE_CREATE' => 'Creare achizitii',
            'REPORT_VIEW' => 'Vizualizare rapoarte',
            'AUDIT_VIEW' => 'Vizualizare audit',
        ];

        foreach ($permissions as $code => $label) {
            $pdo->prepare('INSERT IGNORE INTO permissions (code, label) VALUES (?, ?)')
                ->execute([$code, $label]);
        }

        foreach (array_keys($permissions) as $code) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_name, permission_code, allowed) VALUES (?, ?, ?)')
                ->execute(['admin', $code, 1]);
        }

        $operatorDefaults = [
            'PROCESSING_CREATE',
            'PURCHASE_CREATE',
            'REPORT_VIEW',
        ];
        foreach (array_keys($permissions) as $code) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_name, permission_code, allowed) VALUES (?, ?, ?)')
                ->execute(['operator', $code, in_array($code, $operatorDefaults, true) ? 1 : 0]);
        }

        $seriesCount = (int) $pdo->query('SELECT COUNT(*) FROM document_series')->fetchColumn();
        if ($seriesCount === 0) {
            $storeId = (int) $pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn();
            foreach (['PV-CUST', 'FACT', 'BON', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BORD'] as $type) {
                $pdo->prepare(
                    'INSERT INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)'
                )->execute([$storeId, $type, $type . '-GEST1']);
            }
        }

        $bufferTable = $pdo->query("SHOW TABLES LIKE 'factory_buffer_adjustments'")->fetchColumn();
        $documentColumnsReady = $pdo->query("SHOW COLUMNS FROM documents LIKE 'created_by'")->fetch();
        if ($bufferTable && $documentColumnsReady) {
            $rows = $pdo->query(
                "SELECT a.*
                 FROM factory_buffer_adjustments a
                 LEFT JOIN documents d ON d.reference_type = 'factory_buffer_adjustment'
                    AND d.reference_id = a.id
                    AND d.document_type = 'NIR'
                 WHERE d.id IS NULL"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $stmt = $pdo->prepare('SELECT * FROM document_series WHERE store_id = ? AND document_type = ?');
                $stmt->execute([(int) $row['store_id'], 'NIR']);
                $series = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$series) {
                    $pdo->prepare('INSERT INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)')
                        ->execute([(int) $row['store_id'], 'NIR', 'NIR']);
                    $series = ['id' => (int) $pdo->lastInsertId(), 'series' => 'NIR', 'next_number' => 1];
                }

                $number = (int) $series['next_number'];
                $pdo->prepare(
                    'INSERT INTO documents
                    (document_type, series, number, store_id, factory_batch_id, reference_type, reference_id, status, notes, created_by)
                    VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)'
                )->execute([
                    'NIR',
                    $series['series'],
                    $number,
                    (int) $row['store_id'],
                    'factory_buffer_adjustment',
                    (int) $row['id'],
                    'issued',
                    'NIR buffer fabrica pentru aviz ' . $row['aviz_number'],
                    (int) $row['created_by'],
                ]);
                $pdo->prepare('UPDATE document_series SET next_number = next_number + 1 WHERE id = ?')
                    ->execute([(int) $series['id']]);
            }
        }
    }
}
