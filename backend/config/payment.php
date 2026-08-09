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

    'bitcoin' => [
        'api_key' => env('BITCOIN_API_KEY', ''),
        'api_url' => env('BITCOIN_API_URL', 'https://api.nowpayments.io/v1'),
        'ipn_secret' => env('BITCOIN_IPN_SECRET', ''),
        'ipn_callback_url' => env('BITCOIN_IPN_CALLBACK_URL'),
        'sandbox_rate_uzs_per_btc' => (float) env('BITCOIN_SANDBOX_RATE_UZS', 1_200_000_000),
        // Fallback when CoinGecko has no UZS pair — used to price NOWPayments invoices in USD.
        'uzs_per_usd' => (float) env('BITCOIN_UZS_PER_USD', 12_500),
        'sandbox_address' => env('BITCOIN_SANDBOX_ADDRESS', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'),
        'invoice_ttl_minutes' => (int) env('BITCOIN_INVOICE_TTL_MINUTES', 30),
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
