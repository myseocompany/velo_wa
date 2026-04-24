<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Jobs\DispatchOutboundWebhook;
use App\Events\MessageReceived;
use App\Models\Message;
use App\Services\WhatsAppClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 15;

    public function __construct(
        private readonly Message $message,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsAppClientService $client): void
    {
        try {
            $message      = $this->message->load(['conversation.whatsappLine', 'conversation.contact.tenant']);
            $conversation = $message->conversation;
            $contact      = $conversation->contact;
            $tenant       = $contact?->tenant;

            if (! $contact || ! $tenant) {
                Log::warning('SendWhatsAppMessage: contact or tenant not found (soft-deleted?)', [
                    'message_id'      => $message->id,
                    'conversation_id' => $conversation?->id,
                ]);
                $this->updateStatus(MessageStatus::Failed, 'Contact or tenant not found');
                return;
            }

            $instanceName = $this->resolveInstanceName($message, $conversation, $tenant);
            if ($instanceName === null) {
                return;
            }

            // Use the full wa_id for @lid contacts (WhatsApp privacy mode).
            // For regular contacts strip the @s.whatsapp.net suffix so Evolution
            // API accepts just the phone number (e.g. "573004410097").
            $waId  = $contact->wa_id ?? '';
            $phone = str_ends_with($waId, '@lid')
                ? $waId                                        // keep full JID for LID contacts
                : ($contact->phone ?? preg_replace('/@.*/', '', $waId));

            if ($message->hasMedia() && $message->media_url) {
                // For stored media use base64 payload; for external URLs pass URL directly.
                $mediaPayload = $this->resolveStoredPath($message->media_url);

                if (! $this->isExternalUrl($mediaPayload)) {
                    $diskName = (string) config('filesystems.media_disk', config('filesystems.default', 'local'));
                    $binary   = Storage::disk($diskName)->get($mediaPayload);
                    $mediaPayload = base64_encode($binary);
                }

                $result = $client->sendMedia(
                    $instanceName,
                    $phone,
                    $message->media_type,
                    $mediaPayload,
                    $message->body,
                );
            } else {
                $result = $client->sendText($instanceName, $phone, $message->body ?? '');
            }

            $waMessageId = $result['key']['id'] ?? null;

            $message->update([
                'status'        => MessageStatus::Sent,
                'wa_message_id' => $waMessageId,
            ]);

            $freshMessage = $message->fresh();
            broadcast(new MessageReceived($freshMessage));

            if ($tenant->webhook_url) {
                DispatchOutboundWebhook::dispatch(
                    $tenant->webhook_url,
                    'message.sent',
                    DispatchOutboundWebhook::payloadFromMessage($freshMessage, 'message.sent'),
                    $tenant->webhook_secret,
                );
            }
        } catch (Throwable $e) {
            Log::error('SendWhatsAppMessage: failed to send', [
                'message_id' => $this->message->id,
                'error'      => $e->getMessage(),
            ]);

            // On final attempt, mark as failed and notify the frontend; otherwise retry
            if ($this->attempts() >= $this->tries) {
                $this->updateStatus(MessageStatus::Failed, $e->getMessage());
                broadcast(new MessageReceived($this->message->fresh()));
            } else {
                throw $e;
            }
        }
    }

    private function updateStatus(MessageStatus $status, string $errorMessage = ''): void
    {
        $updates = ['status' => $status];

        if ($errorMessage) {
            $updates['error_message'] = $errorMessage;
        }

        $this->message->update($updates);
    }

    private function resolveInstanceName(Message $message, $conversation, $tenant): ?string
    {
        $line = $conversation->whatsappLine ?: $tenant->defaultWhatsAppLine()->first();

        if ($line) {
            if (! $line->isConnected() || ! $line->instance_id) {
                Log::warning('SendWhatsAppMessage: WA line disconnected', [
                    'message_id' => $message->id,
                    'tenant_id' => $message->tenant_id,
                    'line_id' => $line->id,
                ]);
                $this->updateStatus(MessageStatus::Failed, 'Line disconnected');
                return null;
            }

            return $line->instance_id;
        }

        if (! $tenant->wa_instance_id) {
            Log::warning('SendWhatsAppMessage: tenant has no WA instance', [
                'message_id' => $message->id,
                'tenant_id' => $message->tenant_id,
            ]);
            $this->updateStatus(MessageStatus::Failed, 'Tenant has no WhatsApp instance configured');
            return null;
        }

        return $tenant->wa_instance_id;
    }

    /**
     * Extract the S3 path from either a stored path or a legacy full URL.
     * New records store "tenantId/media/YYYY-MM/file.ext".
     * Legacy records may store "http://host/bucket/tenantId/media/...".
     */
    private function resolveStoredPath(string $mediaUrl): string
    {
        if (! str_starts_with($mediaUrl, 'http')) {
            return $mediaUrl;
        }

        $publicPath = parse_url($mediaUrl, PHP_URL_PATH);
        if (is_string($publicPath) && preg_match('~/storage/(.+)$~', $publicPath, $m)) {
            return $m[1];
        }

        $bucket = config('filesystems.disks.s3.bucket', 'velo-media');
        if (preg_match('~/' . preg_quote($bucket, '~') . '/(.+)~', $mediaUrl, $m)) {
            return $m[1];
        }

        return $mediaUrl;
    }


    private function isExternalUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
