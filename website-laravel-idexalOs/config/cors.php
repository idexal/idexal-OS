<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | The desktop app calls the public API from an Electron renderer, whose
    | origin can be `file://`, `null`, or a localhost development URL. Reflect
    | the requesting origin so credentials-aware fetches keep working.
    |
    */

    'paths' => [
        'api/*',
        'v1/*',
        'webhooks/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Request-Id',
        'X-Idexal-Request-Id',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 3600,

    'supports_credentials' => true,

];
