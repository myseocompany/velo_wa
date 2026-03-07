<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;

class StoreInboundMessage
{
    /**
     * @param  array{
     *     waMessageId: string,
     *     body: ?string,
     *     mediaUrl: ?string,
     *     mediaType: ?string,
     *     mediaMimeType: ?string,
     *     mediaFilename: ?string,
     *     timestamp: int
     * }  $msgData
     */
    public function handle(Conversation $conversation, array $msgData, bool $fromMe): ?Message
    {
        $waMessageId = $msgData['waMessageId'];

        // Idempotent: skip if already stored
        $exists = Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('wa_message_id', $waMessageId)
            ->exists();

        if ($exists) {
            return null;
        }

        $direction = $fromMe ? MessageDirection::Out : MessageDirection::In;

        $message = Message::create([
            'conversation_id'  => $conversation->id,
            'tenant_id'        => $conversation->tenant_id,
            'direction'        => $direction,
            'body'             => $msgData['body'] ?? null,
            'media_url'        => $msgData['mediaUrl'] ?? null,
            'media_type'       => $msgData['mediaType'] ?? null,
            'media_mime_type'  => $msgData['mediaMimeType'] ?? null,
            'media_filename'   => $msgData['mediaFilename'] ?? null,
            'status'           => $fromMe ? MessageStatus::Sent : MessageStatus::Delivered,
            'wa_message_id'    => $waMessageId,
            'is_automated'     => false,
            'created_at'       => now()->setTimestamp($msgData['timestamp']),
            'updated_at'       => now(),
        ]);

        // Update conversation counters
        $updates = [
            'last_message_at' => $message->created_at,
        ];
        $updates['message_count'] = $conversation->message_count + 1;

        // Set Dt1 first_response_at: first outbound message on a conversation that started with inbound
        if ($fromMe && $conversation->first_response_at === null && $conversation->first_message_at !== null) {
            $updates['first_response_at'] = $message->created_at;
        }

        $conversation->update($updates);

        return $message;
    }
}
