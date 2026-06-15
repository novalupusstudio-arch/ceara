<?php

final class App
{
    private const ROLES = ['admin', 'operator'];

    public function __construct(public PDO $pdo)
    {
    }

    public function stores(): array
    {
        return $this->pdo->query('SELECT * FROM stores ORDER BY name')->fetchAll();
    }

    public function processors(): array
    {
        return $this->pdo->query('SELECT * FROM processors ORDER BY name')->fetchAll();
    }

    public function dashboard(): array
    {
        return [
            'foundation_operational_g' => $this->sumInventory('foundation_operational'),
            'wax_custody_g' => $this->sumInventory('wax_custody'),
            'pending_lots' => $this->countRows('processing_lots', "status = 'In Validare'"),
            'rejected_lots' => $this->countRows('processing_lots', "status = 'Respins'"),
            'wax_owned_g' => $this->sumInventory('wax_owned'),
            'foundation_merchandise_g' => $this->sumInventory('foundation_merchandise'),
        ];
    }

    public function processingLots(): array
    {
        return $this->pdo->query(
            'SELECT p.*, c.name AS customer_name, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             ORDER BY p.id DESC'
        )->fetchAll();
    }

    public function purchaseLots(): array
    {
        return $this->pdo->query(
            'SELECT p.*, s.name AS supplier_name, st.name AS store_name, pr.name AS processor_name
             FROM purchase_lots p
             JOIN suppliers s ON s.id = p.supplier_id
             JOIN stores st ON st.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             ORDER BY p.id DESC'
        )->fetchAll();
    }

    public function documents(): array
    {
        return $this->pdo->query(
            'SELECT d.*, st.name AS store_name
             FROM documents d
             LEFT JOIN stores st ON st.id = d.store_id
             ORDER BY d.id DESC LIMIT 80'
        )->fetchAll();
    }

    public function audit(): array
    {
        return $this->pdo->query(
            'SELECT a.*, u.username
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 100'
        )->fetchAll();
    }

