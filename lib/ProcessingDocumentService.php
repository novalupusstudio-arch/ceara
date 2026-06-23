<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Integrations\FgoClient;
use Ceara\Integrations\FiscalWireExporter;
use PDO;
use RuntimeException;
use Throwable;

final class ProcessingDocumentService
{
    /**
     * @param callable(int):?array $documentById
     * @param callable(int):int $issueDocument
     * @param callable(int):void $renderDocumentFile
     * @param callable(int):?array $findStore
     * @param callable():array $companySettings
     */
    public function __construct(
        private PDO $pdo,
        private array $config,
        private $documentById,
        private $issueDocument,
        private $renderDocumentFile,
        private $findStore,
        private $companySettings
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
            'SELECT id, file_path, external_url FROM documents WHERE reference_type = ? AND reference_id = ? AND document_type = ? LIMIT 1'
        );
        $stmt->execute([$referenceType, $referenceId, $documentType]);
        $existingDocument = $stmt->fetch();
        if ($existingDocument) {
            if ($documentType === 'FACT') {
                $this->ensureFgoInvoice((int) $existingDocument['id'], $lotId, $movementId, $userId);
            } elseif ($documentType === 'BON') {
                $this->ensureFiscalWireReceipt((int) $existingDocument['id'], $lotId, $movementId, $paymentMethod, $userId);
            } elseif (empty($existingDocument['file_path'])) {
                ($this->renderDocumentFile)((int) $existingDocument['id']);
            }
            return (int) $existingDocument['id'];
        }

        $documentId = ($this->issueDocument)(
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
            $this->ensureFgoInvoice($documentId, $lotId, $movementId, $userId);
        }
        if ($documentType === 'BON') {
            $this->ensureFiscalWireReceipt($documentId, $lotId, $movementId, $paymentMethod, $userId);
        }

