<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'contact_id'      => $this->contact_id,
            'conversation_id' => $this->conversation_id,
            'title'           => $this->title,
            'stage'           => $this->stage->value,
            'value'           => $this->value,
            'currency'        => $this->currency,
            'lead_at'         => $this->lead_at?->toIso8601String(),
            'qualified_at'    => $this->qualified_at?->toIso8601String(),
            'proposal_at'     => $this->proposal_at?->toIso8601String(),
            'negotiation_at'  => $this->negotiation_at?->toIso8601String(),
            'assigned_to'     => $this->assigned_to,
            'notes'           => $this->notes,
            'closed_at'       => $this->closed_at?->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
            'contact'         => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'assignee'        => $this->whenLoaded('assignee', fn () => [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
        ];
    }
}
