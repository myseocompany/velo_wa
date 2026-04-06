<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Confirmed => 'Confirmado',
            self::Preparing => 'Preparando',
            self::Ready => 'Listo',
            self::OutForDelivery => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    public static function activeStages(): array
    {
        return [self::New, self::Confirmed, self::Preparing, self::Ready, self::OutForDelivery];
    }
}

