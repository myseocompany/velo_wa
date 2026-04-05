<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Automation;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAutomationSequenceStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param array{type:string,body?:string|null,media_url?:string|null,delay_seconds?:int|null} $step
     */
    public function __construct(
        private readonly string $automationId,
        private readonly string $conversationId,
        private readonly array $step,
        private readonly string $sequenceStartedAt,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(): void
    {
        $automation = Automation::withoutGlobalScope('tenant')->find($this->automationId);
        $conversation = Conversation::withoutGlobalScope('tenant')
            ->with('contact')
            ->find($this->conversationId);

        if (! $automation || ! $conversation) {
            return;
        }

        if ($conversation->status !== ConversationStatus::Open) {
            return;
        }

        $startedAt = CarbonImmutable::parse($this->sequenceStartedAt);
        $hasInboundAfterStart = Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::In)
            ->where('created_at', '>', $startedAt)
            ->exists();

        if ($hasInboundAfterStart) {
            return;
        }

        $type = (string) ($this->step['type'] ?? 'text');
        $bodyTemplate = trim((string) ($this->step['body'] ?? ''));
        $body = $this->renderTemplate($bodyTemplate, $conversation);

        $payload = [
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Out,
            'status' => MessageStatus::Pending,
            'is_automated' => true,
            'body' => $body !== '' ? $body : null,
        ];

        if ($type !== 'text') {
            $mediaUrl = trim((string) ($this->step['media_url'] ?? ''));
            if ($mediaUrl === '') {
                return;
            }

            $payload['media_type'] = $type;
            $payload['media_url'] = $mediaUrl;
        }

        $message = Message::create($payload);

        $updates = ['last_message_at' => $message->created_at];
        if ($conversation->first_response_at === null && $conversation->first_message_at !== null) {
            $updates['first_response_at'] = $message->created_at;
        }

        $conversation->increment('message_count', 1, $updates);

        SendWhatsAppMessage::dispatch($message);
    }

    private function renderTemplate(string $template, Conversation $conversation): string
    {
        $contact = $conversation->contact;

        return strtr($template, [
            '{{name}}' => $contact?->name ?? $contact?->push_name ?? 'Cliente',
            '{{phone}}' => $contact?->phone ?? '',
            '{{company}}' => $contact?->company ?? '',
        ]);
    }
}
