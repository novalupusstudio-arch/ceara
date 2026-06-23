<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;

final class ProcessingDocumentService
{
    /**
     * @param callable(int):?array $documentById
     * @param callable(int):void $renderDocumentFile
     */
    public function __construct(
        private PDO $pdo,
        private DocumentService $documents,
        private FgoService $fgo,
        private FiscalWireService $fiscalWire,
        private $renderDocumentFile
    ) {
    }

    public function ensureProcessingDocument(int $lotId, string $documentType, int $userId, int $movementId = 0, string $paymentMethod = 'cash'): int
    {
        $lot = $this->findProcessingLot($lotId);
        if (!$lot) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $referenceType = $movementId > 0 ? 'processing_lot_movement' : 'processing_lot';
        $referenceId = $movementId > 0 ? $movementId : $lotId;
        $stmt = $this->pdo->prepare(
            'SELECT id, file_path, external_url, notes FROM documents WHERE reference_type = ? AND reference_id = ? AND document_type = ? LIMIT 1'
        );
        $stmt->execute([$referenceType, $referenceId, $documentType]);
        $existingDocument = $stmt->fetch();
        if ($existingDocument) {
            if ($documentType === 'FACT') {
                $this->fgo->emitInvoice((int) $existingDocument['id'], $lotId, $movementId, $userId);
            } elseif ($documentType === 'BON') {
                if (!empty($existingDocument['file_path'])) {
                    $issuedPaymentMethod = $this->receiptPaymentMethod($existingDocument);
                    $requestedPaymentMethod = $paymentMethod === 'card' ? 'card' : 'cash';
                    if ($issuedPaymentMethod !== '' && $issuedPaymentMethod !== $requestedPaymentMethod) {
                        throw new RuntimeException('Bonul a fost deja emis pe ' . ($issuedPaymentMethod === 'card' ? 'card' : 'numerar') . '.');
                    }
                } else {
                    $this->fiscalWire->exportReceipt((int) $existingDocument['id'], $lotId, $movementId, $paymentMethod, $userId);
                }
            } elseif (empty($existingDocument['file_path'])) {
                ($this->renderDocumentFile)((int) $existingDocument['id']);
            }
            return (int) $existingDocument['id'];
        }

        $documentId = $this->documents->issue(
            $documentType,
            $referenceType,
            $referenceId,
            (int) $lot['store_id'],
            'issued',
            'Document generat manual',
            [
                'lot_id' => $lotId,
                'movement_id' => $movementId > 0 ? $movementId : null,
                'created_by' => $userId,
            ]
        );
        $this->logAudit($userId, 'PROCESSING_DOCUMENT', 'documents', $lotId, null, $documentType);

        if ($documentType === 'FACT') {
            $this->fgo->emitInvoice($documentId, $lotId, $movementId, $userId);
        }
        if ($documentType === 'BON') {
            $this->fiscalWire->exportReceipt($documentId, $lotId, $movementId, $paymentMethod, $userId);
        }

        return $documentId;
    }

    private function findProcessingLot(int $lotId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM processing_lots WHERE id = ?');
        $stmt->execute([$lotId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function receiptPaymentMethod(array $document): string
    {
        $notes = strtolower((string) ($document['notes'] ?? ''));
        if (str_contains($notes, ' bon fiscalwire card ') || str_contains($notes, 'bon fiscalwire card')) {
            return 'card';
        }
        if (str_contains($notes, ' bon fiscalwire numerar ') || str_contains($notes, 'bon fiscalwire numerar')) {
            return 'cash';
        }

        return '';
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }
}
