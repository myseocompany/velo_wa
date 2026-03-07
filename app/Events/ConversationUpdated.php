<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
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
        $conv = $this->conversation->load(['contact', 'assignee', 'latestMessage']);

        return [
            'id'              => $conv->id,
            'status'          => $conv->status->value,
            'last_message_at' => $conv->last_message_at?->toIso8601String(),
            'message_count'   => $conv->message_count,
            'assigned_to'     => $conv->assigned_to,
            'last_message'    => $conv->latestMessage ? [
                'body'       => $conv->latestMessage->body,
                'direction'  => $conv->latestMessage->direction->value,
                'created_at' => $conv->latestMessage->created_at->toIso8601String(),
                'media_type' => $conv->latestMessage->media_type,
            ] : null,
            'contact'         => $conv->contact ? [
                'id'           => $conv->contact->id,
                'name'         => $conv->contact->name,
                'push_name'    => $conv->contact->push_name,
                'phone'        => $conv->contact->phone,
                'profile_pic_url' => $conv->contact->profile_pic_url,
            ] : null,
            'assignee'        => $conv->assignee ? [
                'id'   => $conv->assignee->id,
                'name' => $conv->assignee->name,
            ] : null,
        ];
    }
}
