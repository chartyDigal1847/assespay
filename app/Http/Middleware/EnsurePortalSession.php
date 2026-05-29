<?php

namespace App\Http\Middleware;

use App\Services\PortalUserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalSession
{
    public function __construct(protected PortalUserService $portalUsers) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->portalUsers->isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'unauthenticated',
                'message' => 'DEORIS Portal session required.',
            ], 401);
        }

        return $next($request);
    }
}
