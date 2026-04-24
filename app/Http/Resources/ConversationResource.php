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
            'whatsapp_line_id'  => $this->whatsapp_line_id,
            'assigned_to'       => $this->assigned_to ? (string) $this->assigned_to : null,
            'assigned_at'       => $this->assigned_at?->toIso8601String(),
            'ai_agent_enabled'  => $this->ai_agent_enabled,
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
            'whatsapp_line'     => $this->whenLoaded('whatsappLine', fn () => $this->whatsappLine ? [
                'id'     => $this->whatsappLine->id,
                'label'  => $this->whatsappLine->label,
                'phone'  => $this->whatsappLine->phone,
                'status' => $this->whatsappLine->status->value,
            ] : null),
            'last_message'      => $this->whenLoaded('messages', function () {
                $last = $this->messages->sortByDesc('created_at')->first();

                return $last ? [
                    'body'       => $last->body,
                    'direction'  => $last->direction->value,
                    'created_at' => $last->created_at->toIso8601String(),
                    'media_type' => $last->media_type,
                ] : null;
            }),
            'created_at'        => $this->created_at->toIso8601String(),
        ];
    }
}
