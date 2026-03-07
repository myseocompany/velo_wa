<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationActionType: string
{
    case SendMessage = 'send_message';
    case AssignAgent = 'assign_agent';
    case AddTag = 'add_tag';
    case MoveStage = 'move_stage';

    public function label(): string
    {
        return match($this) {
            self::SendMessage => 'Enviar mensaje',
            self::AssignAgent => 'Asignar agente',
            self::AddTag => 'Agregar etiqueta',
            self::MoveStage => 'Mover etapa',
        };
    }
}
