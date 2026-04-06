<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /** GET /onboarding */
    public function show(Request $request): Response|RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($tenant->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding', [
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'wa_status' => $tenant->wa_status->value,
                'wa_phone' => $tenant->wa_phone,
                'onboarding_vertical' => $tenant->onboarding_vertical,
            ],
        ]);
    }

    /** POST /onboarding/complete */
    public function complete(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $data = $request->validate([
            'vertical' => ['nullable', 'string', 'in:restaurant,health,retail,services,education,other'],
        ]);

        $tenant->update([
            'onboarding_completed_at' => now(),
            'onboarding_vertical' => $data['vertical'] ?? $tenant->onboarding_vertical,
        ]);

        return redirect()->route('dashboard')->with('success', '¡Bienvenido a AriCRM! Tu cuenta está lista.');
    }
}
