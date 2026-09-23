<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'api_token' => env('SMS_API_TOKEN'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'fallback_models' => env('GEMINI_FALLBACK_MODELS', 'gemini-flash-lite-latest,gemini-3.1-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 12),
        'forecast_cache_minutes' => (int) env('GEMINI_FORECAST_CACHE_MINUTES', 360),
        'forecast_max_items' => (int) env('GEMINI_FORECAST_MAX_ITEMS', 100),
        // How long the statistical placeholder stays on the screens before the
        // deferred AI pass replaces it. It is also the floor between warm-up
        // attempts, so a Gemini outage costs one request per window rather than
        // one per page view.
        'forecast_fallback_minutes' => (int) env('GEMINI_FORECAST_FALLBACK_MINUTES', 10),
    ],

];
