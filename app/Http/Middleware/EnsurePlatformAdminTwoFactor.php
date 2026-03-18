<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces 2FA verification.
 * Passes immediately if admin has NOT set up 2FA yet (allows first-time setup).
 * Redirects to the 2FA challenge if 2FA is enabled but not verified in session.
 */
class EnsurePlatformAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('platform')->user();

        if (! $admin) {
            return redirect()->route('superadmin.login');
        }

        // No 2FA configured → pass through (admin can set it up)
        if (! $admin->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // 2FA configured + verified in session → pass through
        if ($request->session()->get('platform_2fa_verified')) {
            return $next($request);
        }

        // 2FA configured but not verified → challenge
        return redirect()->route('superadmin.two-factor');
    }
}
