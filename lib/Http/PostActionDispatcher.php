<?php

declare(strict_types=1);

namespace Ceara\Http;

use App;
use Auth;
use RuntimeException;
use Throwable;

final class PostActionDispatcher
{
    public function handleLogin(App $app, Auth $auth): void
    {
        if ($auth->login(\post_string('username'), \post_string('password'))) {
            \redirect('dashboard');
        }

        \flash('Utilizator sau parola invalida.', 'error');
        \redirect('login');
    }

    public function handleAuthenticated(App $app, Auth $auth, array $user, string $action): void
    {
        if ($action === 'logout') {
            $auth->logout();
            \redirect('login');
        }

        if ($action === 'select_flow') {
            $flow = \post_string('flow');
            if (!in_array($flow, ['processing', 'purchase'], true)) {
                \flash('Fluxul selectat nu exista.', 'error');
                \redirect('dashboard');
            }

            $_SESSION['active_flow'] = $flow;
            \flash($flow === 'processing' ? 'Fluxul de procesare a fost activat.' : 'Fluxul de achizitie a fost activat.');
            \redirect('dashboard');
        }

        if ($action === 'create_processing') {
            $assignedStore = $app->userPrimaryStore($user['id']);
            if (!$assignedStore) {
                throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
            }

            $app->createProcessingLot([
                'customer_name' => \post_string('customer_name'),
                'customer_phone' => \post_string('customer_phone'),
                'customer_address' => \post_string('customer_address'),
                'customer_identifier' => \post_string('customer_identifier'),
                'customer_type' => \post_string('customer_type', 'PF'),
                'customer_name_pj' => \post_string('customer_name_pj'),
                'customer_phone_pj' => \post_string('customer_phone_pj'),
                'customer_address_pj' => \post_string('customer_address_pj'),
                'customer_cui' => \post_string('customer_cui'),
                'customer_representative' => \post_string('customer_representative'),
                'customer_county_code' => \post_string('customer_county_code'),
                'customer_county_name' => \post_string('customer_county_name'),
                'customer_locality_siruta' => \post_int('customer_locality_siruta'),
                'customer_locality_name' => \post_string('customer_locality_name'),
                'customer_postal_code' => \post_string('customer_postal_code'),
                'customer_registry_number' => \post_string('customer_registry_number'),
                'customer_legal_form' => \post_string('customer_legal_form'),
                'customer_vat_status' => \post_string('customer_vat_status'),
                'customer_external_source' => \post_string('customer_external_source'),
                'customer_external_checked_at' => \post_string('customer_external_checked_at'),
                'existing_customer_id' => \post_int('existing_customer_id'),
                'force_new_customer' => \post_int('force_new_customer'),
                'known_customer' => \post_int('known_customer'),
                'gross_kg' => \post_string('gross_kg'),
                'store_id' => (int) $assignedStore['id'],
                'processor_id' => \post_int('processor_id'),
                'processing_price' => \post_string('processing_price'),
                'shrinkage_pct' => \post_string('shrinkage_pct'),
            ], $user['id']);
            \flash('Lotul de procesare a fost creat.');
            \redirect('lots');
        }

        if ($action === 'transition_processing') {
            $app->transitionProcessingLot(\post_int('lot_id'), \post_string('transition'), $user['id']);
            \flash('Statusul lotului a fost actualizat.');
            \redirect('lots');
        }

        if ($action === 'processing_document') {
            $documentId = $app->ensureProcessingDocument(
                \post_int('lot_id'),
                \post_string('document_type'),
                $user['id'],
                \post_int('movement_id'),
                \post_string('payment_method', 'cash')
            );
            \redirect('document_mock', ['document_id' => $documentId]);
        }

        if ($action === 'processing_exchange') {
            $app->createProcessingExchange(\post_int('lot_id'), \post_string('exchange_kg'), $user['id']);
            \flash('Schimbul de ceara a fost inregistrat.');
            \redirect('lot_detail', ['lot_id' => \post_int('lot_id')]);
        }

        if ($action === 'processing_return') {
            $app->createProcessingReturn(\post_int('lot_id'), \post_string('return_kg'), \post_string('return_notes'), $user['id']);
            \flash('Returul de ceara a fost inregistrat.');
            \redirect('lot_detail', ['lot_id' => \post_int('lot_id')]);
        }

        if ($action === 'create_factory_batch') {
            $app->createFactoryBatch([
                'processor_id' => \post_int('processor_id'),
                'aviz_number' => \post_string('aviz_number'),
                'aviz_date' => \post_string('aviz_date'),
                'lot_qty' => $_POST['lot_qty'] ?? [],
                'reject_qty' => $_POST['reject_qty'] ?? [],
            ], $user['id']);
            \flash('Predarea catre fabrica a fost salvata.');
            \redirect('factory_delivery', ['processor_id' => \post_int('processor_id')]);
        }

        if ($action === 'factory_buffer_adjustment') {
            $app->createFactoryBufferAdjustment([
                'adjustment_type' => \post_string('adjustment_type'),
                'aviz_number' => \post_string('aviz_number'),
                'aviz_date' => \post_string('aviz_date'),
                'reception_date' => \post_string('reception_date'),
                'qty_kg' => \post_string('qty_kg'),
                'notes' => \post_string('notes'),
            ], $user['id']);
            \flash('Avizul de buffer fabrica a fost inregistrat.');
            \redirect('factory_buffer');
        }

        if ($action === 'create_purchase') {
            $assignedStore = $app->userPrimaryStore($user['id']);
            if (!$assignedStore) {
                throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
            }
            $app->createPurchaseLot([
                'supplier_name' => \post_string('supplier_name'),
                'supplier_type' => \post_string('supplier_type'),
                'supplier_phone' => \post_string('supplier_phone'),
                'supplier_identifier' => \post_string('supplier_identifier'),
                'supplier_cui' => \post_string('supplier_cui'),
                'supplier_address' => \post_string('supplier_address'),
                'supplier_county_code' => \post_string('supplier_county_code'),
                'supplier_county_name' => \post_string('supplier_county_name'),
                'supplier_locality_siruta' => \post_int('supplier_locality_siruta'),
                'supplier_locality_name' => \post_string('supplier_locality_name'),
                'supplier_postal_code' => \post_string('supplier_postal_code'),
                'purchase_date' => \post_string('purchase_date'),
                'document_series' => \post_string('document_series'),
                'document_number' => \post_string('document_number'),
                'document_position' => \post_string('document_position'),
                'document_date' => \post_string('document_date'),
                'gross_kg' => \post_string('gross_kg'),
                'shrinkage_pct' => \post_string('shrinkage_pct'),
                'purchase_price' => \post_string('purchase_price'),
                'store_id' => (int) $assignedStore['id'],
            ], $user['id']);
            \flash('Achizitia a fost creata.');
            \redirect('purchases');
        }

        if ($action === 'advance_purchase') {
            $app->advancePurchaseLot(\post_int('lot_id'), $user['id']);
            \flash('Achizitia a avansat in flux.');
            \redirect('purchases');
        }

        if ($action === 'purchase_wax_exit') {
            $assignedStore = $app->userPrimaryStore($user['id']);
            if (!$assignedStore) {
                throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
            }
            $app->createPurchaseWaxExit([
                'partner_name' => \post_string('partner_name'),
                'partner_identifier' => \post_string('partner_identifier'),
                'document_type' => \post_string('document_type'),
                'document_series' => \post_string('document_series'),
                'document_number' => \post_string('document_number'),
                'document_date' => \post_string('document_date'),
                'qty_kg' => \post_string('qty_kg'),
                'notes' => \post_string('notes'),
                'store_id' => (int) $assignedStore['id'],
            ], $user['id']);
            \flash('Iesirea de ceara achizitionata a fost salvata.');
            \redirect('purchase_exit');
        }

        if ($action === 'change_password') {
            $app->changeOwnPassword(
                $user['id'],
                \post_string('new_password'),
                \post_string('confirm_password')
            );
            \flash('Parola a fost schimbata.');
            \redirect('settings', ['settings_tab' => 'password']);
        }

        if ($action === 'save_role_permissions') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate edita rolurile.');
            }
            $app->saveRolePermissions($_POST['permissions'] ?? [], $user['id']);
            \flash('Permisiunile rolurilor au fost salvate.');
            \redirect('settings', ['settings_tab' => 'roles']);
        }

        if ($action === 'create_user') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate crea utilizatori.');
            }
            $app->createUser([
                'username' => \post_string('username'),
                'full_name' => \post_string('full_name'),
                'password' => \post_string('password'),
                'role' => \post_string('role'),
                'active' => isset($_POST['active']),
                'store_id' => \post_int('store_id'),
            ], $user['id']);
            \flash('Utilizatorul a fost creat.');
            \redirect('settings', ['settings_tab' => 'users']);
        }

        if ($action === 'update_user') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate edita utilizatori.');
            }
            $app->updateUser([
                'id' => \post_int('user_id'),
                'full_name' => \post_string('full_name'),
                'password' => \post_string('password'),
                'role' => \post_string('role'),
                'active' => isset($_POST['active']),
                'store_id' => \post_int('store_id'),
            ], $user['id']);
            \flash('Utilizatorul a fost actualizat.');
            \redirect('settings', ['settings_tab' => 'users']);
        }

        if ($action === 'save_store') {
            if (!$app->roleHasPermission($user['role'], 'STORE_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi gestiuni.');
            }
            $app->saveStore([
                'id' => \post_int('store_id'),
                'code' => \post_string('store_code'),
                'name' => \post_string('store_name'),
                'address' => \post_string('store_address'),
                'fgo_series' => \post_string('store_fgo_series'),
                'processor_id' => \post_int('store_processor_id'),
                'processing_shrinkage_pct' => \post_string('store_processing_shrinkage_pct'),
                'processing_price' => \post_string('store_processing_price'),
                'purchase_shrinkage_pct' => \post_string('store_purchase_shrinkage_pct'),
                'purchase_price' => \post_string('store_purchase_price'),
            ], $user['id']);
            \flash('Gestiunea a fost salvata.');
            \redirect('settings', ['settings_tab' => 'stores']);
        }

        if ($action === 'save_processor') {
            if (!$app->roleHasPermission($user['role'], 'PROCESSOR_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi procesatori.');
            }
            $app->saveProcessor([
                'id' => \post_int('processor_id'),
                'name' => \post_string('processor_name'),
                'cui' => \post_string('processor_cui'),
                'address' => \post_string('processor_address'),
                'processing_price' => \post_string('processing_price'),
                'exchange_shrinkage_pct' => \post_string('exchange_shrinkage_pct'),
            ], $user['id']);
            \flash('Procesatorul a fost salvat.');
            \redirect('settings', ['settings_tab' => 'processors']);
        }

        if ($action === 'save_document_templates') {
            if (!$app->roleHasPermission($user['role'], 'DOCUMENT_TEMPLATE_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi template-uri de documente.');
            }
            $app->saveDocumentTemplates($_POST['templates'] ?? [], $user['id']);
            \flash('Template-urile de documente au fost salvate.');
            \redirect('settings', ['settings_tab' => 'document_templates']);
        }

        if ($action === 'save_document_series') {
            if (!$app->roleHasPermission($user['role'], 'STORE_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi seriile de documente.');
            }
            $app->saveDocumentSeries($_POST['series'] ?? [], $user['id']);
            \flash('Seriile documentelor au fost salvate.');
            \redirect('settings', ['settings_tab' => 'document_series']);
        }

        if ($action === 'save_company_settings') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate edita datele societatii.');
            }
            $app->saveCompanySettings([
                'company_name' => \post_string('company_name'),
                'vat_number' => \post_string('vat_number'),
                'registry_number' => \post_string('registry_number'),
                'address' => \post_string('address'),
                'phone' => \post_string('phone'),
                'email' => \post_string('email'),
                'fgo_url' => \post_string('fgo_url'),
                'fgo_token' => \post_string('fgo_token'),
            ], $user['id']);
            \flash('Datele societatii au fost salvate.');
            \redirect('settings', ['settings_tab' => 'company']);
        }

        if ($action === 'create_database_backup') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate genera backup-uri SQL.');
            }
            $result = $app->createDatabaseBackup($user['id']);
            \flash('Backup SQL generat: ' . $result['file_name']);
            \redirect('settings', ['settings_tab' => 'environment']);
        }

        if ($action === 'import_database_backup') {
            if (!\is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate importa backup-uri SQL.');
            }
            $result = $app->importDatabaseBackup($_FILES['database_backup_file'] ?? [], $user['id']);
            \flash('Importul SQL s-a terminat. Backup local automat: ' . $result['backup_file'] . '. Reintrodu manual FGO URL si FGO token din Date societate.', 'warning');
            \redirect('settings', ['settings_tab' => 'environment']);
        }

    }

    public function handleError(Throwable $error, string $page): void
    {
        \flash($error->getMessage(), 'error');
        if ($page === 'lot_detail' && \post_int('lot_id') > 0) {
            \redirect('lot_detail', ['lot_id' => \post_int('lot_id')]);
        }
        \redirect($page);
    }
}
