<?php

declare(strict_types=1);

return [
    'video_max_products' => (int) env('COMMERCE_VIDEO_MAX_PRODUCTS', 20),
    'guest_cart_ttl_days' => (int) env('COMMERCE_GUEST_CART_TTL_DAYS', 30),
    'shipping_flat_rate_uzs' => (int) env('COMMERCE_SHIPPING_FLAT_RATE_UZS', 0),
    'inventory_reservation_ttl_minutes' => (int) env('COMMERCE_INVENTORY_RESERVATION_TTL_MINUTES', 15),
    'cart_max_quantity_per_line' => (int) env('COMMERCE_CART_MAX_QUANTITY_PER_LINE', 99),
];
