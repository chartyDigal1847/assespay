<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiAccess
{
    public function __construct(protected ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->logger->log('api.access', null, null, [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
        ], $request);

        return $response;
    }
}
