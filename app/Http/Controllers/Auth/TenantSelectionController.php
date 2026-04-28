<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantSelectionController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $users = $this->tenantUsers($request->user());

        if ($users->count() <= 1) {
            return redirect()->route('settings')
                ->with('error', 'Este usuario solo tiene una empresa disponible.');
        }

        return Inertia::render('Auth/SelectTenant', [
            'submitUrl' => route('tenant.store', absolute: false),
            'tenants' => $this->tenantOptions($users),
            'backUrl' => route('settings', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
        ]);

        $users = $this->tenantUsers($request->user());
        $user = $users->firstWhere('id', $validated['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'No tienes acceso a esa empresa.',
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    private function tenantUsers(User $user)
    {
        return User::query()
            ->with('tenant:id,name,slug')
            ->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
            ->whereNotNull('tenant_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function tenantOptions($users): array
    {
        return $users->map(fn (User $user): array => [
            'user_id' => $user->id,
            'tenant_name' => $user->tenant?->name ?? 'Sin nombre',
            'tenant_slug' => $user->tenant?->slug,
            'user_name' => $user->name,
            'role' => $user->role->label(),
        ])->values()->all();
    }
}
