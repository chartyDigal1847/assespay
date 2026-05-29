<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    | Add the auth service entry so SsoController can reach it.
    */

    'portal' => [
    'base_url' => env('PORTAL_BASE_URL', 'https://deoris.test'),
],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'auth' => [
        'url' => env('AUTH_SERVICE_URL', 'https://deoris.test'),
        'public_key' => env('AUTH_PUBLIC_KEY'),
        'verify_ssl' => filter_var(
            env('AUTH_VERIFY_SSL', env('APP_ENV', 'production') === 'local' ? 'false' : 'true'),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'enrollease' => [
        'url' => env('ENROLLEASE_API_URL', 'https://enrollease.deoris.test/api/v1'),
        'key' => env('ENROLLEASE_API_KEY'),
        'timeout' => (int) env('ENROLLEASE_TIMEOUT', 10),
        'retry_attempts' => (int) env('ENROLLEASE_RETRY_ATTEMPTS', 3),
        'verify_ssl' => (bool) env('ENROLLEASE_VERIFY_SSL', false),
    ],
];