<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->tenant_id) {
            abort(403, 'No tenant associated with this account.');
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
