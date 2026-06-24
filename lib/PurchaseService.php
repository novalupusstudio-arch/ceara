<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Inventory\InventoryService;
use PDO;
use RuntimeException;
use Throwable;

final class PurchaseService
{
    /**
     * @param callable(int):void $documentRenderer
     */
    public function __construct(
        private PDO $pdo,
        private InventoryService $inventory,
        private SupplierService $suppliers,
        private DocumentService $documents,
        private $documentRenderer
    ) {
    }

    public function purchaseLots(int $userId): array
    {
        $store = $this->requireUserPrimaryStore($userId);
        $stmt = $this->pdo->prepare(
            'SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone, s.identifier AS supplier_identifier,
                    s.cui AS supplier_cui, s.locality_name AS supplier_locality, st.name AS store_name
             FROM purchase_lots p
             JOIN suppliers s ON s.id = p.supplier_id
             JOIN stores st ON st.id = p.store_id
             WHERE p.store_id = ?
             ORDER BY p.id DESC'
        );
        $stmt->execute([(int) $store['id']]);
        return $stmt->fetchAll();
    }

    public function purchaseRegisterData(int $userId, string $dateStart = '', string $dateEnd = ''): array
    {
        $store = $this->requireUserPrimaryStore($userId);

        $dateStart = $this->normalizeDate($dateStart) ?: date('Y-m-01');
        $dateEnd = $this->normalizeDate($dateEnd) ?: date('Y-m-d');
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        $periodStart = $dateStart . ' 00:00:00';
        $periodEnd = $dateEnd . ' 23:59:59';

        $rows = [];
        foreach ($this->inventory->purchaseRegisterRows((int) $store['id'], $periodStart, $periodEnd) as $row) {
            $rows[] = array_merge($row, $this->purchaseRegisterMeta($row['reference_type'], (int) $row['reference_id']));
        }

        return [
            'store' => $store,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'stock_g' => $this->sumInventoryForStore('wax_purchased', (int) $store['id']),
            'opening_g' => $this->sumInventoryForStoreUntil('wax_purchased', (int) $store['id'], $periodStart, false),
            'closing_g' => $this->sumInventoryForStoreUntil('wax_purchased', (int) $store['id'], $periodEnd, true),
            'rows' => $rows,
            'lots' => $this->purchaseLots($userId),
        ];
    }

    public function purchaseExitData(int $userId): array
    {
        $store = $this->requireUserPrimaryStore($userId);

        return [
            'store' => $store,
            'stock_g' => $this->sumInventoryForStore('wax_purchased', (int) $store['id']),
            'exits' => $this->purchaseWaxExits((int) $store['id']),
        ];
    }

