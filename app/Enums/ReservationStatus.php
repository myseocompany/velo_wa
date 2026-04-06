<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitada',
            self::Confirmed => 'Confirmada',
            self::Seated => 'En servicio',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No show',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }
}

