<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;
use Throwable;

final class SettingsService
{
    /**
     * @param callable(string,int):?array $find
     * @param callable(int,string,string,?int,?string,?string):void $logAudit
     */
    public function __construct(
        private PDO $pdo,
        private $find,
        private $logAudit
    ) {
    }

    public function settings(): array
    {
        return [
            'stores' => $this->stores(),
            'processors' => $this->processors(),
            'permissions' => $this->permissions(),
            'role_permissions' => $this->rolePermissions(),
            'users' => $this->users(),
            'user_stores' => $this->userStores(),
            'document_templates' => $this->documentTemplates(),
            'company_settings' => $this->companySettings(),
            'series' => $this->pdo->query(
                'SELECT ds.*, s.name AS store_name FROM document_series ds JOIN stores s ON s.id = ds.store_id ORDER BY s.name, ds.document_type'
            )->fetchAll(),
        ];
    }

    public function saveSettings(array $data, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ?, processor_id = ? WHERE id = ?')
                ->execute([$data['store_code'], $data['store_name'], $data['store_address'], ($data['store_processor_id'] ?? 0) ?: null, $data['store_id']]);
            $this->pdo->prepare(
                'UPDATE processors SET name = ?, cui = ?, contact = ?, processing_price_cents = ?, exchange_shrinkage_pct = ?, purchase_shrinkage_pct = ? WHERE id = ?'
            )->execute([
                $data['processor_name'],
                $data['processor_cui'],
                $data['processor_contact'],
                (int) round(((float) str_replace(',', '.', $data['processing_price'])) * 100),
                (float) str_replace(',', '.', $data['exchange_shrinkage_pct']),
                (float) str_replace(',', '.', $data['purchase_shrinkage_pct']),
                $data['processor_id'],
            ]);

            foreach ($data['series'] as $id => $series) {
                $this->pdo->prepare('UPDATE document_series SET series = ?, next_number = ? WHERE id = ?')
                    ->execute([trim($series['series']), max(1, (int) $series['next_number']), (int) $id]);
            }

            ($this->logAudit)($userId, 'SETTINGS_UPDATE', 'settings', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function changeOwnPassword(int $userId, string $newPassword, string $confirmPassword): void
    {
        if ($newPassword === '' || strlen($newPassword) < 4) {
            throw new RuntimeException('Parola noua trebuie sa aiba minimum 4 caractere.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Confirmarea parolei nu se potriveste.');
        }

        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        ($this->logAudit)($userId, 'PASSWORD_CHANGE', 'users', $userId, null, 'changed');
    }

    public function saveRolePermissions(array $matrix, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($this->permissions() as $permission) {
                foreach (self::roles() as $role) {
                    $allowed = isset($matrix[$role][$permission['code']]) ? 1 : 0;
                    $this->pdo->prepare(
                        'INSERT INTO role_permissions (role_name, permission_code, allowed)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
                    )->execute([$role, $permission['code'], $allowed]);
                }
            }
            ($this->logAudit)($userId, 'ROLE_PERMISSIONS_UPDATE', 'role_permissions', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createUser(array $data, int $userId): void
    {
        $username = trim($data['username']);
        $password = (string) $data['password'];
        $role = in_array($data['role'], self::roles(), true) ? $data['role'] : 'operator';

        if ($username === '' || $password === '') {
            throw new RuntimeException('Utilizatorul si parola sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, active) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                trim($data['full_name']) ?: $username,
                $role,
                isset($data['active']) ? 1 : 0,
            ]);
            $newUserId = (int) $this->pdo->lastInsertId();

            foreach (($data['store_ids'] ?? []) as $storeId) {
                $this->pdo->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)')
                    ->execute([$newUserId, (int) $storeId]);
            }

            ($this->logAudit)($userId, 'USER_CREATE', 'users', $newUserId, null, $username);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveStore(array $data, int $userId): void
    {
        $id = (int) ($data['id'] ?? 0);
        $code = strtoupper(trim((string) $data['code']));
        $name = trim($data['name']);
        $address = trim($data['address']);
        $processorId = (int) ($data['processor_id'] ?? 0);
        $fgoSeries = strtoupper(trim((string) ($data['fgo_series'] ?? '')));
        if ($fgoSeries === '' && $code !== '') {
            $fgoSeries = $this->defaultDocumentSeries('FACT', $code);
        }
        $processingShrinkage = (float) str_replace(',', '.', (string) ($data['processing_shrinkage_pct'] ?? '0'));
        $processingPriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['processing_price'] ?? '0'))) * 100);
        $purchaseShrinkage = (float) str_replace(',', '.', (string) ($data['purchase_shrinkage_pct'] ?? '0'));
        $purchasePriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['purchase_price'] ?? '0'))) * 100);

        if ($code === '' || $name === '') {
            throw new RuntimeException('Codul si denumirea gestiunii sunt obligatorii.');
        }
        if ($processorId <= 0 || !($this->find)('processors', $processorId)) {
            throw new RuntimeException('Alege procesatorul asignat gestiunii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ?, fgo_series = ?, processing_shrinkage_pct = ?, processing_price_cents = ?, purchase_shrinkage_pct = ?, purchase_price_cents_per_kg = ?, processor_id = ? WHERE id = ?')
                    ->execute([$code, $name, $address, $fgoSeries, $processingShrinkage, $processingPriceCents, $purchaseShrinkage, $purchasePriceCents, $processorId, $id]);
                $storeId = $id;
                $operation = 'STORE_UPDATE';
            } else {
                $this->pdo->prepare('INSERT INTO stores (code, name, address, fgo_series, processing_shrinkage_pct, processing_price_cents, purchase_shrinkage_pct, purchase_price_cents_per_kg, processor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$code, $name, $address, $fgoSeries, $processingShrinkage, $processingPriceCents, $purchaseShrinkage, $purchasePriceCents, $processorId]);
                $storeId = (int) $this->pdo->lastInsertId();
                $operation = 'STORE_CREATE';

                foreach (['PV-CUST', 'FACT', 'BON', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BORD'] as $type) {
                    $this->pdo->prepare(
                        'INSERT IGNORE INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)'
                    )->execute([$storeId, $type, $this->defaultDocumentSeries($type, $code)]);
                }
            }

            ($this->logAudit)($userId, $operation, 'stores', $storeId, null, $code);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveProcessor(array $data, int $userId): void
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name']);
        $cui = trim($data['cui']);
        $address = trim($data['address']);
        $processingPrice = (int) round(((float) str_replace(',', '.', $data['processing_price'])) * 100);
        $exchangeShrinkage = (float) str_replace(',', '.', $data['exchange_shrinkage_pct']);
        $purchaseShrinkage = (float) str_replace(',', '.', $data['purchase_shrinkage_pct']);

        if ($name === '' || $cui === '' || $address === '') {
            throw new RuntimeException('Numele, CUI si adresa procesatorului sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare(
                    'UPDATE processors
                     SET name = ?, cui = ?, address = ?, processing_price_cents = ?, exchange_shrinkage_pct = ?, purchase_shrinkage_pct = ?
                     WHERE id = ?'
                )->execute([$name, $cui, $address, $processingPrice, $exchangeShrinkage, $purchaseShrinkage, $id]);
                $processorId = $id;
                $operation = 'PROCESSOR_UPDATE';
            } else {
                $this->pdo->prepare(
                    'INSERT INTO processors (name, cui, address, contact, processing_price_cents, exchange_shrinkage_pct, purchase_shrinkage_pct)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $cui, $address, '', $processingPrice, $exchangeShrinkage, $purchaseShrinkage]);
                $processorId = (int) $this->pdo->lastInsertId();
                $operation = 'PROCESSOR_CREATE';
            }

            ($this->logAudit)($userId, $operation, 'processors', $processorId, null, $name);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveDocumentTemplates(array $templates, int $userId): void
    {
        $existing = $this->documentTemplates();
        $existingById = [];
        foreach ($existing as $template) {
            $existingById[(int) $template['id']] = $template;
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($templates as $id => $templateData) {
                $templateId = (int) $id;
                if (!isset($existingById[$templateId])) {
                    continue;
                }

                $bodyHtml = trim((string) ($templateData['body_html'] ?? ''));
                if ($bodyHtml === '') {
                    throw new RuntimeException('Template-ul "' . $existingById[$templateId]['name'] . '" nu poate fi gol.');
                }

                $this->pdo->prepare(
                    'UPDATE document_templates
                     SET body_html = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([$bodyHtml, $userId, $templateId]);
            }

            ($this->logAudit)($userId, 'DOCUMENT_TEMPLATES_UPDATE', 'document_templates', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function companySettings(): array
    {
        $settings = $this->pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!$settings) {
            $this->pdo->prepare('INSERT INTO company_settings (id) VALUES (1)')->execute();
            $settings = $this->pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1')->fetch();
        }

        return $settings ?: [
            'company_name' => '',
            'vat_number' => '',
            'registry_number' => '',
            'address' => '',
            'fgo_private_key' => '',
            'purchase_default_shrinkage_pct' => 0,
            'purchase_default_price_cents_per_kg' => 0,
            'purchase_factory_shrinkage_pct' => 0,
            'purchase_factory_price_cents_per_kg' => 0,
        ];
    }

    public function permissions(): array
    {
        return $this->pdo->query(
            "SELECT * FROM permissions ORDER BY FIELD(
                code,
                'USER_CREATE',
                'USER_EDIT',
                'USER_RESET_PASSWORD',
                'STORE_MANAGE',
                'PROCESSOR_MANAGE',
                'DOCUMENT_TEMPLATE_MANAGE',
                'PROCESSING_CREATE',
                'PROCESSING_ACCEPT',
                'PROCESSING_REJECT',
                'PURCHASE_CREATE',
                'REPORT_VIEW',
                'AUDIT_VIEW'
            )"
        )->fetchAll();
    }

    public function rolePermissions(): array
    {
        $rows = $this->pdo->query('SELECT * FROM role_permissions')->fetchAll();
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['role_name']][$row['permission_code']] = (bool) $row['allowed'];
        }
        return $matrix;
    }

    public function documentTemplates(): array
    {
        $rows = $this->pdo->query('SELECT * FROM document_templates ORDER BY id')->fetchAll();
        foreach ($rows as &$row) {
            $variables = json_decode((string) $row['variables_json'], true);
            $row['variables'] = is_array($variables) ? $variables : [];
        }
        unset($row);
        return $rows;
    }

    public function users(): array
    {
        return $this->pdo->query('SELECT id, username, full_name, role, active, created_at FROM users ORDER BY id')->fetchAll();
    }

    public function userStores(): array
    {
        $rows = $this->pdo->query('SELECT * FROM user_stores')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (int) $row['store_id'];
        }
        return $map;
    }

    public function stores(): array
    {
        return $this->pdo->query(
            'SELECT s.*, p.name AS processor_name
             FROM stores s
             LEFT JOIN processors p ON p.id = s.processor_id
             ORDER BY s.name'
        )->fetchAll();
    }

    public function processors(): array
    {
        return $this->pdo->query('SELECT * FROM processors ORDER BY id')->fetchAll();
    }

    private static function roles(): array
    {
        return ['admin', 'operator'];
    }

    private function defaultDocumentSeries(string $type, string $code): string
    {
        $type = strtoupper(trim($type));
        $code = strtoupper(trim($code));
        return $type . '-' . $code;
    }
}
