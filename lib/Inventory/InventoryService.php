<?php

declare(strict_types=1);

namespace Ceara\Inventory;

use PDO;

final class InventoryService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(string $type, int $qty, int $storeId, string $refType, int $refId, string $notes): void
    {
        $this->writer()->record($type, $qty, $storeId, $refType, $refId, $notes);
    }

    public function sum(string $type): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty_g), 0) FROM inventory_transactions WHERE movement_type = ?');
        $stmt->execute([$type]);

        return (int) $stmt->fetchColumn();
    }

    public function sumForStore(string $type, int $storeId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty_g), 0) FROM inventory_transactions WHERE movement_type = ? AND store_id = ?');
        $stmt->execute([$type, $storeId]);

        return (int) $stmt->fetchColumn();
    }

    public function sumForStoreUntil(string $type, int $storeId, string $dateTime, bool $inclusive): int
    {
        $operator = $inclusive ? '<=' : '<';
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(qty_g), 0)
             FROM inventory_transactions
             WHERE movement_type = ?
               AND store_id = ?
               AND created_at $operator ?"
        );
        $stmt->execute([$type, $storeId, $dateTime]);

        return (int) $stmt->fetchColumn();
    }

    public function processingRegisterRows(int $storeId, string $periodStart, string $periodEnd): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT reference_type,
                    reference_id,
                    MAX(created_at) AS created_at,
                    COALESCE(SUM(CASE WHEN movement_type = 'wax_custody' THEN qty_g ELSE 0 END), 0) AS wax_g,
                    COALESCE(SUM(CASE WHEN movement_type = 'foundation_operational' THEN qty_g ELSE 0 END), 0) AS foundation_g
             FROM inventory_transactions
             WHERE store_id = ?
               AND movement_type IN ('wax_custody', 'foundation_operational')
               AND reference_type IN ('processing_lot', 'processing_lot_movement', 'factory_batch', 'factory_buffer_adjustment')
             GROUP BY reference_type, reference_id
             HAVING created_at BETWEEN ? AND ?
             ORDER BY created_at DESC, reference_id DESC"
        );
        $stmt->execute([$storeId, $periodStart, $periodEnd]);

        return $stmt->fetchAll();
    }

    public function purchaseRegisterRows(int $storeId, string $periodStart, string $periodEnd): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, u.username
             FROM inventory_transactions i
             LEFT JOIN users u ON u.id = (
                CASE
                    WHEN i.reference_type = 'purchase_lot' THEN (SELECT created_by FROM purchase_lots WHERE id = i.reference_id)
                    WHEN i.reference_type = 'purchase_wax_exit' THEN (SELECT created_by FROM purchase_wax_exits WHERE id = i.reference_id)
                    ELSE NULL
                END
             )
             WHERE i.store_id = ?
               AND i.movement_type = 'wax_purchased'
               AND i.created_at BETWEEN ? AND ?
             ORDER BY i.created_at DESC, i.id DESC"
        );
        $stmt->execute([$storeId, $periodStart, $periodEnd]);

        return $stmt->fetchAll();
    }

    private function writer(): InventoryWriter
    {
        return new InventoryWriter($this->pdo);
    }
}
