<?php

final class App
{
    private const ROLES = ['admin', 'operator'];

    public function __construct(public PDO $pdo, private array $config = [])
    {
    }

    public function stores(): array
    {
        return $this->pdo->query(
            'SELECT s.*, p.name AS processor_name
             FROM stores s
             LEFT JOIN processors p ON p.id = s.processor_id
             ORDER BY s.name'
        )->fetchAll();
    }

    public function processors(): array
    {
        return $this->pdo->query('SELECT * FROM processors ORDER BY id')->fetchAll();
    }

    public function sirutaCounties(): array
    {
        return $this->pdo->query(
            'SELECT county_code, name
             FROM siruta_counties
             ORDER BY name'
        )->fetchAll();
    }

    public function sirutaLocalities(string $countyCode, string $term = ''): array
    {
        $countyCode = trim($countyCode);
        if ($countyCode === '') {
            return [];
        }

        $term = trim($term);
        if ($term !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT siruta_code, county_code, name, display_name, postal_code, parent_name, parent_type
                 FROM siruta_localities
                 WHERE county_code = ?
                    AND (normalized_name LIKE ? OR display_name LIKE ?)
                 ORDER BY display_name
                 LIMIT 120'
            );
            $stmt->execute([$countyCode, '%' . $this->normalizeLocationName($term) . '%', '%' . $term . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            'SELECT siruta_code, county_code, name, display_name, postal_code, parent_name, parent_type
             FROM siruta_localities
             WHERE county_code = ?
             ORDER BY display_name
             LIMIT 400'
        );
        $stmt->execute([$countyCode]);
        return $stmt->fetchAll();
    }

    public function lookupAnafCompany(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', strtoupper(trim($cui))) ?? '';
        if ($cui === '') {
            throw new RuntimeException('CUI-ul este obligatoriu pentru preluarea datelor ANAF.');
        }

        $url = 'https://demoanaf.ro/api/company/' . rawurlencode($cui);
        $context = stream_context_create([
            'http' => [
                'timeout' => 12,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('Nu am putut contacta serviciul ANAF.');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['success']) || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Serviciul ANAF nu a returnat date valide pentru CUI-ul cautat.');
        }

        $data = $payload['data'];
        $hq = is_array($data['headquartersAddress'] ?? null) ? $data['headquartersAddress'] : [];
        $countyName = (string) ($hq['county'] ?? '');
        $localityRaw = (string) ($hq['locality'] ?? '');
        $match = $this->matchSirutaLocation($countyName, $localityRaw, (string) ($hq['postalCode'] ?? $data['postalCode'] ?? ''));

        $address = $this->cleanCompanyAddress(
            (string) ($data['address'] ?? ''),
            $match['county_name'] ?: $countyName,
            $match['locality_name'] ?: $localityRaw,
            (string) ($hq['postalCode'] ?? $data['postalCode'] ?? '')
        );

        return [
            'customer_type' => 'PJ',
            'name' => (string) ($data['name'] ?? ''),
            'identifier' => $cui,
            'cui' => $cui,
            'phone' => '',
            'representative' => '',
            'address' => $address,
            'registry_number' => (string) ($data['registrationNumber'] ?? ''),
            'legal_form' => (string) ($data['legalForm'] ?? ''),
            'vat_status' => (string) ($data['vatStatus'] ?? ''),
            'postal_code' => (string) ($hq['postalCode'] ?? $data['postalCode'] ?? ''),
            'county_name' => $match['county_name'] ?: $countyName,
            'county_code' => $match['county_code'],
            'locality_name' => $match['locality_name'] ?: $localityRaw,
            'locality_siruta' => $match['locality_siruta'],
            'locality_display_name' => $match['locality_display_name'],
            'external_source' => 'anaf',
            'external_checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function userPrimaryStore(int $userId): ?array
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

    public function defaultProcessor(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM processors ORDER BY id LIMIT 1');
        $processor = $stmt->fetch();
        return $processor ?: null;
    }

    public function defaultProcessorForUser(int $userId): ?array
    {
        $store = $this->userPrimaryStore($userId);
        $processorId = (int) ($store['processor_id'] ?? 0);
        if ($processorId > 0) {
            $processor = $this->find('processors', $processorId);
            if ($processor) {
                $processor['processing_price_cents'] = (int) ($store['processing_price_cents'] ?? $processor['processing_price_cents']);
                $processor['exchange_shrinkage_pct'] = (float) ($store['processing_shrinkage_pct'] ?? $processor['exchange_shrinkage_pct']);
                $processor['purchase_shrinkage_pct'] = (float) ($store['purchase_shrinkage_pct'] ?? $processor['purchase_shrinkage_pct']);
                return $processor;
            }
        }

        return $this->defaultProcessor();
    }

    public function dashboard(): array
    {
        $summaries = $this->processingLotSummaries();
        $openLots = 0;
        $recoveryLots = 0;
        $waxCustody = 0;
        $foundationOperational = $this->sumInventory('foundation_operational');
        foreach ($summaries as $summary) {
            if ($summary['calculated_status'] !== 'Inchis') {
                $openLots++;
            }
            if ($summary['calculated_status'] === 'Recuperare') {
                $recoveryLots++;
            }
            $waxCustody += $summary['wax_custody_g'];
        }

        return [
            'foundation_operational_g' => $foundationOperational,
            'wax_custody_g' => $waxCustody,
            'pending_lots' => $openLots,
            'rejected_lots' => $recoveryLots,
            'wax_owned_g' => $this->sumInventory('wax_owned') + $this->sumInventory('wax_purchased'),
            'foundation_merchandise_g' => $this->sumInventory('foundation_merchandise'),
        ];
    }

    public function processingLots(): array
    {
        return $this->pdo->query(
            'SELECT p.*, c.name AS customer_name, c.customer_type, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             ORDER BY p.id DESC'
        )->fetchAll();
    }

    public function factoryDeliveryData(int $processorId): array
    {
        $processors = $this->processors();
        $selectedProcessor = null;
        foreach ($processors as $processor) {
            if ((int) $processor['id'] === $processorId) {
                $selectedProcessor = $processor;
                break;
            }
        }
        if (!$selectedProcessor) {
            $selectedProcessor = $processors[0] ?? null;
        }

        $selectedProcessorId = $selectedProcessor ? (int) $selectedProcessor['id'] : 0;

        $rows = [];
        $totals = [
            'wax_g' => 0,
            'foundation_g' => 0,
            'cost_cents' => 0,
        ];

        foreach ($this->processingLotSummaries() as $summary) {
            $lot = $summary['lot'];
            if ((int) $lot['processor_id'] !== $selectedProcessorId || $summary['wax_custody_g'] <= 0) {
                continue;
            }

            $remaining = (int) $summary['wax_custody_g'];
            $selected = $remaining;
            $cost = (int) round(($selected / 1000) * (int) $selectedProcessor['processing_price_cents']);
            $foundation = max(0, (int) round($selected * (1 - (((float) $selectedProcessor['exchange_shrinkage_pct']) / 100))));

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

        return [
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

    public function processingLotsBoard(array $filters = []): array
    {
        $selectedStatuses = array_values(array_filter(
            array_map('trim', $filters),
            fn ($status) => in_array($status, $this->processingLotStatuses(), true)
        ));
        if (!$selectedStatuses) {
            $selectedStatuses = ['Procesare', 'Recuperare'];
        }

        $summaries = array_values(array_filter(
            $this->processingLotSummaries(),
            fn (array $summary) => in_array($summary['calculated_status'], $selectedStatuses, true)
        ));

        return [
            'lots' => $summaries,
            'selected_statuses' => $selectedStatuses,
            'all_statuses' => $this->processingLotStatuses(),
        ];
    }

    public function factoryBufferData(): array
    {
        return [
            'stores' => $this->stores(),
            'current_stock_g' => $this->sumInventory('foundation_operational'),
            'adjustments' => $this->pdo->query(
                'SELECT a.*, s.name AS store_name, u.username,
                        d.id AS nir_document_id, d.series AS nir_series, d.number AS nir_number, d.document_type AS nir_type
                 FROM factory_buffer_adjustments a
                 JOIN stores s ON s.id = a.store_id
                 JOIN users u ON u.id = a.created_by
                 LEFT JOIN documents d ON d.reference_type = "factory_buffer_adjustment"
                    AND d.reference_id = a.id
                    AND d.document_type = "NIR"
                 ORDER BY a.id DESC
                 LIMIT 100'
            )->fetchAll(),
        ];
    }

    public function documentById(int $documentId): ?array
    {
        return $this->documentFiles()->findById($documentId);
    }

    public function documentPreviewByTemplateId(int $templateId): ?string
    {
        $template = $this->find('document_templates', $templateId);
        if (!$template) {
            return null;
        }

        return $this->wrapDocumentHtml($this->renderTemplate((string) $template['body_html'], $this->sampleTemplateVariables()));
    }

    public function documentPdfById(int $documentId): ?string
    {
        return $this->documentFiles()->pdfById($documentId, fn (int $id) => $this->renderDocumentFile($id));
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

        $stmt = $this->pdo->prepare(
            "SELECT reference_type,
                    reference_id,
                    MAX(created_at) AS created_at,
                    COALESCE(SUM(CASE WHEN movement_type = 'wax_custody' THEN qty_g ELSE 0 END), 0) AS wax_g,
                    COALESCE(SUM(CASE WHEN movement_type = 'foundation_operational' THEN qty_g ELSE 0 END), 0) AS foundation_g
             FROM inventory_transactions
             WHERE store_id = ?
               AND movement_type IN ('wax_custody', 'foundation_operational')
               AND reference_type IN ('processing_lot', 'processing_lot_movement', 'factory_batch', 'factory_buffer_adjustment')
             GROUP BY reference_type, reference_id
             HAVING created_at BETWEEN ? AND ?
             ORDER BY created_at DESC, reference_id DESC"
        );
        $stmt->execute([(int) $store['id'], $periodStart, $periodEnd]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = array_merge($row, $this->processingRegisterMeta($row['reference_type'], (int) $row['reference_id']));
        }

        return [
            'store' => $store,
            'wax_total_g' => $this->sumInventoryForStore('wax_custody', (int) $store['id']),
            'foundation_total_g' => $this->sumInventoryForStore('foundation_operational', (int) $store['id']),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'opening_wax_g' => $this->sumInventoryForStoreUntil('wax_custody', (int) $store['id'], $periodStart, false),
            'opening_foundation_g' => $this->sumInventoryForStoreUntil('foundation_operational', (int) $store['id'], $periodStart, false),
            'closing_wax_g' => $this->sumInventoryForStoreUntil('wax_custody', (int) $store['id'], $periodEnd, true),
            'closing_foundation_g' => $this->sumInventoryForStoreUntil('foundation_operational', (int) $store['id'], $periodEnd, true),
            'rows' => $rows,
        ];
    }

    public function processingLotDetail(int $lotId): array
    {
        $summary = $this->processingLotSummary($lotId);
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

        $documents = $this->documentsForLot($lotId);

        return [
            'summary' => $summary,
            'movements' => $movements,
            'documents' => $documents,
            'foundation_stock_g' => $this->sumInventory('foundation_operational'),
        ];
    }

    public function purchaseLots(): array
    {
        return $this->pdo->query(
            'SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone, s.identifier AS supplier_identifier,
                    s.cui AS supplier_cui, s.locality_name AS supplier_locality, st.name AS store_name
             FROM purchase_lots p
             JOIN suppliers s ON s.id = p.supplier_id
             JOIN stores st ON st.id = p.store_id
             ORDER BY p.id DESC'
        )->fetchAll();
    }

    public function purchaseRegisterData(int $userId, string $dateStart = '', string $dateEnd = ''): array
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

        $stmt = $this->pdo->prepare(
            "SELECT i.*, u.username
             FROM inventory_transactions i
             LEFT JOIN users u ON u.id = (
                CASE
                    WHEN i.reference_type = 'purchase_lot' THEN (SELECT created_by FROM purchase_lots WHERE id = i.reference_id)
                    WHEN i.reference_type = 'purchase_wax_exit' THEN (SELECT created_by FROM purchase_wax_exits WHERE id = i.reference_id)
                    ELSE NULL
                END
             )
             WHERE i.store_id = ?
               AND i.movement_type = 'wax_purchased'
               AND i.created_at BETWEEN ? AND ?
             ORDER BY i.created_at DESC, i.id DESC"
        );
        $stmt->execute([(int) $store['id'], $periodStart, $periodEnd]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = array_merge($row, $this->purchaseRegisterMeta($row['reference_type'], (int) $row['reference_id']));
        }

        return [
            'store' => $store,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'stock_g' => $this->sumInventoryForStore('wax_purchased', (int) $store['id']),
            'opening_g' => $this->sumInventoryForStoreUntil('wax_purchased', (int) $store['id'], $periodStart, false),
            'closing_g' => $this->sumInventoryForStoreUntil('wax_purchased', (int) $store['id'], $periodEnd, true),
            'rows' => $rows,
            'lots' => $this->purchaseLots(),
        ];
    }

    public function purchaseExitData(int $userId): array
    {
        $store = $this->userPrimaryStore($userId);
        if (!$store) {
            throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
        }
        return [
            'store' => $store,
            'stock_g' => $this->sumInventoryForStore('wax_purchased', (int) $store['id']),
            'exits' => $this->purchaseWaxExits(),
        ];
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

    public function audit(): array
    {
        return $this->pdo->query(
            'SELECT a.*, u.username
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 100'
        )->fetchAll();
    }

    public function createProcessingLot(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $customer = $this->resolveProcessingCustomer($data);
            $lotNumber = $this->nextLotNumber('PROC');
            $gross = kg_to_grams($data['gross_kg']);
            $processor = $this->processingProcessorData((int) $data['processor_id'], (int) $data['store_id']);
            $shrinkage = (float) str_replace(',', '.', (string) ($data['shrinkage_pct'] ?? $processor['exchange_shrinkage_pct']));
            $processingPriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['processing_price'] ?? '0'))) * 100);
            if ($processingPriceCents <= 0) {
                $processingPriceCents = (int) $processor['processing_price_cents'];
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

            $this->inventory('wax_custody', $gross, (int) $data['store_id'], 'processing_lot', $lotId, 'Ceara client in custodie');
            $movementId = $this->processingMovement($lotId, 'RECEIVE_WAX_FROM_CLIENT', $gross, 0, 0, trim((string) ($data['notes'] ?? '')), $userId);
            $this->recordProcessingLotStatus($lotId, $status, $userId);
            $this->document('PV-CUST', 'processing_lot_movement', $movementId, (int) $data['store_id'], 'issued', 'PV primire ceara in custodie', [
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
        $stock = $this->sumInventory('foundation_operational');
        if ($foundation > $stock) {
            throw new RuntimeException('Stocul operational de faguri este insuficient pentru acest schimb.');
        }

        $serviceValue = (int) round(($wax / 1000) * (int) $summary['lot']['processing_price_cents']);

        $this->pdo->beginTransaction();
        try {
            $movementId = $this->processingMovement($lotId, 'EXCHANGE_WAX_WITH_CLIENT', $wax, $foundation, $serviceValue, '', $userId);
            $this->inventory('foundation_operational', -$foundation, (int) $summary['lot']['store_id'], 'processing_lot_movement', $movementId, 'Faguri predati client la schimb');
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
            $this->inventory('wax_custody', -$wax, (int) $summary['lot']['store_id'], 'processing_lot_movement', $movementId, 'Ceara returnata client');
            $this->document('PV-RET', 'processing_lot_movement', $movementId, (int) $summary['lot']['store_id'], 'draft', 'PV retur ceara client', [
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
        $qty = kg_to_grams((string) ($data['qty_kg'] ?? '0'));
        $notes = trim((string) ($data['notes'] ?? ''));

        if (!$this->find('stores', $storeId)) {
            throw new RuntimeException('Gestiunea selectata nu exista.');
        }
        if ($avizNumber === '') {
            throw new RuntimeException('Numarul avizului este obligatoriu.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('Cantitatea trebuie sa fie mai mare decat zero.');
        }

        $signedQty = $type === 'plus' ? $qty : -$qty;
        if ($signedQty < 0 && abs($signedQty) > $this->sumInventory('foundation_operational')) {
            throw new RuntimeException('Avizul de minus depaseste stocul operational de faguri disponibil.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO factory_buffer_adjustments
                (adjustment_type, aviz_number, qty_g, store_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$type, $avizNumber, $qty, $storeId, $notes, $userId]);
            $adjustmentId = (int) $this->pdo->lastInsertId();

            $this->inventory(
                'foundation_operational',
                $signedQty,
                $storeId,
                'factory_buffer_adjustment',
                $adjustmentId,
                'Aviz buffer fabrica ' . strtoupper($type) . ' ' . $avizNumber
            );
            $this->document('NIR', 'factory_buffer_adjustment', $adjustmentId, $storeId, 'issued', 'NIR buffer fabrica pentru aviz ' . $avizNumber, [
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
                $this->document($type, 'processing_lot', $lotId, (int) $lot['store_id'], 'mock', 'Document generat mock');
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
        $processor = $this->find('processors', $processorId);
        if (!$processor) {
            throw new RuntimeException('Procesatorul selectat nu exista.');
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
                'INSERT INTO factory_batches (batch_number, processor_id, store_id, wax_g, foundation_g, processing_cost_cents, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $batchNumber,
                $processorId,
                (int) $selectedLots[0]['lot']['store_id'],
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
                $this->inventory('wax_custody', -$totalWax, (int) $selectedLots[0]['lot']['store_id'], 'factory_batch', $batchId, 'Ceara trimisa la procesator');
                $this->inventory('foundation_operational', $totalFoundation, (int) $selectedLots[0]['lot']['store_id'], 'factory_batch', $batchId, 'Faguri primiti de la procesator');
                $this->document('AVIZ', 'factory_batch', $batchId, (int) $selectedLots[0]['lot']['store_id'], 'issued', 'Aviz catre procesator', [
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

    public function ensureProcessingDocument(int $lotId, string $documentType, int $userId, int $movementId = 0, string $paymentMethod = 'cash'): int
    {
        $lot = $this->find('processing_lots', $lotId);
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
                $this->renderDocumentFile((int) $existingDocument['id']);
            }
            return (int) $existingDocument['id'];
        }

        $this->pdo->beginTransaction();
        try {
            $documentId = $this->document($documentType, $referenceType, $referenceId, (int) $lot['store_id'], 'issued', 'Document generat manual', [
                'lot_id' => $lotId,
                'movement_id' => $movementId > 0 ? $movementId : null,
                'created_by' => $userId,
            ]);
            $this->logAudit($userId, 'PROCESSING_DOCUMENT', 'documents', $lotId, null, $documentType);
            if ($documentType === 'FACT') {
                $this->ensureFgoInvoice($documentId, $lotId, $movementId, $userId);
            }
            if ($documentType === 'BON') {
                $this->ensureFiscalWireReceipt($documentId, $lotId, $movementId, $paymentMethod, $userId);
            }
            $this->pdo->commit();
            return $documentId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function ensureFgoInvoice(int $documentId, int $lotId, int $movementId, int $userId): void
    {
        $doc = $this->documentById($documentId);
        if (!$doc) {
            throw new RuntimeException('Documentul de factura nu exista.');
        }
        if (!empty($doc['external_url'])) {
            return;
        }

        $fgoConfig = $this->fgoConfig((int) $doc['store_id']);
        $fgo = new \Ceara\Integrations\FgoClient($fgoConfig);
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
        $company = $this->companySettings();
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
        $doc = $this->documentById($documentId);
        if (!$doc) {
            throw new RuntimeException('Documentul de bon nu exista.');
        }

        $exporter = new \Ceara\Integrations\FiscalWireExporter($this->config['fiscalwire'] ?? []);
        if (!$exporter->enabled()) {
            throw new RuntimeException('Integrarea FiscalWire nu este activa.');
        }
        if ($movementId <= 0) {
            throw new RuntimeException('Bonul FiscalWire trebuie legat de o operatie de schimb ceara.');
        }

        $receiptData = $this->fiscalWireReceiptData($lotId, $movementId, $paymentMethod);
        $content = $exporter->buildReceipt($receiptData);
        $store = $this->find('stores', (int) $doc['store_id']);
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

    public function createPurchaseLot(array $data, int $userId): int
    {
        $supplierType = $this->normalizeSupplierType((string) ($data['supplier_type'] ?? 'PF'));
        $gross = kg_to_grams((string) ($data['gross_kg'] ?? '0'));
        $shrinkage = (float) str_replace(',', '.', (string) ($data['shrinkage_pct'] ?? '0'));
        $priceCents = (int) round(((float) str_replace(',', '.', (string) ($data['purchase_price'] ?? '0'))) * 100);
        $net = max(0, (int) round($gross * (1 - ($shrinkage / 100))));
        $total = (int) round(($gross / 1000) * $priceCents);
        $document = $this->purchaseDocumentData($supplierType, $data);

        if ($gross <= 0) {
            throw new RuntimeException('Cantitatea de ceara trebuie sa fie mai mare decat zero.');
        }
        if ($priceCents < 0) {
            throw new RuntimeException('Pretul de achizitie nu poate fi negativ.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->assertUniquePurchaseDocument($document);
            $supplierId = $this->upsertSupplier($this->purchaseSupplierPayload($supplierType, $data));
            $lotNumber = $this->nextLotNumber('ACH');

            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_lots
                (lot_number, supplier_id, supplier_type, status, purchase_date, external_document_type,
                 external_document_series, external_document_number, external_document_date, borderou_position,
                 gross_g, shrinkage_pct, net_g, purchase_price_cents_per_kg, total_amount_cents, foundation_g, store_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $stmt->execute([
                $lotNumber,
                $supplierId,
                $supplierType,
                'In stoc',
                $this->normalizeDate((string) ($data['purchase_date'] ?? '')) ?: date('Y-m-d'),
                $document['type'],
                $document['series'],
                $document['number'],
                $document['date'] ?: null,
                $document['position'],
                $gross,
                $shrinkage,
                $net,
                $priceCents,
                $total,
                $data['store_id'],
                $userId,
            ]);
            $lotId = (int) $this->pdo->lastInsertId();

            $this->inventory('wax_purchased', $gross, (int) $data['store_id'], 'purchase_lot', $lotId, 'Ceara cumparata');
            $this->logAudit($userId, 'PURCHASE_CREATE', 'purchase_lots', $lotId, null, 'Achizitie');

            $this->pdo->commit();
            return $lotId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function advancePurchaseLot(int $lotId, int $userId): void
    {
        $lot = $this->find('purchase_lots', $lotId);
        if (!$lot) {
            throw new RuntimeException('Achizitia nu exista.');
        }

        $next = [
            'Achizitie' => 'Predat Procesator',
            'Predat Procesator' => 'Receptionat Faguri',
            'Receptionat Faguri' => 'Inchis',
        ];

        if (!isset($next[$lot['status']])) {
            throw new RuntimeException('Achizitia este deja inchisa.');
        }

        $this->pdo->beginTransaction();
        try {
            $newStatus = $next[$lot['status']];
            $this->pdo->prepare('UPDATE purchase_lots SET status = ? WHERE id = ?')->execute([$newStatus, $lotId]);
            if ($newStatus === 'Predat Procesator') {
                $this->document('AVIZ', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'Aviz procesator');
            }
            if ($newStatus === 'Receptionat Faguri') {
                $this->document('NIR', 'purchase_lot', $lotId, (int) $lot['store_id'], 'mock', 'NIR produse finite');
                $this->inventory('foundation_merchandise', (int) $lot['foundation_g'], (int) $lot['store_id'], 'purchase_lot', $lotId, 'Faguri marfa receptionati');
            }
            $this->logAudit($userId, 'PURCHASE_ADVANCE', 'purchase_lots', $lotId, $lot['status'], $newStatus);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveSettings(array $data, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ?, processor_id = ? WHERE id = ?')
                ->execute([$data['store_code'], $data['store_name'], $data['store_address'], ($data['store_processor_id'] ?? 0) ?: null, $data['store_id']]);
            $this->pdo->prepare(
                'UPDATE processors SET name = ?, cui = ?, contact = ?, processing_price_cents = ?, exchange_shrinkage_pct = ?, purchase_shrinkage_pct = ? WHERE id = ?'
            )->execute([
                $data['processor_name'],
                $data['processor_cui'],
                $data['processor_contact'],
                (int) round(((float) str_replace(',', '.', $data['processing_price'])) * 100),
                (float) str_replace(',', '.', $data['exchange_shrinkage_pct']),
                (float) str_replace(',', '.', $data['purchase_shrinkage_pct']),
                $data['processor_id'],
            ]);

            foreach ($data['series'] as $id => $series) {
                $this->pdo->prepare('UPDATE document_series SET series = ?, next_number = ? WHERE id = ?')
                    ->execute([trim($series['series']), max(1, (int) $series['next_number']), (int) $id]);
            }

            $this->logAudit($userId, 'SETTINGS_UPDATE', 'settings', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function settings(): array
    {
        return [
            'stores' => $this->stores(),
            'processors' => $this->processors(),
            'permissions' => $this->permissions(),
            'role_permissions' => $this->rolePermissions(),
            'users' => $this->users(),
            'user_stores' => $this->userStores(),
            'document_templates' => $this->documentTemplates(),
            'company_settings' => $this->companySettings(),
            'series' => $this->pdo->query(
                'SELECT ds.*, s.name AS store_name FROM document_series ds JOIN stores s ON s.id = ds.store_id ORDER BY s.name, ds.document_type'
            )->fetchAll(),
        ];
    }

    public function changeOwnPassword(int $userId, string $newPassword, string $confirmPassword): void
    {
        if ($newPassword === '' || strlen($newPassword) < 4) {
            throw new RuntimeException('Parola noua trebuie sa aiba minimum 4 caractere.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Confirmarea parolei nu se potriveste.');
        }

        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        $this->logAudit($userId, 'PASSWORD_CHANGE', 'users', $userId, null, 'changed');
    }

    public function saveRolePermissions(array $matrix, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($this->permissions() as $permission) {
                foreach (self::ROLES as $role) {
                    $allowed = isset($matrix[$role][$permission['code']]) ? 1 : 0;
                    $this->pdo->prepare(
                        'INSERT INTO role_permissions (role_name, permission_code, allowed)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
                    )->execute([$role, $permission['code'], $allowed]);
                }
            }
            $this->logAudit($userId, 'ROLE_PERMISSIONS_UPDATE', 'role_permissions', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createUser(array $data, int $userId): void
    {
        $username = trim($data['username']);
        $password = (string) $data['password'];
        $role = in_array($data['role'], self::ROLES, true) ? $data['role'] : 'operator';

        if ($username === '' || $password === '') {
            throw new RuntimeException('Utilizatorul si parola sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, active) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                trim($data['full_name']) ?: $username,
                $role,
                isset($data['active']) ? 1 : 0,
            ]);
            $newUserId = (int) $this->pdo->lastInsertId();

            foreach (($data['store_ids'] ?? []) as $storeId) {
                $this->pdo->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)')
                    ->execute([$newUserId, (int) $storeId]);
            }

            $this->logAudit($userId, 'USER_CREATE', 'users', $newUserId, null, $username);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveStore(array $data, int $userId): void
    {
        $id = (int) ($data['id'] ?? 0);
        $code = strtoupper(trim((string) $data['code']));
        $name = trim($data['name']);
        $address = trim($data['address']);
        $processorId = (int) ($data['processor_id'] ?? 0);
        $fgoSeries = strtoupper(trim((string) ($data['fgo_series'] ?? '')));
        if ($fgoSeries === '' && $code !== '') {
            $fgoSeries = $this->defaultDocumentSeries('FACT', $code);
        }
        $processingShrinkage = (float) str_replace(',', '.', (string) ($data['processing_shrinkage_pct'] ?? '0'));
        $processingPriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['processing_price'] ?? '0'))) * 100);
        $purchaseShrinkage = (float) str_replace(',', '.', (string) ($data['purchase_shrinkage_pct'] ?? '0'));
        $purchasePriceCents = (int) round(((float) str_replace(',', '.', (string) ($data['purchase_price'] ?? '0'))) * 100);

        if ($code === '' || $name === '') {
            throw new RuntimeException('Codul si denumirea gestiunii sunt obligatorii.');
        }
        if ($processorId <= 0 || !$this->find('processors', $processorId)) {
            throw new RuntimeException('Alege procesatorul asignat gestiunii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare('UPDATE stores SET code = ?, name = ?, address = ?, fgo_series = ?, processing_shrinkage_pct = ?, processing_price_cents = ?, purchase_shrinkage_pct = ?, purchase_price_cents_per_kg = ?, processor_id = ? WHERE id = ?')
                    ->execute([$code, $name, $address, $fgoSeries, $processingShrinkage, $processingPriceCents, $purchaseShrinkage, $purchasePriceCents, $processorId, $id]);
                $storeId = $id;
                $operation = 'STORE_UPDATE';
            } else {
                $this->pdo->prepare('INSERT INTO stores (code, name, address, fgo_series, processing_shrinkage_pct, processing_price_cents, purchase_shrinkage_pct, purchase_price_cents_per_kg, processor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$code, $name, $address, $fgoSeries, $processingShrinkage, $processingPriceCents, $purchaseShrinkage, $purchasePriceCents, $processorId]);
                $storeId = (int) $this->pdo->lastInsertId();
                $operation = 'STORE_CREATE';

                foreach (['PV-CUST', 'FACT', 'BON', 'PV-FAG', 'PV-RET', 'AVIZ', 'NIR', 'BORD'] as $type) {
                    $this->pdo->prepare(
                        'INSERT IGNORE INTO document_series (store_id, document_type, series, next_number) VALUES (?, ?, ?, 1)'
                    )->execute([$storeId, $type, $this->defaultDocumentSeries($type, $code)]);
                }
            }

            $this->logAudit($userId, $operation, 'stores', $storeId, null, $code);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
    public function saveProcessor(array $data, int $userId): void
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name']);
        $cui = trim($data['cui']);
        $address = trim($data['address']);
        $processingPrice = (int) round(((float) str_replace(',', '.', $data['processing_price'])) * 100);
        $exchangeShrinkage = (float) str_replace(',', '.', $data['exchange_shrinkage_pct']);
        $purchaseShrinkage = (float) str_replace(',', '.', $data['purchase_shrinkage_pct']);

        if ($name === '' || $cui === '' || $address === '') {
            throw new RuntimeException('Numele, CUI si adresa procesatorului sunt obligatorii.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $this->pdo->prepare(
                    'UPDATE processors
                     SET name = ?, cui = ?, address = ?, processing_price_cents = ?, exchange_shrinkage_pct = ?, purchase_shrinkage_pct = ?
                     WHERE id = ?'
                )->execute([$name, $cui, $address, $processingPrice, $exchangeShrinkage, $purchaseShrinkage, $id]);
                $processorId = $id;
                $operation = 'PROCESSOR_UPDATE';
            } else {
                $this->pdo->prepare(
                    'INSERT INTO processors (name, cui, address, contact, processing_price_cents, exchange_shrinkage_pct, purchase_shrinkage_pct)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $cui, $address, '', $processingPrice, $exchangeShrinkage, $purchaseShrinkage]);
                $processorId = (int) $this->pdo->lastInsertId();
                $operation = 'PROCESSOR_CREATE';
            }

            $this->logAudit($userId, $operation, 'processors', $processorId, null, $name);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createPurchaseWaxExit(array $data, int $userId): int
    {
        $storeId = (int) ($data['store_id'] ?? 0);
        $qty = kg_to_grams((string) ($data['qty_kg'] ?? '0'));
        $stock = $this->sumInventoryForStore('wax_purchased', $storeId);
        $partnerName = trim((string) ($data['partner_name'] ?? ''));
        $documentNumber = trim((string) ($data['document_number'] ?? ''));

        if ($qty <= 0) {
            throw new RuntimeException('Cantitatea de iesire trebuie sa fie mai mare decat zero.');
        }
        if ($qty > $stock) {
            throw new RuntimeException('Cantitatea de iesire depaseste stocul de ceara achizitionata.');
        }
        if ($partnerName === '') {
            throw new RuntimeException('Partenerul/fabrica este obligatoriu.');
        }
        if ($documentNumber === '') {
            throw new RuntimeException('Numarul documentului este obligatoriu.');
        }

        $this->pdo->beginTransaction();
        try {
            $exitNumber = $this->nextLotNumber('IES-ACH');
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_wax_exits
                (exit_number, partner_name, partner_identifier, document_type, document_series, document_number,
                 document_date, qty_g, store_id, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $exitNumber,
                $partnerName,
                trim((string) ($data['partner_identifier'] ?? '')),
                trim((string) ($data['document_type'] ?? '')),
                trim((string) ($data['document_series'] ?? '')),
                $documentNumber,
                $this->normalizeDate((string) ($data['document_date'] ?? '')) ?: null,
                $qty,
                $storeId,
                trim((string) ($data['notes'] ?? '')),
                $userId,
            ]);
            $exitId = (int) $this->pdo->lastInsertId();
            $this->inventory('wax_purchased', -$qty, $storeId, 'purchase_wax_exit', $exitId, 'Iesire ceara achizitionata');
            $this->logAudit($userId, 'PURCHASE_WAX_EXIT', 'purchase_wax_exits', $exitId, null, $exitNumber);
            $this->pdo->commit();
            return $exitId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function normalizeSupplierType(string $supplierType): string
    {
        return match ($supplierType) {
            'Producator agricol' => 'Producator agricol',
            'PJ/PFA', 'PFA/SRL' => 'PJ/PFA',
            default => 'PF',
        };
    }

    private function purchaseDocumentData(string $supplierType, array $data): array
    {
        $type = match ($supplierType) {
            'Producator agricol' => 'carnet',
            'PJ/PFA' => 'factura',
            default => 'borderou',
        };
        $series = trim((string) ($data['document_series'] ?? ''));
        $number = trim((string) ($data['document_number'] ?? ''));
        $position = trim((string) ($data['document_position'] ?? ''));
        $date = $this->normalizeDate((string) ($data['document_date'] ?? ''));

        if ($series === '' || $number === '') {
            throw new RuntimeException($type === 'factura' ? 'Seria si numarul facturii sunt obligatorii.' : 'Seria si numarul documentului sunt obligatorii.');
        }
        if ($type !== 'factura' && $position === '') {
            throw new RuntimeException('Pozitia din document este obligatorie.');
        }
        if ($type === 'factura' && !$date) {
            throw new RuntimeException('Data facturii este obligatorie.');
        }

        return compact('type', 'series', 'number', 'position', 'date');
    }

    private function assertUniquePurchaseDocument(array $document): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM purchase_lots
             WHERE external_document_type = ?
               AND external_document_series = ?
               AND external_document_number = ?
               AND borderou_position = ?
             LIMIT 1'
        );
        $stmt->execute([$document['type'], $document['series'], $document['number'], $document['position']]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Exista deja o achizitie cu acelasi document si pozitie.');
        }
    }

    private function purchaseSupplierPayload(string $supplierType, array $data): array
    {
        $name = trim((string) ($data['supplier_name'] ?? ''));
        $identifier = trim((string) ($data['supplier_identifier'] ?? ''));
        $cui = trim((string) ($data['supplier_cui'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Numele furnizorului este obligatoriu.');
        }
        if ($supplierType === 'PJ/PFA' && $cui === '') {
            throw new RuntimeException('CUI-ul este obligatoriu pentru PJ/PFA.');
        }
        if ($supplierType !== 'PJ/PFA' && $identifier === '') {
            throw new RuntimeException('CNP/CI este obligatoriu pentru furnizorii persoane fizice.');
        }

        return [
            'supplier_type' => $supplierType,
            'name' => $name,
            'phone' => trim((string) ($data['supplier_phone'] ?? '')),
            'identifier' => $identifier,
            'cui' => $cui,
            'address' => trim((string) ($data['supplier_address'] ?? '')),
            'county_code' => trim((string) ($data['supplier_county_code'] ?? '')),
            'county_name' => trim((string) ($data['supplier_county_name'] ?? '')),
            'locality_siruta' => (int) ($data['supplier_locality_siruta'] ?? 0),
            'locality_name' => trim((string) ($data['supplier_locality_name'] ?? '')),
            'postal_code' => trim((string) ($data['supplier_postal_code'] ?? '')),
        ];
    }

    public function saveDocumentTemplates(array $templates, int $userId): void
    {
        $existing = $this->documentTemplates();
        $existingById = [];
        foreach ($existing as $template) {
            $existingById[(int) $template['id']] = $template;
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($templates as $id => $templateData) {
                $templateId = (int) $id;
                if (!isset($existingById[$templateId])) {
                    continue;
                }

                $bodyHtml = trim((string) ($templateData['body_html'] ?? ''));
                if ($bodyHtml === '') {
                    throw new RuntimeException('Template-ul "' . $existingById[$templateId]['name'] . '" nu poate fi gol.');
                }

                $this->pdo->prepare(
                    'UPDATE document_templates
                     SET body_html = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([$bodyHtml, $userId, $templateId]);
            }

            $this->logAudit($userId, 'DOCUMENT_TEMPLATES_UPDATE', 'document_templates', null, null, 'updated');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function companySettings(): array
    {
        $settings = $this->pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!$settings) {
            $this->pdo->prepare('INSERT INTO company_settings (id) VALUES (1)')->execute();
            $settings = $this->pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1')->fetch();
        }

        return $settings ?: [
            'company_name' => '',
            'vat_number' => '',
            'registry_number' => '',
            'address' => '',
            'fgo_private_key' => '',
            'purchase_default_shrinkage_pct' => 0,
            'purchase_default_price_cents_per_kg' => 0,
            'purchase_factory_shrinkage_pct' => 0,
            'purchase_factory_price_cents_per_kg' => 0,
        ];
    }

    public function saveCompanySettings(array $data, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO company_settings
            (id, company_name, vat_number, registry_number, address, fgo_private_key,
             purchase_default_shrinkage_pct, purchase_default_price_cents_per_kg,
             purchase_factory_shrinkage_pct, purchase_factory_price_cents_per_kg,
             updated_by, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                company_name = VALUES(company_name),
                vat_number = VALUES(vat_number),
                registry_number = VALUES(registry_number),
                address = VALUES(address),
                fgo_private_key = VALUES(fgo_private_key),
                purchase_default_shrinkage_pct = VALUES(purchase_default_shrinkage_pct),
                purchase_default_price_cents_per_kg = VALUES(purchase_default_price_cents_per_kg),
                purchase_factory_shrinkage_pct = VALUES(purchase_factory_shrinkage_pct),
                purchase_factory_price_cents_per_kg = VALUES(purchase_factory_price_cents_per_kg),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP'
        )->execute([
            trim((string) ($data['company_name'] ?? '')),
            trim((string) ($data['vat_number'] ?? '')),
            trim((string) ($data['registry_number'] ?? '')),
            trim((string) ($data['address'] ?? '')),
            trim((string) ($data['fgo_private_key'] ?? '')),
            (float) str_replace(',', '.', (string) ($data['purchase_default_shrinkage_pct'] ?? '0')),
            (int) round(((float) str_replace(',', '.', (string) ($data['purchase_default_price'] ?? '0'))) * 100),
            (float) str_replace(',', '.', (string) ($data['purchase_factory_shrinkage_pct'] ?? '0')),
            (int) round(((float) str_replace(',', '.', (string) ($data['purchase_factory_price'] ?? '0'))) * 100),
            $userId,
        ]);

        $this->logAudit($userId, 'COMPANY_SETTINGS_UPDATE', 'company_settings', 1, null, 'updated');
    }

    public function roleHasPermission(string $role, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT allowed FROM role_permissions WHERE role_name = ? AND permission_code = ? LIMIT 1'
        );
        $stmt->execute([$role, $permission]);
        return (bool) $stmt->fetchColumn();
    }

    public function permissions(): array
    {
        return $this->pdo->query(
            "SELECT * FROM permissions ORDER BY FIELD(
                code,
                'USER_CREATE',
                'USER_EDIT',
                'USER_RESET_PASSWORD',
                'STORE_MANAGE',
                'PROCESSOR_MANAGE',
                'DOCUMENT_TEMPLATE_MANAGE',
                'PROCESSING_CREATE',
                'PROCESSING_ACCEPT',
                'PROCESSING_REJECT',
                'PURCHASE_CREATE',
                'REPORT_VIEW',
                'AUDIT_VIEW'
            )"
        )->fetchAll();
    }

    public function rolePermissions(): array
    {
        $rows = $this->pdo->query('SELECT * FROM role_permissions')->fetchAll();
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['role_name']][$row['permission_code']] = (bool) $row['allowed'];
        }
        return $matrix;
    }

    public function documentTemplates(): array
    {
        $rows = $this->pdo->query('SELECT * FROM document_templates ORDER BY id')->fetchAll();
        foreach ($rows as &$row) {
            $variables = json_decode((string) $row['variables_json'], true);
            $row['variables'] = is_array($variables) ? $variables : [];
        }
        unset($row);
        return $rows;
    }

    public function users(): array
    {
            return $this->pdo->query('SELECT id, username, full_name, role, active, created_at FROM users ORDER BY id')->fetchAll();
    }

    public function userStores(): array
    {
        $rows = $this->pdo->query('SELECT * FROM user_stores')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (int) $row['store_id'];
        }
        return $map;
    }

    private function matchSirutaLocation(string $countyName, string $localityText, string $postalCode = ''): array
    {
        $empty = [
            'county_code' => '',
            'county_name' => '',
            'locality_siruta' => 0,
            'locality_name' => '',
            'locality_display_name' => '',
        ];

        $countyNorm = $this->normalizeLocationName($countyName);
        if ($countyNorm === '') {
            return $empty;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM siruta_counties WHERE normalized_name = ? LIMIT 1');
        $stmt->execute([$countyNorm]);
        $county = $stmt->fetch();
        if (!$county) {
            return $empty;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $localityText))));
        $candidate = $parts[0] ?? $localityText;
        $candidate = preg_replace('/^(SAT|SATUL|COMUNA|ORASUL|ORAS|MUNICIPIUL)\s+/iu', '', $candidate) ?? $candidate;
        $candidateNorm = $this->normalizeLocationName($candidate);
        if ($candidateNorm === '') {
            return [
                'county_code' => (string) $county['county_code'],
                'county_name' => (string) $county['name'],
                'locality_siruta' => 0,
                'locality_name' => '',
                'locality_display_name' => '',
            ];
        }

        $parentNorm = '';
        if (isset($parts[1])) {
            $parent = preg_replace('/^(COMUNA|ORASUL|ORAS|MUNICIPIUL)\s+/iu', '', $parts[1]) ?? $parts[1];
            $parentNorm = $this->normalizeLocationName($parent);
        }

        $sql = 'SELECT *
                FROM siruta_localities
                WHERE county_code = ? AND normalized_name = ?';
        $params = [(string) $county['county_code'], $candidateNorm];
        if ($postalCode !== '') {
            $sql .= ' ORDER BY (postal_code = ?) DESC, display_name LIMIT 20';
            $params[] = $postalCode;
        } else {
            $sql .= ' ORDER BY display_name LIMIT 20';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $matches = $stmt->fetchAll();

        $locality = $matches[0] ?? null;
        if ($parentNorm !== '') {
            foreach ($matches as $match) {
                if ($this->normalizeLocationName((string) $match['parent_name']) === $parentNorm) {
                    $locality = $match;
                    break;
                }
            }
        }

        return [
            'county_code' => (string) $county['county_code'],
            'county_name' => (string) $county['name'],
            'locality_siruta' => $locality ? (int) $locality['siruta_code'] : 0,
            'locality_name' => $locality ? (string) $locality['name'] : '',
            'locality_display_name' => $locality ? (string) $locality['display_name'] : '',
        ];
    }

    private function cleanCompanyAddress(string $address, string $countyName, string $localityName, string $postalCode): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn ($part) => $part !== ''));
        if (!$parts) {
            return '';
        }

        $countyNorm = $this->normalizeLocationName($countyName);
        $localityNorm = $this->normalizeLocationName($localityName);
        $postalCode = trim($postalCode);
        $clean = [];

        foreach ($parts as $part) {
            $normalized = $this->normalizeLocationName($part);
            if ($postalCode !== '' && preg_replace('/\D+/', '', $part) === $postalCode) {
                continue;
            }
            if ($countyNorm !== '' && str_contains($normalized, $countyNorm)) {
                continue;
            }
            if ($localityNorm !== '' && ($normalized === $localityNorm || str_contains($normalized, $localityNorm))) {
                continue;
            }
            if (in_array($normalized, ['ROMANIA', 'RO'], true)) {
                continue;
            }
            $clean[] = $part;
        }

        if (!$clean) {
            $clean = [$parts[0]];
        }

        return implode(', ', array_unique($clean));
    }

    private function normalizeLocationName(string $value): string
    {
        if (function_exists('mb_convert_encoding')) {
            try {
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2, UTF-8');
            } catch (ValueError) {
            }
        }
        $value = strtr($value, [
            'Ã„â€š' => 'A', 'Ãƒâ€š' => 'A', 'ÃƒÅ½' => 'I', 'ÃˆËœ' => 'S', 'Ã…Å¾' => 'S', 'ÃˆÅ¡' => 'T', 'Ã…Â¢' => 'T',
            'Ã„Æ’' => 'a', 'ÃƒÂ¢' => 'a', 'ÃƒÂ®' => 'i', 'Ãˆâ„¢' => 's', 'Ã…Å¸' => 's', 'Ãˆâ€º' => 't', 'Ã…Â£' => 't',
            'ÃƒÅ¾' => 'T', 'ÃƒÂ¾' => 't', 'Ã‚Âª' => 'S', 'Ã‚Âº' => 's',
        ]);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public function searchCustomers(string $customerType, string $term): array
    {
        $customerType = $customerType === 'PJ' ? 'PJ' : 'PF';
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if ($customerType === 'PJ') {
            $stmt = $this->pdo->prepare(
                "SELECT id, customer_type, name, phone, address, identifier, cui, representative,
                        county_code, county_name, locality_siruta, locality_name, postal_code,
                        registry_number, legal_form, vat_status, external_source, external_checked_at
                 FROM customers
                 WHERE customer_type = ? AND (identifier LIKE ? OR cui LIKE ?)
                 ORDER BY id DESC
                 LIMIT 8"
            );
            $stmt->execute([$customerType, '%' . $term . '%', '%' . $term . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, customer_type, name, phone, address, identifier, cui, representative,
                    county_code, county_name, locality_siruta, locality_name, postal_code,
                    registry_number, legal_form, vat_status, external_source, external_checked_at
             FROM customers
             WHERE customer_type = ? AND (phone LIKE ? OR identifier LIKE ? OR cui LIKE ?)
             ORDER BY id DESC
             LIMIT 8"
        );
        $stmt->execute([$customerType, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private function resolveProcessingCustomer(array $data): array
    {
        $customerType = $data['customer_type'] === 'PJ' ? 'PJ' : 'PF';
        $existingCustomerId = (int) ($data['existing_customer_id'] ?? 0);
        $isNewCustomer = !empty($data['force_new_customer']);

        if ($existingCustomerId > 0 && !$isNewCustomer) {
            $existing = $this->find('customers', $existingCustomerId);
            if (!$existing) {
                throw new RuntimeException('Clientul selectat nu mai exista.');
            }
            $customerPayload = [
                'customer_type' => $customerType,
                'name' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_name_pj'] ?? $data['customer_name']))
                    : trim((string) $data['customer_name']),
                'phone' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_phone_pj'] ?? $data['customer_phone']))
                    : trim((string) $data['customer_phone']),
                'address' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_address_pj'] ?? $data['customer_address']))
                    : trim((string) $data['customer_address']),
                'identifier' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_cui'] ?? ''))
                    : trim((string) ($data['customer_identifier'] ?? '')),
                'cui' => trim((string) ($data['customer_cui'] ?? '')),
                'representative' => trim((string) ($data['customer_representative'] ?? '')),
            ] + $this->customerLocationPayload($data);
            $this->updateCustomer($existingCustomerId, $customerPayload);
            $updated = $this->find('customers', $existingCustomerId);
            if (!$updated) {
                throw new RuntimeException('Clientul nu a putut fi actualizat.');
            }
            return $updated;
        }

        $customerId = $this->upsertCustomer([
            'customer_type' => $customerType,
            'name' => $customerType === 'PJ'
                ? trim((string) ($data['customer_name_pj'] ?? $data['customer_name']))
                : trim((string) $data['customer_name']),
            'phone' => $customerType === 'PJ'
                ? trim((string) ($data['customer_phone_pj'] ?? $data['customer_phone']))
                : trim((string) $data['customer_phone']),
            'address' => $customerType === 'PJ'
                ? trim((string) ($data['customer_address_pj'] ?? $data['customer_address']))
                : trim((string) $data['customer_address']),
            'identifier' => $customerType === 'PJ'
                ? trim((string) ($data['customer_cui'] ?? ''))
                : trim((string) ($data['customer_identifier'] ?? '')),
            'cui' => trim((string) ($data['customer_cui'] ?? '')),
            'representative' => trim((string) ($data['customer_representative'] ?? '')),
            'known_customer' => !empty($data['known_customer']),
        ] + $this->customerLocationPayload($data));

        $created = $this->find('customers', $customerId);
        if (!$created) {
            throw new RuntimeException('Clientul nu a putut fi creat.');
        }
        return $created;
    }

    private function upsertCustomer(array $customer): int
    {
        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            throw new RuntimeException('Numele, telefonul si adresa sunt obligatorii.');
        }

        if ($customer['customer_type'] === 'PJ' && ($customer['identifier'] === '' || $customer['representative'] === '')) {
            throw new RuntimeException('Pentru PJ sunt obligatorii CUI-ul si reprezentantul.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers
            (customer_type, name, phone, address, identifier, cui, representative, county_code, county_name,
             locality_siruta, locality_name, postal_code, registry_number, legal_form, vat_status, external_source,
             external_checked_at, known_customer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customer['customer_type'],
            $customer['name'],
            $customer['phone'],
            $customer['address'],
            $customer['identifier'],
            $customer['cui'],
            $customer['representative'],
            $customer['county_code'],
            $customer['county_name'],
            $customer['locality_siruta'] ?: null,
            $customer['locality_name'],
            $customer['postal_code'],
            $customer['registry_number'],
            $customer['legal_form'],
            $customer['vat_status'],
            $customer['external_source'],
            $customer['external_checked_at'] ?: null,
            $customer['known_customer'] ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function updateCustomer(int $customerId, array $customer): void
    {
        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            throw new RuntimeException('Numele, telefonul si adresa sunt obligatorii.');
        }

        if ($customer['customer_type'] === 'PJ' && ($customer['identifier'] === '' || $customer['representative'] === '')) {
            throw new RuntimeException('Pentru PJ sunt obligatorii CUI-ul si reprezentantul.');
        }

        $this->pdo->prepare(
            'UPDATE customers
             SET customer_type = ?, name = ?, phone = ?, address = ?, identifier = ?, cui = ?, representative = ?,
                 county_code = ?, county_name = ?, locality_siruta = ?, locality_name = ?, postal_code = ?,
                 registry_number = ?, legal_form = ?, vat_status = ?, external_source = ?, external_checked_at = ?
             WHERE id = ?'
        )->execute([
            $customer['customer_type'],
            $customer['name'],
            $customer['phone'],
            $customer['address'],
            $customer['identifier'],
            $customer['cui'],
            $customer['representative'],
            $customer['county_code'],
            $customer['county_name'],
            $customer['locality_siruta'] ?: null,
            $customer['locality_name'],
            $customer['postal_code'],
            $customer['registry_number'],
            $customer['legal_form'],
            $customer['vat_status'],
            $customer['external_source'],
            $customer['external_checked_at'] ?: null,
            $customerId,
        ]);
    }

    private function customerLocationPayload(array $data): array
    {
        return [
            'county_code' => trim((string) ($data['customer_county_code'] ?? '')),
            'county_name' => trim((string) ($data['customer_county_name'] ?? '')),
            'locality_siruta' => (int) ($data['customer_locality_siruta'] ?? 0),
            'locality_name' => trim((string) ($data['customer_locality_name'] ?? '')),
            'postal_code' => trim((string) ($data['customer_postal_code'] ?? '')),
            'registry_number' => trim((string) ($data['customer_registry_number'] ?? '')),
            'legal_form' => trim((string) ($data['customer_legal_form'] ?? '')),
            'vat_status' => trim((string) ($data['customer_vat_status'] ?? '')),
            'external_source' => trim((string) ($data['customer_external_source'] ?? '')),
            'external_checked_at' => trim((string) ($data['customer_external_checked_at'] ?? '')),
        ];
    }

    private function upsertSupplier(array $supplier): int
    {
        $lookupSql = $supplier['supplier_type'] === 'PJ/PFA'
            ? 'SELECT id FROM suppliers WHERE supplier_type = ? AND cui = ? AND cui <> "" LIMIT 1'
            : 'SELECT id FROM suppliers WHERE supplier_type = ? AND name = ? AND identifier = ? LIMIT 1';
        $lookupParams = $supplier['supplier_type'] === 'PJ/PFA'
            ? [$supplier['supplier_type'], $supplier['cui']]
            : [$supplier['supplier_type'], $supplier['name'], $supplier['identifier']];

        $stmt = $this->pdo->prepare($lookupSql);
        $stmt->execute($lookupParams);
        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            $this->pdo->prepare(
                'UPDATE suppliers
                 SET name = ?, phone = ?, identifier = ?, cui = ?, address = ?, county_code = ?, county_name = ?,
                     locality_siruta = ?, locality_name = ?, postal_code = ?
                 WHERE id = ?'
            )->execute([
                $supplier['name'],
                $supplier['phone'],
                $supplier['identifier'],
                $supplier['cui'],
                $supplier['address'],
                $supplier['county_code'],
                $supplier['county_name'],
                $supplier['locality_siruta'] ?: null,
                $supplier['locality_name'],
                $supplier['postal_code'],
                $existingId,
            ]);
            return $existingId;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers
            (name, supplier_type, phone, identifier, cui, address, county_code, county_name, locality_siruta, locality_name, postal_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $supplier['name'],
            $supplier['supplier_type'],
            $supplier['phone'],
            $supplier['identifier'],
            $supplier['cui'],
            $supplier['address'],
            $supplier['county_code'],
            $supplier['county_name'],
            $supplier['locality_siruta'] ?: null,
            $supplier['locality_name'],
            $supplier['postal_code'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function nextLotNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function document(string $type, string $referenceType, int $referenceId, int $storeId, string $status, string $notes, array $links = []): int
    {
        return (new \Ceara\Documents\DocumentIssuer($this->pdo))->issue(
            $type,
            $referenceType,
            $referenceId,
            $storeId,
            $status,
            $notes,
            $links,
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function renderDocumentFile(int $documentId): void
    {
        $doc = $this->documentById($documentId);
        if (!$doc) {
            return;
        }

        $template = $this->documentTemplateByCode((string) $doc['document_type']);
        if (!$template) {
            return;
        }

        $store = $this->find('stores', (int) $doc['store_id']);
        if (!$store) {
            return;
        }

        $oldRelativePath = (string) ($doc['file_path'] ?? '');
        $html = $this->wrapDocumentHtml($this->renderTemplate(
            (string) $template['body_html'],
            $this->documentVariables($doc, $store)
        ));
        $relativePath = $this->documentRelativePath($doc, $store, 'pdf');
        $absolutePath = $this->storagePath($relativePath);
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($absolutePath, $this->buildPdfFromHtml($html));
        $this->pdo->prepare('UPDATE documents SET file_path = ? WHERE id = ?')
            ->execute([$relativePath, $documentId]);
        $this->removeReplacedHtmlDocument($oldRelativePath, $relativePath);
    }

    private function removeReplacedHtmlDocument(string $oldRelativePath, string $newRelativePath): void
    {
        $this->documentFiles()->removeReplacedHtmlDocument($oldRelativePath, $newRelativePath);
    }

    private function documentTemplateByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_templates WHERE code = ? AND active = 1 LIMIT 1');
        $stmt->execute([$code]);
        $template = $stmt->fetch();
        return $template ?: null;
    }

    private function documentVariables(array $doc, array $store): array
    {
        $company = $this->companySettings();
        $variables = [
            'document_number' => $this->formatDocumentLabel($doc),
            'document_date' => date('Y-m-d', strtotime((string) ($doc['created_at'] ?? 'now'))),
            'company_name' => (string) ($company['company_name'] ?? ''),
            'company_vat_number' => (string) ($company['vat_number'] ?? ''),
            'company_registry_number' => (string) ($company['registry_number'] ?? ''),
            'company_address' => (string) ($company['address'] ?? ''),
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
            'nir_number' => $this->formatDocumentLabel($doc),
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
                $variables['aviz_date'] = date('Y-m-d', strtotime((string) ($adjustment['created_at'] ?? $doc['created_at'] ?? 'now')));
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

    private function renderTemplate(string $html, array $variables): string
    {
        return (new \Ceara\Documents\TemplateRenderer())->render($html, $variables);
    }

    private function wrapDocumentHtml(string $body): string
    {
        return (new \Ceara\Documents\TemplateRenderer())->wrapDocument($body);
    }

    private function buildPdfFromHtml(string $html): string
    {
        return (new \Ceara\Documents\PdfRenderer())->renderA4Portrait($html);
    }

    private function documentRelativePath(array $doc, array $store, string $extension): string
    {
        return $this->documentFiles()->relativePath($doc, $store, $this->formatDocumentLabel($doc), $extension);
    }

    private function safePathPart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
        return trim((string) $safe, '_') ?: 'document';
    }

    private function storagePath(string $relativePath): string
    {
        return $this->documentFiles()->storagePath($relativePath);
    }

    private function documentFiles(): \Ceara\Documents\DocumentFiles
    {
        return new \Ceara\Documents\DocumentFiles($this->pdo, dirname(__DIR__) . '/storage');
    }

    private function inventory(string $type, int $qty, int $storeId, string $refType, int $refId, string $notes): void
    {
        (new \Ceara\Inventory\InventoryWriter($this->pdo))->record($type, $qty, $storeId, $refType, $refId, $notes);
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

    private function processingLotSummaries(): array
    {
        $lots = $this->processingLots();
        $summaries = [];
        foreach ($lots as $lot) {
            $summaries[] = $this->buildProcessingLotSummary($lot, $this->movementTotals([(int) $lot['id']])[(int) $lot['id']] ?? []);
        }
        return $summaries;
    }

    private function processingLotSummary(int $lotId): ?array
    {
        $lot = $this->pdo->prepare(
            'SELECT p.*, c.name AS customer_name, c.customer_type, s.name AS store_name, pr.name AS processor_name
             FROM processing_lots p
             JOIN customers c ON c.id = p.customer_id
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN processors pr ON pr.id = p.processor_id
             WHERE p.id = ?'
        );
        $lot->execute([$lotId]);
        $row = $lot->fetch();
        if (!$row) {
            return null;
        }

        return $this->buildProcessingLotSummary($row, $this->movementTotals([$lotId])[$lotId] ?? []);
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
        $waxAvailableForExchange = max(0, $received - $exchanged - $sentFactory - $returned - $rejected - $lossWax);
        $waxToFactory = max(0, $exchanged - $sentFactory - $rejected);
        $openRejectedWax = max(0, $rejected - $lossWax);
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
                'SELECT p.id, c.name AS partner_name, u.username
                 FROM processing_lots p
                 JOIN customers c ON c.id = p.customer_id
                 JOIN users u ON u.id = p.created_by
                 WHERE p.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            return [
                'partner' => $row['partner_name'] ?? 'Client',
                'document' => $this->documentInfoForLot((int) ($row['id'] ?? $referenceId), 'PV-CUST'),
                'lot_number' => $this->lotNumber((int) ($row['id'] ?? $referenceId)),
                'lot_url' => 'index.php?page=lot_detail&lot_id=' . (int) ($row['id'] ?? $referenceId),
                'operator' => $row['username'] ?? '-',
            ];
        }

        if ($referenceType === 'processing_lot_movement') {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, p.lot_number, c.name AS partner_name, u.username
                 FROM processing_lot_movements m
                 JOIN processing_lots p ON p.id = m.lot_id
                 JOIN customers c ON c.id = p.customer_id
                 JOIN users u ON u.id = m.created_by
                 WHERE m.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            $fallback = match ($row['movement_type'] ?? '') {
                'EXCHANGE_WAX_WITH_CLIENT' => 'PV-FAG',
                'RETURN_WAX_TO_CLIENT' => 'PV-RET',
                default => 'PV',
            };
            return [
                'partner' => $row['partner_name'] ?? 'Client',
                'document' => $this->documentInfoForMovement($referenceId, $fallback),
                'lot_number' => $row['lot_number'] ?? '-',
                'lot_url' => 'index.php?page=lot_detail&lot_id=' . (int) ($row['lot_id'] ?? 0),
                'operator' => $row['username'] ?? '-',
            ];
        }

        if ($referenceType === 'factory_batch') {
            $stmt = $this->pdo->prepare(
                'SELECT b.*, p.name AS partner_name, u.username
                 FROM factory_batches b
                 JOIN processors p ON p.id = b.processor_id
                 JOIN users u ON u.id = b.created_by
                 WHERE b.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            return [
                'partner' => $row['partner_name'] ?? 'Fabrica',
                'document' => $this->documentInfoForReference('factory_batch', $referenceId, 'AVIZ'),
                'lot_number' => '',
                'lot_url' => '',
                'operator' => $row['username'] ?? '-',
            ];
        }

        if ($referenceType === 'factory_buffer_adjustment') {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, s.processor_id, p.name AS partner_name, u.username
                 FROM factory_buffer_adjustments a
                 JOIN stores s ON s.id = a.store_id
                 LEFT JOIN processors p ON p.id = s.processor_id
                 JOIN users u ON u.id = a.created_by
                 WHERE a.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            return [
                'partner' => $row['partner_name'] ?? 'Fabrica',
                'document' => $this->documentInfoForReference('factory_buffer_adjustment', $referenceId, 'NIR'),
                'lot_number' => '',
                'lot_url' => '',
                'operator' => $row['username'] ?? '-',
            ];
        }

        return [
            'partner' => '-',
            'document' => ['label' => '-', 'url' => ''],
            'lot_number' => '',
            'lot_url' => '',
            'operator' => '-',
        ];
    }

    private function documentInfoForLot(int $lotId, string $fallback): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type, series, number
             FROM documents
             WHERE lot_id = ? AND document_type = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$lotId, $fallback]);
        $document = $stmt->fetch();
        return $this->documentInfo($document ?: null, $fallback);
    }

    private function purchaseRegisterMeta(string $referenceType, int $referenceId): array
    {
        if ($referenceType === 'purchase_lot') {
            $stmt = $this->pdo->prepare(
                'SELECT p.*, s.name AS supplier_name, u.username
                 FROM purchase_lots p
                 JOIN suppliers s ON s.id = p.supplier_id
                 JOIN users u ON u.id = p.created_by
                 WHERE p.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'partner' => (string) $row['supplier_name'],
                    'document' => trim((string) ($row['external_document_series'] . '-' . $row['external_document_number']), '-'),
                    'position' => (string) ($row['borderou_position'] ?? ''),
                    'operator' => (string) $row['username'],
                ];
            }
        }

        if ($referenceType === 'purchase_wax_exit') {
            $stmt = $this->pdo->prepare(
                'SELECT e.*, u.username
                 FROM purchase_wax_exits e
                 JOIN users u ON u.id = e.created_by
                 WHERE e.id = ?'
            );
            $stmt->execute([$referenceId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'partner' => (string) $row['partner_name'],
                    'document' => trim((string) ($row['document_series'] . '-' . $row['document_number']), '-'),
                    'position' => '',
                    'operator' => (string) $row['username'],
                ];
            }
        }

        return [
            'partner' => '-',
            'document' => '-',
            'position' => '',
            'operator' => '-',
        ];
    }

    private function purchaseWaxExits(): array
    {
        return $this->pdo->query(
            'SELECT e.*, s.name AS store_name, u.username
             FROM purchase_wax_exits e
             JOIN stores s ON s.id = e.store_id
             JOIN users u ON u.id = e.created_by
             ORDER BY e.id DESC
             LIMIT 100'
        )->fetchAll();
    }

    private function documentInfoForMovement(int $movementId, string $fallback): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type, series, number
             FROM documents
             WHERE movement_id = ?
               AND document_type IN ("PV-CUST", "PV-FAG", "PV-RET", "NIR", "AVIZ")
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$movementId]);
        $document = $stmt->fetch();
        return $this->documentInfo($document ?: null, $fallback);
    }

    private function documentInfoForReference(string $referenceType, int $referenceId, string $fallback): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type, series, number
             FROM documents
             WHERE reference_type = ? AND reference_id = ?
               AND document_type = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$referenceType, $referenceId, $fallback]);
        $document = $stmt->fetch();
        return $this->documentInfo($document ?: null, $fallback);
    }

    private function documentInfo(?array $document, string $fallback): array
    {
        if (!$document) {
            return ['label' => $fallback, 'url' => ''];
        }

        return [
            'label' => $this->formatDocumentLabel($document),
            'url' => 'index.php?page=document_mock&document_id=' . (int) $document['id'],
        ];
    }

    private function formatDocumentLabel(array $document): string
    {
        return trim($document['document_type'] . ' ' . $document['series'] . '-' . $document['number']);
    }

    private function lotNumber(int $lotId): string
    {
        $stmt = $this->pdo->prepare('SELECT lot_number FROM processing_lots WHERE id = ?');
        $stmt->execute([$lotId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function isAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() === 'admin';
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

    private function sumInventory(string $type): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty_g), 0) FROM inventory_transactions WHERE movement_type = ?');
        $stmt->execute([$type]);
        return (int) $stmt->fetchColumn();
    }

    private function sumInventoryForStore(string $type, int $storeId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty_g), 0) FROM inventory_transactions WHERE movement_type = ? AND store_id = ?');
        $stmt->execute([$type, $storeId]);
        return (int) $stmt->fetchColumn();
    }

    private function sumInventoryForStoreUntil(string $type, int $storeId, string $dateTime, bool $inclusive): int
    {
        $operator = $inclusive ? '<=' : '<';
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(qty_g), 0)
             FROM inventory_transactions
             WHERE movement_type = ?
               AND store_id = ?
               AND created_at $operator ?"
        );
        $stmt->execute([$type, $storeId, $dateTime]);
        return (int) $stmt->fetchColumn();
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

    private function countRows(string $table, string $where): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
    }

    private function find(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function processingProcessorData(int $processorId, int $storeId = 0): array
    {
        if ($processorId <= 0) {
            return [
                'id' => null,
                'processing_price_cents' => 0,
                'exchange_shrinkage_pct' => 0,
            ];
        }

        $processor = $this->find('processors', $processorId);
        if (!$processor) {
            throw new RuntimeException('Procesatorul selectat nu exista.');
        }
        if ($storeId > 0) {
            $store = $this->find('stores', $storeId);
            if ($store && (int) ($store['processor_id'] ?? 0) === $processorId) {
                $processor['processing_price_cents'] = (int) ($store['processing_price_cents'] ?? $processor['processing_price_cents']);
                $processor['exchange_shrinkage_pct'] = (float) ($store['processing_shrinkage_pct'] ?? $processor['exchange_shrinkage_pct']);
            }
        }
        return $processor;
    }
}
