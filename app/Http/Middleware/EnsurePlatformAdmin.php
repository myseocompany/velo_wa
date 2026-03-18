<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checks that a platform admin is logged in and active. Does NOT check 2FA. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('platform')->user();

        if (! $admin) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->route('superadmin.login');
        }

        if (! $admin->is_active) {
            auth('platform')->logout();

            return redirect()->route('superadmin.login')
                ->withErrors(['email' => 'Tu cuenta ha sido desactivada.']);
        }

        return $next($request);
    }
}
