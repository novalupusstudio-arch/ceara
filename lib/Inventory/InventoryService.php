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

    private function writer(): InventoryWriter
    {
        return new InventoryWriter($this->pdo);
    }
}
