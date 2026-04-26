<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Enums\ReservationStatus;
use App\Models\Conversation;
use App\Models\Reservation;

class GetContactTool implements Tool
{
    public function name(): string
    {
        return 'get_contact';
    }

    public function description(): string
    {
        return 'Consulta datos basicos del contacto de la conversacion actual.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        $contact = $conversation->contact;
        if (! $contact) {
            return ['error' => 'contact_not_found'];
        }

        $lastVisit = Reservation::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('contact_id', $contact->id)
            ->whereIn('status', [ReservationStatus::Seated->value, ReservationStatus::Completed->value])
            ->orderByDesc('starts_at')
            ->first();

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'notes' => $contact->notes,
            'is_returning' => $lastVisit !== null,
            'last_visit_at' => $lastVisit?->starts_at?->toIso8601String(),
        ];
    }
}
