<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Delivered => 'Entregado',
            self::Read => 'Leído',
            self::Failed => 'Fallido',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Read, self::Failed]);
    }
}
