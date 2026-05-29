<?php

use Illuminate\Support\Str;

$sessionDriver = env('SESSION_DRIVER', 'database');

// Prevent hard 500s when DB-backed sessions are configured but DB creds are missing.
if (
    $sessionDriver === 'database'
    && (
        ! is_string(env('DB_USERNAME')) || trim(env('DB_USERNAME')) === ''
        || ! is_string(env('DB_DATABASE')) || trim(env('DB_DATABASE')) === ''
    )
) {
    $sessionDriver = 'file';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    */

    'driver' => $sessionDriver,

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    | For iframe SSO: leave this empty (or set to null) so the session cookie
    | is automatically scoped to the current domain (assesspay.deoris.test)
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    | CRITICAL: Must be true when SameSite=none for iframe to work.
    | Set via .env: SESSION_SECURE_COOKIE=true
    */

    'secure' => env('SESSION_SECURE_COOKIE', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    | Keep this true for security (session cannot be accessed via JavaScript)
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    | 
    | CRITICAL FOR IFRAME SSO:
    | 
    | Change from 'lax' to 'none' because:
    | - Your iframe is on assesspay.deoris.test
    | - Parent portal is on deoris.test
    | - They are cross-origin, so 'lax' blocks the cookie
    | - 'none' allows cross-origin; REQUIRES secure: true
    |
    | Set via .env: SESSION_SAME_SITE=none
    |
    | If iframe and parent are SAME domain, use 'lax' instead.
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    | Not needed for this use case
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];