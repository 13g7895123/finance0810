<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debug System Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the debug system, including
    | debug panels, API endpoints, monitoring features, and security settings.
    |
    */

    'enabled' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Debug Panel Settings
    |--------------------------------------------------------------------------
    */
    'panel' => [
        'enabled' => env('DEBUG_PANEL_ENABLED', false),
        'auto_show' => env('DEBUG_PANEL_AUTO_SHOW', false),
        'position' => env('DEBUG_PANEL_POSITION', 'bottom-right'),
        'theme' => env('DEBUG_PANEL_THEME', 'dark'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug API Endpoints
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => env('DEBUG_API_ENABLED', false),
        'health_checks' => env('DEBUG_HEALTH_CHECKS', true),
        'batch_operations' => env('DEBUG_BATCH_OPERATIONS', true),
        'data_validation' => env('DEBUG_DATA_VALIDATION', true),
        'cleanup_operations' => env('DEBUG_CLEANUP_OPERATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'query_performance' => env('MONITOR_QUERY_PERFORMANCE', false),
        'cache_performance' => env('MONITOR_CACHE_PERFORMANCE', false),
        'firebase_sync' => env('MONITOR_FIREBASE_SYNC', false),
        'response_time_threshold' => env('MONITOR_RESPONSE_TIME_THRESHOLD', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Access Control
    |--------------------------------------------------------------------------
    */
    'security' => [
        'require_admin' => env('DEBUG_REQUIRE_ADMIN', true),
        'require_debug_mode' => env('DEBUG_REQUIRE_DEBUG_MODE', true),
        'log_user_actions' => env('DEBUG_LOG_USER_ACTIONS', true),
        'allowed_ips' => array_filter(explode(',', env('DEBUG_ALLOWED_IPS', ''))),
        'rate_limit' => env('DEBUG_RATE_LIMIT', 60), // per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Debug Configuration
    |--------------------------------------------------------------------------
    */
    'firebase' => [
        'debug_mode' => env('FIREBASE_DEBUG_MODE', false),
        'log_level' => env('FIREBASE_LOG_LEVEL', 'info'),
        'batch_sync_limit' => env('FIREBASE_BATCH_SYNC_LIMIT', 100),
        'health_check_interval' => env('FIREBASE_HEALTH_CHECK_INTERVAL', 300),
        'enable_sync_monitoring' => env('FIREBASE_SYNC_MONITORING', false),
        'log_sync_errors' => env('FIREBASE_LOG_SYNC_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'level' => env('DEBUG_LOG_LEVEL', 'debug'),
        'channels' => [
            'debug' => env('DEBUG_LOG_CHANNEL', 'daily'),
            'firebase' => env('FIREBASE_LOG_CHANNEL', 'firebase'),
            'performance' => env('PERFORMANCE_LOG_CHANNEL', 'performance'),
        ],
        'log_queries' => env('DEBUG_LOG_QUERIES', false),
        'log_requests' => env('DEBUG_LOG_REQUESTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Validation & Integrity
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'enabled' => env('DEBUG_DATA_VALIDATION', true),
        'check_mysql_firebase_sync' => env('DEBUG_CHECK_SYNC_INTEGRITY', true),
        'validate_user_permissions' => env('DEBUG_VALIDATE_PERMISSIONS', true),
        'check_data_consistency' => env('DEBUG_CHECK_CONSISTENCY', true),
        'auto_fix_issues' => env('DEBUG_AUTO_FIX_ISSUES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Features Toggle
    |--------------------------------------------------------------------------
    */
    'features' => [
        'system_health_check' => env('DEBUG_SYSTEM_HEALTH', true),
        'batch_sync_operations' => env('DEBUG_BATCH_SYNC', false),
        'data_cleanup' => env('DEBUG_DATA_CLEANUP', false),
        'performance_profiling' => env('DEBUG_PERFORMANCE_PROFILING', false),
        'memory_usage_tracking' => env('DEBUG_MEMORY_TRACKING', false),
        'query_analysis' => env('DEBUG_QUERY_ANALYSIS', false),
    ],
];