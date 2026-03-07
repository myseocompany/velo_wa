<?php

declare(strict_types=1);

namespace App\Enums;

enum AssignmentRuleType: string
{
    case RoundRobin = 'round_robin';
    case LeastBusy = 'least_busy';
    case TagBased = 'tag_based';
    case Manual = 'manual';

    public function label(): string
    {
        return match($this) {
            self::RoundRobin => 'Turno rotativo',
            self::LeastBusy => 'Menos ocupado',
            self::TagBased => 'Por etiqueta',
            self::Manual => 'Manual',
        };
    }
}
