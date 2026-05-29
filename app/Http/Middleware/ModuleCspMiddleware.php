<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * ModuleCspMiddleware — AssesPay
 *
 * Sets CSP headers so the Deoris portal can embed AssesPay in an <iframe>.
 * CRITICAL: frame-ancestors must match APP_PORTAL_URL or the iframe is blank.
 */
class ModuleCspMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $portalUrl  = config('app.portal_url', 'https://deoris.test');
        $appUrl     = config('app.url', 'https://assesspay.deoris.test');
        $reverbHost = config('broadcasting.connections.reverb.options.host', 'assesspay.deoris.test');
        $reverbPort = config('broadcasting.connections.reverb.options.port', 8084);
        $reverbWss  = 'wss://' . $reverbHost . ':' . $reverbPort;
        $reverbHttp = 'https://' . $reverbHost . ':' . $reverbPort;
        $debugConnectSrc = app()->hasDebugModeEnabled() ? ' http://127.0.0.1:7481' : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' {$portalUrl} https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "script-src-elem 'self' 'unsafe-inline' {$portalUrl} https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "img-src 'self' data:",
            // Allow portal API calls, Reverb WebSocket, and Reverb HTTP auth endpoint
            "connect-src 'self' {$portalUrl} {$reverbWss} {$reverbHttp} https://cdn.jsdelivr.net{$debugConnectSrc}",
            "frame-ancestors {$portalUrl}",   // CRITICAL — allows portal to iframe this module
            "frame-src 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}