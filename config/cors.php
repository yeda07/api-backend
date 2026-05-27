<?php

$defaultAllowedOrigins = implode(',', [
    'https://vende-mas.com.co',
    'https://www.vende-mas.com.co',
    'https://norion.vercel.app',
    'http://localhost:3000',
    'http://localhost:5173',
]);

$configuredOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', ''));

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', $configuredOrigins !== '' ? $configuredOrigins : $defaultAllowedOrigins)
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
