<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function index(Conversation $conversation): AnonymousResourceCollection
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->cursorPaginate(50);

        return MessageResource::collection($messages);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): MessageResource
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => MessageDirection::Out,
            'body'            => $request->input('body'),
            'status'          => MessageStatus::Pending,
            'sent_by'         => $request->user()->id,
            'is_automated'    => false,
        ]);

        // Update conversation counters synchronously so the list reorders at once
        $conversation->increment('message_count');
        $conversation->update(['last_message_at' => $message->created_at]);

        broadcast(new ConversationUpdated($conversation->fresh()));

        SendWhatsAppMessage::dispatch($message);

        return new MessageResource($message);
    }
}
