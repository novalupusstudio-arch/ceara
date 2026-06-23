<?php

declare(strict_types=1);

namespace Ceara;

use PDO;

final class DatabaseSeeder
{
    private DocumentTemplateSeeder $documentTemplateSeeder;
    private SirutaSeeder $sirutaSeeder;

    public function __construct(private PDO $pdo, private array $config)
    {
        $this->documentTemplateSeeder = new DocumentTemplateSeeder($this->pdo);
        $this->sirutaSeeder = new SirutaSeeder($this->pdo);
    }

    public function run(): void
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
            $this->pdo->prepare('INSERT IGNORE INTO permissions (code, label) VALUES (?, ?)')
                ->execute([$code, $label]);
        }

        foreach (array_keys($permissions) as $code) {
            $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_name, permission_code, allowed) VALUES (?, ?, ?)')
                ->execute(['admin', $code, 1]);
        }

        $operatorDefaults = [
            'PROCESSING_CREATE',
            'PURCHASE_CREATE',
            'REPORT_VIEW',
        ];
        foreach (array_keys($permissions) as $code) {
            $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_name, permission_code, allowed) VALUES (?, ?, ?)')
                ->execute(['operator', $code, in_array($code, $operatorDefaults, true) ? 1 : 0]);
        }

        $this->pdo->prepare('INSERT IGNORE INTO company_settings (id) VALUES (1)')->execute();

        $this->documentTemplateSeeder->run();
        $this->sirutaSeeder->run();

        if (!($this->config['seed_defaults'] ?? true)) {
            return;
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'Administrator', 'admin']);
        }

        $this->seedMissingFactoryBufferNirs();
    }

    private function seedMissingFactoryBufferNirs(): void
    {
        $bufferTable = $this->pdo->query("SHOW TABLES LIKE 'factory_buffer_adjustments'")->fetchColumn();
        $documentColumnsReady = $this->pdo->query("SHOW COLUMNS FROM documents LIKE 'created_by'")->fetch();
        if (!$bufferTable || !$documentColumnsReady) {
            return;
        }

        $rows = $this->pdo->query(
            "SELECT a.*
             FROM factory_buffer_adjustments a
             LEFT JOIN documents d ON d.reference_type = 'factory_buffer_adjustment'
                AND d.reference_id = a.id
                AND d.document_type = 'NIR'
             WHERE d.id IS NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $stmt = $this->pdo->prepare('SELECT * FROM document_series WHERE store_id = ? AND document_type = ?');
            $stmt->execute([(int) $row['store_id'], 'NIR']);
            $series = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$series) {
                throw new \RuntimeException('Lipseste seria NIR pentru gestiunea #' . (int) $row['store_id'] . '. Configureaza seria documentului si reincearca.');
            }

            $number = (int) $series['next_number'];
            $this->pdo->prepare(
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
            $this->pdo->prepare('UPDATE document_series SET next_number = next_number + 1 WHERE id = ?')
                ->execute([(int) $series['id']]);
        }
    }
}
