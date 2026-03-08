<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendMediaMessageRequest;
use App\Http\Requests\Api\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\QuickReply;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Conversation $conversation): AnonymousResourceCollection
    {
        // DESC order: newest first. Frontend reverses for display and uses
        // the `next` cursor to load older messages on scroll-up.
        $messages = $conversation->messages()
            ->reorder()
            ->orderByDesc('created_at')
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

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);

        return new MessageResource($message);
    }

    public function storeMedia(SendMediaMessageRequest $request, Conversation $conversation): MessageResource
    {
        $file      = $request->file('media');
        $mimeType  = $file->getMimeType() ?? 'application/octet-stream';
        $mediaType = $this->detectMediaType($mimeType);
        $filename  = $file->getClientOriginalName() ?: (Str::uuid() . '.' . $file->getClientOriginalExtension());
        $tenantId  = $conversation->tenant_id;
        $month     = now()->format('Y-m');
        $path      = "{$tenantId}/media/{$month}/{$filename}";

        Storage::disk('s3')->putFileAs(
            "{$tenantId}/media/{$month}",
            $file,
            $filename,
            ['ContentType' => $mimeType]
        );

        $mediaUrl = Storage::disk('s3')->url($path);

        $message = Message::create([
            'conversation_id'  => $conversation->id,
            'tenant_id'        => $conversation->tenant_id,
            'direction'        => MessageDirection::Out,
            'body'             => $request->input('body'),
            'media_url'        => $mediaUrl,
            'media_type'       => $mediaType,
            'media_mime_type'  => $mimeType,
            'media_filename'   => $filename,
            'status'           => MessageStatus::Pending,
            'sent_by'          => $request->user()->id,
            'is_automated'     => false,
        ]);

        $conversation->increment('message_count');
        $conversation->update(['last_message_at' => $message->created_at]);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);

        return new MessageResource($message);
    }

    public function storeQuickReply(Conversation $conversation, QuickReply $quickReply): MessageResource
    {
        $contact = $conversation->contact;
        $body    = $quickReply->interpolate([
            'name'    => $contact?->displayName() ?? '',
            'phone'   => $contact?->phone ?? '',
            'company' => $contact?->company ?? '',
        ]);

        $quickReply->increment('usage_count');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => MessageDirection::Out,
            'body'            => $body,
            'status'          => MessageStatus::Pending,
            'sent_by'         => request()->user()->id,
            'is_automated'    => false,
        ]);

        $conversation->increment('message_count');
        $conversation->update(['last_message_at' => $message->created_at]);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);

        return new MessageResource($message);
    }

    private function detectMediaType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default                               => 'document',
        };
    }
}
