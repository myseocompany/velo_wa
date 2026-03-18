<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\PlatformAudit;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /** GET /superadmin/login */
    public function showLogin(): Response|RedirectResponse
    {
        if (auth('platform')->check()) {
            return redirect()->route('superadmin.dashboard');
        }

        return Inertia::render('SuperAdmin/Login');
    }

    /** POST /superadmin/login */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('platform')->attempt($request->only('email', 'password'))) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.']);
        }

        $admin = auth('platform')->user();

        if (! $admin->is_active) {
            Auth::guard('platform')->logout();

            return back()->withErrors(['email' => 'Tu cuenta ha sido desactivada.']);
        }

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $request->session()->regenerate();

        PlatformAudit::log($admin, 'login', metadata: ['ip' => $request->ip()], request: $request);

        // If 2FA is enabled, redirect to challenge
        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('superadmin.two-factor');
        }

        return redirect()->route('superadmin.dashboard');
    }

    /** GET /superadmin/two-factor */
    public function showTwoFactor(): Response|RedirectResponse
    {
        if (! auth('platform')->check()) {
            return redirect()->route('superadmin.login');
        }

        if (request()->session()->get('platform_2fa_verified')) {
            return redirect()->route('superadmin.dashboard');
        }

        return Inertia::render('SuperAdmin/TwoFactor');
    }

    /** POST /superadmin/two-factor */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $admin = auth('platform')->user();

        if (! $admin) {
            return redirect()->route('superadmin.login');
        }

        if (! Totp::verify($admin->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto o expirado.']);
        }

        $request->session()->put('platform_2fa_verified', true);

        PlatformAudit::log($admin, '2fa_verified', request: $request);

        return redirect()->route('superadmin.dashboard');
    }

    /** POST /superadmin/logout */
    public function logout(Request $request): RedirectResponse
    {
        $admin = auth('platform')->user();

        if ($admin) {
            PlatformAudit::log($admin, 'logout', request: $request);
        }

        Auth::guard('platform')->logout();

        $request->session()->forget(['platform_2fa_verified', 'impersonating_user_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }

    // ── 2FA Setup ──────────────────────────────────────────────────────────────

    /** GET /superadmin/2fa/setup */
    public function showTwoFactorSetup(): Response
    {
        $admin  = auth('platform')->user();
        $secret = $admin->two_factor_secret ?? Totp::generateSecret();

        // Store temp secret in session if not yet confirmed
        if (! $admin->two_factor_secret) {
            session(['platform_2fa_pending_secret' => $secret]);
        }

        $uri = Totp::otpauthUri($secret, $admin->email);

        return Inertia::render('SuperAdmin/TwoFactorSetup', [
            'secret'     => $secret,
            'otpauth'    => $uri,
            'confirmed'  => $admin->hasTwoFactorEnabled(),
        ]);
    }

    /** POST /superadmin/2fa/enable */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $secret = session('platform_2fa_pending_secret')
            ?? auth('platform')->user()->two_factor_secret;

        if (! $secret || ! Totp::verify($secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Verifica la hora de tu dispositivo.']);
        }

        $admin = auth('platform')->user();
        $admin->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget('platform_2fa_pending_secret');
        $request->session()->put('platform_2fa_verified', true);

        PlatformAudit::log($admin, '2fa_enabled', request: $request);

        return redirect()->route('superadmin.dashboard')
            ->with('success', '2FA activado correctamente.');
    }

    /** DELETE /superadmin/2fa */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $admin = auth('platform')->user();

        if (! Totp::verify($admin->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        $admin->update([
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);

        $request->session()->forget('platform_2fa_verified');

        PlatformAudit::log($admin, '2fa_disabled', request: $request);

        return redirect()->route('superadmin.2fa.setup')
            ->with('success', '2FA desactivado.');
    }
}
