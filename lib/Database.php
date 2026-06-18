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
            if (!$pdo->query("SHOW COLUMNS FROM customers LIKE '$name'")->fetch()) {
                $pdo->exec($sql);
            }
        }
        if ($pdo->query("SHOW COLUMNS FROM customers LIKE 'identifier'")->fetch()
            && $pdo->query("SHOW COLUMNS FROM customers LIKE 'cui'")->fetch()) {
            $pdo->exec("UPDATE customers SET identifier = cui WHERE identifier = '' AND cui <> ''");
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
            'file_path' => 'ALTER TABLE documents ADD file_path VARCHAR(255) NULL AFTER status',
            'external_url' => 'ALTER TABLE documents ADD external_url VARCHAR(500) NULL AFTER file_path',
            'created_by' => 'ALTER TABLE documents ADD created_by INT NULL AFTER notes',
            'printed_at' => 'ALTER TABLE documents ADD printed_at TIMESTAMP NULL AFTER created_by',
        ];
        foreach ($documentColumns as $name => $sql) {
            if (!$pdo->query("SHOW COLUMNS FROM documents LIKE '$name'")->fetch()) {
                $pdo->exec($sql);
            }
        }

        if (!$pdo->query("SHOW TABLES LIKE 'document_templates'")->fetchColumn()) {
            $pdo->exec(
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

        if (!$pdo->query("SHOW TABLES LIKE 'company_settings'")->fetchColumn()) {
            $pdo->exec(
                "CREATE TABLE company_settings (
                    id TINYINT PRIMARY KEY,
                    company_name VARCHAR(160) NOT NULL DEFAULT '',
                    vat_number VARCHAR(40) NOT NULL DEFAULT '',
                    registry_number VARCHAR(80) NOT NULL DEFAULT '',
                    address VARCHAR(255) NOT NULL DEFAULT '',
                    updated_by INT NULL,
                    updated_at TIMESTAMP NULL,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$pdo->query("SHOW TABLES LIKE 'siruta_counties'")->fetchColumn()) {
            $pdo->exec(
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
        if (!$pdo->query("SHOW TABLES LIKE 'siruta_localities'")->fetchColumn()) {
            $pdo->exec(
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

    private function seed(PDO $pdo): void
    {
        $permissions = [
            'USER_CREATE' => 'Creare utilizatori',
            'USER_EDIT' => 'Editare utilizatori',
            'USER_RESET_PASSWORD' => 'Resetare parole',
            'STORE_MANAGE' => 'Administrare gestiuni',
            'PROCESSOR_MANAGE' => 'Administrare procesatori',
            'DOCUMENT_TEMPLATE_MANAGE' => 'Administrare template documente',
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

        $pdo->prepare('INSERT IGNORE INTO company_settings (id) VALUES (1)')->execute();

        $this->seedDocumentTemplates($pdo);
        $this->seedSiruta($pdo);

        if (!($this->config['seed_defaults'] ?? true)) {
            return;
        }

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

    private function seedDocumentTemplates(PDO $pdo): void
    {
        foreach ($this->defaultDocumentTemplates() as $template) {
            $pdo->prepare(
                'INSERT IGNORE INTO document_templates
                (code, name, description, body_html, variables_json, active)
                VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([
                $template['code'],
                $template['name'],
                $template['description'],
                $template['body_html'],
                json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function seedSiruta(PDO $pdo): void
    {
        if (((int) $pdo->query('SELECT COUNT(*) FROM siruta_localities')->fetchColumn()) > 0) {
            return;
        }

        $path = __DIR__ . '/../release/siruta.csv';
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            fclose($handle);
            return;
        }

        $headers = array_map(fn ($value) => trim((string) $value, " \t\n\r\0\x0B\""), $headers);
        $rows = [];
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (count($line) < count($headers)) {
                continue;
            }
            $row = array_combine($headers, $line);
            if (!$row) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        $counties = [];
        foreach ($rows as $row) {
            $level = $this->sirutaInt($row['NIV'] ?? 0);
            $type = $this->sirutaInt($row['TIP'] ?? 0);
            if ($level !== 1 || $type !== 40) {
                continue;
            }

            $name = $this->sirutaCleanName((string) ($row['DENLOC'] ?? ''));
            $name = preg_replace('/^JUDETUL\s+/i', '', $name) ?? $name;
            $countyCode = trim((string) ($row['JUD'] ?? ''));
            if ($countyCode === '') {
                continue;
            }
            $counties[$countyCode] = [
                'siruta_code' => $this->sirutaInt($row['SIRUTA'] ?? 0),
                'name' => $name,
                'normalized_name' => $this->sirutaNormalize($name),
            ];
        }

        $pdo->beginTransaction();
        try {
            $countyStmt = $pdo->prepare(
                'INSERT INTO siruta_counties (county_code, siruta_code, name, normalized_name)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE siruta_code = VALUES(siruta_code), name = VALUES(name), normalized_name = VALUES(normalized_name)'
            );
            foreach ($counties as $countyCode => $county) {
                $countyStmt->execute([$countyCode, $county['siruta_code'], $county['name'], $county['normalized_name']]);
            }

            $localities = [];
            foreach ($rows as $row) {
                $level = $this->sirutaInt($row['NIV'] ?? 0);
                $type = $this->sirutaInt($row['TIP'] ?? 0);
                $countyCode = trim((string) ($row['JUD'] ?? ''));
                if ($level < 2 || $type === 40 || $countyCode === '' || !isset($counties[$countyCode])) {
                    continue;
                }

                $siruta = $this->sirutaInt($row['SIRUTA'] ?? 0);
                if ($siruta <= 0) {
                    continue;
                }

                $rawName = $this->sirutaCleanName((string) ($row['DENLOC'] ?? ''));
                $name = $this->sirutaDisplayBaseName($rawName);
                $localities[$siruta] = [
                    'siruta_code' => $siruta,
                    'county_code' => $countyCode,
                    'name' => $name,
                    'normalized_name' => $this->sirutaNormalize($name),
                    'display_name' => $name,
                    'postal_code' => (string) max(0, $this->sirutaInt($row['CODP'] ?? 0)),
                    'parent_siruta' => $this->sirutaInt($row['SIRSUP'] ?? 0),
                    'parent_name' => '',
                    'parent_type' => '',
                    'type_code' => $type,
                    'level_no' => $level,
                    'duplicate_name_in_county' => 0,
                ];
            }

            foreach ($localities as &$locality) {
                $parent = $localities[$locality['parent_siruta']] ?? null;
                if ($parent) {
                    $locality['parent_name'] = $parent['name'];
                    $locality['parent_type'] = $this->sirutaParentType((int) $parent['type_code']);
                }
            }
            unset($locality);

            $duplicates = [];
            foreach ($localities as $locality) {
                $key = $locality['county_code'] . '|' . $locality['normalized_name'];
                $duplicates[$key] = ($duplicates[$key] ?? 0) + 1;
            }

            foreach ($localities as &$locality) {
                $key = $locality['county_code'] . '|' . $locality['normalized_name'];
                if (($duplicates[$key] ?? 0) > 1 && $locality['parent_name'] !== '') {
                    $locality['duplicate_name_in_county'] = 1;
                    $locality['display_name'] = $locality['name'] . ' (' . trim($locality['parent_type'] . ' ' . $locality['parent_name']) . ')';
                }
            }
            unset($locality);

            $localityStmt = $pdo->prepare(
                'INSERT INTO siruta_localities
                (siruta_code, county_code, name, normalized_name, display_name, postal_code, parent_siruta, parent_name, parent_type, type_code, level_no, duplicate_name_in_county)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    county_code = VALUES(county_code),
                    name = VALUES(name),
                    normalized_name = VALUES(normalized_name),
                    display_name = VALUES(display_name),
                    postal_code = VALUES(postal_code),
                    parent_siruta = VALUES(parent_siruta),
                    parent_name = VALUES(parent_name),
                    parent_type = VALUES(parent_type),
                    type_code = VALUES(type_code),
                    level_no = VALUES(level_no),
                    duplicate_name_in_county = VALUES(duplicate_name_in_county)'
            );
            foreach ($localities as $locality) {
                $localityStmt->execute([
                    $locality['siruta_code'],
                    $locality['county_code'],
                    $locality['name'],
                    $locality['normalized_name'],
                    $locality['display_name'],
                    $locality['postal_code'] === '0' ? '' : $locality['postal_code'],
                    $locality['parent_siruta'] ?: null,
                    $locality['parent_name'],
                    $locality['parent_type'],
                    $locality['type_code'],
                    $locality['level_no'],
                    $locality['duplicate_name_in_county'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    private function sirutaInt(mixed $value): int
    {
        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    private function sirutaCleanName(string $value): string
    {
        $converted = $value;
        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2, UTF-8');
            } catch (ValueError) {
                $converted = $value;
            }
        }
        $converted = strtr($converted, [
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Þ' => 'T', 'þ' => 't', 'ª' => 'S', 'º' => 's',
        ]);
        $converted = preg_replace('/\s+/', ' ', $converted) ?? $converted;
        return trim($converted);
    }

    private function sirutaDisplayBaseName(string $name): string
    {
        $name = preg_replace('/^(MUNICIPIUL|ORASUL|ORAS|COMUNA|SATUL|SAT)\s+/i', '', $name) ?? $name;
        return trim($name);
    }

    private function sirutaNormalize(string $value): string
    {
        $value = $this->sirutaCleanName($value);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function sirutaParentType(int $typeCode): string
    {
        return match ($typeCode) {
            1 => 'mun.',
            2 => 'oras',
            3 => 'com.',
            default => 'loc.',
        };
    }

    private function templateFile(string $fileName): string
    {
        $path = __DIR__ . '/templates/' . $fileName;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Lipseste template-ul de document: ' . $fileName);
        }
        return $content;
    }

    private function defaultDocumentTemplates(): array
    {
        return [
            [
                'code' => 'PV-CUST',
                'name' => 'PV primire ceara bruta in custodie',
                'description' => 'Proces-verbal pentru luarea in custodie a cerii brute de la client.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'customer_type',
                    'lot_number',
                    'gross_wax_kg',
                    'package_count',
                    'wax_observations',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => $this->templateFile('pv-cust.html'),
            ],
            [
                'code' => 'PV-FAG',
                'name' => 'PV predare faguri client',
                'description' => 'Proces-verbal pentru predarea fagurilor catre client in urma schimbului de ceara.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'lot_number',
                    'wax_processed_kg',
                    'shrinkage_pct',
                    'foundation_delivered_kg',
                    'service_value',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .notes-box {
    border: 1px solid #000;
    min-height: 42px;
    padding: 6px;
    margin-top: 6px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>PV-FAG</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">PROCES-VERBAL DE PREDARE FAGURI</div>
<div class="document-subtitle">aferent serviciului de procesare ceara</div>

<table class="info-table">
  <tr>
    <td class="label">Client</td>
    <td>[customer_name]</td>
    <td class="label">Lot</td>
    <td>[lot_number]</td>
  </tr>
  <tr>
    <td class="label">CNP / CI / CUI</td>
    <td>[customer_identifier]</td>
    <td class="label">Telefon</td>
    <td>[customer_phone]</td>
  </tr>
  <tr>
    <td class="label">Adresa</td>
    <td colspan="3">[customer_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Data predarii</td>
    <td>[document_date]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:42%;">Denumire</th>
      <th style="width:10%;">UM</th>
      <th style="width:15%;">Cantitate ceara</th>
      <th style="width:15%;">Scazamant</th>
      <th style="width:10%;">Faguri predati</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-center">1</td>
      <td>Faguri de ceara rezultati din serviciul de procesare</td>
      <td class="text-center">kg</td>
      <td class="text-right">[wax_processed_kg]</td>
      <td class="text-right">[shrinkage_pct] %</td>
      <td class="text-right">[foundation_delivered_kg]</td>
    </tr>
    <tr>
      <td colspan="5" class="text-right"><strong>Total faguri predati</strong></td>
      <td class="text-right"><strong>[foundation_delivered_kg]</strong></td>
    </tr>
  </tbody>
</table>

<h4>Observatii</h4>
<div class="notes-box">[notes]</div>

<div class="legal-box">
  Prin prezentul proces-verbal clientul confirma primirea cantitatii de faguri mentionate mai sus.
  Cantitatea de faguri a fost determinata prin aplicarea scazamantului aferent serviciului de procesare asupra cantitatii de ceara preluate.
  Predarea se realizeaza in sistem de echivalent cantitativ si calitativ, conform conditiilor generale ale serviciului de procesare ceara.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de</strong><br>
      [customer_name]
      <div class="signature-line">Semnatura client</div>
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'PV-RET',
                'name' => 'PV retur ceara client',
                'description' => 'Proces-verbal pentru returnarea cerii catre client cand ceara este respinsa.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'lot_number',
                    'wax_returned_kg',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .notes-box {
    border: 1px solid #000;
    min-height: 42px;
    padding: 6px;
    margin-top: 6px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>PV-RET</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">PROCES-VERBAL DE RETUR CEARA</div>
<div class="document-subtitle">ceara respinsa / neacceptata la procesare</div>

<table class="info-table">
  <tr>
    <td class="label">Client</td>
    <td>[customer_name]</td>
    <td class="label">Lot</td>
    <td>[lot_number]</td>
  </tr>
  <tr>
    <td class="label">CNP / CI / CUI</td>
    <td>[customer_identifier]</td>
    <td class="label">Telefon</td>
    <td>[customer_phone]</td>
  </tr>
  <tr>
    <td class="label">Adresa</td>
    <td colspan="3">[customer_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Data returului</td>
    <td>[document_date]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:62%;">Denumire</th>
      <th style="width:10%;">UM</th>
      <th style="width:20%;">Cantitate returnata</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-center">1</td>
      <td>Ceara bruta returnata clientului ca respinsa / neacceptata la procesare</td>
      <td class="text-center">kg</td>
      <td class="text-right">[wax_returned_kg]</td>
    </tr>
    <tr>
      <td colspan="3" class="text-right"><strong>Total ceara returnata</strong></td>
      <td class="text-right"><strong>[wax_returned_kg]</strong></td>
    </tr>
  </tbody>
</table>

<h4>Observatii / motiv retur</h4>
<div class="notes-box">[notes]</div>

<div class="legal-box">
  Prin prezentul proces-verbal clientul confirma primirea cantitatii de ceara mentionate mai sus.
  Returul se face ca urmare a respingerii sau neacceptarii cerii pentru serviciul de procesare.
  Predarea cerii returnate inchide evidenta operationala aferenta cantitatii returnate din lot.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de</strong><br>
      [customer_name]
      <div class="signature-line">Semnatura client</div>
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'AVIZ',
                'name' => 'Aviz predare ceara catre fabrica',
                'description' => 'Aviz pentru predarea catre procesator a cerii brute din unul sau mai multe loturi.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'processor_name',
                    'processor_identifier',
                    'processor_address',
                    'factory_batch_number',
                    'factory_items_rows',
                    'factory_wax_total_kg',
                    'factory_foundation_expected_kg',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>AVIZ</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">AVIZ DE PREDARE CEARA CATRE FABRICA</div>
<div class="document-subtitle">pentru serviciul de procesare ceara</div>

<table class="info-table">
  <tr>
    <td class="label">Procesator</td>
    <td>[processor_name]</td>
    <td class="label">Batch</td>
    <td>[factory_batch_number]</td>
  </tr>
  <tr>
    <td class="label">CUI procesator</td>
    <td>[processor_identifier]</td>
    <td class="label">Data predarii</td>
    <td>[document_date]</td>
  </tr>
  <tr>
    <td class="label">Adresa procesator</td>
    <td colspan="3">[processor_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Gestiune</td>
    <td>[store_name]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:32%;">Lot</th>
      <th style="width:40%;">Client</th>
      <th style="width:20%;">Ceara predata</th>
    </tr>
  </thead>
  <tbody>
    [factory_items_rows]
    <tr>
      <td colspan="3" class="text-right"><strong>Total ceara predata</strong></td>
      <td class="text-right"><strong>[factory_wax_total_kg]</strong></td>
    </tr>
  </tbody>
</table>

<p>
  <strong>Faguri estimati dupa procesare:</strong> [factory_foundation_expected_kg]
</p>

<div class="legal-box">
  Ceara mentionata mai sus este predata catre procesator in vederea procesarii.
  Loturile raman evidentiate operational separat, iar cantitatile receptionate ulterior de la procesator se vor inregistra pe baza documentelor de receptie.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de procesator</strong><br>
      [processor_name]
      <div class="signature-line">Semnatura / stampila</div>
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'NIR',
                'name' => 'NIR buffer faguri fabrica',
                'description' => 'Nota de intrare receptie pentru avizul de faguri primit de la fabrica.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'nir_number',
                    'nir_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_code',
                    'store_address',
                    'operator_name',
                    'processor_name',
                    'processor_identifier',
                    'processor_address',
                    'aviz_number',
                    'aviz_date',
                    'adjustment_type',
                    'adjustment_label',
                    'foundation_qty_kg',
                    'foundation_qty_g',
                    'item_name',
                    'item_unit',
                    'item_qty',
                    'item_unit_price',
                    'item_value',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    line-height: 1.25;
  }

  h2 {
    font-size: 16px;
    margin: 0 0 8px;
    text-align: center;
  }

  p {
    margin: 6px 0;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  th,
  td {
    border: 1px solid #222;
    padding: 5px;
  }
</style>

<h2>NOTA DE INTRARE RECEPTIE</h2>

<p>
  <strong>Nr.:</strong> [nir_number] &nbsp;&nbsp;
  <strong>Data:</strong> [nir_date]
</p>

<p>
  <strong>Societate:</strong> [company_name]<br>
  <strong>CUI:</strong> [company_vat_number]<br>
  <strong>Nr. Reg. Com.:</strong> [company_registry_number]<br>
  <strong>Sediu:</strong> [company_address]<br>
  <strong>Gestiune:</strong> [store_name] - [store_address]
</p>

<p>
  <strong>Furnizor / Procesator:</strong> [processor_name]<br>
  <strong>CUI:</strong> [processor_identifier]<br>
  <strong>Adresa:</strong> [processor_address]
</p>

<p>
  <strong>Aviz:</strong> [aviz_number] din [aviz_date]<br>
  <strong>Tip miscare:</strong> [adjustment_label]
</p>

<table>
  <thead>
    <tr>
      <th>Produs</th>
      <th>UM</th>
      <th>Cantitate</th>
      <th>Pret unitar</th>
      <th>Valoare</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>[item_name]</td>
      <td>[item_unit]</td>
      <td style="text-align:right;">[item_qty]</td>
      <td style="text-align:right;">[item_unit_price]</td>
      <td style="text-align:right;">[item_value]</td>
    </tr>
  </tbody>
</table>

<p>
  <strong>Observatii:</strong><br>
  [notes]
</p>

<table style="margin-top:40px; border:0;">
  <tr>
    <td style="width:50%; text-align:center; border:0;">
      <strong>Gestionar</strong><br><br>
      [operator_name]<br><br>
      Semnatura: ______________________
    </td>
    <td style="width:50%; text-align:center; border:0;">
      <strong>Comisie receptie</strong><br><br><br><br>
      Semnatura: ______________________
    </td>
  </tr>
</table>

HTML,
            ],
        ];
    }
}
