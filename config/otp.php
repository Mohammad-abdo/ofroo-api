<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP generation (testing)
    |--------------------------------------------------------------------------
    */
    'test_mode' => (bool) env('OTP_TEST_MODE', false),

    'test_code' => env('OTP_TEST_CODE', '123456'),

    /*
    |--------------------------------------------------------------------------
    | Phone OTP delivery: log | welniz | http
    |--------------------------------------------------------------------------
    | welniz: POST sendText (WhatsApp text per Welniz docs — not classic SMS).
    | http: configurable JSON request for your SMS provider (set SMS_HTTP_URL + body).
    */
    'phone_driver' => env('OTP_PHONE_DRIVER', 'log'),

    /*
    | If true, phone OTP is sent in the same HTTP request (no queue worker needed).
    | If false, ensure QUEUE_CONNECTION=sync OR run `php artisan queue:work`.
    */
    'phone_dispatch_sync' => filter_var(env('OTP_PHONE_DISPATCH_SYNC', false), FILTER_VALIDATE_BOOLEAN),

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 45),

    'welniz' => [
        'base_url' => rtrim(env('WELNIZ_BASE_URL', 'https://evo.welniz.org'), '/'),
        'instance' => env('WELNIZ_INSTANCE', 'Ofroo'),
        'api_key' => env('WELNIZ_API_KEY') ?: env('SMS_API_KEY'),
        'timeout' => (int) env('WELNIZ_TIMEOUT', 10),
        'link_preview' => filter_var(env('WELNIZ_LINK_PREVIEW', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'method' => strtoupper(env('SMS_HTTP_METHOD', 'POST')),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 15),
        'headers' => array_filter([
            'Content-Type' => 'application/json',
            'apikey' => env('SMS_HTTP_API_KEY') ?: env('SMS_API_KEY'),
            'Authorization' => env('SMS_HTTP_AUTHORIZATION') ?: null,
        ]),
        /*
         * Placeholders {{phone}} (digits only) and {{text}} (full SMS body).
         * Default matches Welniz-style JSON; override for your SMS API.
         */
        'body' => [
            'number' => '{{phone}}',
            'text' => '{{text}}',
        ],
    ],
];
