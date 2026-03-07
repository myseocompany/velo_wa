<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open => 'Abierta',
            self::Pending => 'Pendiente',
            self::Closed => 'Cerrada',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Pending]);
    }
}
