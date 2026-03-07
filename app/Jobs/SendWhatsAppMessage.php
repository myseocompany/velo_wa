<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Events\MessageReceived;
use App\Models\Message;
use App\Services\WhatsAppClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        $message      = $this->message->load(['conversation.contact.tenant']);
        $conversation = $message->conversation;
        $contact      = $conversation->contact;
        $tenant       = $contact->tenant;

        if (! $tenant?->wa_instance_id) {
            Log::warning('SendWhatsAppMessage: tenant has no WA instance', [
                'message_id' => $message->id,
                'tenant_id'  => $message->tenant_id,
            ]);
            $this->updateStatus(MessageStatus::Failed, 'Tenant has no WhatsApp instance configured');
            return;
        }

        $instanceName = WhatsAppClientService::instanceName($tenant->id);
        $phone        = $contact->phone;

        try {
            $result = $client->sendText($instanceName, $phone, $message->body ?? '');

            $waMessageId = $result['key']['id'] ?? null;

            $message->update([
                'status'        => MessageStatus::Sent,
                'wa_message_id' => $waMessageId,
            ]);

            broadcast(new MessageReceived($message->fresh()));
        } catch (Throwable $e) {
            Log::error('SendWhatsAppMessage: failed to send', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            // On final attempt, mark as failed; otherwise let the queue retry
            if ($this->attempts() >= $this->tries) {
                $this->updateStatus(MessageStatus::Failed, $e->getMessage());
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
}
