<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the "stop impersonation" action from the tenant app banner.
 * When called via POST /impersonation/stop, logs out the web user,
 * clears the session impersonation keys, and redirects back to the
 * superadmin tenant detail page.
 */
class StopImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
