<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Integrations\FiscalWireExporter;
use PDO;
use RuntimeException;

final class FiscalWireService
{
    /**
     * @param callable(int):?array $documentById
     * @param callable(int):?array $findStore
     */
    public function __construct(
        private PDO $pdo,
        private array $config,
        private $documentById,
        private $findStore
    ) {
    }

    public function exportReceipt(int $documentId, int $lotId, int $movementId, string $paymentMethod, int $userId): void
    {
        $doc = ($this->documentById)($documentId);
        if (!$doc) {
            throw new RuntimeException('Documentul de bon nu exista.');
        }

        $exporter = new FiscalWireExporter($this->config['fiscalwire'] ?? []);
        if (!$exporter->enabled()) {
            throw new RuntimeException('Integrarea FiscalWire nu este activa.');
        }
        if ($movementId <= 0) {
            throw new RuntimeException('Bonul FiscalWire trebuie legat de o operatie de schimb ceara.');
        }

        $receiptData = $this->fiscalWireReceiptData($lotId, $movementId, $paymentMethod);
        $content = $exporter->buildReceipt($receiptData);
        $store = ($this->findStore)((int) $doc['store_id']);
        if (!$store) {
            throw new RuntimeException('Gestiunea pentru bon nu exista.');
        }

        $fileName = $this->safePathPart(($receiptData['lot_number'] ?? 'lot') . '_' . date('ymdHi')) . '.' . $exporter->extension();
        $relativePath = 'fiscalwire/' . $this->safePathPart((string) ($store['code'] ?? 'gestiune')) . '/' . $fileName;
        $absolutePath = $this->storagePath($relativePath);
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolutePath, $content);
        $exportPath = $exporter->writeReceipt($fileName, $content);

        $notes = 'Bon FiscalWire ' . ($receiptData['payment_method'] === 'card' ? 'card' : 'numerar') . ' exportat: ' . $exportPath;
        $this->pdo->prepare(
            'UPDATE documents SET file_path = ?, status = ?, notes = ?, created_by = COALESCE(created_by, ?) WHERE id = ?'
        )->execute([$relativePath, 'issued', $notes, $userId, $documentId]);
        $this->logAudit($userId, 'FISCALWIRE_RECEIPT_CREATE', 'documents', $documentId, null, $fileName);
    }

    private function fiscalWireReceiptData(int $lotId, int $movementId, string $paymentMethod): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, p.lot_number,
                    c.name AS customer_name, c.identifier, c.cui
             FROM processing_lot_movements m
             JOIN processing_lots p ON p.id = m.lot_id
             JOIN customers c ON c.id = p.customer_id
             WHERE m.id = ? AND p.id = ?'
        );
        $stmt->execute([$movementId, $lotId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Operatia de schimb pentru bon nu exista.');
        }
        if ((string) $row['movement_type'] !== 'EXCHANGE_WAX_WITH_CLIENT') {
            throw new RuntimeException('Bonul FiscalWire se emite doar pentru schimb ceara client.');
        }
        if ((int) $row['service_value_cents'] <= 0) {
            throw new RuntimeException('Valoarea manoperei este zero; bonul FiscalWire nu poate fi emis.');
        }

        return [
            'article_name' => (string) ($this->config['fiscalwire']['article_name'] ?? 'Servicii procesare'),
            'lot_number' => (string) $row['lot_number'],
            'client_name' => (string) $row['customer_name'],
            'client_identifier' => trim((string) ($row['identifier'] ?: ($row['cui'] ?? ''))),
            'amount_cents' => (int) $row['service_value_cents'],
            'payment_method' => $paymentMethod === 'card' ? 'card' : 'cash',
            'wax_kg' => grams_to_kg((int) $row['wax_g']),
        ];
    }

    private function safePathPart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
        return trim((string) $safe, '_') ?: 'document';
    }

    private function storagePath(string $relativePath): string
    {
        return dirname(__DIR__) . '/storage/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }
}
