<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\ConversationUpdated;
use App\Jobs\SendWhatsAppMessage;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    public function agentForTenant(string $tenantId): ?AiAgent
    {
        return AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function shouldRespond(Conversation $conversation, AiAgent $agent): bool
    {
        if ($conversation->status !== ConversationStatus::Open) {
            return false;
        }

        if ($conversation->ai_agent_enabled !== null) {
            return (bool) $conversation->ai_agent_enabled;
        }

        return (bool) $agent->is_enabled;
    }

    public function generateAndSend(Conversation $conversation, AiAgent $agent, Message $triggeringMessage): void
    {
        if (! $this->shouldRespond($conversation, $agent)) {
            return;
        }

        $apiKey = (string) config('services.anthropic.key', '');
        if ($apiKey === '') {
            Log::warning('AiAgent: missing ANTHROPIC_API_KEY', [
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
            ]);
            return;
        }

        $prompt = trim((string) ($agent->system_prompt ?? ''));
        if ($prompt === '') {
            Log::warning('AiAgent: empty system prompt', [
                'tenant_id' => $conversation->tenant_id,
                'agent_id' => $agent->id,
            ]);
            return;
        }

        $history = $this->buildContextMessages($conversation, (int) $agent->context_messages);
        if ($history === []) {
            return;
        }

        $reply = $this->generateReply($agent, $prompt, $history);
        if ($reply === null || trim($reply) === '') {
            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $conversation->tenant_id,
            'direction' => MessageDirection::Out,
            'body' => trim($reply),
            'status' => MessageStatus::Pending,
            'sent_by' => null,
            'is_automated' => true,
        ]);

        $updates = ['last_message_at' => $message->created_at];
        if ($conversation->first_response_at === null && $conversation->first_message_at !== null) {
            $updates['first_response_at'] = $message->created_at;
        }

        $conversation->increment('message_count', 1, $updates);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContextMessages(Conversation $conversation, int $contextMessages): array
    {
        $limit = max(3, min($contextMessages, 50));

        $messages = Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $history = [];
        foreach ($messages as $msg) {
            $text = $msg->body;
            if (! $text && $msg->media_type) {
                $text = '[Media: '.$msg->media_type.']';
            }

            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }

            $history[] = [
                'role' => $msg->direction === MessageDirection::In ? 'user' : 'assistant',
                'content' => $text,
            ];
        }

        return $history;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generateReply(AiAgent $agent, string $prompt, array $history): ?string
    {
        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.key', ''),
            'anthropic-version' => '2023-06-01',
        ])->timeout(40)->post('https://api.anthropic.com/v1/messages', [
            'model' => $agent->llm_model,
            'max_tokens' => 1024,
            'system' => $prompt,
            'messages' => $history,
        ]);

        if ($response->failed()) {
            Log::error('AiAgent: anthropic request failed', [
                'agent_id' => $agent->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Anthropic API request failed: '.$response->status());
        }

        $content = $response->json('content');
        if (! is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                $parts[] = $item['text'];
            }
        }

        $text = trim(implode("\n", $parts));

        return $text !== '' ? $text : null;
    }
}