    public function createPurchaseLot(array $data, int $userId): int
    {
        $supplierType = $this->normalizeSupplierType((string) ($data['supplier_type'] ?? 'PF'));
        $gross = kg_to_grams((string) ($data['gross_kg'] ?? '0'));
        $shrinkage = (float) str_replace(',', '.', (string) ($data['shrinkage_pct'] ?? '0'));
        $priceCents = (int) round(((float) str_replace(',', '.', (string) ($data['purchase_price'] ?? '0'))) * 100);
        $net = max(0, (int) round($gross * (1 - ($shrinkage / 100))));
        $total = (int) round(($gross / 1000) * $priceCents);
        $document = $this->purchaseDocumentData($supplierType, $data);

        if ($gross <= 0) {
            throw new RuntimeException('Cantitatea de ceara trebuie sa fie mai mare decat zero.');
        }
        if ($priceCents < 0) {
            throw new RuntimeException('Pretul de achizitie nu poate fi negativ.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->assertUniquePurchaseDocument($document);
            $supplier = $this->suppliers->resolvePurchaseSupplier($data);
            $supplierId = (int) $supplier['id'];
            $lotNumber = $this->nextLotNumber('ACH');

            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_lots
                (lot_number, supplier_id, supplier_type, status, purchase_date, external_document_type,
                 external_document_series, external_document_number, external_document_date, borderou_position,
                 gross_g, shrinkage_pct, net_g, purchase_price_cents_per_kg, total_amount_cents, foundation_g, store_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $stmt->execute([
                $lotNumber,
                $supplierId,
                $supplierType,
                'In stoc',
                $this->normalizeDate((string) ($data['purchase_date'] ?? '')) ?: date('Y-m-d'),
                $document['type'],
                $document['series'],
                $document['number'],
                $document['date'] ?: null,
                $document['position'],
                $gross,
                $shrinkage,
                $net,
                $priceCents,
                $total,
                $data['store_id'],
                $userId,
            ]);
            $lotId = (int) $this->pdo->lastInsertId();

            $this->inventory->record('wax_purchased', $gross, (int) $data['store_id'], 'purchase_lot', $lotId, 'Ceara cumparata');
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
        $store = $this->requireUserPrimaryStore($userId);
        $lot = $this->find('purchase_lots', $lotId);
        if (!$lot) {
            throw new RuntimeException('Achizitia nu exista.');
        }
        if ((int) $lot['store_id'] !== (int) $store['id']) {
            throw new RuntimeException('Achizitia nu apartine gestiunii utilizatorului.');
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
                $this->documents->issue('AVIZ', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'Aviz procesator');
            }
            if ($newStatus === 'Receptionat Faguri') {
                $this->documents->issue('NIR', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'NIR produse finite');
                $this->inventory->record('foundation_merchandise', (int) $lot['foundation_g'], (int) $lot['store_id'], 'purchase_lot', $lotId, 'Faguri marfa receptionati');
            }
            $this->logAudit($userId, 'PURCHASE_ADVANCE', 'purchase_lots', $lotId, $lot['status'], $newStatus);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createPurchaseWaxExit(array $data, int $userId): int
    {
        $storeId = (int) ($data['store_id'] ?? 0);
        $qty = kg_to_grams((string) ($data['qty_kg'] ?? '0'));
        $stock = $this->sumInventoryForStore('wax_purchased', $storeId);
        $partnerName = trim((string) ($data['partner_name'] ?? ''));
        $documentNumber = trim((string) ($data['document_number'] ?? ''));

        if ($qty <= 0) {
            throw new RuntimeException('Cantitatea de iesire trebuie sa fie mai mare decat zero.');
        }
        if ($qty > $stock) {
            throw new RuntimeException('Cantitatea de iesire depaseste stocul de ceara achizitionata.');
        }
        if ($partnerName === '') {
            throw new RuntimeException('Partenerul/fabrica este obligatoriu.');
        }
        if ($documentNumber === '') {
            throw new RuntimeException('Numarul documentului este obligatoriu.');
        }

        $this->pdo->beginTransaction();
        try {
            $exitNumber = $this->nextLotNumber('IES-ACH');
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_wax_exits
                (exit_number, partner_name, partner_identifier, document_type, document_series, document_number,
                 document_date, qty_g, store_id, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $exitNumber,
                $partnerName,
                trim((string) ($data['partner_identifier'] ?? '')),
                trim((string) ($data['document_type'] ?? '')),
                trim((string) ($data['document_series'] ?? '')),
                $documentNumber,
                $this->normalizeDate((string) ($data['document_date'] ?? '')) ?: null,
                $qty,
                $storeId,
                trim((string) ($data['notes'] ?? '')),
                $userId,
            ]);
            $exitId = (int) $this->pdo->lastInsertId();
            $this->inventory->record('wax_purchased', -$qty, $storeId, 'purchase_wax_exit', $exitId, 'Iesire ceara achizitionata');
            $this->logAudit($userId, 'PURCHASE_WAX_EXIT', 'purchase_wax_exits', $exitId, null, $exitNumber);
            $this->pdo->commit();

            return $exitId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function purchaseDocumentData(string $supplierType, array $data): array
    {
        $type = match ($supplierType) {
            'Producator agricol' => 'carnet',
            'PJ/PFA' => 'factura',
            default => 'borderou',
        };
        $series = trim((string) ($data['document_series'] ?? ''));
        $number = trim((string) ($data['document_number'] ?? ''));
        $position = trim((string) ($data['document_position'] ?? ''));
        $date = $this->normalizeDate((string) ($data['document_date'] ?? ''));

        if ($series === '' || $number === '') {
            throw new RuntimeException($type === 'factura' ? 'Seria si numarul facturii sunt obligatorii.' : 'Seria si numarul documentului sunt obligatorii.');
        }
        if ($type !== 'factura' && $position === '') {
            throw new RuntimeException('Pozitia din document este obligatorie.');
        }
        if ($type === 'factura' && !$date) {
            throw new RuntimeException('Data facturii este obligatorie.');
        }

        return compact('type', 'series', 'number', 'position', 'date');
    }

    private function normalizeSupplierType(string $supplierType): string
    {
        return match ($supplierType) {
            'Producator agricol' => 'Producator agricol',
            'PJ/PFA', 'PFA/SRL' => 'PJ/PFA',
            default => 'PF',
        };
    }

    private function assertUniquePurchaseDocument(array $document): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM purchase_lots
             WHERE external_document_type = ?
               AND external_document_series = ?
               AND external_document_number = ?
               AND borderou_position = ?
             LIMIT 1'
        );
        $stmt->execute([
            $document['type'],
            $document['series'],
            $document['number'],
            $document['position'],
        ]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Documentul de achizitie exista deja in baza de date.');
        }
    }

    private function purchaseRegisterMeta(string $referenceType, int $referenceId): array
    {
        if ($referenceType === 'purchase_lot') {
            $stmt = $this->pdo->prepare(
                'SELECT p.*, s.name AS supplier_name, u.username
                 FROM purchase_lots p
                 JOIN suppliers s ON s.id = p.supplier_id
                 JOIN users u ON u.id = p.created_by
                 WHERE p.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'partner' => (string) $row['supplier_name'],
                    'document' => trim((string) ($row['external_document_series'] . '-' . $row['external_document_number']), '-'),
                    'position' => (string) ($row['borderou_position'] ?? ''),
                    'operator' => (string) $row['username'],
                ];
            }
        }

        if ($referenceType === 'purchase_wax_exit') {
            $stmt = $this->pdo->prepare(
                'SELECT e.*, u.username
                 FROM purchase_wax_exits e
                 JOIN users u ON u.id = e.created_by
                 WHERE e.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'partner' => (string) $row['partner_name'],
                    'document' => trim((string) ($row['document_series'] . '-' . $row['document_number']), '-'),
                    'position' => '',
                    'operator' => (string) $row['username'],
                ];
            }
        }

        return [
            'partner' => '-',
            'document' => '-',
            'position' => '',
            'operator' => '-',
        ];
    }

    private function purchaseWaxExits(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, s.name AS store_name, u.username
             FROM purchase_wax_exits e
             JOIN stores s ON s.id = e.store_id
             JOIN users u ON u.id = e.created_by
             WHERE e.store_id = ?
             ORDER BY e.id DESC
             LIMIT 100'
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    private function userPrimaryStore(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.name AS processor_name
             FROM user_stores us
             JOIN stores s ON s.id = us.store_id
             LEFT JOIN processors p ON p.id = s.processor_id
             WHERE us.user_id = ?
             ORDER BY us.store_id
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $store = $stmt->fetch();

        return $store ?: null;
    }

    private function requireUserPrimaryStore(int $userId): array
    {
        $store = $this->userPrimaryStore($userId);
        if (!$store) {
            throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
        }

        return $store;
    }

    private function sumInventoryForStore(string $type, int $storeId): int
    {
        return $this->inventory->sumForStore($type, $storeId);
    }

    private function sumInventoryForStoreUntil(string $type, int $storeId, string $dateTime, bool $inclusive): int
    {
        return $this->inventory->sumForStoreUntil($type, $storeId, $dateTime, $inclusive);
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed instanceof \DateTime && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        $parts = array_map('intval', explode('-', $date));
        return checkdate($parts[1], $parts[2], $parts[0]) ? $date : '';
    }

    private function nextLotNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value, created_at)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }
}
