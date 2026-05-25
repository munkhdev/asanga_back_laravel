<?php

declare(strict_types=1);

function normalizeOrigin(string $value): string
{
    return rtrim(trim($value), '/');
}

function withWwwVariants(string $origin): array
{
    $origin = normalizeOrigin($origin);
    if ($origin === '') {
        return [];
    }

    $parts = parse_url($origin);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return [$origin];
    }

    $scheme = (string) $parts['scheme'];
    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    $base = $scheme . '://' . $host . $port;
    if (str_starts_with($host, 'www.')) {
        $withoutWww = substr($host, 4);
        return array_values(array_unique([
            $base,
            $scheme . '://' . $withoutWww . $port,
        ]));
    }

    return array_values(array_unique([
        $base,
        $scheme . '://www.' . $host . $port,
    ]));
}

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $v): string => normalizeOrigin($v),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('CORS_ORIGINS', '')))
)));

if ($allowedOrigins === []) {
    $fallback = array_filter([
        trim((string) env('FRONTEND_URL', '')),
        trim((string) env('ADMIN_URL', '')),
    ]);

    $allowedOrigins = array_values(array_unique($fallback));
}

$allowedOrigins = array_values(array_unique(array_merge(
    [],
    ...array_map(static fn (string $origin): array => withWwwVariants($origin), $allowedOrigins)
)));

return [
    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
