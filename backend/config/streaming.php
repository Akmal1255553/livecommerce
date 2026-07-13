<?php

declare(strict_types=1);

return [
    'provider' => env('STREAMING_PROVIDER', 'fake'),
    'max_pinned_products' => 3,

    'agora' => [
        'app_id' => env('AGORA_APP_ID', ''),
        'app_certificate' => env('AGORA_APP_CERTIFICATE', ''),
    ],
];
