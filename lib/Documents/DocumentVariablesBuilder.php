<?php

declare(strict_types=1);

namespace Ceara\Documents;

use PDO;

final class DocumentVariablesBuilder
{
    public function __construct(private PDO $pdo, private array $company)
    {
    }

    public function build(array $doc, array $store, string $documentLabel): array
    {
        $variables = [
            'document_number' => $documentLabel,
            'document_date' => date('Y-m-d', strtotime((string) ($doc['created_at'] ?? 'now'))),
            'company_name' => (string) ($this->company['company_name'] ?? ''),
            'company_vat_number' => (string) ($this->company['vat_number'] ?? ''),
            'company_registry_number' => (string) ($this->company['registry_number'] ?? ''),
            'company_address' => (string) ($this->company['address'] ?? ''),
            'store_name' => (string) ($store['name'] ?? ''),
            'store_code' => (string) ($store['code'] ?? ''),
            'store_address' => (string) ($store['address'] ?? ''),
            'operator_name' => '',
            'processor_name' => '',
            'processor_identifier' => '',
            'processor_address' => '',
            'customer_name' => '',
            'customer_identifier' => '',
            'customer_address' => '',
            'customer_phone' => '',
            'customer_type' => '',
            'lot_number' => '',
            'gross_wax_kg' => '',
            'package_count' => '',
            'wax_observations' => '',
            'wax_processed_kg' => '',
            'wax_returned_kg' => '',
            'shrinkage_pct' => '',
            'foundation_delivered_kg' => '',
            'service_value' => '',
            'nir_number' => $documentLabel,
            'nir_date' => date('Y-m-d', strtotime((string) ($doc['created_at'] ?? 'now'))),
            'aviz_number' => '',
            'aviz_date' => '',
            'adjustment_type' => '',
            'adjustment_label' => '',
            'foundation_qty_kg' => '',
            'foundation_qty_g' => '',
            'factory_batch_number' => '',
            'factory_wax_total_kg' => '',
            'factory_foundation_expected_kg' => '',
            'factory_items_rows' => '',
            'item_name' => 'Faguri ceara',
            'item_unit' => 'kg',
            'item_qty' => '',
            'item_unit_price' => '',
            'item_value' => '',
            'notes' => '',
            'app_name' => 'Ceara',
            'generated_at' => date('Y-m-d H:i'),
        ];

        if (!empty($doc['created_by'])) {
            $user = $this->find('users', (int) $doc['created_by']);
            if ($user) {
                $variables['operator_name'] = (string) ($user['full_name'] ?: $user['username']);
            }
        }

        $lotId = (int) ($doc['lot_id'] ?? 0);
        if ($lotId <= 0 && (string) $doc['reference_type'] === 'processing_lot') {
            $lotId = (int) $doc['reference_id'];
        }

        if ($lotId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT p.*, c.name AS customer_name, c.customer_type, c.phone, c.address, c.identifier, c.cui
                 FROM processing_lots p
                 JOIN customers c ON c.id = p.customer_id
                 WHERE p.id = ?'
            );
            $stmt->execute([$lotId]);
            $lot = $stmt->fetch();
            if ($lot) {
                $variables['customer_name'] = (string) $lot['customer_name'];
                $variables['customer_identifier'] = (string) ($lot['identifier'] ?: ($lot['cui'] ?? ''));
                $variables['customer_address'] = (string) ($lot['address'] ?? '');
                $variables['customer_phone'] = (string) ($lot['phone'] ?? '');
                $variables['customer_type'] = (string) ($lot['customer_type'] ?? '');
                $variables['lot_number'] = (string) $lot['lot_number'];
                $variables['gross_wax_kg'] = grams_to_kg((int) $lot['gross_g']);
                $variables['shrinkage_pct'] = rtrim(rtrim(number_format((float) $lot['shrinkage_pct'], 3, '.', ''), '0'), '.');
            }
        }

        if (!empty($store['processor_id'])) {
            $processor = $this->find('processors', (int) $store['processor_id']);
            if ($processor) {
                $variables['processor_name'] = (string) ($processor['name'] ?? '');
                $variables['processor_identifier'] = (string) ($processor['cui'] ?? '');
                $variables['processor_address'] = (string) ($processor['address'] ?? '');
            }
        }

