<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Agent = 'agent';

    public function label(): string
    {
        return match($this) {
            self::Owner => 'Propietario',
            self::Admin => 'Administrador',
            self::Agent => 'Agente',
        };
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin]);
    }

    public function canManageSettings(): bool
    {
        return $this === self::Owner;
    }
}
