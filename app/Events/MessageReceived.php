<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->message->tenant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->load(['sender', 'conversation']);

        return [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'whatsapp_line_id' => $message->conversation?->whatsapp_line_id,
            'direction'       => $message->direction->value,
            'body'            => $message->body,
            'media_url'       => $message->media_url,
            'media_type'      => $message->media_type,
            'media_mime_type' => $message->media_mime_type,
            'media_filename'  => $message->media_filename,
            'status'          => $message->status->value,
            'wa_message_id'   => $message->wa_message_id,
            'error_message'   => $message->error_message,
            'is_automated'    => $message->is_automated,
            'sent_by'         => $message->sent_by,
            'sender'          => $message->sender ? [
                'id'   => $message->sender->id,
                'name' => $message->sender->name,
            ] : null,
            'created_at'      => $message->created_at->toIso8601String(),
            'updated_at'      => $message->updated_at->toIso8601String(),
        ];
    }
}
