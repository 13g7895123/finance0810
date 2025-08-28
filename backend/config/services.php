<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'line' => [
        'channel_access_token' => env('LINE_BOT_CHANNEL_ACCESS_TOKEN') ?: env('LINE_CHANNEL_ACCESS_TOKEN'),
        'channel_secret' => env('LINE_BOT_CHANNEL_SECRET') ?: env('LINE_CHANNEL_SECRET'),
        'bot_basic_id' => env('LINE_BOT_BASIC_ID'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')),
        'project_id' => env('FIREBASE_PROJECT_ID', 'finance0810new'), // Unified to finance0810new project
        'database_url' => env('FIREBASE_DATABASE_URL', 'https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/'), // Unified to finance0810new project
        
        // Feature toggles
        'enabled' => env('FIREBASE_ENABLED', true),
        'sync_enabled' => env('FIREBASE_SYNC_ENABLED', true),
        'realtime_enabled' => env('FIREBASE_REALTIME_ENABLED', true),
        
        // Debug & monitoring
        'debug_mode' => env('FIREBASE_DEBUG_MODE', true),
        'log_level' => env('FIREBASE_LOG_LEVEL', 'info'),
        'batch_sync_limit' => env('FIREBASE_BATCH_SYNC_LIMIT', 100),
        'health_check_interval' => env('FIREBASE_HEALTH_CHECK_INTERVAL', 300),
    ],

];