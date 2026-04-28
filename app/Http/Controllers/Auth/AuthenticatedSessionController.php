<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $users = $request->matchingUsers();

        if ($users->count() > 1) {
            $request->session()->put('login.tenant_user_ids', $users->pluck('id')->all());
            $request->session()->put('login.remember', $request->boolean('remember'));

            return redirect()->route('login.tenant.select');
        }

        Auth::login($users->first(), $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Display tenant selection for credentials matching multiple tenants.
     */
    public function selectTenant(Request $request): Response|RedirectResponse
    {
        $userIds = $request->session()->get('login.tenant_user_ids', []);

        if (! is_array($userIds) || $userIds === []) {
            return redirect()->route('login');
        }

        $users = User::query()
            ->with('tenant:id,name,slug')
            ->whereIn('id', $userIds)
            ->whereNotNull('tenant_id')
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            $request->session()->forget(['login.tenant_user_ids', 'login.remember']);

            return redirect()->route('login');
        }

        return Inertia::render('Auth/SelectTenant', [
            'tenants' => $users->map(fn (User $user): array => [
                'user_id' => $user->id,
                'tenant_name' => $user->tenant?->name ?? 'Sin nombre',
                'tenant_slug' => $user->tenant?->slug,
                'user_name' => $user->name,
                'role' => $user->role->value,
            ])->values(),
        ]);
    }

    /**
     * Complete login with the selected tenant-scoped user.
     */
    public function storeTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
        ]);

        $userIds = $request->session()->get('login.tenant_user_ids', []);

        if (! is_array($userIds) || ! in_array($validated['user_id'], $userIds, true)) {
            throw ValidationException::withMessages([
                'user_id' => 'La sesión de selección expiró. Inicia sesión nuevamente.',
            ]);
        }

        $user = User::query()
            ->whereKey($validated['user_id'])
            ->whereNotNull('tenant_id')
            ->where('is_active', true)
            ->firstOrFail();

        $remember = (bool) $request->session()->get('login.remember', false);

        $request->session()->forget(['login.tenant_user_ids', 'login.remember']);

        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
