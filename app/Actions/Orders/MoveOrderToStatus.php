<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;

class MoveOrderToStatus
{
    public function handle(Order $order, OrderStatus $status): Order
    {
        if ($order->status === $status) {
            return $order;
        }

        $now = CarbonImmutable::now();
        $updates = ['status' => $status];

        if ($order->new_at === null && $status === OrderStatus::New) {
            $updates['new_at'] = $now;
        }
        if ($order->confirmed_at === null && $status === OrderStatus::Confirmed) {
            $updates['confirmed_at'] = $now;
        }
        if ($order->preparing_at === null && $status === OrderStatus::Preparing) {
            $updates['preparing_at'] = $now;
        }
        if ($order->ready_at === null && $status === OrderStatus::Ready) {
            $updates['ready_at'] = $now;
        }
        if ($order->out_for_delivery_at === null && $status === OrderStatus::OutForDelivery) {
            $updates['out_for_delivery_at'] = $now;
        }
        if ($order->delivered_at === null && $status === OrderStatus::Delivered) {
            $updates['delivered_at'] = $now;
        }
        if ($order->cancelled_at === null && $status === OrderStatus::Cancelled) {
            $updates['cancelled_at'] = $now;
        }

        $order->fill($updates)->save();

        return $order;
    }
}

