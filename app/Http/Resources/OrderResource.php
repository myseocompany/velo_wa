<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'conversation_id' => $this->conversation_id,
            'assigned_to' => $this->assigned_to,
            'code' => $this->code,
            'status' => $this->status->value,
            'total' => $this->total,
            'currency' => $this->currency,
            'items' => $this->items ?? [],
            'notes' => $this->notes,
            'new_at' => $this->new_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'preparing_at' => $this->preparing_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'out_for_delivery_at' => $this->out_for_delivery_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
        ];
    }
}

