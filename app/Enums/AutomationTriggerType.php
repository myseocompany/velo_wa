<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationTriggerType: string
{
    case NewConversation = 'new_conversation';
    case Keyword = 'keyword';
    case OutsideHours = 'outside_hours';
    case NoResponseTimeout = 'no_response_timeout';

    public function label(): string
    {
        return match($this) {
            self::NewConversation => 'Nueva conversación',
            self::Keyword => 'Palabra clave',
            self::OutsideHours => 'Fuera de horario',
            self::NoResponseTimeout => 'Sin respuesta',
        };
    }
}
