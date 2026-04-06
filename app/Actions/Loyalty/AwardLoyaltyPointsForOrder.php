<?php

declare(strict_types=1);

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyEvent;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AwardLoyaltyPointsForOrder
{
    public function handle(Order $order): void
    {
        if ($order->total === null || (float) $order->total <= 0) {
            return;
        }

        $points = $this->pointsFromOrderTotal((float) $order->total);
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points): void {
            $alreadyAwarded = LoyaltyEvent::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_id', $order->id)
                ->where('type', LoyaltyEventType::OrderReward->value)
                ->exists();

            if ($alreadyAwarded) {
                return;
            }

            $account = LoyaltyAccount::query()->firstOrCreate(
                [
                    'tenant_id' => $order->tenant_id,
                    'contact_id' => $order->contact_id,
                ],
                [
                    'points_balance' => 0,
                    'total_earned' => 0,
                    'total_redeemed' => 0,
                ]
            );

            $account->increment('points_balance', $points);
            $account->increment('total_earned', $points);

            LoyaltyEvent::create([
                'tenant_id' => $order->tenant_id,
                'loyalty_account_id' => $account->id,
                'contact_id' => $order->contact_id,
                'order_id' => $order->id,
                'type' => LoyaltyEventType::OrderReward,
                'points' => $points,
                'description' => 'Puntos por pedido entregado',
                'meta' => [
                    'order_code' => $order->code,
                    'order_total' => $order->total,
                    'currency' => $order->currency,
                ],
            ]);
        });
    }

    private function pointsFromOrderTotal(float $total): int
    {
        return max(1, (int) floor($total / 1000));
    }
}

