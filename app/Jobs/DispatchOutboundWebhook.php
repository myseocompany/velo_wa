<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOutboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 30;

    public function __construct(
        private readonly string $webhookUrl,
        private readonly string $event,
        private readonly array $payload,
        private readonly ?string $secret = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $request = Http::timeout(10)
            ->withHeaders($this->buildHeaders($body));

        $response = $request->post($this->webhookUrl, $this->payload);

        if ($response->failed()) {
            Log::warning('DispatchOutboundWebhook: non-2xx response', [
                'url'    => $this->webhookUrl,
                'event'  => $this->event,
                'status' => $response->status(),
            ]);

            // Retry on server errors (5xx); give up on client errors (4xx)
            if ($response->serverError()) {
                throw new \RuntimeException("Webhook endpoint returned {$response->status()}");
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('DispatchOutboundWebhook: all retries exhausted', [
            'url'   => $this->webhookUrl,
            'event' => $this->event,
            'error' => $e->getMessage(),
        ]);
    }

    private function buildHeaders(string $body): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-AriCRM-Event' => $this->event,
        ];

        if ($this->secret) {
            $signature = 'sha256=' . hash_hmac('sha256', $body, $this->secret);
            $headers['X-AriCRM-Signature'] = $signature;
        }

        return $headers;
    }

    /**
     * Build a normalized payload from a Message model.
     */
    public static function payloadFromMessage(Message $message, string $event): array
    {
        $message->loadMissing(['conversation.contact']);
        $conversation = $message->conversation;
        $contact      = $conversation?->contact;

        return [
            'event'        => $event,
            'message_id'   => $message->id,
            'wa_message_id' => $message->wa_message_id,
            'direction'    => $message->direction?->value,
            'body'         => $message->body,
            'media_type'   => $message->media_type,
            'media_url'    => $message->media_url,
            'status'       => $message->status?->value,
            'sent_at'      => $message->created_at?->toIso8601String(),
            'conversation' => [
                'id'     => $conversation?->id,
                'status' => $conversation?->status?->value,
            ],
            'contact'      => [
                'id'        => $contact?->id,
                'name'      => $contact?->name,
                'phone'     => $contact?->phone,
                'wa_id'     => $contact?->wa_id,
            ],
            'tenant_id'    => $message->tenant_id,
        ];
    }
}