    public function createProcessingLot(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $customerId = $this->upsertCustomer($data['customer_name'], $data['customer_phone'], $data['known_customer']);
            $lotNumber = $this->nextLotNumber('PROC');
            $gross = kg_to_grams($data['gross_kg']);
            $shrinkage = (float) $data['shrinkage_pct'];
            $foundation = max(0, (int) round($gross * (1 - ($shrinkage / 100))));
            $status = $data['known_customer'] ? 'Acceptat' : 'In Validare';

            $stmt = $this->pdo->prepare(
                'INSERT INTO processing_lots
                (lot_number, customer_id, status, gross_g, shrinkage_pct, foundation_g, store_id, processor_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $lotNumber,
                $customerId,
                $status,
                $gross,
                $shrinkage,
                $foundation,
                $data['store_id'],
                $data['processor_id'] ?: null,
                $userId,
            ]);
            $lotId = (int) $this->pdo->lastInsertId();

            $this->inventory('wax_custody', $gross, (int) $data['store_id'], 'processing_lot', $lotId, 'Ceara client in custodie');
            $this->document('PV-CUST', 'processing_lot', $lotId, (int) $data['store_id'], 'mock', 'PV predare in custodie');
            if ($status === 'Acceptat') {
                $this->document('FACT', 'processing_lot', $lotId, (int) $data['store_id'], 'mock', 'Factura serviciu mock');
                $this->document('PV-FAG', 'processing_lot', $lotId, (int) $data['store_id'], 'mock', 'PV predare faguri');
            }
            $this->logAudit($userId, 'PROCESSING_CREATE', 'processing_lots', $lotId, null, $status);

            $this->pdo->commit();
            return $lotId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function transitionProcessingLot(int $lotId, string $action, int $userId): void
    {
        $lot = $this->find('processing_lots', $lotId);
        if (!$lot) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $map = [
            'accept' => ['from' => 'In Validare', 'to' => 'Acceptat', 'docs' => ['FACT', 'PV-FAG']],
            'reject' => ['from' => 'In Validare', 'to' => 'Respins', 'docs' => []],
            'send_factory' => ['from' => 'Acceptat', 'to' => 'Predat Fabricii', 'docs' => ['AVIZ', 'NIR']],
            'return' => ['from' => 'Respins', 'to' => 'Returnat', 'docs' => ['PV-RET']],
        ];

        if (!isset($map[$action]) || $lot['status'] !== $map[$action]['from']) {
            throw new RuntimeException('Tranzitie invalida pentru statusul curent.');
        }

        $this->pdo->beginTransaction();
        try {
            $newStatus = $map[$action]['to'];
            $this->pdo->prepare('UPDATE processing_lots SET status = ? WHERE id = ?')->execute([$newStatus, $lotId]);
            foreach ($map[$action]['docs'] as $type) {
                $this->document($type, 'processing_lot', $lotId, (int) $lot['store_id'], 'mock', 'Document generat mock');
            }
            $this->logAudit($userId, 'PROCESSING_' . strtoupper($action), 'processing_lots', $lotId, $lot['status'], $newStatus);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createPurchaseLot(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $supplierId = $this->upsertSupplier($data['supplier_name'], $data['supplier_type'], $data['supplier_cui']);
            $lotNumber = $this->nextLotNumber('ACH');
            $gross = kg_to_grams($data['gross_kg']);
            $shrinkage = (float) $data['shrinkage_pct'];
            $foundation = max(0, (int) round($gross * (1 - ($shrinkage / 100))));

            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_lots
                (lot_number, supplier_id, supplier_type, status, gross_g, shrinkage_pct, foundation_g, store_id, processor_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $lotNumber,
                $supplierId,
                $data['supplier_type'],
                'Achizitie',
                $gross,
                $shrinkage,
                $foundation,
                $data['store_id'],
                $data['processor_id'] ?: null,
                $userId,
            ]);
            $lotId = (int) $this->pdo->lastInsertId();

            $this->inventory('wax_owned', $gross, (int) $data['store_id'], 'purchase_lot', $lotId, 'Ceara cumparata');
            $this->document($data['supplier_type'] === 'PF' ? 'BORD' : 'FACT', 'purchase_lot', $lotId, (int) $data['store_id'], 'mock', 'Document achizitie mock');
            $this->document('NIR', 'purchase_lot', $lotId, (int) $data['store_id'], 'mock', 'NIR materie prima');
            $this->logAudit($userId, 'PURCHASE_CREATE', 'purchase_lots', $lotId, null, 'Achizitie');

            $this->pdo->commit();
            return $lotId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function advancePurchaseLot(int $lotId, int $userId): void
    {
        $lot = $this->find('purchase_lots', $lotId);
        if (!$lot) {
            throw new RuntimeException('Achizitia nu exista.');
        }

        $next = [
            'Achizitie' => 'Predat Procesator',
            'Predat Procesator' => 'Receptionat Faguri',
            'Receptionat Faguri' => 'Inchis',
        ];

        if (!isset($next[$lot['status']])) {
            throw new RuntimeException('Achizitia este deja inchisa.');
        }

        $this->pdo->beginTransaction();
        try {
            $newStatus = $next[$lot['status']];
            $this->pdo->prepare('UPDATE purchase_lots SET status = ? WHERE id = ?')->execute([$newStatus, $lotId]);
            if ($newStatus === 'Predat Procesator') {
                $this->document('AVIZ', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'Aviz procesator');
            }
            if ($newStatus === 'Receptionat Faguri') {
                $this->document('NIR', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'NIR produse finite');
                $this->inventory('foundation_merchandise', (int) $lot['foundation_g'], (int) $lot['store_id'], 'purchase_lot', $lotId, 'Faguri marfa receptionati');
            }
            $this->logAudit($userId, 'PURCHASE_ADVANCE', 'purchase_lots', $lotId, $lot['status'], $newStatus);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveSettings(array $data, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ? WHERE id = ?')
                ->execute([$data['store_code'], $data['store_name'], $data['store_address'], $data['store_id']]);
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

            $this->logAudit($userId, 'SETTINGS_UPDATE', 'settings', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
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
            'series' => $this->pdo->query(
                'SELECT ds.*, s.name AS store_name FROM document_series ds JOIN stores s ON s.id = ds.store_id ORDER BY s.name, ds.document_type'
            )->fetchAll(),
        ];
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
        $this->logAudit($userId, 'PASSWORD_CHANGE', 'users', $userId, null, 'changed');
    }

    public function saveRolePermissions(array $matrix, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($this->permissions() as $permission) {
                foreach (self::ROLES as $role) {
                    $allowed = isset($matrix[$role][$permission['code']]) ? 1 : 0;
                    $this->pdo->prepare(
                        'INSERT INTO role_permissions (role_name, permission_code, allowed)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
                    )->execute([$role, $permission['code'], $allowed]);
                }
            }
            $this->logAudit($userId, 'ROLE_PERMISSIONS_UPDATE', 'role_permissions', null, null, 'updated');
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
        $role = in_array($data['role'], self::ROLES, true) ? $data['role'] : 'operator';

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

            $this->logAudit($userId, 'USER_CREATE', 'users', $newUserId, null, $username);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveStore(array $data, int $userId): void
    {
        $id = (int) ($data['id'] ?? 0);
        $code = trim($data['code']);
        $name = trim($data['name']);
        $address = trim($data['address']);

        if ($code === '' || $name === '') {
            throw new RuntimeException('Codul si denumirea gestiunii sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ? WHERE id = ?')
                    ->execute([$code, $name, $address, $id]);
                $storeId = $id;
                $operation = 'STORE_UPDATE';
            } else {
                $this->pdo->prepare('INSERT INTO stores (code, name, address) VALUES (?, ?, ?)')
                    ->execute([$code, $name, $address]);
                $storeId = (int) $this->pdo->lastInsertId();
                $operation = 'STORE_CREATE';

                foreach (['PV-CUST', 'FACT', 'BON', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BORD'] as $type) {
                    $this->pdo->prepare(
                        'INSERT IGNORE INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)'
                    )->execute([$storeId, $type, $type . '-' . $code]);
                }
            }

            $this->logAudit($userId, $operation, 'stores', $storeId, null, $code);
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

            $this->logAudit($userId, $operation, 'processors', $processorId, null, $name);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function roleHasPermission(string $role, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT allowed FROM role_permissions WHERE role_name = ? AND permission_code = ? LIMIT 1'
        );
        $stmt->execute([$role, $permission]);
        return (bool) $stmt->fetchColumn();
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

    private function upsertCustomer(string $name, string $phone, bool $known): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO customers (name, phone, known_customer) VALUES (?, ?, ?)');
        $stmt->execute([$name, $phone, $known ? 1 : 0]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upsertSupplier(string $name, string $type, string $cui): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO suppliers (name, supplier_type, cui) VALUES (?, ?, ?)');
        $stmt->execute([$name, $type, $cui]);
        return (int) $this->pdo->lastInsertId();
    }

    private function nextLotNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function document(string $type, string $referenceType, int $referenceId, int $storeId, string $status, string $notes): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_series WHERE store_id = ? AND document_type = ? FOR UPDATE'
        );
        $stmt->execute([$storeId, $type]);
        $series = $stmt->fetch();

        if (!$series) {
            $this->pdo->prepare(
                'INSERT INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)'
            )->execute([$storeId, $type, $type]);
            $series = ['id' => $this->pdo->lastInsertId(), 'series' => $type, 'next_number' => 1];
        }

        $number = (int) $series['next_number'];
        $this->pdo->prepare(
            'INSERT INTO documents (document_type, series, number, store_id, reference_type, reference_id, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$type, $series['series'], $number, $storeId, $referenceType, $referenceId, $status, $notes]);

        $this->pdo->prepare('UPDATE document_series SET next_number = next_number + 1 WHERE id = ?')
            ->execute([$series['id']]);
    }

    private function inventory(string $type, int $qty, int $storeId, string $refType, int $refId, string $notes): void
    {
        $this->pdo->prepare(
            'INSERT INTO inventory_transactions (movement_type, qty_g, store_id, reference_type, reference_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$type, $qty, $storeId, $refType, $refId, $notes]);
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }

    private function sumInventory(string $type): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty_g), 0) FROM inventory_transactions WHERE movement_type = ?');
        $stmt->execute([$type]);
        return (int) $stmt->fetchColumn();
    }

    private function countRows(string $table, string $where): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
