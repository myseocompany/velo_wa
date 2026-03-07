<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'wa_id'           => $this->wa_id,
            'phone'           => $this->phone,
            'name'            => $this->name,
            'push_name'       => $this->push_name,
            'profile_pic_url' => $this->profile_pic_url,
            'tags'            => $this->tags ?? [],
            'source'          => $this->source?->value,
            'first_contact_at' => $this->first_contact_at?->toIso8601String(),
            'last_contact_at'  => $this->last_contact_at?->toIso8601String(),
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
            'assignee'        => $this->whenLoaded('assignee', fn () => [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
        ];
    }
}
