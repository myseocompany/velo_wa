<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\WhatsApp\CreateOrUpdateContact;
use App\Actions\WhatsApp\CreateOrUpdateConversation;
use App\Actions\WhatsApp\StoreInboundMessage;
use App\Enums\AutomationTriggerType;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Jobs\DispatchOutboundWebhook;
use App\Jobs\DownloadMessageMedia;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\AssignmentEngineService;
use App\Services\AutomationEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleInboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly array $payload,
        private readonly string $tenantId,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(
        CreateOrUpdateContact $createContact,
        CreateOrUpdateConversation $createConversation,
        StoreInboundMessage $storeMessage,
        AssignmentEngineService $assignmentEngine,
        AutomationEngineService $automationEngine,
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::warning('HandleInboundMessage: tenant not found', ['tenant_id' => $this->tenantId]);
            return;
        }

        $messages = $this->payload['data'] ?? [];

        // Normalise: single message vs array
        if (isset($messages['key'])) {
            $messages = [$messages];
        }

        foreach ($messages as $msgPayload) {
            $this->processMessage($tenant, $msgPayload, $createContact, $createConversation, $storeMessage, $assignmentEngine, $automationEngine);
        }
    }

    private function processMessage(
        Tenant $tenant,
        array $msgPayload,
        CreateOrUpdateContact $createContact,
        CreateOrUpdateConversation $createConversation,
        StoreInboundMessage $storeMessage,
        AssignmentEngineService $assignmentEngine,
        AutomationEngineService $automationEngine,
    ): void {
        $key       = $msgPayload['key'] ?? [];
        $fromMe    = (bool) ($key['fromMe'] ?? false);
        $remoteJid = $key['remoteJid'] ?? '';

        // Skip group messages and status broadcasts
        if (str_contains($remoteJid, '@g.us') || $remoteJid === 'status@broadcast') {
            return;
        }

        // When fromMe=true, pushName is OUR name, not the contact's
        $waData = [
            'remoteJid'     => $remoteJid,
            'pushName'      => $fromMe ? null : ($msgPayload['pushName'] ?? null),
            'profilePicUrl' => null,
        ];

        $contact      = $createContact->handle($tenant, $waData);
        $isNewConversation = false;
        $conversation = $createConversation->handle($contact, $isNewConversation);

        $msgData = $this->extractMessageData($key, $msgPayload);

        if (! $msgData['waMessageId']) {
            return;
        }

        $message = $storeMessage->handle($conversation, $msgData, $fromMe);

        if ($message) {
            // Auto-assign new conversations if no agent assigned yet
            if ($isNewConversation) {
                $assignmentEngine->autoAssign($conversation);
                $conversation->refresh();
            }

            // Fire automations (only for inbound messages)
            if (! $fromMe) {
                if ($isNewConversation) {
                    $automationEngine->dispatch($conversation, AutomationTriggerType::NewConversation, $message);
                    $automationEngine->dispatch($conversation, AutomationTriggerType::OutsideHours, $message);
                }
                $automationEngine->dispatch($conversation, AutomationTriggerType::Keyword, $message);
            }

            // Dispatch media download if message has media
            if ($msgData['mediaType'] && $tenant->wa_instance_id) {
                DownloadMessageMedia::dispatch(
                    $message->id,
                    $tenant->wa_instance_id,
                    $key,
                    $msgData['mediaType'],
                    $msgData['mediaMimeType'],
                    $msgData['mediaFilename'],
                );
            }

            broadcast(new MessageReceived($message));
            broadcast(new ConversationUpdated($conversation->fresh()));

            if ($tenant->webhook_url) {
                $event = $fromMe ? 'message.sent' : 'message.received';
                DispatchOutboundWebhook::dispatch(
                    $tenant->webhook_url,
                    $event,
                    DispatchOutboundWebhook::payloadFromMessage($message, $event),
                    $tenant->webhook_secret,
                );
            }
        }
    }

    private function extractMessageData(array $key, array $payload): array
    {
        $message   = $payload['message'] ?? [];
        $timestamp = (int) ($payload['messageTimestamp'] ?? now()->timestamp);

        // Text
        $body = $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? null;

        // Media
        $mediaType     = null;
        $mediaMimeType = null;
        $mediaUrl      = null;
        $mediaFilename = null;

        if (isset($message['imageMessage'])) {
            $mediaType     = 'image';
            $mediaMimeType = $message['imageMessage']['mimetype'] ?? null;
            $body          ??= $message['imageMessage']['caption'] ?? null;
        } elseif (isset($message['videoMessage'])) {
            $mediaType     = 'video';
            $mediaMimeType = $message['videoMessage']['mimetype'] ?? null;
            $body          ??= $message['videoMessage']['caption'] ?? null;
        } elseif (isset($message['audioMessage'])) {
            $mediaType     = 'audio';
            $mediaMimeType = $message['audioMessage']['mimetype'] ?? null;
        } elseif (isset($message['documentMessage'])) {
            $mediaType     = 'document';
            $mediaMimeType = $message['documentMessage']['mimetype'] ?? null;
            $mediaFilename = $message['documentMessage']['fileName'] ?? null;
            $body          ??= $message['documentMessage']['title'] ?? null;
        } elseif (isset($message['stickerMessage'])) {
            $mediaType     = 'sticker';
            $mediaMimeType = $message['stickerMessage']['mimetype'] ?? null;
        }

        return [
            'waMessageId'   => $key['id'] ?? null,
            'body'          => $body,
            'mediaUrl'      => $mediaUrl,
            'mediaType'     => $mediaType,
            'mediaMimeType' => $mediaMimeType,
            'mediaFilename' => $mediaFilename,
            'timestamp'     => $timestamp,
        ];
    }
}
