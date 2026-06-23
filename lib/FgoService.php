<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Integrations\FgoClient;
use PDO;
use RuntimeException;

final class FgoService
{
    /**
     * @param callable(int):?array $documentById
     * @param callable():array $companySettings
     */
    public function __construct(
        private PDO $pdo,
        private array $config,
        private $documentById,
        private $companySettings
    ) {
    }

    public function emitInvoice(int $documentId, int $lotId, int $movementId, int $userId): void
    {
        $doc = ($this->documentById)($documentId);
        if (!$doc) {
            throw new RuntimeException('Documentul de factura nu exista.');
        }
        if (!empty($doc['external_url'])) {
            return;
        }
        if ($movementId <= 0) {
            throw new RuntimeException('Factura FGO trebuie legata de o operatie de schimb ceara.');
        }

        $fgoConfig = $this->fgoConfig();
        $fgo = new FgoClient($fgoConfig);
        if (!$fgo->enabled()) {
            throw new RuntimeException('Integrarea FGO nu este activa.');
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

    private function fgoConfig(): array
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
        return [
            'payload' => [
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
            ],
            'client_name' => $clientName,
        ];
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }
}
