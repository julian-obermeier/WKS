<?php
declare(strict_types=1);

use WKS\Core\Env;

return [
    'name' => 'WKS',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'base_path' => '/' . trim((string) Env::get('APP_BASE_PATH', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Berlin'),
    'key' => Env::get('APP_KEY', ''),
    'version' => trim((string) @file_get_contents(BASE_PATH . '/VERSION')),
];
