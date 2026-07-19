<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Payment gateway driver
    |--------------------------------------------------------------------------
    |
    | fake  — instant success (tests / local without redirect). Forbidden in production.
    | local — sandbox redirect + HMAC webhooks (Sprint 7 MVP)
    | click|payme|uzum — real provider adapters (sandbox URL when credentials empty)
    |
    */
    'driver' => env('PAYMENT_GATEWAY', 'fake'),

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', env('PAYMENT_GATEWAY_SECRET', 'local-dev-webhook-secret')),

    'gateway_url' => env('PAYMENT_GATEWAY_URL'),

    'gateway_key' => env('PAYMENT_GATEWAY_KEY'),

    'click' => [
        'merchant_id' => env('CLICK_MERCHANT_ID', ''),
        'service_id' => env('CLICK_SERVICE_ID', ''),
        'merchant_user_id' => env('CLICK_MERCHANT_USER_ID', ''),
        'secret' => env('CLICK_SECRET', ''),
        'return_url' => env('CLICK_RETURN_URL'),
    ],

    'payme' => [
        'merchant_id' => env('PAYME_MERCHANT_ID', ''),
        'secret' => env('PAYME_SECRET', ''),
    ],

    'uzum' => [
        'merchant_id' => env('UZUM_MERCHANT_ID', ''),
        'secret' => env('UZUM_SECRET', ''),
        'checkout_url' => env('UZUM_CHECKOUT_URL', 'https://checkout.uzumbank.uz'),
        'return_url' => env('UZUM_RETURN_URL'),
    ],

    /*
    | Sandbox complete endpoint for mobile Pay/Cancel.
    | Defaults off in production unless PAYMENT_SANDBOX_ENABLED=true.
    */
    'sandbox_enabled' => filter_var(
        env('PAYMENT_SANDBOX_ENABLED', env('APP_ENV', 'production') !== 'production'),
        FILTER_VALIDATE_BOOL,
    ),
];
