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
            'email'           => $this->email,
            'company'         => $this->company,
            'notes'           => $this->notes,
            'tags'            => $this->whenLoaded('tags', fn () =>
                $this->tags->map(fn ($t) => [
                    'id'                   => $t->id,
                    'name'                 => $t->name,
                    'slug'                 => $t->slug,
                    'color'                => $t->color,
                    'exclude_from_metrics' => $t->exclude_from_metrics,
                ])->values()->all()
            , []),
            'custom_fields'   => $this->custom_fields ?? [],
            'assigned_to'     => $this->assigned_to,
            'source'          => $this->source?->value,
            'first_contact_at' => $this->first_contact_at?->toIso8601String(),
            'last_contact_at'  => $this->last_contact_at?->toIso8601String(),
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
            'assignee'        => $this->whenLoaded('assignee', fn () => [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'conversations'   => $this->whenLoaded('conversations', fn () =>
                $this->conversations->map(fn ($c) => [
                    'id'              => $c->id,
                    'status'          => $c->status->value,
                    'message_count'   => $c->message_count,
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                    'created_at'      => $c->created_at->toIso8601String(),
                ])
            ),
        ];
    }
}
