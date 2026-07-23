<?php

declare(strict_types=1);

return [
    'cache' => [
        /** Active category tree TTL (seconds). */
        'categories_tree_ttl' => (int) env('CATALOG_CATEGORIES_TREE_TTL', 600),
        /** Public user profile TTL (seconds). */
        'profile_ttl' => (int) env('USER_PROFILE_CACHE_TTL', 300),
    ],
];
