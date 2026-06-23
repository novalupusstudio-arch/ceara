<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Inventory\InventoryService;
use PDO;
use RuntimeException;
use Throwable;

final class ProcessingWriteService
{
    /**
     * @param callable(int):void $documentRenderer
     */
    public function __construct(
        private PDO $pdo,
        private InventoryService $inventory,
        private CustomerService $customers,
        private DocumentService $documents,
        private $documentRenderer
    ) {
    }

    public function createProcessingLot(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $customer = $this->customers->resolveProcessingCustomer($data);
            $lotNumber = $this->nextLotNumber('PROC');
            $gross = kg_to_grams($data['gross_kg']);
            $this->processingProcessorData((int) $data['processor_id']);
            $shrinkage = (float) str_replace(',', '.', (string) ($data['shrinkage_pct'] ?? '0'));
            $processingPriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['processing_price'] ?? '0'))) * 100);
            if ($shrinkage <= 0) {
                throw new RuntimeException('Scazamantul lotului este obligatoriu.');
            }
            if ($processingPriceCents <= 0) {
                throw new RuntimeException('Pretul de procesare al lotului este obligatoriu.');
            }
            $foundation = max(0, (int) round($gross * (1 - ($shrinkage / 100))));
            $status = 'In Validare';

            $stmt = $this->pdo->prepare(
                'INSERT INTO processing_lots
                (lot_number, customer_id, status, gross_g, processing_price_cents, shrinkage_pct, foundation_g, store_id, processor_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $lotNumber,
                $customer['id'],
                $status,
                $gross,
                $processingPriceCents,
                $shrinkage,
                $foundation,
                $data['store_id'],
                $data['processor_id'] ?: null,
                $userId,
            ]);
            $lotId = (int) $this->pdo->lastInsertId();

            $this->inventory->record('wax_custody', $gross, (int) $data['store_id'], 'processing_lot', $lotId, 'Ceara client in custodie');
            $movementId = $this->processingMovement($lotId, 'RECEIVE_WAX_FROM_CLIENT', $gross, 0, 0, trim((string) ($data['notes'] ?? '')), $userId);
            $this->recordProcessingLotStatus($lotId, $status, $userId);
            $this->documents->issue('PV-CUST', 'processing_lot_movement', $movementId, (int) $data['store_id'], 'issued', 'PV primire ceara in custodie', [
                'lot_id' => $lotId,
                'movement_id' => $movementId,
                'created_by' => $userId,
            ]);
            $this->logAudit($userId, 'PROCESSING_CREATE', 'processing_lots', $lotId, null, $status);

            $this->pdo->commit();
            return $lotId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createProcessingExchange(int $lotId, string $waxKg, int $userId): int
    {
        $summary = $this->processingLotSummary($lotId);
        if (!$summary) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $wax = kg_to_grams($waxKg);
        if ($wax <= 0) {
            throw new RuntimeException('Cantitatea de schimb trebuie sa fie mai mare decat zero.');
        }
        if ($wax > $summary['wax_available_for_exchange_g']) {
            throw new RuntimeException('Cantitatea de schimb depaseste ceara neschimbata din lot.');
        }

        $foundation = $this->foundationForWax($wax, (float) $summary['lot']['shrinkage_pct']);
        $stock = $this->inventory->sum('foundation_operational');
        if ($foundation > $stock) {
            throw new RuntimeException('Stocul operational de faguri este insuficient pentru acest schimb.');
        }

        $serviceValue = (int) round(($wax / 1000) * (int) $summary['lot']['processing_price_cents']);

        $this->pdo->beginTransaction();
        try {
            $movementId = $this->processingMovement($lotId, 'EXCHANGE_WAX_WITH_CLIENT', $wax, $foundation, $serviceValue, '', $userId);
            $this->inventory->record('foundation_operational', -$foundation, (int) $summary['lot']['store_id'], 'processing_lot_movement', $movementId, 'Faguri predati client la schimb');
            $this->logAudit($userId, 'PROCESSING_EXCHANGE', 'processing_lot_movements', $movementId, null, (string) $lotId);
            $this->pdo->commit();
            return $movementId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createProcessingReturn(int $lotId, string $waxKg, string $notes, int $userId): int
    {
        $summary = $this->processingLotSummary($lotId);
        if (!$summary) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $wax = kg_to_grams($waxKg);
        if ($wax <= 0) {
            throw new RuntimeException('Cantitatea de retur trebuie sa fie mai mare decat zero.');
        }
        if ($wax > $summary['wax_custody_g']) {
            throw new RuntimeException('Cantitatea de retur depaseste ceara aflata in custodie.');
        }

        $this->pdo->beginTransaction();
        try {
            $movementId = $this->processingMovement($lotId, 'RETURN_WAX_TO_CLIENT', $wax, 0, 0, $notes, $userId);
            $this->inventory->record('wax_custody', -$wax, (int) $summary['lot']['store_id'], 'processing_lot_movement', $movementId, 'Ceara returnata client');
            $this->documents->issue('PV-RET', 'processing_lot_movement', $movementId, (int) $summary['lot']['store_id'], 'draft', 'PV retur ceara client', [
                'lot_id' => $lotId,
                'movement_id' => $movementId,
                'created_by' => $userId,
            ]);
            $this->logAudit($userId, 'PROCESSING_RETURN', 'processing_lot_movements', $movementId, null, (string) $lotId);
            $this->pdo->commit();
            return $movementId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createFactoryBufferAdjustment(array $data, int $userId): int
    {
        $type = $data['adjustment_type'] === 'minus' ? 'minus' : 'plus';
        $storeId = (int) ($data['store_id'] ?? 0);
        $avizNumber = trim((string) ($data['aviz_number'] ?? ''));
        $avizDate = $this->normalizeDate((string) ($data['aviz_date'] ?? '')) ?: date('Y-m-d');
        $receptionDate = $this->normalizeDate((string) ($data['reception_date'] ?? '')) ?: date('Y-m-d');
        $qty = kg_to_grams((string) ($data['qty_kg'] ?? '0'));
        $notes = trim((string) ($data['notes'] ?? ''));

        if (!$this->find('stores', $storeId)) {
            throw new RuntimeException('Gestiunea selectata nu exista.');
        }
        if ($avizNumber === '') {
            throw new RuntimeException('Numarul avizului este obligatoriu.');
        }
        if ($avizDate === '') {
            throw new RuntimeException('Data avizului este obligatorie.');
        }
        if ($receptionDate === '') {
            throw new RuntimeException('Data receptiei este obligatorie.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('Cantitatea trebuie sa fie mai mare decat zero.');
        }

        $signedQty = $type === 'plus' ? $qty : -$qty;
        if ($signedQty < 0 && abs($signedQty) > $this->inventory->sum('foundation_operational')) {
            throw new RuntimeException('Avizul de minus depaseste stocul operational de faguri disponibil.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO factory_buffer_adjustments
                (adjustment_type, aviz_number, aviz_date, reception_date, qty_g, store_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$type, $avizNumber, $avizDate, $receptionDate, $qty, $storeId, $notes, $userId]);
            $adjustmentId = (int) $this->pdo->lastInsertId();

            $this->inventory->record(
                'foundation_operational',
                $signedQty,
                $storeId,
                'factory_buffer_adjustment',
                $adjustmentId,
                'Aviz buffer fabrica ' . strtoupper($type) . ' ' . $avizNumber
            );
            $this->documents->issue('NIR', 'factory_buffer_adjustment', $adjustmentId, $storeId, 'issued', 'NIR buffer fabrica pentru aviz ' . $avizNumber, [
                'created_by' => $userId,
            ]);
            $this->logAudit($userId, 'FACTORY_BUFFER_' . strtoupper($type), 'factory_buffer_adjustments', $adjustmentId, null, $avizNumber);

            $this->pdo->commit();
            return $adjustmentId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function transitionProcessingLot(int $lotId, string $action, int $userId): void
    {
        $lot = $this->find('processing_lots', $lotId);
        if (!$lot) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $map = [
            'accept' => ['from' => 'In Validare', 'to' => 'Acceptat', 'docs' => ['FACT', 'BON']],
            'reject' => ['from' => 'In Validare', 'to' => 'Respins', 'docs' => []],
            'return' => ['from' => 'Respins', 'to' => 'Returnat', 'docs' => ['PV-RET']],
        ];

        if (!isset($map[$action]) || $lot['status'] !== $map[$action]['from']) {
            throw new RuntimeException('Tranzitie invalida pentru statusul curent.');
        }

        $this->pdo->beginTransaction();
        try {
            $newStatus = $map[$action]['to'];
            $this->pdo->prepare('UPDATE processing_lots SET status = ? WHERE id = ?')->execute([$newStatus, $lotId]);
            $this->recordProcessingLotStatus($lotId, $newStatus, $userId);
            foreach ($map[$action]['docs'] as $type) {
                $this->documents->issue($type, 'processing_lot', $lotId, (int) $lot['store_id'], 'mock', 'Document generat mock');
            }
            $this->logAudit($userId, 'PROCESSING_' . strtoupper($action), 'processing_lots', $lotId, $lot['status'], $newStatus);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createFactoryBatch(array $data, int $userId): int
    {
        $processorId = (int) ($data['processor_id'] ?? 0);
        $avizNumber = trim((string) ($data['aviz_number'] ?? ''));
        $avizDate = $this->normalizeDate((string) ($data['aviz_date'] ?? '')) ?: date('Y-m-d');
        $processor = $this->find('processors', $processorId);
        if (!$processor) {
            throw new RuntimeException('Procesatorul selectat nu exista.');
        }
        if ($avizNumber === '') {
            throw new RuntimeException('Numarul avizului este obligatoriu.');
        }

        $lotsInput = $data['lot_qty'] ?? [];
        $rejectInput = $data['reject_qty'] ?? [];
        if ((!is_array($lotsInput) || !$lotsInput) && (!is_array($rejectInput) || !$rejectInput)) {
            throw new RuntimeException('Alege cel putin un lot pentru predare sau respingere.');
        }

        $this->pdo->beginTransaction();
        try {
            $selectedLots = [];
            $totalWax = 0;
            $totalFoundation = 0;
            $totalCostCents = 0;

            $lotIds = array_unique(array_merge(array_keys((array) $lotsInput), array_keys((array) $rejectInput)));
            foreach ($lotIds as $lotIdRaw) {
                $lotId = (int) $lotIdRaw;
                $qty = kg_to_grams((string) ($lotsInput[$lotIdRaw] ?? 0));
                $rejectQty = kg_to_grams((string) ($rejectInput[$lotIdRaw] ?? 0));
                if ($qty <= 0 && $rejectQty <= 0) {
                    continue;
                }

                $lot = $this->find('processing_lots', $lotId);
                if (!$lot) {
                    throw new RuntimeException('Un lot selectat nu mai exista.');
                }
                if ((int) $lot['processor_id'] !== $processorId) {
                    throw new RuntimeException('Toate loturile trebuie sa apartina procesatorului selectat.');
                }
                $summary = $this->processingLotSummary($lotId);
                if (!$summary) {
                    throw new RuntimeException('Un lot selectat nu mai exista.');
                }
                $remaining = (int) $summary['wax_custody_g'];
                if ($qty + $rejectQty > $remaining) {
                    throw new RuntimeException('Suma dintre predare si respingere depaseste ceara in custodie pentru un lot.');
                }

                $foundationQty = max(0, (int) round($qty * (1 - (((float) $processor['exchange_shrinkage_pct']) / 100))));
                $selectedLots[] = [
                    'lot' => $lot,
                    'qty' => $qty,
                    'reject_qty' => $rejectQty,
                    'foundation_qty' => $foundationQty,
                ];
                $totalWax += $qty;
                $totalFoundation += $foundationQty;
                $totalCostCents += (int) round(($qty / 1000) * (int) $processor['processing_price_cents']);
            }

            if (!$selectedLots) {
                throw new RuntimeException('Nu exista cantitati valide pentru predare sau respingere.');
            }

            $batchNumber = $this->nextLotNumber('FAB');
            $stmt = $this->pdo->prepare(
                'INSERT INTO factory_batches (batch_number, processor_id, store_id, aviz_number, aviz_date, wax_g, foundation_g, processing_cost_cents, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $batchNumber,
                $processorId,
                (int) $selectedLots[0]['lot']['store_id'],
                $avizNumber,
                $avizDate,
                $totalWax,
                $totalFoundation,
                $totalCostCents,
                $userId,
            ]);
            $batchId = (int) $this->pdo->lastInsertId();

            foreach ($selectedLots as $item) {
                $lot = $item['lot'];
                $qty = $item['qty'];
                $rejectQty = $item['reject_qty'];
                $foundationQty = $item['foundation_qty'];
                if ($qty > 0) {
                    $this->pdo->prepare(
                        'INSERT INTO factory_batch_items (batch_id, processing_lot_id, wax_g) VALUES (?, ?, ?)'
                    )->execute([$batchId, (int) $lot['id'], $qty]);

                    $newSent = (int) $lot['factory_sent_g'] + $qty;
                    $this->pdo->prepare(
                        'UPDATE processing_lots SET factory_sent_g = ? WHERE id = ?'
                    )->execute([$newSent, (int) $lot['id']]);
                    $this->processingMovement((int) $lot['id'], 'SEND_WAX_TO_FACTORY', $qty, 0, 0, 'Predare batch ' . $batchNumber, $userId);
                    $this->processingMovement((int) $lot['id'], 'RECEIVE_FOUNDATION_FROM_FACTORY', 0, $foundationQty, 0, 'Receptie automata batch ' . $batchNumber, $userId);
                }
                if ($rejectQty > 0) {
                    $this->processingMovement((int) $lot['id'], 'FACTORY_REJECT_WAX', $rejectQty, 0, 0, 'Respingere fabrica batch ' . $batchNumber, $userId);
                }
            }

            if ($totalWax > 0) {
                $this->inventory->record('wax_custody', -$totalWax, (int) $selectedLots[0]['lot']['store_id'], 'factory_batch', $batchId, 'Ceara trimisa la procesator');
                $this->inventory->record('foundation_operational', $totalFoundation, (int) $selectedLots[0]['lot']['store_id'], 'factory_batch', $batchId, 'Faguri primiti de la procesator');
                $this->documents->issue('AVIZ', 'factory_batch', $batchId, (int) $selectedLots[0]['lot']['store_id'], 'issued', 'Aviz catre procesator', [
                    'factory_batch_id' => $batchId,
                    'created_by' => $userId,
                ]);
                $this->documents->issue('NIR', 'factory_batch', $batchId, (int) $selectedLots[0]['lot']['store_id'], 'issued', 'NIR pentru aviz ' . $avizNumber, [
                    'factory_batch_id' => $batchId,
                    'created_by' => $userId,
                ]);
            }
            $this->logAudit($userId, 'FACTORY_BATCH_CREATE', 'factory_batches', $batchId, null, $batchNumber);

            $this->pdo->commit();
            return $batchId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function processingLotSummary(int $lotId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS customer_name, c.customer_type, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             WHERE p.id = ?'
        );
        $stmt->execute([$lotId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $totals = $this->movementTotals([$lotId])[$lotId] ?? [];
        return $this->buildProcessingLotSummary($row, $totals);
    }

    private function movementTotals(array $lotIds): array
    {
        if (!$lotIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT lot_id, movement_type,
                    COALESCE(SUM(wax_g), 0) AS wax_g,
                    COALESCE(SUM(foundation_g), 0) AS foundation_g,
                    COALESCE(SUM(service_value_cents), 0) AS service_value_cents
             FROM processing_lot_movements
             WHERE lot_id IN ($placeholders)
             GROUP BY lot_id, movement_type"
        );
        $stmt->execute($lotIds);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals[(int) $row['lot_id']][$row['movement_type']] = [
                'wax_g' => (int) $row['wax_g'],
                'foundation_g' => (int) $row['foundation_g'],
                'service_value_cents' => (int) $row['service_value_cents'],
            ];
        }

        return $totals;
    }

    private function buildProcessingLotSummary(array $lot, array $totals): array
    {
        $wax = static fn (string $type): int => (int) ($totals[$type]['wax_g'] ?? 0);
        $foundation = static fn (string $type): int => (int) ($totals[$type]['foundation_g'] ?? 0);

        $received = $wax('RECEIVE_WAX_FROM_CLIENT');
        $exchanged = $wax('EXCHANGE_WAX_WITH_CLIENT');
        $sentFactory = $wax('SEND_WAX_TO_FACTORY');
        $returned = $wax('RETURN_WAX_TO_CLIENT');
        $rejected = $wax('FACTORY_REJECT_WAX');
        $lossWax = $wax('RECORD_LOSS');
        $foundationDelivered = $foundation('EXCHANGE_WAX_WITH_CLIENT');
        $foundationReceived = $foundation('RECEIVE_FOUNDATION_FROM_FACTORY');
        $foundationRecovered = $foundation('RECOVER_FOUNDATION_FROM_CLIENT');
        $lossFoundation = $foundation('RECORD_LOSS');

        $waxCustody = max(0, $received - $sentFactory - $returned - $lossWax);
        $waxAvailableForExchange = max(0, $received - $exchanged - $returned - $lossWax);
        $waxToFactory = max(0, $exchanged - $sentFactory - $rejected);
        $openRejectedWax = max(0, $rejected - $returned - $lossWax);
        $exchangedWaxNotSentFactory = max(0, $exchanged - $sentFactory);
        $rejectedWaxWithFoundationDelivered = min($openRejectedWax, $exchangedWaxNotSentFactory);
        $foundationToRecover = max(0, $this->foundationForWax($rejectedWaxWithFoundationDelivered, (float) $lot['shrinkage_pct']) - $foundationRecovered - $lossFoundation);
        $foundationExpectedForClient = $this->foundationForWax($exchanged, (float) $lot['shrinkage_pct']);
        $foundationToClient = max(0, $foundationExpectedForClient - $foundationDelivered);

        $status = 'Procesare';
        if ($foundationToRecover > 0) {
            $status = 'Recuperare';
        } elseif ($waxCustody === 0 && $waxToFactory === 0 && $foundationToClient === 0 && $foundationToRecover === 0 && $openRejectedWax === 0) {
            $status = 'Inchis';
        }

        return [
            'lot' => $lot,
            'total_received_g' => $received,
            'wax_custody_g' => $waxCustody,
            'wax_available_for_exchange_g' => $waxAvailableForExchange,
            'wax_exchanged_g' => $exchanged,
            'foundation_delivered_g' => $foundationDelivered,
            'wax_to_factory_g' => $waxToFactory,
            'wax_sent_factory_g' => $sentFactory,
            'foundation_received_factory_g' => $foundationReceived,
            'wax_rejected_factory_g' => $rejected,
            'wax_returned_client_g' => $returned,
            'foundation_to_recover_g' => $foundationToRecover,
            'foundation_to_client_g' => $foundationToClient,
            'loss_g' => $lossWax + $lossFoundation,
            'calculated_status' => $status,
        ];
    }

    private function foundationForWax(int $wax, float $shrinkagePct): int
    {
        return max(0, (int) round($wax * (1 - ($shrinkagePct / 100))));
    }

    private function processingMovement(int $lotId, string $type, int $wax, int $foundation, int $serviceValue, string $notes, int $userId): int
    {
        $this->pdo->prepare(
            'INSERT INTO processing_lot_movements
            (lot_id, movement_type, wax_g, foundation_g, service_value_cents, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$lotId, $type, $wax, $foundation, $serviceValue, $notes, $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function processingProcessorData(int $processorId): void
    {
        if ($processorId <= 0) {
            throw new RuntimeException('Procesatorul lotului este obligatoriu.');
        }

        $processor = $this->find('processors', $processorId);
        if (!$processor) {
            throw new RuntimeException('Procesatorul selectat nu exista.');
        }
    }

    private function recordProcessingLotStatus(int $lotId, string $status, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO processing_lot_status_events (lot_id, status, created_by) VALUES (?, ?, ?)'
        )->execute([$lotId, $status, $userId]);
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed instanceof \DateTime && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        $parts = array_map('intval', explode('-', $date));
        return checkdate($parts[1], $parts[2], $parts[0]) ? $date : '';
    }

    private function nextLotNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
