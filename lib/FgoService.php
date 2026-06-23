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
        $invoiceData['payload']['Serie'] = (string) ($doc['series'] ?? '');
        $response = $fgo->emitInvoice($invoiceData['payload'], $invoiceData['client_name']);
        $invoice = is_array($response['Factura'] ?? null) ? $response['Factura'] : [];
        $link = (string) ($invoice['Link'] ?? $response['Link'] ?? '');
        if ($link === '') {
            throw new RuntimeException('FGO a emis raspuns fara link de factura.');
        }

        $serie = trim((string) ($invoice['Serie'] ?? ''));
        $number = (int) ($invoice['Numar'] ?? $invoice['NrFactura'] ?? 0);
        if ($serie === '' || $number <= 0) {
            throw new RuntimeException('FGO a raspuns fara seria sau numarul final al facturii.');
        }
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
        $company = ($this->companySettings)();
        $baseUrl = trim((string) ($company['fgo_url'] ?? ''));
        $token = trim((string) ($company['fgo_token'] ?? ''));
        $vatNumber = preg_replace('/\D+/', '', (string) ($company['vat_number'] ?? ''));

        if ($baseUrl === '') {
            throw new RuntimeException('URL-ul FGO nu este configurat in Date societate.');
        }
        if ($token === '') {
            throw new RuntimeException('Tokenul FGO nu este configurat in Date societate.');
        }
        if ($vatNumber === '') {
            throw new RuntimeException('CUI-ul societatii nu este configurat in Date societate.');
        }

        return [
            'enabled' => true,
            'base_url' => $baseUrl,
            'private_key' => $token,
            'cod_unic' => $vatNumber,
            'platforma_url' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        ];
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
            'Tara' => 'RO',
            'Judet' => (string) ($row['county_name'] ?: ''),
            'Localitate' => (string) ($row['locality_name'] ?: ''),
            'Adresa' => (string) ($row['address'] ?? ''),
            'Telefon' => (string) ($row['phone'] ?? ''),
        ];
        if ($clientIdentifier !== '') {
            $client['CodUnic'] = $clientIdentifier;
        }

        return [
            'payload' => [
                'DataEmitere' => date('Y-m-d'),
                'IdExtern' => 'ceara-movement-' . $movementId,
                'Serie' => (string) ($row['series'] ?? ''),
                'Client' => $client,
                'Continut' => [[
                    'Denumire' => 'Servicii procesare ceara - lot ' . $row['lot_number'],
                    'Descriere' => 'Cantitate ceara schimbata: ' . grams_to_kg((int) $row['wax_g']),
                    'NrProduse' => 1,
                    'UM' => 'BUC',
                    'CotaTVA' => 21,
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
