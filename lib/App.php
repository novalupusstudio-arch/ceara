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
        $summaries = $this->processingService()->processingLotSummaries();
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
        return $this->processingService()->processingLots();
    }

    public function factoryDeliveryData(int $processorId): array
    {
        return $this->processingService()->factoryDeliveryData($processorId, fn () => $this->processingService()->processingLotSummaries());
    }

    public function processingLotStatuses(): array
    {
        return $this->processingService()->processingLotStatuses();
    }

    public function processingLotsBoard(array $filters = []): array
    {
        return $this->processingService()->processingLotsBoard($this->processingService()->processingLotSummaries(), $filters);
    }

    public function factoryBufferData(): array
    {
        return $this->processingService()->factoryBufferData();
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

        $renderer = new \Ceara\Documents\TemplateRenderer();
        return $renderer->wrapDocument($renderer->render((string) $template['body_html'], $this->sampleTemplateVariables()));
    }

    public function documentPdfById(int $documentId): ?string
    {
        return $this->documentFiles()->pdfById($documentId, fn (int $id) => $this->renderDocumentFile($id));
    }

    public function processingRegisterData(int $userId, string $dateStart = '', string $dateEnd = ''): array
    {
        return $this->processingService()->processingRegisterData($userId, $dateStart, $dateEnd);
    }

    public function processingLotDetail(int $lotId): array
    {
        return $this->processingService()->processingLotDetail($lotId, fn (int $id) => $this->processingService()->processingLotSummary($id));
    }

    public function purchaseLots(): array
    {
        return $this->purchaseService()->purchaseLots();
    }

    public function purchaseRegisterData(int $userId, string $dateStart = '', string $dateEnd = ''): array
    {
        return $this->purchaseService()->purchaseRegisterData($userId, $dateStart, $dateEnd);
    }

    public function purchaseExitData(int $userId): array
    {
        return $this->purchaseService()->purchaseExitData($userId);
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
        return $this->processingWriteService()->createProcessingLot($data, $userId);
    }

    public function createProcessingExchange(int $lotId, string $waxKg, int $userId): int
    {
        return $this->processingWriteService()->createProcessingExchange($lotId, $waxKg, $userId);
    }

    public function createProcessingReturn(int $lotId, string $waxKg, string $notes, int $userId): int
    {
        return $this->processingWriteService()->createProcessingReturn($lotId, $waxKg, $notes, $userId);
    }

    public function createFactoryBufferAdjustment(array $data, int $userId): int
    {
        return $this->processingWriteService()->createFactoryBufferAdjustment($data, $userId);
    }

    public function transitionProcessingLot(int $lotId, string $action, int $userId): void
    {
        $this->processingWriteService()->transitionProcessingLot($lotId, $action, $userId);
    }

    public function createFactoryBatch(array $data, int $userId): int
    {
        return $this->processingWriteService()->createFactoryBatch($data, $userId);
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
        return $this->purchaseService()->createPurchaseLot($data, $userId);
    }

    public function advancePurchaseLot(int $lotId, int $userId): void
    {
        $this->purchaseService()->advancePurchaseLot($lotId, $userId);
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
        return $this->purchaseService()->createPurchaseWaxExit($data, $userId);
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
        $this->documentGenerator()->renderFile(
            $documentId,
            fn (array $doc, array $store) => $this->documentVariables($doc, $store),
            fn (array $doc) => $this->formatDocumentLabel($doc)
        );
    }

    private function documentVariables(array $doc, array $store): array
    {
        return (new \Ceara\Documents\DocumentVariablesBuilder(
            $this->pdo,
            $this->companySettings()
        ))->build($doc, $store, $this->formatDocumentLabel($doc));
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

    private function documentGenerator(): \Ceara\Documents\DocumentGenerator
    {
        return new \Ceara\Documents\DocumentGenerator(
            $this->pdo,
            $this->documentFiles(),
            new \Ceara\Documents\TemplateRenderer(),
            new \Ceara\Documents\PdfRenderer()
        );
    }

    private function processingService(): ProcessingService
    {
        return new ProcessingService($this->pdo, $this->inventoryService());
    }

    private function processingWriteService(): \Ceara\ProcessingWriteService
    {
        return new \Ceara\ProcessingWriteService(
            $this->pdo,
            $this->inventoryService(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function purchaseService(): \Ceara\PurchaseService
    {
        return new \Ceara\PurchaseService(
            $this->pdo,
            $this->inventoryService(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function inventory(string $type, int $qty, int $storeId, string $refType, int $refId, string $notes): void
    {
        $this->inventoryService()->record($type, $qty, $storeId, $refType, $refId, $notes);
    }


    private function isAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() === 'admin';
    }

    private function logAudit(int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new): void
    {
        $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, operation, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $operation, $entity, $entityId, $old, $new]);
    }

    private function sumInventory(string $type): int
    {
        return $this->inventoryService()->sum($type);
    }

    private function sumInventoryForStore(string $type, int $storeId): int
    {
        return $this->inventoryService()->sumForStore($type, $storeId);
    }

    private function sumInventoryForStoreUntil(string $type, int $storeId, string $dateTime, bool $inclusive): int
    {
        return $this->inventoryService()->sumForStoreUntil($type, $storeId, $dateTime, $inclusive);
    }

    private function inventoryService(): \Ceara\Inventory\InventoryService
    {
        return new \Ceara\Inventory\InventoryService($this->pdo);
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

}
