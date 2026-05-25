<?php

declare(strict_types=1);

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $v): string => trim($v),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('CORS_ORIGINS', '')))
)));

if ($allowedOrigins === []) {
    $fallback = array_filter([
        trim((string) env('FRONTEND_URL', '')),
        trim((string) env('ADMIN_URL', '')),
    ]);

    $allowedOrigins = array_values(array_unique($fallback));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
