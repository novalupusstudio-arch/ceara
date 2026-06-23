<?php

declare(strict_types=1);

namespace Ceara\Inventory;

use PDO;

final class InventoryWriter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(string $type, int $qty, int $storeId, string $refType, int $refId, string $notes): void
    {
        $this->pdo->prepare(
            'INSERT INTO inventory_transactions (movement_type, qty_g, store_id, reference_type, reference_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$type, $qty, $storeId, $refType, $refId, $notes]);
    }
}
