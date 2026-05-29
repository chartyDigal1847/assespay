<?php

namespace App\Http\Middleware;

use App\Services\PortalUserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssessPayRole
{
    public function __construct(protected PortalUserService $portalUsers) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $this->portalUsers->role($request);

        if (! $role || ! in_array($role, $roles, true)) {
            return response()->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Insufficient permissions for this operation.',
            ], 403);
        }

        return $next($request);
    }
}
