<?php

return [
    'service_name' => env('ASSESSPAY_SERVICE_NAME', 'AssessPay'),
    'service_key' => env('ASSESSPAY_SERVICE_KEY', 'assesspay-service'),
    'service_url' => env('ASSESSPAY_SERVICE_URL', env('APP_URL', 'http://localhost')),
    'api_version' => env('ASSESSPAY_API_VERSION', 'v1'),
    'api_prefix' => '/api/v1',

    'portal' => [
        'trusted_url' => env('PORTAL_BASE_URL', 'https://deoris.test'),
        'auth_url' => env('AUTH_SERVICE_URL', 'https://deoris.test'),
        'verify_ssl' => filter_var(
            env('AUTH_VERIFY_SSL', env('APP_ENV', 'production') === 'local' ? 'false' : 'true'),
            FILTER_VALIDATE_BOOL
        ),
        'sso_exchange_path' => '/api/v1/sso/exchange',
        'sso_validate_path' => '/api/v1/sso/validate',
    ],

    'clearcheck' => [
        'api_url' => env('CLEARCHECK_API_URL', 'https://clearcheck.deoris.test/api/v1'),
        'service_key' => env('CLEARCHECK_SERVICE_KEY', 'clearcheck-service'),
        'cache_ttl' => (int) env('CLEARCHECK_CACHE_TTL', 300),
    ],

    'event_hub' => [
        'url' => env('EVENT_HUB_URL', 'https://events.deoris.test/api/v1/publish'),
        'secret' => env('ASSESSPAY_EVENT_SECRET', ''),
        'schema_version' => '1.0',
        'source_service' => 'assesspay-service',
        'max_skew_seconds' => 300,
        'nonce_ttl' => 600,
    ],

    'redis' => [
        'channels' => [
            'billing' => env('REDIS_CHANNEL_BILLING', 'billing.events'),
            'payments' => env('REDIS_CHANNEL_PAYMENTS', 'payments.notifications'),
            'analytics' => env('REDIS_CHANNEL_ANALYTICS', 'financial.analytics'),
        ],
        'prefix' => env('REDIS_PREFIX', 'assesspay:'),
    ],

    'queues' => [
        'payments' => env('QUEUE_PAYMENTS', 'payments'),
        'billing' => env('QUEUE_BILLING', 'billing'),
        'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
        'events' => env('QUEUE_EVENTS', 'events'),
    ],

    'roles' => [
        'cashier' => 'cashier',
        'student' => 'student',
        'admin' => 'admin',
    ],

    'rate_limit' => [
        'api' => (int) env('ASSESSPAY_API_RATE_LIMIT', 120),
    ],

    'realtime' => [
        'enabled' => filter_var(env('ASSESSPAY_REALTIME_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
];
