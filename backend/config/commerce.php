<?php

declare(strict_types=1);

return [
    'video_max_products' => (int) env('COMMERCE_VIDEO_MAX_PRODUCTS', 20),
    'guest_cart_ttl_days' => (int) env('COMMERCE_GUEST_CART_TTL_DAYS', 30),
    'shipping_flat_rate_uzs' => (int) env('COMMERCE_SHIPPING_FLAT_RATE_UZS', 0),
    'inventory_reservation_ttl_minutes' => (int) env('COMMERCE_INVENTORY_RESERVATION_TTL_MINUTES', 15),
    'cart_max_quantity_per_line' => (int) env('COMMERCE_CART_MAX_QUANTITY_PER_LINE', 99),
    'order_number_prefix' => env('COMMERCE_ORDER_NUMBER_PREFIX', 'LC'),
    'order_number' => [
        'strategy' => env('COMMERCE_ORDER_NUMBER_STRATEGY', 'date_sequence'),
    ],
    'refund_window_days' => (int) env('COMMERCE_REFUND_WINDOW_DAYS', 14),
    'checkout_idempotency_ttl_hours' => (int) env('COMMERCE_CHECKOUT_IDEMPOTENCY_TTL_HOURS', 24),
];
