<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    private const PLANS = [
        'starter' => [
            'name'         => 'Starter',
            'price_id'     => null, // Set STRIPE_PRICE_STARTER in .env
            'price'        => '$29/mes',
            'max_agents'   => 3,
            'max_contacts' => 2000,
        ],
        'growth' => [
            'name'         => 'Growth',
            'price_id'     => null, // Set STRIPE_PRICE_GROWTH in .env
            'price'        => '$79/mes',
            'max_agents'   => 10,
            'max_contacts' => 15000,
        ],
        'scale' => [
            'name'         => 'Scale',
            'price_id'     => null, // Set STRIPE_PRICE_SCALE in .env
            'price'        => '$199/mes',
            'max_agents'   => null,
            'max_contacts' => null,
        ],
    ];

    /** GET /settings/billing */
    public function show(Request $request): Response
    {
        $tenant       = $request->user()->tenant;
        $subscription = $tenant->subscription('default');

        return Inertia::render('Settings/Billing', [
            'plans'        => self::PLANS,
            'subscription' => $subscription ? [
                'stripe_status' => $subscription->stripe_status,
                'stripe_price'  => $subscription->stripe_price,
                'ends_at'       => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ] : null,
            'pm_type'       => $tenant->pm_type,
            'pm_last_four'  => $tenant->pm_last_four,
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'on_trial'      => $tenant->onTrial(),
            'subscribed'    => $tenant->subscribed('default'),
        ]);
    }

    /** POST /settings/billing/checkout/{plan} */
    public function checkout(Request $request, string $plan): RedirectResponse
    {
        $priceId = match ($plan) {
            'starter' => config('services.stripe.price_starter'),
            'growth'  => config('services.stripe.price_growth'),
            'scale'   => config('services.stripe.price_scale'),
            default   => abort(404),
        };

        if (! $priceId) {
            return back()->withErrors(['plan' => 'Plan no configurado. Contacta soporte.']);
        }

        $tenant = $request->user()->tenant;

        $checkout = $tenant->newSubscription('default', $priceId)
            ->trialDays(14)
            ->checkout([
                'success_url' => route('settings.billing') . '?success=1',
                'cancel_url'  => route('settings.billing'),
            ]);

        return redirect($checkout->url);
    }

    /** POST /settings/billing/portal */
    public function portal(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $url = $tenant->billingPortalUrl(route('settings.billing'));

        return redirect($url);
    }

    /** POST /settings/billing/cancel */
    public function cancel(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->subscription('default')?->cancel();

        return back()->with('success', 'Suscripción cancelada. Tienes acceso hasta el final del período actual.');
    }

    /** POST /settings/billing/resume */
    public function resume(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->subscription('default')?->resume();

        return back()->with('success', 'Suscripción reactivada.');
    }
}
