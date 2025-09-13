<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://finance.local',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3301',
        'http://127.0.0.1:3301',
        'http://localhost:9121',
        'http://127.0.0.1:9121',
        'http://localhost:9221',
        'http://127.0.0.1:9221',
        'https://dev-finance.mercylife.cc',
        'https://finance.mercylife.cc',
    ],

    'allowed_origins_patterns' => [
        '/^http:\/\/localhost(:[0-9]+)?$/',
        '/^http:\/\/127\.0\.0\.1(:[0-9]+)?$/',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN'
    ],

    'exposed_headers' => [
        'Authorization',
        'Content-Disposition'
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];