        if ((string) $doc['reference_type'] === 'factory_buffer_adjustment') {
            $adjustment = $this->find('factory_buffer_adjustments', (int) $doc['reference_id']);
            if ($adjustment) {
                $qty = (int) $adjustment['qty_g'];
                $type = (string) $adjustment['adjustment_type'];
                $variables['aviz_number'] = (string) $adjustment['aviz_number'];
                $variables['aviz_date'] = date('Y-m-d', strtotime((string) ($adjustment['aviz_date'] ?? $adjustment['created_at'] ?? $doc['created_at'] ?? 'now')));
                $variables['adjustment_type'] = $type;
                $variables['adjustment_label'] = $type === 'minus' ? 'Iesire buffer faguri' : 'Intrare buffer faguri';
                $variables['foundation_qty_kg'] = grams_to_kg($qty);
                $variables['foundation_qty_g'] = (string) $qty;
                $variables['item_qty'] = grams_to_kg($qty);
                $variables['notes'] = nl2br(h((string) ($adjustment['notes'] ?? '')));
            }
        }

        $factoryBatchId = (int) ($doc['factory_batch_id'] ?? 0);
        if ($factoryBatchId <= 0 && (string) $doc['reference_type'] === 'factory_batch') {
            $factoryBatchId = (int) $doc['reference_id'];
        }

        if ($factoryBatchId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT b.*, p.name AS processor_name, p.cui AS processor_cui, p.address AS processor_address
                 FROM factory_batches b
                 JOIN processors p ON p.id = b.processor_id
                 WHERE b.id = ?'
            );
            $stmt->execute([$factoryBatchId]);
            $batch = $stmt->fetch();
            if ($batch) {
                $variables['factory_batch_number'] = (string) $batch['batch_number'];
                $variables['factory_wax_total_kg'] = grams_to_kg((int) $batch['wax_g']);
                $variables['factory_foundation_expected_kg'] = grams_to_kg((int) $batch['foundation_g']);
                $variables['processor_name'] = (string) ($batch['processor_name'] ?? '');
                $variables['processor_identifier'] = (string) ($batch['processor_cui'] ?? '');
                $variables['processor_address'] = (string) ($batch['processor_address'] ?? '');
                $variables['aviz_number'] = (string) ($batch['aviz_number'] ?? '');
                $variables['aviz_date'] = !empty($batch['aviz_date']) ? date('Y-m-d', strtotime((string) $batch['aviz_date'])) : '';
                $variables['foundation_qty_kg'] = grams_to_kg((int) $batch['foundation_g']);
                $variables['foundation_qty_g'] = (string) ((int) $batch['foundation_g']);
                $variables['item_qty'] = grams_to_kg((int) $batch['foundation_g']);
                $variables['factory_items_rows'] = $this->factoryBatchItemsRows($factoryBatchId);
            }
        }

        if (!empty($doc['movement_id'])) {
            $movement = $this->find('processing_lot_movements', (int) $doc['movement_id']);
            if ($movement) {
                if ($variables['operator_name'] === '' && !empty($movement['created_by'])) {
                    $movementUser = $this->find('users', (int) $movement['created_by']);
                    if ($movementUser) {
                        $variables['operator_name'] = (string) ($movementUser['full_name'] ?: $movementUser['username']);
                    }
                }
                $notes = nl2br(h((string) ($movement['notes'] ?? '')));
                $variables['wax_observations'] = $notes;
                $variables['notes'] = $notes;
                $variables['wax_processed_kg'] = grams_to_kg((int) ($movement['wax_g'] ?? 0));
                $variables['wax_returned_kg'] = grams_to_kg((int) ($movement['wax_g'] ?? 0));
                $variables['foundation_delivered_kg'] = grams_to_kg((int) ($movement['foundation_g'] ?? 0));
                $variables['service_value'] = money((int) ($movement['service_value_cents'] ?? 0));
            }
        }

        return $variables;
    }

    private function factoryBatchItemsRows(int $batchId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.wax_g, p.lot_number, c.name AS customer_name
             FROM factory_batch_items i
             JOIN processing_lots p ON p.id = i.processing_lot_id
             JOIN customers c ON c.id = p.customer_id
             WHERE i.batch_id = ?
             ORDER BY i.id ASC'
        );
        $stmt->execute([$batchId]);

        $rows = [];
        $index = 1;
        foreach ($stmt->fetchAll() as $item) {
            $rows[] = '<tr>'
                . '<td class="text-center">' . $index . '</td>'
                . '<td>' . h((string) $item['lot_number']) . '</td>'
                . '<td>' . h((string) $item['customer_name']) . '</td>'
                . '<td class="text-right">' . h(grams_to_kg((int) $item['wax_g'])) . '</td>'
                . '</tr>';
            $index++;
        }

        if (!$rows) {
            return '<tr><td colspan="4" class="text-center">Nu exista loturi pe acest aviz.</td></tr>';
        }

        return implode('', $rows);
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
