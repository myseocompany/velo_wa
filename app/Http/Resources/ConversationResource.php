<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'status'            => $this->status->value,
            'channel'           => $this->channel->value,
            'assigned_to'       => $this->assigned_to,
            'assigned_at'       => $this->assigned_at?->toIso8601String(),
            'first_message_at'  => $this->first_message_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'last_message_at'   => $this->last_message_at?->toIso8601String(),
            'message_count'     => $this->message_count,
            'closed_at'         => $this->closed_at?->toIso8601String(),
            'contact'           => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'assignee'          => $this->whenLoaded('assignee', fn () => [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'last_message'      => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body'       => $this->latestMessage->body,
                'direction'  => $this->latestMessage->direction->value,
                'created_at' => $this->latestMessage->created_at->toIso8601String(),
                'media_type' => $this->latestMessage->media_type,
            ] : null),
            'created_at'        => $this->created_at->toIso8601String(),
        ];
    }
}
