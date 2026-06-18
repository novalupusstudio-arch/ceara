<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/FgoClient.php';
require __DIR__ . '/lib/FiscalWireExporter.php';
require __DIR__ . '/lib/App.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

$config = require __DIR__ . '/config/config.php';
$db = new Database($config);

try {
    $db->ensureDatabase();
    $db->migrateAndSeed();
    $pdo = $db->pdo();
} catch (Throwable $error) {
    http_response_code(500);
    $setupError = $error->getMessage();
    require __DIR__ . '/views/setup-error.php';
    exit;
}

$auth = new Auth($pdo);
$app = new App($pdo, $config);
$page = $_GET['page'] ?? 'dashboard';
$activeFlow = $_SESSION['active_flow'] ?? '';

if ($page === 'customer_lookup') {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'customers' => $app->searchCustomers((string) ($_GET['customer_type'] ?? 'PF'), (string) ($_GET['term'] ?? '')),
    ]);
    exit;
}

if ($page === 'counties_lookup') {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['counties' => $app->sirutaCounties()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($page === 'localities_lookup') {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'localities' => $app->sirutaLocalities(
            (string) ($_GET['county_code'] ?? ''),
            (string) ($_GET['term'] ?? '')
        ),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($page === 'anaf_company_lookup') {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode(['company' => $app->lookupAnafCompany((string) ($_GET['cui'] ?? ''))], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        http_response_code(422);
        echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($page === 'document_mock') {
    require_login();
    $doc = $app->documentById((int) ($_GET['document_id'] ?? 0));
    if (!$doc) {
        http_response_code(404);
        echo 'Documentul nu exista.';
        exit;
    }

    if (!empty($doc['external_url'])) {
        header('Location: ' . $doc['external_url']);
        exit;
    }

    if ($doc['document_type'] === 'BON' && !empty($doc['file_path'])) {
        $path = __DIR__ . '/storage/' . ltrim(str_replace('\\', '/', (string) $doc['file_path']), '/');
        if (is_file($path)) {
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . basename($path) . '"');
            readfile($path);
            exit;
        }
    }

    $pdf = $app->documentPdfById((int) $doc['id']);
    if ($pdf !== null) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($doc['document_type'] . '-' . $doc['series'] . '-' . $doc['number'])) . '.pdf"');
        echo $pdf;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "Mock document PDF\n";
    echo $doc['document_type'] . ' ' . $doc['series'] . '-' . $doc['number'] . "\n";
    echo 'Status: ' . $doc['status'] . "\n";
    echo 'Generarea PDF va fi definita ulterior.';
    exit;
}

if ($page === 'document_template_preview') {
    require_login();
    $user = current_user();
    if (!$app->roleHasPermission($user['role'], 'DOCUMENT_TEMPLATE_MANAGE')) {
        http_response_code(403);
        echo 'Nu ai dreptul sa previzualizezi template-uri de documente.';
        exit;
    }

    $html = $app->documentPreviewByTemplateId((int) ($_GET['template_id'] ?? 0));
    if ($html === null) {
        http_response_code(404);
        echo 'Template-ul nu exista.';
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            if ($auth->login(post_string('username'), post_string('password'))) {
                redirect('dashboard');
            }
            flash('Utilizator sau parola invalida.', 'error');
            redirect('login');
        }

        require_login();
        $user = current_user();

        if ($action === 'logout') {
            $auth->logout();
            redirect('login');
        }

        if ($action === 'select_flow') {
            $flow = post_string('flow');
            if ($flow !== 'processing') {
                flash('Fluxul de achizitie va fi reconstruit separat.', 'error');
                redirect('dashboard');
            }

            $_SESSION['active_flow'] = 'processing';
            flash('Fluxul de procesare a fost activat.');
            redirect('dashboard');
        }

        if ($action === 'create_processing') {
            $assignedStore = $app->userPrimaryStore($user['id']);
            if (!$assignedStore) {
                throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
            }

            $app->createProcessingLot([
                'customer_name' => post_string('customer_name'),
                'customer_phone' => post_string('customer_phone'),
                'customer_address' => post_string('customer_address'),
                'customer_identifier' => post_string('customer_identifier'),
                'customer_type' => post_string('customer_type', 'PF'),
                'customer_name_pj' => post_string('customer_name_pj'),
                'customer_phone_pj' => post_string('customer_phone_pj'),
                'customer_address_pj' => post_string('customer_address_pj'),
                'customer_cui' => post_string('customer_cui'),
                'customer_representative' => post_string('customer_representative'),
                'customer_county_code' => post_string('customer_county_code'),
                'customer_county_name' => post_string('customer_county_name'),
                'customer_locality_siruta' => post_int('customer_locality_siruta'),
                'customer_locality_name' => post_string('customer_locality_name'),
                'customer_postal_code' => post_string('customer_postal_code'),
                'customer_registry_number' => post_string('customer_registry_number'),
                'customer_legal_form' => post_string('customer_legal_form'),
                'customer_vat_status' => post_string('customer_vat_status'),
                'customer_external_source' => post_string('customer_external_source'),
                'customer_external_checked_at' => post_string('customer_external_checked_at'),
                'existing_customer_id' => post_int('existing_customer_id'),
                'force_new_customer' => post_int('force_new_customer'),
                'known_customer' => post_int('known_customer'),
                'gross_kg' => post_string('gross_kg'),
                'store_id' => (int) $assignedStore['id'],
                'processor_id' => post_int('processor_id'),
            ], $user['id']);
            flash('Lotul de procesare a fost creat.');
            redirect('lots');
        }

        if ($action === 'transition_processing') {
            $app->transitionProcessingLot(post_int('lot_id'), post_string('transition'), $user['id']);
            flash('Statusul lotului a fost actualizat.');
            redirect('lots');
        }

        if ($action === 'processing_document') {
            $documentId = $app->ensureProcessingDocument(
                post_int('lot_id'),
                post_string('document_type'),
                $user['id'],
                post_int('movement_id'),
                post_string('payment_method', 'cash')
            );
            redirect('document_mock', ['document_id' => $documentId]);
        }

        if ($action === 'processing_exchange') {
            $app->createProcessingExchange(post_int('lot_id'), post_string('exchange_kg'), $user['id']);
            flash('Schimbul de ceara a fost inregistrat.');
            redirect('lot_detail', ['lot_id' => post_int('lot_id')]);
        }

        if ($action === 'processing_return') {
            $app->createProcessingReturn(post_int('lot_id'), post_string('return_kg'), post_string('return_notes'), $user['id']);
            flash('Returul de ceara a fost inregistrat.');
            redirect('lot_detail', ['lot_id' => post_int('lot_id')]);
        }

        if ($action === 'create_factory_batch') {
            $app->createFactoryBatch([
                'processor_id' => post_int('processor_id'),
                'lot_qty' => $_POST['lot_qty'] ?? [],
                'reject_qty' => $_POST['reject_qty'] ?? [],
            ], $user['id']);
            flash('Predarea catre fabrica a fost salvata.');
            redirect('factory_delivery', ['processor_id' => post_int('processor_id')]);
        }

        if ($action === 'factory_buffer_adjustment') {
            $app->createFactoryBufferAdjustment([
                'adjustment_type' => post_string('adjustment_type'),
                'aviz_number' => post_string('aviz_number'),
                'qty_kg' => post_string('qty_kg'),
                'store_id' => post_int('store_id'),
                'notes' => post_string('notes'),
            ], $user['id']);
            flash('Avizul de buffer fabrica a fost inregistrat.');
            redirect('factory_buffer');
        }

        if ($action === 'create_purchase') {
            $app->createPurchaseLot([
                'supplier_name' => post_string('supplier_name'),
                'supplier_type' => post_string('supplier_type'),
                'supplier_cui' => post_string('supplier_cui'),
                'gross_kg' => post_string('gross_kg'),
                'shrinkage_pct' => post_string('shrinkage_pct'),
                'store_id' => post_int('store_id'),
                'processor_id' => post_int('processor_id'),
            ], $user['id']);
            flash('Achizitia a fost creata.');
            redirect('purchases');
        }

        if ($action === 'advance_purchase') {
            $app->advancePurchaseLot(post_int('lot_id'), $user['id']);
            flash('Achizitia a avansat in flux.');
            redirect('purchases');
        }

        if ($action === 'change_password') {
            $app->changeOwnPassword(
                $user['id'],
                post_string('new_password'),
                post_string('confirm_password')
            );
            flash('Parola a fost schimbata.');
            redirect('settings', ['settings_tab' => 'password']);
        }

        if ($action === 'save_role_permissions') {
            if (!is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate edita rolurile.');
            }
            $app->saveRolePermissions($_POST['permissions'] ?? [], $user['id']);
            flash('Permisiunile rolurilor au fost salvate.');
            redirect('settings', ['settings_tab' => 'roles']);
        }

        if ($action === 'create_user') {
            if (!is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate crea utilizatori.');
            }
            $app->createUser([
                'username' => post_string('username'),
                'full_name' => post_string('full_name'),
                'password' => post_string('password'),
                'role' => post_string('role'),
                'active' => isset($_POST['active']),
                'store_ids' => $_POST['store_ids'] ?? [],
            ], $user['id']);
            flash('Utilizatorul a fost creat.');
            redirect('settings', ['settings_tab' => 'users']);
        }

        if ($action === 'save_store') {
            if (!$app->roleHasPermission($user['role'], 'STORE_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi gestiuni.');
            }
            $app->saveStore([
                'id' => post_int('store_id'),
                'code' => post_string('store_code'),
                'name' => post_string('store_name'),
                'address' => post_string('store_address'),
                'processor_id' => post_int('store_processor_id'),
            ], $user['id']);
            flash('Gestiunea a fost salvata.');
            redirect('settings', ['settings_tab' => 'stores']);
        }

        if ($action === 'save_processor') {
            if (!$app->roleHasPermission($user['role'], 'PROCESSOR_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi procesatori.');
            }
            $app->saveProcessor([
                'id' => post_int('processor_id'),
                'name' => post_string('processor_name'),
                'cui' => post_string('processor_cui'),
                'address' => post_string('processor_address'),
                'processing_price' => post_string('processing_price'),
                'exchange_shrinkage_pct' => post_string('exchange_shrinkage_pct'),
                'purchase_shrinkage_pct' => post_string('purchase_shrinkage_pct'),
            ], $user['id']);
            flash('Procesatorul a fost salvat.');
            redirect('settings', ['settings_tab' => 'processors']);
        }

        if ($action === 'save_document_templates') {
            if (!$app->roleHasPermission($user['role'], 'DOCUMENT_TEMPLATE_MANAGE')) {
                throw new RuntimeException('Nu ai dreptul sa administrezi template-uri de documente.');
            }
            $app->saveDocumentTemplates($_POST['templates'] ?? [], $user['id']);
            flash('Template-urile de documente au fost salvate.');
            redirect('settings', ['settings_tab' => 'document_templates']);
        }

        if ($action === 'save_company_settings') {
            if (!is_initial_admin()) {
                throw new RuntimeException('Doar adminul initial poate edita datele societatii.');
            }
            $app->saveCompanySettings([
                'company_name' => post_string('company_name'),
                'vat_number' => post_string('vat_number'),
                'registry_number' => post_string('registry_number'),
                'address' => post_string('address'),
                'fgo_private_key' => post_string('fgo_private_key'),
            ], $user['id']);
            flash('Datele societatii au fost salvate.');
            redirect('settings', ['settings_tab' => 'company']);
        }

        if ($action === 'save_settings') {
            $app->saveSettings([
                'store_id' => post_int('store_id'),
                'store_code' => post_string('store_code'),
                'store_name' => post_string('store_name'),
                'store_address' => post_string('store_address'),
                'store_processor_id' => post_int('store_processor_id'),
                'processor_id' => post_int('processor_id'),
                'processor_name' => post_string('processor_name'),
                'processor_cui' => post_string('processor_cui'),
                'processor_contact' => post_string('processor_contact'),
                'processing_price' => post_string('processing_price'),
                'exchange_shrinkage_pct' => post_string('exchange_shrinkage_pct'),
                'purchase_shrinkage_pct' => post_string('purchase_shrinkage_pct'),
                'series' => $_POST['series'] ?? [],
            ], $user['id']);
            flash('Setarile au fost salvate.');
            redirect('settings');
        }
    } catch (Throwable $error) {
        flash($error->getMessage(), 'error');
        redirect($page);
    }
}

if ($page === 'login') {
    require __DIR__ . '/views/login.php';
    exit;
}

require_login();

$activeFlow = $_SESSION['active_flow'] ?? '';
$basePages = ['dashboard', 'documents', 'reports', 'settings', 'audit'];
$processingPages = ['processing', 'lots', 'lot_detail', 'factory_delivery', 'factory_buffer', 'processing_register'];
$allowed = $activeFlow === 'processing'
    ? array_merge($basePages, $processingPages)
    : $basePages;

if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

if ($page === 'lot_detail' && (int) ($_GET['lot_id'] ?? 0) <= 0) {
    flash('Lotul nu a fost gasit.', 'error');
    redirect('lots');
}

try {
    $data = match ($page) {
        'dashboard' => array_merge($app->dashboard(), ['active_flow' => $activeFlow]),
        'processing' => [
            'processors' => $app->processors(),
            'assigned_store' => $app->userPrimaryStore(current_user()['id']),
            'default_processor' => $app->defaultProcessorForUser(current_user()['id']),
        ],
        'lots' => $app->processingLotsBoard((array) ($_GET['status'] ?? [])),
        'lot_detail' => $app->processingLotDetail((int) ($_GET['lot_id'] ?? 0)),
        'factory_delivery' => $app->factoryDeliveryData((int) ($_GET['processor_id'] ?? 0)),
        'factory_buffer' => $app->factoryBufferData(),
        'processing_register' => $app->processingRegisterData(
            current_user()['id'],
            (string) ($_GET['date_start'] ?? ''),
            (string) ($_GET['date_end'] ?? '')
        ),
        'documents' => ['documents' => $app->documents()],
        'reports' => ['dashboard' => $app->dashboard(), 'processing' => $app->processingLots(), 'purchases' => $app->purchaseLots()],
        'settings' => $app->settings(),
        'audit' => ['entries' => $app->audit()],
    };
} catch (RuntimeException $error) {
    flash($error->getMessage(), 'error');
    redirect($page === 'lot_detail' ? 'lots' : 'dashboard');
}

require __DIR__ . '/views/layout.php';
