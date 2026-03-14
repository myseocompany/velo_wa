<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Accepted role arguments (most permissive to least):
     *   'admin'  → owner or admin
     *   'owner'  → owner only
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = collect($roles)->contains(fn (string $role) => match ($role) {
            'owner' => $user->isOwner(),
            'admin' => $user->isOwner() || $user->isAdmin(),
            default => false,
        });

        abort_unless($allowed, 403, 'Insufficient permissions.');

        return $next($request);
    }
}
