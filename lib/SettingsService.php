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
            'processors' => $this->processors(),
            'stores' => $this->stores(),
            'permissions' => $this->permissions(),
            'role_permissions' => $this->rolePermissions(),
            'users' => $this->users(),
            'user_stores' => $this->userStores(),
            'document_templates' => $this->documentTemplates(),
            'company_settings' => $this->companySettings(),
            'series' => $this->documentSeries(),
        ];
    }

    public function saveCompanySettings(array $data, int $userId): void
    {
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $vatNumber = trim((string) ($data['vat_number'] ?? ''));
        $registryNumber = trim((string) ($data['registry_number'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $fgoUrl = trim((string) ($data['fgo_url'] ?? ''));
        $fgoToken = trim((string) ($data['fgo_token'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO company_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id = id')->execute();
            $this->pdo->prepare(
                'UPDATE company_settings
                 SET company_name = ?, vat_number = ?, registry_number = ?, address = ?, phone = ?, email = ?, fgo_url = ?, fgo_token = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = 1'
            )->execute([$companyName, $vatNumber, $registryNumber, $address, $phone, $email, $fgoUrl, $fgoToken, $userId]);

            ($this->logAudit)($userId, 'COMPANY_SETTINGS_UPDATE', 'company_settings', 1, null, $companyName);
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
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) $data['password'];
        $role = in_array(($data['role'] ?? ''), self::roles(), true) ? $data['role'] : 'operator';
        $storeId = $this->normalizeUserStoreId($data['store_id'] ?? 0);

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
                trim((string) ($data['full_name'] ?? '')) ?: $username,
                $role,
                isset($data['active']) ? 1 : 0,
            ]);
            $newUserId = (int) $this->pdo->lastInsertId();

            $this->syncUserStore($newUserId, $storeId);

            ($this->logAudit)($userId, 'USER_CREATE', 'users', $newUserId, null, $username);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function updateUser(array $data, int $userId): void
    {
        $targetUserId = (int) ($data['id'] ?? 0);
        $existing = $this->findUser($targetUserId);
        if (!$existing) {
            throw new RuntimeException('Utilizatorul selectat nu exista.');
        }

        $role = in_array(($data['role'] ?? ''), self::roles(), true) ? $data['role'] : 'operator';
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $storeId = $this->normalizeUserStoreId($data['store_id'] ?? 0);
        $active = isset($data['active']) ? 1 : 0;

        $this->pdo->beginTransaction();
        try {
            $params = [
                $fullName !== '' ? $fullName : (string) $existing['username'],
                $role,
                $active,
            ];
            $sql = 'UPDATE users SET full_name = ?, role = ?, active = ?';

            if ($password !== '') {
                if (strlen($password) < 4) {
                    throw new RuntimeException('Parola noua trebuie sa aiba minimum 4 caractere.');
                }
                $sql .= ', password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = ?';
            $params[] = $targetUserId;

            $this->pdo->prepare($sql)->execute($params);
            $this->syncUserStore($targetUserId, $storeId);

            ($this->logAudit)($userId, 'USER_UPDATE', 'users', $targetUserId, (string) $existing['username'], (string) $existing['username']);
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
        $processingShrinkage = (float) str_replace(',', '.', (string) ($data['processing_shrinkage_pct'] ?? '0'));
        $processingPriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['processing_price'] ?? '0'))) * 100);
        $purchaseShrinkage = (float) str_replace(',', '.', (string) ($data['purchase_shrinkage_pct'] ?? '0'));
        $purchasePriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['purchase_price'] ?? '0'))) * 100);

        if ($code === '' || $name === '') {
            throw new RuntimeException('Codul si denumirea gestiunii sunt obligatorii.');
        }
        if ($fgoSeries === '') {
            throw new RuntimeException('Seria FGO este obligatorie pentru gestiune.');
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

                foreach (['PV-CUST', 'BON', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BORD'] as $type) {
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

        if ($name === '' || $cui === '' || $address === '') {
            throw new RuntimeException('Numele, CUI si adresa procesatorului sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare(
                    'UPDATE processors
                     SET name = ?, cui = ?, address = ?, processing_price_cents = ?, exchange_shrinkage_pct = ?
                     WHERE id = ?'
                )->execute([$name, $cui, $address, $processingPrice, $exchangeShrinkage, $id]);
                $processorId = $id;
                $operation = 'PROCESSOR_UPDATE';
            } else {
                $this->pdo->prepare(
                    'INSERT INTO processors (name, cui, address, processing_price_cents, exchange_shrinkage_pct)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$name, $cui, $address, $processingPrice, $exchangeShrinkage]);
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

    public function saveDocumentSeries(array $seriesRows, int $userId): void
    {
        $allowedTypes = $this->internalDocumentSeriesTypes();

        $this->pdo->beginTransaction();
        try {
            foreach ($seriesRows as $id => $series) {
                $seriesId = (int) $id;
                $existing = $this->findDocumentSeries($seriesId);
                if (!$existing) {
                    continue;
                }
                if (!in_array((string) $existing['document_type'], $allowedTypes, true)) {
                    continue;
                }

                $seriesValue = strtoupper(trim((string) ($series['series'] ?? '')));
                $nextNumber = max(1, (int) ($series['next_number'] ?? 1));
                if ($seriesValue === '') {
                    throw new RuntimeException('Seria documentului nu poate fi goala.');
                }

                $this->pdo->prepare('UPDATE document_series SET series = ?, next_number = ? WHERE id = ?')
                    ->execute([$seriesValue, $nextNumber, $seriesId]);
            }

            ($this->logAudit)($userId, 'DOCUMENT_SERIES_UPDATE', 'document_series', null, null, 'updated');
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

        if (!$settings) {
            throw new RuntimeException('Setarile societatii nu au putut fi initializate.');
        }

        return $settings;
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

    public function documentSeries(): array
    {
        $allowedTypes = $this->internalDocumentSeriesTypes();
        $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT ds.*, s.name AS store_name, s.code AS store_code
             FROM document_series ds
             JOIN stores s ON s.id = ds.store_id
             WHERE ds.document_type IN ($placeholders)
             ORDER BY s.name, FIELD(ds.document_type, 'PV-CUST', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BON', 'BORD')"
        );
        $stmt->execute($allowedTypes);

        return $stmt->fetchAll();
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

    private function internalDocumentSeriesTypes(): array
    {
        return ['PV-CUST', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BON', 'BORD'];
    }

    private function normalizeUserStoreId(mixed $rawStoreId): int
    {
        $storeId = (int) $rawStoreId;
        if ($storeId > 0 && !($this->find)('stores', $storeId)) {
            throw new RuntimeException('Gestiunea selectata nu exista.');
        }

        return max(0, $storeId);
    }

    private function syncUserStore(int $targetUserId, int $storeId): void
    {
        $this->pdo->prepare('DELETE FROM user_stores WHERE user_id = ?')->execute([$targetUserId]);

        if ($storeId > 0) {
            $this->pdo->prepare('INSERT INTO user_stores (user_id, store_id) VALUES (?, ?)')
                ->execute([$targetUserId, $storeId]);
        }
    }

    private function findUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findDocumentSeries(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_series WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
