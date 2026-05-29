<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AssessPay uses DEORIS SSO session keys — not Laravel password auth.
 * Strip stale login_web_* keys left from the removed users table.
 */
class ClearLegacyWebAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            foreach (array_keys($request->session()->all()) as $key) {
                if (str_starts_with($key, 'login_web_')) {
                    $request->session()->forget($key);
                }
            }
        }

        return $next($request);
    }
}
