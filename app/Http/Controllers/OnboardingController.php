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
            'tenant' => [
                'id'         => $tenant->id,
                'name'       => $tenant->name,
                'wa_status'  => $tenant->wa_status->value,
                'wa_phone'   => $tenant->wa_phone,
            ],
        ]);
    }

    /** POST /onboarding/complete */
    public function complete(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')->with('success', '¡Bienvenido a AriCRM! Tu cuenta está lista.');
    }
}
