<?php

$config = [
    'app_name' => 'Ceara',
    'app_version' => '1.1.006',
    'seed_defaults' => getenv('CEARA_SEED_DEFAULTS') !== '0',
    'db' => [
        'host' => getenv('CEARA_DB_HOST') ?: '',
        'port' => getenv('CEARA_DB_PORT') ?: '',
        'name' => getenv('CEARA_DB_NAME') ?: '',
        'user' => getenv('CEARA_DB_USER') ?: '',
        'pass' => getenv('CEARA_DB_PASS') !== false ? getenv('CEARA_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
];

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
