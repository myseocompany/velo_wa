<?php

declare(strict_types=1);

namespace App\Enums;

enum WaStatus: string
{
    case Disconnected = 'disconnected';
    case QrPending = 'qr_pending';
    case Connected = 'connected';
    case Banned = 'banned';

    public function label(): string
    {
        return match($this) {
            self::Disconnected => 'Desconectado',
            self::QrPending => 'Esperando QR',
            self::Connected => 'Conectado',
            self::Banned => 'Bloqueado',
        };
    }

    public function isConnected(): bool
    {
        return $this === self::Connected;
    }
}
