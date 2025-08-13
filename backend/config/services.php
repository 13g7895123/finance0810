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
        'auto_reply_enabled' => env('LINE_AUTO_REPLY_ENABLED', true),
        'default_reply_message' => env('LINE_DEFAULT_REPLY_MESSAGE', '感謝您的訊息，專員將盡快回覆您。'),
        'business_hours_enabled' => env('LINE_BUSINESS_HOURS_ENABLED', false),
        'business_hours_start' => env('LINE_BUSINESS_HOURS_START', '09:00'),
        'business_hours_end' => env('LINE_BUSINESS_HOURS_END', '18:00'),
        'out_of_hours_message' => env('LINE_OUT_OF_HOURS_MESSAGE', '目前為非營業時間，我們將在營業時間內盡快回覆您。營業時間：週一至週五 9:00-18:00'),
    ],

];