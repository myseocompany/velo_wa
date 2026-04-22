<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversations\CalculateDt1;
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

        $this->updateConversationAfterOutbound($conversation, $message);

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
        $diskName  = (string) config('filesystems.media_disk', config('filesystems.default', 'local'));

        Storage::disk($diskName)->putFileAs(
            "{$tenantId}/media/{$month}",
            $file,
            $filename,
            ['ContentType' => $mimeType]
        );

        $message = Message::create([
            'conversation_id'  => $conversation->id,
            'tenant_id'        => $conversation->tenant_id,
            'direction'        => MessageDirection::Out,
            'body'             => $request->input('body'),
            'media_url'        => $path,
            'media_type'       => $mediaType,
            'media_mime_type'  => $mimeType,
            'media_filename'   => $filename,
            'status'           => MessageStatus::Pending,
            'sent_by'          => $request->user()->id,
            'is_automated'     => false,
        ]);

        $this->updateConversationAfterOutbound($conversation, $message);

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

        $this->updateConversationAfterOutbound($conversation, $message);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);

        return new MessageResource($message);
    }

    /**
     * Update conversation counters after any outbound message (text, media, quick reply).
     *
     * Sets first_response_at exactly once — on the first outbound message sent by a human
     * or automation through this application. This is the authoritative write path for Dt1:
     * the webhook echo from Evolution API arrives after wa_message_id is already set, so
     * StoreInboundMessage skips that message and never has a chance to set it.
     */
    private function updateConversationAfterOutbound(Conversation $conversation, Message $message): void
    {
        $updates = ['last_message_at' => $message->created_at];

        if ($conversation->first_response_at === null && $conversation->first_message_at !== null) {
            $updates['first_response_at'] = $message->created_at;
        }

        $conversation->increment('message_count', 1, $updates);
        $conversation->refresh();

        // Calculate DT1 (business minutes to first human response) if applicable.
        // is_automated=false is already guaranteed by all three callers (store/storeMedia/storeQuickReply).
        // We still need to skip auto-reply quick replies to avoid polluting the metric.
        if (! $message->is_automated && ! $this->isAutoReplyBody($message)) {
            (new CalculateDt1())->handle($conversation, $message);
        }
    }

    /**
     * Returns true if the message body matches the tenant's configured auto-reply quick reply.
     */
    private function isAutoReplyBody(Message $message): bool
    {
        return QuickReply::where('tenant_id', $message->tenant_id)
            ->where('is_auto_reply', true)
            ->whereRaw('LOWER(TRIM(body)) = LOWER(TRIM(?))', [$message->body ?? ''])
            ->exists();
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
