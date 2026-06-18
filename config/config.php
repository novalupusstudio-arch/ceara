<?php

$config = [
    'app_name' => 'Ceara',
    'app_version' => '1.0.020',
    'seed_defaults' => getenv('CEARA_SEED_DEFAULTS') !== '0',
    'db' => [
        'host' => getenv('CEARA_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('CEARA_DB_PORT') ?: '3306',
        'name' => getenv('CEARA_DB_NAME') ?: 'ceara',
        'user' => getenv('CEARA_DB_USER') ?: 'root',
        'pass' => getenv('CEARA_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'fgo' => [
        'enabled' => false,
        'environment' => 'test',
        'base_url' => 'https://api-testuat.fgo.ro/v1',
        'cod_unic' => '',
        'private_key' => '',
        'platforma_url' => '',
        'serie' => '',
        'vat_rate' => 21,
        'article_name' => 'Servicii procesare ceara',
        'article_um' => 'BUC',
        'default_country' => 'RO',
        'default_county' => 'Bacau',
        'default_locality' => 'Onesti',
    ],
    'fiscalwire' => [
        'enabled' => true,
        'export_dir' => dirname(__DIR__) . '/storage/fiscalwire-out',
        'vat_rate' => 21,
        'vat_code' => '1',
        'department' => 1,
        'group' => 1,
        'logical_printer' => 1,
        'extension' => 'inp',
        'article_name' => 'Servicii procesare',
    ],
];

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

$fgoLocalConfig = __DIR__ . '/fgo.local.php';
if (is_file($fgoLocalConfig)) {
    $config = array_replace_recursive($config, require $fgoLocalConfig);
}

return $config;
