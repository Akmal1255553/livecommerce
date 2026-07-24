<?php

declare(strict_types=1);

return [
    'currency' => env('WALLET_CURRENCY', 'UZS'),

    /*
    | Amounts are in minor units, same convention as orders.total.
    */
    'top_up' => [
        'min' => (int) env('WALLET_TOPUP_MIN', 10000),
        'max' => (int) env('WALLET_TOPUP_MAX', 50000000),
    ],

    'withdrawal' => [
        'min' => (int) env('WALLET_WITHDRAWAL_MIN', 50000),
        'max' => (int) env('WALLET_WITHDRAWAL_MAX', 20000000),
        // Percent of the requested amount, charged on top of it.
        'fee_percent' => (float) env('WALLET_WITHDRAWAL_FEE_PERCENT', 1.0),
        'max_pending' => (int) env('WALLET_WITHDRAWAL_MAX_PENDING', 3),
    ],

    /*
    | Sandbox endpoints let the mobile app drive top-up / payout confirmation
    | before real provider webhooks exist. Mirrors payment.sandbox_enabled.
    */
    'sandbox_enabled' => filter_var(
        env('WALLET_SANDBOX_ENABLED', env('PAYMENT_SANDBOX_ENABLED', env('APP_ENV', 'production') !== 'production')),
        FILTER_VALIDATE_BOOL,
    ),
];
