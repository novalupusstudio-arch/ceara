<?php

declare(strict_types=1);

namespace Ceara\Documents;

use PDO;
use RuntimeException;

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
        if ($type === 'FACT') {
            return $this->issueFgoInvoicePlaceholder(
                $referenceType,
                $referenceId,
                $storeId,
                $status,
                $notes,
                $links,
                $renderDocument
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_series WHERE store_id = ? AND document_type = ? FOR UPDATE'
        );
        $stmt->execute([$storeId, $type]);
        $series = $stmt->fetch();

        if (!$series) {
            throw new RuntimeException('Seria pentru documentul ' . $type . ' nu este configurata pe gestiune.');
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

    private function issueFgoInvoicePlaceholder(
        string $referenceType,
        int $referenceId,
        int $storeId,
        string $status,
        string $notes,
        array $links,
        callable $renderDocument
    ): int {
        $stmt = $this->pdo->prepare('SELECT fgo_series FROM stores WHERE id = ? LIMIT 1');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
        $series = strtoupper(trim((string) ($store['fgo_series'] ?? '')));
        if ($series === '') {
            throw new RuntimeException('Seria facturii FGO nu este configurata pe gestiune.');
        }

        $this->pdo->prepare(
            'INSERT INTO documents
            (document_type, series, number, store_id, lot_id, movement_id, factory_batch_id, reference_type, reference_id, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            'FACT',
            $series,
            0,
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

        $renderDocument($documentId);

        return $documentId;
    }
}
