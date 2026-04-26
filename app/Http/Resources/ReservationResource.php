<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'conversation_id' => $this->conversation_id,
            'assigned_to' => $this->assigned_to,
            'bookable_unit_id' => $this->bookable_unit_id,
            'service' => $this->service,
            'code' => $this->code,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'party_size' => $this->party_size,
            'notes' => $this->notes,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'seated_at' => $this->seated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'no_show_at' => $this->no_show_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'bookable_unit' => $this->whenLoaded('bookableUnit', fn () => $this->bookableUnit ? [
                'id' => $this->bookableUnit->id,
                'name' => $this->bookableUnit->name,
                'type' => $this->bookableUnit->type,
            ] : null),
        ];
    }
}
