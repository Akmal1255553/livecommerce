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
    | click|payme|uzum — use local adapter with that provider name until real SDKs
    |
    */
    'driver' => env('PAYMENT_GATEWAY', 'fake'),

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', env('PAYMENT_GATEWAY_SECRET', 'local-dev-webhook-secret')),

    'gateway_url' => env('PAYMENT_GATEWAY_URL'),

    'gateway_key' => env('PAYMENT_GATEWAY_KEY'),

    /*
    | Sandbox complete endpoint for mobile Pay/Cancel.
    | Defaults off in production unless PAYMENT_SANDBOX_ENABLED=true.
    */
    'sandbox_enabled' => filter_var(
        env('PAYMENT_SANDBOX_ENABLED', env('APP_ENV', 'production') !== 'production'),
        FILTER_VALIDATE_BOOL,
    ),
];
