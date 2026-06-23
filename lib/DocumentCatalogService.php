<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Documents\DocumentFiles;
use Ceara\Documents\TemplateRenderer;
use PDO;

final class DocumentCatalogService
{
    /**
     * @param callable(int):void $renderDocumentFile
     */
    public function __construct(
        private PDO $pdo,
        private DocumentFiles $files,
        private $renderDocumentFile
    ) {
    }

    public function documentById(int $documentId): ?array
    {
        return $this->files->findById($documentId);
    }

    public function documentPdfById(int $documentId): ?string
    {
        return $this->files->pdfById($documentId, $this->renderDocumentFile);
    }

    public function documentPreviewByTemplateId(int $templateId): ?string
    {
        $template = $this->find('document_templates', $templateId);
        if (!$template) {
            return null;
        }

        $renderer = new TemplateRenderer();
        return $renderer->wrapDocument($renderer->render((string) $template['body_html'], $this->sampleTemplateVariables()));
    }

    public function documents(): array
    {
        $documents = $this->pdo->query(
            'SELECT d.*, st.name AS store_name
             FROM documents d
             LEFT JOIN stores st ON st.id = d.store_id
             ORDER BY d.id DESC LIMIT 80'
        )->fetchAll();

        foreach ($documents as &$document) {
            $document['label'] = $this->formatDocumentLabel($document);
            $document['url'] = 'index.php?page=document_mock&document_id=' . (int) $document['id'];
        }
        unset($document);

        return $documents;
    }

    private function formatDocumentLabel(array $document): string
    {
        $series = trim((string) ($document['series'] ?? ''));
        $number = trim((string) ($document['number'] ?? ''));
        $type = trim((string) ($document['document_type'] ?? ''));
        $label = trim($series . '-' . $number, '-');
        if ($label === '') {
            $label = $type ?: 'Document';
        }

        return $label;
    }

    private function sampleTemplateVariables(): array
    {
        return [
            'document_number' => 'PV-CUST-GEST1-1',
            'document_date' => date('Y-m-d'),
            'company_name' => 'Stuparul',
            'company_vat_number' => 'RO00000000',
            'company_registry_number' => 'J00/000/2026',
            'company_address' => 'Adresa societate',
            'store_name' => 'Gestiune principala',
            'store_code' => 'GEST1',
            'store_address' => 'Adresa gestiune',
            'operator_name' => 'Administrator',
            'processor_name' => 'Procesator Exemplu',
            'processor_identifier' => 'RO12345678',
            'processor_address' => 'Adresa procesator',
            'customer_name' => 'Client Exemplu',
            'customer_identifier' => 'CNP/CUI exemplu',
            'customer_address' => 'Localitate exemplu',
            'customer_phone' => '0700000000',
            'customer_type' => 'PF',
            'lot_number' => 'PROC-20260616-ABC123',
            'gross_wax_kg' => '10.000',
            'package_count' => '1',
            'wax_observations' => 'Ceara bruta receptionata pentru procesare.',
            'wax_processed_kg' => '10.000',
            'wax_returned_kg' => '4.000',
            'shrinkage_pct' => '2',
            'foundation_delivered_kg' => '9.800',
            'service_value' => '30.00 lei',
            'nir_number' => 'NIR-GEST1-1',
            'nir_date' => date('Y-m-d'),
            'aviz_number' => 'AVZ-123',
            'aviz_date' => date('Y-m-d'),
            'adjustment_type' => 'plus',
            'adjustment_label' => 'Intrare buffer faguri',
            'foundation_qty_kg' => '25,000 kg',
            'foundation_qty_g' => '25000',
            'factory_batch_number' => 'FAB-20260617-ABC123',
            'factory_wax_total_kg' => '36,000 kg',
            'factory_foundation_expected_kg' => '35,280 kg',
            'factory_items_rows' => '<tr><td class="text-center">1</td><td>PROC-20260617-ABC123</td><td>Client Exemplu</td><td class="text-right">36,000 kg</td></tr>',
            'item_name' => 'Faguri ceara',
            'item_unit' => 'kg',
            'item_qty' => '25,000 kg',
            'item_unit_price' => '',
            'item_value' => '',
            'notes' => 'Receptie faguri conform aviz fabrica.',
            'app_name' => 'Ceara',
            'generated_at' => date('Y-m-d H:i'),
        ];
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
