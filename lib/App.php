<?php

final class App
{
    public function __construct(public PDO $pdo, private array $config = [])
    {
    }

    public function stores(): array
    {
        return $this->settingsService()->stores();
    }

    public function processors(): array
    {
        return $this->settingsService()->processors();
    }

    public function sirutaCounties(): array
    {
        return $this->customerService()->sirutaCounties();
    }

    public function sirutaLocalities(string $countyCode, string $term = ''): array
    {
        return $this->customerService()->sirutaLocalities($countyCode, $term);
    }

    public function lookupAnafCompany(string $cui): array
    {
        return $this->customerService()->lookupAnafCompany($cui);
    }

    public function userPrimaryStore(int $userId): ?array
    {
        return $this->customerService()->userPrimaryStore($userId);
    }

    public function defaultProcessorForUser(int $userId): ?array
    {
        return $this->customerService()->defaultProcessorForUser($userId);
    }

    public function searchCustomers(string $customerType, string $term): array
    {
        return $this->customerService()->searchCustomers($customerType, $term);
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
        if ($processorId <= 0) {
            $store = $this->userPrimaryStore((int) current_user()['id']);
            $processorId = (int) ($store['processor_id'] ?? 0);
        }

        if ($processorId <= 0) {
            throw new RuntimeException('Gestiunea utilizatorului nu are procesator asignat pentru predarea la fabrica.');
        }

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
        return $this->documentCatalogService()->documentById($documentId);
    }

    public function documentPreviewByTemplateId(int $templateId): ?string
    {
        return $this->documentCatalogService()->documentPreviewByTemplateId($templateId);
    }

    public function documentPdfById(int $documentId): ?string
    {
        return $this->documentCatalogService()->documentPdfById($documentId);
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
        return $this->documentCatalogService()->documents();
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
        return $this->processingDocumentService()->ensureProcessingDocument($lotId, $documentType, $userId, $movementId, $paymentMethod);
    }

    public function createPurchaseLot(array $data, int $userId): int
    {
        return $this->purchaseService()->createPurchaseLot($data, $userId);
    }

    public function advancePurchaseLot(int $lotId, int $userId): void
    {
        $this->purchaseService()->advancePurchaseLot($lotId, $userId);
    }

    public function settings(): array
    {
        return $this->settingsService()->settings();
    }

    public function changeOwnPassword(int $userId, string $newPassword, string $confirmPassword): void
    {
        $this->settingsService()->changeOwnPassword($userId, $newPassword, $confirmPassword);
    }

    public function saveRolePermissions(array $matrix, int $userId): void
    {
        $this->settingsService()->saveRolePermissions($matrix, $userId);
    }

    public function createUser(array $data, int $userId): void
    {
        $this->settingsService()->createUser($data, $userId);
    }

    public function saveStore(array $data, int $userId): void
    {
        $this->settingsService()->saveStore($data, $userId);
    }
    public function saveProcessor(array $data, int $userId): void
    {
        $this->settingsService()->saveProcessor($data, $userId);
    }

    public function createPurchaseWaxExit(array $data, int $userId): int
    {
        return $this->purchaseService()->createPurchaseWaxExit($data, $userId);
    }

    public function saveDocumentTemplates(array $templates, int $userId): void
    {
        $this->settingsService()->saveDocumentTemplates($templates, $userId);
    }

    public function saveDocumentSeries(array $seriesRows, int $userId): void
    {
        $this->settingsService()->saveDocumentSeries($seriesRows, $userId);
    }

    public function companySettings(): array
    {
        return $this->settingsService()->companySettings();
    }

    public function saveCompanySettings(array $data, int $userId): void
    {
        $this->settingsService()->saveCompanySettings($data, $userId);
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
        return $this->settingsService()->permissions();
    }

    public function rolePermissions(): array
    {
        return $this->settingsService()->rolePermissions();
    }

    public function documentTemplates(): array
    {
        return $this->settingsService()->documentTemplates();
    }

    public function users(): array
    {
        return $this->settingsService()->users();
    }

    public function userStores(): array
    {
        return $this->settingsService()->userStores();
    }

    private function customerService(): \Ceara\CustomerService
    {
        return new \Ceara\CustomerService(
            $this->pdo,
            $this->config,
            fn (string $table, int $id) => $this->find($table, $id)
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
            $this->customerService(),
            $this->documentService(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function processingDocumentService(): \Ceara\ProcessingDocumentService
    {
        return new \Ceara\ProcessingDocumentService(
            $this->pdo,
            $this->documentService(),
            $this->fgoService(),
            $this->fiscalWireService(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function purchaseService(): \Ceara\PurchaseService
    {
        return new \Ceara\PurchaseService(
            $this->pdo,
            $this->inventoryService(),
            $this->supplierService(),
            $this->documentService(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function documentService(): \Ceara\DocumentService
    {
        return new \Ceara\DocumentService(
            $this->pdo,
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function documentCatalogService(): \Ceara\DocumentCatalogService
    {
        return new \Ceara\DocumentCatalogService(
            $this->pdo,
            $this->documentFiles(),
            fn (int $documentId) => $this->renderDocumentFile($documentId)
        );
    }

    private function fgoService(): \Ceara\FgoService
    {
        return new \Ceara\FgoService(
            $this->pdo,
            $this->config,
            fn (int $documentId) => $this->documentById($documentId),
            fn () => $this->companySettings()
        );
    }

    private function fiscalWireService(): \Ceara\FiscalWireService
    {
        return new \Ceara\FiscalWireService(
            $this->pdo,
            $this->config,
            fn (int $documentId) => $this->documentById($documentId),
            fn (int $storeId) => $this->find('stores', $storeId)
        );
    }

    private function supplierService(): \Ceara\SupplierService
    {
        return new \Ceara\SupplierService($this->pdo);
    }

    private function settingsService(): \Ceara\SettingsService
    {
        return new \Ceara\SettingsService(
            $this->pdo,
            fn (string $table, int $id) => $this->find($table, $id),
            fn (int $userId, string $operation, string $entity, ?int $entityId, ?string $old, ?string $new) => $this->logAudit($userId, $operation, $entity, $entityId, $old, $new)
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
