<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', ''),
        env('ADMIN_DASHBOARD_URL', ''),
    ]),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https?://.*\.vercel\.app$#',
        '#^https?://.*\.railway\.app$#',
        '#^https?://ofroo.*$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-Token',
        'X-App-Locale',
    ],

    'exposed_headers' => [
        'X-Total-Count',
        'X-Page-Count',
    ],

    'max_age' => 86400,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),
];
