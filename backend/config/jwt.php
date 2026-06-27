<?php

declare(strict_types=1);

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'algo' => 'HS256',
    'ttl' => (int) env('JWT_TTL', 15),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 43200),
    'issuer' => env('APP_NAME', 'LiveCommerce'),
];
