<?php

declare(strict_types=1);

namespace App\Enums;

enum DealStage: string
{
    case Lead = 'lead';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    public function label(): string
    {
        return match($this) {
            self::Lead => 'Lead',
            self::Qualified => 'Calificado',
            self::Proposal => 'Propuesta',
            self::Negotiation => 'Negociación',
            self::ClosedWon => 'Ganado',
            self::ClosedLost => 'Perdido',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::ClosedWon, self::ClosedLost]);
    }

    public function isWon(): bool
    {
        return $this === self::ClosedWon;
    }

    public static function activeStages(): array
    {
        return [self::Lead, self::Qualified, self::Proposal, self::Negotiation];
    }
}
