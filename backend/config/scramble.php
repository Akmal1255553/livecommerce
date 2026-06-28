<?php

declare(strict_types=1);
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    'api_path' => 'api',
    'api_domain' => null,
    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'LiveCommerce Platform REST API',
    ],

    'ui' => [
        'title' => 'LiveCommerce API',
        'theme' => 'light',
        'hide_try_it' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'include',
        'layout' => 'responsive',
    ],

    'servers' => null,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
