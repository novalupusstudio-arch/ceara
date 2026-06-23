<?php

declare(strict_types=1);

session_start();

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

require __DIR__ . '/lib/autoload.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/App.php';
require __DIR__ . '/lib/Http/PostActionDispatcher.php';
require __DIR__ . '/lib/ProcessingService.php';

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
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }

    $pdf = $app->documentPdfById((int) $doc['id']);
    if ($pdf !== null) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($doc['series'] . '-' . str_pad((string) max(1, (int) $doc['number']), 4, '0', STR_PAD_LEFT))) . '.pdf"');
        echo $pdf;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "Mock document PDF\n";
    echo $doc['series'] . '-' . str_pad((string) max(1, (int) $doc['number']), 4, '0', STR_PAD_LEFT) . "\n";
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
            (new \Ceara\Http\PostActionDispatcher())->handleLogin($app, $auth);
        }

        require_login();
        $user = current_user();
        (new \Ceara\Http\PostActionDispatcher())->handleAuthenticated($app, $auth, $user, $action);
    } catch (Throwable $error) {
        (new \Ceara\Http\PostActionDispatcher())->handleError($error, $page);
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
$purchasePages = ['purchases', 'purchase_register', 'purchase_exit'];
$allowed = $activeFlow === 'processing'
    ? array_merge($basePages, $processingPages)
    : ($activeFlow === 'purchase' ? array_merge($basePages, $purchasePages) : $basePages);

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
        'purchases' => [
            'lots' => $app->purchaseLots(),
            'assigned_store' => $app->userPrimaryStore(current_user()['id']),
            'company_settings' => $app->companySettings(),
        ],
        'purchase_register' => $app->purchaseRegisterData(
            current_user()['id'],
            (string) ($_GET['date_start'] ?? ''),
            (string) ($_GET['date_end'] ?? '')
        ),
        'purchase_exit' => $app->purchaseExitData(current_user()['id']),
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
