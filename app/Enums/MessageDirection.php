<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function isInbound(): bool
    {
        return $this === self::In;
    }

    public function isOutbound(): bool
    {
        return $this === self::Out;
    }
}
