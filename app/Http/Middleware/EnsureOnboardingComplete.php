<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects new tenants to /onboarding until they complete the setup wizard.
 * Only applies to web routes — API routes are unaffected.
 */
class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant || $tenant->hasCompletedOnboarding()) {
            return $next($request);
        }

        // Allow the onboarding routes themselves
        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        return redirect()->route('onboarding.show');
    }
}
