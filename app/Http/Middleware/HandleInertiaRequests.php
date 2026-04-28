<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        // Impersonation state (shared to tenant app so the banner shows)
        $impersonating = $request->session()->has('impersonating_user_id')
            ? [
                'active'    => true,
                'tenant_id' => $request->session()->get('impersonating_tenant_id'),
                'admin_id'  => $request->session()->get('impersonating_admin_id'),
            ]
            : ['active' => false];

        // Platform admin (shared to SuperAdmin pages)
        $platformAdmin = auth('platform')->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user'   => $user,
                'tenant' => $tenant ? [
                    ...$tenant->toArray(),
                    'current_plan' => $tenant->currentPlan()->value,
                    'max_wa_lines' => $tenant->currentPlan()->maxWhatsAppLines(),
                    'current_wa_lines_count' => $tenant->currentWhatsAppLinesCount(),
                ] : null,
                'tenant_switcher_available' => $user
                    ? User::query()
                        ->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
                        ->whereNotNull('tenant_id')
                        ->where('is_active', true)
                        ->count() > 1
                    : false,
            ],
            'impersonation' => $impersonating,
            'platform_admin' => $platformAdmin ? [
                'id'              => $platformAdmin->id,
                'name'            => $platformAdmin->name,
                'email'           => $platformAdmin->email,
                'two_factor_enabled' => $platformAdmin->hasTwoFactorEnabled(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
