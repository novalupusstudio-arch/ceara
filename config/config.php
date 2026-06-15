<?php

return [
    'app_name' => 'Ceara',
    'db' => [
        'host' => getenv('CEARA_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('CEARA_DB_PORT') ?: '3306',
        'name' => getenv('CEARA_DB_NAME') ?: 'ceara',
        'user' => getenv('CEARA_DB_USER') ?: 'root',
        'pass' => getenv('CEARA_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];

