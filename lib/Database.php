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
    }
}
