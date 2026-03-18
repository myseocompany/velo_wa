<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Extends Cashier's webhook controller to handle subscription lifecycle events
 * and sync plan limits when a subscription changes.
 */
class StripeWebhookController extends CashierWebhookController
{
    /** Called when subscription becomes active (payment succeeded) */
    protected function handleCustomerSubscriptionUpdated(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $stripeSubscription = $payload['data']['object'];
        $tenant = Tenant::where('stripe_id', $stripeSubscription['customer'])->first();

        if ($tenant) {
            $this->syncPlanLimits($tenant, $stripeSubscription['items']['data'][0]['price']['id'] ?? null);
        }

        return $response;
    }

    /** Sync max_agents / max_contacts based on active price */
    private function syncPlanLimits(Tenant $tenant, ?string $priceId): void
    {
        $limits = match ($priceId) {
            config('services.stripe.price_starter') => ['max_agents' => 3,    'max_contacts' => 2000],
            config('services.stripe.price_growth')  => ['max_agents' => 10,   'max_contacts' => 15000],
            config('services.stripe.price_scale')   => ['max_agents' => null, 'max_contacts' => null],
            default                                 => [],
        };

        if (! empty($limits)) {
            $tenant->update($limits);
        }
    }
}
