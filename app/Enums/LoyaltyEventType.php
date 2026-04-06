<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyEventType: string
{
    case OrderReward = 'order_reward';
    case ManualAdjustment = 'manual_adjustment';
    case Redemption = 'redemption';
}

