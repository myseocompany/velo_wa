<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InstrumentDashboardRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $user = $request->user();

        Log::info('Dashboard view', [
            'request_id' => $requestId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'inertia' => $request->header('X-Inertia') === 'true',
        ]);

        $response->headers->set('X-Dashboard-Request-Id', $requestId);

        return $response;
    }
}
