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

        $this->seedDocumentTemplates($pdo);

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
                'body_html' => <<<'HTML'
<h2 style="text-align:center;">
  PROCES-VERBAL DE PREDARE IN CUSTODIE CEARA BRUTA
</h2>

<p style="text-align:center;">
  pentru serviciul de procesare ceara
</p>

<p>
  <strong>Nr.:</strong> [document_number] &nbsp;&nbsp;
  <strong>Data:</strong> [document_date]
</p>

<h3>1. Date prestator</h3>

<p>
  <strong>Societate:</strong> [company_name]<br>
  <strong>CUI:</strong> [company_vat_number]<br>
  <strong>Nr. Reg. Com.:</strong> [company_registry_number]<br>
  <strong>Sediu:</strong> [company_address]<br>
  <strong>Punct de lucru / gestiune:</strong> [store_name] - [store_address]
</p>

<p>
  Reprezentata prin operator / gestionar: <strong>[operator_name]</strong>
</p>

<h3>2. Date client</h3>

<p>
  <strong>Nume / Denumire:</strong> [customer_name]<br>
  <strong>CNP / CUI:</strong> [customer_identifier]<br>
  <strong>Adresa / Localitate:</strong> [customer_address]<br>
  <strong>Telefon:</strong> [customer_phone]<br>
  <strong>Tip client:</strong> [customer_type]
</p>

<h3>3. Date lot</h3>

<p>
  <strong>Lot intern:</strong> [lot_number]<br>
  <strong>Cantitate ceara bruta predata:</strong> [gross_wax_kg] kg<br>
  <strong>Numar bucati / colete:</strong> [package_count]<br>
  <strong>Observatii privind starea cerii:</strong><br>
  [wax_observations]
</p>

<h3>4. Obiectul predarii</h3>

<p>
  Clientul preda societatii cantitatea de ceara bruta mentionata mai sus, in custodie,
  in vederea prestarii serviciului de procesare ceara.
</p>

<p>
  Predarea cerii nu reprezinta vanzare, achizitie, donatie sau transfer de proprietate catre societate.
</p>

<p>
  Ceara bruta ramane in evidenta operationala a societatii ca bun primit in custodie
  pentru executarea serviciului de procesare.
</p>

<h3>5. Conditii de procesare</h3>

<p>Clientul ia la cunostinta ca:</p>

<ul>
  <li>societatea poate efectua schimbul imediat, din stocul operational de faguri, sau poate conditiona predarea fagurilor de verificarea prealabila a cerii;</li>
  <li>cantitatea de faguri rezultata se calculeaza prin aplicarea scazamantului stabilit pentru serviciul de procesare;</li>
  <li>serviciul se poate realiza in sistem de echivalent cantitativ si calitativ, fara obligatia restituirii fizice a exact aceleiasi mase de ceara;</li>
  <li>ceara cu suspiciune de parafina, impuritati excesive, corpuri straine sau alte neconformitati poate fi refuzata;</li>
  <li>ceara refuzata se poate restitui clientului pe baza de proces-verbal de predare ceara neacceptata.</li>
</ul>

<h3>6. Conditii generale</h3>

<p>
  Clientul declara ca a luat cunostinta si accepta
  <strong>Conditiile Generale pentru Serviciul de Procesare Ceara</strong>
  ale societatii, disponibile in punctul de lucru si/sau pe site-ul societatii.
</p>

<h3>7. Confirmare predare</h3>

<p>
  Prin semnarea prezentului proces-verbal, clientul confirma ca a predat cantitatea de ceara bruta
  mentionata mai sus, iar operatorul confirma primirea acesteia in custodie.
</p>

<table style="width:100%; margin-top:40px;">
  <tr>
    <td style="width:50%; text-align:center;">
      <strong>Client / Predator</strong><br><br>
      Nume: [customer_name]<br><br>
      Semnatura: ______________________
    </td>
    <td style="width:50%; text-align:center;">
      <strong>Operator / Gestionar</strong><br><br>
      Nume: [operator_name]<br><br>
      Semnatura: ______________________
    </td>
  </tr>
</table>

<p style="font-size:10px; margin-top:30px;">
  Document generat din aplicatia [app_name] la data [generated_at]. Cod document: [document_number]. Lot: [lot_number].
</p>
HTML,
            ],
        ];
    }
}
