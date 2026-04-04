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
        [$provider, $model] = $this->resolveProviderAndModel($agent->llm_model);
        $apiKey = $this->providerApiKey($provider);

        if ($apiKey === '') {
            Log::warning('AiAgent: missing provider API key', [
                'agent_id' => $agent->id,
                'provider' => $provider,
            ]);

            return null;
        }

        return match ($provider) {
            'anthropic' => $this->generateAnthropicReply($apiKey, $model, $prompt, $history, $agent->id),
            'openai' => $this->generateOpenAiReply($apiKey, $model, $prompt, $history, $agent->id),
            'gemini' => $this->generateGeminiReply($apiKey, $model, $prompt, $history, $agent->id),
            default => throw new \RuntimeException('Unsupported provider: '.$provider),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveProviderAndModel(string $llmModel): array
    {
        $value = trim($llmModel);

        if (str_contains($value, ':')) {
            [$provider, $model] = explode(':', $value, 2);

            return [strtolower(trim($provider)), trim($model)];
        }

        $lower = strtolower($value);

        if (str_starts_with($lower, 'claude')) {
            return ['anthropic', $value];
        }

        if (str_starts_with($lower, 'gpt') || str_starts_with($lower, 'o')) {
            return ['openai', $value];
        }

        if (str_starts_with($lower, 'gemini')) {
            return ['gemini', $value];
        }

        return ['anthropic', $value];
    }

    private function providerApiKey(string $provider): string
    {
        return match ($provider) {
            'anthropic' => (string) config('services.anthropic.key', ''),
            'openai' => (string) config('services.openai.key', ''),
            'gemini' => (string) config('services.gemini.key', ''),
            default => '',
        };
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function generateAnthropicReply(string $apiKey, string $model, string $prompt, array $history, string $agentId): ?string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(40)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $prompt,
            'messages' => $history,
        ]);

        if ($response->failed()) {
            $this->throwProviderRequestException('anthropic', $agentId, $response->status(), $response->body());
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

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function generateOpenAiReply(string $apiKey, string $model, string $prompt, array $history, string $agentId): ?string
    {
        $response = Http::withToken($apiKey)
            ->timeout(40)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ...$history,
                ],
                'max_tokens' => 1024,
            ]);

        if ($response->failed()) {
            $this->throwProviderRequestException('openai', $agentId, $response->status(), $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (is_string($content)) {
            $text = trim($content);

            return $text !== '' ? $text : null;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }

            $text = trim(implode("\n", $parts));

            return $text !== '' ? $text : null;
        }

        return null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function generateGeminiReply(string $apiKey, string $model, string $prompt, array $history, string $agentId): ?string
    {
        $contents = array_map(static function (array $item): array {
            return [
                'role' => $item['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $item['content']],
                ],
            ];
        }, $history);

        $response = Http::timeout(40)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.$apiKey, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if ($response->failed()) {
            $this->throwProviderRequestException('gemini', $agentId, $response->status(), $response->body());
        }

        $parts = $response->json('candidates.0.content.parts');
        if (! is_array($parts)) {
            return null;
        }

        $texts = [];
        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $texts[] = $part['text'];
            }
        }

        $text = trim(implode("\n", $texts));

        return $text !== '' ? $text : null;
    }

    private function throwProviderRequestException(string $provider, string $agentId, int $status, string $body): void
    {
        Log::error('AiAgent: provider request failed', [
            'provider' => $provider,
            'agent_id' => $agentId,
            'status' => $status,
            'body' => $body,
        ]);

        throw new \RuntimeException(ucfirst($provider).' API request failed: '.$status);
    }
}