        return $documentId;
    }

    private function ensureFgoInvoice(int $documentId, int $lotId, int $movementId, int $userId): void
    {
        $doc = ($this->documentById)($documentId);
        if (!$doc) {
            throw new RuntimeException('Documentul de factura nu exista.');
        }
        if (!empty($doc['external_url'])) {
            return;
        }

        $fgoConfig = $this->fgoConfig((int) $doc['store_id']);
        $fgo = new FgoClient($fgoConfig);
        if (!$fgo->enabled()) {
            throw new RuntimeException('Integrarea FGO nu este activa.');
        }
        if ($movementId <= 0) {
            throw new RuntimeException('Factura FGO trebuie legata de o operatie de schimb ceara.');
        }

        $invoiceData = $this->fgoInvoiceData($lotId, $movementId);
        $response = $fgo->emitInvoice($invoiceData['payload'], $invoiceData['client_name']);
        $invoice = is_array($response['Factura'] ?? null) ? $response['Factura'] : [];
        $link = (string) ($invoice['Link'] ?? $response['Link'] ?? '');
        if ($link === '') {
            throw new RuntimeException('FGO a emis raspuns fara link de factura.');
        }

        $serie = (string) ($invoice['Serie'] ?? $fgoConfig['serie'] ?? $doc['series']);
        $number = (int) ($invoice['Numar'] ?? $invoice['NrFactura'] ?? $doc['number']);
        $notes = 'Factura FGO emisa';
        if (!empty($invoice['LinkPlata'])) {
            $notes .= ' | Link plata: ' . $invoice['LinkPlata'];
        }

        $this->pdo->prepare(
            'UPDATE documents SET series = ?, number = ?, external_url = ?, status = ?, notes = ?, created_by = COALESCE(created_by, ?) WHERE id = ?'
        )->execute([$serie, $number, $link, 'issued', $notes, $userId, $documentId]);
        $this->logAudit($userId, 'FGO_INVOICE_CREATE', 'documents', $documentId, null, $serie . '-' . $number);
    }

    private function fgoConfig(?int $storeId = null): array
    {
        $config = $this->config['fgo'] ?? [];
        $company = ($this->companySettings)();
        $privateKey = trim((string) ($company['fgo_private_key'] ?? ''));
        if ($privateKey !== '') {
            $config['private_key'] = $privateKey;
        }

        return $config;
    }

    private function fgoInvoiceData(int $lotId, int $movementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, p.lot_number,
                    c.name AS customer_name, c.customer_type, c.phone, c.address, c.identifier, c.cui,
                    c.county_name, c.locality_name, c.postal_code
             FROM processing_lot_movements m
             JOIN processing_lots p ON p.id = m.lot_id
             JOIN customers c ON c.id = p.customer_id
             WHERE m.id = ? AND p.id = ?'
        );
        $stmt->execute([$movementId, $lotId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Operatia de schimb pentru factura nu exista.');
        }
        if ((string) $row['movement_type'] !== 'EXCHANGE_WAX_WITH_CLIENT') {
            throw new RuntimeException('Factura FGO se emite doar pentru schimb ceara client.');
        }

        $serviceValueCents = (int) $row['service_value_cents'];
        if ($serviceValueCents <= 0) {
            throw new RuntimeException('Valoarea manoperei este zero; factura FGO nu poate fi emisa.');
        }

        $clientName = trim((string) $row['customer_name']);
        $clientType = (string) $row['customer_type'] === 'PJ' ? 'PJ' : 'PF';
        $clientIdentifier = trim((string) ($row['identifier'] ?: ($row['cui'] ?? '')));
        $client = [
            'Denumire' => $clientName,
            'Tip' => $clientType,
            'Tara' => (string) ($this->config['fgo']['default_country'] ?? 'RO'),
            'Judet' => (string) ($row['county_name'] ?: ($this->config['fgo']['default_county'] ?? 'Bacau')),
            'Localitate' => (string) ($row['locality_name'] ?: ($this->config['fgo']['default_locality'] ?? 'Onesti')),
            'Adresa' => (string) ($row['address'] ?? ''),
            'Telefon' => (string) ($row['phone'] ?? ''),
        ];
        if ($clientIdentifier !== '') {
            $client['CodUnic'] = $clientIdentifier;
        }

        $articleName = (string) ($this->config['fgo']['article_name'] ?? 'Servicii procesare ceara');
        $payload = [
            'DataEmitere' => date('Y-m-d'),
            'IdExtern' => 'ceara-movement-' . $movementId,
            'Client' => $client,
            'Continut' => [[
                'Denumire' => $articleName . ' - lot ' . $row['lot_number'],
                'Descriere' => 'Cantitate ceara schimbata: ' . grams_to_kg((int) $row['wax_g']),
                'NrProduse' => 1,
                'UM' => (string) ($this->config['fgo']['article_um'] ?? 'BUC'),
                'CotaTVA' => (float) ($this->config['fgo']['vat_rate'] ?? 21),
                'PretTotal' => round($serviceValueCents / 100, 2),
            ]],
            'Explicatii' => 'Factura generata din aplicatia Ceara pentru lot ' . $row['lot_number'] . '.',
            'VerificareDuplicat' => true,
        ];

        return [
            'payload' => $payload,
            'client_name' => $clientName,
        ];
    }

    private function ensureFiscalWireReceipt(int $documentId, int $lotId, int $movementId, string $paymentMethod, int $userId): void
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
            'amount_cents' => (int) $row['service_value_cents'],
            'payment_method' => $paymentMethod === 'card' ? 'card' : 'cash',
            'lot_number' => (string) $row['lot_number'],
            'wax_kg' => grams_to_kg((int) $row['wax_g']),
            'client_identifier' => (string) ($row['identifier'] ?: ($row['cui'] ?? '')),
        ];
    }

    private function findProcessingLot(int $lotId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM processing_lots WHERE id = ?');
        $stmt->execute([$lotId]);

        $row = $stmt->fetch();
        return $row ?: null;
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
