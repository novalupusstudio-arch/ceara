<?php

declare(strict_types=1);

namespace Ceara\Documents;

use PDO;

final class DocumentIssuer
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(
        string $type,
        string $referenceType,
        int $referenceId,
        int $storeId,
        string $status,
        string $notes,
        array $links,
        callable $renderDocument
    ): int {
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
            'INSERT INTO documents
            (document_type, series, number, store_id, lot_id, movement_id, factory_batch_id, reference_type, reference_id, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $type,
            $series['series'],
            $number,
            $storeId,
            $links['lot_id'] ?? null,
            $links['movement_id'] ?? null,
            $links['factory_batch_id'] ?? null,
            $referenceType,
            $referenceId,
            $status,
            $notes,
            $links['created_by'] ?? null,
        ]);
        $documentId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE document_series SET next_number = next_number + 1 WHERE id = ?')
            ->execute([$series['id']]);

        $renderDocument($documentId);

        return $documentId;
    }
}
