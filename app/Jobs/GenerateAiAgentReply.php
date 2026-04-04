<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAiAgentReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 15;

    public function __construct(
        private readonly string $conversationId,
        private readonly string $agentId,
        private readonly string $triggeringMessageId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(AiAgentService $service): void
    {
        $conversation = Conversation::withoutGlobalScope('tenant')->find($this->conversationId);
        $agent = AiAgent::withoutGlobalScope('tenant')->find($this->agentId);
        $triggeringMessage = Message::withoutGlobalScope('tenant')->find($this->triggeringMessageId);

        if (! $conversation || ! $agent || ! $triggeringMessage) {
            return;
        }

        if ($conversation->tenant_id !== $agent->tenant_id || $conversation->tenant_id !== $triggeringMessage->tenant_id) {
            Log::warning('GenerateAiAgentReply: tenant mismatch', [
                'conversation_id' => $conversation->id,
                'agent_id' => $agent->id,
                'triggering_message_id' => $triggeringMessage->id,
            ]);
            return;
        }

        $service->generateAndSend($conversation, $agent, $triggeringMessage);
    }
}
