<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/App.php';

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
$app = new App($pdo);
$page = $_GET['page'] ?? 'dashboard';

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

        if ($action === 'create_processing') {
            $app->createProcessingLot([
                'customer_name' => post_string('customer_name'),
                'customer_phone' => post_string('customer_phone'),
                'known_customer' => isset($_POST['known_customer']),
                'gross_kg' => post_string('gross_kg'),
                'shrinkage_pct' => post_string('shrinkage_pct'),
                'store_id' => post_int('store_id'),
                'processor_id' => post_int('processor_id'),
            ], $user['id']);
            flash('Lotul de procesare a fost creat.');
            redirect('processing');
        }

        if ($action === 'transition_processing') {
            $app->transitionProcessingLot(post_int('lot_id'), post_string('transition'), $user['id']);
            flash('Statusul lotului a fost actualizat.');
            redirect('processing');
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
                post_string('current_password'),
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
            $app->saveStore([
                'id' => post_int('store_id'),
                'code' => post_string('store_code'),
                'name' => post_string('store_name'),
                'address' => post_string('store_address'),
            ], $user['id']);
            flash('Gestiunea a fost salvata.');
            redirect('settings', ['settings_tab' => 'stores']);
        }

        if ($action === 'save_settings') {
            $app->saveSettings([
                'store_id' => post_int('store_id'),
                'store_code' => post_string('store_code'),
                'store_name' => post_string('store_name'),
                'store_address' => post_string('store_address'),
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

$allowed = ['dashboard', 'processing', 'purchases', 'documents', 'reports', 'settings', 'audit'];
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

$data = match ($page) {
    'dashboard' => $app->dashboard(),
    'processing' => ['lots' => $app->processingLots(), 'stores' => $app->stores(), 'processors' => $app->processors()],
    'purchases' => ['lots' => $app->purchaseLots(), 'stores' => $app->stores(), 'processors' => $app->processors()],
    'documents' => ['documents' => $app->documents()],
    'reports' => ['dashboard' => $app->dashboard(), 'processing' => $app->processingLots(), 'purchases' => $app->purchaseLots()],
    'settings' => $app->settings(),
    'audit' => ['entries' => $app->audit()],
};

require __DIR__ . '/views/layout.php';
