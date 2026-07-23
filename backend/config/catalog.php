<?php

declare(strict_types=1);

return [
    'cache' => [
        /** Active category tree TTL (seconds). */
        'categories_tree_ttl' => (int) env('CATALOG_CATEGORIES_TREE_TTL', 600),
    ],
];
