<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->conversation->tenant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        $conv = $this->conversation->load([
            'contact',
            'assignee',
            'whatsappLine',
            'messages' => fn ($q) => $q->reorder()->orderByDesc('created_at')->limit(1),
        ]);
        $lastMessage = $conv->messages->sortByDesc('created_at')->first();

        return [
            // Full Conversation shape so the frontend can insert new conversations
            // without an extra API round-trip
            'id'                => $conv->id,
            'tenant_id'         => $conv->tenant_id,
            'contact_id'        => $conv->contact_id,
            'status'            => $conv->status->value,
            'channel'           => $conv->channel->value,
            'whatsapp_line_id'  => $conv->whatsapp_line_id,
            'assigned_to'       => $conv->assigned_to,
            'assigned_at'       => $conv->assigned_at?->toIso8601String(),
            'ai_agent_enabled'  => $conv->ai_agent_enabled,
            'first_message_at'  => $conv->first_message_at?->toIso8601String(),
            'first_response_at' => $conv->first_response_at?->toIso8601String(),
            'last_message_at'   => $conv->last_message_at?->toIso8601String(),
            'message_count'     => $conv->message_count,
            'closed_at'         => $conv->closed_at?->toIso8601String(),
            'reopen_count'      => $conv->reopen_count,
            'created_at'        => $conv->created_at->toIso8601String(),
            'updated_at'        => $conv->updated_at->toIso8601String(),
            'last_message'      => $lastMessage ? [
                'body'       => $lastMessage->body,
                'direction'  => $lastMessage->direction->value,
                'created_at' => $lastMessage->created_at->toIso8601String(),
                'media_type' => $lastMessage->media_type,
            ] : null,
            'contact'           => $conv->contact ? [
                'id'              => $conv->contact->id,
                'name'            => $conv->contact->name,
                'push_name'       => $conv->contact->push_name,
                'phone'           => $conv->contact->phone,
                'profile_pic_url' => $conv->contact->profile_pic_url,
            ] : null,
            'assignee'          => $conv->assignee ? [
                'id'   => $conv->assignee->id,
                'name' => $conv->assignee->name,
            ] : null,
            'whatsapp_line'     => $conv->whatsappLine ? [
                'id'     => $conv->whatsappLine->id,
                'label'  => $conv->whatsappLine->label,
                'phone'  => $conv->whatsappLine->phone,
                'status' => $conv->whatsappLine->status->value,
            ] : null,
        ];
    }
}
