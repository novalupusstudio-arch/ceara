<?php

declare(strict_types=1);

final class ProcessingService
{
    public function __construct(
        private PDO $pdo,
        private \Ceara\Inventory\InventoryService $inventory,
        private array $config = []
    )
    {
    }

    public function processingLots(int $userId): array
    {
        $store = $this->requireUserPrimaryStore($userId);
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS customer_name, c.customer_type, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             WHERE p.store_id = ?
             ORDER BY p.id DESC'
        );
        $stmt->execute([(int) $store['id']]);
        return $stmt->fetchAll();
    }

    public function processingLotSummaries(int $userId): array
    {
        $lots = $this->processingLots($userId);
        $summaries = [];
        foreach ($lots as $lot) {
            $summaries[] = $this->buildProcessingLotSummary($lot, $this->movementTotals([(int) $lot['id']])[(int) $lot['id']] ?? []);
        }

        return $summaries;
    }

    public function processingLotSummary(int $lotId, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS customer_name, c.customer_type, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             WHERE p.id = ? AND p.store_id = ?'
        );
        $stmt->execute([$lotId, $storeId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->buildProcessingLotSummary($row, $this->movementTotals([$lotId])[$lotId] ?? []);
    }

    public function factoryDeliveryData(int $userId, int $processorId = 0): array
    {
        $store = $this->requireUserPrimaryStore($userId);
        $processors = $this->pdo->query('SELECT * FROM processors ORDER BY name')->fetchAll();
        $selectedProcessor = null;

        if ($processorId > 0) {
            $selectedProcessor = $this->findProcessor($processorId);
        } elseif ((int) ($store['processor_id'] ?? 0) > 0) {
            $selectedProcessor = $this->findProcessor((int) $store['processor_id']);
        }

        if (!$selectedProcessor && count($processors) === 1) {
            $selectedProcessor = $processors[0];
        }

        $selectedProcessorId = (int) ($selectedProcessor['id'] ?? 0);
        $rows = [];
        $totals = ['wax_g' => 0, 'foundation_g' => 0, 'cost_cents' => 0];

        if ($selectedProcessorId > 0) {
            foreach ($this->processingLotSummaries($userId) as $summary) {
                $lot = $summary['lot'];
                if ((int) $lot['processor_id'] !== $selectedProcessorId || $summary['wax_custody_g'] <= 0) {
                    continue;
                }

                $remaining = (int) $summary['wax_custody_g'];
                $selected = $remaining;
                $foundation = max(0, (int) round($selected * (1 - (((float) $selectedProcessor['exchange_shrinkage_pct']) / 100))));
                $cost = (int) round(($foundation / 1000) * (int) $selectedProcessor['processing_price_cents']);

                $rows[] = [
                    'lot' => $lot,
                    'summary' => $summary,
                    'remaining_g' => $remaining,
                    'selected_g' => $selected,
                    'cost_cents' => $cost,
                    'foundation_g' => $foundation,
                ];

                $totals['wax_g'] += $selected;
                $totals['foundation_g'] += $foundation;
                $totals['cost_cents'] += $cost;
            }
        }

        return [
            'store' => $store,
            'processors' => $processors,
            'selected_processor' => $selectedProcessor,
            'lots' => $rows,
            'totals' => $totals,
        ];
    }

    public function processingLotStatuses(): array
    {
        return ['Procesare', 'Recuperare', 'Inchis'];
    }

    public function processingLotsBoard(array $lotSummaries, array $filters = []): array
    {
        $selectedStatuses = array_values(array_filter(
            array_map('trim', $filters),
            fn ($status) => in_array($status, $this->processingLotStatuses(), true)
        ));
        if (!$selectedStatuses) {
            $selectedStatuses = ['Procesare', 'Recuperare'];
        }

        $summaries = array_values(array_filter(
            $lotSummaries,
            fn (array $summary) => in_array($summary['calculated_status'], $selectedStatuses, true)
        ));

        return [
            'lots' => $summaries,
            'selected_statuses' => $selectedStatuses,
            'all_statuses' => $this->processingLotStatuses(),
        ];
    }

    public function factoryBufferData(int $userId): array
    {
        $store = $this->requireUserPrimaryStore($userId);
        $stmt = $this->pdo->prepare(
            'SELECT a.*, s.name AS store_name, u.username,
                    d.id AS document_id, d.series AS document_series, d.number AS document_number, d.document_type
             FROM factory_buffer_adjustments a
             JOIN stores s ON s.id = a.store_id
             JOIN users u ON u.id = a.created_by
             LEFT JOIN documents d ON d.reference_type = "factory_buffer_adjustment"
                AND d.reference_id = a.id
                AND d.document_type = CASE WHEN a.adjustment_type = "minus" THEN "AVIZ-RETUR" ELSE "NIR" END
             WHERE a.store_id = ?
             ORDER BY a.id DESC
             LIMIT 100'
        );
        $stmt->execute([(int) $store['id']]);

        return [
            'store' => $store,
            'current_stock_g' => $this->inventory->sumForStore('foundation_operational', (int) $store['id']),
            'today' => date('Y-m-d'),
            'adjustments' => $stmt->fetchAll(),
        ];
    }

    public function processingRegisterData(int $userId, string $dateStart = '', string $dateEnd = ''): array
    {
        $store = $this->userPrimaryStore($userId);
        if (!$store) {
            throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
        }

        $dateStart = $this->normalizeDate($dateStart) ?: date('Y-m-01');
        $dateEnd = $this->normalizeDate($dateEnd) ?: date('Y-m-d');
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        $periodStart = $dateStart . ' 00:00:00';
        $periodEnd = $dateEnd . ' 23:59:59';

        $rows = [];
        foreach ($this->inventory->processingRegisterRows((int) $store['id'], $periodStart, $periodEnd) as $row) {
            $rows[] = array_merge($row, $this->processingRegisterMeta($row['reference_type'], (int) $row['reference_id']));
        }

        return [
            'store' => $store,
            'wax_total_g' => $this->inventory->sumForStore('wax_custody', (int) $store['id']),
            'foundation_total_g' => $this->inventory->sumForStore('foundation_operational', (int) $store['id']),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'opening_wax_g' => $this->inventory->sumForStoreUntil('wax_custody', (int) $store['id'], $periodStart, false),
            'opening_foundation_g' => $this->inventory->sumForStoreUntil('foundation_operational', (int) $store['id'], $periodStart, false),
            'closing_wax_g' => $this->inventory->sumForStoreUntil('wax_custody', (int) $store['id'], $periodEnd, true),
            'closing_foundation_g' => $this->inventory->sumForStoreUntil('foundation_operational', (int) $store['id'], $periodEnd, true),
            'rows' => $rows,
        ];
    }

    public function processingLotDetail(int $lotId, int $userId, callable $summaryProvider): array
    {
        $store = $this->requireUserPrimaryStore($userId);
        $storeId = (int) $store['id'];
        $summary = $summaryProvider($lotId, $storeId);
        if (!$summary) {
            throw new RuntimeException('Lotul nu exista.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT m.*, u.username
             FROM processing_lot_movements m
             JOIN users u ON u.id = m.created_by
             WHERE m.lot_id = ?
             ORDER BY m.id ASC'
        );
        $stmt->execute([$lotId]);
        $movements = $stmt->fetchAll();

        return [
            'summary' => $summary,
            'movements' => $movements,
            'documents' => $this->documentsForLot($lotId),
            'foundation_stock_g' => $this->inventory->sumForStore('foundation_operational', $storeId),
        ];
    }

    public function processingFeeReceiptsReport(
        int $userId,
        string $dateStart = '',
        string $dateEnd = '',
        string $period = '',
        int $page = 1,
        int $perPage = 50
    ): array {
        $store = $this->requireUserPrimaryStore($userId);

        if ($period === 'previous_month') {
            $dateStart = date('Y-m-01', strtotime('first day of previous month'));
            $dateEnd = date('Y-m-t', strtotime('last day of previous month'));
        } else {
            $dateStart = $this->normalizeDate($dateStart) ?: date('Y-m-01');
            $dateEnd = $this->normalizeDate($dateEnd) ?: date('Y-m-d');
        }
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        $periodStart = $dateStart . ' 00:00:00';
        $periodEnd = $dateEnd . ' 23:59:59';
        $storeId = (int) $store['id'];
        $vatRate = max(0, (int) ($this->config['vat_rate'] ?? 21));
        $vatDivisor = 100 + $vatRate;

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT DATE(d.created_at))
             FROM (
                 SELECT movement_id, store_id, MIN(created_at) AS created_at
                 FROM documents
                 WHERE document_type = "BON" AND movement_id IS NOT NULL
                 GROUP BY movement_id, store_id
             ) d
             JOIN processing_lot_movements m ON m.id = d.movement_id
             JOIN processing_lots p ON p.id = m.lot_id
             WHERE d.store_id = ?
               AND m.movement_type = "EXCHANGE_WAX_WITH_CLIENT"
               AND m.service_value_cents > 0
               AND d.created_at BETWEEN ? AND ?'
        );
        $countStmt->execute([$storeId, $periodStart, $periodEnd]);
        $totalRows = (int) $countStmt->fetchColumn();
        $totalPages = $perPage > 0 ? max(1, (int) ceil($totalRows / $perPage)) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = $perPage > 0 ? ($page - 1) * $perPage : 0;

        $totalsStmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT m.id) AS receipt_count,
                    COALESCE(SUM(CASE WHEN d.is_card = 1 THEN 0 ELSE m.service_value_cents END), 0) AS cash_cents,
                    COALESCE(SUM(CASE WHEN d.is_card = 1 THEN m.service_value_cents ELSE 0 END), 0) AS card_cents,
                    COALESCE(SUM(ROUND(m.service_value_cents * 100 / ?)), 0) AS net_cents,
                    COALESCE(SUM(m.service_value_cents), 0) AS total_cents
             FROM (
                 SELECT movement_id, store_id, MIN(created_at) AS created_at,
                        MAX(CASE WHEN LOWER(notes) LIKE "%card%" THEN 1 ELSE 0 END) AS is_card
                 FROM documents
                 WHERE document_type = "BON" AND movement_id IS NOT NULL
                 GROUP BY movement_id, store_id
             ) d
             JOIN processing_lot_movements m ON m.id = d.movement_id
             JOIN processing_lots p ON p.id = m.lot_id
             WHERE d.store_id = ?
               AND m.movement_type = "EXCHANGE_WAX_WITH_CLIENT"
               AND m.service_value_cents > 0
               AND d.created_at BETWEEN ? AND ?'
        );
        $totalsStmt->execute([$vatDivisor, $storeId, $periodStart, $periodEnd]);
        $totals = $totalsStmt->fetch() ?: [];

        $rowsStmt = $this->pdo->prepare(
            'SELECT DATE(d.created_at) AS receipt_date,
                    COUNT(DISTINCT m.id) AS receipt_count,
                    SUM(CASE WHEN d.is_card = 1 THEN 0 ELSE m.service_value_cents END) AS cash_cents,
                    SUM(CASE WHEN d.is_card = 1 THEN m.service_value_cents ELSE 0 END) AS card_cents,
                    SUM(ROUND(m.service_value_cents * 100 / ?)) AS net_cents,
                    SUM(m.service_value_cents) AS total_cents
             FROM (
                 SELECT movement_id, store_id, MIN(created_at) AS created_at,
                        MAX(CASE WHEN LOWER(notes) LIKE "%card%" THEN 1 ELSE 0 END) AS is_card
                 FROM documents
                 WHERE document_type = "BON" AND movement_id IS NOT NULL
                 GROUP BY movement_id, store_id
             ) d
             JOIN processing_lot_movements m ON m.id = d.movement_id
             JOIN processing_lots p ON p.id = m.lot_id
             WHERE d.store_id = ?
               AND m.movement_type = "EXCHANGE_WAX_WITH_CLIENT"
               AND m.service_value_cents > 0
               AND d.created_at BETWEEN ? AND ?
             GROUP BY DATE(d.created_at)
             ORDER BY receipt_date DESC
             ' . ($perPage > 0 ? 'LIMIT ? OFFSET ?' : '')
        );
        $rowsStmt->bindValue(1, $vatDivisor, PDO::PARAM_INT);
        $rowsStmt->bindValue(2, $storeId, PDO::PARAM_INT);
        $rowsStmt->bindValue(3, $periodStart);
        $rowsStmt->bindValue(4, $periodEnd);
        if ($perPage > 0) {
            $rowsStmt->bindValue(5, $perPage, PDO::PARAM_INT);
            $rowsStmt->bindValue(6, $offset, PDO::PARAM_INT);
        }
        $rowsStmt->execute();

        $rows = array_map(static function (array $row): array {
            $row['vat_cents'] = (int) $row['total_cents'] - (int) $row['net_cents'];
            return $row;
        }, $rowsStmt->fetchAll());
        $netCents = (int) ($totals['net_cents'] ?? 0);
        $totalCents = (int) ($totals['total_cents'] ?? 0);

        return [
            'store' => $store,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'vat_rate' => $vatRate,
            'rows' => $rows,
            'totals' => [
                'receipt_count' => (int) ($totals['receipt_count'] ?? 0),
                'cash_cents' => (int) ($totals['cash_cents'] ?? 0),
                'card_cents' => (int) ($totals['card_cents'] ?? 0),
                'net_cents' => $netCents,
                'vat_cents' => $totalCents - $netCents,
                'total_cents' => $totalCents,
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => max(0, $perPage),
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
            ],
        ];
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
        $foundationAvailableForExchange = $this->foundationForWax($waxAvailableForExchange, (float) $lot['shrinkage_pct']);
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
            'foundation_available_for_exchange_g' => $foundationAvailableForExchange,
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

    private function documentsForLot(int $lotId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documents
             WHERE lot_id = ? OR (reference_type = ? AND reference_id = ?)
             ORDER BY id ASC'
        );
        $stmt->execute([$lotId, 'processing_lot', $lotId]);
        $documents = [];
        foreach ($stmt->fetchAll() as $document) {
            $key = ((int) ($document['movement_id'] ?? 0)) . ':' . $document['document_type'];
            $documents[$key][] = $document;
        }
        return $documents;
    }

    private function processingRegisterMeta(string $referenceType, int $referenceId): array
    {
        if ($referenceType === 'processing_lot') {
            $stmt = $this->pdo->prepare(
                'SELECT p.*, c.name AS customer_name, c.customer_type, u.username
                 FROM processing_lots p
                 JOIN customers c ON c.id = p.customer_id
                 JOIN users u ON u.id = p.created_by
                 WHERE p.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                $document = $this->findDocumentByLotAndType((int) $row['id'], 'PV-CUST');
                return [
                    'partner' => (string) $row['customer_name'],
                    'lot_number' => (string) $row['lot_number'],
                    'lot_url' => 'index.php?page=lot_detail&lot_id=' . (int) $row['id'],
                    'document' => $this->documentLink($document, 'PV-CUST'),
                    'operator' => (string) $row['username'],
                    'created_at' => $row['created_at'],
                ];
            }
        }

        if ($referenceType === 'processing_lot_movement') {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, p.lot_number, p.id AS lot_id, c.name AS customer_name, u.username
                 FROM processing_lot_movements m
                 JOIN processing_lots p ON p.id = m.lot_id
                 JOIN customers c ON c.id = p.customer_id
                 JOIN users u ON u.id = m.created_by
                 WHERE m.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                $documentType = match ((string) $row['movement_type']) {
                    'EXCHANGE_WAX_WITH_CLIENT' => 'PV-FAG',
                    'RETURN_WAX_TO_CLIENT' => 'PV-RET',
                    'RECEIVE_WAX_FROM_CLIENT' => 'PV-CUST',
                    'SEND_WAX_TO_FACTORY' => 'AVIZ',
                    'RECEIVE_FOUNDATION_FROM_FACTORY' => 'NIR',
                    default => '-',
                };
                $document = $documentType !== '-' ? $this->findDocumentByReference('processing_lot_movement', (int) $row['id'], $documentType) : null;
                return [
                    'partner' => (string) $row['customer_name'],
                    'lot_number' => (string) $row['lot_number'],
                    'lot_url' => 'index.php?page=lot_detail&lot_id=' . (int) $row['lot_id'],
                    'document' => $this->documentLink($document, $documentType),
                    'operator' => (string) $row['username'],
                    'created_at' => $row['created_at'],
                ];
            }
        }

        if ($referenceType === 'factory_batch') {
            $stmt = $this->pdo->prepare(
                'SELECT b.*, p.name AS processor_name, u.username
                 FROM factory_batches b
                 JOIN processors p ON p.id = b.processor_id
                 JOIN users u ON u.id = b.created_by
                 WHERE b.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                $nirDocument = $this->findDocumentByReference('factory_batch', (int) $row['id'], 'NIR');
                return [
                    'partner' => (string) $row['processor_name'],
                    'lot_number' => trim(
                        (string) ($row['aviz_number'] ?: $row['batch_number'])
                        . (!empty($row['processor_invoice_number']) ? ' / Factura ' . $row['processor_invoice_number'] : '')
                    ),
                    'lot_url' => '',
                    'document' => $this->documentLink($nirDocument, 'NIR'),
                    'operator' => (string) $row['username'],
                    'created_at' => $row['created_at'],
                ];
            }
        }

        if ($referenceType === 'factory_buffer_adjustment') {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, s.name AS store_name, u.username
                 FROM factory_buffer_adjustments a
                 JOIN stores s ON s.id = a.store_id
                 JOIN users u ON u.id = a.created_by
                 WHERE a.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                $documentType = $row['adjustment_type'] === 'minus' ? 'AVIZ-RETUR' : 'NIR';
                $document = $this->findDocumentByReference('factory_buffer_adjustment', (int) $row['id'], $documentType);
                $documentLink = $this->documentLink($document, $documentType);
                if ($documentType === 'AVIZ-RETUR') {
                    $documentLink['label'] = 'AVIZ';
                }
                return [
                    'partner' => (string) $row['store_name'],
                    'lot_number' => '',
                    'lot_url' => '',
                    'document' => $documentLink,
                    'operator' => (string) $row['username'],
                    'created_at' => $row['created_at'],
                ];
            }
        }

        return [
            'partner' => '-',
            'lot_number' => '',
            'lot_url' => '',
            'document' => ['label' => '-', 'url' => ''],
            'operator' => '-',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function documentLink(?array $document, string $fallbackLabel): array
    {
        if (!$document) {
            return ['label' => $fallbackLabel, 'url' => ''];
        }

        return [
            'label' => (string) ($document['document_type'] ?? $fallbackLabel),
            'url' => 'index.php?page=document_mock&document_id=' . (int) $document['id'],
        ];
    }

    private function findDocumentByReference(string $referenceType, int $referenceId, string $documentType): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type
             FROM documents
             WHERE reference_type = ? AND reference_id = ? AND document_type = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$referenceType, $referenceId, $documentType]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findDocumentByLotAndType(int $lotId, string $documentType): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type
             FROM documents
             WHERE lot_id = ? AND document_type = ?
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([$lotId, $documentType]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function userPrimaryStore(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.name AS processor_name
             FROM user_stores us
             JOIN stores s ON s.id = us.store_id
             LEFT JOIN processors p ON p.id = s.processor_id
             WHERE us.user_id = ?
             ORDER BY us.store_id
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $store = $stmt->fetch();

        return $store ?: null;
    }

    private function requireUserPrimaryStore(int $userId): array
    {
        $store = $this->userPrimaryStore($userId);
        if (!$store) {
            throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
        }

        return $store;
    }

    private function findProcessor(int $processorId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM processors WHERE id = ? LIMIT 1');
        $stmt->execute([$processorId]);
        $processor = $stmt->fetch();

        return $processor ?: null;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        $parts = array_map('intval', explode('-', $date));
        return checkdate($parts[1], $parts[2], $parts[0]) ? $date : '';
    }
}
